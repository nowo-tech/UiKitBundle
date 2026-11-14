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
 * throttled to one animation frame. Since 2.29.1 a pass after a mutation or a resize syncs only the regions concerned
 * (the whole document is scanned once, at start-up), regions that left the document are unobserved, and a label the
 * script set follows the caption. No `resize` listener. No inline styles or handlers (CSP).
 *
 * 2.48: `pre` and `table` elements inside rendered content (`.nowo-ui-rendered-content` or
 * `[data-nowo-ui-rendered-content]`) are regions too (the kit CSS makes them scroll sideways); a `table` gets only the
 * tab stop (its own table semantics stay). Anything inside an editable area (`[contenteditable]` other than "false")
 * is skipped, and attributes set before it became editable are removed. `data-nowo-ui-scroll-role="group"` uses
 * `role="group"` instead of `region` (any other value: `region`).
 */

export const REGION =
  '[data-nowo-ui-region="table"], [data-nowo-ui-scroll-region], :is(.nowo-ui-rendered-content, [data-nowo-ui-rendered-content]) :is(pre, table)';
export const MARK = 'data-nowo-ui-scroll-focusable';
/** Editable areas: their content is the editor's, never a scroll region of this script. */
export const EDITABLE = '[contenteditable]:not([contenteditable="false"])';

/** Whether an element is (inside) an editable area. */
export function isEditable(element: Element): boolean {
  return element.closest(EDITABLE) !== null;
}

/** The landmark role the script sets: `group` when asked (`data-nowo-ui-scroll-role`), else `region`. */
export function regionRole(element: HTMLElement): string {
  return element.getAttribute('data-nowo-ui-scroll-role') === 'group' ? 'group' : 'region';
}

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

/** Removes the attributes this script set on a region (only those). */
function unmark(element: HTMLElement): void {
  (element.getAttribute(MARK) ?? '').split(' ').forEach((name) => {
    if (name !== '') element.removeAttribute(name);
  });
  element.removeAttribute(MARK);
}

/** Makes one region focusable while it overflows, and undoes only what this script set when it does not. */
export function syncRegion(element: HTMLElement): void {
  const marked = element.hasAttribute(MARK);
  if (isEditable(element)) {
    if (marked) unmark(element);
    return;
  }
  if (overflows(element)) {
    if (marked) {
      // A label this script set follows the caption / label when it changes (2.29.1).
      const name = regionName(element);
      if (name !== '' && (element.getAttribute(MARK) ?? '').split(' ').includes('aria-label') && element.getAttribute('aria-label') !== name) {
        element.setAttribute('aria-label', name);
      }
      return;
    }
    if (element.hasAttribute('tabindex')) {
      return;
    }
    element.setAttribute('tabindex', '0');
    const name = element.tagName === 'TABLE' ? '' : regionName(element);
    const roles: string[] = [];
    if (name !== '' && !element.hasAttribute('role')) {
      element.setAttribute('role', regionRole(element));
      roles.push('role');
      if (!element.hasAttribute('aria-label') && !element.hasAttribute('aria-labelledby')) {
        element.setAttribute('aria-label', name);
        roles.push('aria-label');
      }
    }
    element.setAttribute(MARK, ['tabindex', ...roles].join(' '));
  } else if (marked) {
    unmark(element);
  }
}

let frame = 0;
let fullScan = false;
let resizeObserver: ResizeObserver | null = null;
/** Watched regions => the first child observed with them (2.29.1: forgotten once they leave the document). */
const watched = new Map<HTMLElement, Element | null>();
/** Regions to sync on the next pass (mutations and resizes name them; no full-document scan). */
const pending = new Set<HTMLElement>();

/** Starts (or keeps) watching the size of a region and of its current first child. */
function watch(region: HTMLElement): void {
  if (!resizeObserver) return;
  const child = region.firstElementChild;
  const known = watched.has(region);
  const previous = watched.get(region) ?? null;
  if (!known) resizeObserver.observe(region);
  if (!known || previous !== child) {
    if (previous) resizeObserver.unobserve(previous);
    if (child) resizeObserver.observe(child);
  }
  watched.set(region, child);
}

