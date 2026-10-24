/**
 * Listing preference cookie (listing customizer): one cookie per listing, read by the server
 * (`Listing\CookieListingPreferenceStore`) to render the chosen columns on first paint.
 *
 * Value: URI-encoded JSON `{"v": 1, "columns": [{key, visible, merged?}], "filters": [{key, visible}], "mode": …}`,
 * every member optional (the responsive table may store the mode alone). Written with path `/` (configurable),
 * SameSite=Lax, max-age one year, `Secure` on https. Writes larger than {@link LISTING_COOKIE_MAX_BYTES} encoded
 * bytes are refused (browsers drop cookies over ~4 KiB silently).
 */

export const LISTING_COOKIE_VERSION = 1;
export const LISTING_COOKIE_MAX_BYTES = 4000;
export const LISTING_COOKIE_MAX_AGE = 31536000;

export type ListingCookieData = Record<string, unknown>;

export type ListingCookieOptions = {
  path?: string;
  maxAge?: number;
  maxBytes?: number;
};

/** The parsed listing cookie, null when it is missing or not a JSON object. */
export function readListingCookie(name: string): ListingCookieData | null {
  const prefix = `${name}=`;
  for (const part of document.cookie.split(';')) {
    const item = part.trim();
    if (!item.startsWith(prefix)) {
      continue;
    }
    try {
      const data: unknown = JSON.parse(decodeURIComponent(item.slice(prefix.length)));

      return null !== data && 'object' === typeof data && !Array.isArray(data) ? (data as ListingCookieData) : null;
    } catch {
      return null;
    }
  }

  return null;
}

/** Writes the listing cookie; false (nothing written) when the encoded value is too large. */
export function writeListingCookie(name: string, data: ListingCookieData, options: ListingCookieOptions = {}): boolean {
  const value = encodeURIComponent(JSON.stringify({ ...data, v: LISTING_COOKIE_VERSION }));
  if (value.length > (options.maxBytes ?? LISTING_COOKIE_MAX_BYTES)) {
    return false;
  }
  document.cookie = `${name}=${value}${attributes(options.path ?? '/', options.maxAge ?? LISTING_COOKIE_MAX_AGE)}`;

  return true;
}

/** Merges `patch` into the stored cookie (e.g. the table mode next to the layout) and writes it. */
export function patchListingCookie(name: string, patch: ListingCookieData, options: ListingCookieOptions = {}): boolean {
  return writeListingCookie(name, { ...(readListingCookie(name) ?? {}), ...patch }, options);
}

export function deleteListingCookie(name: string, path = '/'): void {
  document.cookie = `${name}=${attributes(path, 0)}`;
}

function attributes(path: string, maxAge: number): string {
  const secure = 'https:' === window.location.protocol ? '; Secure' : '';

  return `; path=${path}; max-age=${maxAge}; SameSite=Lax${secure}`;
}
