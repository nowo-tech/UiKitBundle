/**
 * Optional Stimulus peer (1.15) — collapsible panel whose open state survives navigation.
 *
 * The state of every panel is kept in one localStorage JSON map (`{ [id]: open }`) under `storageKey`,
 * so the same panel id shares its state across pages. Without a saved state the server default applies
 * (`collapsed` value), so hosts render per-user defaults without any global JS.
 *
 * Identifier: `collapse-panel`
 * Values: `id` (panel id, required for persistence), `storageKey` (default `nowo-ui.collapse-panels`),
 *         `collapsed` (server default when nothing is saved, default false),
 *         `openIcon` / `closedIcon` (icon target text, default ▾ / ▸).
 * Targets: `body` (hidden when collapsed), `button` (gets `aria-expanded`), `icon` (optional).
 * Actions: `collapse-panel#toggle`.
 * Root class: `is-collapsed`. Event: `collapse-panel:toggled` (`detail.open`, `detail.id`).
 */

import { Controller } from '@hotwired/stimulus';

type PanelStateMap = Record<string, boolean>;

export const COLLAPSE_PANEL_STORAGE_DEFAULT = 'nowo-ui.collapse-panels';

export default class extends Controller<HTMLElement> {
  static override targets = ['body', 'button', 'icon'];

  static override values = {
    id: { type: String, default: '' },
    storageKey: { type: String, default: COLLAPSE_PANEL_STORAGE_DEFAULT },
    collapsed: { type: Boolean, default: false },
    openIcon: { type: String, default: '▾' },
    closedIcon: { type: String, default: '▸' },
  };

  declare readonly bodyTarget: HTMLElement;
  declare readonly hasButtonTarget: boolean;
  declare readonly buttonTarget: HTMLElement;
  declare readonly hasIconTarget: boolean;
  declare readonly iconTarget: HTMLElement;
  declare readonly idValue: string;
  declare readonly storageKeyValue: string;
  declare readonly collapsedValue: boolean;
  declare readonly openIconValue: string;
  declare readonly closedIconValue: string;

  override connect(): void {
    this.apply(this.resolveOpen(), false);
  }

  toggle(event?: Event): void {
    event?.preventDefault();
    this.apply(!this.isOpen(), true);
  }

  private resolveOpen(): boolean {
    const saved = this.idValue === '' ? undefined : this.readState()[this.idValue];
    if (typeof saved === 'boolean') {
      return saved;
    }

    return !this.collapsedValue;
  }

  private isOpen(): boolean {
    return !this.element.classList.contains('is-collapsed');
  }

  private apply(open: boolean, persist: boolean): void {
    this.element.classList.toggle('is-collapsed', !open);
    if (this.hasButtonTarget) {
      this.buttonTarget.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    this.bodyTarget.hidden = !open;
    if (this.hasIconTarget) {
      this.iconTarget.textContent = open ? this.openIconValue : this.closedIconValue;
    }

    if (persist && this.idValue !== '') {
      const state = this.readState();
      state[this.idValue] = open;
      this.writeState(state);
    }
    if (persist) {
      this.dispatch('toggled', { detail: { open, id: this.idValue } });
    }
  }

  private readState(): PanelStateMap {
    try {
      const raw = window.localStorage.getItem(this.storageKeyValue);
      if (!raw) {
        return {};
      }
      const parsed = JSON.parse(raw) as unknown;
      if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
        return {};
      }

      return parsed as PanelStateMap;
    } catch {
      return {};
    }
  }

  private writeState(state: PanelStateMap): void {
    try {
      window.localStorage.setItem(this.storageKeyValue, JSON.stringify(state));
    } catch {
      // Ignore quota / private mode failures.
    }
  }
}
