/**
 * 2.68 — `confirm_dialog` on demand (`dialog_src`): the page holds only the opener link and an empty remote form
 * dialog shell (tests/Fixtures/dialog-src/confirm-dialog-src.html, rendered by the Twig partial and checked by
 * ConfirmDialogSrc268Test). The body is fetched when the dialog opens, a 422 is re-rendered in place, and a failed
 * load either follows the link (default, the no-JS path) or shows the load error.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as mod from './nowo-ui-dialog';
import { remoteNavigation } from './nowo-ui-remote-form-core';
import FIXTURE from '../../../../tests/Fixtures/dialog-src/confirm-dialog-src.html?raw';

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

const tick = (): Promise<void> => new Promise((r) => setTimeout(r, 0));
const reply = (status: number, body = '', headers: Record<string, string> = { 'content-type': 'text/html; charset=UTF-8' }): Response =>
  ({ status, type: 'basic', ok: status < 300, headers: new Headers(headers), text: () => Promise.resolve(body) }) as unknown as Response;

function click(el: Element): MouseEvent {
  const event = new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 });
  el.dispatchEvent(event);
  return event;
}

const FORM = '<form method="post" action="/members/1/edit"><input name="name" id="name"><div class="nowo-ui-form__actions"><button type="submit" id="save">Save</button></div></form>';
const INVALID =
  '<form method="post" action="/members/1/edit"><input name="name" id="name" aria-invalid="true" value="x"><p id="name-error">Too short</p><div class="nowo-ui-form__actions"><button type="submit" id="save">Save</button></div></form>';

describe('confirm_dialog dialog_src (2.68)', () => {
  let opener: HTMLAnchorElement;
  let dialog: HTMLDialogElement;
  let body: HTMLElement;

  beforeEach(() => {
    document.body.innerHTML = FIXTURE;
    dialog = document.getElementById('edit-1') as HTMLDialogElement;
    polyfill(dialog);
    opener = document.querySelector('[data-testid="edit-open"]') as HTMLAnchorElement;
    body = dialog.querySelector('[data-nowo-ui-dialog-body]') as HTMLElement;
    mod.bindNowoUiDialog();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    window.NowoUiOverlay?.releaseAll();
    document.body.innerHTML = '';
  });

  it('ships no body: the opener is a link to the page and the shell is empty', () => {
    expect(opener.tagName).toBe('A');
    expect(opener.getAttribute('href')).toBe('/members/1/edit');
    expect(opener.getAttribute('data-nowo-ui-dialog-src')).toBe(opener.getAttribute('href'));
    expect(body.children.length).toBe(0);
    expect(document.querySelector('form')).toBeNull();
    expect(dialog.open).toBe(false);
  });

  it('fetches the fragment on open, busy while loading, with the dialog request header', async () => {
    let resolveFetch: (r: Response) => void = () => {};
    const fetchMock = vi.fn(() => new Promise<Response>((r) => (resolveFetch = r)));
    vi.stubGlobal('fetch', fetchMock);

    const event = click(opener);
    expect(event.defaultPrevented).toBe(true);
    expect(dialog.open).toBe(true);
    expect(document.getElementById('edit-1-title')?.textContent).toBe('Edit member');
    expect(body.getAttribute('aria-busy')).toBe('true');
    expect(fetchMock).toHaveBeenCalledTimes(1);
    const [url, init] = fetchMock.mock.calls[0] as unknown as [string, RequestInit];
    expect(new URL(url, location.href).pathname).toBe('/members/1/edit');
    expect(new Headers(init.headers).get('X-Nowo-Dialog')).toBe('1');
    expect(init.mode).toBe('same-origin');

    resolveFetch(reply(200, FORM));
    for (let i = 0; i < 4; i += 1) await tick();
    expect(body.hasAttribute('aria-busy')).toBe(false);
    expect(body.querySelector('#name')).not.toBeNull();
  });

  it('answers a 422 in place: re-rendered, still open, focus on the invalid field', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply(200, FORM)));
    click(opener);
    for (let i = 0; i < 4; i += 1) await tick();

    const assign = vi.spyOn(remoteNavigation, 'assign').mockImplementation(() => {});
    const invalid = vi.fn();
    dialog.addEventListener('nowo-ui:remote-form:invalid', invalid);
    const post = vi.fn().mockResolvedValue(reply(422, INVALID));
    vi.stubGlobal('fetch', post);
    body.querySelector('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    for (let i = 0; i < 6; i += 1) await tick();

    expect((post.mock.calls[0] as unknown as [string, RequestInit])[1].method).toBe('POST');
    expect(dialog.open).toBe(true);
    expect(body.querySelector('#name-error')?.textContent).toBe('Too short');
    expect(document.activeElement?.id).toBe('name');
    expect(invalid).toHaveBeenCalled();
    expect(assign).not.toHaveBeenCalled();
  });

  it('follows the opener link when the load fails (default)', async () => {
    const assign = vi.spyOn(remoteNavigation, 'assign').mockImplementation(() => {});
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply(500, 'boom')));
    click(opener);
    for (let i = 0; i < 4; i += 1) await tick();
    expect(assign).toHaveBeenCalledWith('/members/1/edit');
    expect(dialog.open).toBe(false);
  });

  it('shows the load error in the dialog with follow_on_load_error false', async () => {
    dialog.setAttribute('data-nowo-ui-remote-form-follow-on-load-error', 'false');
    const assign = vi.spyOn(remoteNavigation, 'assign').mockImplementation(() => {});
    const failed = vi.fn();
    dialog.addEventListener('nowo-ui:remote-form:failed', failed);
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('network')));
    click(opener);
    for (let i = 0; i < 4; i += 1) await tick();
    expect(assign).not.toHaveBeenCalled();
    expect(dialog.open).toBe(true);
    expect(body.hasAttribute('aria-busy')).toBe(false);
    const alert = body.querySelector('[role="alert"]');
    expect(alert?.textContent).toBe('The form could not be loaded.');
    expect(failed).toHaveBeenCalled();
  });

  it('empties the body again on close and returns focus to the opener', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply(200, FORM)));
    opener.focus();
    click(opener);
    for (let i = 0; i < 4; i += 1) await tick();
    expect(body.querySelector('form')).not.toBeNull();
    mod.closeDialog(dialog);
    for (let i = 0; i < 4; i += 1) await tick();
    expect(dialog.open).toBe(false);
    expect(body.querySelector('form')).toBeNull();
    expect(document.activeElement).toBe(opener);
  });
});
