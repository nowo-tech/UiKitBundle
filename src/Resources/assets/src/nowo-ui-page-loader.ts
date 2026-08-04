/**
 * Full-page loader overlay ([data-nowo-ui-page-loader]).
 * Toggle via helpers or [data-nowo-ui-page-loader-show] / [data-nowo-ui-page-loader-hide].
 */

declare global {
  interface Window {
    nowoUiShowPageLoader?: () => void;
    nowoUiHidePageLoader?: () => void;
  }
}

function loaderRoot(): HTMLElement | null {
  const el = document.querySelector('[data-nowo-ui-page-loader]');
  return el instanceof HTMLElement ? el : null;
}

export function showPageLoader(): void {
  const el = loaderRoot();
  if (!el) {
    return;
  }
  el.classList.add('is-active');
  el.setAttribute('aria-busy', 'true');
  el.removeAttribute('hidden');
}

export function hidePageLoader(): void {
  const el = loaderRoot();
  if (!el) {
    return;
  }
  el.classList.remove('is-active');
  el.setAttribute('aria-busy', 'false');
  el.setAttribute('hidden', '');
}

function onDocumentClick(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) {
    return;
  }
  if (target.closest('[data-nowo-ui-page-loader-show]')) {
    showPageLoader();
    return;
  }
  if (target.closest('[data-nowo-ui-page-loader-hide]')) {
    hidePageLoader();
  }
}

let loaderBound = false;

export function bindNowoUiPageLoader(): void {
  if (loaderBound) {
    return;
  }
  loaderBound = true;
  document.addEventListener('click', onDocumentClick);
  window.nowoUiShowPageLoader = showPageLoader;
  window.nowoUiHidePageLoader = hidePageLoader;
}

bindNowoUiPageLoader();
