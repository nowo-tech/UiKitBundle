/**
 * App shell chrome: burger / left aside toggle, backdrop, main width (full ↔ content).
 * Expects a root with [data-nowo-ui-shell].
 */

declare global {
  interface Window {
    nowoUiToggleAside?: (shell: HTMLElement | null, forceOpen?: boolean) => void;
    nowoUiSetMainWidth?: (width: 'full' | 'content', shell?: HTMLElement | null) => void;
    nowoUiToggleMainWidth?: (shell?: HTMLElement | null) => void;
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
    toggleAside(shellRoot(burger));
    return;
  }

  const backdrop = target.closest('[data-nowo-ui-aside-backdrop]');
  if (backdrop) {
    toggleAside(shellRoot(backdrop), false);
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
  if (event.key !== 'Escape') {
    return;
  }
  const shell = document.querySelector('[data-nowo-ui-shell].is-aside-open-mobile');
  if (shell instanceof HTMLElement) {
    toggleAside(shell, false);
  }
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
  }
  document.querySelectorAll('[data-nowo-ui-shell]').forEach((el) => {
    if (el instanceof HTMLElement) {
      applyStoredMainWidth(el);
    }
  });
}

bindNowoUiShell();
