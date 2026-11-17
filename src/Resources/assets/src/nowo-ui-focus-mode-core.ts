/**
 * Shell focus mode (2.54): side-effect-free helpers of `nowo-ui-focus-mode.ts` — the configured lists on the shell root,
 * the shell regions a list names, hiding and restoring them (with the `keep` list), the per-area memory and the
 * keyboard rules. No listener is bound here, so tests and other entries can import it freely.
 *
 * Shell root (printed by the `app_shell_open` / `shell` partials when `nowo_ui_kit.theme.focus_mode.enabled`):
 *   `data-nowo-ui-focus-regions="sidebar header footer side_panels edge_tabs dock"` (the marker: no attribute = off)
 *   `data-nowo-ui-focus-keep-list="breadcrumb_min search timer_chip"`
 *   `data-nowo-ui-focus-shortcut="F"` (`none` = no shortcut; missing = `F`)
 *   `data-nowo-ui-focus-fullscreen="false"` (no fullscreen step; missing = allowed)
 */

export const SHELL = '[data-nowo-ui-shell]';
export const TOGGLE = '[data-nowo-ui-focus-mode]';
export const BAR = '[data-nowo-ui-focus-bar]';
export const STATUS = '[data-nowo-ui-focus-status]';
export const AREA = '[data-nowo-ui-focus-area]';
export const REGIONS_ATTR = 'data-nowo-ui-focus-regions';
export const KEEP_LIST_ATTR = 'data-nowo-ui-focus-keep-list';
export const SHORTCUT_ATTR = 'data-nowo-ui-focus-shortcut';
export const FULLSCREEN_ATTR = 'data-nowo-ui-focus-fullscreen';
export const STATE_ATTR = 'data-focus-mode';
export const HTML_STATE_ATTR = 'data-nowo-ui-focus-state';
export const HIDDEN_ATTR = 'data-nowo-ui-focus-hidden';
export const PARTIAL_ATTR = 'data-nowo-ui-focus-partial';
export const KEEP_ATTR = 'data-nowo-ui-focus-keep';
export const REGION_ATTR = 'data-nowo-ui-focus-region';
export const STORAGE_PREFIX = 'nowo-ui-focus:';

export type FocusLevel = 'off' | 'on' | 'fullscreen';

/** The regions hidden when the shell does not list its own (same as the config default). */
export const DEFAULT_REGIONS = ['sidebar', 'header', 'footer', 'side_panels', 'edge_tabs', 'dock'] as const;

/**
 * Built-in region name => how its elements are found. Every region (built-in or not) also takes the elements marked
 * `data-nowo-ui-focus-region="<name>"` anywhere in the document (a host's messages dock, its own top bar…).
 */
const BUILT_IN: Record<string, (shell: HTMLElement) => Element[]> = {
  sidebar: (shell) => [...shell.querySelectorAll('[data-nowo-ui-aside], [data-nowo-ui-aside-backdrop]')],
  header: (shell) => [...shell.children].filter((child) => child.tagName === 'HEADER'),
  footer: (shell) => [...shell.children].filter((child) => child.tagName === 'FOOTER'),
  side_panels: () => [...document.querySelectorAll('[data-nowo-ui-side-panel], [data-nowo-ui-side-panel-toggle], [data-nowo-ui-side-panel-backdrop]')],
  edge_tabs: () => [...document.querySelectorAll('[data-nowo-ui-edge-tabs-launcher]')],
};

const NAME = /^[a-z][a-z0-9_]{0,31}$/;

/** A space / comma separated list of names (`a-z 0-9 _`, starting with a letter); anything else is dropped. */
export function parseList(value: string | null | undefined): string[] {
  const names = (value ?? '').split(/[\s,]+/).filter((name) => NAME.test(name));

  return names.filter((name, index) => names.indexOf(name) === index);
}

/** Whether the shell has focus mode (the config printed the regions attribute). */
export function isEnabled(shell: Element | null): shell is HTMLElement {
  return shell instanceof HTMLElement && shell.hasAttribute(REGIONS_ATTR);
}

