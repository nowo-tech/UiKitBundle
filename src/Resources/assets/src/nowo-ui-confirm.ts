/**
 * Accessible confirm dialog via native <dialog data-nowo-ui-confirm>.
 * Open: [data-nowo-confirm-open][data-nowo-confirm-target="id"]
 * Close: [data-nowo-confirm-close] or Escape / cancel.
 * Host owns destructive POST + CSRF inside the dialog body/footer.
 */

declare global {
  interface Window {
    nowoUiOpenConfirm?: (id: string | HTMLDialogElement | null) => void;
    nowoUiCloseConfirm?: (el: HTMLDialogElement | null) => void;
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

export function openConfirm(id: string | HTMLDialogElement | null): void {
  const dialog = resolveDialog(id);
  if (!dialog || typeof dialog.showModal !== 'function') {
    return;
  }
  if (!dialog.open) {
    dialog.showModal();
  }
}

export function closeConfirm(el: HTMLDialogElement | null): void {
  if (!el || typeof el.close !== 'function') {
    return;
  }
  if (el.open) {
    el.close();
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
    if (targetId) {
      event.preventDefault();
      openConfirm(targetId);
    }
    return;
  }

  const closeBtn = target.closest('[data-nowo-confirm-close]');
  if (closeBtn) {
    const dialog = closeBtn.closest('dialog[data-nowo-ui-confirm]');
    if (dialog instanceof HTMLDialogElement) {
      event.preventDefault();
      closeConfirm(dialog);
    }
  }
}

let confirmBound = false;

export function bindNowoUiConfirm(): void {
  if (confirmBound) {
    return;
  }
  confirmBound = true;
  document.addEventListener('click', onDocumentClick);
  window.nowoUiOpenConfirm = openConfirm;
  window.nowoUiCloseConfirm = closeConfirm;
}

bindNowoUiConfirm();
