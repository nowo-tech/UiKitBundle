/**
 * 2.70 — both runtimes on one page: js/nowo-ui-dialog.js + js/nowo-ui-confirm.js (imported: they bind on import) next
 * to the `remote-form-dialog` / `confirm-shared` peers. A dialog claimed by a peer is driven by the peer only: one
 * request per open, one POST per submit, one fill per toggle. Before the peers connect (or after they stop) the IIFE
 * runtimes drive the same markup, with the same protocol. Fixtures: tests/Fixtures/stimulus-dialogs/page-*.html.
 */
import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ConfirmSharedController from './confirm_shared_controller';
import RemoteFormDialogController from './remote_form_dialog_controller';
import * as dialogRuntime from '../src/nowo-ui-dialog';
import * as confirmRuntime from '../src/nowo-ui-confirm';
import { remoteNavigation, RemoteFormSession, DEFAULT_SETTINGS } from '../src/nowo-ui-remote-form-core';
import { claim, markBound, OWNER_ATTR, ownedByStimulus, release } from '../src/nowo-ui-runtime-owner';
import STIMULUS from '../../../../tests/Fixtures/stimulus-dialogs/page-stimulus.html?raw';
import IIFE from '../../../../tests/Fixtures/stimulus-dialogs/page-iife.html?raw';

const tick = (): Promise<void> => new Promise((r) => setTimeout(r, 0));

