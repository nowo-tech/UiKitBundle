/**
 * App shell chrome: burger / left aside toggle, backdrop, main width (full ↔ content).
 * Expects a root with [data-nowo-ui-shell].
 *
 * Opt-in three-mode rail (1.15): add `data-nowo-ui-sidebar-rail` to the shell root. The burger then
 * walks hidden → rail → expanded → rail → hidden on desktop (drawer open / hidden on phones) and the
 * mode is persisted (localStorage + optional cookie). Key names: `data-nowo-ui-sidebar-storage`,
 * `data-nowo-ui-sidebar-cookie`, `data-nowo-ui-sidebar-hidden-value`, `data-nowo-ui-sidebar-default`
 * (server / account default). See `nowo-ui-sidebar-core.ts`.
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
  const btn = shell.querySelector('[data-nowo-ui-burger]');
  if (isDesktop()) {
    const collapsed = typeof forceOpen === 'boolean' ? !forceOpen : !shell.classList.contains('is-aside-collapsed');
    shell.classList.toggle('is-aside-collapsed', collapsed);
    shell.classList.remove('is-aside-open-mobile');
    document.body.classList.remove('nowo-ui-aside-open');
    setExpanded(btn, !collapsed);
    return;
  }
  const open = typeof forceOpen === 'boolean' ? forceOpen : !shell.classList.contains('is-aside-open-mobile');
  shell.classList.toggle('is-aside-open-mobile', open);
  document.body.classList.toggle('nowo-ui-aside-open', open);
  setExpanded(btn, open);
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

function onDocumentClick(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) {
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
    }
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
  if (event.key !== 'Escape') {
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

let shellBound = false;

export function bindNowoUiShell(): void {
  if (!shellBound) {
    shellBound = true;
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onDocumentKeydown);
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
  }
  document.querySelectorAll('[data-nowo-ui-shell]').forEach((el) => {
    if (el instanceof HTMLElement) {
      applyStoredMainWidth(el);
      if (isRailShell(el)) {
        initSidebarRail(el);
      }
    }
  });
}

bindNowoUiShell();
