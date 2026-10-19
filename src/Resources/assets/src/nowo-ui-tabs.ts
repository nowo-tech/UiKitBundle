/**
 * In-page tab panels ([data-nowo-ui-tabs]).
 * Triggers: [data-nowo-ui-tabs-trigger][data-nowo-ui-tab-id]
 * Panels:   [data-nowo-ui-tabs-panel][data-nowo-ui-tab-id]
 * Initial:  data-nowo-ui-tabs-active on the root (optional).
 *
 * 2.5 (WAI-ARIA tabs pattern, `locale_tabs` partial):
 * - every root only drives its OWN triggers / panels (the nearest [data-nowo-ui-tabs]), so tab sets nest;
 * - triggers with role="tab": roving tabindex, ArrowLeft / ArrowRight (mirrored in RTL; ArrowUp / ArrowDown with
 *   aria-orientation="vertical"), Home / End move the selection (automatic activation) and the focus;
 * - an `invalid` event of a control inside a hidden panel opens that panel (and its parent panels) so the browser
 *   can focus the control and show its message;
 * - `nowo-ui:tabs:change` (bubbling CustomEvent on the root, detail {tabId, previousTabId, trigger, panel, reason})
 *   after each change — mount editors (CKEditor, Tiptap) of a panel that just became visible;
 * - progressive roots ([data-nowo-ui-tabs-upgrade]) render every panel visible (stacked fieldsets) until this script
 *   upgrades them: it shows the [data-nowo-ui-tabs-list] tab list, gives the panels role="tabpanel" and sets
 *   data-nowo-ui-tabs-ready;
 * - active / inactive trigger classes from data-nowo-ui-tabs-tab-active-class / data-nowo-ui-tabs-tab-class on the
 *   root (theme slots tab_active / tab);
 * - re-sync after a DOM morph (Live Component re-render, Turbo morph, remote dialog content): the tab the user chose
 *   stays open, new roots are upgraded.
 *
 * Link-based navigation tabs stay in Twig `_tabs.html.twig` (no JS required).
 */

declare global {
  interface Window {
    nowoUiActivateTab?: (root: HTMLElement | string | null, tabId: string) => void;
  }
}

export const TABS_CHANGE_EVENT = 'nowo-ui:tabs:change';

export type TabsChangeReason = 'click' | 'keyboard' | 'invalid' | 'api';

export interface TabsChangeDetail {
  tabId: string;
  previousTabId: string | null;
  trigger: HTMLElement | null;
  panel: HTMLElement | null;
  reason: TabsChangeReason;
}

const ROOT = '[data-nowo-ui-tabs]';
const TRIGGER = '[data-nowo-ui-tabs-trigger]';
const PANEL = '[data-nowo-ui-tabs-panel]';
const LIST = '[data-nowo-ui-tabs-list]';
const OBSERVED = ['data-nowo-ui-tabs-active', 'hidden', 'aria-selected', 'data-active'];

/** Tab chosen per root (click, keys, invalid, API): restored when a morph resets the server-rendered state. */
const chosen = new WeakMap<HTMLElement, string>();

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

function owned(root: HTMLElement, selector: string): HTMLElement[] {
  return Array.from(root.querySelectorAll<HTMLElement>(selector)).filter((el) => el.closest(ROOT) === root);
}

function tabIdOf(el: Element): string {
  return el.getAttribute('data-nowo-ui-tab-id') ?? '';
}

function setAttr(el: Element, name: string, value: string): void {
  if (el.getAttribute(name) !== value) {
    el.setAttribute(name, value);
  }
}

function toggleAttr(el: Element, name: string, on: boolean): void {
  if (el.hasAttribute(name) !== on) {
    el.toggleAttribute(name, on);
  }
}

function tokens(value: string | null): string[] {
  return (value ?? '').split(/\s+/).filter((t) => t !== '');
}

function swapClasses(el: HTMLElement, add: string[], remove: string[]): void {
  remove.filter((t) => !add.includes(t) && el.classList.contains(t)).forEach((t) => el.classList.remove(t));
  add.filter((t) => !el.classList.contains(t)).forEach((t) => el.classList.add(t));
}

/** Progressive root: show the tab list, panels become tab panels. Idempotent. */
function upgrade(root: HTMLElement, triggers: HTMLElement[], panels: HTMLElement[]): void {
  owned(root, LIST).forEach((list) => toggleAttr(list, 'hidden', false));
  panels.forEach((panel) => {
    setAttr(panel, 'role', 'tabpanel');
    const trigger = triggers.find((t) => tabIdOf(t) === tabIdOf(panel));
    if (trigger && trigger.id !== '') {
      setAttr(panel, 'aria-labelledby', trigger.id);
    }
  });
  toggleAttr(root, 'data-nowo-ui-tabs-ready', true);
}

