/**
 * Confirm dialog core (2.70): what the IIFE runtime (nowo-ui-confirm.ts) and the `confirm-shared` Stimulus peer share —
 * type-to-confirm locking, the shared dialog fill from its row toggle (2.68), the safe first focus (2.61) and the open
 * sequence itself — so both runtimes behave the same. No document listeners here: importing it binds nothing.
 */

import { rememberOpener } from './nowo-ui-focus-return';
import { finishClosing } from './nowo-ui-motion-core';

/** 2.70: a group dialog of menus / row actions (not the table's): moved to <body>, one per id (adoptSharedDialog). */
export const PORTAL = 'data-nowo-confirm-portal';

/**
 * The one dialog of a menu group (2.70, audit ZAF-L1 / ZAF-L2). Twig prints it inside the first menu of the group, so
 * it is moved to the end of <body>: removing that row (Turbo Stream), a display:none responsive copy or a fragment
 * replaced later no longer takes it away. A fragment (fetch, Turbo frame, ESI) that brings another dialog with the same
 * id adds a duplicate: the one already in <body> (else the first in document order) is kept, the closed newcomers are
 * removed. A dialog without PORTAL or without an id is returned as it is. An open dialog is never moved or removed.
 */
export function adoptSharedDialog(dialog: HTMLDialogElement): HTMLDialogElement {
  if (!dialog.hasAttribute(PORTAL) || dialog.id === '') {
    return dialog;
  }
  const same = Array.from(document.querySelectorAll<HTMLDialogElement>(`dialog[${PORTAL}]`)).filter((d) => d.id === dialog.id);
  const keeper = same.find((d) => d.parentElement === document.body) ?? same[0] ?? dialog;
  same.forEach((d) => {
    if (d !== keeper && !d.open) {
      d.remove();
    }
  });
  if (keeper.parentElement !== document.body && !keeper.open) {
    document.body.append(keeper);
  }
  return keeper;
}

export const EXPECTED = 'input[data-nowo-confirm-expected]';
export const SCOPE = 'dialog[data-nowo-ui-confirm], [data-nowo-confirm-scope]';

/** Enables the scope's confirm buttons only when the input holds exactly the expected value. */
export function syncExpected(input: HTMLInputElement): void {
  const scope = input.closest(SCOPE);
  if (!scope) {
    return;
  }
  const matches = input.value === input.getAttribute('data-nowo-confirm-expected');
  scope.querySelectorAll('[data-nowo-confirm-submit]').forEach((button) => {
    if (button instanceof HTMLButtonElement) {
      button.disabled = !matches;
    }
  });
}

/** Clears the expected-value inputs of a scope (dialog open) and re-locks its confirm buttons. */
export function resetExpected(scope: ParentNode): HTMLInputElement | null {
  let first: HTMLInputElement | null = null;
  scope.querySelectorAll(EXPECTED).forEach((input) => {
    if (input instanceof HTMLInputElement) {
      input.value = '';
      syncExpected(input);
      first ??= input;
    }
  });
  return first;
}

const SIZES = ['sm', 'lg'];

function slot(dialog: HTMLDialogElement, name: string): HTMLElement | null {
  return dialog.querySelector<HTMLElement>(`[data-nowo-confirm-slot="${name}"]`);
}

function setText(el: HTMLElement | null, text: string | null): void {
  if (el) {
    el.textContent = text ?? '';
    el.hidden = text === null || text === '';
  }
}

/**
 * Fills a shared confirm dialog from its row toggle (see the module comment). False when the toggle names no form on
 * the page (the dialog must not open with a confirm button bound to nothing).
 */
