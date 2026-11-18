/**
 * Context menus (2.54, opt-in runtime `context-menu`): a right-click, the ContextMenu key, Shift+F10 or a long press
 * on a row or card opens the SAME items as the kebab / row-action menu that row or card already renders.
 *
 * The menu is a proxy: each of its items forwards a `click()` to the kebab item it mirrors, so links, POST forms,
 * confirm toggles, dialogs and Stimulus actions behave exactly as from the kebab. It adds no item and no
 * authorization surface: the host already filtered the kebab by permission.
 *
 * Which element has a context menu (resolved by delegation, so rows added later work too):
 *  - `[data-nowo-ui-context-menu]` (explicit): an empty value takes the first kebab / menu / row actions inside the
 *    element; a value is the id of one of them (or of its panel, `#` optional); `off` keeps the native menu (opt-out
 *    under the theme flag).
 *  - `[data-nowo-ui-context-menu-source]`, printed by the `kebab` / `row_actions` / `menu` partials with
 *    `theme.context_menus: true` or their `context_menu` option: the nearest SCOPE around it (a table row, an ARIA row,
 *    a UiKit card, or any `[data-nowo-ui-context-menu-scope]`).
 *
 * The browser's own menu is kept with text selected, on inputs, textareas, selects, contenteditable and links, and
 * with Shift or Ctrl held. Menu: WAI-ARIA menu (Up / Down / Home / End, typeahead, Enter / Space, Escape returns focus
 * to the element, Right / Left for submenus, Tab closes), one open at a time, closed on an outside click, scroll,
 * resize, window blur, Turbo cache and when another kit popover opens. Placement: a `popover="manual"` panel in the
 * top layer (a `position: fixed` panel without the Popover API) at the pointer, kept inside the viewport; for the
 * keyboard, under the focused element. Coordinates go through the CSSOM; no inline script or style attribute (CSP).
 *
 * 2.58: a horizontal ROW of items (`[data-nowo-ui-menu-row]` in the kebab, e.g. quick reactions) is shown as one
 * `role="group"` row: Left / Right move inside it, Up / Down leave it. With `data-nowo-ui-sheet` on the source (kebab
 * partial `sheet: true`) or on the host, the menu opens as a bottom sheet above a backdrop on narrow screens
 * (SHEET_QUERY): full width, CSS-placed, a tap on the backdrop closes it without reaching the page.
 *
 * Events on the host element: `nowo-ui:context-menu:open` (cancelable; detail `{source, trigger}` with trigger
 * `pointer` | `keyboard` | `touch`) and `nowo-ui:context-menu:close` (detail `{source}`).
 */

import { recordInvoker } from './nowo-ui-focus-return';
import { EDGE_PX, GAP_PX } from './nowo-ui-popover-core';

/** Explicit host attribute. */
export const ATTR = 'data-nowo-ui-context-menu';
/** Marker printed by the kit partials on a kebab / menu / row-actions root (theme flag or `context_menu` option). */
export const SOURCE_ATTR = 'data-nowo-ui-context-menu-source';
/** Optional host-supplied name of the menu (on the host or the source). */
export const LABEL_ATTR = 'data-nowo-ui-context-menu-label';
/** Elements that own the context menu of a marked source inside them. */
export const SCOPE = '[data-nowo-ui-context-menu-scope], tr, [role="row"], [data-nowo-ui-card]';
/** Kebab / menu / row-action roots an explicit host can point at. */
export const SOURCE = `[${SOURCE_ATTR}], [data-nowo-ui-kebab], [data-nowo-ui-menu], [data-nowo-ui-popover-menu], .nowo-ui-row-actions`;
/** Panels inside a source (the first one in document order is the source's own). */
export const PANEL = '[data-nowo-ui-popover-panel], .nowo-ui-kebab__menu, .nowo-ui-menu__panel, [role="menu"]';
/** Targets whose browser menu is kept. */
export const NATIVE_TARGET = 'input, textarea, select, [contenteditable]:not([contenteditable="false"]), a[href]';
/** Controls mirrored as items. */
const CONTROL = 'a[href], button, input[type="submit"], input[type="button"], [role="menuitem"]';
/** Nested menus mirrored as submenus. */
const NESTED_ROOT = '[data-nowo-ui-menu], [data-nowo-ui-kebab], [data-nowo-ui-popover-menu], details';
/** 2.58: a horizontal row of items inside a kebab panel (quick reactions…). */
export const ROW_ATTR = 'data-nowo-ui-menu-row';
/** 2.58: opt-in bottom sheet on narrow screens (on the source or the host). */
export const SHEET_ATTR = 'data-nowo-ui-sheet';
/** 2.58: the viewport width below which a sheet-enabled menu opens as a bottom sheet. */
export const SHEET_QUERY = '(max-width: 40rem)';
/** Toggles of a menu root (their label names a submenu; they are never items). */
const TOGGLE = 'summary, [data-nowo-ui-popover-trigger]';

/** Long-press delay (ms) and the finger movement (px) that cancels it. */
export const LONG_PRESS_MS = 500;
export const MOVE_TOLERANCE_PX = 10;
/** Typeahead buffer lifetime (ms). */
export const TYPEAHEAD_MS = 500;
/** How long the browser's own `contextmenu` / `click` following a keyboard or touch open is swallowed (ms). */
export const SUPPRESS_MS = 800;
/** After the pointerup that ends a long press, how long its click is still swallowed (ms). */
export const CLICK_GRACE_MS = 100;

