/**
 * Generic native dialog (2.1, partial `dialog`): framework-free alternative to the Bootstrap-JS `modal_shell`.
 * Open: [data-nowo-ui-dialog-open="<id>"] (any element; links keep their href as a no-JS fallback).
 * Close: [data-nowo-ui-dialog-close] inside the dialog (or [data-nowo-ui-dialog-close="<id>"] anywhere), Escape
 * (native), a <form method="dialog">, or a click on the backdrop when the dialog has data-nowo-ui-dialog-backdrop-close.
 * Focus returns to the opener on close. No inline handlers (CSP).
 * Replace (2.2): an opener with [data-nowo-ui-dialog-replace] inside an open dialog closes that dialog first and
 * hands its focus-return target over to the new one (focus goes back to whatever opened the first dialog).
 * Remote content (2.2): an opener with [data-nowo-ui-dialog-src="<url>"] fetches that URL (same origin only,
 * credentials same-origin, header X-Requested-With) into the dialog body ([data-nowo-ui-dialog-body] or
 * .nowo-ui-dialog__body; aria-busy while loading) and dispatches `nowo-ui:dialog-loaded` (detail {url}) on the
 * dialog. The fetched HTML is inserted as markup only (scripts do not run: CSP-safe); behaviour comes from delegated
 * listeners / Stimulus. On a failed request the opener's href (the no-JS fallback) is followed.
 * Menus (2.3, partial `menu`): light dismiss for the native <details data-nowo-ui-menu> disclosures — a click outside
 * an open menu or Escape closes it (Escape returns focus to its <summary>). Without this script the menu still works
 * (it closes when its toggle is activated again).
 */

declare global {
  interface Window {
    nowoUiOpenDialog?: (id: string | HTMLDialogElement | null, opener?: Element | null) => void;
    nowoUiCloseDialog?: (id: string | HTMLDialogElement | null) => void;
  }
}

const DIALOG = 'dialog[data-nowo-ui-dialog]';
const MENU = 'details[data-nowo-ui-menu]';
const openers = new WeakMap<HTMLDialogElement, Element>();

export function resolveDialog(id: string | HTMLDialogElement | null): HTMLDialogElement | null {
  if (id === null) {
    return null;
  }
  if (typeof id === 'string') {
    const el = document.getElementById(id);
    return el instanceof HTMLDialogElement && el.matches(DIALOG) ? el : null;
  }
  return id;
}

export function openDialog(id: string | HTMLDialogElement | null, opener: Element | null = null): boolean {
  const dialog = resolveDialog(id);
  if (!dialog || typeof dialog.showModal !== 'function') {
    return false;
  }
  if (!dialog.open) {
    if (opener) {
      openers.set(dialog, opener);
    }
    dialog.showModal();
    const autofocus = dialog.querySelector('[autofocus]');
    if (autofocus instanceof HTMLElement) {
      autofocus.focus();
    }
  }
  return true;
}

export function closeDialog(id: string | HTMLDialogElement | null): void {
  const dialog = resolveDialog(id);
  if (dialog && dialog.open && typeof dialog.close === 'function') {
    dialog.close();
  }
}

/**
 * Opens `id` in place of the open dialog enclosing `opener`: the enclosing dialog closes without restoring focus and
 * its focus-return target is handed over to the new dialog (falls back to a plain open outside a dialog).
 */
export function replaceDialog(id: string | HTMLDialogElement | null, opener: Element): boolean {
  const next = resolveDialog(id);
  const current = opener.closest('dialog[open]');
  if (!next || !(current instanceof HTMLDialogElement) || current === next) {
    return openDialog(next, opener);
  }
  const handover = openers.get(current) ?? null;
  openers.delete(current);
  closeDialog(current);
  return openDialog(next, handover);
}

/** Same-origin absolute URL of a dialog source, null for another origin or an invalid URL. */
export function sameOriginUrl(src: string): string | null {
  try {
    const url = new URL(src, window.location.href);
    return url.origin === window.location.origin ? url.href : null;
  } catch {
    return null;
  }
}

/** Follows a URL (separate function so tests can observe it). */
export const navigation = {
  assign(url: string): void {
    window.location.assign(url);
  },
};

/**
 * Fetches `src` into the body of `dialog` (see the module comment). Resolves true when the content was inserted.
 */