export function fillSharedConfirm(dialog: HTMLDialogElement, opener: Element): boolean {
  const formId = opener.getAttribute('data-nowo-confirm-form');
  const form = formId ? document.getElementById(formId) : null;
  if (!formId || !(form instanceof HTMLFormElement)) {
    return false;
  }
  const read = (name: string): string | null => opener.getAttribute(`data-nowo-confirm-${name}`);
  const title = slot(dialog, 'title');
  if (title) {
    title.textContent = read('title') ?? '';
  }
  const message = read('message');
  const inHeader = read('message-in-header') === '1';
  setText(slot(dialog, 'header-message'), inHeader ? message : null);
  setText(slot(dialog, 'message'), inHeader ? null : message);
  const body = slot(dialog, 'body');
  if (body) {
    body.hidden = inHeader || message === null || message === '';
  }
  const size = read('size');
  SIZES.forEach((s) => dialog.classList.toggle(`nowo-ui-confirm--${s}`, s === size));

  const cancel = slot(dialog, 'cancel');
  if (cancel) {
    cancel.dataset.nowoConfirmDefault ??= cancel.textContent ?? '';
    cancel.textContent = read('cancel-label') ?? cancel.dataset.nowoConfirmDefault;
  }

  const expected = read('expected-value');
  const buttons = Array.from(dialog.querySelectorAll<HTMLButtonElement>('button[data-nowo-confirm-variant]'));
  const variant = read('variant');
  const chosen = buttons.find((b) => b.getAttribute('data-nowo-confirm-variant') === variant) ?? buttons[0];
  buttons.forEach((button) => {
    const active = button === chosen;
    button.hidden = !active;
    button.dataset.nowoConfirmDefault ??= button.textContent ?? '';
    if (active) {
      button.setAttribute('form', formId);
      button.textContent = read('confirm-label') ?? button.dataset.nowoConfirmDefault;
    } else {
      button.removeAttribute('form');
    }
    button.toggleAttribute('data-nowo-confirm-submit', active && expected !== null);
    button.disabled = false;
  });

  const expect = slot(dialog, 'expect');
  const input = slot(dialog, 'expect-input');
  if (expect && input instanceof HTMLInputElement) {
    expect.hidden = expected === null;
    setText(slot(dialog, 'expect-label'), expected === null ? null : read('expected-label'));
    if (expected === null) {
      input.removeAttribute('data-nowo-confirm-expected');
      input.removeAttribute('pattern');
      input.removeAttribute('form');
      input.required = false;
    } else {
      input.setAttribute('data-nowo-confirm-expected', expected);
      input.setAttribute('pattern', read('expected-pattern') ?? '');
      input.setAttribute('form', formId);
      input.required = true;
    }
  }
  return true;
}

/** Drops the row binding of a closed shared dialog (confirm buttons and the type-to-confirm input). */
export function unbindSharedConfirm(dialog: HTMLDialogElement): void {
  dialog.querySelectorAll('[data-nowo-confirm-variant], [data-nowo-confirm-slot="expect-input"]').forEach((el) => el.removeAttribute('form'));
}

/** The Cancel button of a destructive confirm (see the module comment), else null. */
export function safeCancel(dialog: HTMLDialogElement): HTMLElement | null {
  const flag = dialog.getAttribute('data-nowo-ui-confirm-cancel-first');
  const danger =
    flag !== null ? flag !== 'false' : dialog.querySelector('button[type="submit"].nowo-ui-btn--danger:not([hidden]), [data-nowo-confirm-submit].nowo-ui-btn--danger:not([hidden])') !== null;
  if (!danger) {
    return null;
  }
  return dialog.querySelector<HTMLElement>('.nowo-ui-confirm__footer [data-nowo-confirm-close]');
}

/**
 * Opens a resolved confirm dialog (both runtimes; a menu group dialog is adopted first, adoptSharedDialog()): a shared dialog is filled from `opener` first and stays closed when
 * that opener names no row form; then the opener is remembered for focus return (2.52.2), the type-to-confirm input is
 * cleared and focused, else a destructive dialog focuses Cancel. Returns whether the dialog is open afterwards.
 */
export function openConfirmDialog(target: HTMLDialogElement, opener: Element | null = null): boolean {
  const dialog = adoptSharedDialog(target);
  if (typeof dialog.showModal !== 'function') {
    return false;
  }
  finishClosing(dialog);
  if (!dialog.open && dialog.hasAttribute('data-nowo-confirm-shared') && !(opener && fillSharedConfirm(dialog, opener))) {
    return false;
  }
  if (!dialog.open) {
    rememberOpener(dialog, opener);
    const input = resetExpected(dialog);
    dialog.showModal();
    (input ?? safeCancel(dialog))?.focus();
  }
  return true;
}
