import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as mod from './nowo-ui-theme';

describe('nowo-ui-theme', () => {
  beforeEach(() => {
    document.documentElement.removeAttribute('data-theme');
    document.documentElement.removeAttribute('data-bs-theme');
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
    for (const attr of ['data-theme', 'data-bs-theme', 'data-nowo-ui-bs-theme', 'data-nowo-ui-pref-theme']) {
      document.documentElement.removeAttribute(attr);
    }
    window.localStorage.clear();
    vi.restoreAllMocks();
  });

  it('sets data-bs-theme with data-theme (2.15.4)', () => {
    mod.setTheme('dark');
    expect(document.documentElement.getAttribute('data-bs-theme')).toBe('dark');
    mod.toggleTheme();
    expect(document.documentElement.getAttribute('data-theme')).toBe('light');
    expect(document.documentElement.getAttribute('data-bs-theme')).toBe('light');
  });

  it('keeps data-bs-theme untouched when <html data-nowo-ui-bs-theme="off">', () => {
    document.documentElement.setAttribute('data-nowo-ui-bs-theme', 'off');
    mod.setTheme('dark');
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
    expect(document.documentElement.hasAttribute('data-bs-theme')).toBe(false);
  });

  it('follows OS colour-scheme changes while no theme is stored or preferred', () => {
    mod.onSystemThemeChange({ matches: true });
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
    expect(document.documentElement.getAttribute('data-bs-theme')).toBe('dark');
    expect(document.querySelector('[data-nowo-ui-theme-label]')?.textContent).toBe('Night');
    expect(window.localStorage.getItem('nowo-ui-theme')).toBeNull();
    mod.onSystemThemeChange({ matches: false });
    expect(document.documentElement.getAttribute('data-bs-theme')).toBe('light');
  });

  it('ignores OS changes once the user chose a theme or the server set one', () => {
    mod.setTheme('light');
    mod.onSystemThemeChange({ matches: true });
    expect(document.documentElement.getAttribute('data-theme')).toBe('light');

    window.localStorage.clear();
    document.documentElement.setAttribute('data-nowo-ui-pref-theme', 'light');
    mod.onSystemThemeChange({ matches: true });
    expect(document.documentElement.getAttribute('data-theme')).toBe('light');
  });

  it('ignores OS changes when storage is unreadable', () => {
    document.documentElement.setAttribute('data-theme', 'light');
    const original = Object.getOwnPropertyDescriptor(window, 'localStorage');
    Object.defineProperty(window, 'localStorage', {
      configurable: true,
      get: () => {
        throw new Error('blocked');
      },
    });
    try {
      mod.onSystemThemeChange({ matches: true });
    } finally {
      if (original) {
        Object.defineProperty(window, 'localStorage', original);
      }
    }
    expect(document.documentElement.getAttribute('data-theme')).toBe('light');
    expect(document.documentElement.hasAttribute('data-bs-theme')).toBe(false);
  });

  it('listens to the prefers-color-scheme media query', () => {
    let listener: ((e: { matches: boolean }) => void) | null = null;
    const watch = mod.watchSystemTheme({
      addEventListener: (_type: string, cb: (e: { matches: boolean }) => void) => {
        listener = cb;
      },
    } as unknown as MediaQueryList);
    expect(watch).toBe(true);
    listener!({ matches: true });
    expect(document.documentElement.getAttribute('data-bs-theme')).toBe('dark');
    expect(mod.watchSystemTheme(null)).toBe(false);
    expect(mod.watchSystemTheme({} as MediaQueryList)).toBe(false);
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

