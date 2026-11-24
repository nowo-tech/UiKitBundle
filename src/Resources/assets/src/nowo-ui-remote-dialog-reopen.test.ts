/**
 * 2.73: the client end of the direct-GET contract (nowo-ui-remote-dialog-reopen.ts) — safe list, same-origin target,
 * history clean-up — and its use by the IIFE runtime (reopenRemoteForms()) and the remote-form-dialog peer.
 */
import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import RemoteFormDialogController from '../stimulus-peers/remote_form_dialog_controller';
import { reopenRemoteForms } from './nowo-ui-dialog';
import {
  REOPENED_ATTR,
  reopenFromLocation,
  reopenOpener,
  reopenParameter,
  reopenTarget,
  withoutReopenParameter,
} from './nowo-ui-remote-dialog-reopen';
import { remoteNavigation } from './nowo-ui-remote-form-core';

const tick = (): Promise<void> => new Promise((r) => setTimeout(r, 0));

const reply = (status: number, body = '', headers: Record<string, string> = { 'content-type': 'text/html' }): Response =>
  ({ status, type: 'basic', ok: status < 300, headers: new Headers(headers), text: () => Promise.resolve(body) }) as unknown as Response;

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

const ATTRS = {
  'data-nowo-ui-remote-form-reopen-param': '_dialog',
  'data-nowo-ui-remote-form-reopen-routes': 'crm_import_mapping crm_import_upload',
  'data-nowo-ui-remote-form-reopen-route': 'crm_import_mapping',
  'data-nowo-ui-remote-form-reopen-url': '/import/42/mapping',
};

function dialogWith(attrs: Record<string, string> = ATTRS): HTMLDialogElement {
  const dialog = document.createElement('dialog');
  dialog.id = 'import-dialog';
  for (const [name, value] of Object.entries(attrs)) {
    dialog.setAttribute(name, value);
  }
  return dialog;
}

const QUERY = '?tab=all&_dialog%5Broute%5D=crm_import_mapping&_dialog%5Bparams%5D%5Bid%5D=42';

describe('reopen target', () => {
  it('accepts a safe-listed route that the server resolved, and opens the server URL', () => {
    expect(reopenParameter(dialogWith())).toBe('_dialog');
    expect(reopenTarget(dialogWith(), QUERY)).toBe('/import/42/mapping');
  });

  it('ignores a dialog without the contract, an invalid parameter name or a query without a route', () => {
    expect(reopenTarget(dialogWith({}), QUERY)).toBeNull();
    expect(reopenTarget(dialogWith({ ...ATTRS, 'data-nowo-ui-remote-form-reopen-param': 'x[y]' }), QUERY)).toBeNull();
    expect(reopenTarget(dialogWith(), '?tab=all')).toBeNull();
    expect(reopenTarget(dialogWith(), '?_dialog%5Broute%5D=')).toBeNull();
  });

  it('rejects a route that is not in the safe list, even when the server attribute names it', () => {
    const forged = dialogWith({ ...ATTRS, 'data-nowo-ui-remote-form-reopen-route': 'admin_delete' });
    expect(reopenTarget(forged, '?_dialog%5Broute%5D=admin_delete')).toBeNull();
    expect(reopenTarget(dialogWith(), '?_dialog%5Broute%5D=admin_delete')).toBeNull();
  });

  it('rejects a safe-listed route the server did not resolve (other route, unknown parameters)', () => {
    expect(reopenTarget(dialogWith(), '?_dialog%5Broute%5D=crm_import_upload')).toBeNull();
    const { 'data-nowo-ui-remote-form-reopen-route': _route, 'data-nowo-ui-remote-form-reopen-url': _url, ...listOnly } = ATTRS;
    expect(reopenTarget(dialogWith(listOnly), QUERY)).toBeNull();
    const { 'data-nowo-ui-remote-form-reopen-routes': _routes, ...noList } = ATTRS;
    expect(reopenTarget(dialogWith(noList), QUERY)).toBeNull();
  });

  it('never opens another origin, a protocol-relative URL or a javascript: URL', () => {
    for (const url of ['https://evil.example/x', '//evil.example/x', 'javascript:alert(1)', '/.//evil.example', '/a\\b', '']) {
      expect(reopenTarget(dialogWith({ ...ATTRS, 'data-nowo-ui-remote-form-reopen-url': url }), QUERY)).toBeNull();
    }
  });

  it('removes the reopen parameter and its nested keys, keeping the rest of the URL', () => {
    expect(withoutReopenParameter(`/import${QUERY}#list`, '_dialog')).toBe('/import?tab=all#list');
    expect(withoutReopenParameter('/import?_dialog=x&_dialogue=1', '_dialog')).toBe('/import?_dialogue=1');
    expect(withoutReopenParameter('/import?_dialog%5Broute%5D=x', '_dialog')).toBe('/import');
  });
});

