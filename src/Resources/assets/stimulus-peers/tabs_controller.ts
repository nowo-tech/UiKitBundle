/**
 * Optional Stimulus peer — in-page tab panels.
 *
 * Prefer kit IIFE `nowo-ui-tabs.js` with `data-nowo-ui-tabs*` when no Stimulus.
 * Twig `_tabs.html.twig` remains link-based navigation (no JS).
 *
 * Identifier: `tabs`
 * Targets: `trigger` (tab buttons), `tab` (panels), `list` (2.5, the tab list of a progressive root)
 * Value: `activeTab` (tab id)
 * Tab id attrs: `data-nowo-ui-tab-id` (preferred) or legacy `data-tab-id`
 *
 * When the element is also a kit root (`data-nowo-ui-tabs`) and `nowo-ui-tabs.js` is loaded, the peer delegates to
 * `window.nowoUiActivateTab` (the runtime then handles keys, `invalid`, the change event and morphs). Standalone, the
 * peer does the same itself (2.5): role="tab" triggers get a roving tabindex and ArrowLeft / ArrowRight (mirrored in
 * RTL) / Home / End; an `invalid` control in a hidden panel opens it; `nowo-ui:tabs:change` (bubbling, detail
 * {tabId, previousTabId, trigger, panel, reason}) after each change; a progressive root (`data-nowo-ui-tabs-upgrade`)
 * shows its `list` target and gets `data-nowo-ui-tabs-ready`; the state is re-applied when a morph resets it.
 * Nested `tabs` controllers only see their own targets (Stimulus scoping).
 * 2.5.2: the legends of its panels (`legend.nowo-ui-locale-tabs__legend`, [data-nowo-ui-tabs-legend]) are made visually
 * hidden by the peer itself (class `visually-hidden`, data-nowo-ui-visually-hidden, CSSOM properties — strict-CSP
 * safe), so hosts without nowo-ui.css do not show them; screen readers still read them.
 */

import { Controller } from '@hotwired/stimulus';

const CHANGE_EVENT = 'nowo-ui:tabs:change';
const LEGEND = 'legend.nowo-ui-locale-tabs__legend, [data-nowo-ui-tabs-legend]';
const VISUALLY_HIDDEN: Readonly<Record<string, string>> = {
  position: 'absolute',
  width: '1px',
  height: '1px',
  margin: '-1px',
  padding: '0px',
  overflow: 'hidden',
  clip: 'rect(0px, 0px, 0px, 0px)',
  'white-space': 'nowrap',
  border: '0px',
};

