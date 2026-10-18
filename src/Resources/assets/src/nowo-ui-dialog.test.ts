import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
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

  it('replaces the enclosing dialog and hands the focus-return target over', () => {
    const opener = document.getElementById('opener') as HTMLElement;
    const d1 = document.getElementById('d1') as HTMLDialogElement;
    const d2 = document.getElementById('d2') as HTMLDialogElement;
    d1.insertAdjacentHTML('beforeend', '<button type="button" id="swap" data-nowo-ui-dialog-open="d2" data-nowo-ui-dialog-replace>Next</button>');
    click(opener);
    expect(d1.open).toBe(true);
    const event = click(document.getElementById('swap')!);
    expect(event.defaultPrevented).toBe(true);
    expect(d1.open).toBe(false);
    expect(d2.open).toBe(true);
    mod.closeDialog(d2);
    expect(document.activeElement).toBe(opener);
  });

  it('replace falls back to a plain open outside a dialog, for the same dialog, and without a handover', () => {
    document.body.insertAdjacentHTML('beforeend', '<button type="button" id="lonely" data-nowo-ui-dialog-open="d2" data-nowo-ui-dialog-replace>x</button>');
    const lonely = document.getElementById('lonely') as HTMLElement;
    click(lonely);
    const d2 = document.getElementById('d2') as HTMLDialogElement;
    expect(d2.open).toBe(true);
    mod.closeDialog(d2);
    expect(document.activeElement).toBe(lonely);

    const d1 = document.getElementById('d1') as HTMLDialogElement;
    d1.insertAdjacentHTML('beforeend', '<button type="button" id="self" data-nowo-ui-dialog-open="d1" data-nowo-ui-dialog-replace>x</button>');
    mod.openDialog(d1);
    expect(mod.replaceDialog('d1', document.getElementById('self')!)).toBe(true);
    expect(d1.open).toBe(true);
    expect(mod.replaceDialog('d2', document.getElementById('self')!)).toBe(true);
    expect(d1.open).toBe(false);
    expect(d2.open).toBe(true);
    expect(mod.replaceDialog('nope', document.getElementById('self')!)).toBe(false);
  });

  it('ignores clicks whose target is not an element', () => {
    const event = new MouseEvent('click', { bubbles: true, cancelable: true });
    document.dispatchEvent(event);
    expect(event.defaultPrevented).toBe(false);
  });
});

describe('nowo-ui-dialog remote content', () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <a href="/items/7/edit" id="remote" data-nowo-ui-dialog-open="r1" data-nowo-ui-dialog-src="/items/7/edit?modal=1">Edit</a>
      <dialog id="r1" data-nowo-ui-dialog><div class="nowo-ui-dialog__body"></div></dialog>
      <dialog id="r2" data-nowo-ui-dialog><p>no body</p></dialog>
    `;
    document.querySelectorAll('dialog').forEach((d) => polyfill(d as HTMLDialogElement));
    mod.bindNowoUiDialog();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    document.body.innerHTML = '';
  });

  it('fetches same-origin HTML into the body and dispatches nowo-ui:dialog-loaded', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, text: () => Promise.resolve('<form id="loaded"></form>') });
    vi.stubGlobal('fetch', fetchMock);
    const dialog = document.getElementById('r1') as HTMLDialogElement;
    const loaded = new Promise<CustomEvent>((resolve) => dialog.addEventListener('nowo-ui:dialog-loaded', (e) => resolve(e as CustomEvent)));
    const event = click(document.getElementById('remote')!);
    expect(event.defaultPrevented).toBe(true);
    const detail = (await loaded).detail as { url: string };
    expect(detail.url).toBe(`${window.location.origin}/items/7/edit?modal=1`);
    expect(dialog.querySelector('#loaded')).not.toBeNull();
    expect(dialog.querySelector('.nowo-ui-dialog__body')?.hasAttribute('aria-busy')).toBe(false);
    expect(fetchMock.mock.calls[0][1]).toMatchObject({ credentials: 'same-origin' });
  });

  it('follows the fallback href when the request fails, and refuses other origins or missing bodies', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 500, text: () => Promise.resolve('') }));
    const assign = vi.spyOn(mod.navigation, 'assign').mockImplementation(() => undefined);
    const dialog = document.getElementById('r1') as HTMLDialogElement;
    mod.openDialog(dialog);
    expect(await mod.loadDialogContent(dialog, '/x', '/items/7/edit')).toBe(false);
    expect(assign).toHaveBeenCalledWith('/items/7/edit');
    expect(dialog.open).toBe(false);

    expect(await mod.loadDialogContent(dialog, '/x')).toBe(false);
    expect(await mod.loadDialogContent(dialog, 'https://evil.example/x')).toBe(false);
    expect(await mod.loadDialogContent(document.getElementById('r2') as HTMLDialogElement, '/x')).toBe(false);
    expect(mod.sameOriginUrl('http://[bad')).toBeNull();
  });

  it('navigation.assign follows the URL', () => {
    const spy = vi.fn();
    const original = window.location;
    Object.defineProperty(window, 'location', { configurable: true, value: { ...original, assign: spy, href: original.href, origin: original.origin } });
    mod.navigation.assign('/next');
    expect(spy).toHaveBeenCalledWith('/next');
    Object.defineProperty(window, 'location', { configurable: true, value: original });
  });

  it('closes open menus on an outside click and on Escape (focus back to the toggle)', () => {
    document.body.innerHTML = `
      <details id="m1" data-nowo-ui-menu open><summary id="t1">Menu</summary><ul><li><a id="i1" href="#a">A</a></li></ul></details>
      <details id="m2" data-nowo-ui-menu open><summary id="t2">More</summary><ul><li><a id="i2" href="#b">B</a></li></ul></details>
      <details id="plain" open><summary>Plain</summary></details>
      <p id="outside">x</p>
    `;
    const m1 = document.getElementById('m1') as HTMLDetailsElement;
    const m2 = document.getElementById('m2') as HTMLDetailsElement;

    click(document.getElementById('i1')!);
    expect(m1.hasAttribute('open')).toBe(true);
    expect(m2.hasAttribute('open')).toBe(false);

    click(document.getElementById('outside')!);
    expect(m1.hasAttribute('open')).toBe(false);
    expect(document.getElementById('plain')!.hasAttribute('open')).toBe(true);

    m2.setAttribute('open', '');
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter' }));
    expect(m2.hasAttribute('open')).toBe(true);
    (document.getElementById('i2') as HTMLElement).focus();
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    expect(m2.hasAttribute('open')).toBe(false);
    expect(document.activeElement?.id).toBe('t2');

    m1.setAttribute('open', '');
    (document.getElementById('outside') as HTMLElement).focus();
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    expect(m1.hasAttribute('open')).toBe(false);
    expect(mod.closeMenus()).toEqual([]);
  });
});
