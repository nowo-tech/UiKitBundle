import { afterEach, describe, expect, it } from 'vitest';

import { askConfirm, pageConfirmCloseLabel, pageConfirmDismissible, pageConfirmLabels } from './ask_confirm';

const labels = { confirm: 'Reset', cancel: 'Cancel' };
const dialog = (): HTMLDialogElement => document.querySelector('dialog.nowo-ui-confirm')!;
const button = (text: string): HTMLButtonElement =>
  Array.from(document.querySelectorAll<HTMLButtonElement>('dialog.nowo-ui-confirm button')).find((b) => b.textContent === text)!;

describe('askConfirm', () => {
  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('is not moved by elements that clobber document properties (audit HG-I7)', async () => {
    const realBody = document.body;
    const clobbered = document.createElement('img');
    realBody.append(clobbered);
    // A named <img> or <form> becomes an own property of the document and hides the prototype's. `documentElement` is not
    // here because happy-dom's own `body` getter reads it; the demo e2e clobbers all three in real browsers.
    for (const name of ['body', 'createElement']) {
      Object.defineProperty(document, name, { value: clobbered, configurable: true });
    }
    try {
      const answer = askConfirm('Delete?', { confirm: 'Reset', cancel: 'Cancel' });
      const shown = realBody.querySelector('dialog.nowo-ui-confirm')!;
      expect(shown.parentElement).toBe(realBody);
      expect(clobbered.querySelector('dialog')).toBeNull();
      (Array.from(shown.querySelectorAll('button')).find((b) => b.textContent === 'Reset') as HTMLButtonElement).click();
      await expect(answer).resolves.toBe(true);
    } finally {
      for (const name of ['body', 'createElement']) {
        delete (document as unknown as Record<string, unknown>)[name];
      }
    }
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
    expect(Array.from(document.querySelectorAll('dialog button')).map((b) => b.textContent)).toEqual(['×', 'Cancel', 'Sí']);
    // A 2.30 class map without `close` keeps the kit class for the ×.
    expect(document.querySelector('dialog button')!.className).toBe('nowo-ui-confirm__close');
    yes.click();
    await expect(answer).resolves.toBe(true);
    delete document.documentElement.dataset.confirmOk;
  });

  it('renders the close button (×) after the title by default, labelled and cancelling (2.31)', async () => {
    const answer = askConfirm('Reset?', { ...labels, title: 'Reset layout' });
    const header = dialog().querySelector('.nowo-ui-confirm__header')!;
    expect(Array.from(header.children).map((el) => el.className)).toEqual(['nowo-ui-confirm__title', 'nowo-ui-confirm__close', 'nowo-ui-confirm__body']);
    const close = header.querySelector<HTMLButtonElement>('button.nowo-ui-confirm__close')!;
    expect(close.type).toBe('button');
    expect(close.getAttribute('aria-label')).toBe('Close');
    expect(close.title).toBe('Close');
    expect(close.innerHTML).toBe('<span aria-hidden="true">×</span>');
    // Focus still starts on Cancel (audit A11Y-03), not on the ×.
    expect(document.activeElement).toBe(button('Cancel'));
    close.click();
    await expect(answer).resolves.toBe(false);
    expect(document.querySelector('dialog')).toBeNull();
  });

  it('takes the close label and class from the options or the page, and can drop the × (2.31)', async () => {
    expect(pageConfirmCloseLabel()).toBe('Close');
    expect(pageConfirmDismissible()).toBe(true);
    document.documentElement.dataset.confirmClose = 'Cerrar';
    const page = askConfirm('A?', { ...labels, classes: { close: 'btn-ghost confirm-dialog__close' } });
    let close = dialog().querySelector<HTMLButtonElement>('.confirm-dialog__close')!;
    expect(close.className).toBe('btn-ghost confirm-dialog__close');
    expect(close.getAttribute('aria-label')).toBe('Cerrar');
    close.click();
    await expect(page).resolves.toBe(false);

    const own = askConfirm('B?', { ...labels, closeLabel: 'Dismiss' });
    close = dialog().querySelector<HTMLButtonElement>('.nowo-ui-confirm__close')!;
    expect(close.getAttribute('aria-label')).toBe('Dismiss');
    button('Reset').click();
    await expect(own).resolves.toBe(true);
    delete document.documentElement.dataset.confirmClose;

    const off = askConfirm('C?', { ...labels, dismissible: false });
    expect(dialog().querySelector('.nowo-ui-confirm__close')).toBeNull();
    button('Cancel').click();
    await expect(off).resolves.toBe(false);

    document.documentElement.dataset.confirmDismissible = 'false';
    expect(pageConfirmDismissible()).toBe(false);
    const pageOff = askConfirm('D?', labels);
    expect(dialog().querySelector('.nowo-ui-confirm__close')).toBeNull();
    button('Cancel').click();
    await expect(pageOff).resolves.toBe(false);
    const forced = askConfirm('E?', { ...labels, dismissible: true });
    expect(dialog().querySelector('.nowo-ui-confirm__close')).not.toBeNull();
    button('Cancel').click();
    await expect(forced).resolves.toBe(false);
    document.documentElement.dataset.confirmDismissible = '0';
    expect(pageConfirmDismissible()).toBe(false);
    for (const keeps of ['', 'no', 'off', 'False']) {
      document.documentElement.dataset.confirmDismissible = keeps;
      expect(pageConfirmDismissible()).toBe(true);
    }
    delete document.documentElement.dataset.confirmDismissible;
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
