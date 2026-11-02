/**
 * Optional Stimulus peer (2.23) — pagination modes `infinite` / `responsive` of a server-paginated list.
 *
 * Markup (printed by the `pagination` / `pagination_compact` partials for `View\Pagination::withMode('infinite' |
 * 'responsive')`, never for the default `pager`): on the pagination root `data-controller="list-pagination"` with
 * `data-nowo-ui-pagination-mode`, `-narrow` / `-wide` (`pager` | `infinite`), `-query` (media query of the narrow
 * side), `-auto` (automatic loads, default 3), `-next` (URL of the next page), `-loaded-text` (`%count%`) and
 * `-error-text`; inside it the pager links (`.nowo-ui-pagination__nav`, the no-JS fallback), the hidden
 * `[data-nowo-ui-pagination-more-region]` (sentinel `[data-nowo-ui-pagination-sentinel]` + "Load more" button
 * `[data-nowo-ui-pagination-more]`) and the live region `[data-nowo-ui-pagination-status]`. The rows live in
 * `[data-nowo-ui-list-rows]` (the list scaffold marks its `<tbody>`; a host list marks its own container) in the
 * nearest ancestor of the pagination that holds one.
 *
 * In `infinite` mode the peer hides the pager links and shows the "Load more" button (always present for keyboard
 * users and browsers without IntersectionObserver). The sentinel loads the next page automatically when it scrolls
 * into view, `-auto` times at most; then only the button loads more. A load fetches the `-next` URL (the server
 * renders the full page with the same filters / sort / per_page: hosts change no query), appends only the children
 * of the response's matching `[data-nowo-ui-list-rows]` (scripts dropped) and the row confirm dialogs it lacks,
 * takes the response's pagination (links, info line, next URL), sets `?page=` with `history.replaceState`, announces
 * "N more rows loaded" in the live region and dispatches `nowo-ui:rows-appended` (bubbles; detail `{rows, page, url}`)
 * on the rows region so other scripts can initialise the new rows (Stimulus connects controllers by itself). Focus
 * stays on the button; when the last page is in, it moves to the first appended row. Only same-origin URLs load;
 * redirects are refused (`redirect: 'error'`) and a non-`basic` or off-origin response is rejected; imported nodes
 * lose `<script>`, `on*`, `srcdoc` and `javascript:` URLs (audit CS L1).
 *
 * In `responsive` mode `-narrow` applies while `-query` matches, `-wide` otherwise, switched live on matchMedia
 * change. Going back to `pager` stops loading, shows the links again and keeps the rows already appended. The mode in
 * force is reflected as `data-nowo-ui-pagination-active="pager|infinite"` on the root (styling / tests); while a page
 * loads the root has `data-nowo-ui-pagination-loading` and the rows region `aria-busy="true"`.
 *
 * Identifier: `list-pagination`.
 */

import { Controller } from '@hotwired/stimulus';

export type PaginationMode = 'pager' | 'infinite';

export const ROWS_SELECTOR = '[data-nowo-ui-list-rows]';
export const ROOT_SELECTOR = '[data-nowo-ui-pagination-mode]';
export const APPENDED_EVENT = 'nowo-ui:rows-appended';

/** `pager` or `infinite` for a mode attribute value (anything else: `fallback`). */
export function normaliseMode(value: string | null, fallback: PaginationMode): PaginationMode {
  return value === 'pager' || value === 'infinite' ? value : fallback;
}

/** The mode in force: `infinite` for `infinite`, else narrow / wide by the media query. */
export function resolveMode(root: Element, matches: boolean): PaginationMode {
  if (root.getAttribute('data-nowo-ui-pagination-mode') !== 'responsive') {
    return 'infinite';
  }

  return matches
    ? normaliseMode(root.getAttribute('data-nowo-ui-pagination-narrow'), 'infinite')
    : normaliseMode(root.getAttribute('data-nowo-ui-pagination-wide'), 'pager');
}

/** Absolute same-origin URL of a next-page link, null when missing or off-site. */
export function sameOriginUrl(href: string | null, base: string = window.location.href): URL | null {
  if (href === null || href.trim() === '') {
    return null;
  }
  try {
    const url = new URL(href, base);

    return url.origin === new URL(base).origin ? url : null;
  } catch {
    return null;
  }
}

const URL_ATTRIBUTES = ['href', 'src', 'action', 'formaction', 'xlink:href', 'data', 'poster'];

/**
 * Defence in depth for imported markup (2.23, audit CS L1/I1): removes `<script>`, every `on*` attribute, `srcdoc`
 * and `javascript:` / `vbscript:` URLs (control characters and blanks ignored, any case) under `root`, root included.
 * Kit markup (rows, confirm dialogs, pager links) carries none of them, so it imports unchanged.
 */