function polyfill(dialog: HTMLDialogElement): void {
  dialog.showModal = function showModal() {
    this.setAttribute('open', '');
  };
  dialog.close = function close() {
    if (!this.hasAttribute('open')) return;
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

const reply = (status: number, body = '', headers: Record<string, string> = { 'content-type': 'text/html' }): Response =>
  ({ status, type: 'basic', ok: status < 300, headers: new Headers(headers), text: () => Promise.resolve(body) }) as unknown as Response;

function click(el: Element, init: MouseEventInit = {}): MouseEvent {
  const event = new MouseEvent('click', { bubbles: true, cancelable: true, button: 0, ...init });
  el.dispatchEvent(event);
  return event;
}

const FORM = '<form method="post" action="/members/1/edit"><input name="name"><button type="submit">Save</button></form>';

describe('both runtimes loaded (2.70)', () => {
  let application: Application | null = null;
  const confirmDialog = (): HTMLDialogElement => document.getElementById('members-confirm') as HTMLDialogElement;
  const remote = (): HTMLDialogElement => document.getElementById('member-edit') as HTMLDialogElement;
  const toggles = (): HTMLButtonElement[] => Array.from(document.querySelectorAll<HTMLButtonElement>('[data-nowo-confirm-target="members-confirm"]'));
  const body = (): HTMLElement => remote().querySelector('[data-nowo-ui-dialog-body]') as HTMLElement;

  const mount = async (html: string, peers: boolean): Promise<void> => {
    document.body.innerHTML = html;
    document.querySelectorAll('dialog').forEach((d) => polyfill(d));
    if (peers) {
      application = Application.start();
      application.register('confirm-shared', ConfirmSharedController);
      application.register('remote-form-dialog', RemoteFormDialogController);
      await tick();
    }
  };

  beforeEach(() => {
    vi.spyOn(remoteNavigation, 'assign').mockImplementation(() => undefined);
    vi.spyOn(remoteNavigation, 'reload').mockImplementation(() => undefined);
  });

  afterEach(() => {
    application?.unload(['confirm-shared', 'remote-form-dialog']);
    application?.stop();
    application = null;
    document.body.innerHTML = '';
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    window.NowoUiOverlay?.releaseAll();
  });

  it('binds each runtime once per window, whatever the number of copies', () => {
    expect(markBound('dialog')).toBe(false);
    expect(markBound('confirm')).toBe(false);
    expect(markBound('2.70-test')).toBe(true);
    expect(markBound('2.70-test')).toBe(false);
    dialogRuntime.bindNowoUiDialog();
    confirmRuntime.bindNowoUiConfirm();
    expect(typeof window.nowoUiOpenRemoteForm).toBe('function');
    expect(typeof window.nowoUiOpenConfirm).toBe('function');
  });

  it('owner helpers', () => {
    const el = document.createElement('div');
    expect(ownedByStimulus(el)).toBe(false);
    expect(ownedByStimulus(null)).toBe(false);
    claim(el);
    expect(el.getAttribute(OWNER_ATTR)).toBe('stimulus');
    expect(ownedByStimulus(el)).toBe(true);
    release(el);
    expect(ownedByStimulus(el)).toBe(false);
  });

  it('opens the remote form once and posts once (no double submit)', async () => {
    await mount(STIMULUS, true);
    const fetchMock = vi.fn().mockResolvedValue(reply(200, FORM));
    vi.stubGlobal('fetch', fetchMock);
    const iifeLoaded = vi.fn();
    remote().addEventListener('nowo-ui:remote-form:loaded', iifeLoaded);
    click(document.querySelector('[data-testid="edit-1"]')!);
    await tick();
    await tick();
    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(iifeLoaded).toHaveBeenCalledTimes(1);

    const post = vi.fn().mockResolvedValue(reply(204, '', { 'X-Nowo-Redirect': '/members' }));
    vi.stubGlobal('fetch', post);
    body().querySelector('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await tick();
    await tick();
    expect(post).toHaveBeenCalledTimes(1);
    expect(remoteNavigation.assign).toHaveBeenCalledTimes(1);
    expect(remoteNavigation.assign).toHaveBeenCalledWith('/members');
  });

  it('the IIFE runtime leaves a claimed dialog alone: × and backdrop are the peer\'s', async () => {
    await mount(STIMULUS, true);
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply(200, FORM)));
    click(document.querySelector('[data-testid="edit-1"]')!);
    const closed = vi.fn();
    remote().addEventListener('nowo-ui:remote-form:closed', closed);
    remote().setAttribute('data-nowo-ui-dialog-backdrop-close', '');
    click(remote(), { clientX: -100, clientY: -100 });
    click(remote().querySelector('[data-nowo-ui-dialog-close]')!);
    expect(remote().open).toBe(false);
    expect(closed).toHaveBeenCalledTimes(1);
  });

  it('fills the shared confirm once (peer) and binds the right row form', async () => {
    await mount(STIMULUS, true);
    click(toggles()[1]!);
    expect(confirmDialog().open).toBe(true);
    const submit = confirmDialog().querySelector('button[data-nowo-confirm-variant]:not([hidden])') as HTMLButtonElement;
    expect(submit.getAttribute('form')).toBe(toggles()[1]!.getAttribute('data-nowo-confirm-form'));
    // The IIFE close handler ignores the claimed dialog; the peer closes it.
    click(confirmDialog().querySelector('.nowo-ui-confirm__close')!);
    expect(confirmDialog().open).toBe(false);
    expect(submit.hasAttribute('form')).toBe(false);
  });

  it('the IIFE runtimes skip every handler of a claimed dialog', async () => {
    await mount(STIMULUS, false);
    claim(confirmDialog());
    claim(remote());
    const fetchMock = vi.fn().mockResolvedValue(reply(200, FORM));
    vi.stubGlobal('fetch', fetchMock);
    const toggle = click(toggles()[0]!);
    expect(toggle.defaultPrevented).toBe(false);
    expect(confirmDialog().open).toBe(false);
    const opener = click(document.querySelector('[data-testid="edit-1"]')!);
    expect(opener.defaultPrevented).toBe(false);
    expect(remote().open).toBe(false);
    expect(fetchMock).not.toHaveBeenCalled();
    // Opened by hand: the IIFE close button, backdrop and close-event handlers leave it too.
    remote().showModal();
    remote().setAttribute('data-nowo-ui-dialog-backdrop-close', '');
    expect(click(remote().querySelector('[data-nowo-ui-dialog-close]')!).defaultPrevented).toBe(false);
    click(remote(), { clientX: -100, clientY: -100 });
    expect(remote().open).toBe(true);
    confirmDialog().showModal();
    expect(click(confirmDialog().querySelector('[data-nowo-confirm-slot="cancel"]')!).defaultPrevented).toBe(false);
    expect(confirmDialog().open).toBe(true);
    const submit = confirmDialog().querySelector('button[data-nowo-confirm-variant]') as HTMLButtonElement;
    submit.setAttribute('form', 'x');
    confirmDialog().close();
    expect(submit.getAttribute('form')).toBe('x');
    const input = confirmDialog().querySelector('[data-nowo-confirm-slot="expect-input"]') as HTMLInputElement;
    input.setAttribute('data-nowo-confirm-expected', 'y');
    submit.setAttribute('data-nowo-confirm-submit', '');
    submit.disabled = false;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    expect(submit.disabled).toBe(false);
    remote().close();
  });

  it('a session the IIFE runtime opened before the peer connected is superseded by the peer (one POST)', async () => {
    await mount(STIMULUS, false);
    // The IIFE runtime drives the unclaimed dialog first.
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply(200, FORM)));
    click(document.querySelector('[data-testid="edit-1"]')!);
    await tick();
    await tick();
    remote().close();
    // Then the peers connect and claim it.
    application = Application.start();
    application.register('remote-form-dialog', RemoteFormDialogController);
    await tick();
    expect(remote().getAttribute(OWNER_ATTR)).toBe('stimulus');
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply(200, FORM)));
    click(document.querySelector('[data-testid="edit-2"]')!);
    await tick();
    await tick();
    const post = vi.fn().mockResolvedValue(reply(422, FORM));
    vi.stubGlobal('fetch', post);
    body().querySelector('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await tick();
    await tick();
    expect(post).toHaveBeenCalledTimes(1);
    const closed = vi.fn();
    remote().addEventListener('nowo-ui:remote-form:closed', closed);
    remote().close();
    expect(closed).toHaveBeenCalledTimes(1);
  });

  it('the IIFE runtime drives the same Stimulus markup without the peers (fallback)', async () => {
    await mount(STIMULUS, false);
    expect(remote().hasAttribute(OWNER_ATTR)).toBe(false);
    click(toggles()[0]!);
    expect(confirmDialog().open).toBe(true);
    expect(confirmDialog().querySelector('[data-nowo-confirm-slot="title"]')!.textContent).toBe('Remove Ann?');
    click(confirmDialog().querySelector('[data-nowo-confirm-slot="cancel"]')!);
    expect(confirmDialog().open).toBe(false);
  });

  it('the IIFE page: a menu group shares one dialog and each toggle binds its own row form', async () => {
    await mount(IIFE, false);
    expect(document.querySelectorAll('#members-confirm')).toHaveLength(1);
    click(toggles()[0]!);
    const submit = confirmDialog().querySelector('button[data-nowo-confirm-variant]:not([hidden])') as HTMLButtonElement;
    const form = document.getElementById(submit.getAttribute('form')!) as HTMLFormElement;
    expect(form.getAttribute('action')).toBe('/members/1/delete');
    expect((form.querySelector('input[name="_token"]') as HTMLInputElement).value).toBe('tok-1');
    confirmDialog().close();
    click(toggles()[1]!);
    const form2 = document.getElementById(submit.getAttribute('form')!) as HTMLFormElement;
    expect(form2.getAttribute('action')).toBe('/members/2/delete');
    // Typing in a type-to-confirm input of an unclaimed dialog is the IIFE's.
    const input = confirmDialog().querySelector('[data-nowo-confirm-slot="expect-input"]') as HTMLInputElement;
    input.setAttribute('data-nowo-confirm-expected', 'x');
    submit.setAttribute('data-nowo-confirm-submit', '');
    input.dispatchEvent(new Event('input', { bubbles: true }));
    expect(submit.disabled).toBe(true);
  });

  it('the IIFE page: the group dialog goes to <body> on open and a fragment duplicate is dropped', async () => {
    await mount(IIFE, false);
    const dialog = confirmDialog();
    const fragment = document.createElement('div');
    fragment.innerHTML = dialog.outerHTML;
    document.querySelector('[data-testid="rows"]')!.prepend(fragment);
    polyfill(fragment.querySelector('dialog')!);
    expect(document.querySelectorAll('#members-confirm')).toHaveLength(2);
    // getElementById now returns the newcomer (first in document order): the open still uses one dialog.
    click(toggles()[1]!);
    expect(document.querySelectorAll('#members-confirm')).toHaveLength(1);
    const kept = confirmDialog();
    expect(kept.parentElement).toBe(document.body);
    expect(kept.open).toBe(true);
  });

  it('a session only handles its dialog while it is the active one', () => {
    const dialog = document.createElement('dialog');
    dialog.innerHTML = '<h2></h2><div class="b"></div>';
    document.body.append(dialog);
    polyfill(dialog);
    const make = (): RemoteFormSession =>
      new RemoteFormSession(dialog, () => dialog.querySelector('h2'), () => dialog.querySelector('.b'), () => ({ ...DEFAULT_SETTINGS, closeOnBackdrop: true }), () => true);
    const first = make();
    const second = make();
    first.bind();
    second.bind();
    expect(first.isActive()).toBe(true);
    vi.stubGlobal('fetch', vi.fn(() => new Promise<Response>(() => undefined)));
    expect(first.open('/a', null, 'A')).toBe(true);
    expect(second.isActive()).toBe(false);
    expect(second.open('/b', null, 'B')).toBe(true);
    expect(first.isActive()).toBe(false);
    // The inactive session ignores Escape and the backdrop.
    const cancel = new Event('cancel', { cancelable: true });
    dialog.dispatchEvent(cancel);
    expect(cancel.defaultPrevented).toBe(false);
    // Destroying the inactive one leaves the active one in charge.
    first.destroy();
    expect(second.isActive()).toBe(true);
    second.destroy();
    expect(first.isActive()).toBe(true);
  });
});

describe('openConfirmDialog (2.70)', () => {
  it('returns false without showModal and true for an already open dialog', async () => {
    const { openConfirmDialog } = await import('../src/nowo-ui-confirm-core');
    const dialog = document.createElement('dialog');
    Object.defineProperty(dialog, 'showModal', { value: undefined, configurable: true });
    expect(openConfirmDialog(dialog)).toBe(false);
    const open = document.createElement('dialog');
    polyfill(open);
    open.showModal();
    expect(openConfirmDialog(open)).toBe(true);
  });
});
