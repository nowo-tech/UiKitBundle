/**
 * Theme toggle peer: toggles data-theme on <html> and optional localStorage key.
 * Host may sync preference via Stimulus using the same data attributes.
 *
 * Markup: [data-nowo-ui-theme-toggle]
 * Optional: data-nowo-ui-theme-storage="nowo-ui-theme" (on the toggle, or on <html> — shared with
 *           `nowo-ui-theme-boot.js`, 1.15), data-nowo-ui-theme-cookie (optional cookie, 1.15)
 *           data-aria-to-light / data-aria-to-dark
 */

import { isTheme, persistTheme, prefersMatch } from './nowo-ui-theme-core';

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
  document.documentElement.setAttribute('data-theme', theme);
  persistTheme(theme, btn instanceof HTMLElement ? btn : null);
  syncToggleUi(theme);
}

export function toggleTheme(btn: Element | null = null): void {
  setTheme(currentTheme() === 'dark' ? 'light' : 'dark', btn);
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
  }
  syncToggleUi(currentTheme());
}

bindNowoUiTheme();
