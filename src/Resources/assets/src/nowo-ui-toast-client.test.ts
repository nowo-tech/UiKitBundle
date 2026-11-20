import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as mod from './nowo-ui-toast';

/** 2.61: client toasts (`_toasts` with `client: true`), pause on hover / focus, reduced motion. */
const STACK = `
  <div class="nowo-ui-toast-stack" data-nowo-ui-toast-stack data-nowo-ui-toast-client aria-live="polite"></div>
  <template data-nowo-ui-toast-template>
    <div class="nowo-ui-toast nowo-ui-flash" data-nowo-ui-toast data-timeout="5000" data-timeout-urgent="8000">
      <div class="nowo-ui-toast__content"><p data-nowo-ui-toast-title hidden></p><p data-nowo-ui-toast-message></p></div>
      <button type="button" data-nowo-ui-toast-dismiss aria-label="Dismiss notification">×</button>
    </div>
  </template>
  <button type="button" id="show" data-nowo-ui-toast-show data-nowo-ui-toast-message="Invoice saved" data-nowo-ui-toast-type="success" data-nowo-ui-toast-title="Done">Save</button>
  <button type="button" id="show-urgent" data-nowo-ui-toast-show data-nowo-ui-toast-message="Bank feed failed" data-nowo-ui-toast-type="error">Fail</button>
  <button type="button" id="show-sticky" data-nowo-ui-toast-show data-nowo-ui-toast-message="Kept" data-nowo-ui-toast-timeout="0">Keep</button>`;

describe('nowo-ui-toast client toasts', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    document.documentElement.removeAttribute('data-motion');
    document.body.innerHTML = STACK;
    mod.bindNowoUiToast();
  });

  afterEach(() => {
    vi.useRealTimers();
    document.body.innerHTML = '';
    document.documentElement.removeAttribute('data-motion');
    vi.unstubAllGlobals();
  });

  const stack = () => document.querySelector('[data-nowo-ui-toast-stack]')!;

  it('adds a toast from a button: text only, type class, title shown', () => {
    document.getElementById('show')!.click();
    const toast = stack().querySelector<HTMLElement>('[data-nowo-ui-toast]')!;
    expect(toast.classList.contains('nowo-ui-flash--success')).toBe(true);
    expect(toast.querySelector('[data-nowo-ui-toast-message]')!.textContent).toBe('Invoice saved');
    expect((toast.querySelector('[data-nowo-ui-toast-title]') as HTMLElement).hidden).toBe(false);
    expect(toast.getAttribute('data-timeout')).toBe('5000');
    vi.advanceTimersByTime(5000);
    vi.advanceTimersByTime(200);
    expect(stack().querySelector('[data-nowo-ui-toast]')).toBeNull();
    // The client stack stays for the next toast.
    expect(document.querySelector('[data-nowo-ui-toast-stack]')).not.toBeNull();
  });

  it('errors keep the longer timeout; timeout 0 stays until dismissed', () => {
    document.getElementById('show-urgent')!.click();
    expect(stack().querySelector('[data-nowo-ui-toast]')!.getAttribute('data-timeout')).toBe('8000');
    document.getElementById('show-sticky')!.click();
    const sticky = stack().querySelectorAll<HTMLElement>('[data-nowo-ui-toast]')[1];
    expect(sticky.classList.contains('nowo-ui-flash--info')).toBe(true);
    vi.advanceTimersByTime(60000);
    expect(sticky.isConnected).toBe(true);
    expect((sticky.querySelector('[data-nowo-ui-toast-title]') as HTMLElement).hidden).toBe(true);
  });

  it('pauses while hovered or focused and restarts when left', () => {
    const toast = mod.showToast({ message: 'Hello', timeout: 1000 })!;
    toast.dispatchEvent(new MouseEvent('mouseenter'));
    vi.advanceTimersByTime(3000);
    expect(toast.isConnected).toBe(true);
    toast.dispatchEvent(new MouseEvent('mouseleave'));
    vi.advanceTimersByTime(1200);
    expect(toast.isConnected).toBe(false);

    const focused = mod.showToast({ message: 'Again', timeout: 1000 })!;
    const dismiss = focused.querySelector<HTMLElement>('[data-nowo-ui-toast-dismiss]')!;
    dismiss.focus();
    focused.dispatchEvent(new MouseEvent('mouseleave'));
    vi.advanceTimersByTime(3000);
    expect(focused.isConnected).toBe(true);
    const inner = new FocusEvent('focusout', { relatedTarget: focused });
    focused.dispatchEvent(inner);
    vi.advanceTimersByTime(3000);
    expect(focused.isConnected).toBe(true);
    dismiss.blur();
    focused.dispatchEvent(new FocusEvent('focusout', { relatedTarget: document.body }));
    vi.advanceTimersByTime(1200);
    expect(focused.isConnected).toBe(false);
  });

  it('removes at once with reduced motion', () => {
    document.documentElement.setAttribute('data-motion', 'reduce');
    const toast = mod.showToast({ message: 'Now' })!;
    mod.dismissToast(toast);
    expect(toast.isConnected).toBe(false);
    document.documentElement.removeAttribute('data-motion');
    vi.stubGlobal('matchMedia', () => ({ matches: true }));
    expect(mod.prefersReducedMotion()).toBe(true);
    vi.stubGlobal('matchMedia', undefined);
    expect(mod.prefersReducedMotion()).toBe(false);
  });

  it('returns null without a message, a client stack or a template', () => {
    expect(mod.showToast({ message: '  ' })).toBeNull();
    document.querySelector('template')!.remove();
    expect(mod.showToast({ message: 'x' })).toBeNull();
    document.body.innerHTML = '<div data-nowo-ui-toast-stack></div>';
    expect(mod.showToast({ message: 'x' })).toBeNull();
    expect(window.nowoUiToast).toBe(mod.showToast);
  });

  it('a template without message / title slots still shows', () => {
    document.body.innerHTML = `<div data-nowo-ui-toast-stack data-nowo-ui-toast-client></div><template data-nowo-ui-toast-template><div data-nowo-ui-toast></div></template>`;
    const toast = mod.showToast({ message: 'x', type: 'bogus', timeout: Number.NaN })!;
    expect(toast.getAttribute('data-nowo-ui-toast-type')).toBe('info');
    expect(toast.hasAttribute('data-timeout')).toBe(false);
    document.body.innerHTML = `<div data-nowo-ui-toast-stack data-nowo-ui-toast-client></div><template data-nowo-ui-toast-template>text only</template>`;
    expect(mod.showToast({ message: 'x' })).toBeNull();
  });
});