function apply(root: HTMLElement, tabId: string): void {
  const triggers = owned(root, TRIGGER);
  const panels = owned(root, PANEL);
  setAttr(root, 'data-nowo-ui-tabs-active', tabId);
  if (root.hasAttribute('data-nowo-ui-tabs-upgrade')) {
    upgrade(root, triggers, panels);
  }
  const activeClasses = tokens(root.getAttribute('data-nowo-ui-tabs-tab-active-class'));
  const inactiveClasses = tokens(root.getAttribute('data-nowo-ui-tabs-tab-class'));

  triggers.forEach((trigger) => {
    const isActive = tabIdOf(trigger) === tabId;
    toggleAttr(trigger, 'data-active', isActive);
    setAttr(trigger, 'aria-selected', isActive ? 'true' : 'false');
    if (trigger.getAttribute('role') === 'tab') {
      setAttr(trigger, 'tabindex', isActive ? '0' : '-1');
    }
    if (activeClasses.length > 0 || inactiveClasses.length > 0) {
      swapClasses(trigger, isActive ? activeClasses : inactiveClasses, isActive ? inactiveClasses : activeClasses);
    }
  });

  panels.forEach((panel) => {
    const isActive = tabIdOf(panel) === tabId;
    toggleAttr(panel, 'data-active', isActive);
    setAttr(panel, 'data-state', isActive ? 'active' : 'inactive');
    if (panel.classList.contains('hidden') === isActive) {
      panel.classList.toggle('hidden', !isActive);
    }
    toggleAttr(panel, 'hidden', !isActive);
  });
}

function select(root: HTMLElement, tabId: string, reason: TabsChangeReason): void {
  const previous = chosen.get(root) ?? root.getAttribute('data-nowo-ui-tabs-active');
  chosen.set(root, tabId);
  apply(root, tabId);
  if (previous === tabId) {
    return;
  }
  const detail: TabsChangeDetail = {
    tabId,
    previousTabId: previous,
    trigger: owned(root, TRIGGER).find((t) => tabIdOf(t) === tabId) ?? null,
    panel: owned(root, PANEL).find((p) => tabIdOf(p) === tabId) ?? null,
    reason,
  };
  root.dispatchEvent(new CustomEvent<TabsChangeDetail>(TABS_CHANGE_EVENT, { bubbles: true, detail }));
}

export function activateTab(root: HTMLElement | string | null, tabId: string): void {
  const el = resolveRoot(root);
  if (!el || tabId === '') {
    return;
  }
  select(el, tabId, 'api');
}

function hasTab(root: HTMLElement, tabId: string): boolean {
  return [...owned(root, TRIGGER), ...owned(root, PANEL)].some((el) => tabIdOf(el) === tabId);
}

/** Applies the chosen tab (kept across morphs), else the server-rendered one; writes only what differs. */
export function syncRoot(root: HTMLElement): void {
  const remembered = chosen.get(root);
  const active =
    (remembered !== undefined && hasTab(root, remembered) ? remembered : null) ??
    root.getAttribute('data-nowo-ui-tabs-active') ??
    owned(root, TRIGGER).find((t) => t.hasAttribute('data-active'))?.getAttribute('data-nowo-ui-tab-id') ??
    owned(root, TRIGGER)[0]?.getAttribute('data-nowo-ui-tab-id') ??
    '';
  if (active !== '') {
    apply(root, active);
  }
}

function rootOf(trigger: Element): HTMLElement | null {
  const root = trigger.closest(ROOT);
  return root instanceof HTMLElement ? root : null;
}

function onDocumentClick(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) {
    return;
  }
  const trigger = target.closest(TRIGGER);
  if (!(trigger instanceof HTMLElement)) {
    return;
  }
  const root = rootOf(trigger);
  if (root === null) {
    return;
  }
  const tabId = tabIdOf(trigger);
  if (tabId === '') {
    return;
  }
  event.preventDefault();
  select(root, tabId, 'click');
}

/** Right-to-left writing direction of an element (nearest dir attribute, else the computed direction). */
export function isRtl(el: Element): boolean {
  const dir = el.closest('[dir]')?.getAttribute('dir')?.toLowerCase();
  if (dir === 'rtl' || dir === 'ltr') {
    return dir === 'rtl';
  }
  return typeof getComputedStyle === 'function' && getComputedStyle(el).direction === 'rtl';
}

