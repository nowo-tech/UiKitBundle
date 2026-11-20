/**
 * App shell chrome: burger / left aside toggle, backdrop, main width (full ↔ content).
 * Expects a root with [data-nowo-ui-shell].
 *
 * Opt-in three-mode rail (1.15): add `data-nowo-ui-sidebar-rail` to the shell root. The burger then
 * walks hidden → rail → expanded → rail → hidden on desktop (drawer open / hidden on phones) and the
 * mode is persisted (localStorage + optional cookie). Key names: `data-nowo-ui-sidebar-storage`,
 * `data-nowo-ui-sidebar-cookie`, `data-nowo-ui-sidebar-hidden-value`, `data-nowo-ui-sidebar-default`
 * (server / account default). See `nowo-ui-sidebar-core.ts`.
 *
 * Hover-expanded rail (2.32): with `data-nowo-ui-sidebar-hover-expand` on a rail shell, the pointer or the keyboard
 * focus on the icons-only rail adds `is-aside-peek` (the kit CSS widens the panel over the page); leaving it, Escape or
 * any mode change removes it. The pointer leaving closes it even after a click inside (a focused button); keyboard
 * focus inside keeps it open. Short delays keep a pointer that only crosses the rail from opening it; touch never peeks.
 * Every `[data-nowo-ui-burger]` of a shell (the header's and, in the full-height layout, the aside head's) is kept in
 * sync.
 *
 * Accessible drawer (2.60, opt-in with `data-nowo-ui-drawer-a11y` on a two-mode shell root, set by the demo platform
 * shell): a hidden aside (closed phone drawer, collapsed desktop aside) is `inert`, so its links leave the tab order; the
 * burgers carry the real state (aria-expanded, next-action label, glyph) on load and on every breakpoint change; opening
 * the phone drawer moves the focus into it (the current link, else the first one), makes the rest of the page inert and
 * locks the page scroll (`nowo-ui-drawer-open` on <html>); closing it (burger, backdrop, Escape, a link) gives the page
 * back and returns the focus to the burger when it was inside the drawer. Shells without the attribute are unchanged.
 *
 * Dismissible messages (2.60): a click on `[data-nowo-ui-dismiss]` removes its `[data-nowo-ui-dismissible]` ancestor.
 */

import {
  advanceSidebarMode,
  applySidebarShellState,
  isMobileSidebar,
  parseSidebarMode,
  persistSidebarMode,
  readStoredSidebarMode,
  resolveSidebarMode,
  sidebarOptionsFrom,
  SIDEBAR_KIT_RAIL_LABEL_SELECTOR,
  syncSidebarToggle,
  type SidebarMode,
} from './nowo-ui-sidebar-core';

declare global {
  interface Window {
    nowoUiToggleAside?: (shell: HTMLElement | null, forceOpen?: boolean) => void;
    nowoUiSetMainWidth?: (width: 'full' | 'content', shell?: HTMLElement | null) => void;
    nowoUiToggleMainWidth?: (shell?: HTMLElement | null) => void;
    nowoUiSetSidebarMode?: (mode: SidebarMode, shell?: HTMLElement | null) => void;
  }
}

export type MainWidth = 'full' | 'content';

function shellRoot(from: Element | null): HTMLElement | null {
  if (from instanceof Element) {
    const closest = from.closest('[data-nowo-ui-shell]');
    if (closest instanceof HTMLElement) {
      return closest;
    }
  }
  const fallback = document.querySelector('[data-nowo-ui-shell]');
  return fallback instanceof HTMLElement ? fallback : null;
}

