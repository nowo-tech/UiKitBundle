/**
 * Three-mode desktop sidebar (expanded / icons-only rail / hidden) — side-effect-free helpers.
 *
 * Shared by `nowo-ui-shell.ts` (opt-in rail on `[data-nowo-ui-shell][data-nowo-ui-sidebar-rail]`) and
 * `nowo-ui-theme-boot.ts` (pre-paint cookie ↔ localStorage sync). Bundled hosts with their own shell
 * markup can import these helpers directly and pass their own key names / classes.
 *
 * Persistence: localStorage key + optional cookie (so the server can render the right mode on the first
 * byte). The burger "ping-pongs" through the rail on desktop: hidden → rail → expanded → rail → hidden;
 * the walking direction lives in sessionStorage. Phones only toggle open / hidden and never persist.
 */

/** Desktop sidebar: full panel, icons-only rail, or hidden. Phones only use expanded (open) / hidden. */
export type SidebarMode = 'expanded' | 'rail' | 'hidden';

/** Burger state: closed (hidden), rail, or open (expanded). */
export type SidebarToggleState = 'closed' | 'rail' | 'open';

export type SidebarDirection = 'opening' | 'closing';

export interface SidebarOptions {
  /** localStorage key holding the desktop mode. */
  storageKey: string;
  /** Cookie name mirroring the mode for server rendering; empty = no cookie. */
  cookieName: string;
  cookieMaxAge: number;
  /** sessionStorage key for the burger walking direction. */
  directionKey: string;
  /** Media query below which the sidebar is a drawer (no rail, no persistence). */
  mobileQuery: string;
  /** Value written to storage / cookie for the hidden mode (e.g. `collapsed` for existing data). */
  hiddenValue: string;
}

export const SIDEBAR_DEFAULTS: SidebarOptions = {
  storageKey: 'nowo-ui-sidebar',
  cookieName: '',
  cookieMaxAge: 31536000,
  directionKey: 'nowo-ui-sidebar-direction',
  mobileQuery: '(max-width: 900px)',
  hiddenValue: 'hidden',
};

export interface SidebarShellClasses {
  hidden: string;
  rail: string;
  mobileOpen: string;
}

/** Kit shell classes (`nowo-ui.css`). */
export const SIDEBAR_KIT_CLASSES: SidebarShellClasses = {
  hidden: 'is-aside-collapsed',
  rail: 'is-aside-rail',
  mobileOpen: 'is-aside-open-mobile',
};

/** Elements that get a rail tooltip in the kit aside. */
export const SIDEBAR_KIT_RAIL_LABEL_SELECTOR = '.nowo-ui-aside__link, .nowo-ui-aside__group-toggle';

const ICON_SELECTOR = 'svg, img, i, .nowo-ui-icon';

/** Options from `data-nowo-ui-sidebar-*` attributes (missing ones keep the defaults). */
export function sidebarOptionsFrom(el: Element | null, base: SidebarOptions = SIDEBAR_DEFAULTS): SidebarOptions {
  const attr = (name: string): string | null => (el instanceof Element ? el.getAttribute(`data-nowo-ui-sidebar-${name}`) : null);
  const storageKey = attr('storage') || base.storageKey;
  const maxAge = Number.parseInt(attr('cookie-max-age') || '', 10);

  return {
    storageKey,
    cookieName: attr('cookie') ?? base.cookieName,
    cookieMaxAge: Number.isFinite(maxAge) && maxAge > 0 ? maxAge : base.cookieMaxAge,
    directionKey: attr('direction-storage') || `${storageKey}-direction`,
    mobileQuery: attr('mobile-query') || base.mobileQuery,
    hiddenValue: attr('hidden-value') || base.hiddenValue,
  };
}