function enabledTabs(root: HTMLElement): HTMLElement[] {
  return owned(root, TRIGGER).filter(
    (t) => t.getAttribute('role') === 'tab' && !t.hasAttribute('disabled') && t.getAttribute('aria-disabled') !== 'true',
  );
}

/**
 * Index of the tab a key moves to (null for other keys): next / previous with wrap-around, Home / End.
 * Horizontal lists mirror the arrows in RTL; vertical lists use ArrowUp / ArrowDown.
 */
export function targetIndex(key: string, current: number, count: number, rtl: boolean, vertical: boolean): number | null {
  const forward = vertical ? 'ArrowDown' : rtl ? 'ArrowLeft' : 'ArrowRight';
  const backward = vertical ? 'ArrowUp' : rtl ? 'ArrowRight' : 'ArrowLeft';
  switch (key) {
    case forward:
      return (current + 1) % count;
    case backward:
      return (current - 1 + count) % count;
    case 'Home':
      return 0;
    case 'End':
      return count - 1;
    default:
      return null;
  }
}

function onDocumentKeydown(event: KeyboardEvent): void {
  const target = event.target;
  if (!(target instanceof Element) || event.altKey || event.ctrlKey || event.metaKey) {
    return;
  }
  const trigger = target.closest(TRIGGER);
  if (!(trigger instanceof HTMLElement) || trigger.getAttribute('role') !== 'tab') {
    return;
  }
  const root = rootOf(trigger);
  if (root === null) {
    return;
  }
  const tabs = enabledTabs(root);
  const vertical = trigger.closest('[role="tablist"]')?.getAttribute('aria-orientation') === 'vertical';
  const index = targetIndex(event.key, Math.max(0, tabs.indexOf(trigger)), tabs.length, isRtl(trigger), vertical);
  if (index === null || tabs.length === 0) {
    return;
  }
  event.preventDefault();
  const next = tabs[index];
  select(root, tabIdOf(next), 'keyboard');
  next.focus();
}

/** Form of the validation pass being handled (one task), undefined between passes. */
let invalidPass: HTMLFormElement | null | undefined;

function formOf(el: Element): HTMLFormElement | null {
  const form = (el as Partial<HTMLInputElement>).form;
  return form instanceof HTMLFormElement ? form : null;
}

/** Opens every hidden panel around the first invalid control of a validation pass (submit fires one per control). */
function onDocumentInvalid(event: Event): void {
  const target = event.target;
  if (!(target instanceof Element) || (invalidPass !== undefined && invalidPass === formOf(target))) {
    return;
  }
  invalidPass = formOf(target);
  setTimeout(() => {
    invalidPass = undefined;
  }, 0);
  const chain: HTMLElement[] = [];
  for (let panel = target.closest<HTMLElement>(PANEL); panel !== null; panel = panel.parentElement?.closest<HTMLElement>(PANEL) ?? null) {
    chain.unshift(panel);
  }
  chain.forEach((panel) => {
    const root = rootOf(panel);
    const tabId = tabIdOf(panel);
    if (root !== null && tabId !== '' && (panel.hidden || !panel.hasAttribute('data-active'))) {
      select(root, tabId, 'invalid');
    }
  });
}

function rootsOf(records: MutationRecord[]): Set<HTMLElement> {
  const roots = new Set<HTMLElement>();
  const add = (el: Element | null): void => {
    const root = el?.closest(ROOT);
    if (root instanceof HTMLElement) {
      roots.add(root);
    }
  };
  records.forEach((record) => {
    if (record.type === 'attributes') {
      add(record.target instanceof Element ? record.target : null);
      return;
    }
    add(record.target instanceof Element ? record.target : null);
    record.addedNodes.forEach((node) => {
      if (node instanceof Element) {
        add(node);
        node.querySelectorAll(ROOT).forEach((el) => add(el));
      }
    });
  });
  return roots;
}

function onMutations(records: MutationRecord[]): void {
  rootsOf(records).forEach((root) => {
    if (root.isConnected) {
      syncRoot(root);
    }
  });
}

let tabsBound = false;

export function bindNowoUiTabs(): void {
  if (!tabsBound) {
    tabsBound = true;
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onDocumentKeydown);
    document.addEventListener('invalid', onDocumentInvalid, true);
    window.nowoUiActivateTab = activateTab;
    if (typeof MutationObserver === 'function') {
      new MutationObserver(onMutations).observe(document.documentElement, {
        subtree: true,
        childList: true,
        attributes: true,
        attributeFilter: OBSERVED,
      });
    }
  }
  document.querySelectorAll<HTMLElement>(ROOT).forEach(syncRoot);
}

bindNowoUiTabs();
