/**
 * Shell focus mode (2.55, `js/nowo-ui-focus-mode.js`; the `scripts` partial adds it when
 * `nowo_ui_kit.theme.focus_mode.enabled` is true). Two levels on the `[data-nowo-ui-shell]` root:
 *   1. `data-focus-mode="on"`: the configured shell regions (sidebar, header, footer, side panels, edge tabs, a host's
 *      dock…) get `inert` + `data-nowo-ui-focus-hidden` (display: none in the kit CSS), the work area goes full width
 *      and the focus bar (exit control, optional one-line breadcrumb) shows. Kept elements (`keep` list) stay.
 *   2. `data-focus-mode="fullscreen"`: level 1 plus the browser's Fullscreen API on the shell, entered from a control.
 *
 * Controls (delegated clicks, any element; a link's default is prevented):
 *   `[data-nowo-ui-focus-mode]` or `="toggle"`: level 1 on / off (aria-pressed follows)
 *   `[data-nowo-ui-focus-mode="fullscreen"]`: fullscreen on (entering level 1 first) / back to level 1 (aria-pressed;
 *     hidden where the browser or the config has no fullscreen)
 *   `[data-nowo-ui-focus-mode="exit"]`: leaves focus mode (and fullscreen)
 * Keyboard: the shell shortcut (`F` by default; none with `data-nowo-ui-focus-shortcut="none"`) toggles level 1 when the
 * page has a toggle, neither a text field nor a link / button / other control is focused, no modifier is pressed and no
 * dialog / menu is open. Escape leaves fullscreen first, then focus mode, only when nothing else owns it (see
 * overlayOwnsKeys) and no text field is focused. Ctrl / Cmd combinations (Ctrl+K search…) are never touched.
 * A second copy of this script only assigns the API (window flag Symbol.for('nowo-ui.focus-mode.bound')); the inert
 * watcher is one per page (Symbol.for('nowo-ui.focus-mode.inert-watcher')), whichever copy hides the regions.
 * Hidden elements: `inert` written by the host during focus mode is the host's (kept on exit).
 * Memory: per area (`data-nowo-ui-focus-area` on the toggle, an ancestor, or anywhere on the page) in localStorage
 * `nowo-ui-focus:<area>` = `on`, restored on load (level 1: browsers only allow fullscreen from a user gesture). Every
 * storage access is in try / catch: without storage nothing is remembered.
 * Accessibility: the focused element keeps focus unless it was hidden (then the exit control gets it; leaving focus
 * mode from the bar returns it to the toggle used to enter); each change is announced in the polite live region
 * `[data-nowo-ui-focus-status]` (created when missing) with the texts of its `data-nowo-ui-focus-msg-*` attributes.
 * Event: `nowo-ui:focus-mode:change` (bubbling, on the shell) `{ level, previous, area, source }`.
 * API: `window.NowoUiFocusMode` — enter / exit / toggle / fullscreen / level.
 * Delegated listeners, no inline handlers or style attributes (strict CSP); Turbo: re-applied on turbo:load and
 * turbo:frame-load; nothing is kept between pages but the DOM and the storage.
 */

import {
  areaOf,
  BAR,
  fullscreenSupported,
  HIDDEN_ATTR,
  HTML_STATE_ATTR,
  hideRegions,
  isEnabled,
  isInteractive,
  isTyping,
  levelOf,
  MESSAGES,
  messageAttr,
  type FocusLevel,
  type MessageKey,
  overlayOwnsKeys,
  readRemembered,
  remember,
  restoreRegions,
  SHELL,
  shortcutOf,
  STATE_ATTR,
  STATUS,
  TOGGLE,
} from './nowo-ui-focus-mode-core';
import { matchesShortcut, parseShortcut } from './nowo-ui-shortcut-core';

export type FocusSource = 'toggle' | 'shortcut' | 'escape' | 'fullscreenchange' | 'restore' | 'api';

/** The toggle that entered focus mode (focus goes back to it when the mode is left from the bar). */
let returnTo: HTMLElement | null = null;

/** The focus-mode shell of an element (its closest one), else the first one on the page. */
export function shellFor(from: Element | null): HTMLElement | null {
  const own = from?.closest(SHELL) ?? null;
  if (isEnabled(own)) return own;

  return [...document.querySelectorAll<HTMLElement>(SHELL)].find((shell) => isEnabled(shell)) ?? null;
}

function modeOf(toggle: Element): 'toggle' | 'fullscreen' | 'exit' {
  const value = toggle.getAttribute('data-nowo-ui-focus-mode');

  return value === 'fullscreen' || value === 'exit' ? value : 'toggle';
}

