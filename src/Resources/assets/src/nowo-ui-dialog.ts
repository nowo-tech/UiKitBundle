/**
 * Generic native dialog (2.1, partial `dialog`): framework-free alternative to the Bootstrap-JS `modal_shell`.
 * Open: [data-nowo-ui-dialog-open="<id>"] (any element; links keep their href as a no-JS fallback).
 * Close: [data-nowo-ui-dialog-close] inside the dialog (or [data-nowo-ui-dialog-close="<id>"] anywhere), Escape
 * (native), a <form method="dialog">, or a click on the backdrop when the dialog has data-nowo-ui-dialog-backdrop-close.
 * Focus returns to the opener on close. No inline handlers (CSP).
 */

declare global {
  interface Window {
    nowoUiOpenDialog?: (id: string | HTMLDialogElement | null, opener?: Element | null) => void;
    nowoUiCloseDialog?: (id: string | HTMLDialogElement | null) => void;
  }
}

const DIALOG = 'dialog[data-nowo-ui-dialog]';
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

function onDocumentClick(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) {
    return;
  }

  const openBtn = target.closest('[data-nowo-ui-dialog-open]');
  if (openBtn) {
    if (openDialog(openBtn.getAttribute('data-nowo-ui-dialog-open'), openBtn)) {
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
  // `close` does not bubble: listen in the capture phase.
  document.addEventListener('close', onDialogClose, true);
  window.nowoUiOpenDialog = openDialog;
  window.nowoUiCloseDialog = closeDialog;
}

bindNowoUiDialog();
