/**
 * Minimal modal open/close for custom / none / tailwind stacks (REQ-UI-001).
 * Bootstrap stacks use data-bs-* / data-toggle and must not rely on this script.
 */

export type ModalElement = string | HTMLElement | null;

declare global {
  interface Window {
    nowoOpenModal?: (id: ModalElement) => void;
    nowoCloseModal?: (el: HTMLElement | null) => void;
  }
}

function resolveModal(id: ModalElement): HTMLElement | null {
  if (id === null) {
    return null;
  }
  if (typeof id === 'string') {
    return document.getElementById(id);
  }
  return id;
}

export function openModal(id: ModalElement): void {
  const el = resolveModal(id);
  if (!el) {
    return;
  }
  el.classList.add('nowo-ui-modal-open');
  document.body.classList.add('nowo-modal-open');
}

export function closeModal(el: HTMLElement | null): void {
  if (!el) {
    return;
  }
  el.classList.remove('nowo-ui-modal-open');
  if (!document.querySelector('.nowo-ui-modal.nowo-ui-modal-open')) {
    document.body.classList.remove('nowo-modal-open');
  }
}

function onDocumentClick(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) {
    return;
  }

  const openBtn = target.closest('[data-nowo-modal-open]');
  if (openBtn) {
    const targetId = openBtn.getAttribute('data-nowo-modal-target');
    if (targetId) {
      event.preventDefault();
      openModal(targetId);
    }
    return;
  }

  const closeBtn = target.closest('[data-nowo-modal-close]');
  if (closeBtn) {
    const modal = closeBtn.closest('.nowo-ui-modal');
    if (modal instanceof HTMLElement) {
      event.preventDefault();
      closeModal(modal);
    }
    return;
  }

  if (
    target instanceof HTMLElement &&
    target.classList.contains('nowo-ui-modal') &&
    target.classList.contains('nowo-ui-modal-open')
  ) {
    closeModal(target);
  }
}

function onDocumentKeydown(event: KeyboardEvent): void {
  if (event.key !== 'Escape') {
    return;
  }
  const open = document.querySelector('.nowo-ui-modal.nowo-ui-modal-open');
  if (open instanceof HTMLElement) {
    closeModal(open);
  }
}

export function bindNowoUiModal(): void {
  document.addEventListener('click', onDocumentClick);
  document.addEventListener('keydown', onDocumentKeydown);
  window.nowoOpenModal = openModal;
  window.nowoCloseModal = closeModal;
}

bindNowoUiModal();