/** aria-pressed of every control follows the level; fullscreen controls hide where fullscreen is not available. */
export function syncToggles(shell: HTMLElement): void {
  const level = levelOf(shell);
  const fullscreen = fullscreenSupported(shell);
  document.querySelectorAll<HTMLElement>(TOGGLE).forEach((toggle) => {
    const mode = modeOf(toggle);
    if (mode === 'exit') return;
    toggle.setAttribute('aria-pressed', (mode === 'fullscreen' ? level === 'fullscreen' : level !== 'off') ? 'true' : 'false');
    if (mode === 'fullscreen' && !fullscreen) toggle.hidden = true;
  });
}

/** The live region (the first one in the shell, else on the page, else a new one at the end of the shell). */
export function statusRegion(shell: HTMLElement): HTMLElement {
  const found = shell.querySelector<HTMLElement>(STATUS) ?? document.querySelector<HTMLElement>(STATUS);
  if (found) return found;
  const region = document.createElement('p');
  region.className = 'visually-hidden';
  region.setAttribute('role', 'status');
  region.setAttribute('aria-live', 'polite');
  region.setAttribute('data-nowo-ui-focus-status', '');
  shell.appendChild(region);

  return region;
}

/** Announces a change in the live region (a repeated text alternates a trailing no-break space, so it is read again). */
export function announce(shell: HTMLElement, key: MessageKey): string {
  const region = statusRegion(shell);
  const text = region.getAttribute(messageAttr(key)) || MESSAGES[key];
  region.textContent = region.textContent === text ? `${text} ` : text;

  return text;
}

const HEIGHT_VAR = '--nowo-ui-focus-bar-height';
let barObserver: ResizeObserver | null = null;

/** Writes the focus bar's height on <html> (JS DOM, CSP-safe): its measured height with the sticky crumbs bar, else 0px (floating bar). */
function writeBarHeight(bar: HTMLElement): void {
  const height = bar.classList.contains('nowo-ui-focus-bar--crumbs') ? Math.ceil(bar.getBoundingClientRect().height) : 0;
  document.documentElement.style.setProperty(HEIGHT_VAR, `${height}px`);
}

/** Publishes `--nowo-ui-focus-bar-height` while focus mode is on (updated when the bar resizes); clears it when off. */
export function syncBarHeight(shell: HTMLElement | null, level: FocusLevel): void {
  barObserver?.disconnect();
  barObserver = null;
  const bar = level === 'off' || !shell ? null : (shell.querySelector<HTMLElement>(BAR) ?? document.querySelector<HTMLElement>(BAR));
  if (!bar) {
    document.documentElement.style.removeProperty(HEIGHT_VAR);
    return;
  }
  writeBarHeight(bar);
  if (typeof ResizeObserver === 'function') {
    barObserver = new ResizeObserver(() => writeBarHeight(bar));
    barObserver.observe(bar);
  }
}

function setState(shell: HTMLElement, level: FocusLevel): void {
  syncBarHeight(shell, level);
  if (level === 'off') {
    shell.removeAttribute(STATE_ATTR);
    document.documentElement.removeAttribute(HTML_STATE_ATTR);
  } else {
    shell.setAttribute(STATE_ATTR, level);
    document.documentElement.setAttribute(HTML_STATE_ATTR, level);
  }
  syncToggles(shell);
}

function dispatch(shell: HTMLElement, level: FocusLevel, previous: FocusLevel, area: string | null, source: FocusSource): void {
  shell.dispatchEvent(new CustomEvent('nowo-ui:focus-mode:change', { bubbles: true, detail: { level, previous, area, source } }));
}

function exitControl(shell: HTMLElement): HTMLElement | null {
  return shell.querySelector<HTMLElement>(`${BAR} [data-nowo-ui-focus-mode="exit"]`) ?? document.querySelector<HTMLElement>(`${BAR} [data-nowo-ui-focus-mode="exit"]`);
}

function isHidden(el: Element): boolean {
  return el.closest(`[${HIDDEN_ATTR}]`) !== null || el.closest('[hidden]') !== null;
}

/**
 * Level 1. `via` is the control used (the area comes from it). Returns whether the level changed.
 * The focused element keeps the focus unless focus mode hid it: then the exit control gets it.
 */
export function enter(shell: HTMLElement, source: FocusSource = 'api', via: Element | null = null, quiet = false): boolean {
  const previous = levelOf(shell);
  if (previous !== 'off') return false;
  const active = document.activeElement;
  setState(shell, 'on');
  hideRegions(shell);
  const area = areaOf(via);
  if (area && source !== 'restore') remember(area, true);
  if (via instanceof HTMLElement && modeOf(via) !== 'exit') returnTo = via;
  if (active instanceof HTMLElement && active !== document.body && isHidden(active)) exitControl(shell)?.focus();
  if (!quiet) announce(shell, 'on');
  dispatch(shell, 'on', previous, area, source);

  return true;
}

