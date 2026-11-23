/**
 * 2.68 — the shared confirm dialog of a table (`table` partial with confirm_shared). The markup is
 * tests/Fixtures/table-confirm/shared-two-rows.html, rendered by the Twig partials and checked by
 * TableSharedConfirm268Test: row 1 a danger confirm with a body message, row 2 a primary, large, type-to-confirm
 * confirm with its message in the header and its own Cancel label.
 */
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import * as mod from './nowo-ui-confirm';
import FIXTURE from '../../../../tests/Fixtures/table-confirm/shared-two-rows.html?raw';

function polyfill(dialog: HTMLDialogElement): void {
  dialog.showModal = function showModal() {
    this.setAttribute('open', '');
  };
  dialog.close = function close() {
    this.removeAttribute('open');
    this.dispatchEvent(new Event('close'));
  };
  Object.defineProperty(dialog, 'open', {
    configurable: true,
    get() {
      return this.hasAttribute('open');
    },
  });
}

function click(el: Element): MouseEvent {
  const event = new MouseEvent('click', { bubbles: true, cancelable: true });
  el.dispatchEvent(event);
  return event;
}

describe('shared table confirm (2.68)', () => {
  let dialog: HTMLDialogElement;
  let toggles: HTMLButtonElement[];
  const q = <T extends Element = HTMLElement>(sel: string): T => dialog.querySelector(sel) as T;
  const visibleSubmit = (): HTMLButtonElement => q<HTMLButtonElement>('button[data-nowo-confirm-variant]:not([hidden])');

  beforeEach(() => {
    document.body.innerHTML = FIXTURE;
    dialog = document.getElementById('nowo-ui-table-confirm') as HTMLDialogElement;
    polyfill(dialog);
    toggles = Array.from(document.querySelectorAll<HTMLButtonElement>('button[data-nowo-confirm-target="nowo-ui-table-confirm"]'));
    mod.bindNowoUiConfirm();
  });

  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('renders one dialog for both rows, empty until opened', () => {
    expect(document.querySelectorAll('dialog')).toHaveLength(1);
    expect(toggles).toHaveLength(2);
    expect(q('[data-nowo-confirm-slot="title"]').textContent).toBe('');
    expect(dialog.querySelector('form')).toBeNull();
  });

  it('fills the dialog from the row toggle and binds Confirm to the row form', () => {
    const event = click(toggles[0]);
    expect(event.defaultPrevented).toBe(true);
    expect(dialog.open).toBe(true);
    expect(q('[data-nowo-confirm-slot="title"]').textContent).toBe('Delete A?');
    expect(q('[data-nowo-confirm-slot="message"]').textContent).toBe('This cannot be undone.');
    expect(q('[data-nowo-confirm-slot="body"]').hidden).toBe(false);
    expect(q('[data-nowo-confirm-slot="header-message"]').hidden).toBe(true);
    expect(q('[data-nowo-confirm-slot="expect"]').hidden).toBe(true);

    const submit = visibleSubmit();
    expect(submit.getAttribute('data-nowo-confirm-variant')).toBe('danger');
    expect(submit.textContent).toBe('Delete');
    expect(submit.disabled).toBe(false);
    const formId = toggles[0].getAttribute('data-nowo-confirm-form')!;
    expect(submit.getAttribute('form')).toBe(formId);
    const form = document.getElementById(formId) as HTMLFormElement;
    expect(form.getAttribute('action')).toBe('/items/1/delete');
    expect(form.querySelector<HTMLInputElement>('input[type="hidden"]')?.value).toBe('tok-1');
    expect(dialog.querySelectorAll('button[data-nowo-confirm-variant]:not([hidden])')).toHaveLength(1);
    // danger action, no type-to-confirm: first focus on Cancel (2.61), labelled by the filled title
    expect(document.activeElement).toBe(q('[data-nowo-confirm-slot="cancel"]'));
    expect(dialog.getAttribute('aria-labelledby')).toBe(q('[data-nowo-confirm-slot="title"]').id);
  });

  it('refills for another row: header message, size, own labels and type-to-confirm', () => {
    click(toggles[0]);
    mod.closeConfirm(dialog);
    click(toggles[1]);
    expect(q('[data-nowo-confirm-slot="title"]').textContent).toBe('Archive B?');
    expect(q('[data-nowo-confirm-slot="header-message"]').textContent).toBe('Gone.');
    expect(q('[data-nowo-confirm-slot="header-message"]').hidden).toBe(false);
    expect(q('[data-nowo-confirm-slot="body"]').hidden).toBe(true);
    expect(dialog.classList.contains('nowo-ui-confirm--lg')).toBe(true);
    expect(q('[data-nowo-confirm-slot="cancel"]').textContent).toBe('Keep');

    const submit = visibleSubmit();
    expect(submit.getAttribute('data-nowo-confirm-variant')).toBe('primary');
    expect(submit.textContent).toBe('Archive');
    expect(submit.getAttribute('form')).toBe(toggles[1].getAttribute('data-nowo-confirm-form'));
    expect(q('button[data-nowo-confirm-variant="danger"]').hasAttribute('form')).toBe(false);

    const input = q<HTMLInputElement>('[data-nowo-confirm-slot="expect-input"]');
    expect(q('[data-nowo-confirm-slot="expect"]').hidden).toBe(false);
    expect(q('[data-nowo-confirm-slot="expect-label"]').textContent).toBe('Type B to confirm');
    expect(input.required).toBe(true);
    expect(input.getAttribute('form')).toBe(submit.getAttribute('form'));
    expect(document.activeElement).toBe(input);
    expect(submit.disabled).toBe(true);
    input.value = 'B';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    expect(submit.disabled).toBe(false);

    // back to row 1: defaults restored, type-to-confirm gone
    mod.closeConfirm(dialog);
    click(toggles[0]);
    expect(dialog.classList.contains('nowo-ui-confirm--lg')).toBe(false);
    expect(q('[data-nowo-confirm-slot="cancel"]').textContent).toBe('Cancel');
    expect(input.required).toBe(false);
    expect(input.hasAttribute('data-nowo-confirm-expected')).toBe(false);
    expect(visibleSubmit().disabled).toBe(false);
  });

  it('returns focus to the row toggle on close', () => {
    toggles[1].focus();
    click(toggles[1]);
    mod.closeConfirm(dialog);
    dialog.dispatchEvent(new Event('close'));
    expect(document.activeElement).toBe(toggles[1]);
  });

  it('binds Confirm to the row form whose own submit is disabled', () => {
    click(toggles[0]);
    const submit = visibleSubmit();
    const form = document.getElementById(toggles[0].getAttribute('data-nowo-confirm-form')!) as HTMLFormElement;
    expect(submit.form).toBe(form);
    const own = form.querySelector<HTMLButtonElement>('button[type="submit"]')!;
    expect(own.disabled).toBe(true);
    expect(own.tabIndex).toBe(-1);
  });

  it('drops the binding on close and refuses to open from script without a row toggle', () => {
    click(toggles[0]);
    expect(visibleSubmit().hasAttribute('form')).toBe(true);
    mod.closeConfirm(dialog);
    expect(dialog.open).toBe(false);
    expect(dialog.querySelectorAll('[form]')).toHaveLength(0);
    window.nowoUiOpenConfirm?.('nowo-ui-table-confirm');
    expect(dialog.open).toBe(false);
    mod.openConfirm('nowo-ui-table-confirm', document.body);
    expect(dialog.open).toBe(false);
    mod.openConfirm('nowo-ui-table-confirm', toggles[1]);
    expect(dialog.open).toBe(true);
    expect(visibleSubmit().getAttribute('form')).toBe(toggles[1].getAttribute('data-nowo-confirm-form'));
  });

  it('does not open when the toggle names no form on the page', () => {
    const formId = toggles[0].getAttribute('data-nowo-confirm-form')!;
    document.getElementById(formId)?.remove();
    click(toggles[0]);
    expect(dialog.open).toBe(false);
    expect(mod.fillSharedConfirm(dialog, document.createElement('button'))).toBe(false);
  });
});
