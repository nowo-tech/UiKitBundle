import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as mod from './nowo-ui-toast';

describe('nowo-ui-toast', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    document.body.innerHTML = `
      <div data-nowo-ui-toast-stack>
        <div data-nowo-ui-toast data-timeout="1000" class="nowo-ui-toast">
          <button type="button" data-nowo-ui-toast-dismiss>×</button>
        </div>
      </div>
    `;
    mod.bindNowoUiToast();
  });

  afterEach(() => {
    vi.useRealTimers();
    document.body.innerHTML = '';
  });

  it('dismisses via button and removes empty stack', () => {
    const btn = document.querySelector('[data-nowo-ui-toast-dismiss]')!;
    btn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect(document.querySelector('[data-nowo-ui-toast]')?.classList.contains('is-leaving')).toBe(true);
    vi.advanceTimersByTime(200);
    expect(document.querySelector('[data-nowo-ui-toast-stack]')).toBeNull();
  });

  it('auto-hides after data-timeout', () => {
    vi.advanceTimersByTime(1000);
    expect(document.querySelector('[data-nowo-ui-toast]')?.classList.contains('is-leaving')).toBe(true);
    vi.advanceTimersByTime(200);
    expect(document.querySelector('[data-nowo-ui-toast]')).toBeNull();
  });

  it('dismissToast ignores null and exposes window helper', () => {
    mod.dismissToast(null);
    expect(window.nowoUiDismissToast).toBe(mod.dismissToast);
  });

  it('bindNowoUiToast is idempotent', () => {
    mod.bindNowoUiToast();
    mod.bindNowoUiToast();
    expect(window.nowoUiDismissToast).toBe(mod.dismissToast);
  });

  it('skips auto-hide when timeout is invalid', () => {
    document.body.innerHTML = `
      <div data-nowo-ui-toast-stack>
        <div data-nowo-ui-toast data-timeout="0">x</div>
      </div>
    `;
    mod.bindNowoUiToast();
    vi.advanceTimersByTime(5000);
    expect(document.querySelector('[data-nowo-ui-toast]')).not.toBeNull();
  });

  it('ignores clicks outside dismiss controls', () => {
    document.body.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect(document.querySelector('[data-nowo-ui-toast]')).not.toBeNull();
  });
});

