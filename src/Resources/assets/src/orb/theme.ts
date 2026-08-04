/**
 * Theme + reduced-motion helpers (vanilla).
 *
 * Adapted from thinking-orbs theme hooks (MIT © 2026 Jakub Antalik).
 */

import type { OrbTheme } from './types';

function ancestorTheme(el: Element | null): boolean | null {
  let node: Element | null = el;
  while (node) {
    const attr = node.getAttribute('data-theme');
    if (attr === 'dark') {
      return true;
    }
    if (attr === 'light') {
      return false;
    }
    if (node.classList.contains('dark')) {
      return true;
    }
    if (node.classList.contains('light')) {
      return false;
    }
    node = node.parentElement;
  }
  return null;
}

function systemDark(): boolean {
  return typeof matchMedia === 'undefined' || matchMedia('(prefers-color-scheme: dark)').matches;
}

/** Resolve whether the canvas should use dark-substrate ink (light dots). */
export function resolveDark(theme: OrbTheme, host: Element | null): boolean {
  if (theme === 'dark') {
    return true;
  }
  if (theme === 'light') {
    return false;
  }
  return ancestorTheme(host) ?? systemDark();
}

export type ThemeUnsub = () => void;

/**
 * Subscribe to live theme changes when theme is `auto`.
 * Calls `onChange` immediately with the resolved value.
 */
export function watchDark(theme: OrbTheme, host: Element | null, onChange: (dark: boolean) => void): ThemeUnsub {
  const emit = () => onChange(resolveDark(theme, host));
  emit();

  if (theme !== 'auto') {
    return () => undefined;
  }

  const mq = typeof matchMedia !== 'undefined' ? matchMedia('(prefers-color-scheme: dark)') : null;
  const onMq = () => emit();
  mq?.addEventListener('change', onMq);

  let mo: MutationObserver | null = null;
  if (typeof MutationObserver !== 'undefined') {
    mo = new MutationObserver(emit);
    mo.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ['class', 'data-theme'],
      subtree: true,
    });
  }

  return () => {
    mq?.removeEventListener('change', onMq);
    mo?.disconnect();
  };
}

export function prefersReducedMotion(): boolean {
  return typeof matchMedia !== 'undefined' && matchMedia('(prefers-reduced-motion: reduce)').matches;
}

export function watchReducedMotion(onChange: (reduced: boolean) => void): ThemeUnsub {
  if (typeof matchMedia === 'undefined') {
    onChange(false);
    return () => undefined;
  }
  const mq = matchMedia('(prefers-reduced-motion: reduce)');
  const on = (e: MediaQueryListEvent) => onChange(e.matches);
  onChange(mq.matches);
  mq.addEventListener('change', on);
  return () => mq.removeEventListener('change', on);
}
