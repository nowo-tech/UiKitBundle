import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import * as mod from './nowo-ui-dialog';

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

function click(el: Element, init: MouseEventInit = {}): MouseEvent {
  const event = new MouseEvent('click', { bubbles: true, cancelable: true, ...init });
  el.dispatchEvent(event);
  return event;
}

describe('nowo-ui-dialog', () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <a href="/fallback" id="opener" data-nowo-ui-dialog-open="d1">Open</a>
      <button type="button" id="missing" data-nowo-ui-dialog-open="nope">Missing</button>
      <button type="button" id="outside-close" data-nowo-ui-dialog-close="d1">Close d1</button>
      <dialog id="d1" data-nowo-ui-dialog data-nowo-ui-dialog-backdrop-close>
        <input id="first" autofocus>
        <button type="button" id="inner-close" data-nowo-ui-dialog-close>Close</button>
      </dialog>
      <dialog id="d2" data-nowo-ui-dialog><p id="d2-text">x</p></dialog>
      <dialog id="plain"></dialog>
    `;
    document.querySelectorAll('dialog').forEach((d) => polyfill(d as HTMLDialogElement));
    mod.bindNowoUiDialog();
  });

  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('opens from an opener, focuses [autofocus] and prevents the fallback navigation', () => {
    const event = click(document.getElementById('opener')!);
    const dialog = document.getElementById('d1') as HTMLDialogElement;
    expect(dialog.open).toBe(true);
    expect(event.defaultPrevented).toBe(true);
    expect(document.activeElement?.id).toBe('first');
  });

  it('keeps the default action when the target is not a UiKit dialog', () => {
    expect(click(document.getElementById('missing')!).defaultPrevented).toBe(false);
    expect(mod.resolveDialog('plain')).toBeNull();
    expect(mod.resolveDialog(null)).toBeNull();
    expect(mod.openDialog('nope')).toBe(false);
  });

  it('closes from inside, from outside by id, and returns focus to the opener', () => {
    const opener = document.getElementById('opener') as HTMLElement;
    const dialog = document.getElementById('d1') as HTMLDialogElement;
    click(opener);
    click(document.getElementById('inner-close')!);
    expect(dialog.open).toBe(false);
    expect(document.activeElement).toBe(opener);

    mod.openDialog('d1');
    expect(click(document.getElementById('outside-close')!).defaultPrevented).toBe(true);
    expect(dialog.open).toBe(false);
  });

  it('ignores close buttons that target no dialog and clicks on plain content', () => {
    document.body.insertAdjacentHTML('beforeend', '<button type="button" id="stray" data-nowo-ui-dialog-close>x</button>');
    expect(click(document.getElementById('stray')!).defaultPrevented).toBe(false);
    click(document.getElementById('d2-text')!);
  });

  it('closes on a backdrop click only when enabled', () => {
    const d1 = document.getElementById('d1') as HTMLDialogElement;
    const d2 = document.getElementById('d2') as HTMLDialogElement;
    const rect = { left: 100, right: 300, top: 100, bottom: 300, width: 200, height: 200, x: 100, y: 100, toJSON: () => ({}) };
    d1.getBoundingClientRect = () => rect as DOMRect;
    d2.getBoundingClientRect = () => rect as DOMRect;
    mod.openDialog(d1);
    mod.openDialog(d2);

    click(d1, { clientX: 150, clientY: 150 });
    expect(d1.open).toBe(true);
    click(d2, { clientX: 10, clientY: 10 });
    expect(d2.open).toBe(true);
    click(d1, { clientX: 10, clientY: 10 });
    expect(d1.open).toBe(false);
  });

  it('isBackdropClick ignores zero-size boxes', () => {
    const d1 = document.getElementById('d1') as HTMLDialogElement;
    d1.getBoundingClientRect = () => ({ left: 0, right: 0, top: 0, bottom: 0, width: 0, height: 0 }) as DOMRect;
    expect(mod.isBackdropClick(d1, 5, 5)).toBe(false);
  });

  it('exposes window helpers and is idempotent', () => {
    mod.bindNowoUiDialog();
    window.nowoUiOpenDialog?.('d2');
    expect((document.getElementById('d2') as HTMLDialogElement).open).toBe(true);
    window.nowoUiCloseDialog?.('d2');
    expect((document.getElementById('d2') as HTMLDialogElement).open).toBe(false);
    mod.closeDialog('d2');
    mod.closeDialog(null);
  });

  it('opening an open dialog is a no-op and focus restore skips detached openers', () => {
    const d1 = document.getElementById('d1') as HTMLDialogElement;
    const detached = document.createElement('button');
    mod.openDialog(d1, detached);
    expect(mod.openDialog(d1)).toBe(true);
    mod.restoreFocus(d1);
    mod.restoreFocus(d1);
  });

  it('a close event from another dialog is ignored', () => {
    const plain = document.getElementById('plain') as HTMLDialogElement;
    plain.dispatchEvent(new Event('close'));
  });
});
