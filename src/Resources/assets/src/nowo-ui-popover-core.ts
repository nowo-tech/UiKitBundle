/** Gap between the toggle and the panel (px). */
export const GAP_PX = 4;
/** Minimum distance between the panel and the viewport edges (px). */
export const EDGE_PX = 8;

export type PlaceOptions = {
  /** The panel's left edge follows the anchor's left edge; otherwise the right edges align. */
  alignLeft?: boolean;
  /** Also keep it inside the viewport vertically when it fits neither below nor above. */
  keepInside?: boolean;
};

/**
 * Below the anchor, above it when the viewport has no room below, kept inside the viewport horizontally. Coordinates go through the
 * CSSOM (strict CSP), for a panel that is `position: fixed` or in the top layer. Shared by the popover menus and the emoji picker.
 */
export function placeBeside(anchor: Element, panel: HTMLElement, options: PlaceOptions = {}): void {
  const rect = anchor.getBoundingClientRect();
  const width = panel.offsetWidth;
  const height = panel.offsetHeight;
  const below = rect.bottom + GAP_PX;
  const above = rect.top - GAP_PX - height;
  let top = below + height > window.innerHeight - EDGE_PX && above >= EDGE_PX ? above : below;
  if (options.keepInside) {
    top = Math.max(EDGE_PX, Math.min(top, window.innerHeight - EDGE_PX - height));
  }
  const start = options.alignLeft ? rect.left : rect.right - width;
  const left = Math.min(Math.max(EDGE_PX, start), window.innerWidth - EDGE_PX - width);
  panel.style.top = `${Math.round(top)}px`;
  panel.style.left = `${Math.round(Math.max(EDGE_PX, left))}px`;
}