function exitFullscreen(): void {
  if (typeof document.exitFullscreen !== 'function' || !document.fullscreenElement) return;
  try {
    Promise.resolve(document.exitFullscreen()).catch(() => undefined);
  } catch {
    // Already left (or never entered): the fullscreenchange sync has nothing to do.
  }
}

/** Leaves focus mode (and fullscreen). Returns whether the level changed. */
export function exit(shell: HTMLElement, source: FocusSource = 'api', via: Element | null = null): boolean {
  const previous = levelOf(shell);
  if (previous === 'off') return false;
  const active = document.activeElement;
  // Left from the focus bar (which hides now): the focus goes back to the toggle that entered, else the page's toggle.
  const fromBar = (active instanceof Element && active.closest(BAR) !== null) || (via !== null && via.closest(BAR) !== null);
  if (previous === 'fullscreen' || document.fullscreenElement === shell) exitFullscreen();
  restoreRegions();
  setState(shell, 'off');
  const area = areaOf(via);
  if (area) remember(area, false);
  if (fromBar) {
    const back = returnTo && returnTo.isConnected && !isHidden(returnTo) && !returnTo.closest(BAR) ? returnTo : firstToggle();
    back?.focus();
  }
  returnTo = null;
  announce(shell, 'off');
  dispatch(shell, 'off', previous, area, source);

  return true;
}

/** The first level control outside the focus bar (the page's own toggle). */
function firstToggle(): HTMLElement | null {
  return [...document.querySelectorAll<HTMLElement>(TOGGLE)].find((toggle) => modeOf(toggle) !== 'exit' && !toggle.closest(BAR) && !isHidden(toggle)) ?? null;
}

/**
 * Level 2: enters level 1 when needed and asks the browser for fullscreen on the shell. Unsupported or refused: level 1
 * stays, without an error, and the live region says so. The level becomes `fullscreen` on the fullscreenchange event.
 */
export function requestFullscreen(shell: HTMLElement, source: FocusSource = 'api', via: Element | null = null): boolean {
  enter(shell, source, via, true);
  if (!fullscreenSupported(shell)) {
    announce(shell, 'fullscreen_unavailable');
    syncToggles(shell);
    return false;
  }
  const refused = (): void => {
    announce(shell, 'fullscreen_unavailable');
    syncToggles(shell);
  };
  try {
    const result = shell.requestFullscreen({ navigationUI: 'hide' });
    if (result && typeof result.catch === 'function') result.catch(refused);
  } catch {
    refused();
    return false;
  }

  return true;
}

/** Back to level 1 from fullscreen (the state follows on fullscreenchange). */
export function leaveFullscreen(shell: HTMLElement): boolean {
  if (levelOf(shell) !== 'fullscreen' && document.fullscreenElement !== shell) return false;
  exitFullscreen();

  return true;
}

/** fullscreenchange: the browser (Escape, F11, the API, a removed element) decides; the state follows it. */
export function onFullscreenChange(): void {
  const current = document.fullscreenElement ?? null;
  document.querySelectorAll<HTMLElement>(SHELL).forEach((shell) => {
    if (!isEnabled(shell)) return;
    const previous = levelOf(shell);
    if (current === shell && previous !== 'fullscreen') {
      if (previous === 'off') hideRegions(shell);
      setState(shell, 'fullscreen');
      announce(shell, 'fullscreen');
      dispatch(shell, 'fullscreen', previous, areaOf(null), 'fullscreenchange');
    } else if (current !== shell && previous === 'fullscreen') {
      setState(shell, 'on');
      announce(shell, 'fullscreen_off');
      dispatch(shell, 'on', previous, areaOf(null), 'fullscreenchange');
    }
  });
}

function onClick(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) return;
  const toggle = target.closest<HTMLElement>(TOGGLE);
  // Handled already (another copy of this script, a host handler): never toggle twice (audit SK-M2).
  if (!toggle || event.defaultPrevented) return;
  const shell = shellFor(toggle);
  if (!shell) return;
  // A modified or non-primary click on a link keeps the browser's behaviour (new tab / window).
  if (toggle instanceof HTMLAnchorElement && (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0)) return;
  event.preventDefault();
  const level = levelOf(shell);
  switch (modeOf(toggle)) {
    case 'exit':
      exit(shell, 'toggle', toggle);
      break;
    case 'fullscreen':
      if (level === 'fullscreen') {
        leaveFullscreen(shell);
      } else {
        requestFullscreen(shell, 'toggle', toggle);
      }
      break;
    default:
      if (level === 'off') {
        enter(shell, 'toggle', toggle);
      } else {
        exit(shell, 'toggle', toggle);
      }
  }
}

/** Whether the page offers focus mode (a toggle outside the focus bar): the shortcut does nothing elsewhere. */
function pageHasToggle(): boolean {
  return [...document.querySelectorAll(TOGGLE)].some((toggle) => modeOf(toggle) !== 'exit' && !toggle.closest(BAR));
}

