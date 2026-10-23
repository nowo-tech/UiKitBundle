import { afterEach, describe, expect, it } from 'vitest';

import { askConfirm, pageConfirmLabels } from './ask_confirm';

const labels = { confirm: 'Reset', cancel: 'Cancel' };
const dialog = (): HTMLDialogElement => document.querySelector('dialog.nowo-ui-confirm')!;
const button = (text: string): HTMLButtonElement =>
  Array.from(document.querySelectorAll<HTMLButtonElement>('dialog.nowo-ui-confirm button')).find((b) => b.textContent === text)!;

describe('askConfirm', () => {
  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('resolves true on confirm and removes the dialog', async () => {
    const answer = askConfirm('Reset this layout?', labels);
    expect(dialog().getAttribute('aria-labelledby')).toBe(dialog().querySelector('h2')!.id);
    expect(dialog().querySelector('h2')!.textContent).toBe('Reset this layout?');
    // A stray Enter must not run the action (audit A11Y-03).
    expect(document.activeElement).toBe(button('Cancel'));
    expect(button('Reset').className).toBe('nowo-ui-btn nowo-ui-btn--primary');
    button('Reset').click();
    await expect(answer).resolves.toBe(true);
    expect(document.querySelector('dialog')).toBeNull();
  });

  it('paints a destructive confirmation and reads the page labels', async () => {
    const answer = askConfirm('Delete?', { ...labels, danger: true });
    expect(button('Reset').className).toBe('nowo-ui-btn nowo-ui-btn--danger');
    button('Cancel').click();
    await expect(answer).resolves.toBe(false);

    expect(pageConfirmLabels()).toEqual({ confirm: 'Confirm', cancel: 'Cancel' });
    document.documentElement.dataset.confirmOk = 'Confirmar';
    document.documentElement.dataset.confirmCancel = 'Cancelar';
    expect(pageConfirmLabels()).toEqual({ confirm: 'Confirmar', cancel: 'Cancelar' });
    delete document.documentElement.dataset.confirmOk;
    delete document.documentElement.dataset.confirmCancel;
  });

  it('resolves false on cancel, close (Escape) and backdrop click', async () => {
    const cancelled = askConfirm('A?', { ...labels, title: 'Reset' });
    expect(dialog().querySelector('.nowo-ui-confirm__body')!.textContent).toBe('A?');
    button('Cancel').click();
    await expect(cancelled).resolves.toBe(false);

    const escaped = askConfirm('B?', labels);
    dialog().dispatchEvent(new Event('close'));
    await expect(escaped).resolves.toBe(false);

    const outside = askConfirm('C?', labels);
    const el = dialog();
    el.querySelector('h2')!.click();
    el.click();
    await expect(outside).resolves.toBe(false);
  });

  it('takes the host classes and falls back to the page labels', async () => {
    document.documentElement.dataset.confirmOk = 'Sí';
    const answer = askConfirm('F?', { classes: { dialog: 'confirm-dialog', confirm: 'btn-primary' } });
    expect(document.querySelector('dialog')!.className).toBe('confirm-dialog');
    const yes = Array.from(document.querySelectorAll<HTMLButtonElement>('dialog button')).find((b) => b.textContent === 'Sí')!;
    expect(yes.className).toBe('btn-primary');
    expect(Array.from(document.querySelectorAll('dialog button')).map((b) => b.textContent)).toEqual(['Cancel', 'Sí']);
    yes.click();
    await expect(answer).resolves.toBe(true);
    delete document.documentElement.dataset.confirmOk;
  });

  it('uses the native modal API when the engine has it', async () => {
    const proto = HTMLDialogElement.prototype as unknown as Record<string, unknown>;
    const original = { showModal: proto.showModal, close: proto.close };
    proto.showModal = function (this: HTMLDialogElement): void {
      this.setAttribute('open', '');
    };
    // Like a browser: close() fires `close` synchronously here, which must not resolve twice.
    proto.close = function (this: HTMLDialogElement): void {
      this.removeAttribute('open');
      this.dispatchEvent(new Event('close'));
    };
    try {
      const answer = askConfirm('E?', labels);
      expect(dialog().open).toBe(true);
      button('Reset').click();
      await expect(answer).resolves.toBe(true);
    } finally {
      proto.showModal = original.showModal;
      proto.close = original.close;
    }
  });

  it('falls back to the open attribute without showModal', async () => {
    const original = HTMLDialogElement.prototype.showModal;
    // @ts-expect-error simulate an engine without <dialog> support
    HTMLDialogElement.prototype.showModal = undefined;
    try {
      const answer = askConfirm('D?', labels);
      expect(dialog().hasAttribute('open')).toBe(true);
      button('Reset').click();
      await expect(answer).resolves.toBe(true);
    } finally {
      HTMLDialogElement.prototype.showModal = original;
    }
  });
});
