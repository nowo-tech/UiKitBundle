/**
 * Compact breadcrumb collapse (2.27, partial `header_crumbs`; opt-in: not in the default `scripts` list, add
 * `header-crumbs` to `script_names`). The server prints the whole trail with the "…" item `hidden`, so without this
 * script every level shows on one line, each truncated. For every `[data-nowo-ui-header-crumbs]` with
 * `data-collapsible="true"` and without `data-collapsed`, on start-up and for trails inserted later (a
 * `MutationObserver` on `document.body`, for Turbo and lazy HTML), it:
 *  - sets `data-collapsed="true"` (the CSS then hides the `--middle` levels);
 *  - removes `hidden` from the "…" item (`[data-nowo-ui-header-crumbs-more]`).
 * That is its whole job: no state, no per-element set-up. The "…" disclosure itself (placement, aria-expanded,
 * Escape, outside click, item click) is the default `nowo-ui-popover.js`. Without the Popover API the trail stays
 * un-collapsed, so nothing ever becomes unreachable. No inline handlers or styles (CSP).
 */

export const TRAIL = '[data-nowo-ui-header-crumbs][data-collapsible="true"]:not([data-collapsed])';
export const MORE = '[data-nowo-ui-header-crumbs-more]';

/** Whether the browser has the Popover API the "…" panel needs. */
export function supportsPopover(): boolean {
  return typeof HTMLElement.prototype.togglePopover === 'function';
}

/** Collapses one trail (idempotent: a trail with `data-collapsed` is left alone by the selector). */
export function collapseTrail(trail: HTMLElement): void {
  trail.setAttribute('data-collapsed', 'true');
  trail.querySelector<HTMLElement>(MORE)?.removeAttribute('hidden');
}

/** Collapses the trails under `root` (and `root` itself); returns how many changed. */
export function enhanceHeaderCrumbs(root: ParentNode = document): number {
  if (!supportsPopover()) {
    return 0;
  }
  const trails: HTMLElement[] = root instanceof HTMLElement && root.matches(TRAIL) ? [root] : [];
  root.querySelectorAll<HTMLElement>(TRAIL).forEach((trail) => trails.push(trail));
  trails.forEach(collapseTrail);

  return trails.length;
}

let observer: MutationObserver | null = null;
let bound = false;

/** Start-up: enhance now (or on DOMContentLoaded) and watch `document.body` for trails inserted later. */
export function bindNowoUiHeaderCrumbs(): void {
  if (bound || !supportsPopover()) {
    return;
  }
  bound = true;
  const start = (): void => {
    enhanceHeaderCrumbs();
    observer = new MutationObserver(() => enhanceHeaderCrumbs());
    observer.observe(document.body, { childList: true, subtree: true });
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start, { once: true });
  } else {
    start();
  }
}

/** Stops watching; a later bind starts again (tests, hosts tearing the page down). */
export function unbindNowoUiHeaderCrumbs(): void {
  observer?.disconnect();
  observer = null;
  bound = false;
}

bindNowoUiHeaderCrumbs();