function onKeydown(event: KeyboardEvent): void {
  if (event.defaultPrevented || event.isComposing) return;
  const shell = shellFor(event.target instanceof Element ? event.target : null);
  if (!shell) return;
  const level = levelOf(shell);

  if (event.key === 'Escape' || event.key === 'Esc') {
    // A text field keeps its Escape (clearing a search, leaving an editor's mode; audit SK-L3).
    if (level === 'off' || event.ctrlKey || event.altKey || event.metaKey || event.shiftKey || isTyping(event.target) || isTyping(document.activeElement) || overlayOwnsKeys(event.target)) return;
    event.preventDefault();
    if (level === 'fullscreen' || document.fullscreenElement === shell) {
      leaveFullscreen(shell);
    } else {
      exit(shell, 'escape');
    }
    return;
  }

  const shortcut = parseShortcut(shortcutOf(shell));
  if (!shortcut || event.repeat || !matchesShortcut(event, shortcut) || !pageHasToggle()) return;
  const plain = !shortcut.ctrl && !shortcut.alt && !shortcut.meta;
  // A single-character shortcut (WCAG 2.1.4) never fires while typing or while a link, button or other control has the
  // focus: there the key belongs to the control (audit I6).
  if ((plain && (isTyping(event.target) || isTyping(document.activeElement) || isInteractive(event.target) || isInteractive(document.activeElement))) || overlayOwnsKeys(event.target)) return;
  event.preventDefault();
  if (level === 'off') {
    enter(shell, 'shortcut', firstToggle());
  } else {
    exit(shell, 'shortcut', firstToggle());
  }
}

/**
 * Applies the state on start (and after a Turbo visit or frame load): a state the server rendered (`data-focus-mode`)
 * gets its regions hidden; otherwise a remembered area restores level 1 quietly (no announcement, no focus move).
 */
export function start(): void {
  const shells = [...document.querySelectorAll<HTMLElement>(SHELL)].filter((shell) => isEnabled(shell));
  // A page without a focus-mode shell (a Turbo visit away from one) keeps no stale state (audit I7).
  if (shells.length === 0) {
    document.documentElement.removeAttribute(HTML_STATE_ATTR);
    syncBarHeight(null, 'off');
  }
  shells.forEach((shell) => {
    // The live region exists before the first change, so the first message is read (audit I8).
    statusRegion(shell);
    const level = levelOf(shell);
    if (level !== 'off') {
      if (level === 'fullscreen' && document.fullscreenElement !== shell) shell.setAttribute(STATE_ATTR, 'on');
      document.documentElement.setAttribute(HTML_STATE_ATTR, levelOf(shell));
      syncBarHeight(shell, levelOf(shell));
      hideRegions(shell);
      syncToggles(shell);
      return;
    }
    const area = areaOf(null);
    if (area && readRemembered(area)) {
      enter(shell, 'restore', null, true);
      return;
    }
    document.documentElement.removeAttribute(HTML_STATE_ATTR);
    syncToggles(shell);
  });
}

function first(): HTMLElement {
  const shell = shellFor(null);
  if (!shell) throw new Error('No focus-mode shell ([data-nowo-ui-shell][data-nowo-ui-focus-regions]) on the page.');

  return shell;
}

export const api = {
  enter: (): boolean => enter(first(), 'api', firstToggle()),
  exit: (): boolean => exit(first(), 'api', firstToggle()),
  toggle: (): FocusLevel => {
    const shell = first();
    if (levelOf(shell) === 'off') {
      enter(shell, 'api', firstToggle());
    } else {
      exit(shell, 'api', firstToggle());
    }
    return levelOf(shell);
  },
  fullscreen: (): boolean => requestFullscreen(first(), 'api', firstToggle()),
  level: (): FocusLevel => {
    const shell = shellFor(null);
    return shell ? levelOf(shell) : 'off';
  },
};

/** Set on window by the first copy of this script: a second copy (a Turbo body script, two bundles) binds nothing. */
export const BOUND = Symbol.for('nowo-ui.focus-mode.bound');

export function bindNowoUiFocusMode(): void {
  const host = window as unknown as { NowoUiFocusMode: typeof api; [BOUND]?: true };
  host.NowoUiFocusMode = api;
  // A second copy stops here (audit SK-D1): the first copy's listeners (turbo:load, turbo:frame-load) apply the state.
  if (host[BOUND]) return;
  host[BOUND] = true;
  start();
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
  document.addEventListener('click', onClick);
  document.addEventListener('keydown', onKeydown);
  document.addEventListener('fullscreenchange', onFullscreenChange);
  document.addEventListener('turbo:load', start);
  document.addEventListener('turbo:frame-load', start);
}

bindNowoUiFocusMode();