export function regionsOf(shell: HTMLElement): string[] {
  return parseList(shell.getAttribute(REGIONS_ATTR));
}

export function keepListOf(shell: HTMLElement): string[] {
  return parseList(shell.getAttribute(KEEP_LIST_ATTR));
}

/** The level the shell shows now (`data-focus-mode`: `fullscreen`, any other value = `on`, missing = `off`). */
export function levelOf(shell: HTMLElement): FocusLevel {
  const value = shell.getAttribute(STATE_ATTR);
  if (value === null) return 'off';

  return value === 'fullscreen' ? 'fullscreen' : 'on';
}

/** The elements of one region (built-in selector + marked elements), without duplicates. */
export function regionElements(shell: HTMLElement, name: string): Element[] {
  const builtIn = Object.prototype.hasOwnProperty.call(BUILT_IN, name) ? BUILT_IN[name](shell) : [];
  const found = [...builtIn, ...document.querySelectorAll(`[${REGION_ATTR}~="${name}"]`)];

  return found.filter((el, index) => found.indexOf(el) === index);
}

/**
 * The elements that stay visible inside hidden regions: those marked `data-nowo-ui-focus-keep` with a name of the keep
 * list, and always the focus bar (the exit control) and the live region.
 */
export function keptElements(shell: HTMLElement): Element[] {
  const keep = keepListOf(shell);
  const marked = [...document.querySelectorAll(`[${KEEP_ATTR}]`)].filter((el) => parseList(el.getAttribute(KEEP_ATTR)).some((name) => keep.includes(name)));

  return [...marked, ...document.querySelectorAll(`${BAR}, ${STATUS}`)];
}

function hideElement(el: Element): void {
  // `inert` takes the element out of the tab order and the accessibility tree; the kit CSS adds display: none.
  // An element that was already inert stays inert when it comes back (the value records who added it).
  el.setAttribute(HIDDEN_ATTR, el.hasAttribute('inert') ? '' : 'inert');
  el.setAttribute('inert', '');
}

/** Hides every branch of `region` that holds no kept element; the branches on the way to one stay (partially). */
function hideBranches(region: Element, kept: Element[]): void {
  region.setAttribute(PARTIAL_ATTR, '');
  for (const child of [...region.children]) {
    if (kept.includes(child)) continue;
    if (kept.some((el) => child.contains(el))) {
      hideBranches(child, kept);
    } else {
      hideElement(child);
    }
  }
}

/** Hides the shell's regions; returns the hidden elements (whole regions and branches). */
export function hideRegions(shell: HTMLElement): Element[] {
  const kept = keptElements(shell);
  for (const name of regionsOf(shell)) {
    for (const region of regionElements(shell, name)) {
      if (region.hasAttribute(HIDDEN_ATTR) || region.hasAttribute(PARTIAL_ATTR) || kept.includes(region)) continue;
      // Never hide a region around the shell itself (a host marking a wrapper): only its other branches.
      if (kept.some((el) => region.contains(el)) || region.contains(shell)) {
        hideBranches(region, [...kept, shell]);
      } else {
        hideElement(region);
      }
    }
  }

  return [...document.querySelectorAll(`[${HIDDEN_ATTR}]`)];
}

/** Shows again everything focus mode hid (only the `inert` it added itself is removed). */
export function restoreRegions(): void {
  document.querySelectorAll(`[${HIDDEN_ATTR}]`).forEach((el) => {
    if (el.getAttribute(HIDDEN_ATTR) === 'inert') el.removeAttribute('inert');
    el.removeAttribute(HIDDEN_ATTR);
  });
  document.querySelectorAll(`[${PARTIAL_ATTR}]`).forEach((el) => el.removeAttribute(PARTIAL_ATTR));
}

/** A usable area key (1–64 of `A-Z a-z 0-9 _ . : -`), else null: nothing is remembered. */
export function areaKey(value: string | null | undefined): string | null {
  return typeof value === 'string' && /^[A-Za-z0-9_.:-]{1,64}$/.test(value) ? value : null;
}

