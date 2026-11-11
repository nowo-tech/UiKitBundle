/**
 * Remembered layout state without a host store (2.48): side-effect-free helpers shared by `nowo-ui-side-panel.ts`,
 * `nowo-ui-rail.ts` and the optional pre-paint script `nowo-ui-layout-state.ts`.
 *
 * The host store stays primary: a host that keeps the open / collapsed state per user renders it on the server and does
 * NOT print the persist attributes. Only markup that carries them uses the browser fallback:
 *   - side panel  `aside[data-nowo-ui-side-panel][data-nowo-ui-side-panel-persist="<key>"]`
 *                 → localStorage `nowo-ui-panel:<key>` = `open` | `closed`
 *   - rail toggle `[data-nowo-ui-rail-toggle][data-nowo-ui-rail-persist="<key>"]` (its host: the closest
 *                 `[data-nowo-ui-rail-host]`) → localStorage `nowo-ui-rail:<key>` = `collapsed` | `expanded`;
 *                 ignored when the toggle also has a host store URL (`data-nowo-ui-rail-state-url`)
 * Every storage access is wrapped in try / catch (private mode, blocked site data, quota): without storage the server
 * state simply stays. Once a stored state is applied, `<html>` mirrors it as `data-nowo-ui-panel-<key>` /
 * `data-nowo-ui-rail-<key>` (key lower-cased, other characters as `-`) for host CSS.
 *
 * A stored `open` never opens a panel that would show as a drawer (overlay layout, or a viewport under its
 * `data-drawer-below` breakpoint): a drawer covering the page on load is worse than a closed panel. For the same reason
 * the side panel only writes the state while it is an inline column. The same rule covers the server state (audit
 * LV-L2): a panel with `data-drawer-below="lg|xl"` that the server rendered open (a host store remembers the inline
 * column) starts closed when the page loads at or under its breakpoint, where it would be a modal drawer; nothing is
 * stored or dispatched, so the host store keeps `open` for wider screens. The 2.25 default (`md`) and overlay panels
 * keep their server state.
 */

export const PANEL_PERSIST_ATTR = 'data-nowo-ui-side-panel-persist';
export const RAIL_PERSIST_ATTR = 'data-nowo-ui-rail-persist';
export const PANEL_STORAGE_PREFIX = 'nowo-ui-panel:';
export const RAIL_STORAGE_PREFIX = 'nowo-ui-rail:';
export const RAIL_HOST = '[data-nowo-ui-rail-host]';
export const RAIL_TOGGLE = '[data-nowo-ui-rail-toggle]';

/** Drawer breakpoints of the side panel (`drawer_below`): the panel is a drawer at or under these widths. */
export const DRAWER_QUERIES = {
  md: '(max-width: 767px)',
  lg: '(max-width: 1199px)',
  xl: '(max-width: 1439px)',
} as const;

export type DrawerBreakpoint = keyof typeof DRAWER_QUERIES;

/** The media query under which a panel is a drawer (`data-drawer-below`; anything else is `md`, the 2.25 default). */
export function drawerQueryOf(panel: Element): string {
  const value = panel.getAttribute('data-drawer-below');

  return value === 'lg' || value === 'xl' ? DRAWER_QUERIES[value] : DRAWER_QUERIES.md;
}

/** Whether a media query matches (false where matchMedia is missing). */
export function mediaMatches(query: string): boolean {
  return typeof window.matchMedia === 'function' && window.matchMedia(query).matches === true;
}

/** Whether the panel shows as an overlay drawer now. */
export function panelIsDrawer(panel: Element): boolean {
  return panel.getAttribute('data-layout') === 'overlay' || mediaMatches(drawerQueryOf(panel));
}

/** A usable persist key (1–64 of `A-Z a-z 0-9 _ . : -`), else null: nothing is read or written. */
export function persistKey(value: string | null | undefined): string | null {
  return typeof value === 'string' && /^[A-Za-z0-9_.:-]{1,64}$/.test(value) ? value : null;
}

/** The `<html>` attribute suffix of a key (lower case, other characters as `-`). */
export function attributeSuffix(key: string): string {
  return key.toLowerCase().replace(/[^a-z0-9-]/g, '-');
}

export function readStored(name: string): string | null {
  try {
    return window.localStorage.getItem(name);
  } catch {
    return null;
  }
}

/** Writes a value; false when storage is unavailable (nothing else happens). */
export function writeStored(name: string, value: string): boolean {
  try {
    window.localStorage.setItem(name, value);
    return true;
  } catch {
    return false;
  }
}

/** The stored open state of a panel key: true / false, or null when nothing (valid) is stored. */
export function storedPanelOpen(key: string): boolean | null {
  const value = readStored(PANEL_STORAGE_PREFIX + key);

  return value === 'open' ? true : value === 'closed' ? false : null;
}

/** The stored collapsed state of a rail key: true / false, or null when nothing (valid) is stored. */
export function storedRailCollapsed(key: string): boolean | null {
  const value = readStored(RAIL_STORAGE_PREFIX + key);

  return value === 'collapsed' ? true : value === 'expanded' ? false : null;
}

function mirror(prefix: 'panel' | 'rail', key: string, value: string): void {
  document.documentElement.setAttribute(`data-nowo-ui-${prefix}-${attributeSuffix(key)}`, value);
}

