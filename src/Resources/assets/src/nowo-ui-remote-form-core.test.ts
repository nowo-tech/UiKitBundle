import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  ALERT_ATTR,
  DEFAULT_SETTINGS,
  firstInvalid,
  formDataWith,
  hasUnsafeSegments,
  isFragment,
  isPlainClick,
  nestedLayerOwnsEscape,
  openerTitle,
  parseFragment,
  remoteNavigation,
  RemoteFormSession,
  sameOriginHref,
  sameOriginPath,
  submitTarget,
  urlEncoded,
  type RemoteFormSettings,
} from './nowo-ui-remote-form-core';

const tick = (): Promise<void> => new Promise((r) => setTimeout(r, 0));

export function polyfill(dialog: HTMLDialogElement): void {
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

type Reply = { status?: number; type?: string; headers?: Record<string, string>; body?: string };

export function reply({ status = 200, type = 'basic', headers = { 'content-type': 'text/html; charset=UTF-8' }, body = '' }: Reply = {}): Response {
  return { status, type, ok: status >= 200 && status < 300, headers: new Headers(headers), text: () => Promise.resolve(body) } as unknown as Response;
}

describe('remote form helpers', () => {
  it('accepts only same-origin path-absolute redirect targets', () => {
    expect(sameOriginPath('/a?b=1#c')).toBe('/a?b=1#c');
    expect(sameOriginPath('/')).toBe('/');
    for (const bad of [null, undefined, '', '//evil.test/x', '/\\evil.test', '/a\\b', 'https://evil.test/', `${window.location.origin}/same`, 'javascript:alert(1)', 'a/relative', '/a\u0000b', '/a\nb', `/${'x'.repeat(2048)}`]) {
      expect(sameOriginPath(bad)).toBeNull();
    }
    expect(sameOriginPath('/%0d%0a')).toBe('/%0d%0a');
  });

  it('refuses dot segments that would normalise to another origin (REQ-SEC-004 ES-M1)', () => {
    for (const dotted of [
      '/.//evil.example/x',
      '/..//evil.example/x',
      '/%2e//evil.example/x',
      '/%2E%2E//evil.example/x',
      '/a/..//evil.example/x',
      '/%2e%2E//evil.example/x',
      '/.%2e//evil.example/x',
      '/a/%2E/b',
      '/a/./b',
      '/a/..',
      '/.',
      '/%252e//evil.example/x',
      '/%2F%2Fevil.example',
      '/a%5c..%5cb',
      '/./?x=1',
      '/%E0%A4%A',
      '/files/a%2Eb',
      '/x%2etxt',
    ]) {
      expect(sameOriginPath(dotted), dotted).toBeNull();
    }
    expect(hasUnsafeSegments('/a/b')).toBe(false);
    expect(sameOriginPath('/files/a..b/.well-known/x.y')).toBe('/files/a..b/.well-known/x.y');
    expect(sameOriginPath('/x?next=/../evil#..')).toBe('/x?next=/../evil#..');
  });

  it('strips what could run or navigate from a fragment', () => {
    const fragment = parseFragment(`<form onsubmit="x()"><input onfocus="y()" name="a"></form><iframe srcdoc="<script>1</script>"></iframe>
      <iframe src="/frame"></iframe><meta http-equiv="refresh" content="0;url=https://evil.example"><base href="https://evil.example/">
      <object data="x"></object><embed src="x"><img src="x" ONERROR="z()">`);
    expect(fragment.querySelector('iframe[srcdoc], meta, base, object, embed')).toBeNull();
    expect(fragment.querySelector('iframe[src="/frame"]')).not.toBeNull();
    expect(fragment.querySelector('form')?.hasAttribute('onsubmit')).toBe(false);
    expect(fragment.querySelector('input')?.hasAttribute('onfocus')).toBe(false);
    expect(fragment.querySelector('img')?.attributes.length).toBe(1);
  });

  it('resolves submit targets and data like a native submit (ES-L2, ES-I1)', () => {
    document.body.innerHTML = `<form id="f" method="post" action="/save"><input type="hidden" name="op" value="hidden">
      <button id="plain" type="submit">Save</button>
      <button id="again" type="submit" name="op" value="again">Save + new</button>
      <button id="del" type="submit" name="del" value="1" formaction="/delete" formmethod="post" formenctype="application/x-www-form-urlencoded">Delete</button>
      <button id="get" type="submit" formmethod="get">Search</button></form>
      <form id="bare" method="post"></form>`;
    const form = document.getElementById('f') as HTMLFormElement;
    const button = (id: string): HTMLElement => document.getElementById(id) as HTMLElement;
    expect(submitTarget(form, button('plain'), '/dialog')).toMatchObject({ action: `${window.location.origin}/save`, method: 'post' });
    expect(submitTarget(form, button('del'), '/dialog')).toMatchObject({ action: `${window.location.origin}/delete`, method: 'post', enctype: 'application/x-www-form-urlencoded' });
    expect(submitTarget(form, button('get'), '/dialog').method).toBe('get');
    expect(submitTarget(document.getElementById('bare') as HTMLFormElement, null, '/dialog').action).toBe('/dialog');
    expect(formDataWith(form, button('again')).getAll('op')).toEqual(['hidden', 'again']);
    expect(formDataWith(form, button('plain')).getAll('op')).toEqual(['hidden']);
    expect(formDataWith(form, null).getAll('op')).toEqual(['hidden']);
    const encoded = urlEncoded(formDataWith(form, button('del')));
    expect(encoded.get('del')).toBe('1');
    const withFile = new FormData();
    withFile.append('file', new File(['x'], 'a.txt'));
    expect(urlEncoded(withFile).get('file')).toBe('a.txt');
  });

  it('falls back when FormData ignores or refuses the submitter', () => {
    document.body.innerHTML = '<form id="f"><button id="b" name="go" value="1">Go</button></form>';
    const Native = window.FormData;
    class NoSubmitter extends Native {
      constructor(form?: HTMLFormElement, submitter?: HTMLElement | null) {
        if (submitter) throw new TypeError('unsupported');
        super(form);
      }
    }
    vi.stubGlobal('FormData', NoSubmitter);
    try {
      expect(formDataWith(document.getElementById('f') as HTMLFormElement, document.getElementById('b')).getAll('go')).toEqual(['1']);
    } finally {
      vi.unstubAllGlobals();
    }
  });

  it('resolves fetch targets on this origin only', () => {
    expect(sameOriginHref('/form')).toBe(`${window.location.origin}/form`);
    expect(sameOriginHref('https://evil.test/form')).toBeNull();
    expect(sameOriginHref('javascript:alert(1)')).toBeNull();
    expect(sameOriginHref('  ')).toBeNull();
    expect(sameOriginHref(null)).toBeNull();
    expect(sameOriginHref('http://[::1')).toBeNull();
  });

  it('tells fragments from whole documents and non-HTML answers', () => {
    expect(isFragment('text/html; charset=UTF-8', '<form></form>')).toBe(true);
    expect(isFragment('text/html', '<!DOCTYPE html><html></html>')).toBe(false);
    expect(isFragment('text/html', '  <HTML lang="en">')).toBe(false);
    expect(isFragment('application/json', '{}')).toBe(false);
    expect(isFragment(null, '<p></p>')).toBe(false);
  });

  it('parses in an inert template and drops scripts', () => {
    const fragment = parseFragment('<p id="x">a</p><script>window.__evil = 1</script>');
    expect(fragment.querySelector('script')).toBeNull();
    expect(fragment.querySelector('#x')?.textContent).toBe('a');
  });

  it('finds the first invalid control, then the host selector', () => {
    document.body.innerHTML = '<div id="r"><div class="err"><input id="b"></div><input id="a" aria-invalid="true"></div>';
    const root = document.getElementById('r') as HTMLElement;
    expect(firstInvalid(root)?.id).toBe('a');
    root.querySelector('#a')?.removeAttribute('aria-invalid');
    expect(firstInvalid(root)).toBeNull();
    expect(firstInvalid(root, '.err input')?.id).toBe('b');
    expect(firstInvalid(root, '[[bad')).toBeNull();
  });

  it('reads opener titles and plain clicks', () => {
    const a = document.createElement('a');
    a.textContent = '  Add  ';
    expect(openerTitle(a, undefined)).toBe('Add');
    a.setAttribute('aria-label', 'Add destination');
    expect(openerTitle(a, undefined)).toBe('Add destination');
    expect(openerTitle(a, 'Explicit')).toBe('Explicit');
    expect(isPlainClick(new Event('click'))).toBe(true);
    expect(isPlainClick(new MouseEvent('click', { button: 0 }))).toBe(true);
    expect(isPlainClick(new MouseEvent('click', { button: 0, ctrlKey: true }))).toBe(false);
    expect(isPlainClick(new MouseEvent('click', { button: 1 }))).toBe(false);
  });

  it('lets nested layers own Escape', () => {
    document.body.innerHTML = `<dialog id="d"><div class="ts-wrapper dropdown-active"><input id="ts"></div><input id="plain">
      <dialog id="inner" open><input id="in"></dialog></dialog><input id="out">`;
    const dialog = document.getElementById('d') as HTMLDialogElement;
    expect(nestedLayerOwnsEscape(dialog, document.getElementById('ts'))).toBe(true);
    expect(nestedLayerOwnsEscape(dialog, document.getElementById('plain'))).toBe(false);
    expect(nestedLayerOwnsEscape(dialog, document.getElementById('in'))).toBe(true);
    expect(nestedLayerOwnsEscape(dialog, document.getElementById('out'))).toBe(false);
    expect(nestedLayerOwnsEscape(dialog, null)).toBe(false);
  });
});

describe('RemoteFormSession', () => {
  let dialog: HTMLDialogElement;
  let session: RemoteFormSession;
  let settings: RemoteFormSettings;
  let events: Array<[string, Record<string, unknown>, boolean]>;
  let prevent: Set<string>;
  let assign: ReturnType<typeof vi.spyOn>;
  let reload: ReturnType<typeof vi.spyOn>;
  const body = (): HTMLElement => dialog.querySelector('[data-body]') as HTMLElement;
  const opener = (): HTMLElement => document.getElementById('opener') as HTMLElement;

  const submitForm = async (form: HTMLFormElement, submitter: HTMLElement | null = null): Promise<Event> => {
    const event = new Event('submit', { bubbles: true, cancelable: true }) as SubmitEvent;
    Object.defineProperty(event, 'submitter', { value: submitter });
    form.dispatchEvent(event);
    await tick();
    await tick();
    return event;
  };

  const openWith = async (response: Response, href = '/form'): Promise<boolean> => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(response));
    const opened = session.open(href, opener(), 'Edit');
    await tick();
    await tick();
    return opened;
  };

  beforeEach(() => {
    document.body.innerHTML = `<a id="opener" href="/form">Open</a>
      <dialog id="d"><h2 id="d-title"></h2><div data-body></div></dialog>`;
    dialog = document.getElementById('d') as HTMLDialogElement;
    polyfill(dialog);
    settings = { ...DEFAULT_SETTINGS, loading: 'Loading', error: 'Failed', loadError: 'Load failed' };
    events = [];
    prevent = new Set();
    session = new RemoteFormSession(
      dialog,
      () => document.getElementById('d-title'),
      body,
      () => settings,
      (name, detail, cancelable = false) => {
        events.push([name, detail, cancelable]);
        return !(cancelable && prevent.has(name));
      },
    );
    session.bind();
    assign = vi.spyOn(remoteNavigation, 'assign').mockImplementation(() => undefined);
    reload = vi.spyOn(remoteNavigation, 'reload').mockImplementation(() => undefined);
  });

  afterEach(() => {
    session.destroy();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    document.body.innerHTML = '';
    window.NowoUiOverlay?.releaseAll();
  });

  it('opens, sends the dialog headers, injects the fragment and focuses [autofocus]', async () => {
    const fetchMock = vi.fn().mockResolvedValue(reply({ body: '<form method="post" action="/form"><input id="f" autofocus></form>' }));
    vi.stubGlobal('fetch', fetchMock);
    expect(session.open('/form', opener(), 'Edit')).toBe(true);
    expect(dialog.open).toBe(true);
    expect(document.getElementById('d-title')?.textContent).toBe('Edit');
    expect(document.getElementById('d-title')?.getAttribute('tabindex')).toBe('-1');
    expect(body().getAttribute('aria-busy')).toBe('true');
    expect(body().querySelector('[role="status"]')?.textContent).toBe('Loading');
    expect(document.documentElement.getAttribute('data-nowo-overlay')).toBe('1');
    await tick();
    await tick();
    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe(`${window.location.origin}/form`);
    expect(init).toMatchObject({ credentials: 'same-origin', mode: 'same-origin', redirect: 'manual' });
    expect(init.headers).toMatchObject({ 'X-Nowo-Dialog': '1', 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' });
    expect(body().hasAttribute('aria-busy')).toBe(false);
    expect(document.activeElement?.id).toBe('f');
    expect(events[0]).toEqual(['loaded', { url: `${window.location.origin}/form` }, false]);
    expect(session.currentUrl()).toBe(`${window.location.origin}/form`);
  });

  it('uses renamed headers', async () => {
    settings.requestHeader = 'X-Beacon-Dialog';
    const fetchMock = vi.fn().mockResolvedValue(reply({ body: '<p>x</p>' }));
    vi.stubGlobal('fetch', fetchMock);
    session.open('/form', opener(), 'Edit');
    await tick();
    expect((fetchMock.mock.calls[0] as [string, RequestInit])[1].headers).toMatchObject({ 'X-Beacon-Dialog': '1' });
  });

  it('does nothing for cross-origin openers, missing bodies or dialogs without showModal', () => {
    expect(session.open('https://evil.test/form', opener(), 'x')).toBe(false);
    expect(session.open(null, opener(), 'x')).toBe(false);
    (dialog as unknown as { showModal: unknown }).showModal = undefined;
    expect(session.supported()).toBe(false);
    expect(session.open('/form', opener(), 'x')).toBe(false);
    expect(dialog.open).toBe(false);
  });

  it('follows the link when the load fails (status, redirect, whole document, network)', async () => {
    for (const response of [reply({ status: 500 }), reply({ status: 0, type: 'opaqueredirect' }), reply({ body: '<!doctype html><html></html>' })]) {
      assign.mockClear();
      await openWith(response);
      expect(assign).toHaveBeenCalledWith('/form');
      expect(dialog.open).toBe(false);
    }
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));
    assign.mockClear();
    session.open('/form', opener(), 'Edit');
    await tick();
    await tick();
    expect(assign).toHaveBeenCalledWith('/form');
    expect(events.filter(([name]) => name === 'failed')[0]?.[1]).toEqual({ stage: 'load', status: null });
  });

  it('keeps the dialog open with the load alert when followOnLoadError is false', async () => {
    settings.followOnLoadError = false;
    await openWith(reply({ status: 404 }));
    expect(assign).not.toHaveBeenCalled();
    expect(dialog.open).toBe(true);
    expect(body().querySelector(`[${ALERT_ATTR}="load"]`)?.textContent).toBe('Load failed');
    settings.loadError = '';
    await openWith(reply({ status: 404 }));
    expect(body().querySelector(`[${ALERT_ATTR}]`)?.textContent).toBe('Failed');
  });

  it('ignores a late answer after close (abort) and never navigates', async () => {
    let resolve: (r: Response) => void = () => undefined;
    vi.stubGlobal('fetch', vi.fn().mockImplementation(() => new Promise<Response>((r) => (resolve = r))));
    session.open('/form', opener(), 'Edit');
    session.close();
    resolve(reply({ status: 500 }));
    await tick();
    expect(assign).not.toHaveBeenCalled();
    expect(body().childNodes.length).toBe(0);
    expect(document.activeElement).toBe(opener());
    expect(events[events.length - 1]?.[0]).toBe('closed');
    expect(document.documentElement.hasAttribute('data-nowo-overlay')).toBe(false);
  });

  it('ignores a late successful load after close', async () => {
    let resolve: (r: Response) => void = () => undefined;
    vi.stubGlobal('fetch', vi.fn().mockImplementation(() => new Promise<Response>((r) => (resolve = r))));
    session.open('/form', opener(), 'Edit');
    session.close();
    resolve(reply({ body: '<p id="late">late</p>' }));
    await tick();
    expect(document.getElementById('late')).toBeNull();
  });

  describe('submit', () => {
    const form = (): HTMLFormElement => body().querySelector('form') as HTMLFormElement;

    beforeEach(async () => {
      await openWith(reply({ body: '<form method="post" action="/form"><input name="name" value="A"><button type="submit" name="save" value="again" id="again">Save + new</button><button type="submit" id="save">Save</button></form>' }));
    });

    it('posts FormData with the submitter, then follows a valid redirect header', async () => {
      const fetchMock = vi.fn().mockResolvedValue(reply({ status: 204, headers: { 'X-Nowo-Redirect': '/settings?tab=alerts' } }));
      vi.stubGlobal('fetch', fetchMock);
      const event = await submitForm(form(), document.getElementById('again'));
      expect(event.defaultPrevented).toBe(true);
      const init = (fetchMock.mock.calls[0] as [string, RequestInit])[1];
      expect(init).toMatchObject({ method: 'POST', mode: 'same-origin', redirect: 'manual', credentials: 'same-origin' });
      const data = init.body as FormData;
      expect(data.get('name')).toBe('A');
      expect(data.get('save')).toBe('again');
      expect(assign).toHaveBeenCalledWith('/settings?tab=alerts');
      expect(dialog.open).toBe(false);
      expect(events.find(([name]) => name === 'saved')).toEqual(['saved', { redirect: '/settings?tab=alerts', status: 204 }, true]);
    });

    it('reloads on 204 without a header, on an invalid header and on a 3xx', async () => {
      for (const response of [reply({ status: 204, headers: {} }), reply({ status: 204, headers: { 'X-Nowo-Redirect': 'https://evil.test/' } }), reply({ status: 0, type: 'opaqueredirect' })]) {
        await openWith(reply({ body: '<form method="post" action="/form"></form>' }));
        reload.mockClear();
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(response));
        await submitForm(form());
        expect(reload).toHaveBeenCalledTimes(1);
        expect(assign).not.toHaveBeenCalled();
        warn.mockRestore();
      }
    });

    it('follows a valid redirect header on a 2xx answer only (ES-I5)', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply({ status: 200, headers: { 'X-Nowo-Redirect': '/done' } })));
      await submitForm(form());
      expect(assign).toHaveBeenCalledWith('/done');
      assign.mockClear();
      await openWith(reply({ body: '<form method="post" action="/form"></form>' }));
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply({ status: 500, headers: { 'X-Nowo-Redirect': '/done' } })));
      await submitForm(form());
      expect(assign).not.toHaveBeenCalled();
      expect(body().querySelector('[role="alert"]')).not.toBeNull();
    });

    it('never navigates off-origin through a dot-segment redirect (ES-M1)', async () => {
      const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
      for (const dotted of ['/.//evil.example/x', '/..//evil.example/x', '/%2e//evil.example/x', '/%2E%2E//evil.example/x', '/a/..//evil.example/x']) {
        await openWith(reply({ body: '<form method="post" action="/form"></form>' }));
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply({ status: 204, headers: { 'X-Nowo-Redirect': dotted } })));
        await submitForm(form());
      }
      expect(assign).not.toHaveBeenCalled();
      expect(reload).toHaveBeenCalledTimes(5);
      warn.mockRestore();
    });

    it('honours the submitter: formaction, formmethod and the posted value (ES-L2)', async () => {
      body().innerHTML = `<form method="post" action="/form"><input type="hidden" name="op" value="hidden">
        <button id="again" type="submit" name="op" value="again">Again</button></form>`;
      const fetchMock = vi.fn().mockResolvedValue(reply({ status: 204, headers: {} }));
      vi.stubGlobal('fetch', fetchMock);
      await submitForm(form(), document.getElementById('again'));
      expect(((fetchMock.mock.calls[0] as [string, RequestInit])[1].body as FormData).getAll('op')).toEqual(['hidden', 'again']);
      await openWith(reply({ body: '<form method="post" action="/form"><button id="del" type="submit" name="del" value="1" formaction="/delete" formenctype="application/x-www-form-urlencoded">Delete</button><button id="ext" type="submit" formaction="https://evil.test/x">Ext</button><button id="get" type="submit" formmethod="get">Get</button><button id="pt" type="submit" formenctype="text/plain">T</button></form>' }));
      fetchMock.mockClear();
      vi.stubGlobal('fetch', fetchMock);
      await submitForm(form(), document.getElementById('del'));
      const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
      expect(url).toBe(`${window.location.origin}/delete`);
      expect((init.body as URLSearchParams).get('del')).toBe('1');
      await openWith(reply({ body: '<form method="post" action="/form"><button id="ext" type="submit" formaction="https://evil.test/x">Ext</button><button id="get" type="submit" formmethod="get">Get</button><button id="pt" type="submit" formenctype="text/plain">T</button></form>' }));
      for (const id of ['ext', 'get', 'pt']) {
        const event = await submitForm(form(), document.getElementById(id));
        expect(event.defaultPrevented, id).toBe(false);
      }
    });

    it('posts an action-less form to the URL loaded in the dialog, not the host page (ES-I1)', async () => {
      await openWith(reply({ body: '<form method="post"><button type="submit">Save</button></form>' }), '/dialog/form');
      const fetchMock = vi.fn().mockResolvedValue(reply({ status: 204, headers: {} }));
      vi.stubGlobal('fetch', fetchMock);
      await submitForm(form());
      expect((fetchMock.mock.calls[0] as [string, RequestInit])[0]).toBe(`${window.location.origin}/dialog/form`);
    });

    it('lets the host handle success (saved prevented): closes, no navigation', async () => {
      prevent.add('saved');
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply({ status: 204, headers: {} })));
      await submitForm(form());
      expect(dialog.open).toBe(false);
      expect(reload).not.toHaveBeenCalled();
    });

    it('re-renders a 422 and focuses the first invalid control', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply({ status: 422, body: '<form method="post"><input id="bad" aria-invalid="true"></form>' })));
      await submitForm(form());
      expect(document.activeElement?.id).toBe('bad');
      expect(events[events.length - 1]).toEqual(['invalid', { status: 422 }, false]);
    });

    it('re-renders a 200 fragment and focuses the title', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply({ status: 200, body: '<p id="step2">Step 2</p>' })));
      await submitForm(form());
      expect(document.getElementById('step2')).not.toBeNull();
      expect(document.activeElement?.id).toBe('d-title');
    });

    it('keeps the typed form with one alert on errors', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply({ status: 500 })));
      await submitForm(form());
      vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));
      await submitForm(form());
      expect(body().querySelectorAll(`[${ALERT_ATTR}]`)).toHaveLength(1);
      expect(body().querySelector('[role="alert"]')?.textContent).toBe('Failed');
      expect(form()).not.toBeNull();
      expect((document.getElementById('save') as HTMLButtonElement).disabled).toBe(false);
      expect(events.filter(([name]) => name === 'failed').map(([, detail]) => detail)).toEqual([
        { stage: 'submit', status: 500 },
        { stage: 'submit', status: null },
      ]);
    });

    it('treats a whole document answered with 200 as an error', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply({ status: 200, body: '<!doctype html><html><body>page</body></html>' })));
      await submitForm(form());
      expect(body().querySelector('[role="alert"]')).not.toBeNull();
    });

    it('ignores double submits and disables the buttons meanwhile', async () => {
      let resolve: (r: Response) => void = () => undefined;
      const fetchMock = vi.fn().mockImplementation(() => new Promise<Response>((r) => (resolve = r)));
      vi.stubGlobal('fetch', fetchMock);
      const first = new Event('submit', { bubbles: true, cancelable: true });
      form().dispatchEvent(first);
      expect((document.getElementById('save') as HTMLButtonElement).disabled).toBe(true);
      const second = new Event('submit', { bubbles: true, cancelable: true });
      form().dispatchEvent(second);
      expect(second.defaultPrevented).toBe(true);
      expect(fetchMock).toHaveBeenCalledTimes(1);
      resolve(reply({ status: 204, headers: {} }));
      await tick();
      await tick();
      expect(reload).toHaveBeenCalled();
    });

    it('closing during a submit drops the answer', async () => {
      let resolve: (r: Response) => void = () => undefined;
      vi.stubGlobal('fetch', vi.fn().mockImplementation(() => new Promise<Response>((r) => (resolve = r))));
      form().dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
      session.close();
      resolve(reply({ status: 204, headers: { 'X-Nowo-Redirect': '/x' } }));
      await tick();
      expect(assign).not.toHaveBeenCalled();
    });

    it('leaves GET, native and cross-origin forms to the browser', async () => {
      body().innerHTML = `<form id="g" method="get" action="/search"></form><form id="n" method="post" data-nowo-ui-remote-form-native action="/x"></form>
        <form id="c" method="post" action="https://evil.test/x"></form><form id="self" method="post"></form>`;
      for (const id of ['g', 'n', 'c']) {
        const event = await submitForm(document.getElementById(id) as HTMLFormElement);
        expect(event.defaultPrevented).toBe(false);
      }
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply({ status: 204, headers: {} })));
      const own = await submitForm(document.getElementById('self') as HTMLFormElement);
      expect(own.defaultPrevented).toBe(true);
    });

    it('ignores submits of non-forms and of a closed dialog', async () => {
      expect(await session.submit(new Event('submit'))).toBe(false);
      session.close();
      expect(await session.submit(Object.assign(new Event('submit'), {}))).toBe(false);
    });
  });

  it('closes on a backdrop click only with closeOnBackdrop', async () => {
    await openWith(reply({ body: '<p>x</p>' }));
    vi.spyOn(dialog, 'getBoundingClientRect').mockReturnValue({ left: 10, right: 100, top: 10, bottom: 100, width: 90, height: 90 } as DOMRect);
    dialog.dispatchEvent(new MouseEvent('click', { bubbles: true, clientX: 1, clientY: 1 }));
    expect(dialog.open).toBe(true);
    settings.closeOnBackdrop = true;
    dialog.dispatchEvent(new MouseEvent('click', { bubbles: true, clientX: 50, clientY: 50 }));
    expect(dialog.open).toBe(true);
    dialog.dispatchEvent(new MouseEvent('click', { bubbles: true, clientX: 1, clientY: 1 }));
    expect(dialog.open).toBe(false);
  });

  it('ignores backdrop clicks on an unlaid dialog and clicks inside', async () => {
    settings.closeOnBackdrop = true;
    await openWith(reply({ body: '<p id="in">x</p>' }));
    vi.spyOn(dialog, 'getBoundingClientRect').mockReturnValue({ left: 0, right: 0, top: 0, bottom: 0, width: 0, height: 0 } as DOMRect);
    dialog.dispatchEvent(new MouseEvent('click', { bubbles: true, clientX: 500, clientY: 500 }));
    document.getElementById('in')?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    expect(dialog.open).toBe(true);
  });

  it('prevents cancel while a nested layer owns Escape', async () => {
    await openWith(reply({ body: '<div class="ts-wrapper dropdown-active"><input id="ts"></div><input id="plain">' }));
    (document.getElementById('ts') as HTMLElement).focus();
    const cancel = new Event('cancel', { cancelable: true });
    dialog.dispatchEvent(cancel);
    expect(cancel.defaultPrevented).toBe(true);
    (document.getElementById('plain') as HTMLElement).focus();
    const second = new Event('cancel', { cancelable: true });
    dialog.dispatchEvent(second);
    expect(second.defaultPrevented).toBe(false);
  });

  it('a close queued before a reopen in the same task keeps the new session (ES-I3)', async () => {
    await openWith(reply({ body: '<p>x</p>' }));
    dialog.removeAttribute('open');
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply({ body: '<p id="second">y</p>' })));
    session.open('/form', opener(), 'Again');
    dialog.dispatchEvent(new Event('close'));
    await tick();
    await tick();
    expect(dialog.open).toBe(true);
    expect(document.getElementById('second')).not.toBeNull();
    expect(document.documentElement.getAttribute('data-nowo-overlay')).toBe('1');
  });

  it('empties the body on close and does not focus a removed opener', async () => {
    await openWith(reply({ body: '<p>x</p>' }));
    opener().remove();
    session.close();
    expect(body().childNodes.length).toBe(0);
    expect(document.activeElement).not.toBe(null);
  });

  it('works without title or body targets', () => {
    const bare = new RemoteFormSession(dialog, () => null, () => null, () => settings, () => true);
    expect(bare.open('/form', null, 'x')).toBe(false);
  });
});
