/**
 * Side panel / drawer (2.25, partials `side_panel`, `side_panel_toggle`, `side_panel_container`).
 *
 * Markup (server-rendered, works without this script): `aside[data-nowo-ui-side-panel]` (hidden when closed, named by
 * its `<id>-title` heading), toggles `[data-nowo-ui-side-panel-toggle][aria-controls=<id>]` (links to the host URL of
 * the other state, or buttons), a close control `[data-nowo-ui-side-panel-close]`, a backdrop and a polite status
 * region (`[data-for=<id>]`, outside the panel).
 *
 * - Open: shows the panel, `aria-expanded="true"` on its toggles, focus on the heading (or the element passed to
 *   `open(id, { focus })`), announces `data-nowo-ui-side-panel-opened`.
 * - Close (toggle, close control, Escape inside the panel, backdrop): hides it, returns focus to the toggle that opened
 *   it when focus was inside, announces `data-nowo-ui-side-panel-closed`.
 * - Inline column (>= 768 px, `data-layout="inline"`): no focus trap. Overlay drawer (<= 767 px, or
 *   `data-layout="overlay"`): backdrop shown, the container's `[data-nowo-ui-side-panel-main]` inert (only an `inert`
 *   this script set is removed again; the host's own is kept), Tab cycles inside.
 * - Optional shortcut per toggle (`data-nowo-ui-side-panel-shortcut`, e.g. `Alt+Shift+C`; none by default).
 * - Events (bubbling, on the panel, after the change): `nowo-ui:side-panel:open` / `nowo-ui:side-panel:close`
 *   `{ id, source }` — the host persists the state from them.
 * - `window.NowoUiSidePanel`: open / close / toggle / isOpen / setCount (badge; never announced; 0 hides the badge
 *   and its suffix, so the toggle's name is just its label).
 * - Delegated listeners, no inline handlers or style attributes (strict CSP).
 */

export const PANEL = '[data-nowo-ui-side-panel]';
export const TOGGLE = '[data-nowo-ui-side-panel-toggle]';
export const CLOSE = '[data-nowo-ui-side-panel-close]';
export const BACKDROP = '[data-nowo-ui-side-panel-backdrop]';
export const STATUS = '[data-nowo-ui-side-panel-status]';
export const CONTAINER = '[data-nowo-ui-side-panel-container]';
export const MAIN = '[data-nowo-ui-side-panel-main]';
/** Viewports where an inline panel becomes an overlay drawer. */
export const DRAWER_QUERY = '(max-width: 767px)';

export type SidePanelSource = 'toggle' | 'close' | 'escape' | 'backdrop' | 'shortcut' | 'api';
export type OpenOptions = { focus?: HTMLElement | string | false; source?: SidePanelSource; opener?: HTMLElement | null };
export type Shortcut = { ctrl: boolean; alt: boolean; shift: boolean; meta: boolean; key: string };

const FOCUSABLE =
  'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"]), [contenteditable="true"]';

/** Toggle that opened each panel (focus goes back there on close). */
const openers = new WeakMap<HTMLElement, HTMLElement>();
/** Main columns this script made inert (an `inert` set by the host is never removed). */
const inertByPanel = new WeakSet<HTMLElement>();

/** The panel with this id, or null. */
export function panelById(id: string): HTMLElement | null {
  const el = document.getElementById(id);
  return el instanceof HTMLElement && el.matches(PANEL) ? el : null;
}

/** Every toggle that controls a panel, in document order. */
export function togglesOf(panel: HTMLElement): HTMLElement[] {
  return [...document.querySelectorAll<HTMLElement>(TOGGLE)].filter((toggle) => toggle.getAttribute('aria-controls') === panel.id);
}

const companion = (selector: string, panel: HTMLElement): HTMLElement | null =>
  [...document.querySelectorAll<HTMLElement>(selector)].find((el) => el.getAttribute('data-for') === panel.id) ?? null;

export function isOpen(panel: HTMLElement): boolean {
  return !panel.hidden;
}

/** Whether the panel shows as an overlay drawer now (layout overlay, or a phone-sized viewport). */
export function isDrawer(panel: HTMLElement): boolean {
  return panel.dataset.layout === 'overlay' || (typeof window.matchMedia === 'function' && window.matchMedia(DRAWER_QUERY).matches);
}

/** Says `text` in the panel's polite status region (a repeated text is changed so it is announced again). */
export function announce(panel: HTMLElement, text: string): void {
  const status = companion(STATUS, panel);
  if (!status || text === '') return;
  status.textContent = status.textContent === text ? `${text} ` : text;
}