describe('reopenFromLocation', () => {
  beforeEach(() => {
    window.history.replaceState({ kept: true }, '', `/import${QUERY}`);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    window.history.replaceState(null, '', '/');
    document.body.innerHTML = '';
  });

  it('opens the dialog once and cleans the history entry, keeping its state', () => {
    const dialog = dialogWith();
    const open = vi.fn(() => true);
    expect(reopenFromLocation(dialog, open)).toBe(true);
    expect(open).toHaveBeenCalledWith('/import/42/mapping');
    expect(window.location.pathname + window.location.search).toBe('/import?tab=all');
    expect(window.history.state).toEqual({ kept: true });
    expect(dialog.hasAttribute(REOPENED_ATTR)).toBe(true);
    expect(reopenFromLocation(dialog, open)).toBe(false);
    expect(open).toHaveBeenCalledTimes(1);
  });

  it('keeps the URL when the dialog could not open, and may try again', () => {
    const dialog = dialogWith();
    expect(reopenFromLocation(dialog, () => false)).toBe(false);
    expect(window.location.search).toBe(QUERY);
    expect(dialog.hasAttribute(REOPENED_ATTR)).toBe(false);
  });

  it('ignores a foreign route: nothing opens and the URL stays', () => {
    window.history.replaceState(null, '', '/import?_dialog%5Broute%5D=evil');
    const open = vi.fn(() => true);
    expect(reopenFromLocation(dialogWith(), open)).toBe(false);
    expect(open).not.toHaveBeenCalled();
    expect(window.location.search).toBe('?_dialog%5Broute%5D=evil');
  });

  it('survives a history that refuses replaceState', () => {
    vi.spyOn(window.history, 'replaceState').mockImplementation(() => {
      throw new DOMException('denied', 'SecurityError');
    });
    expect(reopenFromLocation(dialogWith(), () => true)).toBe(true);
  });

  it('finds the page opener of that dialog and URL for the title and focus return', () => {
    document.body.innerHTML = `
      <a id="other" data-nowo-ui-dialog-open="other-dialog" data-nowo-ui-dialog-src="/import/42/mapping">x</a>
      <a id="mine" data-nowo-ui-dialog-open="import-dialog" data-nowo-ui-dialog-src="/import/42/mapping">y</a>`;
    expect(reopenOpener(dialogWith(), '/import/42/mapping')?.id).toBe('mine');
    expect(reopenOpener(dialogWith(), '/import/7/mapping')).toBeNull();
    const anonymous = dialogWith();
    anonymous.removeAttribute('id');
    expect(reopenOpener(anonymous, '/import/42/mapping')).toBeNull();
  });
});

