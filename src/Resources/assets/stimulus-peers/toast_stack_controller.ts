/**
 * Optional Stimulus peer — toast stack auto-dismiss + leave animation.
 *
 * Prefer kit IIFE `nowo-ui-toast.js` with `data-nowo-ui-toast*` when no Stimulus.
 * Omit the IIFE when this controller owns behaviour.
 *
 * Identifier: `toast-stack`
 * Toast node: target `toast` and/or `[data-nowo-ui-toast]`
 * Dismiss: `[data-nowo-ui-toast-dismiss]` (preferred) or action `dismiss`
 */

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['toast'];

  declare readonly toastTargets: HTMLElement[];

  /** Active auto-dismiss timers keyed by toast element. */
  private timers = new Map<HTMLElement, number>();

  toastTargetConnected(toast: HTMLElement): void {
    this.schedule(toast);

    toast.addEventListener('mouseenter', this.pause);
    toast.addEventListener('mouseleave', this.resume);
    toast.addEventListener('focusin', this.pause);
    toast.addEventListener('focusout', this.resume);
  }

  toastTargetDisconnected(toast: HTMLElement): void {
    this.clearTimer(toast);
    toast.removeEventListener('mouseenter', this.pause);
    toast.removeEventListener('mouseleave', this.resume);
    toast.removeEventListener('focusin', this.pause);
    toast.removeEventListener('focusout', this.resume);
  }

  dismiss(event: Event): void {
    const button = event.currentTarget;
    if (!(button instanceof HTMLElement)) {
      return;
    }
    const toast =
      button.closest<HTMLElement>('[data-nowo-ui-toast]') ??
      button.closest<HTMLElement>("[data-toast-stack-target='toast']");
    if (toast) {
      this.leave(toast);
    }
  }

  private schedule = (toast: HTMLElement): void => {
    this.clearTimer(toast);
    const raw = toast.dataset.timeout;
    const timeout = raw ? Number.parseInt(raw, 10) : 5000;
    if (!Number.isFinite(timeout) || timeout <= 0) {
      return;
    }
    const id = window.setTimeout(() => this.leave(toast), timeout);
    this.timers.set(toast, id);
  };

  private pause = (event: Event): void => {
    const toast = event.currentTarget;
    if (toast instanceof HTMLElement) {
      this.clearTimer(toast);
    }
  };

  private resume = (event: Event): void => {
    const toast = event.currentTarget;
    if (toast instanceof HTMLElement && !toast.classList.contains('is-leaving')) {
      this.schedule(toast);
    }
  };

  private clearTimer(toast: HTMLElement): void {
    const id = this.timers.get(toast);
    if (id !== undefined) {
      window.clearTimeout(id);
      this.timers.delete(toast);
    }
  }

  private leave(toast: HTMLElement): void {
    this.clearTimer(toast);
    if (toast.classList.contains('is-leaving')) {
      return;
    }
    if (typeof window.nowoUiDismissToast === 'function' && toast.hasAttribute('data-nowo-ui-toast')) {
      window.nowoUiDismissToast(toast);
      return;
    }
    toast.classList.add('is-leaving');

    let finished = false;
    const done = (): void => {
      if (finished) {
        return;
      }
      finished = true;
      if (toast.isConnected) {
        toast.remove();
      }
      if (this.element.isConnected && this.toastTargets.length === 0) {
        this.element.remove();
      }
    };

    toast.addEventListener('animationend', done, { once: true });
    window.setTimeout(done, 400);
  }
}

declare global {
  interface Window {
    nowoUiDismissToast?: (el: HTMLElement | null) => void;
  }
}
