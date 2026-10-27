import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { applyThemeBoot } from './nowo-ui-theme-boot';
import { readCookie, themeCookieName, themeCookieString, themeStorageKey } from './nowo-ui-theme-core';

function mockMedia(matching: string[]): void {
  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    configurable: true,
    value: vi.fn().mockImplementation((query: string) => ({
      matches: matching.some((m) => query.includes(m)),
      media: query,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
    })),
  });
}

function resetRoot(): void {
  const root = document.documentElement;
  for (const attr of Array.from(root.attributes)) {
    if (attr.name.startsWith('data-')) {
      root.removeAttribute(attr.name);
    }
  }
}

describe('nowo-ui-theme-boot', () => {
  beforeEach(() => {
    localStorage.clear();
    sessionStorage.clear();
    for (const name of ['app_theme', 'app_sidebar']) {
      document.cookie = `${name}=; Path=/; Max-Age=0`;
    }
    resetRoot();
    mockMedia([]);
    delete window.nowoUiPrefs;
  });

  afterEach(() => {
    vi.restoreAllMocks();
    resetRoot();
  });

  it('runs on load and exposes a re-run helper', () => {
    expect(window.nowoUiThemeBoot).toBeTypeOf('function');
    document.documentElement.setAttribute('data-nowo-ui-pref-theme', 'dark');
    expect(window.nowoUiThemeBoot!().theme).toBe('dark');
  });

  it('applies explicit preferences and persists the theme under the shared key', () => {
    const root = document.documentElement;
    root.setAttribute('data-nowo-ui-pref-theme', 'light');
    root.setAttribute('data-nowo-ui-pref-density', 'compact');
    root.setAttribute('data-nowo-ui-pref-motion', 'reduce');
    root.setAttribute('data-nowo-ui-pref-font-scale', 'lg');
    root.setAttribute('data-nowo-ui-pref-contrast', 'more');
    root.setAttribute('data-nowo-ui-theme-storage', 'app-theme');

    const prefs = applyThemeBoot();

    expect(root.getAttribute('data-theme')).toBe('light');
    expect(root.getAttribute('data-ui-density')).toBe('compact');
    expect(root.getAttribute('data-motion')).toBe('reduce');
    expect(root.getAttribute('data-font-scale')).toBe('lg');
    expect(root.getAttribute('data-contrast')).toBe('more');
    expect(localStorage.getItem('app-theme')).toBe('light');
    expect(prefs).toEqual(window.nowoUiPrefs);
  });

  it('accepts the data-user-* alias and safe defaults for unknown values', () => {
    const root = document.documentElement;
    root.setAttribute('data-user-theme', 'dark');
    root.setAttribute('data-user-motion', 'full');
    root.setAttribute('data-user-font-scale', 'xl');
    root.setAttribute('data-user-density', 'cozy');
    applyThemeBoot();
    expect(root.getAttribute('data-theme')).toBe('dark');
    expect(root.getAttribute('data-motion')).toBe('full');
    expect(root.getAttribute('data-font-scale')).toBe('md');
    expect(root.getAttribute('data-ui-density')).toBe('comfortable');
    expect(root.getAttribute('data-contrast')).toBe('system');
    expect(localStorage.getItem('nowo-ui-theme')).toBe('dark');
  });

  it('falls back to cookie, then localStorage, then the OS for an auto preference', () => {
    const root = document.documentElement;
    root.setAttribute('data-nowo-ui-pref-theme', 'auto');
    root.setAttribute('data-nowo-ui-theme-cookie', 'app_theme');

    localStorage.setItem('nowo-ui-theme', 'dark');
    applyThemeBoot();
    expect(root.getAttribute('data-theme')).toBe('dark');

    document.cookie = 'app_theme=light; Path=/';
    applyThemeBoot();
    expect(root.getAttribute('data-theme')).toBe('light');

    document.cookie = 'app_theme=; Path=/; Max-Age=0';
    localStorage.clear();
    mockMedia(['prefers-color-scheme: dark', 'prefers-contrast: more']);
    applyThemeBoot();
    expect(root.getAttribute('data-theme')).toBe('dark');
    expect(root.getAttribute('data-contrast')).toBe('more');
  });

  it('writes the theme cookie with an explicit preference', () => {
    const root = document.documentElement;
    root.setAttribute('data-nowo-ui-pref-theme', 'dark');
    root.setAttribute('data-nowo-ui-theme-cookie', 'app_theme');
    applyThemeBoot();
    expect(document.cookie).toContain('app_theme=dark');
  });

  it('works without matchMedia', () => {
    Object.defineProperty(window, 'matchMedia', { writable: true, configurable: true, value: undefined });
    applyThemeBoot();
    expect(document.documentElement.getAttribute('data-theme')).toBe('light');
  });

  it('syncs the sidebar only when its storage is configured', () => {
    const root = document.documentElement;
    root.setAttribute('data-nowo-ui-pref-sidebar', 'rail');
    applyThemeBoot();
    expect(localStorage.getItem('nowo-ui-sidebar')).toBeNull();

    root.setAttribute('data-nowo-ui-sidebar-cookie', 'app_sidebar');
    root.setAttribute('data-nowo-ui-sidebar-hidden-value', 'collapsed');
    applyThemeBoot();
    expect(localStorage.getItem('nowo-ui-sidebar')).toBe('rail');
    expect(document.cookie).toContain('app_sidebar=rail');

    root.removeAttribute('data-nowo-ui-sidebar-cookie');
    root.setAttribute('data-nowo-ui-sidebar-storage', 'app-sidebar');
    root.setAttribute('data-user-sidebar', 'collapsed');
    root.removeAttribute('data-nowo-ui-pref-sidebar');
    expect(applyThemeBoot().sidebar).toBe('collapsed');
    expect(localStorage.getItem('app-sidebar')).toBe('collapsed');
  });

  it('falls back to safe defaults when storage throws', () => {
    const original = Object.getOwnPropertyDescriptor(window, 'localStorage');
    Object.defineProperty(window, 'localStorage', {
      configurable: true,
      get: () => {
        throw new Error('blocked');
      },
    });
    let prefs;
    try {
      prefs = applyThemeBoot();
    } finally {
      if (original) {
        Object.defineProperty(window, 'localStorage', original);
      }
    }
    expect(prefs).toEqual({ theme: 'light', density: 'comfortable', motion: 'system', fontScale: 'md', contrast: 'system', sidebar: '' });
    expect(document.documentElement.getAttribute('data-theme')).toBe('light');
  });

  it('mirrors the theme to data-bs-theme for Bootstrap 5.3 (2.15.4)', () => {
    const root = document.documentElement;
    root.setAttribute('data-nowo-ui-pref-theme', 'dark');
    applyThemeBoot();
    expect(root.getAttribute('data-theme')).toBe('dark');
    expect(root.getAttribute('data-bs-theme')).toBe('dark');

    root.setAttribute('data-nowo-ui-pref-theme', 'light');
    applyThemeBoot();
    expect(root.getAttribute('data-bs-theme')).toBe('light');
  });

  it('also mirrors data-bs-theme on the safe-default path', () => {
    const original = Object.getOwnPropertyDescriptor(window, 'localStorage');
    Object.defineProperty(window, 'localStorage', {
      configurable: true,
      get: () => {
        throw new Error('blocked');
      },
    });
    try {
      applyThemeBoot();
    } finally {
      if (original) {
        Object.defineProperty(window, 'localStorage', original);
      }
    }
    expect(document.documentElement.getAttribute('data-bs-theme')).toBe('light');
  });

  it('leaves data-bs-theme alone when the host opts out', () => {
    const root = document.documentElement;
    root.setAttribute('data-nowo-ui-bs-theme', 'off');
    root.setAttribute('data-bs-theme', 'light');
    root.setAttribute('data-nowo-ui-pref-theme', 'dark');
    applyThemeBoot();
    expect(root.getAttribute('data-theme')).toBe('dark');
    expect(root.getAttribute('data-bs-theme')).toBe('light');
  });

  it('exposes the shared key helpers', () => {
    const btn = document.createElement('button');
    expect(themeStorageKey(btn)).toBe('nowo-ui-theme');
    document.documentElement.setAttribute('data-nowo-ui-theme-storage', 'html-key');
    expect(themeStorageKey()).toBe('html-key');
    btn.setAttribute('data-nowo-ui-theme-storage', 'btn-key');
    expect(themeStorageKey(btn)).toBe('btn-key');
    expect(themeCookieName()).toBe('');
    btn.setAttribute('data-nowo-ui-theme-cookie', 'c');
    expect(themeCookieName(btn)).toBe('c');
    expect(readCookie('a=1; c=dark', 'c')).toBe('dark');
    expect(readCookie('a=1', 'c')).toBeNull();
    expect(readCookie('c=dark', '')).toBeNull();
    expect(themeCookieString('c', 'dark', true)).toBe('c=dark; Path=/; Max-Age=31536000; SameSite=Lax; Secure');
  });
});
