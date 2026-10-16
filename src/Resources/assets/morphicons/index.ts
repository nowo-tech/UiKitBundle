/**
 * Optional morphicons helpers (1.17) — animated icon toggles for kit chrome and host controllers.
 *
 * Requires the OPTIONAL host dependencies `morphicons` (^1.7) and `lucide` (^1.0) (peer dependencies of the kit
 * assets). Importing this module defines the `<morph-icon>` custom element once.
 *
 * Contract: Twig paints a static icon of the current state (UX Icons, icon font…) next to an empty
 * `<morph-icon data-…-morph>`. CSS shows the static icon until the host element gets `is-morph-ready`; a sync helper
 * hydrates the morph-icon (no animation on the first paint), adds `is-morph-ready`, and later morphs between states.
 * `<html data-motion="reduce">` turns every morph into an instant swap.
 *
 * Kit peers (`responsive-table`, `filters-collapse`, `disclosure-icon`) report their state through
 * `stimulus-peers/icon_state.ts`; call `installMorphiconPeers()` once to animate them.
 */
import { defineMorphIcon, type IconInput, type MorphIconElement } from 'morphicons/element';
import { ChevronDown, ChevronRight, ChevronUp, Expand, Eye, EyeOff, LifeBuoy, LoaderCircle, Menu, Moon, MoveHorizontal, Rows3, Search, Shrink, SlidersVertical, Sun, TextAlignStart, X } from 'lucide';

import { setIconStateHandler, type IconStateKind } from '../stimulus-peers/icon_state';

defineMorphIcon();

export type MorphableIcon = IconInput;

export const themeIcons = {
  /** Shown in light mode → click switches to night. */
  toDark: Moon,
  /** Shown in dark mode → click switches to day. */
  toLight: Sun,
} as const;

export const passwordIcons = {
  /** Password hidden → affordance to reveal. */
  hidden: EyeOff,
  /** Password visible → affordance to conceal. */
  visible: Eye,
} as const;

export const contentWidthIcons = {
  /** Content width → click expands to full. */
  toFull: Expand,
  /** Full width → click returns to content. */
  toContent: Shrink,
} as const;

export const sidebarIcons = {
  /** Sidebar closed → open affordance. */
  closed: Menu,
  /** Icons-only rail: half-way between Menu and X (uneven lines). */
  rail: TextAlignStart,
  /** Sidebar open → close affordance. */
  open: X,
} as const;

export const listingCustomizerIcons = {
  /** Drawer closed → open the customizer (same glyph as tabler:adjustments). */
  closed: SlidersVertical,
  /** Drawer open → close affordance. */
  open: X,
} as const;

export const tableModeIcons = {
  /** Scroll mode (every column, horizontal scroll) — same glyph as tabler:arrows-horizontal. */
  scroll: MoveHorizontal,
  /** Responsive mode (columns that fit, row details) — same glyph as tabler:layout-rows. */
  responsive: Rows3,
} as const;

export const filtersCollapseIcons = {
  /** Filters shown → click folds the card (same glyph as tabler:chevron-up). */
  expanded: ChevronUp,
  /** Filters folded → click unfolds them (same glyph as tabler:chevron-down). */
  collapsed: ChevronDown,
} as const;

export const disclosureIcons = {
  /** Section open (same glyph as tabler:chevron-down). */
  open: ChevronDown,
  /** Section closed (same glyph as tabler:chevron-right). */
  closed: ChevronRight,
} as const;

export const searchIcons = {
  /** Folded magnifier (same glyph as tabler:search). */
  idle: Search,
  /** Stretched into a field → click folds it back. */
  open: X,
  /** Request in flight (spun by CSS while `.is-loading`). */
  loading: LoaderCircle,
} as const;

export const supportIcons = {
  /** Help menu closed (same glyph as tabler:lifebuoy). */
  closed: LifeBuoy,
  /** Menu open → click closes it. */
  open: X,
} as const;

