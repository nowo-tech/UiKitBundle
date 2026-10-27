/**
 * Theme toggle peer: toggles data-theme (and data-bs-theme, 2.15.4) on <html> and optional localStorage key.
 * While no theme is stored or server-preferred, it follows OS `prefers-color-scheme` changes live (2.15.4).
 * Host may sync preference via Stimulus using the same data attributes.
 *
 * Markup: [data-nowo-ui-theme-toggle]
 * Optional: data-nowo-ui-theme-storage="nowo-ui-theme" (on the toggle, or on <html> — shared with
 *           `nowo-ui-theme-boot.js`, 1.15), data-nowo-ui-theme-cookie (optional cookie, 1.15)
 *           data-aria-to-light / data-aria-to-dark
 */

import { applyTheme, isTheme, persistTheme, prefersMatch, readPref, readStoredTheme } from './nowo-ui-theme-core';

declare global {
  interface Window {
    nowoUiSetTheme?: (theme: 'light' | 'dark') => void;
    nowoUiToggleTheme?: () => void;
  }
}

function currentTheme(): 'light' | 'dark' {
  const attr = document.documentElement.getAttribute('data-theme');
  if (isTheme(attr)) {
    return attr;
  }
  return prefersMatch('(prefers-color-scheme: dark)') ? 'dark' : 'light';
}

function syncToggleUi(theme: 'light' | 'dark'): void {
  document.querySelectorAll('[data-nowo-ui-theme-toggle]').forEach((btn) => {
    if (!(btn instanceof HTMLElement)) {
      return;
    }
    const toLight = btn.getAttribute('data-aria-to-light') || 'Switch to light theme';
    const toDark = btn.getAttribute('data-aria-to-dark') || 'Switch to dark theme';
    btn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
    btn.setAttribute('aria-label', theme === 'dark' ? toLight : toDark);
    const label = btn.querySelector('[data-nowo-ui-theme-label]');
    if (label) {
      const lightLabel = btn.getAttribute('data-label-light') || 'Light';
      const darkLabel = btn.getAttribute('data-label-dark') || 'Dark';
      label.textContent = theme === 'dark' ? darkLabel : lightLabel;
    }
  });
}

export function setTheme(theme: 'light' | 'dark', btn: Element | null = null): void {
  applyTheme(theme);
  persistTheme(theme, btn instanceof HTMLElement ? btn : null);
  syncToggleUi(theme);
}

export function toggleTheme(btn: Element | null = null): void {
  setTheme(currentTheme() === 'dark' ? 'light' : 'dark', btn);
}

/**
 * OS colour-scheme change: applied only while the page follows the OS (no server preference, nothing stored);
 * not persisted, so the page keeps following the OS.
 */
export function onSystemThemeChange(event: { matches: boolean }): void {
  const root = document.documentElement;
  try {
    if (isTheme(readPref(root, 'theme')) || readStoredTheme(root) !== null) {
      return;
    }
  } catch {
    return;
  }
  const theme = event.matches ? 'dark' : 'light';
  applyTheme(theme, root);
  syncToggleUi(theme);
}

/** Subscribe to `prefers-color-scheme` changes; false when the media query list cannot be observed. */
export function watchSystemTheme(mq: MediaQueryList | null): boolean {
  if (mq === null || typeof mq.addEventListener !== 'function') {
    return false;
  }
  mq.addEventListener('change', onSystemThemeChange);

  return true;
}

function onDocumentClick(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) {
    return;
  }
  const btn = target.closest('[data-nowo-ui-theme-toggle]');
  if (!btn) {
    return;
  }
  event.preventDefault();
  toggleTheme(btn);
}

let themeBound = false;

export function bindNowoUiTheme(): void {
  if (!themeBound) {
    themeBound = true;
    document.addEventListener('click', onDocumentClick);
    window.nowoUiSetTheme = (theme) => setTheme(theme);
    window.nowoUiToggleTheme = () => toggleTheme();
    watchSystemTheme(typeof window.matchMedia === 'function' ? window.matchMedia('(prefers-color-scheme: dark)') : null);
  }
  syncToggleUi(currentTheme());
}

bindNowoUiTheme();