export function sanitizeImported(root: Element): void {
  root.querySelectorAll('script').forEach((script) => script.remove());
  [root, ...Array.from(root.querySelectorAll('*'))].forEach((el) => {
    Array.from(el.attributes).forEach((attr) => {
      const name = attr.name.toLowerCase();
      const unsafeUrl = URL_ATTRIBUTES.includes(name) && /^(?:javascript|vbscript):/i.test(attr.value.replace(/[\u0000-\u0020\u007f]+/g, ''));
      if (name.startsWith('on') || name === 'srcdoc' || unsafeUrl) {
        el.removeAttribute(attr.name);
      }
    });
  });
}

/** Whether a fetched response is a plain same-origin one (no cross-origin redirect, not opaque / CORS). */
export function isSameOriginResponse(response: { type?: string; url?: string; redirected?: boolean }): boolean {
  if (typeof response.type === 'string' && response.type !== '' && response.type !== 'basic') {
    return false;
  }

  return typeof response.url !== 'string' || response.url === '' || sameOriginUrl(response.url) !== null;
}

/** The rows region of a pagination root: in the nearest ancestor that holds one. */
export function findRowsRegion(root: Element): Element | null {
  for (let node = root.parentElement; node !== null; node = node.parentElement) {
    const rows = node.querySelector(ROWS_SELECTOR);
    if (rows !== null) {
      return rows;
    }
  }

  return null;
}

export class ListPaginationController extends Controller {
  private media: MediaQueryList | null = null;

  private observer: IntersectionObserver | null = null;

  private mode: PaginationMode = 'pager';

  private autoLoads = 0;

  private loading = false;

  /** A load failed: no more automatic loads (the button retries). */
  private failed = false;

  private readonly onMediaChange = (): void => this.apply();

  private readonly onMoreClick = (event: Event): void => {
    event.preventDefault();
    this.failed = false;
    void this.loadMore(true);
  };

  override connect(): void {
    const query = this.element.getAttribute('data-nowo-ui-pagination-query') ?? '(max-width: 900px)';
    if (this.element.getAttribute('data-nowo-ui-pagination-mode') === 'responsive' && typeof window.matchMedia === 'function') {
      this.media = window.matchMedia(query);
      this.media.addEventListener('change', this.onMediaChange);
    }
    this.button()?.addEventListener('click', this.onMoreClick);
    this.apply();
  }

  override disconnect(): void {
    this.media?.removeEventListener('change', this.onMediaChange);
    this.media = null;
    this.button()?.removeEventListener('click', this.onMoreClick);
    this.stopObserving();
  }

  /** Current mode (`pager` | `infinite`). */
  currentMode(): PaginationMode {
    return this.mode;
  }

  /** Applies the mode in force: links or load-more region, observer on or off. Appended rows always stay. */
  apply(): void {
    this.mode = resolveMode(this.element, this.media?.matches ?? false);
    this.element.setAttribute('data-nowo-ui-pagination-active', this.mode);
    const infinite = this.mode === 'infinite';
    const nav = this.element.querySelector('.nowo-ui-pagination__nav');
    if (nav instanceof HTMLElement) {
      nav.hidden = infinite;
    }
    const region = this.region();
    if (region) {
      region.hidden = !infinite || this.nextUrl() === null;
    }
    if (infinite) {
      this.observe();
    } else {
      this.stopObserving();
    }
  }

  /**
   * Loads the next page into the rows region. `fromButton`: a user action (focus handling, does not count as an
   * automatic load). Resolves to the number of rows appended (0 when nothing was loaded).
   */
  async loadMore(fromButton = false): Promise<number> {
    const url = this.nextUrl();
    const rows = findRowsRegion(this.element);
    if (this.loading || url === null || rows === null || this.mode !== 'infinite') {
      return 0;
    }
    this.setLoading(true, rows);
    try {
      const response = await fetch(url.toString(), {
        credentials: 'same-origin',
        // audit CS L1: never follow a redirect (an open redirect could hand over another origin's markup)
        redirect: 'error',
        headers: { Accept: 'text/html', 'X-Nowo-Ui-Pagination': '1' },
      });
      if (!response.ok || !isSameOriginResponse(response)) {
        throw new Error(`HTTP ${response.status}`);
      }
      const html = await response.text();
      const appended = this.merge(new DOMParser().parseFromString(html, 'text/html'), rows, url);
      this.announce((this.element.getAttribute('data-nowo-ui-pagination-loaded-text') ?? '%count% more rows loaded').replace('%count%', String(appended.length)));
      if (fromButton && this.nextUrl() === null && appended[0] instanceof HTMLElement) {
        appended[0].tabIndex = -1;
        appended[0].focus();
      }

      return appended.length;
    } catch {
      this.failed = true;
      this.stopObserving();
      this.announce(this.element.getAttribute('data-nowo-ui-pagination-error-text') ?? 'Could not load more rows.');

      return 0;
    } finally {
      this.setLoading(false, rows);
      this.apply();
    }
  }

