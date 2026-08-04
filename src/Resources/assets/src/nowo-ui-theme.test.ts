import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as mod from './nowo-ui-theme';

describe('nowo-ui-theme', () => {
  beforeEach(() => {
    document.documentElement.removeAttribute('data-theme');
    window.localStorage.clear();
    document.body.innerHTML = `
      <button
        type="button"
        data-nowo-ui-theme-toggle
        data-label-light="Day"
        data-label-dark="Night"
        data-aria-to-light="To light"
        data-aria-to-dark="To dark"
      >
        <span data-nowo-ui-theme-label>Day</span>
      </button>
    `;
    mod.bindNowoUiTheme();
  });

  afterEach(() => {
    document.body.innerHTML = '';
    document.documentElement.removeAttribute('data-theme');
    window.localStorage.clear();
  });

  it('toggles theme and persists', () => {
    mod.setTheme('light');
    expect(document.documentElement.getAttribute('data-theme')).toBe('light');
    mod.toggleTheme();
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
    expect(window.localStorage.getItem('nowo-ui-theme')).toBe('dark');
    expect(document.querySelector('[data-nowo-ui-theme-label]')?.textContent).toBe('Night');
  });

  it('clicks toggle button', () => {
    mod.setTheme('light');
    document.querySelector('[data-nowo-ui-theme-toggle]')!
      .dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
  });

  it('exposes window helpers and is idempotent', () => {
    mod.bindNowoUiTheme();
    window.nowoUiSetTheme?.('light');
    expect(document.documentElement.getAttribute('data-theme')).toBe('light');
    window.nowoUiToggleTheme?.();
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
  });

  it('ignores non-toggle clicks and uses custom storage key', () => {
    document.body.innerHTML = `
      <button type="button" data-nowo-ui-theme-toggle data-nowo-ui-theme-storage="custom-theme-key">T</button>
      <button type="button" id="other">Other</button>
    `;
    mod.bindNowoUiTheme();
    document.getElementById('other')!
      .dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect(document.documentElement.getAttribute('data-theme')).not.toBe('dark');
    document.querySelector('[data-nowo-ui-theme-toggle]')!
      .dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect(window.localStorage.getItem('custom-theme-key')).toBeTruthy();
  });

  it('survives localStorage failures', () => {
    const spy = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('quota');
    });
    mod.setTheme('dark');
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
    spy.mockRestore();
  });
});

