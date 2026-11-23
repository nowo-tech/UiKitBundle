/**
 * Toast stack: dismiss + auto-hide for [data-nowo-ui-toast] items.
 * Stimulus-friendly: hosts may attach controllers; this IIFE is optional peer behaviour.
 *
 * 2.61:
 * - the countdown pauses while the pointer is over a toast or the focus is inside it, and starts again (full
 *   timeout) when both have left (WCAG 2.2.1);
 * - with reduced motion (prefers-reduced-motion: reduce or html[data-motion="reduce"]) a toast is removed at once,
 *   without the leave animation;
 * - client toasts: `_toasts` with `client: true` prints the (empty) stack, which then stays in the page, and a
 *   <template data-nowo-ui-toast-template>; `window.nowoUiToast({message, title, type, timeout})` or a click on
 *   [data-nowo-ui-toast-show] (data-nowo-ui-toast-message / -title / -type / -timeout) adds a toast from it. Text goes
 *   through textContent (never HTML). The stack is a polite live region, so the new toast is announced once.
 */

import { announceScript } from './script-banner';

declare const __UI_KIT_BUILD_TIME__: string;

announceScript('ui-kit/toast', typeof __UI_KIT_BUILD_TIME__ !== 'undefined' ? __UI_KIT_BUILD_TIME__ : undefined);

declare global {
  interface Window {
    nowoUiDismissToast?: (el: HTMLElement | null) => void;
    nowoUiToast?: (options: ToastOptions) => HTMLElement | null;
  }
}

export interface ToastOptions {
  message: string;
  title?: string | null;
  type?: string | null;
  timeout?: number | null;
}

export const TOAST_TYPES = ['success', 'info', 'warning', 'error'];
const LEAVE_MS = 180;
const timers = new WeakMap<HTMLElement, number>();

/** True when the user (or the host's motion preference) asks for no animation. */
export function prefersReducedMotion(): boolean {
  if (document.documentElement.getAttribute('data-motion') === 'reduce') {
    return true;
  }
  return typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function clearTimer(toast: HTMLElement): void {
  const id = timers.get(toast);
  if (id !== undefined) {
    window.clearTimeout(id);
    timers.delete(toast);
  }
}

/**
 * Removes a toast element with a short leave animation (none with reduced motion).
 *
 * @param el - Toast root (`[data-nowo-ui-toast]`) or `null` to no-op.
 */
export function dismissToast(el: HTMLElement | null): void {
  if (!el) {
    return;
  }
  clearTimer(el);
  el.classList.add('is-leaving');
  const remove = (): void => {
    const stack = el.closest('[data-nowo-ui-toast-stack]') ?? document.querySelector('[data-nowo-ui-toast-stack]');
    el.remove();
    if (stack && !stack.hasAttribute('data-nowo-ui-toast-client') && !stack.querySelector('[data-nowo-ui-toast]')) {
      stack.remove();
    }
  };
  if (prefersReducedMotion()) {
    remove();
    return;
  }
  window.setTimeout(remove, LEAVE_MS);
}

function timeoutOf(toast: HTMLElement): number {
  const raw = toast.getAttribute('data-timeout');
  return raw ? Number.parseInt(raw, 10) : 5000;
}

function scheduleAutoHide(toast: HTMLElement): void {
  const ms = timeoutOf(toast);
  clearTimer(toast);
  if (!Number.isFinite(ms) || ms <= 0) {
    return;
  }
  timers.set(
    toast,
    window.setTimeout(() => dismissToast(toast), ms),
  );
}

function resume(toast: HTMLElement): void {
  if (toast.contains(document.activeElement)) {
    return;
  }
  scheduleAutoHide(toast);
}

function arm(toast: HTMLElement): void {
  if (toast.hasAttribute('data-nowo-ui-toast-armed')) {
    return;
  }
  toast.setAttribute('data-nowo-ui-toast-armed', '');
  toast.addEventListener('mouseenter', () => clearTimer(toast));
  toast.addEventListener('focusin', () => clearTimer(toast));
  toast.addEventListener('mouseleave', () => resume(toast));
  toast.addEventListener('focusout', (event) => {
    const next = event.relatedTarget;
    if (!(next instanceof Node && toast.contains(next))) {
      scheduleAutoHide(toast);
    }
  });
  scheduleAutoHide(toast);
}

/** Adds a toast from the page's template; null when there is no client stack, no template or no message. */
export function showToast(options: ToastOptions): HTMLElement | null {
  const message = (options.message ?? '').trim();
  const stack = document.querySelector<HTMLElement>('[data-nowo-ui-toast-stack][data-nowo-ui-toast-client]');
  const template = document.querySelector<HTMLTemplateElement>('template[data-nowo-ui-toast-template]');
  const toast = template?.content.firstElementChild?.cloneNode(true);
  if (message === '' || !stack || !(toast instanceof HTMLElement)) {
    return null;
  }
  const type = TOAST_TYPES.includes(options.type ?? '') ? (options.type as string) : 'info';
  toast.classList.add(`nowo-ui-flash--${type}`);
  toast.setAttribute('data-nowo-ui-toast-type', type);
  const urgent = type === 'error' || type === 'warning' ? toast.getAttribute('data-timeout-urgent') : null;
  const timeout = options.timeout ?? (urgent !== null ? Number.parseInt(urgent, 10) : null);
  if (timeout !== null && Number.isFinite(timeout)) {
    toast.setAttribute('data-timeout', String(timeout));
  }
  const title = toast.querySelector<HTMLElement>('[data-nowo-ui-toast-title]');
  if (title) {
    const text = (options.title ?? '').trim();
    title.textContent = text;
    title.hidden = text === '';
  }
  const body = toast.querySelector<HTMLElement>('[data-nowo-ui-toast-message]');
  if (body) {
    body.textContent = message;
  }
  stack.appendChild(toast);
  arm(toast);
  return toast;
}

function onDocumentClick(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) {
    return;
  }
  const show = target.closest('[data-nowo-ui-toast-show]');
  if (show) {
    event.preventDefault();
    const timeout = show.getAttribute('data-nowo-ui-toast-timeout');
    showToast({
      message: show.getAttribute('data-nowo-ui-toast-message') ?? '',
      title: show.getAttribute('data-nowo-ui-toast-title'),
      type: show.getAttribute('data-nowo-ui-toast-type'),
      timeout: timeout === null ? null : Number.parseInt(timeout, 10),
    });
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
    window.nowoUiToast = showToast;
  }
  document.querySelectorAll<HTMLElement>('[data-nowo-ui-toast]').forEach(arm);
}

bindNowoUiToast();
