/**
 * Collapsible column rail (2.48, partial `rail_toggle`, `js/nowo-ui-rail.js`, opt-in: add `rail` to `script_names`).
 *
 * Markup (server-rendered; works without this script through the toggle's `href`):
 *   host   `[data-nowo-ui-rail-host][data-nowo-ui-rail="expanded|collapsed"]` (the column; host markup)
 *   body   the element the toggle controls (`aria-controls`), usually `[data-nowo-ui-rail-body]`: hidden by the kit CSS
 *          while collapsed (display: none, so it leaves the tab order and the accessibility tree)
 *   toggle `[data-nowo-ui-rail-toggle]` inside the host (a button, or a link to the URL of the other state), with
 *          `aria-expanded`, `data-nowo-ui-rail-label-collapse` / `-expand` and a `[data-nowo-ui-rail-toggle-label]` text
 *
 * - Click / Enter / Space (Space also on the link form): toggles `data-nowo-ui-rail` on the host, syncs `aria-expanded`
 *   and the label of every toggle of the host, keeps focus on the toggle (focus inside the body when collapsing moves to
 *   the toggle), dispatches `nowo-ui:rail:change` `{ id, collapsed, source }` (bubbling, on the host).
 * - Persistence, in this order: the host store (`data-nowo-ui-rail-state-url` + `data-nowo-ui-rail-state-token`: POST
 *   JSON `{ panel, open: collapsed }` with `X-CSRF-Token`, `panel` = `data-nowo-ui-rail-state-panel`, else the persist
 *   key, else the host id), else the localStorage fallback (`data-nowo-ui-rail-persist="<key>"`), else nothing (the
 *   host can listen to the event). The stored state is applied on start (before the first paint with
 *   js/nowo-ui-layout-state.js in <head>).
 * - Optional shortcut per toggle (`data-nowo-ui-rail-shortcut`, e.g. `Alt+Shift+P`; none by default).
 * - Reduced motion: the width transition is CSS only, and none under prefers-reduced-motion / data-motion="reduce".
 * - `window.NowoUiRail`: collapse / expand / toggle / isCollapsed (by host id).
 * - Delegated listeners, no inline handlers or style attributes (strict CSP); no state kept between pages.
 */

import { applyStoredRail, persistKey, RAIL_HOST, RAIL_PERSIST_ATTR, RAIL_TOGGLE, railHostOf, railIsCollapsed, rememberRail } from './nowo-ui-layout-state-core';
import { matchesShortcut, parseShortcut } from './nowo-ui-shortcut-core';

export { RAIL_HOST, RAIL_TOGGLE };

export type RailSource = 'toggle' | 'shortcut' | 'api';

/** Every toggle of a host, in document order (inside it, or controlling an element inside it). */
export function togglesOf(host: HTMLElement): HTMLElement[] {
  return [...document.querySelectorAll<HTMLElement>(RAIL_TOGGLE)].filter((toggle) => railHostOf(toggle) === host);
}

/** aria-expanded and the visually hidden label of a toggle follow the state. */
export function syncToggle(toggle: HTMLElement, collapsed: boolean): void {
  toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
  const label = toggle.getAttribute(collapsed ? 'data-nowo-ui-rail-label-expand' : 'data-nowo-ui-rail-label-collapse');
  const text = toggle.querySelector<HTMLElement>('[data-nowo-ui-rail-toggle-label]');
  if (label !== null && text) text.textContent = label;
}

/**
 * The store URL resolved against the page when it is on the page's own origin (audit LV-I3), else null: the CSRF
 * header is never sent to another origin.
 */
export function sameOriginUrl(url: string): string | null {
  try {
    const resolved = new URL(url, window.location.href);
    return resolved.origin === window.location.origin ? resolved.href : null;
  } catch {
    return null;
  }
}

/**
 * Sends the state to the host store; true when a store URL is configured (localStorage is then not used). A URL on
 * another origin (or an invalid one) is configured but never fetched: the state is then not saved.
 */
export function sendToHostStore(toggle: HTMLElement, host: HTMLElement, collapsed: boolean): boolean {
  const configured = toggle.getAttribute('data-nowo-ui-rail-state-url');
  if (!configured) return false;
  const url = sameOriginUrl(configured);
  if (!url) return true;
  const panel = toggle.getAttribute('data-nowo-ui-rail-state-panel') || persistKey(toggle.getAttribute(RAIL_PERSIST_ATTR)) || host.id;
  const headers: Record<string, string> = { 'Content-Type': 'application/json', Accept: 'application/json' };
  const token = toggle.getAttribute('data-nowo-ui-rail-state-token');
  if (token) headers['X-CSRF-Token'] = token;
  if (typeof fetch === 'function') {
    try {
      // A failed save only loses the remembered state; the page keeps working.
      fetch(url, { method: 'POST', mode: 'same-origin', credentials: 'same-origin', headers, body: JSON.stringify({ panel, open: collapsed }) }).catch(() => undefined);
    } catch {
      // fetch threw synchronously: same as a failed save.
    }
  }
  return true;
}