describe('IIFE runtime (reopenRemoteForms)', () => {
  const markup = (extra = ''): string => `
    <a id="opener" href="/import/42/mapping" data-nowo-ui-dialog-open="import-dialog" data-nowo-ui-dialog-src="/import/42/mapping"
       data-nowo-ui-dialog-form data-nowo-ui-dialog-title="Map columns">Map</a>
    <dialog id="import-dialog" data-nowo-ui-dialog data-nowo-ui-remote-form ${extra}
      ${Object.entries(ATTRS).map(([name, value]) => `${name}="${value}"`).join(' ')}>
      <h2 id="import-dialog-title" tabindex="-1">Import</h2>
      <div data-nowo-ui-dialog-body></div>
    </dialog>`;

  beforeEach(() => {
    window.history.replaceState(null, '', `/import${QUERY}`);
    vi.spyOn(remoteNavigation, 'assign').mockImplementation(() => undefined);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    window.history.replaceState(null, '', '/');
    document.body.innerHTML = '';
  });

  it('loads the server URL with the dialog header, titles it from the opener and cleans the URL', async () => {
    document.body.innerHTML = markup();
    const dialog = document.getElementById('import-dialog') as HTMLDialogElement;
    polyfill(dialog);
    const fetchMock = vi.fn().mockResolvedValue(reply(200, '<form method="post"><input name="a"></form>'));
    vi.stubGlobal('fetch', fetchMock);
    reopenRemoteForms();
    await tick();
    expect(dialog.open).toBe(true);
    const [url, init] = fetchMock.mock.calls[0] as unknown as [string, RequestInit];
    expect(url).toBe(`${window.location.origin}/import/42/mapping`);
    expect((init.headers as Record<string, string>)['X-Nowo-Dialog']).toBe('1');
    expect(document.getElementById('import-dialog-title')?.textContent).toBe('Map columns');
    expect(window.location.search).toBe('?tab=all');
    dialog.close();
    expect(window.location.search).toBe('?tab=all');
  });

  it('shows the alert instead of following the dialog URL when the load fails (no bounce)', async () => {
    document.body.innerHTML = markup();
    const dialog = document.getElementById('import-dialog') as HTMLDialogElement;
    polyfill(dialog);
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply(500, 'boom')));
    reopenRemoteForms();
    await tick();
    expect(remoteNavigation.assign).not.toHaveBeenCalled();
    expect(dialog.querySelector('[role="alert"]')).not.toBeNull();
  });

  it('leaves a dialog that a Stimulus peer drives to the peer', () => {
    document.body.innerHTML = markup('data-remote-form-dialog-target="dialog"');
    const fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
    reopenRemoteForms();
    expect(fetchMock).not.toHaveBeenCalled();
    expect(window.location.search).toBe(QUERY);
  });
});

describe('remote-form-dialog peer', () => {
  let application: Application | undefined;

  afterEach(() => {
    application?.stop();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    window.history.replaceState(null, '', '/');
    document.body.innerHTML = '';
  });

  it('reopens its dialog on connect and cleans the URL', async () => {
    window.history.replaceState(null, '', `/import${QUERY}`);
    document.body.innerHTML = `
      <section data-controller="remote-form-dialog">
        <dialog id="import-dialog" data-remote-form-dialog-target="dialog"
          ${Object.entries(ATTRS).map(([name, value]) => `${name}="${value}"`).join(' ')}>
          <h2 data-remote-form-dialog-target="title" tabindex="-1">Import</h2>
          <div data-remote-form-dialog-target="body" data-action="submit->remote-form-dialog#submit"></div>
        </dialog>
      </section>`;
    polyfill(document.querySelector('dialog') as HTMLDialogElement);
    const fetchMock = vi.fn().mockResolvedValue(reply(200, '<p>step</p>'));
    vi.stubGlobal('fetch', fetchMock);
    application = Application.start();
    application.register('remote-form-dialog', RemoteFormDialogController);
    await tick();
    await tick();
    expect((document.querySelector('dialog') as HTMLDialogElement).open).toBe(true);
    expect(fetchMock.mock.calls[0]?.[0]).toBe(`${window.location.origin}/import/42/mapping`);
    expect(document.querySelector('h2')?.textContent).toBe('Import');
    expect(window.location.search).toBe('?tab=all');
  });
});