export type SidebarIconState = keyof typeof sidebarIcons;
export type Theme = 'light' | 'dark';
export type ContentWidth = 'content' | 'full';

/** Options of the sync helpers: the morph-icon selector inside the host and its box size (px). */
export type MorphSyncOptions = { selector?: string; size?: number };

/**
 * Only the explicit `<html data-motion="reduce">` preference makes morphs instant: the OS
 * prefers-reduced-motion would turn every morph into a swap (reads as "morph not working").
 */
export function resolveReducedMotion(): 'never' | 'always' {
  return document.documentElement.dataset.motion === 'reduce' ? 'always' : 'never';
}

/** Morphicons defaults to display:contents; force a real box so the SVG paints (inline styles win). */
export function prepareMorphHost(el: MorphIconElement, sizePx: number): void {
  el.style.setProperty('display', 'inline-flex', 'important');
  el.style.alignItems = 'center';
  el.style.justifyContent = 'center';
  el.style.width = `${sizePx}px`;
  el.style.height = `${sizePx}px`;
  el.style.lineHeight = '0';
  el.style.color = 'inherit';
  el.reducedMotion = resolveReducedMotion();
  el.spring = 'bouncy';
}

/** The element as a `<morph-icon>`, or null. */
export function asMorphIcon(el: Element | null): MorphIconElement | null {
  if (!el || el.tagName.toLowerCase() !== 'morph-icon') {
    return null;
  }

  return el as MorphIconElement;
}

/**
 * Generic sync: paints (`animate` false) or morphs to `icon` the morph-icon matching `selector` inside `host`, and
 * marks the host `is-morph-ready`. Returns false (no-op) when the host has no such morph-icon.
 */
export function syncMorphIcon(host: HTMLElement, selector: string, icon: MorphableIcon, animate: boolean, size = 14): boolean {
  const el = asMorphIcon(host.querySelector(selector));
  if (!el) {
    return false;
  }

  prepareMorphHost(el, size);
  if (animate) {
    el.morphTo(icon, 'bouncy');
  } else {
    el.set(icon);
  }
  host.classList.add('is-morph-ready');

  return true;
}

/** Theme toggle: moon (light → go dark) ↔ sun. */
export function syncThemeMorphIcon(button: HTMLElement, theme: Theme, animate: boolean, options: MorphSyncOptions = {}): void {
  syncMorphIcon(button, options.selector ?? '[data-theme-morph]', theme === 'dark' ? themeIcons.toLight : themeIcons.toDark, animate, options.size ?? 14);
}

/** Content width toggle: expand ↔ shrink. */
export function syncContentWidthMorphIcon(button: HTMLElement, width: ContentWidth, animate: boolean, options: MorphSyncOptions = {}): void {
  syncMorphIcon(
    button,
    options.selector ?? '[data-content-width-morph]',
    width === 'full' ? contentWidthIcons.toContent : contentWidthIcons.toFull,
    animate,
    options.size ?? 14,
  );
}

/** Sidebar burger: menu → rail → X. */
export function syncSidebarMorphIcon(button: HTMLElement, state: SidebarIconState, animate: boolean, options: MorphSyncOptions = {}): void {
  syncMorphIcon(button, options.selector ?? '[data-sidebar-morph]', sidebarIcons[state], animate, options.size ?? 18);
}

/**
 * Password visibility toggle: eye-off ↔ eye. Creates the morph-icon when the button has none (replacing its
 * content), with `className` (default `nowo-ui-password-toggle__morph`).
 */