/** Normalise a stored / configured value (`collapsed` is accepted for `hidden`). */
export function parseSidebarMode(value: unknown, options: SidebarOptions = SIDEBAR_DEFAULTS): SidebarMode | null {
  if (value === 'expanded' || value === 'rail' || value === 'hidden') {
    return value;
  }
  if (value === 'collapsed' || (typeof value === 'string' && value !== '' && value === options.hiddenValue)) {
    return 'hidden';
  }

  return null;
}

export function isSidebarMode(value: unknown): value is SidebarMode {
  return value === 'expanded' || value === 'rail' || value === 'hidden';
}

/** Value persisted for a mode. */
export function sidebarStoredValue(mode: SidebarMode, options: SidebarOptions = SIDEBAR_DEFAULTS): string {
  return mode === 'hidden' ? options.hiddenValue : mode;
}

/**
 * Burger cycle — desktop ping-pongs through the rail: hidden → rail → expanded → rail → hidden; the rail
 * goes on in the current `direction`. Phones: open ↔ hidden.
 */
export function nextSidebarMode(mode: SidebarMode, mobile: boolean, direction: SidebarDirection = 'opening'): SidebarMode {
  if (mobile) {
    return mode === 'hidden' ? 'expanded' : 'hidden';
  }
  if (mode !== 'rail') {
    return 'rail';
  }

  return direction === 'closing' ? 'hidden' : 'expanded';
}

/** Direction after leaving `mode` (leaving an end of the cycle flips it; the rail keeps it). */
export function sidebarDirectionAfter(mode: SidebarMode, direction: SidebarDirection): SidebarDirection {
  if (mode === 'hidden') {
    return 'opening';
  }

  return mode === 'expanded' ? 'closing' : direction;
}

export function readSidebarDirection(options: SidebarOptions = SIDEBAR_DEFAULTS): SidebarDirection {
  try {
    return window.sessionStorage.getItem(options.directionKey) === 'closing' ? 'closing' : 'opening';
  } catch {
    return 'opening';
  }
}

export function writeSidebarDirection(direction: SidebarDirection, options: SidebarOptions = SIDEBAR_DEFAULTS): void {
  try {
    window.sessionStorage.setItem(options.directionKey, direction);
  } catch {
    // Ignore: the cycle restarts in the "opening" direction.
  }
}

/** One burger click: next mode, remembering the direction when the cycle passes an end. */
export function advanceSidebarMode(mode: SidebarMode, mobile: boolean, options: SidebarOptions = SIDEBAR_DEFAULTS): SidebarMode {
  if (mobile) {
    return nextSidebarMode(mode, true);
  }
  const direction = sidebarDirectionAfter(mode, readSidebarDirection(options));
  writeSidebarDirection(direction, options);

  return nextSidebarMode(mode, false, direction);
}

/** Burger icon state for a mode (the rail never shows on phones). */
export function sidebarToggleState(mode: SidebarMode, mobile: boolean): SidebarToggleState {
  if (mode === 'hidden') {
    return 'closed';
  }

  return mode === 'rail' && !mobile ? 'rail' : 'open';
}

/** Pure resolution: phones start hidden; then the stored state; then the server / account default. */
export function resolveSidebarMode(
  mobile: boolean,
  stored: unknown,
  serverDefault: unknown,
  options: SidebarOptions = SIDEBAR_DEFAULTS,
): SidebarMode {
  if (mobile) {
    return 'hidden';
  }

  return parseSidebarMode(stored, options) ?? parseSidebarMode(serverDefault, options) ?? 'expanded';
}

export function sidebarCookieString(mode: SidebarMode, secure: boolean, options: SidebarOptions = SIDEBAR_DEFAULTS): string {
  return `${options.cookieName}=${sidebarStoredValue(mode, options)}; Path=/; Max-Age=${options.cookieMaxAge}; SameSite=Lax${secure ? '; Secure' : ''}`;
}

