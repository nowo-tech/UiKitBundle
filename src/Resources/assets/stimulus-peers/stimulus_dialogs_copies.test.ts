/**
 * 2.70 (audit ZAF-M1) — js/nowo-ui-dialog.js and the host's Stimulus bundle each carry their own copy of
 * nowo-ui-remote-form-core.ts. Here the IIFE runtime is imported first (one module graph), then the module registry is
 * reset and the `remote-form-dialog` peer imported again (a second copy of the core and of Stimulus). The user opens the
 * remote form before the peers connect (the IIFE creates and binds its session), then the peer claims the dialog:
 * the next submit is posted exactly once.
 */
import { afterEach, describe, expect, it, vi } from 'vitest';
import FIXTURE from '../../../../tests/Fixtures/stimulus-dialogs/page-stimulus.html?raw';

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

const FORM = '<form method="post" action="/members/1/edit"><input name="name"><button type="submit">Save</button></form>';

describe('two copies of the remote form core (2.70, ZAF-M1)', () => {
  afterEach(() => {
    document.body.innerHTML = '';
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('a session the IIFE copy opened before the peer connected never posts once the peer owns the dialog', async () => {
    document.body.innerHTML = FIXTURE;
    document.querySelectorAll('dialog').forEach((d) => polyfill(d));
    const iifeCore = await import('../src/nowo-ui-remote-form-core');
    await import('../src/nowo-ui-dialog');
    vi.spyOn(iifeCore.remoteNavigation, 'assign').mockImplementation(() => undefined);
    vi.spyOn(iifeCore.remoteNavigation, 'reload').mockImplementation(() => undefined);

    vi.resetModules();
    const peerCore = await import('../src/nowo-ui-remote-form-core');
    expect(peerCore.RemoteFormSession).not.toBe(iifeCore.RemoteFormSession);
    vi.spyOn(peerCore.remoteNavigation, 'assign').mockImplementation(() => undefined);
    vi.spyOn(peerCore.remoteNavigation, 'reload').mockImplementation(() => undefined);
    const { Application } = await import('@hotwired/stimulus');
    const Peer = (await import('./remote_form_dialog_controller')).default;

    const remote = document.getElementById('member-edit') as HTMLDialogElement;
    const body = (): HTMLElement => remote.querySelector('[data-nowo-ui-dialog-body]') as HTMLElement;

    // 1. Before the peers: the IIFE copy opens, loads and binds its session.
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply(200, FORM)));
    document.querySelector<HTMLElement>('[data-testid="edit-1"]')!.click();
    await tick();
    await tick();
    expect(remote.open).toBe(true);
    remote.close();

    // 2. The peer copy connects and claims the dialog.
    const application = Application.start();
    application.register('remote-form-dialog', Peer);
    await tick();
    expect(remote.getAttribute('data-nowo-ui-runtime-owner')).toBe('stimulus');
    // window.nowoUiOpenRemoteForm (the IIFE) refuses a claimed dialog.
    expect(window.nowoUiOpenRemoteForm?.('member-edit', '/members/1/edit', null)).toBe(false);

    // 3. Open from the page and submit: one GET, one POST, one closed event.
    const fetchMock = vi.fn().mockResolvedValue(reply(200, FORM));
    vi.stubGlobal('fetch', fetchMock);
    document.querySelector<HTMLElement>('[data-testid="edit-2"]')!.click();
    await tick();
    await tick();
    expect(fetchMock).toHaveBeenCalledTimes(1);
    const post = vi.fn().mockResolvedValue(reply(204, '', { 'X-Nowo-Redirect': '/members' }));
    vi.stubGlobal('fetch', post);
    const closed = vi.fn();
    remote.addEventListener('nowo-ui:remote-form:closed', closed);
    body().querySelector('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await tick();
    await tick();
    expect(post).toHaveBeenCalledTimes(1);
    expect(peerCore.remoteNavigation.assign).toHaveBeenCalledTimes(1);
    expect(iifeCore.remoteNavigation.assign).not.toHaveBeenCalled();
    expect(closed).toHaveBeenCalledTimes(1);

    application.unload('remote-form-dialog');
    application.stop();
  });

  it('the shared registry lives on window', async () => {
    const core = await import('../src/nowo-ui-remote-form-core');
    const dialog = document.createElement('dialog');
    dialog.innerHTML = '<h2></h2><div class="b"></div>';
    document.body.append(dialog);
    polyfill(dialog);
    vi.stubGlobal('fetch', vi.fn(() => new Promise<Response>(() => undefined)));
    const session = new core.RemoteFormSession(dialog, () => null, () => dialog.querySelector('.b'), () => core.DEFAULT_SETTINGS, () => true);
    session.bind();
    expect(session.open('/a', null, 'A')).toBe(true);
    const registry = (window as unknown as Record<symbol, unknown>)[Symbol.for('nowo-ui.remote-form.active')] as WeakMap<HTMLDialogElement, unknown>;
    expect(registry.get(dialog)).toBe(session);
    dialog.close();
    expect(registry.get(dialog)).toBe('closed');
    expect(session.isActive()).toBe(false);
    // A yielding session neither opens nor acts.
    const yielding = new core.RemoteFormSession(dialog, () => null, () => dialog.querySelector('.b'), () => core.DEFAULT_SETTINGS, () => true, true, () => true);
    expect(yielding.open('/b', null, 'B')).toBe(false);
    expect(yielding.isActive()).toBe(false);
    session.destroy();
  });
});
