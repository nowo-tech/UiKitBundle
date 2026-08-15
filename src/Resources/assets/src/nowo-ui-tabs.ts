/**
 * In-page tab panels ([data-nowo-ui-tabs]).
 * Triggers: [data-nowo-ui-tabs-trigger][data-nowo-ui-tab-id]
 * Panels:   [data-nowo-ui-tabs-panel][data-nowo-ui-tab-id]
 * Initial:  data-nowo-ui-tabs-active on the root (optional).
 *
 * Link-based navigation tabs stay in Twig `_tabs.html.twig` (no JS required).
 */

declare global {
  interface Window {
    nowoUiActivateTab?: (root: HTMLElement | string | null, tabId: string) => void;
  }
}

function resolveRoot(root: HTMLElement | string | null): HTMLElement | null {
  if (root === null) {
    return null;
  }
  if (typeof root === 'string') {
    const el = document.getElementById(root) ?? document.querySelector(root);
    return el instanceof HTMLElement ? el : null;
  }
  return root;
}

export function activateTab(root: HTMLElement | string | null, tabId: string): void {
  const el = resolveRoot(root);
  if (!el || tabId === '') {
    return;
  }
  el.setAttribute('data-nowo-ui-tabs-active', tabId);

  el.querySelectorAll<HTMLElement>('[data-nowo-ui-tabs-trigger]').forEach((trigger) => {
    const id = trigger.getAttribute('data-nowo-ui-tab-id') ?? '';
    const isActive = id === tabId;
    trigger.toggleAttribute('data-active', isActive);
    trigger.setAttribute('aria-selected', isActive ? 'true' : 'false');
  });

  el.querySelectorAll<HTMLElement>('[data-nowo-ui-tabs-panel]').forEach((panel) => {
    const id = panel.getAttribute('data-nowo-ui-tab-id') ?? '';
    const isActive = id === tabId;
    panel.toggleAttribute('data-active', isActive);
    panel.dataset.state = isActive ? 'active' : 'inactive';
    panel.classList.toggle('hidden', !isActive);
    panel.hidden = !isActive;
  });
}

function syncRoot(root: HTMLElement): void {
  const active =
    root.getAttribute('data-nowo-ui-tabs-active') ??
    root.querySelector<HTMLElement>('[data-nowo-ui-tabs-trigger][data-active]')?.getAttribute('data-nowo-ui-tab-id') ??
    root.querySelector<HTMLElement>('[data-nowo-ui-tabs-trigger]')?.getAttribute('data-nowo-ui-tab-id') ??
    '';
  if (active !== '') {
    activateTab(root, active);
  }
}

function onDocumentClick(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) {
    return;
  }
  const trigger = target.closest('[data-nowo-ui-tabs-trigger]');
  if (!(trigger instanceof HTMLElement)) {
    return;
  }
  const root = trigger.closest('[data-nowo-ui-tabs]');
  if (!(root instanceof HTMLElement)) {
    return;
  }
  const tabId = trigger.getAttribute('data-nowo-ui-tab-id') ?? '';
  if (tabId === '') {
    return;
  }
  event.preventDefault();
  activateTab(root, tabId);
}

let tabsBound = false;

export function bindNowoUiTabs(): void {
  if (!tabsBound) {
    tabsBound = true;
    document.addEventListener('click', onDocumentClick);
    window.nowoUiActivateTab = activateTab;
  }
  document.querySelectorAll<HTMLElement>('[data-nowo-ui-tabs]').forEach(syncRoot);
}

bindNowoUiTabs();
