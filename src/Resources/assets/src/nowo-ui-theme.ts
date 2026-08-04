/**
 * Theme toggle peer: toggles data-theme on <html> and optional localStorage key.
 * Host may sync preference via Stimulus using the same data attributes.
 *
 * Markup: [data-nowo-ui-theme-toggle]
 * Optional: data-nowo-ui-theme-storage="nowo-ui-theme"
 *           data-aria-to-light / data-aria-to-dark
 */

declare global {
  interface Window {
    nowoUiSetTheme?: (theme: 'light' | 'dark') => void;
    nowoUiToggleTheme?: () => void;
  }
}

function storageKey(btn: Element | null): string {
  if (btn instanceof HTMLElement) {
    return btn.getAttribute('data-nowo-ui-theme-storage') || 'nowo-ui-theme';
  }
  return 'nowo-ui-theme';
}

function currentTheme(): 'light' | 'dark' {
  const attr = document.documentElement.getAttribute('data-theme');
  if (attr === 'dark' || attr === 'light') {
    return attr;
  }
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
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
  try {
    window.localStorage.setItem(storageKey(btn), theme);
  } catch {
    /* private mode */
  }
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