/** Valid mode from a `document.cookie` string, or null (also null when no cookie name is configured). */
export function parseSidebarCookie(cookie: string, options: SidebarOptions = SIDEBAR_DEFAULTS): SidebarMode | null {
  if (options.cookieName === '') {
    return null;
  }
  const prefix = `${options.cookieName}=`;
  const value = cookie
    .split(';')
    .map((part) => part.trim())
    .find((part) => part.startsWith(prefix))
    ?.slice(prefix.length);

  return parseSidebarMode(value, options);
}

export function writeSidebarCookie(mode: SidebarMode, options: SidebarOptions = SIDEBAR_DEFAULTS): void {
  if (options.cookieName === '') {
    return;
  }
  document.cookie = sidebarCookieString(mode, window.location.protocol === 'https:', options);
}

export function isMobileSidebar(options: SidebarOptions = SIDEBAR_DEFAULTS): boolean {
  return typeof window.matchMedia === 'function' && window.matchMedia(options.mobileQuery).matches;
}

export function readStoredSidebarMode(options: SidebarOptions = SIDEBAR_DEFAULTS): SidebarMode | null {
  try {
    return parseSidebarMode(window.localStorage.getItem(options.storageKey), options);
  } catch {
    return null;
  }
}

/** Store the desktop mode (localStorage + optional server-render cookie). */
export function persistSidebarMode(mode: SidebarMode, options: SidebarOptions = SIDEBAR_DEFAULTS): void {
  try {
    window.localStorage.setItem(options.storageKey, sidebarStoredValue(mode, options));
  } catch {
    // Private mode / quota: the cookie still carries the state.
  }
  writeSidebarCookie(mode, options);
}

/**
 * Pre-paint sync: a valid cookie wins over localStorage (the server may rewrite it, e.g. when an account
 * default is saved); otherwise seed localStorage from the server default; then mirror localStorage into
 * the cookie so the next response renders the right mode.
 */
export function syncSidebarStorageFromCookie(serverDefault: string, options: SidebarOptions = SIDEBAR_DEFAULTS): void {
  try {
    const cookieMode = parseSidebarCookie(document.cookie, options);
    const defaultMode = parseSidebarMode(serverDefault, options);
    if (cookieMode !== null) {
      window.localStorage.setItem(options.storageKey, sidebarStoredValue(cookieMode, options));
    } else if (defaultMode !== null && !window.localStorage.getItem(options.storageKey)) {
      window.localStorage.setItem(options.storageKey, sidebarStoredValue(defaultMode, options));
    }
    const stored = readStoredSidebarMode(options);
    if (stored !== null && cookieMode !== stored) {
      writeSidebarCookie(stored, options);
    }
  } catch {
    // Storage blocked: the server-rendered cookie state stays authoritative.
  }
}

/** Data attribute (camelCase dataset key) on the toggle holding the label of the next action. */
export function sidebarToggleLabelAttribute(next: SidebarMode): 'labelExpand' | 'labelRail' | 'labelHide' {
  return next === 'expanded' ? 'labelExpand' : next === 'rail' ? 'labelRail' : 'labelHide';
}

/**
 * Toggle a11y: aria-expanded is false only when the panel is hidden; aria-label / title name the next
 * action (`data-label-expand` / `data-label-rail` / `data-label-hide`). Returns the burger state.
 */
export function syncSidebarToggle(
  button: HTMLElement,
  mode: SidebarMode,
  mobile: boolean,
  options: SidebarOptions = SIDEBAR_DEFAULTS,
): SidebarToggleState {
  const state = sidebarToggleState(mode, mobile);
  button.setAttribute('aria-expanded', mode === 'hidden' ? 'false' : 'true');
  button.setAttribute('data-nowo-ui-sidebar-state', state);
  const direction = mobile ? 'opening' : sidebarDirectionAfter(mode, readSidebarDirection(options));
  const next = nextSidebarMode(mode, mobile, direction);
  // `data-label-collapse` is accepted as an alias of `data-label-hide`.
  const label = button.dataset[sidebarToggleLabelAttribute(next)] || (next === 'hidden' ? button.dataset.labelCollapse : undefined);
  if (label) {
    button.setAttribute('aria-label', label);
    button.setAttribute('title', label);
  }

  return state;
}