/** Backdrop, inert main column and the `data-drawer` marker follow the open state and the current mode. */
export function applyMode(panel: HTMLElement): void {
  const drawer = isOpen(panel) && isDrawer(panel);
  const backdrop = companion(BACKDROP, panel);
  if (backdrop) backdrop.hidden = !drawer;
  panel.toggleAttribute('data-drawer', drawer);
  const main = panel.closest(CONTAINER)?.querySelector<HTMLElement>(MAIN);
  if (!main) return;
  if (drawer && !main.hasAttribute('inert')) {
    // Switching to the drawer while focus is in the content: keep it usable by moving it into the panel first.
    const active = document.activeElement;
    if (active instanceof Node && main.contains(active)) document.getElementById(`${panel.id}-title`)?.focus();
    main.setAttribute('inert', '');
    inertByPanel.add(main);
  } else if (!drawer && inertByPanel.has(main)) {
    main.removeAttribute('inert');
    inertByPanel.delete(main);
  }
}

function emit(panel: HTMLElement, name: string, source: SidePanelSource): void {
  panel.dispatchEvent(new CustomEvent(name, { bubbles: true, detail: { id: panel.id, source } }));
}

function focusInto(panel: HTMLElement, focus: OpenOptions['focus']): void {
  if (focus === false) return;
  let target: HTMLElement | null = null;
  if (typeof focus === 'string') target = panel.querySelector<HTMLElement>(focus);
  else if (focus instanceof HTMLElement) target = focus;
  target ??= document.getElementById(`${panel.id}-title`);
  target?.focus();
}

function setExpanded(panel: HTMLElement, open: boolean): void {
  togglesOf(panel).forEach((toggle) => toggle.setAttribute('aria-expanded', open ? 'true' : 'false'));
}

/** Opens a panel (or only moves focus into it when it is already open). Returns whether it was opened. */
export function openPanel(panel: HTMLElement, options: OpenOptions = {}): boolean {
  if (isOpen(panel)) {
    focusInto(panel, options.focus);
    return false;
  }
  panel.hidden = false;
  setExpanded(panel, true);
  if (options.opener) openers.set(panel, options.opener);
  else openers.delete(panel);
  applyMode(panel);
  focusInto(panel, options.focus);
  announce(panel, panel.getAttribute('data-nowo-ui-side-panel-opened') ?? '');
  emit(panel, 'nowo-ui:side-panel:open', options.source ?? 'api');
  return true;
}

/** Closes a panel; focus returns to its opener (else its first toggle) when it was inside. Returns whether it closed. */
export function closePanel(panel: HTMLElement, source: SidePanelSource = 'api'): boolean {
  if (!isOpen(panel)) return false;
  const active = document.activeElement;
  const hadFocus = active instanceof Node && panel.contains(active);
  panel.hidden = true;
  setExpanded(panel, false);
  applyMode(panel);
  if (hadFocus) (openers.get(panel) ?? togglesOf(panel)[0])?.focus();
  announce(panel, panel.getAttribute('data-nowo-ui-side-panel-closed') ?? '');
  emit(panel, 'nowo-ui:side-panel:close', source);
  return true;
}

export function togglePanel(panel: HTMLElement, source: SidePanelSource = 'api', opener: HTMLElement | null = null): boolean {
  if (isOpen(panel)) {
    closePanel(panel, source);
    return false;
  }
  openPanel(panel, { source, opener });
  return true;
}

/** Updates the count badge (and its visually hidden suffix) of every toggle of a panel; 0 hides them. */
export function setCount(panel: HTMLElement, count: number, suffix?: string): void {
  const has = count > 0;
  togglesOf(panel).forEach((toggle) => {
    const badge = toggle.querySelector<HTMLElement>('[data-nowo-ui-side-panel-badge]');
    if (badge) {
      badge.textContent = has ? String(count) : '';
      badge.hidden = !has;
    }
    const tail = toggle.querySelector<HTMLElement>('[data-nowo-ui-side-panel-badge-suffix]');
    if (tail) {
      if (suffix !== undefined) tail.textContent = suffix;
      tail.hidden = !has;
    }
  });
}

/** Parses `Alt+Shift+C` (modifiers Control / Ctrl, Alt, Shift, Meta; the last part is the key). */
export function parseShortcut(value: string | null | undefined): Shortcut | null {
  const parts = (value ?? '').split('+').map((part) => part.trim()).filter((part) => part !== '');
  const key = parts.pop();
  if (!key) return null;
  const mods = parts.map((part) => part.toLowerCase());
  return { ctrl: mods.includes('control') || mods.includes('ctrl'), alt: mods.includes('alt'), shift: mods.includes('shift'), meta: mods.includes('meta'), key: key.toLowerCase() };
}

/** Whether a key event is the shortcut (letters and digits by physical key, so Alt / Option layouts still match). */
export function matchesShortcut(event: KeyboardEvent, shortcut: Shortcut): boolean {
  if (event.ctrlKey !== shortcut.ctrl || event.altKey !== shortcut.alt || event.shiftKey !== shortcut.shift || event.metaKey !== shortcut.meta) return false;
  if (/^[a-z]$/.test(shortcut.key)) return event.code === `Key${shortcut.key.toUpperCase()}` || event.key.toLowerCase() === shortcut.key;
  if (/^[0-9]$/.test(shortcut.key)) return event.code === `Digit${shortcut.key}` || event.key === shortcut.key;
  return event.key.toLowerCase() === shortcut.key;
}

