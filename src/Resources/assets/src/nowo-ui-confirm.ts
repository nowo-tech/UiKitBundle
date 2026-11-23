/**
 * Accessible confirm dialog via native <dialog data-nowo-ui-confirm>.
 * Open: [data-nowo-confirm-open][data-nowo-confirm-target="id"]
 * Close: [data-nowo-confirm-close] or Escape / cancel.
 * Host owns destructive POST + CSRF inside the dialog body/footer.
 *
 * Type-to-confirm (2.0, View\Confirm::expectedValue): an `input[data-nowo-confirm-expected="VALUE"]` inside the
 * dialog (or inside a `[data-nowo-confirm-scope]` element, e.g. the full-page confirm) keeps every
 * `[data-nowo-confirm-submit]` button of that scope disabled until the input value is exactly VALUE. Opening a
 * dialog clears the input and focuses it. No inline handlers (CSP).
 *
 * Missing peer warning (2.22): a form rendered for the `confirm-submit` Stimulus peer (row actions with
 * `row_actions_confirm` / `confirm_mode` `native` | `dialog`) that submits while no peer connected to it is reported
 * once per form with console.warn.
 *
 * Fail closed (2.23, audit CK L1): such a form submitting while no peer is connected (peer not registered, host bundle
 * not loaded, or a submit before Stimulus connected) asks with `window.confirm(message)` instead and cancels the submit
 * when the user declines — it never submits unconfirmed. Once the peer is connected it confirms and this stays out.
 *
 * Runtime question (2.34): `window.nowoUiAskConfirm(message, { danger, title, confirm, cancel, closeLabel, … })` is
 * `askConfirm()` of `stimulus-peers/ask_confirm.ts` for code without the peers: a `Promise<boolean>`, a kit `<dialog>`
 * built when asked, for actions whose target is only known at runtime.
 *
 * Safe first focus (2.61): a dialog whose confirm button is destructive (`.nowo-ui-btn--danger` submit, the danger
 * `View\Confirm`) and that has no type-to-confirm input opens on its Cancel button (the footer's
 * [data-nowo-confirm-close]), not on the ✕ or the danger action. The markup is unchanged. A host whose theme
 * replaces the danger button classes sets data-nowo-ui-confirm-cancel-first="true" on the dialog (the `confirm` slot
 * attributes); "false" turns it off (audit XJ-7).
 *
 * Shared dialog (2.68, `table` partial with confirm_shared): one `dialog[data-nowo-confirm-shared]` per table; the
 * row toggle carries the content (data-nowo-confirm-title / -message / -message-in-header / -confirm-label /
 * -cancel-label / -variant / -size / -expected-value / -expected-pattern / -expected-label) and the id of its hidden
 * row POST form (data-nowo-confirm-form). Opening fills the dialog (textContent only), shows the confirm button of the
 * variant and binds it to that form with `form="…"`, so Confirm submits the row's own form (target + CSRF). A toggle
 * without a form (disabled action) opens nothing, and so does a shared dialog opened without such a toggle
 * (`nowoUiOpenConfirm(id)` from script); closing it drops the binding.
 *
 * Stimulus runtime (2.69): a dialog claimed by the `confirm-shared` peer (data-nowo-ui-runtime-owner, set by the peer
 * when it connects; nowo-ui-runtime-owner.ts) is left to the peer — no open, close, input or close-event handling here —
 * so this script can stay loaded next to the peers without handling a click twice. A second copy of this script binds
 * nothing. The shared logic lives in nowo-ui-confirm-core.ts, used by both.
 *
 * Focus return (2.52.2, WCAG 2.4.3): closing a confirm dialog (Escape, Cancel, Confirm, ×, backdrop) focuses the
 * element that opened it; when that opener cannot take focus (a hidden trigger a page-actions menu item forwarded its
 * click to), the menu item, else the menu's toggle — never a hidden element (nowo-ui-focus-return.ts).
 */

import { bindFocusReturn, returnFocus } from './nowo-ui-focus-return';
import { bindMotion, closeDialogWithEffect } from './nowo-ui-motion-core';
import { EXPECTED, openConfirmDialog, syncExpected, unbindSharedConfirm } from './nowo-ui-confirm-core';
import { markBound, ownedByStimulus } from './nowo-ui-runtime-owner';
import { askConfirm, confirmTexts, nativeQuestion, type ConfirmOptions } from '../stimulus-peers/ask_confirm';

export { EXPECTED, fillSharedConfirm, openConfirmDialog, resetExpected, safeCancel, SCOPE, syncExpected, unbindSharedConfirm } from './nowo-ui-confirm-core';

declare global {
  interface Window {
    nowoUiOpenConfirm?: (id: string | HTMLDialogElement | null, opener?: Element | null) => void;
    nowoUiCloseConfirm?: (el: HTMLDialogElement | null) => void;
    nowoUiAskConfirm?: (message: string, options?: ConfirmOptions) => Promise<boolean>;
  }
}

function resolveDialog(id: string | HTMLDialogElement | null): HTMLDialogElement | null {
  if (id === null) {
    return null;
  }
  if (typeof id === 'string') {
    const el = document.getElementById(id);
    return el instanceof HTMLDialogElement ? el : null;
  }
  return id;
}

