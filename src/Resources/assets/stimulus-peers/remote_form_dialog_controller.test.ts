import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import RemoteFormDialogController, { navigation } from './remote_form_dialog_controller';

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

describe('remote-form-dialog peer', () => {
  let application: Application;
  const root = (): HTMLElement => document.querySelector('[data-controller="remote-form-dialog"]') as HTMLElement;
  const dialog = (): HTMLDialogElement => document.querySelector('dialog') as HTMLDialogElement;
  const body = (): HTMLElement => document.querySelector('[data-remote-form-dialog-target="body"]') as HTMLElement;

  const mount = async (values = ''): Promise<void> => {
    document.body.innerHTML = `
      <section data-controller="remote-form-dialog" data-remote-form-dialog-loading-value="Loading…"
               data-remote-form-dialog-error-value="Could not save" ${values}>
        <a id="add" href="/notifications/new" data-action="remote-form-dialog#open" data-remote-form-dialog-title="New destination">Add</a>
        <a id="edit" href="/notifications/7/edit" data-action="remote-form-dialog#open">Edit Alerts</a>
        <dialog data-remote-form-dialog-target="dialog">
          <h2 data-remote-form-dialog-target="title" tabindex="-1"></h2>
          <button type="button" id="x" data-action="remote-form-dialog#close">×</button>
          <div data-remote-form-dialog-target="body" data-action="submit->remote-form-dialog#submit"></div>
        </dialog>
      </section>`;
    polyfill(dialog());
    application = Application.start();
    application.register('remote-form-dialog', RemoteFormDialogController);
    await tick();
  };

  beforeEach(() => {
    vi.spyOn(navigation, 'assign').mockImplementation(() => undefined);
    vi.spyOn(navigation, 'reload').mockImplementation(() => undefined);
  });

  afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    window.NowoUiOverlay?.releaseAll();
  });

  it('opens with the opener title, loads with X-Nowo-Dialog and focuses the title', async () => {
    await mount();
    const fetchMock = vi.fn().mockResolvedValue(reply(200, '<form method="post" action="/notifications/new"><input name="label"></form>'));
    vi.stubGlobal('fetch', fetchMock);
    const click = new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 });
    document.getElementById('add')!.dispatchEvent(click);
    expect(click.defaultPrevented).toBe(true);
    expect(dialog().open).toBe(true);
    expect(document.activeElement?.textContent).toBe('New destination');
    await tick();
    expect((fetchMock.mock.calls[0] as [string, RequestInit])[1].headers).toMatchObject({ 'X-Nowo-Dialog': '1' });
    expect(body().querySelector('form')).not.toBeNull();
  });

  it('takes the title from the link text and keeps modified clicks for the browser', async () => {
    await mount();
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply(200, '<p>x</p>')));
    const modified = new MouseEvent('click', { bubbles: true, cancelable: true, button: 0, metaKey: true });
    document.getElementById('edit')!.dispatchEvent(modified);
    expect(modified.defaultPrevented).toBe(false);
    expect(dialog().open).toBe(false);
    document.getElementById('edit')!.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 }));
    expect(document.querySelector('h2')?.textContent).toBe('Edit Alerts');
  });

  it('posts through the body action, dispatches saved and follows the redirect', async () => {
    await mount();
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply(200, '<form method="post" action="/notifications/new"><button type="submit">Save</button></form>')));
    document.getElementById('add')!.click();
    await tick();
    await tick();
    const saved = vi.fn();
    root().addEventListener('remote-form-dialog:saved', saved);
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply(204, '', { 'X-Nowo-Redirect': '/settings/alerts' })));
    body().querySelector('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await tick();
    await tick();
    expect(saved).toHaveBeenCalled();
    expect((saved.mock.calls[0]?.[0] as CustomEvent).detail).toEqual({ redirect: '/settings/alerts', status: 204 });
    expect(navigation.assign).toHaveBeenCalledWith('/settings/alerts');
    expect(dialog().open).toBe(false);
    expect(document.activeElement?.id).toBe('add');
  });

  it('lets a listener prevent saved (the host handles success)', async () => {
    await mount();
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply(200, '<form method="post" action="/x"></form>')));
    document.getElementById('add')!.click();
    await tick();
    await tick();
    root().addEventListener('remote-form-dialog:saved', (event) => event.preventDefault());
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply(204, '', {})));
    body().querySelector('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await tick();
    await tick();
    expect(navigation.reload).not.toHaveBeenCalled();
    expect(dialog().open).toBe(false);
  });

  it('reads renamed headers, invalid selector, load error and backdrop values', async () => {
    await mount(`data-remote-form-dialog-request-header-value="X-Beacon-Dialog" data-remote-form-dialog-redirect-header-value="X-Beacon-Redirect"
      data-remote-form-dialog-invalid-selector-value=".has-error input" data-remote-form-dialog-follow-on-load-error-value="false"
      data-remote-form-dialog-load-error-value="Load failed" data-remote-form-dialog-close-on-backdrop-value="true"`);
    const fetchMock = vi.fn().mockResolvedValue(reply(200, '<form method="post" action="/x"></form>'));
    vi.stubGlobal('fetch', fetchMock);
    document.getElementById('add')!.click();
    await tick();
    await tick();
    expect((fetchMock.mock.calls[0] as [string, RequestInit])[1].headers).toMatchObject({ 'X-Beacon-Dialog': '1' });
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply(422, '<div class="has-error"><input id="bad"></div>')));
    body().querySelector('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await tick();
    await tick();
    expect(document.activeElement?.id).toBe('bad');
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply(500)));
    document.getElementById('edit')!.click();
    await tick();
    await tick();
    expect(body().querySelector('[role="alert"]')?.textContent).toBe('Load failed');
    expect(navigation.assign).not.toHaveBeenCalled();
  });

  it('closes from the close action, emptying the body', async () => {
    await mount();
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply(200, '<p id="frag">x</p>')));
    document.getElementById('add')!.click();
    await tick();
    await tick();
    const closed = vi.fn();
    root().addEventListener('remote-form-dialog:closed', closed);
    document.getElementById('x')!.click();
    expect(dialog().open).toBe(false);
    expect(document.getElementById('frag')).toBeNull();
    expect(closed).toHaveBeenCalled();
  });

  it('stops listening once disconnected', async () => {
    await mount();
    application.stop();
    vi.stubGlobal('fetch', vi.fn());
    document.getElementById('add')!.click();
    expect(dialog().open).toBe(false);
  });

  it('falls back to the default header names when a value is emptied', async () => {
    await mount('data-remote-form-dialog-request-header-value="" data-remote-form-dialog-redirect-header-value=""');
    const fetchMock = vi.fn().mockResolvedValue(reply(200, '<p>x</p>'));
    vi.stubGlobal('fetch', fetchMock);
    document.getElementById('add')!.click();
    await tick();
    expect((fetchMock.mock.calls[0] as [string, RequestInit])[1].headers).toMatchObject({ 'X-Nowo-Dialog': '1' });
  });
});
