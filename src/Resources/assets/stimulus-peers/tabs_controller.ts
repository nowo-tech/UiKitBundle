/**
 * Optional Stimulus peer — in-page tab panels.
 *
 * Prefer kit IIFE `nowo-ui-tabs.js` with `data-nowo-ui-tabs*` when no Stimulus.
 * Twig `_tabs.html.twig` remains link-based navigation (no JS).
 *
 * Identifier: `tabs`
 * Tab id attrs: `data-nowo-ui-tab-id` (preferred) or legacy `data-tab-id`
 */

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['trigger', 'tab'];
  static values = { activeTab: String };

  declare readonly triggerTargets: HTMLElement[];
  declare readonly tabTargets: HTMLElement[];
  declare readonly activeTabValue: string;

  open(e: Event): void {
    const currentTarget = e.currentTarget as HTMLElement | null;
    if (currentTarget === null) {
      return;
    }
    this.activeTabValue =
      currentTarget.getAttribute('data-nowo-ui-tab-id') ?? currentTarget.dataset.tabId ?? '';
  }

  activeTabValueChanged(): void {
    if (typeof window.nowoUiActivateTab === 'function' && this.element instanceof HTMLElement) {
      if (this.element.hasAttribute('data-nowo-ui-tabs')) {
        window.nowoUiActivateTab(this.element, this.activeTabValue);
        return;
      }
    }

    this.triggerTargets.forEach((trigger) => {
      const id = trigger.getAttribute('data-nowo-ui-tab-id') ?? trigger.dataset.tabId ?? '';
      const isActive = id === this.activeTabValue;
      trigger.toggleAttribute('data-active', isActive);
      trigger.ariaSelected = isActive ? 'true' : 'false';
    });

    this.tabTargets.forEach((tab) => {
      const id = tab.getAttribute('data-nowo-ui-tab-id') ?? tab.dataset.tabId ?? '';
      const isActive = id === this.activeTabValue;
      tab.toggleAttribute('data-active', isActive);
      tab.dataset.state = isActive ? 'active' : 'inactive';
      tab.classList.toggle('hidden', !isActive);
      tab.hidden = !isActive;
    });
  }
}

declare global {
  interface Window {
    nowoUiActivateTab?: (root: HTMLElement | string | null, tabId: string) => void;
  }
}