/** Opens a confirm dialog; `opener` (2.52.2) is where focus returns on close (see the module comment). */
export function openConfirm(id: string | HTMLDialogElement | null, opener: Element | null = null): void {
  const dialog = resolveDialog(id);
  if (dialog) {
    openConfirmDialog(dialog, opener);
  }
}

export function closeConfirm(el: HTMLDialogElement | null): void {
  if (!el || typeof el.close !== 'function') {
    return;
  }
  if (el.open) {
    closeDialogWithEffect(el);
  }
}

function onDocumentClick(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) {
    return;
  }

  const openBtn = target.closest('[data-nowo-confirm-open]');
  if (openBtn) {
    const targetId = openBtn.getAttribute('data-nowo-confirm-target');
    // 2.69: a dialog claimed by the `confirm-shared` Stimulus peer opens through the peer only.
    if (targetId && !ownedByStimulus(document.getElementById(targetId))) {
      event.preventDefault();
      openConfirm(targetId, openBtn);
    }
    return;
  }

  const closeBtn = target.closest('[data-nowo-confirm-close]');
  if (closeBtn) {
    const dialog = closeBtn.closest('dialog[data-nowo-ui-confirm]');
    if (dialog instanceof HTMLDialogElement && !ownedByStimulus(dialog)) {
      event.preventDefault();
      closeConfirm(dialog);
    }
  }
}

/** Locks the confirm buttons of every type-to-confirm scope under root (page load: the full-page confirm). */
export function syncAllExpected(root: ParentNode): void {
  root.querySelectorAll(EXPECTED).forEach((input) => {
    if (input instanceof HTMLInputElement) {
      syncExpected(input);
    }
  });
}

/** A dialog closed (native `close`, captured): focus back to its opener or the nearest visible fallback. */
function onDialogClose(event: Event): void {
  const target = event.target;
  if (target instanceof HTMLDialogElement && !ownedByStimulus(target)) {
    if (target.hasAttribute('data-nowo-confirm-shared')) {
      unbindSharedConfirm(target);
    }
    returnFocus(target);
  }
}

function onDocumentInput(event: Event): void {
  const target = event.target;
  if (target instanceof HTMLInputElement && target.matches(EXPECTED) && !ownedByStimulus(target.closest('dialog'))) {
    syncExpected(target);
  }
}

/** Same key as CONFIRM_SUBMIT_PEER_MARK of the confirm-submit peer (Symbol.for is shared across bundles). */
const PEER_MARK = Symbol.for('nowo-ui.confirm-submit');
const PEER_FORM = 'form[data-confirm-submit-message-value]';
const warnedForms = new WeakSet<HTMLFormElement>();

/** Stimulus Boolean parsing of `data-confirm-submit-blocked-value` (audit CS L2): present and not "0" / "false". */
export function isBlocked(value: string | null): boolean {
  return value !== null && !(value === '0' || value.toLowerCase() === 'false');
}

/**
 * A confirm-submit form that submits without the Stimulus peer connected to it: warns (once per form) and asks with
 * `window.confirm()` (every submit), cancelling the submit when declined (2.23: fail closed). An empty message or
 * `data-confirm-submit-blocked-value="true"` mirror the peer (no question / always cancelled).
 */
export function warnMissingConfirmPeer(event: Event): void {
  const form = event.target;
  if (!(form instanceof HTMLFormElement) || !form.matches(PEER_FORM)) {
    return;
  }
  if ((form as unknown as Record<symbol, unknown>)[PEER_MARK] === true) {
    return;
  }
  if (isBlocked(form.getAttribute('data-confirm-submit-blocked-value'))) {
    event.preventDefault();
  } else {
    // 2.69: the title (data-confirm-submit-title-value / data-confirm-title) is asked too, above the message.
    const question = nativeQuestion(confirmTexts(form, 'confirm-submit'));
    if (question !== '' && !window.confirm(question)) {
      event.preventDefault();
    }
  }
  if (warnedForms.has(form)) {
    return;
  }
  warnedForms.add(form);
  console.warn(
    '[nowo-ui] This form expects the "confirm-submit" Stimulus peer (row_actions_confirm native|dialog) but none is connected: it falls back to window.confirm(). Register it: app.register(\'confirm-submit\', ConfirmSubmitController).',
    form,
  );
}

let confirmBound = false;

export function bindNowoUiConfirm(): void {
  if (confirmBound) {
    return;
  }
  confirmBound = true;
  // 2.69: a second copy of this script (another bundle's) binds nothing more.
  if (!markBound('confirm')) {
    return;
  }
  bindMotion();
  bindFocusReturn();
  // `close` does not bubble: listen in the capture phase. Only dialogs with a remembered opener are touched.
  document.addEventListener('close', onDialogClose, true);
  document.addEventListener('click', onDocumentClick);
  document.addEventListener('input', onDocumentInput);
  document.addEventListener('submit', warnMissingConfirmPeer, true);
  syncAllExpected(document);
  window.nowoUiOpenConfirm = openConfirm;
  window.nowoUiCloseConfirm = closeConfirm;
  window.nowoUiAskConfirm = askConfirm;
}

bindNowoUiConfirm();
