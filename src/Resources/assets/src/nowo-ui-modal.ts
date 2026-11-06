/**
 * Minimal modal open/close for custom / none / tailwind stacks (REQ-UI-001).
 * Bootstrap stacks use data-bs-* / data-toggle and must not rely on this script.
 *
 * 2.33 (UI-2): when the modal root is a native `<dialog>`, opening also calls `showModal()` (top layer, ::backdrop,
 * native Escape; no portal needed) and closing calls `close()`; a native close (Escape, a `method="dialog"` form)
 * removes `nowo-ui-modal-open` too and focus returns to the trigger. A trigger names its modal with
 * `data-nowo-modal-target="<id>"` or, without it, `data-nowo-modal-open="<id>"`. Binding is idempotent, also when the
 * script is loaded twice (IIFE + host bundle): the document listeners are attached once per page.
 */

export type ModalElement = string | HTMLElement | null;

declare global {
  interface Window {
    nowoOpenModal?: (id: ModalElement) => void;
    nowoCloseModal?: (el: HTMLElement | null) => void;
  }
}

/** Page-wide marker (shared by every copy of this script on the page). */
const BOUND = Symbol.for('nowo-ui-modal.bound');
const openers = new WeakMap<HTMLElement, HTMLElement>();
const bridged = new WeakSet<HTMLDialogElement>();

function syncBody(): void {
  if (!document.querySelector('.nowo-ui-modal.nowo-ui-modal-open')) {
    document.body.classList.remove('nowo-modal-open');
  }
}

/** A native close (Escape, form method="dialog", script) keeps the class and the body marker in sync. */
function bridge(dialog: HTMLDialogElement): void {
  if (bridged.has(dialog)) {
    return;
  }
  bridged.add(dialog);
  dialog.addEventListener('close', () => {
    dialog.classList.remove('nowo-ui-modal-open');
    syncBody();
    const opener = openers.get(dialog);
    openers.delete(dialog);
    if (opener?.isConnected) {
      opener.focus();
    }
  });
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

export function openModal(id: ModalElement, opener: HTMLElement | null = null): void {
  const el = resolveModal(id);
  if (!el) {
    return;
  }
  el.classList.add('nowo-ui-modal-open');
  document.body.classList.add('nowo-modal-open');
  if (el instanceof HTMLDialogElement) {
    bridge(el);
    if (opener) {
      openers.set(el, opener);
    }
    if (!el.open) {
      try {
        el.showModal();
      } catch {
        // Not connected (or no showModal): the class still shows it.
      }
    }
  }
}

export function closeModal(el: HTMLElement | null): void {
  if (!el) {
    return;
  }
  el.classList.remove('nowo-ui-modal-open');
  if (el instanceof HTMLDialogElement && el.open) {
    el.close();
  }
  syncBody();
}

/** The modal id of a trigger: `data-nowo-modal-target`, else the value of `data-nowo-modal-open` (2.33), `#` dropped. */
export function triggerTarget(trigger: Element): string | null {
  const raw = (trigger.getAttribute('data-nowo-modal-target') || trigger.getAttribute('data-nowo-modal-open') || '').trim().replace(/^#/, '');
  return raw === '' ? null : raw;
}

function onDocumentClick(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) {
    return;
  }

  const openBtn = target.closest('[data-nowo-modal-open]');
  if (openBtn) {
    const targetId = triggerTarget(openBtn);
    if (targetId && document.getElementById(targetId)) {
      event.preventDefault();
      openModal(targetId, openBtn instanceof HTMLElement ? openBtn : null);
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
  // An open native <dialog> closes itself on Escape (cancel → close event, bridged above).
  if (open instanceof HTMLElement && !(open instanceof HTMLDialogElement && open.open)) {
    closeModal(open);
  }
}

export function bindNowoUiModal(): void {
  const page = window as Window & { [BOUND]?: true };
  if (page[BOUND]) {
    return;
  }
  page[BOUND] = true;
  document.addEventListener('click', onDocumentClick);
  document.addEventListener('keydown', onDocumentKeydown);
  window.nowoOpenModal = openModal;
  window.nowoCloseModal = closeModal;
}

bindNowoUiModal();
