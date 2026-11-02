/**
 * Optional Stimulus peer (2.23) — fields that only apply to some values of another field (provider-dependent
 * settings: SMTP host for `smtp`, API key for `ses`…). Distinct from a disabling "field visibility": this peer only
 * HIDES rows, it never disables an input and never clears a value, so hidden fields are still submitted and stored
 * values survive a provider switch (a secret field keeps its "blank = keep" semantics).
 *
 * Markup: `data-controller="visible-when"` on the form (or any container); on each dependent row
 * `data-nowo-ui-visible-when-field="<name of the controlling field>"` and
 * `data-nowo-ui-visible-when-in='["smtp","sendmail"]'` (JSON list; a comma-separated list is accepted too). PHP
 * builds both with `Nowo\UiKitBundle\Form\VisibleWhen::attributes()` (e.g. as a form row's `row_attr`), Twig with
 * `nowo_ui_visible_when()`. The controlling field is looked up by its `name` (`settings[provider]`) in the container's
 * form, else by id; its value is that of the select / text input, the checked radio, a checked checkbox's value ('' when
 * unchecked), or any selected option of a multiple select.
 *
 * A non-matching row gets `hidden`, `inert` and `aria-hidden="true"`; a matching row loses them. The rows re-sync on
 * connect and on every `change` / `input` inside the container. Without JavaScript every row stays visible.
 *
 * Identifier: `visible-when`. Event: `visible-when:synced` on the container (detail `{hidden}`: rows hidden now).
 */

import { Controller } from '@hotwired/stimulus';

export const ROW_SELECTOR = '[data-nowo-ui-visible-when-field]';

/** Values a row is visible for (JSON list or comma-separated; empty list = never). */
export function parseValues(raw: string | null): string[] {
  const text = (raw ?? '').trim();
  if (text.startsWith('[')) {
    try {
      const parsed: unknown = JSON.parse(text);
      if (Array.isArray(parsed)) {
        return parsed.map((value) => String(value));
      }
    } catch {
      return [];
    }
  }

  return text === '' ? [] : text.split(',').map((value) => value.trim());
}

/** Current values of the controlling field (several for a multiple select / checkboxes of the same name). */
export function fieldValues(scope: Element, name: string): string[] | null {
  const form = scope instanceof HTMLFormElement ? scope : scope.querySelector('form') ?? scope.closest('form');
  const named = form ? Array.from(form.elements).filter((el) => (el as HTMLInputElement).name === name) : [];
  const fields = named.length > 0 ? named : Array.from(scope.querySelectorAll(`[name="${CSS.escape(name)}"]`));
  if (fields.length === 0) {
    // scoped to the form / container (audit CS I3), never the whole document
    const byId = (form ?? scope).querySelector(`#${CSS.escape(name)}`);
    if (byId) {
      fields.push(byId);
    }
  }
  if (fields.length === 0) {
    return null;
  }
  const values: string[] = [];
  fields.forEach((field) => {
    if (field instanceof HTMLSelectElement && field.multiple) {
      Array.from(field.options)
        .filter((option) => option.selected)
        .forEach((option) => values.push(option.value));
    } else if (field instanceof HTMLSelectElement) {
      values.push(field.value);
    } else if (field instanceof HTMLInputElement && (field.type === 'radio' || field.type === 'checkbox')) {
      if (field.checked) {
        values.push(field.value);
      }
    } else if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement) {
      values.push(field.value);
    }
  });
  if (values.length === 0) {
    values.push('');
  }

  return values;
}

const REQUIRED_MARK = 'data-nowo-ui-visible-when-required';

/**
 * Shows or hides one row. Never disables an input nor touches a value; a hidden `required` field would block the
 * submit silently, so `required` is lifted while the row is hidden (marked `data-nowo-ui-visible-when-required`) and
 * restored when it shows again (audit CS I3).
 */
export function setRowVisible(row: HTMLElement, visible: boolean): void {
  if (visible) {
    row.querySelectorAll(`[${REQUIRED_MARK}]`).forEach((field) => {
      field.removeAttribute(REQUIRED_MARK);
      field.setAttribute('required', '');
    });
  } else {
    row.querySelectorAll('[required]').forEach((field) => {
      field.removeAttribute('required');
      field.setAttribute(REQUIRED_MARK, '');
    });
  }
  row.hidden = !visible;
  row.toggleAttribute('inert', !visible);
  if (visible) {
    row.removeAttribute('aria-hidden');
  } else {
    row.setAttribute('aria-hidden', 'true');
  }
}

export class VisibleWhenController extends Controller {
  private readonly onChange = (): void => {
    this.sync();
  };

  override connect(): void {
    this.element.addEventListener('change', this.onChange);
    this.element.addEventListener('input', this.onChange);
    this.sync();
  }

  override disconnect(): void {
    this.element.removeEventListener('change', this.onChange);
    this.element.removeEventListener('input', this.onChange);
  }

  /** Re-applies every row of the container; returns the rows hidden now. */
  sync(): HTMLElement[] {
    const hidden: HTMLElement[] = [];
    this.element.querySelectorAll<HTMLElement>(ROW_SELECTOR).forEach((row) => {
      const values = fieldValues(this.element, row.getAttribute('data-nowo-ui-visible-when-field') ?? '');
      if (values === null) {
        // unknown controlling field: leave the row as rendered (visible)
        setRowVisible(row, true);
        return;
      }
      const allowed = parseValues(row.getAttribute('data-nowo-ui-visible-when-in'));
      const visible = values.some((value) => allowed.includes(value));
      setRowVisible(row, visible);
      if (!visible) {
        hidden.push(row);
      }
    });
    this.dispatch('synced', { detail: { hidden } });

    return hidden;
  }
}

export default VisibleWhenController;
