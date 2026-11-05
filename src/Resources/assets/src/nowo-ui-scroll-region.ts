/**
 * Focusable scroll regions (2.28, `js/nowo-ui-scroll-region.js`, opt-in: add `scroll-region` to `script_names`).
 *
 * A table wrap (`[data-nowo-ui-region="table"]`, the `table` partial and the list scaffold) or any element marked
 * `[data-nowo-ui-scroll-region]` whose content overflows horizontally gets `tabindex="0"`, so keyboard users can scroll
 * it (axe `scrollable-region-focusable`), and `role="region"` with an accessible name: `data-nowo-ui-scroll-label`,
 * else the table's `aria-label` or caption text (no name = no role, only the tab stop). When the overflow goes away
 * the attributes this script set are removed again (never ones the host wrote).
 *
 * Content inserted later (list pagination "load more", dialog fragments) is covered: one `MutationObserver` on the
 * document finds new regions, one `ResizeObserver` watches each region and its first child, and every pass is
 * throttled to one animation frame. No `resize` listener. No inline styles or handlers (CSP).
 */

export const REGION = '[data-nowo-ui-region="table"], [data-nowo-ui-scroll-region]';
export const MARK = 'data-nowo-ui-scroll-focusable';

/** Whether an element's content is wider than its box. */
export function overflows(element: HTMLElement): boolean {
  return element.scrollWidth > element.clientWidth + 1;
}

/** The accessible name of a region: its label attribute, else the table's aria-label or caption text. */
export function regionName(element: HTMLElement): string {
  const explicit = element.getAttribute('data-nowo-ui-scroll-label');
  if (explicit !== null && explicit.trim() !== '') {
    return explicit.trim();
  }
  const table = element.querySelector('table');
  const label = table?.getAttribute('aria-label');
  if (label && label.trim() !== '') {
    return label.trim();
  }
  return table?.querySelector('caption')?.textContent?.trim() ?? '';
}

/** Makes one region focusable while it overflows, and undoes only what this script set when it does not. */
export function syncRegion(element: HTMLElement): void {
  const marked = element.hasAttribute(MARK);
  if (overflows(element)) {
    if (marked || element.hasAttribute('tabindex')) {
      return;
    }
    element.setAttribute('tabindex', '0');
    const name = regionName(element);
    const roles: string[] = [];
    if (name !== '' && !element.hasAttribute('role')) {
      element.setAttribute('role', 'region');
      roles.push('role');
      if (!element.hasAttribute('aria-label') && !element.hasAttribute('aria-labelledby')) {
        element.setAttribute('aria-label', name);
        roles.push('aria-label');
      }
    }
    element.setAttribute(MARK, ['tabindex', ...roles].join(' '));
  } else if (marked) {
    (element.getAttribute(MARK) ?? '').split(' ').forEach((name) => {
      if (name !== '') element.removeAttribute(name);
    });
    element.removeAttribute(MARK);
  }
}

let frame = 0;
let resizeObserver: ResizeObserver | null = null;
const watched = new WeakSet<Element>();

/** Syncs every region of `root` (and starts watching its size). */
export function syncRegions(root: ParentNode = document): void {
  root.querySelectorAll<HTMLElement>(REGION).forEach((region) => {
    syncRegion(region);
    if (resizeObserver && !watched.has(region)) {
      watched.add(region);
      resizeObserver.observe(region);
      if (region.firstElementChild) resizeObserver.observe(region.firstElementChild);
    }
  });
}

/** One pass per animation frame, whatever the number of mutations / resizes. */
export function schedule(): void {
  if (frame !== 0) return;
  const run = (): void => {
    frame = 0;
    syncRegions();
  };
  frame = typeof window.requestAnimationFrame === 'function' ? window.requestAnimationFrame(run) : (setTimeout(run, 16) as unknown as number);
}

let bound = false;

export function bindNowoUiScrollRegion(): void {
  if (bound) return;
  bound = true;
  if (typeof ResizeObserver === 'function') {
    resizeObserver = new ResizeObserver(() => schedule());
  }
  if (typeof MutationObserver === 'function') {
    new MutationObserver(() => schedule()).observe(document.documentElement, { childList: true, subtree: true });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => schedule(), { once: true });
  } else {
    schedule();
  }
}

bindNowoUiScrollRegion();