/** The area of a toggle (its own or its closest ancestor's), else the first one on the page. */
export function areaOf(from: Element | null): string | null {
  const own = from?.closest(AREA) ?? document.querySelector(AREA);

  return areaKey(own?.getAttribute('data-nowo-ui-focus-area'));
}

/** Whether focus mode is remembered for the area (false when storage is unavailable). */
export function readRemembered(area: string): boolean {
  try {
    return window.localStorage.getItem(STORAGE_PREFIX + area) === 'on';
  } catch {
    return false;
  }
}

/** Remembers (or forgets) focus mode for the area; false when storage is unavailable (nothing else happens). */
export function remember(area: string, on: boolean): boolean {
  try {
    if (on) {
      window.localStorage.setItem(STORAGE_PREFIX + area, 'on');
    } else {
      window.localStorage.removeItem(STORAGE_PREFIX + area);
    }

    return true;
  } catch {
    return false;
  }
}

/** Whether the element takes typed text (inputs, textareas, selects, contenteditable). */
export function isTyping(el: EventTarget | null): boolean {
  if (!(el instanceof Element)) return false;
  if (el instanceof HTMLElement && el.isContentEditable) return true;

  return el.closest('input, textarea, select, [contenteditable]:not([contenteditable="false"])') !== null;
}

const OVERLAY = 'dialog[open], [role="dialog"], [role="alertdialog"], [role="menu"], [role="listbox"], [aria-modal="true"], [popover], details[open]:is([data-nowo-ui-menu], .nowo-ui-kebab__details, .nowo-ui-user-menu__details)';

function popoverOpen(): boolean {
  try {
    return document.querySelector(':popover-open') !== null;
  } catch {
    // Engines without the pseudo-class: no native popover can be open.
    return false;
  }
}

/**
 * Whether something else owns the key now: the event target is in a dialog, menu, listbox, popover or open kit menu;
 * a dialog or a native popover is open anywhere; the focused control has its popup open (`aria-expanded="true"` on a
 * combobox / popup button); or the phone sidebar drawer is open. Focus mode then leaves Escape (and F) alone.
 */
export function overlayOwnsKeys(target: EventTarget | null): boolean {
  if (target instanceof Element && target.closest(OVERLAY)) return true;
  if (document.querySelector('dialog[open]') || popoverOpen()) return true;
  const active = document.activeElement;
  if (active instanceof Element && active.getAttribute('aria-expanded') === 'true' && active.matches('[aria-haspopup], [role="combobox"]')) return true;

  return document.querySelector(`${SHELL}.is-aside-open-mobile`) !== null;
}

/** The shortcut of the shell: `F` when the attribute is missing, null when it is empty or `none`. */
export function shortcutOf(shell: HTMLElement): string | null {
  const value = shell.getAttribute(SHORTCUT_ATTR);
  if (value === null) return 'F';
  const trimmed = value.trim();

  return trimmed === '' || trimmed.toLowerCase() === 'none' ? null : trimmed;
}

type FullscreenShell = HTMLElement & { requestFullscreen?: (options?: FullscreenOptions) => Promise<void> | void };

/** Whether the browser can put the shell in fullscreen (and the config allows it). */
export function fullscreenSupported(shell: HTMLElement): boolean {
  if (shell.getAttribute(FULLSCREEN_ATTR) === 'false') return false;

  return document.fullscreenEnabled === true && typeof (shell as FullscreenShell).requestFullscreen === 'function';
}

/** Default English texts of the live region (the kit partials print the translated ones as data attributes). */
export const MESSAGES = {
  on: 'Focus mode on. Press Escape to leave it.',
  off: 'Focus mode off.',
  fullscreen: 'Full screen on.',
  fullscreen_off: 'Full screen off. Focus mode stays on.',
  fullscreen_unavailable: 'Full screen is not available. Focus mode stays on.',
} as const;

export type MessageKey = keyof typeof MESSAGES;

/** The message attribute of a key (`data-nowo-ui-focus-msg-fullscreen-off`). */
export function messageAttr(key: MessageKey): string {
  return `data-nowo-ui-focus-msg-${key.replace(/_/g, '-')}`;
}