export function syncPasswordMorphIcon(
  button: HTMLElement,
  visible: boolean,
  animate: boolean,
  options: MorphSyncOptions & { className?: string; strokeWidth?: string } = {},
): void {
  const selector = options.selector ?? '[data-password-morph]';
  if (!asMorphIcon(button.querySelector(selector))) {
    const el = document.createElement('morph-icon');
    el.setAttribute('data-password-morph', '');
    el.setAttribute('aria-hidden', 'true');
    el.setAttribute('size', String(options.size ?? 18));
    el.setAttribute('stroke-width', options.strokeWidth ?? '1.75');
    el.className = options.className ?? 'nowo-ui-password-toggle__morph';
    button.replaceChildren(el);
  }
  syncMorphIcon(button, selector, visible ? passwordIcons.visible : passwordIcons.hidden, animate, options.size ?? 18);
}

/** Listing customizer / drawer tab: sliders ↔ X. */
export function syncListingCustomizerMorphIcon(button: HTMLElement, open: boolean, animate: boolean, options: MorphSyncOptions = {}): void {
  syncMorphIcon(
    button,
    options.selector ?? '[data-listing-morph]',
    open ? listingCustomizerIcons.open : listingCustomizerIcons.closed,
    animate,
    options.size ?? 20,
  );
}

/** Table mode toggle (`responsive-table`): arrows ↔ rows, showing the current mode. */
export function syncTableModeMorphIcon(button: HTMLElement, mode: keyof typeof tableModeIcons, animate: boolean, options: MorphSyncOptions = {}): void {
  syncMorphIcon(button, options.selector ?? '[data-table-mode-morph]', tableModeIcons[mode], animate, options.size ?? 14);
}

/** Filter card fold toggle (`filters-collapse`): chevron up (shown) ↔ down (folded). */
export function syncFiltersCollapseMorphIcon(button: HTMLElement, collapsed: boolean, animate: boolean, options: MorphSyncOptions = {}): void {
  syncMorphIcon(
    button,
    options.selector ?? '[data-filters-collapse-morph]',
    collapsed ? filtersCollapseIcons.collapsed : filtersCollapseIcons.expanded,
    animate,
    options.size ?? 14,
  );
}

/** Disclosure chevron (`disclosure-icon`, `<details>`): right (closed) ↔ down (open). */
export function syncDisclosureMorphIcon(host: HTMLElement, open: boolean, animate: boolean, options: MorphSyncOptions = {}): void {
  syncMorphIcon(host, options.selector ?? '[data-disclosure-morph]', open ? disclosureIcons.open : disclosureIcons.closed, animate, options.size ?? 14);
}

/** Search toggle: magnifier (folded) ↔ X (stretched) ↔ loader while a request is in flight (`is-loading`). */
export function syncSearchMorphIcon(host: HTMLElement, state: keyof typeof searchIcons, animate: boolean, options: MorphSyncOptions = {}): void {
  if (syncMorphIcon(host, options.selector ?? '[data-search-morph]', searchIcons[state], animate, options.size ?? 14)) {
    host.classList.toggle('is-loading', state === 'loading');
  }
}

/** Help / support menu: lifebuoy (closed) ↔ X (open). */
export function syncSupportMorphIcon(host: HTMLElement, open: boolean, animate: boolean, options: MorphSyncOptions = {}): void {
  syncMorphIcon(host, options.selector ?? '[data-support-morph]', open ? supportIcons.open : supportIcons.closed, animate, options.size ?? 14);
}

/** Icon state handler for the kit peers (see `stimulus-peers/icon_state.ts`). */
export function morphiconsIconStateHandler(kind: IconStateKind, host: HTMLElement, state: string, animate: boolean): void {
  if ('table-mode' === kind) {
    syncTableModeMorphIcon(host, 'responsive' === state ? 'responsive' : 'scroll', animate);
  } else if ('filters-collapse' === kind) {
    syncFiltersCollapseMorphIcon(host, 'collapsed' === state, animate);
  } else {
    syncDisclosureMorphIcon(host, 'open' === state, animate);
  }
}

/** Animates the kit peers' icons with morphicons (call once, before Stimulus connects the controllers). */
export function installMorphiconPeers(): void {
  setIconStateHandler(morphiconsIconStateHandler);
}

export type { MorphIconElement };
