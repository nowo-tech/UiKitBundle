/**
 * Pre-paint theme boot (1.15): include as a blocking classic script in <head> — inline through
 * `partials/_theme_boot.html.twig` (CSP nonce) or `js/nowo-ui-theme-boot.js` — before any stylesheet
 * paints, so the page never flashes the wrong theme / density.
 *
 * Inputs on <html> (all optional; server-rendered user / account preferences):
 *   data-nowo-ui-pref-theme       light | dark | (anything else = follow storage / OS)
 *   data-nowo-ui-pref-density     compact | (comfortable)
 *   data-nowo-ui-pref-motion      reduce | full | (system)
 *   data-nowo-ui-pref-font-scale  sm | lg | (md)
 *   data-nowo-ui-pref-contrast    more | (system → prefers-contrast)
 *   data-nowo-ui-pref-sidebar     expanded | rail | hidden (server default for the sidebar rail)
 *   (`data-user-*` with the same suffixes is accepted as an alias.)
 *
 * Storage keys (shared with `nowo-ui-theme.js`):
 *   data-nowo-ui-theme-storage    localStorage key (default `nowo-ui-theme`)
 *   data-nowo-ui-theme-cookie     optional cookie name read as a fallback and written with the theme
 *   data-nowo-ui-sidebar-storage / -cookie / -hidden-value   opt-in sidebar sync (see sidebar core)
 *
 * Outputs on <html>: data-theme (+ data-bs-theme for Bootstrap 5.3, 2.15.4; `data-nowo-ui-bs-theme="off"` opts out),
 * data-ui-density, data-motion, data-font-scale, data-contrast; resolved
 * values are mirrored to `window.nowoUiPrefs` for later scripts.
 */

import { sidebarOptionsFrom, syncSidebarStorageFromCookie } from './nowo-ui-sidebar-core';
import {
  applyTheme,
  isTheme,
  persistTheme,
  prefersMatch,
  readPref,
  readStoredTheme,
  type Theme,
} from './nowo-ui-theme-core';

export interface NowoUiPrefs {
  theme: Theme;
  density: 'comfortable' | 'compact';
  motion: 'system' | 'reduce' | 'full';
  fontScale: 'sm' | 'md' | 'lg';
  contrast: 'system' | 'more';
  sidebar: string;
}

declare global {
  interface Window {
    nowoUiPrefs?: NowoUiPrefs;
    nowoUiThemeBoot?: () => NowoUiPrefs;
  }
}

/** Resolve every preference and write the output attributes on <html>. */
export function applyThemeBoot(root: HTMLElement = document.documentElement): NowoUiPrefs {
  let prefs: NowoUiPrefs;
  try {
    const userTheme = readPref(root, 'theme');
    const theme: Theme = isTheme(userTheme)
      ? userTheme
      : (readStoredTheme(root) ?? (prefersMatch('(prefers-color-scheme: dark)') ? 'dark' : 'light'));
    if (isTheme(userTheme)) {
      persistTheme(userTheme, root);
    }

    const density = readPref(root, 'density') === 'compact' ? 'compact' : 'comfortable';
    const motionPref = readPref(root, 'motion');
    const motion = motionPref === 'reduce' || motionPref === 'full' ? motionPref : 'system';
    const fontPref = readPref(root, 'font-scale');
    const fontScale = fontPref === 'sm' || fontPref === 'lg' ? fontPref : 'md';
    const contrast = readPref(root, 'contrast') === 'more' || prefersMatch('(prefers-contrast: more)') ? 'more' : 'system';
    const sidebar = readPref(root, 'sidebar');

    if (root.hasAttribute('data-nowo-ui-sidebar-storage') || root.hasAttribute('data-nowo-ui-sidebar-cookie')) {
      syncSidebarStorageFromCookie(sidebar, sidebarOptionsFrom(root));
    }

    prefs = { theme, density, motion, fontScale, contrast, sidebar };
  } catch {
    prefs = { theme: 'light', density: 'comfortable', motion: 'system', fontScale: 'md', contrast: 'system', sidebar: '' };
  }

  applyTheme(prefs.theme, root);
  root.setAttribute('data-ui-density', prefs.density);
  root.setAttribute('data-motion', prefs.motion);
  root.setAttribute('data-font-scale', prefs.fontScale);
  root.setAttribute('data-contrast', prefs.contrast);
  window.nowoUiPrefs = prefs;

  return prefs;
}

window.nowoUiThemeBoot = () => applyThemeBoot();
applyThemeBoot();