/** Stores a panel state (when the panel carries a persist key) and mirrors it on `<html>`. */
export function rememberPanel(panel: Element, open: boolean): void {
  const key = persistKey(panel.getAttribute(PANEL_PERSIST_ATTR));
  if (!key) return;
  writeStored(PANEL_STORAGE_PREFIX + key, open ? 'open' : 'closed');
  mirror('panel', key, open ? 'open' : 'closed');
}

/** Stores a rail state under a key and mirrors it on `<html>`. */
export function rememberRail(key: string, collapsed: boolean): void {
  writeStored(RAIL_STORAGE_PREFIX + key, collapsed ? 'collapsed' : 'expanded');
  mirror('rail', key, collapsed ? 'collapsed' : 'expanded');
}

/**
 * Applies the stored state to one persisted panel (only `hidden`; the side-panel script syncs the toggles).
 * Returns whether the panel's visibility changed.
 */
export function applyStoredPanel(panel: HTMLElement): boolean {
  const key = persistKey(panel.getAttribute(PANEL_PERSIST_ATTR));
  if (!key) return false;
  const open = storedPanelOpen(key);
  if (open === null) return false;
  mirror('panel', key, open ? 'open' : 'closed');
  if (open === !panel.hidden) return false;
  if (open && panelIsDrawer(panel)) return false;
  panel.hidden = !open;
  return true;
}

/** Marker the side-panel script sets on every panel it has started (the start rules below run once per panel). */
export const PANEL_READY_ATTR = 'data-nowo-ui-side-panel-ready';

/**
 * Start rule for a server-open panel (2.48, audit LV-L2): a panel with `data-drawer-below="lg|xl"` (not the overlay
 * layout) that is open while the viewport is at or under its breakpoint starts closed (only `hidden`; the side-panel
 * script syncs the toggles). Returns whether it closed the panel.
 */
export function closeStartingDrawer(panel: HTMLElement): boolean {
  const below = panel.getAttribute('data-drawer-below');
  if (below !== 'lg' && below !== 'xl') return false;
  if (panel.hidden || panel.getAttribute('data-layout') === 'overlay' || !panelIsDrawer(panel)) return false;
  panel.hidden = true;
  return true;
}

/** The rail host a toggle drives: its closest host, else the host around (or at) the element it controls. */
export function railHostOf(toggle: Element): HTMLElement | null {
  const own = toggle.closest<HTMLElement>(RAIL_HOST);
  if (own) return own;
  const id = toggle.getAttribute('aria-controls');
  const controlled = id ? document.getElementById(id) : null;

  return controlled?.closest<HTMLElement>(RAIL_HOST) ?? null;
}

/** Whether a rail host is collapsed now. */
export function railIsCollapsed(host: Element): boolean {
  return host.getAttribute('data-nowo-ui-rail') === 'collapsed';
}

/**
 * Applies the stored state to the host of one persisted rail toggle (`data-nowo-ui-rail` + the toggle's
 * `aria-expanded`). Returns null while the host is not in the document yet (the pre-paint script retries), else
 * whether something was applied.
 */
export function applyStoredRail(toggle: HTMLElement): boolean | null {
  const key = persistKey(toggle.getAttribute(RAIL_PERSIST_ATTR));
  // A host store (state URL) rendered the state on the server: it wins over the browser fallback.
  if (!key || toggle.hasAttribute('data-nowo-ui-rail-state-url')) return false;
  const host = railHostOf(toggle);
  if (!host) return null;
  const collapsed = storedRailCollapsed(key);
  if (collapsed === null) return false;
  mirror('rail', key, collapsed ? 'collapsed' : 'expanded');
  host.setAttribute('data-nowo-ui-rail', collapsed ? 'collapsed' : 'expanded');
  toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
  return true;
}

const seen = new WeakSet<Element>();

/**
 * Applies the stored states to every persisted panel and rail toggle of `root` not handled yet, and the start rule of
 * {@link closeStartingDrawer} to every `lg` / `xl` panel.
 */
export function applyStoredStates(root: ParentNode = document): void {
  root.querySelectorAll<HTMLElement>(`[data-nowo-ui-side-panel][${PANEL_PERSIST_ATTR}], [data-nowo-ui-side-panel][data-drawer-below]`).forEach((panel) => {
    if (seen.has(panel)) return;
    seen.add(panel);
    // Already started by the side-panel script: its state is the user's now.
    if (panel.hasAttribute(PANEL_READY_ATTR)) return;
    applyStoredPanel(panel);
    closeStartingDrawer(panel);
  });
  root.querySelectorAll<HTMLElement>(`${RAIL_TOGGLE}[${RAIL_PERSIST_ATTR}]`).forEach((toggle) => {
    if (seen.has(toggle)) return;
    if (applyStoredRail(toggle) !== null) seen.add(toggle);
  });
}

/**
 * Pre-paint: applies the stored states now and, while the document is still being parsed, after every parser chunk
 * (a MutationObserver: its callback runs at the end of the parser task, before the browser can render it). Stops at
 * DOMContentLoaded. Returns a function that stops it earlier.
 */
export function watchStoredStates(doc: Document = document): () => void {
  applyStoredStates(doc);
  if (doc.readyState !== 'loading' || typeof MutationObserver !== 'function') return () => undefined;
  const observer = new MutationObserver(() => applyStoredStates(doc));
  observer.observe(doc.documentElement, { childList: true, subtree: true });
  const stop = (): void => {
    observer.disconnect();
    doc.removeEventListener('DOMContentLoaded', done);
  };
  const done = (): void => {
    applyStoredStates(doc);
    stop();
  };
  doc.addEventListener('DOMContentLoaded', done, { once: true });

  return stop;
}
