import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as mod from './nowo-ui-confirm';

// vitest 4 + happy-dom no longer expose window.confirm; give vi.spyOn() a function to wrap.
if (typeof window.confirm !== 'function') {
  Object.defineProperty(window, 'confirm', { value: (): boolean => true, configurable: true, writable: true });
}

describe('nowo-ui-confirm', () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <button type="button" data-nowo-confirm-open data-nowo-confirm-target="c1">Open</button>
      <dialog id="c1" data-nowo-ui-confirm>
        <button type="button" data-nowo-confirm-close>Cancel</button>
      </dialog>
    `;
    const dialog = document.getElementById('c1') as HTMLDialogElement;
    if (typeof dialog.showModal !== 'function') {
      dialog.showModal = function showModal() {
        this.setAttribute('open', '');
      };
      dialog.close = function close() {
        this.removeAttribute('open');
      };
      Object.defineProperty(dialog, 'open', {
        get() {
          return this.hasAttribute('open');
        },
      });
    }
    mod.bindNowoUiConfirm();
  });

  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('opens and closes via helpers', () => {
    const dialog = document.getElementById('c1') as HTMLDialogElement;
    mod.openConfirm('c1');
    expect(dialog.open).toBe(true);
    mod.closeConfirm(dialog);
    expect(dialog.open).toBe(false);
  });

  it('ignores missing ids and null', () => {
    mod.openConfirm(null);
    mod.openConfirm('missing');
    mod.closeConfirm(null);
    expect((document.getElementById('c1') as HTMLDialogElement).open).toBe(false);
  });

  it('opens via data attributes', () => {
    document.querySelector('[data-nowo-confirm-open]')!
      .dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect((document.getElementById('c1') as HTMLDialogElement).open).toBe(true);
  });

  it('closes via data-nowo-confirm-close', () => {
    mod.openConfirm('c1');
    document.querySelector('[data-nowo-confirm-close]')!
      .dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect((document.getElementById('c1') as HTMLDialogElement).open).toBe(false);
  });

  it('bindNowoUiConfirm is idempotent', () => {
    mod.bindNowoUiConfirm();
    expect(window.nowoUiOpenConfirm).toBe(mod.openConfirm);
  });

  it('opens via HTMLDialogElement and ignores open without target', () => {
    const dialog = document.getElementById('c1') as HTMLDialogElement;
    mod.openConfirm(dialog);
    expect(dialog.open).toBe(true);
    document.body.innerHTML += '<button type="button" data-nowo-confirm-open>No target</button>';
    document.querySelector('[data-nowo-confirm-open]:not([data-nowo-confirm-target])')!
      .dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
  });

  it('ignores unrelated clicks', () => {
    document.body.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect((document.getElementById('c1') as HTMLDialogElement).open).toBe(false);
  });

  describe('type-to-confirm', () => {
    const polyfill = (dialog: HTMLDialogElement): void => {
      if (typeof dialog.showModal !== 'function') {
        dialog.showModal = function showModal() {
          this.setAttribute('open', '');
        };
        dialog.close = function close() {
          this.removeAttribute('open');
        };
        Object.defineProperty(dialog, 'open', {
          get() {
            return this.hasAttribute('open');
          },
        });
      }
    };

    beforeEach(() => {
      document.body.innerHTML = `
        <dialog id="c2" data-nowo-ui-confirm>
          <input data-nowo-confirm-expected="acme-prod" form="c2-form">
          <form id="c2-form"><button type="submit" data-nowo-confirm-submit disabled>Delete</button></form>
        </dialog>
        <section data-nowo-confirm-scope>
          <input id="page-input" data-nowo-confirm-expected="ORDER-1">
          <form><button id="page-submit" type="submit" data-nowo-confirm-submit>Delete</button></form>
        </section>
        <input id="orphan" data-nowo-confirm-expected="x">
        <input id="plain">
      `;
      polyfill(document.getElementById('c2') as HTMLDialogElement);
    });

    const type = (input: HTMLInputElement, value: string): void => {
      input.value = value;
      input.dispatchEvent(new Event('input', { bubbles: true }));
    };

    it('enables the confirm button only on an exact match', () => {
      const input = document.querySelector('#c2 input') as HTMLInputElement;
      const button = document.querySelector('#c2 button') as HTMLButtonElement;
      type(input, 'acme');
      expect(button.disabled).toBe(true);
      type(input, 'acme-prod');
      expect(button.disabled).toBe(false);
      type(input, 'acme-prod ');
      expect(button.disabled).toBe(true);
    });

    it('clears, locks and focuses the input when the dialog opens', () => {
      const dialog = document.getElementById('c2') as HTMLDialogElement;
      const input = dialog.querySelector('input') as HTMLInputElement;
      const button = dialog.querySelector('button') as HTMLButtonElement;
      type(input, 'acme-prod');
      expect(button.disabled).toBe(false);
      mod.openConfirm(dialog);
      expect(dialog.open).toBe(true);
      expect(input.value).toBe('');
      expect(button.disabled).toBe(true);
      expect(document.activeElement).toBe(input);
    });

    it('locks a full-page scope on load and ignores inputs outside a scope', () => {
      const button = document.getElementById('page-submit') as HTMLButtonElement;
      expect(button.disabled).toBe(false);
      mod.syncAllExpected(document);
      expect(button.disabled).toBe(true);
      type(document.getElementById('page-input') as HTMLInputElement, 'ORDER-1');
      expect(button.disabled).toBe(false);
      type(document.getElementById('orphan') as HTMLInputElement, 'x');
      type(document.getElementById('plain') as HTMLInputElement, 'x');
      expect(mod.resetExpected(document.createElement('div'))).toBeNull();
    });

    it('skips non-button submit markers and non-element / non-input event targets', () => {
      document.querySelector('#c2 form')!.insertAdjacentHTML('beforeend', '<a href="#" data-nowo-confirm-submit>x</a>');
      type(document.querySelector('#c2 input') as HTMLInputElement, 'acme-prod');
      expect((document.querySelector('#c2 button') as HTMLButtonElement).disabled).toBe(false);
      document.body.dispatchEvent(new Event('input', { bubbles: true }));
      document.dispatchEvent(new MouseEvent('click', { bubbles: true }));
      expect((document.getElementById('c2') as HTMLDialogElement).open).toBe(false);
    });
  });
});

describe('nowo-ui-confirm missing confirm-submit peer warning', () => {
  const submit = (form: HTMLFormElement): void => {
    form.addEventListener('submit', (event) => event.preventDefault());
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
  };

  beforeEach(() => {
    mod.bindNowoUiConfirm();
  });

  afterEach(() => {
    document.body.innerHTML = '';
    vi.restoreAllMocks();
  });

  it('warns once per peer form submitted without the peer connected', () => {
    document.body.innerHTML = '<form data-controller="confirm-submit" data-confirm-submit-message-value="Delete?"></form>';
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    const form = document.querySelector('form')!;
    submit(form);
    submit(form);
    expect(warn).toHaveBeenCalledTimes(1);
    expect(String(warn.mock.calls[0]![0])).toContain('confirm-submit');
  });

  // 2.23 (audit CK L1): without the peer (not registered, bundle not loaded, not yet connected) the form fails closed
  it('falls back to window.confirm without the peer and cancels the submit when declined', () => {
    document.body.innerHTML = '<form data-controller="confirm-submit" data-confirm-submit-message-value=" Delete invoice #1042? "></form>';
    vi.spyOn(console, 'warn').mockImplementation(() => undefined);
    const ask = vi.spyOn(window, 'confirm').mockReturnValueOnce(false).mockReturnValueOnce(true);
    const form = document.querySelector('form')!;

    const declined = new Event('submit', { bubbles: true, cancelable: true });
    form.dispatchEvent(declined);
    expect(declined.defaultPrevented).toBe(true);
    expect(ask).toHaveBeenLastCalledWith('Delete invoice #1042?');

    const accepted = new Event('submit', { bubbles: true, cancelable: true });
    form.dispatchEvent(accepted);
    expect(accepted.defaultPrevented).toBe(false);
    expect(ask).toHaveBeenCalledTimes(2);
  });

  it('keeps a blocked form cancelled and lets an empty message through without asking (no peer)', () => {
    document.body.innerHTML = '<form id="b" data-confirm-submit-message-value="Remove?" data-confirm-submit-blocked-value="true"></form><form id="e" data-confirm-submit-message-value=" "></form>';
    vi.spyOn(console, 'warn').mockImplementation(() => undefined);
    const ask = vi.spyOn(window, 'confirm');
    const blocked = new Event('submit', { bubbles: true, cancelable: true });
    document.getElementById('b')!.dispatchEvent(blocked);
    const empty = new Event('submit', { bubbles: true, cancelable: true });
    document.getElementById('e')!.dispatchEvent(empty);
    expect(blocked.defaultPrevented).toBe(true);
    expect(empty.defaultPrevented).toBe(false);
    expect(ask).not.toHaveBeenCalled();
  });

  it('stays silent when the peer is connected, for other forms and for non-form targets', () => {
    document.body.innerHTML = '<form id="peer" data-confirm-submit-message-value="Delete?"></form><form id="plain"></form>';
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
    const ask = vi.spyOn(window, 'confirm');
    const peer = document.getElementById('peer') as HTMLFormElement;
    (peer as unknown as Record<symbol, unknown>)[Symbol.for('nowo-ui.confirm-submit')] = true;
    submit(peer);
    submit(document.getElementById('plain') as HTMLFormElement);
    mod.warnMissingConfirmPeer(new Event('submit'));
    expect(warn).not.toHaveBeenCalled();
    expect(ask).not.toHaveBeenCalled();
  });
});