export type Trigger = 'pointer' | 'keyboard' | 'touch';

export type ItemEntry = { kind: 'item'; label: string; original: HTMLElement; danger: boolean; disabled: boolean; current: boolean; icon: Element | null };

export type Entry =
  | ItemEntry
  | { kind: 'separator' }
  | { kind: 'row'; label: string; children: ItemEntry[] }
  | { kind: 'submenu'; label: string; children: Entry[] };

export type Resolved = { host: HTMLElement; source: HTMLElement };

/** `rows`: each item of a row => the items of its row (in order); items outside a row are not in the map. */
type Level = { panel: HTMLElement; items: HTMLElement[]; parent: HTMLElement | null; rows: Map<HTMLElement, HTMLElement[]> };

type OpenState = {
  host: HTMLElement;
  /** 2.58: the backdrop of a bottom sheet, or null (a menu at the pointer / element). */
  backdrop: HTMLElement | null;
  source: HTMLElement;
  returnTo: HTMLElement | null;
  levels: Level[];
  /** Page scroll position when the menu opened: a late `scroll` event without a move keeps it open. */
  scrollX: number;
  scrollY: number;
};

const entries = new WeakMap<HTMLElement, Entry>();
let open: OpenState | null = null;
let sequence = 0;
let typeahead = '';
let typeaheadTimer: ReturnType<typeof setTimeout> | null = null;
let suppressContextMenuUntil = 0;
let suppressClick = false;
/** 2.58.1: the swallowed click may land inside the menu (a sheet under the finger that opened it). */
let suppressInMenu = false;
let suppressClickTimer: ReturnType<typeof setTimeout> | null = null;
/** The pointer whose long press opened the menu: the click that ends it follows its pointerup. */
let suppressPointer: number | null = null;
type Press = { id: number; x: number; y: number; target: Element; timer: ReturnType<typeof setTimeout> };
let press: Press | null = null;

/** The visible label of a control: aria-label, else its text, else title / value. */
export function labelOf(el: Element): string {
  const aria = el.getAttribute('aria-label')?.trim();
  if (aria) {
    return aria;
  }
  const text = (el.textContent ?? '').replace(/\s+/g, ' ').trim();
  if (text) {
    return text;
  }
  return (el.getAttribute('title') ?? (el instanceof HTMLInputElement ? el.value : '')).trim();
}

function isDisabled(el: HTMLElement): boolean {
  return (
    (el as HTMLButtonElement).disabled === true ||
    el.matches(':disabled') ||
    el.closest('fieldset[disabled]') !== null ||
    el.getAttribute('aria-disabled') === 'true' ||
    el.classList.contains('is-disabled')
  );
}

/**
 * Whether a kebab entry is rendered, judged between the entry and its own panel: the closed popover / <details> panel
 * itself is never rendered, so it does not count; `display: none` on the entry or a wrapper inside the panel, or
 * `visibility: hidden` on the entry, does.
 */
function isRendered(el: Element, container: Element): boolean {
  for (let node: Element | null = el; node && node !== container && !node.matches(PANEL); node = node.parentElement) {
    const style = getComputedStyle(node);
    if (style.display === 'none' || (node === el && (style.visibility === 'hidden' || style.visibility === 'collapse'))) {
      return false;
    }
  }
  return true;
}

function isDanger(el: HTMLElement): boolean {
  return /(^|[\s_-])danger\b/.test(el.className) || el.classList.contains('nowo-ui-action--delete');
}

/** The panel of a menu root: its first PANEL in document order, else the root itself (a row-actions cluster). */
export function panelOf(root: HTMLElement): HTMLElement {
  if (root.matches(PANEL)) {
    return root;
  }
  return root.querySelector<HTMLElement>(PANEL) ?? root;
}

function toggleLabel(root: Element): string {
  const toggle = root.querySelector(TOGGLE);
  return toggle ? labelOf(toggle) : '';
}

/** The entries of a container, in document order: controls, separators and nested menus (submenus). */
function walk(container: Element): Entry[] {
  const out: Entry[] = [];
  const visit = (el: Element): void => {
    // Inert or aria-hidden entries are not offered: what the kebab hides stays hidden.
    if (el.matches('dialog, template, script, style, [hidden], [inert], [aria-hidden="true"]') || el.matches(TOGGLE)) {
      return;
    }
    if (el.matches(`[${ROW_ATTR}]`)) {
      // A row holds items only: separators, submenus and rows inside it are not offered.
      const children = walk(el).filter((entry): entry is ItemEntry => entry.kind === 'item');
      if (children.length > 0) {
        out.push({ kind: 'row', label: el.getAttribute('aria-label')?.trim() ?? '', children });
      }
      return;
    }
    if (el.matches(NESTED_ROOT)) {
      const nested = panelOf(el as HTMLElement);
      const children = nested === el ? [] : walk(nested);
      if (children.length > 0) {
        out.push({ kind: 'submenu', label: el.getAttribute('aria-label')?.trim() || toggleLabel(el), children });
      }
      return;
    }
    const list = el.matches('li') ? [...el.children].find((child) => child.matches('ul, ol, [role="menu"]')) : undefined;
    if (list) {
      const children = walk(list);
      const head = [...el.children].find((child) => child !== list);
      const label = head ? labelOf(head) : '';
      if (children.length > 0 && label) {
        out.push({ kind: 'submenu', label, children });
      }
      return;
    }
    if (el.matches(CONTROL) && el instanceof HTMLElement) {
      const label = labelOf(el);
      if (label && isRendered(el, container)) {
        out.push({
          kind: 'item',
          label,
          original: el,
          danger: isDanger(el),
          disabled: isDisabled(el),
          current: el.classList.contains('is-current') || el.hasAttribute('aria-current'),
          icon: el.querySelector('svg, i, .nowo-ui-icon'),
        });
      }
      return;
    }
    if (el.matches('hr, [role="separator"]')) {
      if (out.length > 0 && out[out.length - 1]!.kind !== 'separator') {
        out.push({ kind: 'separator' });
      }
      return;
    }
    [...el.children].forEach(visit);
  };
  [...container.children].forEach(visit);
  while (out.length > 0 && out[out.length - 1]!.kind === 'separator') {
    out.pop();
  }
  return out;
}