  private merge(doc: Document, rows: Element, url: URL): Element[] {
    const index = Array.from(document.querySelectorAll(ROWS_SELECTOR)).indexOf(rows);
    const incoming = doc.querySelectorAll(ROWS_SELECTOR)[Math.max(0, index)];
    const appended: Element[] = [];
    if (incoming) {
      sanitizeImported(incoming);
      Array.from(incoming.children).forEach((child) => {
        const row = document.importNode(child, true);
        rows.appendChild(row);
        appended.push(row);
      });
    }
    this.mergeConfirms(doc, rows);

    const roots = Array.from(document.querySelectorAll(ROOT_SELECTOR));
    const nextRoot = doc.querySelectorAll(ROOT_SELECTOR)[Math.max(0, roots.indexOf(this.element))];
    const next = nextRoot ? nextRoot.getAttribute('data-nowo-ui-pagination-next') : null;
    if (next !== null && sameOriginUrl(next) !== null) {
      this.element.setAttribute('data-nowo-ui-pagination-next', next);
    } else {
      this.element.removeAttribute('data-nowo-ui-pagination-next');
    }
    if (nextRoot) {
      for (const selector of ['.nowo-ui-pagination__nav', '.nowo-ui-pagination__info']) {
        const mine = this.element.querySelector(selector);
        const theirs = nextRoot.querySelector(selector);
        if (mine && theirs) {
          sanitizeImported(theirs);
          mine.replaceChildren(...Array.from(theirs.childNodes).map((node) => document.importNode(node, true)));
        }
      }
    }
    if (typeof history !== 'undefined' && typeof history.replaceState === 'function') {
      history.replaceState(history.state, '', url.toString());
    }
    const page = Number.parseInt(url.searchParams.get('page') ?? '', 10);
    rows.dispatchEvent(new CustomEvent(APPENDED_EVENT, { bubbles: true, detail: { rows: appended, page: Number.isNaN(page) ? null : page, url: url.toString() } }));

    return appended;
  }

  /** Row confirm dialogs of the new rows (region `confirms` after the table) that the page does not have yet. */
  private mergeConfirms(doc: Document, rows: Element): void {
    const dialogs = Array.from(doc.querySelectorAll('[data-nowo-ui-region="confirms"] > [id]')).filter((el) => document.getElementById(el.id) === null);
    if (dialogs.length === 0) {
      return;
    }
    const wrap = rows.closest('[data-nowo-ui-region="table"]') ?? rows.parentElement;
    let target = wrap?.nextElementSibling?.matches('[data-nowo-ui-region="confirms"]') ? wrap.nextElementSibling : null;
    if (target === null && wrap) {
      target = document.createElement('div');
      target.className = 'nowo-ui-list-page__confirms';
      target.setAttribute('data-nowo-ui-region', 'confirms');
      wrap.after(target);
    }
    dialogs.forEach((dialog) => {
      sanitizeImported(dialog);
      target?.appendChild(document.importNode(dialog, true));
    });
  }

  private observe(): void {
    const limit = Number.parseInt(this.element.getAttribute('data-nowo-ui-pagination-auto') ?? '3', 10);
    const sentinel = this.element.querySelector('[data-nowo-ui-pagination-sentinel]');
    if (this.failed || this.observer !== null || sentinel === null || typeof IntersectionObserver === 'undefined' || this.nextUrl() === null || this.autoLoads >= (Number.isNaN(limit) ? 3 : limit)) {
      return;
    }
    this.observer = new IntersectionObserver((entries) => {
      if (entries.some((entry) => entry.isIntersecting) && !this.loading) {
        this.autoLoads += 1;
        this.stopObserving();
        void this.loadMore(false);
      }
    });
    this.observer.observe(sentinel);
  }

  private stopObserving(): void {
    this.observer?.disconnect();
    this.observer = null;
  }

  private setLoading(loading: boolean, rows: Element): void {
    this.loading = loading;
    this.element.toggleAttribute('data-nowo-ui-pagination-loading', loading);
    rows.setAttribute('aria-busy', loading ? 'true' : 'false');
    this.button()?.setAttribute('aria-disabled', loading ? 'true' : 'false');
  }

  private announce(text: string): void {
    const status = this.element.querySelector('[data-nowo-ui-pagination-status]');
    if (status) {
      status.textContent = text;
    }
  }

  private nextUrl(): URL | null {
    return sameOriginUrl(this.element.getAttribute('data-nowo-ui-pagination-next'));
  }

  private region(): HTMLElement | null {
    const region = this.element.querySelector('[data-nowo-ui-pagination-more-region]');

    return region instanceof HTMLElement ? region : null;
  }

  private button(): HTMLElement | null {
    const button = this.element.querySelector('[data-nowo-ui-pagination-more]');

    return button instanceof HTMLElement ? button : null;
  }
}

export default ListPaginationController;