const isTyping = (el: Element | null): boolean =>
  el instanceof HTMLElement && (el.isContentEditable || el.matches('input, textarea, select, [contenteditable="true"]'));

function focusables(panel: HTMLElement): HTMLElement[] {
  return [...panel.querySelectorAll<HTMLElement>(FOCUSABLE)].filter((el) => !el.closest('[hidden]'));
}

function onClick(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) return;
  const toggle = target.closest<HTMLElement>(TOGGLE);
  if (toggle) {
    const panel = panelById(toggle.getAttribute('aria-controls') ?? '');
    if (!panel) return;
    event.preventDefault();
    togglePanel(panel, 'toggle', toggle);
    return;
  }
  const close = target.closest<HTMLElement>(CLOSE);
  const closing = close?.closest<HTMLElement>(PANEL);
  if (closing) {
    event.preventDefault();
    closePanel(closing, 'close');
    return;
  }
  const backdrop = target.closest<HTMLElement>(BACKDROP);
  const owner = backdrop ? panelById(backdrop.getAttribute('data-for') ?? '') : null;
  if (owner) closePanel(owner, 'backdrop');
}

function onKeydown(event: KeyboardEvent): void {
  const active = document.activeElement;
  const panel = active instanceof Element ? active.closest<HTMLElement>(PANEL) : null;
  if (event.key === 'Escape' && panel && isOpen(panel)) {
    // A dialog or menu opened inside the panel closes first.
    if (active instanceof Element && active.closest('dialog[open], [popover]:popover-open') && !active.closest('dialog[open], [popover]:popover-open')?.contains(panel)) return;
    event.preventDefault();
    closePanel(panel, 'escape');
    return;
  }
  if (event.key === 'Tab' && panel && isOpen(panel) && isDrawer(panel)) {
    const items = focusables(panel);
    const first = items[0];
    const last = items[items.length - 1];
    if (!first || !last) return;
    if (event.shiftKey && (active === first || active === document.getElementById(`${panel.id}-title`))) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && active === last) {
      event.preventDefault();
      first.focus();
    }
    return;
  }
  if (event.key === ' ' && active instanceof HTMLElement && active.matches(`a${TOGGLE}, a${CLOSE}`)) {
    // role=button links activate on Space like buttons.
    event.preventDefault();
    active.click();
    return;
  }
  for (const toggle of document.querySelectorAll<HTMLElement>(`${TOGGLE}[data-nowo-ui-side-panel-shortcut]`)) {
    const shortcut = parseShortcut(toggle.getAttribute('data-nowo-ui-side-panel-shortcut'));
    if (!shortcut || !matchesShortcut(event, shortcut)) continue;
    if (isTyping(active) && !shortcut.ctrl && !shortcut.alt && !shortcut.meta) return;
    const target = panelById(toggle.getAttribute('aria-controls') ?? '');
    if (!target) continue;
    event.preventDefault();
    togglePanel(target, 'shortcut', toggle);
    return;
  }
}

function onResize(): void {
  document.querySelectorAll<HTMLElement>(PANEL).forEach((panel) => applyMode(panel));
}

const byId = (id: string): HTMLElement => {
  const panel = panelById(id);
  if (!panel) throw new Error(`No side panel #${id}.`);
  return panel;
};

export const api = {
  open: (id: string, options: Omit<OpenOptions, 'source'> = {}): boolean => openPanel(byId(id), { ...options, source: 'api' }),
  close: (id: string): boolean => closePanel(byId(id), 'api'),
  toggle: (id: string): boolean => togglePanel(byId(id), 'api'),
  isOpen: (id: string): boolean => isOpen(byId(id)),
  setCount: (id: string, count: number, suffix?: string): void => setCount(byId(id), count, suffix),
};

let bound = false;

/** Marks the panels driven by this script and applies the drawer mode to those rendered open. */
export function markSidePanelsReady(root: ParentNode = document): void {
  root.querySelectorAll<HTMLElement>(PANEL).forEach((panel) => {
    panel.setAttribute('data-nowo-ui-side-panel-ready', '');
    applyMode(panel);
  });
}

export function bindNowoUiSidePanel(): void {
  (window as unknown as { NowoUiSidePanel: typeof api }).NowoUiSidePanel = api;
  markSidePanelsReady();
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => markSidePanelsReady(), { once: true });
  if (bound) return;
  bound = true;
  document.addEventListener('click', onClick);
  document.addEventListener('keydown', onKeydown);
  window.addEventListener('resize', onResize);
}

bindNowoUiSidePanel();