/** First letter shown as the fallback icon for rail items without one. */
export function railInitial(label: string): string {
  return Array.from(label.trim())[0]?.toLocaleUpperCase() ?? '';
}

/** DOM contract of the shell (kit defaults; hosts with their own markup override any of them). */
export interface SidebarDomOptions {
  classes: SidebarShellClasses;
  /** Elements that get a rail tooltip. */
  railLabelSelector: string;
  /** Prefix of the rail attributes: `{prefix}-label`, `{prefix}-title`, `{prefix}-initial`. */
  railAttributePrefix: string;
  /** Attribute receiving the current mode on the shell. */
  modeAttribute: string;
  /** Icon elements: items without one get the initial. */
  iconSelector: string;
  /** Visible text naming a rail item (default: its trimmed text). */
  labelFor: (el: HTMLElement) => string;
}

export const SIDEBAR_KIT_DOM: SidebarDomOptions = {
  classes: SIDEBAR_KIT_CLASSES,
  railLabelSelector: SIDEBAR_KIT_RAIL_LABEL_SELECTOR,
  railAttributePrefix: 'data-nowo-ui-rail',
  modeAttribute: 'data-nowo-ui-sidebar-mode',
  iconSelector: ICON_SELECTOR,
  labelFor: (el) => el.textContent?.trim() ?? '',
};

/**
 * The rail hides labels: expose them through `{prefix}-label` (CSS tooltip on :hover / :focus-visible).
 * A native `title` would double the tooltip, so it is parked in `{prefix}-title` and restored when
 * leaving the rail. Items without an icon get `{prefix}-initial` (first letter).
 */
export function syncSidebarRailLabels(root: ParentNode, rail: boolean, dom: Partial<SidebarDomOptions> = {}): void {
  const o: SidebarDomOptions = { ...SIDEBAR_KIT_DOM, ...dom };
  const labelAttr = `${o.railAttributePrefix}-label`;
  const titleAttr = `${o.railAttributePrefix}-title`;
  const initialAttr = `${o.railAttributePrefix}-initial`;
  root.querySelectorAll<HTMLElement>(o.railLabelSelector).forEach((el) => {
    if (rail) {
      if (el.hasAttribute(labelAttr)) {
        return;
      }
      const label = o.labelFor(el);
      if (label === '') {
        return;
      }
      el.setAttribute(labelAttr, label);
      el.setAttribute(titleAttr, el.getAttribute('title') ?? '');
      el.removeAttribute('title');
      if (el.querySelector(o.iconSelector) === null) {
        el.setAttribute(initialAttr, railInitial(label));
      }

      return;
    }

    if (!el.hasAttribute(labelAttr)) {
      return;
    }
    const title = el.getAttribute(titleAttr);
    if (title) {
      el.setAttribute('title', title);
    }
    el.removeAttribute(labelAttr);
    el.removeAttribute(titleAttr);
    el.removeAttribute(initialAttr);
  });
}

/** Shell classes / mode attribute for a mode, rail labels included. */
export function applySidebarShellState(
  shell: HTMLElement,
  mode: SidebarMode,
  mobile: boolean,
  dom: Partial<SidebarDomOptions> = {},
): void {
  const o: SidebarDomOptions = { ...SIDEBAR_KIT_DOM, ...dom };
  const hidden = mode === 'hidden';
  const rail = !mobile && mode === 'rail';
  shell.classList.toggle(o.classes.hidden, !mobile && hidden);
  shell.classList.toggle(o.classes.rail, rail);
  shell.classList.toggle(o.classes.mobileOpen, mobile && !hidden);
  shell.setAttribute(o.modeAttribute, mode);
  syncSidebarRailLabels(shell, rail, o);
}
