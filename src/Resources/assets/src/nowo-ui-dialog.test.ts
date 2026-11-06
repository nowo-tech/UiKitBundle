import { afterEach, beforeEach, describe, expect, it, onTestFinished, vi } from 'vitest';
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

/** A plain-path answer: an HTML fragment unless told otherwise. */
function htmlReply(text: string, contentType = 'text/html; charset=UTF-8'): { ok: boolean; status: number; headers: Headers; text: () => Promise<string> } {
  return { ok: true, status: 200, headers: new Headers({ 'content-type': contentType }), text: () => Promise.resolve(text) };
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
    const fetchMock = vi.fn().mockResolvedValue(htmlReply('<form id="loaded"></form>'));
    vi.stubGlobal('fetch', fetchMock);
    const dialog = document.getElementById('r1') as HTMLDialogElement;
    const loaded = new Promise<CustomEvent>((resolve) => dialog.addEventListener('nowo-ui:dialog-loaded', (e) => resolve(e as CustomEvent)));
    const event = click(document.getElementById('remote')!);
    expect(event.defaultPrevented).toBe(true);
    const detail = (await loaded).detail as { url: string };
    expect(detail.url).toBe(`${window.location.origin}/items/7/edit?modal=1`);
    expect(dialog.querySelector('#loaded')).not.toBeNull();
    expect(dialog.querySelector('.nowo-ui-dialog__body')?.hasAttribute('aria-busy')).toBe(false);
    expect(fetchMock.mock.calls[0][1]).toMatchObject({ credentials: 'same-origin', mode: 'same-origin' });
  });

  describe('plain path filter (audit GT-L2)', () => {
    const body = (dialog: HTMLDialogElement): Element => dialog.querySelector('.nowo-ui-dialog__body') as Element;

    it('removes what could run or navigate', async () => {
      vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(
          htmlReply(`<p id="ok">x</p><meta http-equiv="refresh" content="0;url=https://evil.example/"><iframe srcdoc="<script>x()</script>"></iframe><iframe src="javascript:x()"></iframe>
            <img id="i" src="/x" onerror="x()"><script>x()</script><base href="https://evil.example/"><object data="x"></object><embed src="x"><a id="l" href="javascript:x()">x</a>`),
        ),
      );
      const dialog = document.getElementById('r1') as HTMLDialogElement;
      expect(await mod.loadDialogContent(dialog, '/x')).toBe(true);
      expect(body(dialog).querySelector('#ok')).not.toBeNull();
      expect(body(dialog).querySelector('meta, iframe, script, base, object, embed')).toBeNull();
      expect(body(dialog).querySelector('#i')?.hasAttribute('onerror')).toBe(false);
      expect(body(dialog).querySelector('#l')?.hasAttribute('href')).toBe(false);
    });

    it.each(['attributes', 'removeAttribute', 'getAttributeNames'])('GT-L3: a form control named %s does not hide the form', async (name) => {
      // happy-dom has no named properties on a form: behave as browsers do.
      Object.defineProperty(HTMLFormElement.prototype, name, {
        configurable: true,
        get(this: HTMLFormElement) {
          return Element.prototype.querySelector.call(this, `[name="${name}"]`);
        },
      });
      onTestFinished(() => {
        delete (HTMLFormElement.prototype as unknown as Record<string, unknown>)[name];
      });
      const form = `<form class="c" onfocusin="x()" action="javascript:x()"><input name="${name}" autofocus></form>`;
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue(htmlReply(`${form}<template id="t">${form}</template>`)));
      const dialog = document.getElementById('r1') as HTMLDialogElement;
      expect(await mod.loadDialogContent(dialog, '/x')).toBe(true);
      const names = (element: Element): string[] => Element.prototype.getAttributeNames.call(element);
      expect(names(body(dialog).querySelector('.c') as Element)).toEqual(['class']);
      expect(names((body(dialog).querySelector('#t') as HTMLTemplateElement).content.querySelector('.c') as Element)).toEqual(['class']);
    });

    it('keeps the form partial of a dashboard (BreadcrumbKit shape) as it was', async () => {
      const partial = `<div id="modal-bk-collection-form-container" data-bk-dialog-title="New collection"><form name="c" method="post" action="/admin/collections/new" class="nowo-ui-form"><div><label for="c_code" class="required">Code</label><input type="text" id="c_code" name="c[code]" required="required" maxlength="64"></div><input type="hidden" id="c__token" name="c[_token]" value="abc"><div class="nowo-ui-form__actions"><button type="submit" class="nowo-ui-btn">Create</button><button type="button" class="nowo-ui-btn" data-nowo-ui-dialog-close="">Cancel</button></div></form></div>`;
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue(htmlReply(partial, 'text/html; charset=UTF-8')));
      const dialog = document.getElementById('r1') as HTMLDialogElement;
      const loaded = vi.fn();
      dialog.addEventListener('nowo-ui:dialog-loaded', loaded);
      expect(await mod.loadDialogContent(dialog, '/admin/collections/new')).toBe(true);
      expect(body(dialog).innerHTML).toBe(partial);
      expect(loaded).toHaveBeenCalledTimes(1);
    });

    it('an empty answer still empties the body', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, status: 204, headers: new Headers(), text: () => Promise.resolve('') }));
      const dialog = document.getElementById('r1') as HTMLDialogElement;
      body(dialog).innerHTML = '<p>old</p>';
      expect(await mod.loadDialogContent(dialog, '/x')).toBe(true);
      expect(body(dialog).childNodes.length).toBe(0);
    });

    it.each([
      ['text/plain', '<img src=x onerror=x()>'],
      ['application/json', '{"name":"<img src=x onerror=x()>"}'],
      ['application/xhtml+xml', '<p>x</p>'],
      ['text/html', '<!doctype html><html><body>page</body></html>'],
      ['text/html', '<html><body>page</body></html>'],
    ])('does not insert %s %s: it follows the fallback link', async (type, text) => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue(htmlReply(text, type)));
      const assign = vi.spyOn(mod.navigation, 'assign').mockImplementation(() => undefined);
      const dialog = document.getElementById('r1') as HTMLDialogElement;
      body(dialog).innerHTML = '<p id="old">old</p>';
      mod.openDialog(dialog);
      expect(await mod.loadDialogContent(dialog, '/x', '/items/7/edit')).toBe(false);
      expect(body(dialog).querySelector('#old')).not.toBeNull();
      expect(assign).toHaveBeenCalledWith('/items/7/edit');
    });

    it('a failed fetch (a redirect to another origin under same-origin mode) follows the fallback link', async () => {
      vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));
      const assign = vi.spyOn(mod.navigation, 'assign').mockImplementation(() => undefined);
      expect(await mod.loadDialogContent(document.getElementById('r1') as HTMLDialogElement, '/x', '/items/7/edit')).toBe(false);
      expect(assign).toHaveBeenCalledWith('/items/7/edit');
    });

    it('limits sources to http(s) on this origin', async () => {
      const fetchMock = vi.fn();
      vi.stubGlobal('fetch', fetchMock);
      for (const src of ['javascript:alert(1)', 'data:text/html,x', `blob:${window.location.origin}/x`, 'https://evil.example/x', '//evil.example/x']) {
        expect(mod.sameOriginUrl(src)).toBeNull();
        expect(await mod.loadDialogContent(document.getElementById('r1') as HTMLDialogElement, src)).toBe(false);
      }
      expect(fetchMock).not.toHaveBeenCalled();
      expect(mod.sameOriginUrl('/x')).toBe(`${window.location.origin}/x`);
    });
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

