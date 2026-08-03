/**
 * App shell chrome: burger / left aside toggle + backdrop.
 * Expects a root with [data-nowo-ui-shell].
 */

declare global {
  interface Window {
    nowoUiToggleAside?: (shell: HTMLElement | null, forceOpen?: boolean) => void;
  }
}

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

export function bindNowoUiShell(): void {
  document.addEventListener('click', onDocumentClick);
  document.addEventListener('keydown', onDocumentKeydown);
  window.nowoUiToggleAside = toggleAside;
}

bindNowoUiShell();