/** Visually hidden, still read by screen readers; CSSOM writes (no inline style markup), only what differs. */
function hideVisually(el: HTMLElement): void {
  if (!el.classList.contains('visually-hidden')) {
    el.classList.add('visually-hidden');
  }
  toggleAttr(el, 'data-nowo-ui-visually-hidden', true);
  Object.entries(VISUALLY_HIDDEN).forEach(([name, value]) => {
    if (el.style.getPropertyValue(name) !== value || el.style.getPropertyPriority(name) !== 'important') {
      el.style.setProperty(name, value, 'important');
    }
  });
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

function rtl(el: Element): boolean {
  const dir = el.closest('[dir]')?.getAttribute('dir')?.toLowerCase();
  if (dir === 'rtl' || dir === 'ltr') {
    return dir === 'rtl';
  }
  return getComputedStyle(el).direction === 'rtl';
}

export default class extends Controller {
  static override targets = ['trigger', 'tab', 'list'];
  static override values = { activeTab: String };

  declare readonly triggerTargets: HTMLElement[];
  declare readonly tabTargets: HTMLElement[];
  declare readonly listTargets: HTMLElement[];
  declare activeTabValue: string;

  private shown: string | null = null;
  private reason: 'click' | 'keyboard' | 'invalid' | 'api' = 'api';
  private observer: MutationObserver | null = null;
  private invalidPass: HTMLFormElement | null | undefined;
  private readonly onKeydown = (event: KeyboardEvent): void => this.key(event);
  private readonly onInvalid = (event: Event): void => this.invalid(event);

  override connect(): void {
    if (this.delegated()) {
      return;
    }
    this.element.addEventListener('keydown', this.onKeydown as EventListener);
    this.element.addEventListener('invalid', this.onInvalid, true);
    if (typeof MutationObserver === 'function') {
      this.observer = new MutationObserver(() => {
        if (!this.delegated()) {
          this.render();
        }
      });
      this.observer.observe(this.element, { subtree: true, attributes: true, attributeFilter: ['hidden', 'aria-selected', 'data-active'] });
    }
    this.render();
  }

  override disconnect(): void {
    this.element.removeEventListener('keydown', this.onKeydown as EventListener);
    this.element.removeEventListener('invalid', this.onInvalid, true);
    this.observer?.disconnect();
    this.observer = null;
  }

  open(e: Event): void {
    const currentTarget = e.currentTarget as HTMLElement | null;
    if (currentTarget === null) {
      return;
    }
    this.reason = 'click';
    this.activeTabValue = this.idOf(currentTarget);
  }

  activeTabValueChanged(): void {
    if (this.delegated()) {
      window.nowoUiActivateTab?.(this.element as HTMLElement, this.activeTabValue);
      return;
    }
    this.render();
  }

  private delegated(): boolean {
    return typeof window.nowoUiActivateTab === 'function' && this.element instanceof HTMLElement && this.element.hasAttribute('data-nowo-ui-tabs');
  }

  private idOf(el: Element): string {
    return el.getAttribute('data-nowo-ui-tab-id') ?? (el as HTMLElement).dataset.tabId ?? '';
  }

  /** Applies activeTabValue to the targets (only the attributes that differ, so the observer settles). */
  private render(): void {
    const tabId = this.activeTabValue;
    if (this.element.hasAttribute('data-nowo-ui-tabs-upgrade')) {
      this.listTargets.forEach((list) => toggleAttr(list, 'hidden', false));
      this.tabTargets.forEach((panel) => {
        setAttr(panel, 'role', 'tabpanel');
        const trigger = this.triggerTargets.find((t) => this.idOf(t) === this.idOf(panel));
        if (trigger && trigger.id !== '') {
          setAttr(panel, 'aria-labelledby', trigger.id);
        }
      });
      toggleAttr(this.element, 'data-nowo-ui-tabs-ready', true);
    }
    this.tabTargets.forEach((panel) => {
      panel.querySelectorAll<HTMLElement>(LEGEND).forEach((legend) => {
        // Only this panel's own legends (a nested tab set's panels hide theirs).
        if (legend.closest('[data-nowo-ui-tabs-panel], [data-nowo-ui-tab-id], [data-tab-id]') === panel) {
          hideVisually(legend);
        }
      });
    });

    this.triggerTargets.forEach((trigger) => {
      const isActive = this.idOf(trigger) === tabId;
      toggleAttr(trigger, 'data-active', isActive);
      setAttr(trigger, 'aria-selected', isActive ? 'true' : 'false');
      if (trigger.getAttribute('role') === 'tab') {
        setAttr(trigger, 'tabindex', isActive ? '0' : '-1');
      }
    });

    this.tabTargets.forEach((tab) => {
      const isActive = this.idOf(tab) === tabId;
      toggleAttr(tab, 'data-active', isActive);
      setAttr(tab, 'data-state', isActive ? 'active' : 'inactive');
      if (tab.classList.contains('hidden') === isActive) {
        tab.classList.toggle('hidden', !isActive);
      }
      toggleAttr(tab, 'hidden', !isActive);
    });

    const previous = this.shown;
    this.shown = tabId;
    const reason = this.reason;
    this.reason = 'api';
    if (previous !== null && previous !== tabId) {
      this.element.dispatchEvent(new CustomEvent(CHANGE_EVENT, {
        bubbles: true,
        detail: {
          tabId,
          previousTabId: previous,
          trigger: this.triggerTargets.find((t) => this.idOf(t) === tabId) ?? null,
          panel: this.tabTargets.find((t) => this.idOf(t) === tabId) ?? null,
          reason,
        },
      }));
    }
  }

  private key(event: KeyboardEvent): void {
    const trigger = event.target instanceof Element ? event.target.closest<HTMLElement>('[role="tab"]') : null;
    if (trigger === null || this.delegated() || !this.triggerTargets.includes(trigger) || event.altKey || event.ctrlKey || event.metaKey) {
      return;
    }
    const tabs = this.triggerTargets.filter((t) => t.getAttribute('role') === 'tab' && !t.hasAttribute('disabled') && t.getAttribute('aria-disabled') !== 'true');
    const current = Math.max(0, tabs.indexOf(trigger));
    const isRtl = rtl(trigger);
    const keys: Record<string, number> = {
      [isRtl ? 'ArrowLeft' : 'ArrowRight']: (current + 1) % tabs.length,
      [isRtl ? 'ArrowRight' : 'ArrowLeft']: (current - 1 + tabs.length) % tabs.length,
      Home: 0,
      End: tabs.length - 1,
    };
    if (!(event.key in keys)) {
      return;
    }
    event.preventDefault();
    const next = tabs[keys[event.key]!]!;
    this.reason = 'keyboard';
    this.activeTabValue = this.idOf(next);
    next.focus();
  }

  private invalid(event: Event): void {
    if (this.delegated() || !(event.target instanceof Element)) {
      return;
    }
    const field = (event.target as Partial<HTMLInputElement>).form;
    const form = field instanceof HTMLFormElement ? field : null;
    // One validation pass (a submit fires `invalid` per control): only its first control opens a panel.
    if (this.invalidPass !== undefined && this.invalidPass === form) {
      return;
    }
    this.invalidPass = form;
    setTimeout(() => {
      this.invalidPass = undefined;
    }, 0);
    const panel = this.tabTargets.find((tab) => tab.contains(event.target as Node));
    if (panel !== undefined && this.idOf(panel) !== this.activeTabValue) {
      this.reason = 'invalid';
      this.activeTabValue = this.idOf(panel);
      // Stimulus applies value changes asynchronously; the browser focuses the control right after this event.
      this.render();
    }
  }
}

declare global {
  interface Window {
    nowoUiActivateTab?: (root: HTMLElement | string | null, tabId: string) => void;
  }
}