/**
 * Stops watching the regions (and their first child) that are no longer in the document, so a long-lived page with
 * many "load more" or dialog cycles does not keep detached wraps alive (2.29.1, audit ES-I9).
 */
export function forgetDisconnected(): void {
  watched.forEach((child, region) => {
    if (region.isConnected) return;
    resizeObserver?.unobserve(region);
    if (child) resizeObserver?.unobserve(child);
    watched.delete(region);
  });
}

/** How many regions are watched (tests and diagnostics). */
export function watchedCount(): number {
  return watched.size;
}

/** Syncs every region of `root` (and starts watching its size). */
export function syncRegions(root: ParentNode = document): void {
  forgetDisconnected();
  root.querySelectorAll<HTMLElement>(REGION).forEach((region) => {
    syncRegion(region);
    watch(region);
  });
}

/**
 * One pass per animation frame, whatever the number of mutations / resizes. Without arguments the pass scans the
 * whole document (start-up); with regions (2.29.1) it syncs only those, after forgetting the regions that left.
 */
export function schedule(regions?: Iterable<HTMLElement>): void {
  if (regions === undefined) {
    fullScan = true;
  } else {
    for (const region of regions) pending.add(region);
  }
  if (frame !== 0) return;
  const run = (): void => {
    frame = 0;
    const full = fullScan;
    const batch = Array.from(pending);
    fullScan = false;
    pending.clear();
    if (full) {
      syncRegions();
      return;
    }
    forgetDisconnected();
    batch.forEach((region) => {
      if (region.isConnected) {
        syncRegion(region);
        watch(region);
      }
    });
  };
  frame = typeof window.requestAnimationFrame === 'function' ? window.requestAnimationFrame(run) : (setTimeout(run, 16) as unknown as number);
}

/** The region an element belongs to (itself, or the closest ancestor region), or null. */
function regionOf(node: Node): HTMLElement | null {
  const element = node instanceof Element ? node : node.parentElement;

  return element?.closest<HTMLElement>(REGION) ?? null;
}

/**
 * The regions a batch of mutations concerns: inserted regions (and the ones inside inserted subtrees) and the regions
 * whose content changed (rows, caption text). Removals only ask for a pass, which forgets the regions that left.
 */
export function regionsOfMutations(records: MutationRecord[]): { regions: HTMLElement[]; removed: boolean } {
  const regions: HTMLElement[] = [];
  let removed = false;
  records.forEach((record) => {
    record.addedNodes.forEach((node) => {
      if (!(node instanceof HTMLElement)) return;
      if (node.matches(REGION)) regions.push(node);
      node.querySelectorAll<HTMLElement>(REGION).forEach((region) => regions.push(region));
    });
    if (record.removedNodes.length > 0) removed = true;
    // 2.48: an area that became (or stopped being) editable: the regions inside it follow.
    if (record.type === 'attributes' && record.target instanceof HTMLElement) {
      if (record.target.matches(REGION)) regions.push(record.target);
      record.target.querySelectorAll<HTMLElement>(REGION).forEach((region) => regions.push(region));
    }
    const owner = regionOf(record.target);
    if (owner) regions.push(owner);
  });

  return { regions, removed };
}

let bound = false;

export function bindNowoUiScrollRegion(): void {
  if (bound) return;
  bound = true;
  if (typeof ResizeObserver === 'function') {
    resizeObserver = new ResizeObserver((entries) => {
      const regions: HTMLElement[] = [];
      entries.forEach((entry) => {
        const region = regionOf(entry.target);
        if (region) regions.push(region);
      });
      schedule(regions);
    });
  }
  if (typeof MutationObserver === 'function') {
    new MutationObserver((records) => {
      const { regions, removed } = regionsOfMutations(records);
      if (regions.length > 0 || removed) schedule(regions);
    }).observe(document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['contenteditable'] });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => schedule(), { once: true });
  } else {
    schedule();
  }
}

bindNowoUiScrollRegion();
