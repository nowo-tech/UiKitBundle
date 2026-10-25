/**
 * Optional Stimulus peer (2.14) — progressive multi-select: enhances a native `<select multiple>` (a Symfony
 * `ChoiceType` with `multiple: true`, e.g. a listing filter) with Tom Select: chips with a remove button, search
 * inside the control, keyboard navigation (arrows, Enter, Backspace / Delete on the chips, Escape). Without
 * JavaScript, or when Tom Select cannot be loaded, the native multi-select stays and submits the same values
 * (`name[]=…` in a GET filter form).
 *
 * Markup: any `<select>` with `data-controller="multi-select"` — the ChoiceType option `nowo_ui_multi_select`
 * (`Nowo\UiKitBundle\Form\MultiSelectTypeExtension`) writes it, with the translated labels. The select's
 * `placeholder` / `data-placeholder` attribute is the text of the empty control; its label (`<label for>`) or
 * `aria-label` names the combobox. Skin: `css/nowo-ui-multi-select.css` (standalone; Tom Select's own stylesheet is
 * not needed).
 *
 * Tom Select (`tom-select` ^2.3, host dependency; checked against 2.6) is imported on demand, the first time a
 * select connects. The wrapper gets `nowo-ui-multi-select` only (the select's own classes, e.g. `nowo-ui-input` /
 * `form-select`, stay on the hidden select so the wrapper is not painted twice); `--invalid` when the select has
 * `aria-invalid="true"`. The dropdown is attached to <body> (positioned under the control, so a card with
 * `overflow: hidden` does not clip it) unless the select sits in a `<dialog>` / `[popover]` (top layer): then it
 * stays inline.
 *
 * Identifier: `multi-select`
 * Values: `labels` (`{remove, noResults}`), `options` (Tom Select settings merged over the defaults, e.g.
 *         `{maxItems: 3}`), `dropdownParent` (`auto` default | `body` | `inline`).
 * Events: `multi-select:ready` (`detail.instance`), `multi-select:unavailable` (`detail.error`: Tom Select could not
 *         be loaded; the native select stays).
 */

import { Controller } from '@hotwired/stimulus';

/** The part of a Tom Select instance the peer uses. */
export type TomSelectInstance = {
  wrapper: HTMLElement;
  /** Values of the chosen items. */
  items: string[];
  destroy: () => void;
};

/** Tom Select's constructor (the default export of `tom-select`). */
export type TomSelectConstructor = new (element: HTMLSelectElement, settings: Record<string, unknown>) => TomSelectInstance;

type Labels = {
  remove?: string;
  noResults?: string;
};

/** Tom Select instance per enhanced select (kept on the element, not the controller: a re-created controller finds it). */
const instances = new WeakMap<HTMLSelectElement, TomSelectInstance>();

const escapeHtml = (text: string): string =>
  text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');

export default class MultiSelectController extends Controller<HTMLElement> {
  static override values = {
    labels: { type: Object, default: {} },
    options: { type: Object, default: {} },
    dropdownParent: { type: String, default: 'auto' },
  };

  declare readonly labelsValue: Labels;
  declare readonly optionsValue: Record<string, unknown>;
  declare readonly dropdownParentValue: string;

  private loading: Promise<void> | null = null;

  override connect(): void {
    if (!(this.element instanceof HTMLSelectElement)) {
      return;
    }
    this.loading ??= this.enhance(this.element).finally(() => {
      this.loading = null;
    });
  }

  override disconnect(): void {
    const select = this.element as HTMLSelectElement;
    const instance = instances.get(select);
    if (!instance) {
      return;
    }
    instances.delete(select);
    // Tom Select's destroy() restores the options' initial markup: keep the viewer's choice on the native select.
    const chosen = new Set(instance.items);
    instance.destroy();
    for (const option of select.options) {
      option.selected = chosen.has(option.value);
    }
  }

  /** The Tom Select instance once enhanced (null while loading, without Tom Select, or after a disconnect). */
  get tomSelect(): TomSelectInstance | null {
    return this.element instanceof HTMLSelectElement ? (instances.get(this.element) ?? null) : null;
  }

  /** Tom Select settings: the kit defaults, then the `options` value. */
  settings(select: HTMLSelectElement): Record<string, unknown> {
    const labels = this.labelsValue ?? {};
    const noResults = escapeHtml(labels.noResults ?? 'No results');

    return {
      plugins: { remove_button: { title: labels.remove ?? 'Remove', label: '×' } },
      create: false,
      persist: false,
      closeAfterSelect: !select.multiple,
      hideSelected: select.multiple,
      maxOptions: null,
      maxItems: select.multiple ? null : 1,
      wrapperClass: 'ts-wrapper nowo-ui-multi-select',
      controlClass: 'ts-control nowo-ui-multi-select__control',
      dropdownClass: 'ts-dropdown nowo-ui-multi-select__dropdown',
      dropdownParent: this.dropdownParent(select),
      render: {
        no_results: (): string => `<div class="no-results">${noResults}</div>`,
      },
      ...(this.optionsValue ?? {}),
    };
  }

  /** Loads Tom Select (dynamic import, so pages without a multi-select never fetch it). */
  protected async tomSelectFactory(): Promise<TomSelectConstructor> {
    const module = (await import('tom-select')) as unknown as { default: TomSelectConstructor };

    return module.default;
  }

  private dropdownParent(select: HTMLSelectElement): string | null {
    const mode = this.dropdownParentValue;
    if ('body' === mode) {
      return 'body';
    }
    if ('inline' === mode) {
      return null;
    }

    return select.closest('dialog, [popover]') ? null : 'body';
  }

  private async enhance(select: HTMLSelectElement): Promise<void> {
    let TomSelect: TomSelectConstructor;
    try {
      TomSelect = await this.tomSelectFactory();
    } catch (error) {
      this.dispatch('unavailable', { detail: { error } });

      return;
    }
    if (!select.isConnected || instances.has(select) || 'tomselect' in select) {
      return;
    }
    let instance: TomSelectInstance;
    try {
      instance = new TomSelect(select, this.settings(select));
    } catch (error) {
      this.dispatch('unavailable', { detail: { error } });

      return;
    }
    instances.set(select, instance);
    // Tom Select copies the select's classes onto its wrapper: they stay on the (hidden) select only.
    const own = new Set(['ts-wrapper', 'nowo-ui-multi-select']);
    for (const name of [...select.classList]) {
      if (!own.has(name)) {
        instance.wrapper.classList.remove(name);
      }
    }
    if ('true' === select.getAttribute('aria-invalid')) {
      instance.wrapper.classList.add('nowo-ui-multi-select--invalid');
    }
    this.dispatch('ready', { detail: { instance } });
  }
}
