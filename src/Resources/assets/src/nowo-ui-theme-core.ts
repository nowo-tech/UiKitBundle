/**
 * Theme storage helpers shared by `nowo-ui-theme-boot.ts` (pre-paint) and `nowo-ui-theme.ts` (toggle),
 * so both read and write the same keys: `data-nowo-ui-theme-storage` (localStorage key, default
 * `nowo-ui-theme`) and the optional `data-nowo-ui-theme-cookie`, on the toggle or on <html>.
 * Side-effect free.
 */

export type Theme = 'light' | 'dark';

export const THEME_STORAGE_DEFAULT = 'nowo-ui-theme';
export const THEME_COOKIE_MAX_AGE = 31536000;

/** Read `data-nowo-ui-pref-{name}` (or the `data-user-{name}` alias) from an element. */
export function readPref(root: Element, name: string): string {
  return root.getAttribute(`data-nowo-ui-pref-${name}`) || root.getAttribute(`data-user-${name}`) || '';
}

export function isTheme(value: unknown): value is Theme {
  return value === 'light' || value === 'dark';
}

/** localStorage key for the theme (`data-nowo-ui-theme-storage` on the element or <html>). */
export function themeStorageKey(el: Element | null = null): string {
  return (
    el?.getAttribute('data-nowo-ui-theme-storage') ||
    document.documentElement.getAttribute('data-nowo-ui-theme-storage') ||
    THEME_STORAGE_DEFAULT
  );
}

/** Optional theme cookie name (`data-nowo-ui-theme-cookie` on the element or <html>). */
export function themeCookieName(el: Element | null = null): string {
  return el?.getAttribute('data-nowo-ui-theme-cookie') || document.documentElement.getAttribute('data-nowo-ui-theme-cookie') || '';
}

export function readCookie(cookie: string, name: string): string | null {
  if (name === '') {
    return null;
  }
  const prefix = `${name}=`;
  const part = cookie
    .split(';')
    .map((p) => p.trim())
    .find((p) => p.startsWith(prefix));

  return part === undefined ? null : decodeURIComponent(part.slice(prefix.length));
}

export function themeCookieString(name: string, theme: Theme, secure: boolean): string {
  return `${name}=${theme}; Path=/; Max-Age=${THEME_COOKIE_MAX_AGE}; SameSite=Lax${secure ? '; Secure' : ''}`;
}

/** Persist a theme under the configured keys (localStorage + optional cookie). Never throws. */
export function persistTheme(theme: Theme, el: Element | null = null): void {
  try {
    window.localStorage.setItem(themeStorageKey(el), theme);
  } catch {
    /* private mode */
  }
  const cookie = themeCookieName(el);
  if (cookie !== '') {
    document.cookie = themeCookieString(cookie, theme, window.location.protocol === 'https:');
  }
}

export function prefersMatch(query: string): boolean {
  return typeof window.matchMedia === 'function' && window.matchMedia(query).matches;
}

/** Stored theme: cookie first (server-visible), then localStorage. */
export function readStoredTheme(el: Element | null = null): Theme | null {
  const fromCookie = readCookie(document.cookie, themeCookieName(el));
  if (isTheme(fromCookie)) {
    return fromCookie;
  }
  const stored = window.localStorage.getItem(themeStorageKey(el));

  return isTheme(stored) ? stored : null;
}

