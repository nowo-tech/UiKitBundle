/**
 * Toast stack: dismiss + auto-hide for [data-nowo-ui-toast] items.
 * Stimulus-friendly: hosts may attach controllers; this IIFE is optional peer behaviour.
 */

declare global {
  interface Window {
    nowoUiDismissToast?: (el: HTMLElement | null) => void;
  }
}

/**
 * Removes a toast element with a short leave animation.
 *
 * @param el - Toast root (`[data-nowo-ui-toast]`) or `null` to no-op.
 */
export function dismissToast(el: HTMLElement | null): void {
  if (!el) {
    return;
  }
  el.classList.add('is-leaving');
  const remove = (): void => {
    el.remove();
    const stack = document.querySelector('[data-nowo-ui-toast-stack]');
    if (stack && !stack.querySelector('[data-nowo-ui-toast]')) {
      stack.remove();
    }
  };
  window.setTimeout(remove, 180);
}

function scheduleAutoHide(toast: HTMLElement): void {
  const raw = toast.getAttribute('data-timeout');
  const ms = raw ? Number.parseInt(raw, 10) : 5000;
  if (!Number.isFinite(ms) || ms <= 0) {
    return;
  }
  window.setTimeout(() => dismissToast(toast), ms);
}

function onDocumentClick(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) {
    return;
  }
  const btn = target.closest('[data-nowo-ui-toast-dismiss]');
  if (!btn) {
    return;
  }
  const toast = btn.closest('[data-nowo-ui-toast]');
  if (toast instanceof HTMLElement) {
    event.preventDefault();
    dismissToast(toast);
  }
}

let toastBound = false;

export function bindNowoUiToast(): void {
  if (!toastBound) {
    toastBound = true;
    document.addEventListener('click', onDocumentClick);
    window.nowoUiDismissToast = dismissToast;
  }
  document.querySelectorAll<HTMLElement>('[data-nowo-ui-toast]:not([data-nowo-ui-toast-armed])').forEach((toast) => {
    toast.setAttribute('data-nowo-ui-toast-armed', '');
    scheduleAutoHide(toast);
  });
}

bindNowoUiToast();
