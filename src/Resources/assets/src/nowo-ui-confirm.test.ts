import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import * as mod from './nowo-ui-confirm';

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
});