function persist(toggle: HTMLElement, host: HTMLElement, collapsed: boolean): void {
  if (sendToHostStore(toggle, host, collapsed)) return;
  const key = persistKey(toggle.getAttribute(RAIL_PERSIST_ATTR));
  if (key) rememberRail(key, collapsed);
}

/** Collapses or expands a host. Returns whether the state changed. */
export function setCollapsed(host: HTMLElement, collapsed: boolean, source: RailSource = 'api', via: HTMLElement | null = null): boolean {
  if (railIsCollapsed(host) === collapsed) return false;
  const toggles = togglesOf(host);
  const toggle = via ?? toggles[0] ?? null;
  const active = document.activeElement;
  host.setAttribute('data-nowo-ui-rail', collapsed ? 'collapsed' : 'expanded');
  toggles.forEach((each) => syncToggle(each, collapsed));
  // Focus inside the part that is now hidden goes to the toggle (the expand button), never to <body>.
  if (collapsed && toggle && active instanceof HTMLElement && active !== toggle && host.contains(active) && !toggles.includes(active)) toggle.focus();
  if (toggle) persist(toggle, host, collapsed);
  host.dispatchEvent(new CustomEvent('nowo-ui:rail:change', { bubbles: true, detail: { id: host.id, collapsed, source } }));
  return true;
}

function onClick(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) return;
  const toggle = target.closest<HTMLElement>(RAIL_TOGGLE);
  const host = toggle ? railHostOf(toggle) : null;
  if (!toggle || !host) return;
  // Audit LV-I6: a modified or non-primary click on the link form keeps the browser's behaviour (new tab / window).
  if (toggle instanceof HTMLAnchorElement && (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0)) return;
  event.preventDefault();
  setCollapsed(host, !railIsCollapsed(host), 'toggle', toggle);
}

function onKeydown(event: KeyboardEvent): void {
  const active = document.activeElement;
  if (event.key === ' ' && active instanceof HTMLElement && active.matches(`a${RAIL_TOGGLE}`)) {
    // role=button links activate on Space like buttons.
    event.preventDefault();
    active.click();
    return;
  }
  for (const toggle of document.querySelectorAll<HTMLElement>(`${RAIL_TOGGLE}[data-nowo-ui-rail-shortcut]`)) {
    const shortcut = parseShortcut(toggle.getAttribute('data-nowo-ui-rail-shortcut'));
    if (!shortcut || !matchesShortcut(event, shortcut)) continue;
    const typing = active instanceof HTMLElement && (active.isContentEditable || active.matches('input, textarea, select'));
    if (typing && !shortcut.ctrl && !shortcut.alt && !shortcut.meta) return;
    const host = railHostOf(toggle);
    if (!host) continue;
    event.preventDefault();
    setCollapsed(host, !railIsCollapsed(host), 'shortcut', toggle);
    toggle.focus();
    return;
  }
}

const byId = (id: string): HTMLElement => {
  const host = document.getElementById(id);
  if (!(host instanceof HTMLElement) || !host.matches(RAIL_HOST)) throw new Error(`No rail host #${id}.`);
  return host;
};

export const api = {
  collapse: (id: string): boolean => setCollapsed(byId(id), true, 'api'),
  expand: (id: string): boolean => setCollapsed(byId(id), false, 'api'),
  toggle: (id: string): boolean => {
    const host = byId(id);
    setCollapsed(host, !railIsCollapsed(host), 'api');
    return railIsCollapsed(host);
  },
  isCollapsed: (id: string): boolean => railIsCollapsed(byId(id)),
};

/** Applies remembered states (persist keys only; a host store has rendered its state) and syncs every toggle. */
export function markRailsReady(root: ParentNode = document): void {
  root.querySelectorAll<HTMLElement>(RAIL_TOGGLE).forEach((toggle) => {
    applyStoredRail(toggle);
    const host = railHostOf(toggle);
    if (host) syncToggle(toggle, railIsCollapsed(host));
    toggle.setAttribute('data-nowo-ui-rail-ready', '');
  });
}

let bound = false;

export function bindNowoUiRail(): void {
  (window as unknown as { NowoUiRail: typeof api }).NowoUiRail = api;
  markRailsReady();
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => markRailsReady(), { once: true });
  if (bound) return;
  bound = true;
  document.addEventListener('click', onClick);
  document.addEventListener('keydown', onKeydown);
}

bindNowoUiRail();