/** The entries a context menu shows for a source: the items of its own panel (the row-actions cluster itself). */
export function collectEntries(source: HTMLElement): Entry[] {
  return walk(panelOf(source));
}

/** The nearest SCOPE around a marked source. */
export function scopeOf(source: Element): HTMLElement | null {
  return source.parentElement?.closest<HTMLElement>(SCOPE) ?? null;
}

function explicitSource(host: HTMLElement, value: string): HTMLElement | null {
  if (value === '') {
    return host.querySelector<HTMLElement>(SOURCE);
  }
  const el = document.getElementById(value.replace(/^#/, ''));
  if (!el) {
    return null;
  }
  // Only a kebab / menu / row-actions root, or a panel inside one: never an arbitrary element of the page.
  return el.matches(SOURCE) ? el : el.closest<HTMLElement>(SOURCE);
}

/** The host element and kebab source a target belongs to, or null (no context menu there). */
export function resolveHost(target: Element | null): Resolved | null {
  for (let el = target; el; el = el.parentElement) {
    if (el.matches('.nowo-ui-context-menu')) {
      return null;
    }
    if (!(el instanceof HTMLElement)) {
      continue;
    }
    if (el.hasAttribute(ATTR)) {
      const value = (el.getAttribute(ATTR) ?? '').trim();
      if (value === 'off' || value === 'false') {
        return null;
      }
      const source = explicitSource(el, value);
      return source ? { host: el, source } : null;
    }
    if (el.matches(SCOPE)) {
      const source = [...el.querySelectorAll<HTMLElement>(`[${SOURCE_ATTR}]`)].find((candidate) => scopeOf(candidate) === el);
      if (source) {
        return { host: el, source };
      }
    }
  }
  return null;
}

/** Whether the browser's own menu wins: selected text, a field / link target, or Shift / Ctrl held. */
export function keepsNativeMenu(target: Element | null, modifiers: { shiftKey?: boolean; ctrlKey?: boolean } = {}): boolean {
  if (modifiers.shiftKey || modifiers.ctrlKey) {
    return true;
  }
  const selection = typeof window.getSelection === 'function' ? window.getSelection() : null;
  if (selection && !selection.isCollapsed && selection.toString().trim() !== '') {
    return true;
  }
  return target?.closest(NATIVE_TARGET) != null;
}

function supportsPopover(el: HTMLElement): boolean {
  return typeof el.showPopover === 'function';
}

/** Where the panels go: the open modal dialog around the host (the rest of the page is inert), else the body. */
function containerOf(host: HTMLElement): HTMLElement {
  return host.closest<HTMLElement>('dialog[open]') ?? document.body;
}

function show(panel: HTMLElement, container: HTMLElement): void {
  container.appendChild(panel);
  if (supportsPopover(panel)) {
    panel.setAttribute('popover', 'manual');
    try {
      panel.showPopover();
      return;
    } catch {
      panel.removeAttribute('popover');
    }
  }
  panel.classList.add('nowo-ui-context-menu--fallback');
  panel.hidden = false;
}

function hide(panel: HTMLElement): void {
  if (panel.hasAttribute('popover')) {
    try {
      panel.hidePopover();
    } catch {
      // already hidden
    }
  }
  panel.remove();
}

function clamp(value: number, size: number, viewport: number): number {
  return Math.max(EDGE_PX, Math.min(value, viewport - EDGE_PX - size));
}

/** At a point (the pointer): right / below it, flipped left / above without room, kept inside the viewport. */
export function placeAtPoint(panel: HTMLElement, x: number, y: number): void {
  const width = panel.offsetWidth;
  const height = panel.offsetHeight;
  const left = x + width > window.innerWidth - EDGE_PX ? x - width : x;
  const top = y + height > window.innerHeight - EDGE_PX ? y - height : y;
  panel.style.left = `${Math.round(clamp(left, width, window.innerWidth))}px`;
  panel.style.top = `${Math.round(clamp(top, height, window.innerHeight))}px`;
}

function isRtl(el: Element): boolean {
  return getComputedStyle(el).direction === 'rtl';
}

/** Under an element (keyboard open), start edges aligned, above it without room below, kept inside the viewport. */
export function placeAtElement(panel: HTMLElement, anchor: Element): void {
  const rect = anchor.getBoundingClientRect();
  const width = panel.offsetWidth;
  const height = panel.offsetHeight;
  const below = rect.bottom + GAP_PX;
  const top = below + height > window.innerHeight - EDGE_PX && rect.top - GAP_PX - height >= EDGE_PX ? rect.top - GAP_PX - height : below;
  const left = isRtl(anchor) ? rect.right - width : rect.left;
  panel.style.left = `${Math.round(clamp(left, width, window.innerWidth))}px`;
  panel.style.top = `${Math.round(clamp(top, height, window.innerHeight))}px`;
}

/** Beside its parent item (a submenu): after its end edge, before its start edge without room. */
export function placeBesideItem(panel: HTMLElement, item: Element): void {
  const rect = item.getBoundingClientRect();
  const width = panel.offsetWidth;
  const height = panel.offsetHeight;
  const after = isRtl(item) ? rect.left - width : rect.right;
  const before = isRtl(item) ? rect.right : rect.left - width;
  const fits = after >= EDGE_PX && after + width <= window.innerWidth - EDGE_PX;
  panel.style.left = `${Math.round(clamp(fits ? after : before, width, window.innerWidth))}px`;
  panel.style.top = `${Math.round(clamp(rect.top, height, window.innerHeight))}px`;
}

function itemButton(entry: ItemEntry | Extract<Entry, { kind: 'submenu' }>): HTMLButtonElement {
  const button = document.createElement('button');
  button.type = 'button';
  button.tabIndex = -1;
  button.setAttribute('role', 'menuitem');
  button.className = 'nowo-ui-menu__item nowo-ui-context-menu__item';
  if (entry.kind === 'item') {
    if (entry.danger) {
      button.classList.add('nowo-ui-menu__item--danger');
    }
    if (entry.current) {
      button.classList.add('is-current');
    }
    if (entry.disabled) {
      button.setAttribute('aria-disabled', 'true');
    }
    if (entry.icon) {
      const icon = entry.icon.cloneNode(true) as Element;
      icon.removeAttribute('id');
      icon.setAttribute('aria-hidden', 'true');
      button.classList.add('nowo-ui-menu__item--icon');
      button.appendChild(icon);
    }
  } else {
    button.classList.add('nowo-ui-context-menu__item--submenu');
    button.setAttribute('aria-haspopup', 'menu');
    button.setAttribute('aria-expanded', 'false');
  }
  const text = document.createElement('span');
  text.className = 'nowo-ui-context-menu__label';
  text.textContent = entry.label;
  button.appendChild(text);
  entries.set(button, entry);
  return button;
}

function buildPanel(list: Entry[], label: string, sub: boolean, sheet: boolean): Level {
  const panel = document.createElement('ul');
  panel.className = `nowo-ui-menu__panel nowo-ui-context-menu${sub ? ' nowo-ui-context-menu--sub' : ''}${sheet ? ' nowo-ui-context-menu--sheet' : ''}`;
  panel.id = `nowo-ui-context-menu-${++sequence}`;
  panel.setAttribute('role', 'menu');
  panel.setAttribute('aria-label', label);
  panel.tabIndex = -1;
  panel.setAttribute('data-nowo-ui-context-menu-panel', '');
  const items: HTMLElement[] = [];
  const rows = new Map<HTMLElement, HTMLElement[]>();
  list.forEach((entry) => {
    const li = document.createElement('li');
    if (entry.kind === 'separator') {
      li.setAttribute('role', 'separator');
      li.className = 'nowo-ui-context-menu__separator';
      panel.appendChild(li);
      return;
    }
    li.setAttribute('role', 'none');
    if (entry.kind === 'row') {
      const group = document.createElement('div');
      group.setAttribute('role', 'group');
      group.className = 'nowo-ui-context-menu__row';
      if (entry.label) {
        group.setAttribute('aria-label', entry.label);
      }
      const row = entry.children.map((child) => {
        const button = itemButton(child);
        button.classList.add('nowo-ui-context-menu__item--row');
        // A row item shows what the kebab item shows (an emoji…) and keeps its accessible name.
        const shown = (child.original.textContent ?? '').replace(/\s+/g, ' ').trim();
        if (shown !== '' && shown !== child.label) {
          button.querySelector('.nowo-ui-context-menu__label')!.textContent = shown;
          button.setAttribute('aria-label', child.label);
        }
        group.appendChild(button);
        return button;
      });
      row.forEach((button) => {
        rows.set(button, row);
        items.push(button);
      });
      li.appendChild(group);
      panel.appendChild(li);
      return;
    }
    const button = itemButton(entry);
    items.push(button);
    li.appendChild(button);
    panel.appendChild(li);
  });
  panel.addEventListener('keydown', onMenuKeydown);
  panel.addEventListener('click', onMenuClick);
  panel.addEventListener('pointerover', onMenuPointerOver);
  return { panel, items, parent: null, rows };
}

/** 2.58: whether the menu of this host / source opens as a bottom sheet (opted in, on a narrow screen). */
export function opensAsSheet(host: HTMLElement, source: HTMLElement): boolean {
  if (!host.hasAttribute(SHEET_ATTR) && !source.hasAttribute(SHEET_ATTR)) {
    return false;
  }
  return typeof window.matchMedia === 'function' && window.matchMedia(SHEET_QUERY).matches;
}

function menuLabel(host: HTMLElement, source: HTMLElement): string {
  return (
    host.getAttribute(LABEL_ATTR)?.trim() ||
    source.getAttribute(LABEL_ATTR)?.trim() ||
    toggleLabel(source) ||
    source.getAttribute('aria-label')?.trim() ||
    'Actions'
  );
}

function focusItem(level: Level, index: number): void {
  const item = level.items[index];
  item?.focus({ preventScroll: true });
}

function levelOf(el: Element | null): number {
  if (!open || !el) {
    return -1;
  }
  return open.levels.findIndex((level) => level.panel.contains(el));
}

/** Closes the levels deeper than `depth` (0 = keep only the root menu). */
function closeDeeper(depth: number): void {
  if (!open) {
    return;
  }
  while (open.levels.length > depth + 1) {
    const level = open.levels.pop()!;
    level.parent?.setAttribute('aria-expanded', 'false');
    level.parent?.removeAttribute('aria-controls');
    hide(level.panel);
  }
}

function openSubmenu(item: HTMLElement, focusFirst: boolean): void {
  const entry = entries.get(item);
  const depth = levelOf(item);
  if (!open || !entry || entry.kind !== 'submenu' || depth === -1) {
    return;
  }
  const already = open.levels[depth + 1];
  if (already && already.parent === item) {
    if (focusFirst) {
      focusItem(already, 0);
    }
    return;
  }
  closeDeeper(depth);
  const sheet = open.backdrop !== null;
  const level = buildPanel(entry.children, entry.label, true, sheet);
  level.parent = item;
  open.levels.push(level);
  show(level.panel, containerOf(open.host));
  if (!sheet) {
    // A sheet's submenu covers the sheet (CSS places it); Left / Escape go back.
    placeBesideItem(level.panel, item);
  }
  item.setAttribute('aria-expanded', 'true');
  item.setAttribute('aria-controls', level.panel.id);
  if (focusFirst) {
    focusItem(level, 0);
  }
}

function dispatch(host: HTMLElement, type: 'open' | 'close', detail: Record<string, unknown>, cancelable: boolean): boolean {
  return host.dispatchEvent(new CustomEvent(`nowo-ui:context-menu:${type}`, { bubbles: true, cancelable, detail }));
}

/** Closes kit menus that are open (kebab popovers, <details> menus): one menu at a time. */
function closeOtherMenus(): void {
  document.querySelectorAll<HTMLElement>('[data-nowo-ui-popover-panel]').forEach((panel) => {
    let shown = false;
    try {
      shown = panel.matches(':popover-open');
    } catch {
      shown = false;
    }
    if (shown) {
      try {
        panel.hidePopover();
      } catch {
        // already hidden
      }
    }
  });
  document.querySelectorAll('details[data-nowo-ui-menu][open], details.nowo-ui-kebab__details[open]').forEach((details) => details.removeAttribute('open'));
}

function focusable(el: HTMLElement): boolean {
  return el.tabIndex >= 0 || el.matches('a[href], button, input, select, textarea, summary');
}

/** Where focus goes back: the focused element (keyboard), else the host when focusable, else the kebab's toggle. */
function returnTarget(host: HTMLElement, source: HTMLElement, trigger: Trigger): HTMLElement | null {
  const active = document.activeElement;
  if (trigger === 'keyboard' && active instanceof HTMLElement && active !== document.body) {
    return active;
  }
  if (focusable(host)) {
    return host;
  }
  const toggle = source.querySelector<HTMLElement>(TOGGLE);
  if (toggle) {
    return toggle;
  }
  return active instanceof HTMLElement && active !== document.body ? active : null;
}

/**
 * Opens the context menu of `host` with the items of `source`, at a point (pointer / touch) or under an element
 * (keyboard). Returns false when there is nothing to show or a listener cancelled `nowo-ui:context-menu:open`.
 */
export function openContextMenu(host: HTMLElement, source: HTMLElement, at: { x: number; y: number } | Element, trigger: Trigger): boolean {
  const list = collectEntries(source);
  if (list.length === 0) {
    return false;
  }
  if (!dispatch(host, 'open', { source, trigger }, true)) {
    return false;
  }
  closeContextMenu(false);
  closeOtherMenus();
  const returnTo = returnTarget(host, source, trigger);
  const sheet = opensAsSheet(host, source);
  const root = buildPanel(list, menuLabel(host, source), false, sheet);
  let backdrop: HTMLElement | null = null;
  if (sheet) {
    backdrop = document.createElement('div');
    backdrop.className = 'nowo-ui-context-menu__backdrop';
    backdrop.setAttribute('data-nowo-ui-context-menu-backdrop', '');
    backdrop.setAttribute('aria-hidden', 'true');
  }
  open = { host, source, backdrop, returnTo, levels: [root], scrollX: window.scrollX, scrollY: window.scrollY };
  host.setAttribute('data-nowo-ui-context-menu-open', '');
  if (backdrop) {
    // Shown first: the sheet's panel goes above it in the top layer.
    show(backdrop, containerOf(host));
  }
  show(root.panel, containerOf(host));
  if (sheet) {
    // CSS places the sheet (bottom, full width): no coordinates.
  } else if (at instanceof Element) {
    placeAtElement(root.panel, at);
  } else {
    placeAtPoint(root.panel, at.x, at.y);
  }
  const first = root.items.findIndex((item) => item.getAttribute('aria-disabled') !== 'true');
  focusItem(root, first === -1 ? 0 : first);
  addGlobalListeners();
  return true;
}

/** Closes the open context menu; `returnFocus` puts focus back on the element it was opened from. */
export function closeContextMenu(returnFocus = true): void {
  if (!open) {
    return;
  }
  const state = open;
  closeDeeper(0);
  open = null;
  state.levels.forEach((level) => hide(level.panel));
  if (state.backdrop) {
    hide(state.backdrop);
  }
  state.host.removeAttribute('data-nowo-ui-context-menu-open');
  removeGlobalListeners();
  resetTypeahead();
  if (returnFocus) {
    state.returnTo?.focus({ preventScroll: true });
  }
  dispatch(state.host, 'close', { source: state.source }, false);
}

/** Whether a context menu is open (tests, hosts). */
export function isContextMenuOpen(): boolean {
  return open !== null;
}

/** The open menu panels, root first (tests). */
export function openPanels(): HTMLElement[] {
  return open ? open.levels.map((level) => level.panel) : [];
}

function activate(item: HTMLElement): void {
  const entry = entries.get(item);
  if (!entry || !open) {
    return;
  }
  if (entry.kind === 'submenu') {
    openSubmenu(item, true);
    return;
  }
  if (entry.kind !== 'item' || entry.disabled) {
    return;
  }
  const returnTo = open.returnTo;
  closeContextMenu(true);
  // The forwarded click is the user's choice, not the end of a long press.
  clearClickSuppression();
  const original = entry.original;
  // After the kit's own capture-phase recorder (document), before the item's handlers: a dialog the item opens
  // returns focus to the element the context menu was opened from, else the kebab's toggle.
  original.addEventListener(
    'click',
    () => {
      if (returnTo) {
        recordInvoker(returnTo, original);
      }
    },
    { capture: true, once: true },
  );
  original.click();
}

function resetTypeahead(): void {
  typeahead = '';
  if (typeaheadTimer) {
    clearTimeout(typeaheadTimer);
    typeaheadTimer = null;
  }
}

function onTypeahead(level: Level, key: string): void {
  typeahead += key.toLowerCase();
  if (typeaheadTimer) {
    clearTimeout(typeaheadTimer);
  }
  typeaheadTimer = setTimeout(resetTypeahead, TYPEAHEAD_MS);
  const current = level.items.indexOf(document.activeElement as HTMLElement);
  const count = level.items.length;
  // A repeated single letter cycles through the items that start with it.
  const repeated = typeahead.length > 1 && [...typeahead].every((char) => char === typeahead[0]);
  const needle = repeated ? typeahead[0]! : typeahead;
  const start = current === -1 ? 0 : current + (needle.length === 1 ? 1 : 0);
  for (let step = 0; step < count; step++) {
    const index = (start + step) % count;
    const label = (entries.get(level.items[index]!) as { label?: string } | undefined)?.label ?? '';
    if (label.toLowerCase().startsWith(needle)) {
      focusItem(level, index);
      return;
    }
  }
}

/**
 * 2.58: the index Up / Down moves to from `current`: the next (or previous) item outside the current row, wrapping; a
 * row counts as one stop and is entered on its first item.
 */
function stepGroup(level: Level, current: number, direction: 1 | -1): number {
  const items = level.items;
  const count = items.length;
  const group = level.rows.get(items[current]!);
  let index = current;
  for (let step = 0; step < count; step++) {
    index = (index + direction + count) % count;
    const candidate = level.rows.get(items[index]!);
    if (group === undefined || candidate !== group) {
      return candidate === undefined ? index : items.indexOf(candidate[0]!);
    }
  }
  return current;
}

function onMenuKeydown(event: KeyboardEvent): void {
  if (!open) {
    return;
  }
  const depth = levelOf(event.target as Element);
  const level = open.levels[depth];
  if (!level) {
    return;
  }
  const items = level.items;
  const current = items.indexOf(document.activeElement as HTMLElement);
  const rtl = isRtl(level.panel);
  const forward = rtl ? 'ArrowLeft' : 'ArrowRight';
  const back = rtl ? 'ArrowRight' : 'ArrowLeft';
  const key = event.key;
  const row = current === -1 ? undefined : level.rows.get(items[current]!);
  if (row && (key === forward || key === back)) {
    // 2.58: inside a row, Left / Right move between its items (wrapping); they never open or close a submenu.
    event.preventDefault();
    event.stopPropagation();
    const at = row.indexOf(items[current]!);
    const next = row[(at + (key === forward ? 1 : row.length - 1)) % row.length]!;
    focusItem(level, items.indexOf(next));
    return;
  }
  if (key === 'ArrowDown' || key === 'ArrowUp' || key === 'Home' || key === 'End') {
    event.preventDefault();
    let index = 0;
    if (key === 'End') {
      index = items.length - 1;
    } else if (key === 'ArrowDown') {
      index = current === -1 ? 0 : stepGroup(level, current, 1);
    } else if (key === 'ArrowUp') {
      index = current === -1 ? items.length - 1 : stepGroup(level, current, -1);
    }
    focusItem(level, index);
    return;
  }
  if (key === forward) {
    event.preventDefault();
    if (current !== -1 && entries.get(items[current]!)?.kind === 'submenu') {
      openSubmenu(items[current]!, true);
    }
    return;
  }
  if (key === back || (key === 'Escape' && depth > 0)) {
    event.preventDefault();
    event.stopPropagation();
    if (depth > 0) {
      const parent = level.parent;
      closeDeeper(depth - 1);
      parent?.focus({ preventScroll: true });
    }
    return;
  }
  if (key === 'Escape') {
    event.preventDefault();
    event.stopPropagation();
    closeContextMenu(true);
    return;
  }
  if (key === 'Enter' || key === ' ') {
    event.preventDefault();
    if (current !== -1) {
      activate(items[current]!);
    }
    return;
  }
  if (key === 'Tab') {
    event.preventDefault();
    closeContextMenu(true);
    return;
  }
  if (key.length === 1 && key !== ' ' && !event.ctrlKey && !event.metaKey && !event.altKey) {
    event.preventDefault();
    onTypeahead(level, key);
  }
}

function onMenuClick(event: MouseEvent): void {
  const item = (event.target as Element | null)?.closest<HTMLElement>('[role="menuitem"]');
  if (item) {
    event.preventDefault();
    activate(item);
  }
}

function onMenuPointerOver(event: PointerEvent): void {
  const item = (event.target as Element | null)?.closest<HTMLElement>('[role="menuitem"]');
  if (!item || !open) {
    return;
  }
  const depth = levelOf(item);
  if (entries.get(item)?.kind === 'submenu') {
    openSubmenu(item, false);
  } else {
    closeDeeper(depth);
  }
  if (document.activeElement !== item) {
    item.focus({ preventScroll: true });
  }
}

function insideMenu(target: EventTarget | null): boolean {
  return target instanceof Node && open !== null && open.levels.some((level) => level.panel.contains(target));
}

function onOutsidePointerDown(event: PointerEvent): void {
  if (insideMenu(event.target)) {
    return;
  }
  const onBackdrop = open !== null && open.backdrop !== null && event.target === open.backdrop;
  closeContextMenu(false);
  if (onBackdrop) {
    // 2.58: the tap that closes a sheet never reaches what lies under the backdrop (2.58.1: and the next right-click
    // or long press opens a menu again at once).
    armClickSuppression(event.pointerId, false);
  }
}

/**
 * A scroll that moves the host away closes the menu: the page (when its position changed since the menu opened; some
 * engines deliver the `scroll` of a scroll made just before the right-click after the menu opened), or a scroller around
 * the host. Scrolling inside the menu or in an unrelated scroller does not.
 */
function onScroll(event: Event): void {
  if (!open || insideMenu(event.target)) {
    return;
  }
  const target = event.target;
  if (target instanceof Element) {
    if (target.contains(open.host)) {
      closeContextMenu(false);
    }
    return;
  }
  if (Math.abs(window.scrollX - open.scrollX) > 1 || Math.abs(window.scrollY - open.scrollY) > 1) {
    closeContextMenu(false);
  }
}

function onResize(): void {
  closeContextMenu(false);
}

function onWindowBlur(): void {
  closeContextMenu(false);
}

/** Another kit popover opening (a kebab, a combobox…) closes this menu. */
function onToggle(event: Event): void {
  const target = event.target;
  // The menu's own panels and a sheet's backdrop (2.58) fire their own toggle events.
  if (target instanceof Element && target.matches('[data-nowo-ui-context-menu-panel], [data-nowo-ui-context-menu-backdrop]')) {
    return;
  }
  if ((event as Event & { newState?: string }).newState === 'open') {
    closeContextMenu(false);
  }
}

function onTurbo(): void {
  closeContextMenu(false);
}

function addGlobalListeners(): void {
  document.addEventListener('pointerdown', onOutsidePointerDown, true);
  window.addEventListener('scroll', onScroll, true);
  window.addEventListener('resize', onResize);
  window.addEventListener('blur', onWindowBlur);
  document.addEventListener('toggle', onToggle, true);
}

function removeGlobalListeners(): void {
  document.removeEventListener('pointerdown', onOutsidePointerDown, true);
  window.removeEventListener('scroll', onScroll, true);
  window.removeEventListener('resize', onResize);
  window.removeEventListener('blur', onWindowBlur);
  document.removeEventListener('toggle', onToggle, true);
}

function onContextMenu(event: MouseEvent): void {
  if (event.defaultPrevented) {
    // The host handled this right-click itself.
    return;
  }
  const target = event.target instanceof Element ? event.target : null;
  if (Date.now() < suppressContextMenuUntil || insideMenu(target)) {
    event.preventDefault();
    return;
  }
  if (press) {
    // Android fires its own contextmenu during a long press: open now, once.
    const at = { x: press.x, y: press.y };
    const pointerId = press.id;
    cancelPress();
    const resolved = resolveHost(target);
    if (resolved && !keepsNativeMenu(target) && openContextMenu(resolved.host, resolved.source, at, 'touch')) {
      event.preventDefault();
      armClickSuppression(pointerId);
    }
    return;
  }
  if (keepsNativeMenu(target, event)) {
    return;
  }
  const resolved = resolveHost(target);
  if (!resolved) {
    return;
  }
  // A contextmenu event from the keyboard (not caught as a keydown) has no pointer position.
  const keyboard = event.clientX === 0 && event.clientY === 0 && event.button !== 2;
  const at = keyboard && target ? target : { x: event.clientX, y: event.clientY };
  if (openContextMenu(resolved.host, resolved.source, at, keyboard ? 'keyboard' : 'pointer')) {
    event.preventDefault();
  }
}

function onKeydown(event: KeyboardEvent): void {
  if (event.defaultPrevented || !(event.key === 'ContextMenu' || (event.key === 'F10' && event.shiftKey))) {
    return;
  }
  const active = document.activeElement;
  const target = active instanceof Element && active !== document.body ? active : null;
  if (!target || insideMenu(target) || keepsNativeMenu(target)) {
    return;
  }
  const resolved = resolveHost(target);
  if (resolved && openContextMenu(resolved.host, resolved.source, target, 'keyboard')) {
    event.preventDefault();
    suppressContextMenuUntil = Date.now() + SUPPRESS_MS;
  }
}

function cancelPress(): void {
  if (press) {
    clearTimeout(press.timer);
    press = null;
  }
}

function clearClickSuppression(): void {
  suppressClick = false;
  suppressInMenu = false;
  suppressPointer = null;
  if (suppressClickTimer) {
    clearTimeout(suppressClickTimer);
    suppressClickTimer = null;
  }
}

/** Swallows the click that ends the long press of `pointerId`: until shortly after that pointer's pointerup / pointercancel. */
function armClickSuppression(pointerId: number, contextMenuToo = true): void {
  clearClickSuppression();
  suppressClick = true;
  suppressPointer = pointerId;
  // 2.58.1: a sheet covers the bottom of the screen, so the finger that opened it may rest on one of its items: the
  // click that ends that long press is swallowed inside the sheet too.
  suppressInMenu = open !== null && open.backdrop !== null;
  if (contextMenuToo) {
    suppressContextMenuUntil = Date.now() + SUPPRESS_MS;
  }
}

function onPointerDown(event: PointerEvent): void {
  if (event.target instanceof Element && event.target.matches('[data-nowo-ui-context-menu-backdrop]')) {
    // 2.58: the tap on a sheet's backdrop closed the menu (capture phase) and armed the suppression of its click.
    return;
  }
  // A new pointer: the long press before it is over (its click never came).
  clearClickSuppression();
  if (event.pointerType !== 'touch' && event.pointerType !== 'pen') {
    return;
  }
  cancelPress();
  const target = event.target instanceof Element ? event.target : null;
  if (!target || insideMenu(target) || !event.isPrimary || keepsNativeMenu(target) || !resolveHost(target)) {
    return;
  }
  const id = event.pointerId;
  const x = event.clientX;
  const y = event.clientY;
  const timer = setTimeout(() => {
    press = null;
    const resolved = resolveHost(target);
    if (resolved && !keepsNativeMenu(target) && openContextMenu(resolved.host, resolved.source, { x, y }, 'touch')) {
      armClickSuppression(id);
    }
  }, LONG_PRESS_MS);
  press = { id, x, y, target, timer };
}

function onPointerMove(event: PointerEvent): void {
  if (press && event.pointerId === press.id && Math.hypot(event.clientX - press.x, event.clientY - press.y) > MOVE_TOLERANCE_PX) {
    cancelPress();
  }
}

function onPointerEnd(event: PointerEvent): void {
  if (press && event.pointerId === press.id) {
    cancelPress();
  }
  if (suppressClick && event.pointerId === suppressPointer && !suppressClickTimer) {
    // The click (if the engine sends one) follows the pointerup at once.
    suppressClickTimer = setTimeout(clearClickSuppression, CLICK_GRACE_MS);
  }
}

function onPressScroll(): void {
  cancelPress();
}

/** The click that ends a long press must not activate what is under the finger (the menu itself excepted). */
function onClickCapture(event: MouseEvent): void {
  if (suppressClick && (suppressInMenu || !insideMenu(event.target))) {
    clearClickSuppression();
    event.preventDefault();
    event.stopPropagation();
  }
}

let bound = false;

export function bindNowoUiContextMenu(): void {
  if (bound || typeof document === 'undefined') {
    return;
  }
  bound = true;
  document.addEventListener('contextmenu', onContextMenu);
  document.addEventListener('keydown', onKeydown);
  // Passive: a long press never blocks scrolling.
  document.addEventListener('pointerdown', onPointerDown, { passive: true });
  document.addEventListener('pointermove', onPointerMove, { passive: true });
  document.addEventListener('pointerup', onPointerEnd, { passive: true });
  document.addEventListener('pointercancel', onPointerEnd, { passive: true });
  window.addEventListener('scroll', onPressScroll, { capture: true, passive: true });
  document.addEventListener('click', onClickCapture, true);
  document.addEventListener('turbo:before-cache', onTurbo);
  document.addEventListener('turbo:before-render', onTurbo);
}

/** Unbinds everything and closes the menu (tests). */
export function unbindNowoUiContextMenu(): void {
  closeContextMenu(false);
  cancelPress();
  clearClickSuppression();
  suppressContextMenuUntil = 0;
  if (!bound) {
    return;
  }
  bound = false;
  document.removeEventListener('contextmenu', onContextMenu);
  document.removeEventListener('keydown', onKeydown);
  document.removeEventListener('pointerdown', onPointerDown);
  document.removeEventListener('pointermove', onPointerMove);
  document.removeEventListener('pointerup', onPointerEnd);
  document.removeEventListener('pointercancel', onPointerEnd);
  window.removeEventListener('scroll', onPressScroll, true);
  document.removeEventListener('click', onClickCapture, true);
  document.removeEventListener('turbo:before-cache', onTurbo);
  document.removeEventListener('turbo:before-render', onTurbo);
}

// A second copy of the script (Turbo runs body scripts again on every visit) must not bind again: two copies would
// each open a menu on a long press. A symbol: an element with some id cannot turn this off.
const BOUND_KEY = Symbol.for('nowo-ui.context-menu.bound');
if (typeof window !== 'undefined' && !(window as unknown as Record<symbol, unknown>)[BOUND_KEY]) {
  (window as unknown as Record<symbol, unknown>)[BOUND_KEY] = true;
  bindNowoUiContextMenu();
}
