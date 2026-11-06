/**
 * Optional Stimulus peer — periodic server re-render of page regions (no kit IIFE).
 *
 * Same-origin GET of the current URL (or `url` value), then every `[data-auto-refresh-region]`
 * element inside the controller root is replaced by the element with the same id in the response.
 * CSP-safe (no inline script, no eval), paused while the tab is hidden or when the user clicks
 * the toggle. Regions without an id are never replaced.
 *
 * Same-origin is enforced (2.33.1, audit GT-L4): a `url` on another origin, or not http(s), is refused
 * with one console warning and no request; `mode: 'same-origin'` makes a redirect to another origin
 * fail, and the view stays. Same-origin redirects still work. Each region goes through the fragment
 * filter (docs/STIMULUS.md) before it is imported: no `<script>`, `on*`, `<object>`, `<embed>`…
 *
 * Identifier: `auto-refresh`
 * Values: `interval` (ms, default 10000, floor 2000), `url` (default: current location).
 * Targets: `toggle` (button, gets `aria-pressed`), `status` (gets the last refresh time).
 * Actions: `auto-refresh#toggle`, `auto-refresh#refresh`.
 * Events: `auto-refresh:refreshed` after regions were swapped.
 */

import { Controller } from '@hotwired/stimulus';
import { importForPage, sanitizeFragment } from '../src/nowo-ui-fragment-filter';

export const AUTO_REFRESH_MIN_INTERVAL_MS = 2_000;

/** Whether a `url` value may be polled: http(s) on this origin, resolved as `fetch` resolves it. */
export function isPollableUrl(value: string, base: string = document.baseURI): boolean {
  try {
    const url = new URL(value, base);
    return url.origin === window.location.origin && (url.protocol === 'http:' || url.protocol === 'https:');
  } catch {
    return false;
  }
}

/**
 * The filtered copy of a region, still in the response's inert document (an <img> copied into the live document
 * loads, and runs its `onerror`, before it is ever inserted). Null without one, or when the filter dropped it.
 */
function freshRegion(html: Document, id: string): Element | null {
  const fresh = html.getElementById(id);
  if (!fresh) {
    return null;
  }
  const holder = html.createDocumentFragment();
  holder.append(html.importNode(fresh, true));
  sanitizeFragment(holder);
  return holder.firstElementChild;
}

export default class extends Controller<HTMLElement> {
  static override values = {
    interval: { type: Number, default: 10_000 },
    url: { type: String, default: '' },
  };

  static override targets = ['toggle', 'status'];

  declare readonly intervalValue: number;
  declare readonly urlValue: string;
  declare readonly hasToggleTarget: boolean;
  declare readonly toggleTarget: HTMLElement;
  declare readonly hasStatusTarget: boolean;
  declare readonly statusTarget: HTMLElement;

  private timer: ReturnType<typeof setInterval> | null = null;
  private paused = false;
  private inFlight = false;
  private warned = false;

  override connect(): void {
    this.timer = setInterval(
      () => {
        void this.refresh();
      },
      Math.max(AUTO_REFRESH_MIN_INTERVAL_MS, this.intervalValue),
    );
    this.syncToggle();
  }

  override disconnect(): void {
    if (null !== this.timer) {
      clearInterval(this.timer);
      this.timer = null;
    }
  }

  toggle(): void {
    this.paused = !this.paused;
    this.syncToggle();
    if (!this.paused) {
      void this.refresh();
    }
  }

  async refresh(): Promise<void> {
    if (this.paused || this.inFlight || 'hidden' === document.visibilityState) {
      return;
    }
    if (this.urlValue !== '' && !isPollableUrl(this.urlValue)) {
      if (!this.warned) {
        this.warned = true;
        console.warn('UiKit auto-refresh: refused a url value on another origin.');
      }
      return;
    }
    this.inFlight = true;
    try {
      const response = await fetch(this.urlValue !== '' ? this.urlValue : window.location.href, {
        credentials: 'same-origin',
        mode: 'same-origin',
        headers: { Accept: 'text/html', 'X-Requested-With': 'auto-refresh' },
      });
      if (!response.ok) {
        return;
      }
      const html = new DOMParser().parseFromString(await response.text(), 'text/html');
      const dom = Element.prototype;
      let swapped = 0;
      this.element.querySelectorAll<HTMLElement>('[data-auto-refresh-region]').forEach((region) => {
        const id = dom.getAttribute.call(region, 'id');
        const fresh = id ? freshRegion(html, id) : null;
        if (fresh) {
          dom.replaceWith.call(region, importForPage(fresh));
          swapped += 1;
        }
      });
      if (this.hasStatusTarget) {
        this.statusTarget.textContent = new Date().toLocaleTimeString();
      }
      this.dispatch('refreshed', { detail: { regions: swapped } });
    } catch {
      // Network hiccup: keep the current view, the next tick retries.
    } finally {
      this.inFlight = false;
    }
  }

  private syncToggle(): void {
    if (this.hasToggleTarget) {
      this.toggleTarget.setAttribute('aria-pressed', this.paused ? 'false' : 'true');
    }
  }
}