describe('nowo-ui-dialog remote form mode (2.28)', () => {
  const tick = (): Promise<void> => new Promise((r) => setTimeout(r, 0));
  const reply = (status: number, body = '', headers: Record<string, string> = { 'content-type': 'text/html' }): Response =>
    ({ status, type: 'basic', ok: status < 300, headers: new Headers(headers), text: () => Promise.resolve(body) }) as unknown as Response;

  beforeEach(() => {
    document.body.innerHTML = `
      <a href="/monitors/1/edit" id="ropen" data-nowo-ui-dialog-open="rf" data-nowo-ui-dialog-src="/monitors/1/edit" data-nowo-ui-dialog-form data-nowo-ui-dialog-title="Edit monitor">Edit</a>
      <a href="/x" id="rbad" data-nowo-ui-dialog-open="rf" data-nowo-ui-dialog-src="https://evil.test/x" data-nowo-ui-dialog-form>Bad</a>
      <dialog id="rf" data-nowo-ui-dialog data-nowo-ui-remote-form data-nowo-ui-remote-form-loading="Loading" data-nowo-ui-remote-form-error="Failed"
              data-nowo-ui-remote-form-load-error="Load failed" data-nowo-ui-remote-form-invalid=".err input" data-nowo-ui-remote-form-follow-on-load-error="false">
        <h2 id="rf-title" tabindex="-1"></h2><div class="nowo-ui-dialog__body" data-nowo-ui-dialog-body></div>
      </dialog>
      <dialog id="plain2" data-nowo-ui-dialog><p>p</p></dialog>`;
    document.querySelectorAll('dialog').forEach((d) => polyfill(d as HTMLDialogElement));
    mod.bindNowoUiDialog();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    window.NowoUiOverlay?.releaseAll();
    document.body.innerHTML = '';
  });

  it('reads the settings from the dialog attributes', () => {
    const settings = mod.remoteFormSettings(document.getElementById('rf') as HTMLDialogElement);
    expect(settings).toMatchObject({ loading: 'Loading', error: 'Failed', loadError: 'Load failed', invalidSelector: '.err input', followOnLoadError: false, closeOnBackdrop: false });
    const plain = mod.remoteFormSettings(document.getElementById('plain2') as HTMLDialogElement);
    expect(plain).toMatchObject({ loading: '', followOnLoadError: true, requestHeader: 'X-Nowo-Dialog', redirectHeader: 'X-Nowo-Redirect', locationHeader: 'X-Nowo-Dialog-Location' });
  });

  it('reads configured header names from the dialog, ignoring invalid ones (2.33)', () => {
    const dialog = document.getElementById('plain2') as HTMLDialogElement;
    dialog.setAttribute('data-nowo-ui-remote-form-request-header', 'X-Beacon-Dialog');
    dialog.setAttribute('data-nowo-ui-remote-form-redirect-header', 'X-Beacon-Redirect');
    dialog.setAttribute('data-nowo-ui-remote-form-location-header', 'X-Beacon-Dialog-Location');
    expect(mod.remoteFormSettings(dialog)).toMatchObject({ requestHeader: 'X-Beacon-Dialog', redirectHeader: 'X-Beacon-Redirect', locationHeader: 'X-Beacon-Dialog-Location' });
    dialog.setAttribute('data-nowo-ui-remote-form-location-header', 'X: y\r\nSet-Cookie');
    expect(mod.remoteFormSettings(dialog).locationHeader).toBe('X-Nowo-Dialog-Location');
  });

  it('loads a step answer into the same dialog and emits nowo-ui:remote-form:step (2.33)', async () => {
    const dialog = document.getElementById('rf') as HTMLDialogElement;
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(reply(200, '<form method="post" action="/connect"><button type="submit" id="s1">Next</button></form>'))
      .mockResolvedValueOnce(reply(204, '', { 'X-Nowo-Dialog-Location': '/connect/lists' }))
      .mockResolvedValueOnce(reply(200, '<p id="lists">Lists</p>'));
    vi.stubGlobal('fetch', fetchMock);
    const step = vi.fn();
    dialog.addEventListener('nowo-ui:remote-form:step', step);
    expect(mod.openRemoteForm('rf', '/connect')).toBe(true);
    await tick();
    await tick();
    dialog.querySelector('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    for (let i = 0; i < 6; i += 1) await tick();
    expect(dialog.open).toBe(true);
    expect(document.getElementById('lists')).not.toBeNull();
    expect((step.mock.calls[0]?.[0] as CustomEvent).detail).toEqual({ url: '/connect/lists', status: 204 });
  });

  it('opens the remote form from an opener, posts back and emits nowo-ui:remote-form:* events', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply(200, '<form method="post" action="/monitors/1/edit"><button type="submit">Save</button></form>')));
    const dialog = document.getElementById('rf') as HTMLDialogElement;
    const loaded = vi.fn();
    dialog.addEventListener('nowo-ui:remote-form:loaded', loaded);
    const event = click(document.getElementById('ropen')!);
    expect(event.defaultPrevented).toBe(true);
    expect(dialog.open).toBe(true);
    expect(document.getElementById('rf-title')?.textContent).toBe('Edit monitor');
    await tick();
    await tick();
    expect(loaded).toHaveBeenCalled();
    const saved = vi.fn((e: Event) => e.preventDefault());
    document.addEventListener('nowo-ui:remote-form:saved', saved);
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply(204, '', {})));
    dialog.querySelector('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await tick();
    await tick();
    document.removeEventListener('nowo-ui:remote-form:saved', saved);
    expect(saved).toHaveBeenCalled();
    expect(dialog.open).toBe(false);
  });

  it('lets cross-origin sources and modified clicks navigate', () => {
    expect(click(document.getElementById('rbad')!).defaultPrevented).toBe(false);
    expect(click(document.getElementById('ropen')!, { ctrlKey: true }).defaultPrevented).toBe(false);
  });

  it('opens from script, and refuses unknown dialogs', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply(200, '<p>x</p>')));
    expect(window.nowoUiOpenRemoteForm?.('nope', '/x')).toBe(false);
    expect(mod.openRemoteForm('rf', '/monitors/2/edit')).toBe(true);
    expect((document.getElementById('rf') as HTMLDialogElement).open).toBe(true);
  });

  it('registers plain dialogs with the overlay registry until they close', () => {
    expect(mod.openDialog('plain2')).toBe(true);
    expect(document.documentElement.getAttribute('data-nowo-overlay')).toBe('1');
    mod.closeDialog('plain2');
    expect(document.documentElement.hasAttribute('data-nowo-overlay')).toBe(false);
  });
});