export async function loadDialogContent(dialog: HTMLDialogElement, src: string, fallbackHref: string | null = null): Promise<boolean> {
  const url = sameOriginUrl(src);
  const body = dialog.querySelector('[data-nowo-ui-dialog-body]') ?? dialog.querySelector('.nowo-ui-dialog__body');
  if (!url || !body) {
    return false;
  }
  body.setAttribute('aria-busy', 'true');
  try {
    const response = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' } });
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    body.innerHTML = await response.text();
    body.removeAttribute('aria-busy');
    dialog.dispatchEvent(new CustomEvent('nowo-ui:dialog-loaded', { bubbles: true, detail: { url } }));
    return true;
  } catch {
    body.removeAttribute('aria-busy');
    if (fallbackHref) {
      closeDialog(dialog);
      navigation.assign(fallbackHref);
    }
    return false;
  }
}

/** Returns focus to the element that opened the dialog (native `close` event). */
export function restoreFocus(dialog: HTMLDialogElement): void {
  const opener = openers.get(dialog);
  openers.delete(dialog);
  if (opener instanceof HTMLElement && opener.isConnected) {
    opener.focus();
  }
}

/** Whether a click (client coordinates) falls outside the dialog box, i.e. on the backdrop. */
export function isBackdropClick(dialog: HTMLDialogElement, x: number, y: number): boolean {
  const rect = dialog.getBoundingClientRect();
  if (rect.width === 0 && rect.height === 0) {
    return false;
  }
  return x < rect.left || x > rect.right || y < rect.top || y > rect.bottom;
}

/**
 * Closes every open menu that does not contain `inside` (all of them when null); returns the closed menus.
 */
export function closeMenus(inside: Element | null = null): HTMLDetailsElement[] {
  const closed: HTMLDetailsElement[] = [];
  document.querySelectorAll<HTMLDetailsElement>(`${MENU}[open]`).forEach((menu) => {
    if (inside === null || !menu.contains(inside)) {
      menu.removeAttribute('open');
      closed.push(menu);
    }
  });
  return closed;
}

function onDocumentKeydown(event: KeyboardEvent): void {
  if (event.key !== 'Escape') {
    return;
  }
  const closed = closeMenus();
  const active = document.activeElement;
  const summary = closed.find((menu) => active instanceof Element && menu.contains(active))?.querySelector('summary');
  if (summary instanceof HTMLElement) {
    summary.focus();
  }
}

function onDocumentClick(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) {
    return;
  }
  closeMenus(target);

  const openBtn = target.closest('[data-nowo-ui-dialog-open]');
  if (openBtn) {
    const targetId = openBtn.getAttribute('data-nowo-ui-dialog-open');
    const opened = openBtn.hasAttribute('data-nowo-ui-dialog-replace') ? replaceDialog(targetId, openBtn) : openDialog(targetId, openBtn);
    const src = openBtn.getAttribute('data-nowo-ui-dialog-src');
    const dialog = resolveDialog(targetId);
    if (opened && src && dialog) {
      void loadDialogContent(dialog, src, openBtn.getAttribute('href'));
    }
    if (opened) {
      event.preventDefault();
    }
    return;
  }

  const closeBtn = target.closest('[data-nowo-ui-dialog-close]');
  if (closeBtn) {
    const targetId = closeBtn.getAttribute('data-nowo-ui-dialog-close');
    const dialog = targetId ? resolveDialog(targetId) : closeBtn.closest(DIALOG);
    if (dialog instanceof HTMLDialogElement) {
      event.preventDefault();
      closeDialog(dialog);
    }
    return;
  }

  if (target instanceof HTMLDialogElement && target.matches(DIALOG) && target.hasAttribute('data-nowo-ui-dialog-backdrop-close') && isBackdropClick(target, event.clientX, event.clientY)) {
    closeDialog(target);
  }
}

function onDialogClose(event: Event): void {
  const target = event.target;
  if (target instanceof HTMLDialogElement && target.matches(DIALOG)) {
    restoreFocus(target);
  }
}

let dialogBound = false;

export function bindNowoUiDialog(): void {
  if (dialogBound) {
    return;
  }
  dialogBound = true;
  document.addEventListener('click', onDocumentClick);
  document.addEventListener('keydown', onDocumentKeydown);
  // `close` does not bubble: listen in the capture phase.
  document.addEventListener('close', onDialogClose, true);
  window.nowoUiOpenDialog = openDialog;
  window.nowoUiCloseDialog = closeDialog;
}

bindNowoUiDialog();