function setExpanded(btn: Element | null, open: boolean): void {
  if (btn instanceof HTMLElement) {
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
}

function isDesktop(): boolean {
  return window.matchMedia('(min-width: 901px)').matches;
}

export function toggleAside(shell: HTMLElement | null, forceOpen?: boolean): void {
  if (!shell) {
    return;
  }
  const burgers = shell.querySelectorAll('[data-nowo-ui-burger]');
  if (isDesktop()) {
    const collapsed = typeof forceOpen === 'boolean' ? !forceOpen : !shell.classList.contains('is-aside-collapsed');
    shell.classList.toggle('is-aside-collapsed', collapsed);
    shell.classList.remove('is-aside-open-mobile');
    document.body.classList.remove('nowo-ui-aside-open');
    burgers.forEach((btn) => setExpanded(btn, !collapsed));
    syncDrawer(shell);
    return;
  }
  const open = typeof forceOpen === 'boolean' ? forceOpen : !shell.classList.contains('is-aside-open-mobile');
  shell.classList.toggle('is-aside-open-mobile', open);
  document.body.classList.toggle('nowo-ui-aside-open', open);
  burgers.forEach((btn) => setExpanded(btn, open));
  syncDrawer(shell, open ? 'open' : 'close');
}

export const DRAWER_A11Y_ATTRIBUTE = 'data-nowo-ui-drawer-a11y';
export const DRAWER_OPEN_ROOT_CLASS = 'nowo-ui-drawer-open';
/** Regions made inert while the phone drawer is open (the burger's own header start stays usable). */
const DRAWER_INERT_SELECTOR = '.nowo-ui-main, [data-nowo-ui-shell] > footer, [data-nowo-ui-shell] > .nowo-ui-footer, .nowo-ui-header__end, .nowo-ui-header__context';

function hasDrawerA11y(shell: HTMLElement): boolean {
  return shell.hasAttribute(DRAWER_A11Y_ATTRIBUTE) && !isRailShell(shell);
}

function setInert(el: Element | null, inert: boolean): void {
  if (!(el instanceof HTMLElement)) {
    return;
  }
  if (inert) {
    el.setAttribute('inert', '');
  } else {
    el.removeAttribute('inert');
  }
}

/** Whether the aside of an accessible-drawer shell is shown now (phone: open drawer; desktop: not collapsed). */
export function isAsideShown(shell: HTMLElement): boolean {
  return isDesktop() ? !shell.classList.contains('is-aside-collapsed') : shell.classList.contains('is-aside-open-mobile');
}

/**
 * Bring an accessible-drawer shell in line with its state: inert aside when hidden, burger state, page inert + scroll
 * lock while the phone drawer is open. `transition` moves the focus: `open` into the drawer, `close` back to the burger
 * when the focus was inside the drawer (or on the page behind it).
 */
export function syncDrawer(shell: HTMLElement, transition: 'open' | 'close' | null = null): void {
  if (!hasDrawerA11y(shell)) {
    return;
  }
  const aside = shell.querySelector('[data-nowo-ui-aside]');
  const shown = isAsideShown(shell);
  const drawerOpen = !isDesktop() && shown;
  const focusWasInside = aside instanceof HTMLElement && aside.contains(document.activeElement);
  setInert(aside, !shown);
  shell.querySelectorAll(DRAWER_INERT_SELECTOR).forEach((el) => setInert(el, drawerOpen));
  document.documentElement.classList.toggle(DRAWER_OPEN_ROOT_CLASS, drawerOpen);
  shell.querySelectorAll('[data-nowo-ui-burger]').forEach((btn) => {
    if (!(btn instanceof HTMLElement)) {
      return;
    }
    setExpanded(btn, shown);
    btn.setAttribute('data-nowo-ui-burger-glyph', shown ? 'close' : 'open');
    const label = shown ? btn.getAttribute('data-label-hide') : btn.getAttribute('data-label-expand');
    if (label) {
      btn.setAttribute('aria-label', label);
      btn.setAttribute('title', label);
    }
  });
  if (transition === 'open' && drawerOpen && aside instanceof HTMLElement) {
    const target = aside.querySelector<HTMLElement>('[aria-current="page"]') ?? aside.querySelector<HTMLElement>('a[href], button:not([disabled])');
    target?.focus();
  } else if (transition === 'close' && (focusWasInside || document.activeElement === document.body)) {
    const burger = Array.from(shell.querySelectorAll<HTMLElement>('[data-nowo-ui-burger]')).find((el) => isShown(el));
    burger?.focus();
  }
}

function onDrawerViewportChange(): void {
  document.querySelectorAll(`[data-nowo-ui-shell][${DRAWER_A11Y_ATTRIBUTE}]`).forEach((el) => {
    if (el instanceof HTMLElement) {
      // Leaving the phone layout with the drawer open: the desktop aside takes over, nothing stays locked.
      if (isDesktop()) {
        el.classList.remove('is-aside-open-mobile');
        document.body.classList.remove('nowo-ui-aside-open');
      }
      syncDrawer(el);
    }
  });
}

export const PEEK_CLASS = 'is-aside-peek';
export const PEEK_OPEN_DELAY_MS = 120;
export const PEEK_CLOSE_DELAY_MS = 220;

/** Whether the shell may peek now: hover expand on, the icons-only rail, desktop. */
export function canPeek(shell: HTMLElement): boolean {
  return (
    shell.hasAttribute('data-nowo-ui-sidebar-hover-expand') &&
    shell.classList.contains('is-aside-rail') &&
    !isMobileSidebar(sidebarOptionsFrom(shell))
  );
}

/** Wires the hover expand of a shell's aside once; returns false when there is nothing to bind or it already is. */
export function bindSidebarPeek(shell: HTMLElement): boolean {
  const aside = shell.querySelector('[data-nowo-ui-aside]');
  if (!(aside instanceof HTMLElement) || !shell.hasAttribute('data-nowo-ui-sidebar-hover-expand') || aside.hasAttribute('data-nowo-ui-peek-bound')) {
    return false;
  }
  aside.setAttribute('data-nowo-ui-peek-bound', '');
  let timer: number | undefined;
  // Whether the focus inside the panel came from a pointer (a click): then leaving with the pointer closes the peek;
  // keyboard focus keeps it open until the focus leaves or Escape.
  let pointerFocus = false;
  let pointerPressed = false;
  const schedule = (open: boolean, delay: number): void => {
    window.clearTimeout(timer);
    timer = window.setTimeout(() => {
      shell.classList.toggle(PEEK_CLASS, open && canPeek(shell));
    }, delay);
  };

  aside.addEventListener('pointerenter', (event: PointerEvent) => {
    if (event.pointerType !== 'touch' && canPeek(shell)) {
      schedule(true, PEEK_OPEN_DELAY_MS);
    }
  });
  aside.addEventListener('pointerdown', () => {
    pointerPressed = true;
    pointerFocus = true;
  });
  aside.addEventListener('pointerup', () => {
    pointerPressed = false;
  });
  aside.addEventListener('pointerleave', () => {
    pointerPressed = false;
    if (pointerFocus || !aside.contains(document.activeElement)) {
      schedule(false, PEEK_CLOSE_DELAY_MS);
    }
  });
  aside.addEventListener('focusin', () => {
    // A focus move without a pointer press (Tab, a script) is keyboard focus.
    pointerFocus = pointerPressed;
    if (canPeek(shell)) {
      schedule(true, 0);
    }
  });
  aside.addEventListener('focusout', (event: FocusEvent) => {
    if (!(event.relatedTarget instanceof Node && aside.contains(event.relatedTarget))) {
      schedule(false, PEEK_CLOSE_DELAY_MS);
    }
  });
  aside.addEventListener('keydown', (event: KeyboardEvent) => {
    pointerFocus = false;
    if (event.key === 'Escape' && shell.classList.contains(PEEK_CLASS)) {
      window.clearTimeout(timer);
      shell.classList.remove(PEEK_CLASS);
    }
  });
  shell.addEventListener('nowo-ui:sidebar-mode', () => {
    window.clearTimeout(timer);
  });

  return true;
}

function isRailShell(shell: HTMLElement | null): shell is HTMLElement {
  return shell instanceof HTMLElement && shell.hasAttribute('data-nowo-ui-sidebar-rail');
}

/** Current rail-shell mode (from `data-nowo-ui-sidebar-mode`, else resolved from storage / default). */
export function currentSidebarMode(shell: HTMLElement): SidebarMode {
  const options = sidebarOptionsFrom(shell);
  return (
    parseSidebarMode(shell.getAttribute('data-nowo-ui-sidebar-mode'), options) ??
    resolveSidebarMode(isMobileSidebar(options), readStoredSidebarMode(options), shell.getAttribute('data-nowo-ui-sidebar-default'), options)
  );
}

/**
 * Apply a mode to a rail shell (classes, burger a11y, rail tooltips). Desktop modes are persisted unless
 * `persist` is false; phone drawer state never is.
 */
export function setSidebarMode(shell: HTMLElement | null, mode: SidebarMode, persist = true): void {
  if (!isRailShell(shell)) {
    return;
  }
  const options = sidebarOptionsFrom(shell);
  const mobile = isMobileSidebar(options);
  const selector = shell.getAttribute('data-nowo-ui-sidebar-rail-labels') || SIDEBAR_KIT_RAIL_LABEL_SELECTOR;
  applySidebarShellState(shell, mode, mobile, { railLabelSelector: selector });
  shell.classList.remove(PEEK_CLASS);
  document.body.classList.toggle('nowo-ui-aside-open', mobile && mode !== 'hidden');
  shell.querySelectorAll('[data-nowo-ui-burger]').forEach((btn) => {
    if (btn instanceof HTMLElement) {
      syncSidebarToggle(btn, mode, mobile, options);
    }
  });
  if (persist && !mobile) {
    persistSidebarMode(mode, options);
  }
  shell.dispatchEvent(new CustomEvent('nowo-ui:sidebar-mode', { bubbles: true, detail: { mode, mobile } }));
}

/** One burger click on a rail shell. */
export function advanceSidebar(shell: HTMLElement | null): void {
  if (!isRailShell(shell)) {
    return;
  }
  const options = sidebarOptionsFrom(shell);
  setSidebarMode(shell, advanceSidebarMode(currentSidebarMode(shell), isMobileSidebar(options), options));
}

/** Initial (or viewport-change) mode: phones hidden, desktop stored → server default → expanded. */
export function initSidebarRail(shell: HTMLElement): void {
  const options = sidebarOptionsFrom(shell);
  const mode = resolveSidebarMode(
    isMobileSidebar(options),
    readStoredSidebarMode(options),
    shell.getAttribute('data-nowo-ui-sidebar-default'),
    options,
  );
  setSidebarMode(shell, mode, false);
}

function toggleNavGroup(group: HTMLElement): void {
  const open = !group.classList.contains('is-open');
  group.classList.toggle('is-open', open);
  const btn = group.querySelector('[data-nowo-ui-nav-group-toggle]');
  if (btn instanceof HTMLElement) {
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
}

function widthStorageKey(btn: Element | null): string {
  if (btn instanceof HTMLElement) {
    return btn.getAttribute('data-nowo-ui-width-storage') || 'nowo-ui-main-width';
  }
  return 'nowo-ui-main-width';
}

function readStoredWidth(btn: Element | null = null): MainWidth | null {
  try {
    const raw = window.localStorage.getItem(widthStorageKey(btn));
    if (raw === 'full' || raw === 'content') {
      return raw;
    }
  } catch {
    /* private mode */
  }
  return null;
}

function currentMainWidth(shell: HTMLElement | null): MainWidth {
  if (shell?.classList.contains('is-main-content')) {
    return 'content';
  }
  return 'full';
}

function syncWidthToggleUi(shell: HTMLElement, width: MainWidth): void {
  shell.querySelectorAll('[data-nowo-ui-width-toggle]').forEach((btn) => {
    if (!(btn instanceof HTMLElement)) {
      return;
    }
    const toFull = btn.getAttribute('data-aria-to-full') || 'Switch to full width';
    const toContent = btn.getAttribute('data-aria-to-content') || 'Switch to content width';
    btn.setAttribute('aria-pressed', width === 'content' ? 'true' : 'false');
    btn.setAttribute('aria-label', width === 'content' ? toFull : toContent);
    const label = btn.querySelector('[data-nowo-ui-width-label]');
    if (label) {
      const fullLabel = btn.getAttribute('data-label-full') || 'Full width';
      const contentLabel = btn.getAttribute('data-label-content') || 'Content';
      label.textContent = width === 'content' ? contentLabel : fullLabel;
    }
  });
}

export function setMainWidth(width: MainWidth, shell: HTMLElement | null = null, btn: Element | null = null): void {
  const root = shell ?? shellRoot(btn);
  if (!root) {
    return;
  }
  root.classList.toggle('is-main-content', width === 'content');
  try {
    window.localStorage.setItem(widthStorageKey(btn ?? root.querySelector('[data-nowo-ui-width-toggle]')), width);
  } catch {
    /* private mode */
  }
  syncWidthToggleUi(root, width);
}

export function toggleMainWidth(shell: HTMLElement | null = null, btn: Element | null = null): void {
  const root = shell ?? shellRoot(btn);
  if (!root) {
    return;
  }
  setMainWidth(currentMainWidth(root) === 'content' ? 'full' : 'content', root, btn);
}

function applyStoredMainWidth(shell: HTMLElement): void {
  const btn = shell.querySelector('[data-nowo-ui-width-toggle]');
  const stored = readStoredWidth(btn);
  if (stored) {
    setMainWidth(stored, shell, btn);
    return;
  }
  syncWidthToggleUi(shell, currentMainWidth(shell));
}

function isShown(el: Element): boolean {
  return el.getClientRects().length > 0 && window.getComputedStyle(el).visibility !== 'hidden';
}

/**
 * The full-height layout has two burgers (header and aside head) and shows one at a time: when the focused one has just
 * been hidden by its own click, the focus moves to the one now shown, so a keyboard user never lands on <body>.
 */
export function keepBurgerFocus(shell: HTMLElement | null, burger: Element): void {
  if (!shell || document.activeElement !== burger || isShown(burger)) {
    return;
  }
  const other = Array.from(shell.querySelectorAll('[data-nowo-ui-burger]')).find((el) => el !== burger && isShown(el));
  if (other instanceof HTMLElement) {
    other.focus();
  }
}

function onDocumentClick(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) {
    return;
  }

  // 2.60: an aria-disabled button or link never acts (CSS removes pointer events; this stops keyboard activation).
  if (target.closest('.nowo-ui-btn[aria-disabled="true"]')) {
    event.preventDefault();
    event.stopImmediatePropagation();
    return;
  }

  const burger = target.closest('[data-nowo-ui-burger]');
  if (burger) {
    event.preventDefault();
    const shell = shellRoot(burger);
    if (isRailShell(shell)) {
      advanceSidebar(shell);
    } else {
      toggleAside(shell);
    }
    keepBurgerFocus(shell, burger);
    return;
  }

  const backdrop = target.closest('[data-nowo-ui-aside-backdrop]');
  if (backdrop) {
    const shell = shellRoot(backdrop);
    if (isRailShell(shell)) {
      setSidebarMode(shell, 'hidden');
    } else {
      toggleAside(shell, false);
    }
    return;
  }

  // Phones: choosing a destination closes the rail-shell drawer.
  const asideLink = target.closest('[data-nowo-ui-aside] a[href]');
  if (asideLink) {
    const shell = shellRoot(asideLink);
    if (isRailShell(shell) && isMobileSidebar(sidebarOptionsFrom(shell))) {
      setSidebarMode(shell, 'hidden');
    } else if (shell && hasDrawerA11y(shell) && shell.classList.contains('is-aside-open-mobile')) {
      toggleAside(shell, false);
    }
  }

  const dismiss = target.closest('[data-nowo-ui-dismiss]');
  if (dismiss) {
    const box = dismiss.closest('[data-nowo-ui-dismissible]');
    if (box) {
      event.preventDefault();
      box.remove();
    }
    return;
  }

  const widthToggle = target.closest('[data-nowo-ui-width-toggle]');
  if (widthToggle) {
    event.preventDefault();
    toggleMainWidth(shellRoot(widthToggle), widthToggle);
    return;
  }

  const navToggle = target.closest('[data-nowo-ui-nav-group-toggle]');
  if (navToggle) {
    const group = navToggle.closest('[data-nowo-ui-nav-group]');
    if (group instanceof HTMLElement) {
      event.preventDefault();
      toggleNavGroup(group);
    }
  }
}

function onDocumentKeydown(event: KeyboardEvent): void {
  if (event.key !== 'Escape' || event.defaultPrevented || document.querySelector('dialog[open]')) {
    return;
  }
  const shell = document.querySelector('[data-nowo-ui-shell].is-aside-open-mobile');
  if (shell instanceof HTMLElement) {
    if (isRailShell(shell)) {
      setSidebarMode(shell, 'hidden');
    } else {
      toggleAside(shell, false);
    }
  }
}

function onViewportChange(): void {
  document.querySelectorAll('[data-nowo-ui-shell][data-nowo-ui-sidebar-rail]').forEach((el) => {
    if (el instanceof HTMLElement) {
      initSidebarRail(el);
    }
  });
}

/**
 * Locale switcher (2.8): the page `#fragment` never reaches the server, so a language change would lose the section
 * being read. Inside `[data-nowo-ui-locale-switcher]`, a clicked link or a submitted form gets the current fragment
 * (after the POST, a redirect without a fragment of its own keeps the form action's one).
 */
export function keepLocaleHash(event: Event): void {
  const hash = window.location.hash;
  const target = event.target;
  if (hash === '' || !(target instanceof Element) || !target.closest('[data-nowo-ui-locale-switcher]')) {
    return;
  }
  if (target instanceof HTMLFormElement) {
    target.action = target.action.split('#')[0] + hash;
    return;
  }
  const link = target.closest<HTMLAnchorElement>('a[href]');
  if (link) {
    link.href = link.href.split('#')[0] + hash;
  }
}

let shellBound = false;

export function bindNowoUiShell(): void {
  if (!shellBound) {
    shellBound = true;
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onDocumentKeydown);
    document.addEventListener('click', keepLocaleHash);
    document.addEventListener('submit', keepLocaleHash);
    window.nowoUiToggleAside = toggleAside;
    window.nowoUiSetMainWidth = (width, shell) => setMainWidth(width, shell ?? null);
    window.nowoUiToggleMainWidth = (shell) => toggleMainWidth(shell ?? null);
    window.nowoUiSetSidebarMode = (mode, shell) =>
      setSidebarMode(shell ?? (document.querySelector('[data-nowo-ui-shell][data-nowo-ui-sidebar-rail]') as HTMLElement | null), mode);
    const railShell = document.querySelector('[data-nowo-ui-shell][data-nowo-ui-sidebar-rail]');
    if (railShell && typeof window.matchMedia === 'function') {
      const query = window.matchMedia(sidebarOptionsFrom(railShell).mobileQuery);
      query.addEventListener?.('change', onViewportChange);
    }
    if (document.querySelector(`[data-nowo-ui-shell][${DRAWER_A11Y_ATTRIBUTE}]`) && typeof window.matchMedia === 'function') {
      window.matchMedia('(min-width: 901px)').addEventListener?.('change', onDrawerViewportChange);
    }
  }
  document.querySelectorAll('[data-nowo-ui-shell]').forEach((el) => {
    if (el instanceof HTMLElement) {
      applyStoredMainWidth(el);
      if (isRailShell(el)) {
        initSidebarRail(el);
        bindSidebarPeek(el);
        return;
      }
      // A server-rendered "open" drawer never survives a phone load: the drawer starts closed.
      const shell: HTMLElement = el;
      if (hasDrawerA11y(shell) && !isDesktop()) {
        shell.classList.remove('is-aside-open-mobile');
      }
      syncDrawer(shell);
    }
  });
}

bindNowoUiShell();
