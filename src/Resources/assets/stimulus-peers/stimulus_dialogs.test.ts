/**
 * 2.70 — the Stimulus runtime of the on-demand dialogs on a page WITHOUT js/nowo-ui-dialog.js / js/nowo-ui-confirm.js
 * (Beacon product pages): the `remote-form-dialog` peer in delegated mode on the remote form shell and the
 * `confirm-shared` peer on the shared confirm dialog of two row kebab menus. The markup is
 * tests/Fixtures/stimulus-dialogs/page-stimulus.html, rendered by the Twig partials (StimulusDialogs270Test).
 */
import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ConfirmSharedController from './confirm_shared_controller';
import RemoteFormDialogController, { navigation } from './remote_form_dialog_controller';
import { OWNER_ATTR } from '../src/nowo-ui-runtime-owner';
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

function click(el: Element, init: MouseEventInit = {}): MouseEvent {
  const event = new MouseEvent('click', { bubbles: true, cancelable: true, button: 0, ...init });
  el.dispatchEvent(event);
  return event;
}

describe('Stimulus-only dialogs (2.70)', () => {
  let application: Application;
  const confirmDialog = (): HTMLDialogElement => document.getElementById('members-confirm') as HTMLDialogElement;
  const remote = (): HTMLDialogElement => document.getElementById('member-edit') as HTMLDialogElement;
  const toggles = (): HTMLButtonElement[] => Array.from(document.querySelectorAll<HTMLButtonElement>('[data-nowo-confirm-target="members-confirm"]'));
  const visibleSubmit = (): HTMLButtonElement => confirmDialog().querySelector('button[data-nowo-confirm-variant]:not([hidden])') as HTMLButtonElement;
  const body = (): HTMLElement => remote().querySelector('[data-nowo-ui-dialog-body]') as HTMLElement;

  beforeEach(async () => {
    document.body.innerHTML = FIXTURE;
    document.querySelectorAll('dialog').forEach((d) => polyfill(d));
    vi.spyOn(navigation, 'assign').mockImplementation(() => undefined);
    vi.spyOn(navigation, 'reload').mockImplementation(() => undefined);
    application = Application.start();
    application.register('confirm-shared', ConfirmSharedController);
    application.register('remote-form-dialog', RemoteFormDialogController);
    await tick();
  });

  afterEach(() => {
    // stop() alone keeps the controllers connected (their document listeners would leak into the next test).
    application.unload(['confirm-shared', 'remote-form-dialog']);
    application.stop();
    document.body.innerHTML = '';
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    window.NowoUiOverlay?.releaseAll();
  });

  it('claims both dialogs while connected', () => {
    expect(confirmDialog().getAttribute(OWNER_ATTR)).toBe('stimulus');
    expect(remote().getAttribute(OWNER_ATTR)).toBe('stimulus');
    application.unload(['confirm-shared', 'remote-form-dialog']);
    expect(confirmDialog().hasAttribute(OWNER_ATTR)).toBe(false);
    expect(remote().hasAttribute(OWNER_ATTR)).toBe(false);
  });

  it('fills the shared confirm from the row toggle and binds Confirm to that row form (target + CSRF)', () => {
    const second = toggles()[1]!;
    // The kebab is open when its item is clicked (a toggle in a closed <details> cannot take focus back).
    (second.closest('details') as HTMLDetailsElement).open = true;
    second.focus();
    const event = click(second);
    expect(event.defaultPrevented).toBe(true);
    expect(confirmDialog().open).toBe(true);
    expect(confirmDialog().querySelector('[data-nowo-confirm-slot="title"]')!.textContent).toBe('Remove Bob?');
    expect(confirmDialog().querySelector('[data-nowo-confirm-slot="message"]')!.textContent).toBe('He loses access.');
    const submit = visibleSubmit();
    expect(submit.textContent).toBe('Remove');
    const formId = second.getAttribute('data-nowo-confirm-form')!;
    expect(submit.getAttribute('form')).toBe(formId);
    const form = document.getElementById(formId) as HTMLFormElement;
    expect(form.getAttribute('action')).toBe('/members/2/delete');
    expect((form.querySelector('input[name="_token"]') as HTMLInputElement).value).toBe('tok-2');
    // A danger confirm opens on Cancel (2.61).
    expect(document.activeElement?.getAttribute('data-nowo-confirm-slot')).toBe('cancel');

    // Cancel closes, drops the binding and returns focus to the toggle.
    click(confirmDialog().querySelector('[data-nowo-confirm-slot="cancel"]')!);
    expect(confirmDialog().open).toBe(false);
    expect(submit.hasAttribute('form')).toBe(false);
    expect(document.activeElement).toBe(second);

    // The other row binds its own form.
    click(toggles()[0]!);
    expect(visibleSubmit().getAttribute('form')).toBe(toggles()[0]!.getAttribute('data-nowo-confirm-form'));
    expect(confirmDialog().querySelector('[data-nowo-confirm-slot="title"]')!.textContent).toBe('Remove Ann?');
  });

  it('opens nothing for a toggle without a row form, and ignores other dialogs\' toggles', () => {
    toggles()[0]!.removeAttribute('data-nowo-confirm-form');
    click(toggles()[0]!);
    expect(confirmDialog().open).toBe(false);
    const other = document.createElement('button');
    other.setAttribute('data-nowo-confirm-open', '');
    other.setAttribute('data-nowo-confirm-target', 'elsewhere');
    document.body.append(other);
    expect(click(other).defaultPrevented).toBe(false);
    expect(click(document.body).defaultPrevented).toBe(false);
  });

  it('locks Confirm until the type-to-confirm value matches', () => {
    const toggle = toggles()[0]!;
    toggle.setAttribute('data-nowo-confirm-expected-value', 'ann');
    toggle.setAttribute('data-nowo-confirm-expected-pattern', 'ann');
    toggle.setAttribute('data-nowo-confirm-expected-label', 'Type ann');
    click(toggle);
    const input = confirmDialog().querySelector('[data-nowo-confirm-slot="expect-input"]') as HTMLInputElement;
    expect(document.activeElement).toBe(input);
    expect(visibleSubmit().disabled).toBe(true);
    input.value = 'ann';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    expect(visibleSubmit().disabled).toBe(false);
    confirmDialog().dispatchEvent(new Event('input', { bubbles: true }));
    expect(visibleSubmit().disabled).toBe(false);
  });

  it('closes from script and on Escape (native close)', () => {
    click(toggles()[0]!);
    const controller = application.getControllerForElementAndIdentifier(confirmDialog(), 'confirm-shared') as ConfirmSharedController;
    controller.close(new Event('click'));
    expect(confirmDialog().open).toBe(false);
    controller.close();
    expect(controller.openFor(null)).toBe(false);
    click(toggles()[1]!);
    confirmDialog().close();
    expect(visibleSubmit().hasAttribute('form')).toBe(false);
  });

  it('opens the shared remote shell from each row opener (X-Nowo-Dialog), 422 in place, 204 + X-Nowo-Redirect', async () => {
    const fetchMock = vi.fn().mockResolvedValue(reply(200, '<form method="post" action="/members/2/edit"><input name="name"><div class="nowo-ui-form__actions"><button type="submit">Save</button></div></form>'));
    vi.stubGlobal('fetch', fetchMock);
    const loaded = vi.fn();
    const legacyLoaded = vi.fn();
    remote().addEventListener('remote-form-dialog:loaded', loaded);
    remote().addEventListener('nowo-ui:remote-form:loaded', legacyLoaded);
    const opener = document.querySelector('[data-testid="edit-2"]') as HTMLAnchorElement;
    const event = click(opener);
    expect(event.defaultPrevented).toBe(true);
    expect(remote().open).toBe(true);
    expect(remote().querySelector('h2')!.textContent).toBe('Edit member');
    await tick();
    await tick();
    expect(fetchMock).toHaveBeenCalledTimes(1);
    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe(`${window.location.origin}/members/2/edit`);
    expect(init.headers).toMatchObject({ 'X-Nowo-Dialog': '1' });
    expect(loaded).toHaveBeenCalledTimes(1);
    expect(legacyLoaded).toHaveBeenCalledTimes(1);
    // The footer received the form actions (2.29).
    expect(remote().querySelector('[data-nowo-ui-dialog-footer] button[type="submit"]')).not.toBeNull();

    // 422: re-rendered in place, first invalid control focused.
    const post = vi.fn().mockResolvedValue(reply(422, '<form method="post" action="/members/2/edit"><input name="name" aria-invalid="true"></form>'));
    vi.stubGlobal('fetch', post);
    body().querySelector('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await tick();
    await tick();
    expect(post).toHaveBeenCalledTimes(1);
    expect((post.mock.calls[0] as [string, RequestInit])[1].method).toBe('POST');
    expect(document.activeElement?.getAttribute('aria-invalid')).toBe('true');
    expect(remote().open).toBe(true);

    // 204 + X-Nowo-Redirect: closes and goes there (same origin path).
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply(204, '', { 'X-Nowo-Redirect': '/members' })));
    body().querySelector('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await tick();
    await tick();
    expect(navigation.assign).toHaveBeenCalledWith('/members');
    expect(remote().open).toBe(false);
    expect(document.activeElement).toBe(opener);
  });

  it('follows a 204 + X-Nowo-Dialog-Location step in the same dialog', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply(200, '<form method="post" action="/members/1/edit"></form>')));
    click(document.querySelector('[data-testid="edit-1"]')!);
    await tick();
    await tick();
    const steps: string[] = [];
    remote().addEventListener('nowo-ui:remote-form:step', (e) => steps.push((e as CustomEvent).detail.url));
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(reply(204, '', { 'X-Nowo-Dialog-Location': '/members/1/step-2' }))
      .mockResolvedValueOnce(reply(200, '<p data-step="2">Step 2</p>'));
    vi.stubGlobal('fetch', fetchMock);
    body().querySelector('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await tick();
    await tick();
    await tick();
    expect(steps).toEqual(['/members/1/step-2']);
    expect(fetchMock).toHaveBeenCalledTimes(2);
    expect(body().querySelector('[data-step="2"]')).not.toBeNull();
    expect(remote().open).toBe(true);
  });

  it('lets a legacy nowo-ui:remote-form:saved listener prevent the redirect', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply(200, '<form method="post" action="/members/1/edit"></form>')));
    click(document.querySelector('[data-testid="edit-1"]')!);
    await tick();
    await tick();
    remote().addEventListener('nowo-ui:remote-form:saved', (e) => e.preventDefault());
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply(204, '', { 'X-Nowo-Redirect': '/members' })));
    body().querySelector('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await tick();
    await tick();
    expect(navigation.assign).not.toHaveBeenCalled();
    expect(navigation.reload).not.toHaveBeenCalled();
    expect(remote().open).toBe(false);
  });

  it('closes on the × and ignores modified clicks and other dialogs\' openers', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply(200, '<p>x</p>')));
    const opener = document.querySelector('[data-testid="edit-1"]') as HTMLAnchorElement;
    expect(click(opener, { ctrlKey: true }).defaultPrevented).toBe(false);
    expect(remote().open).toBe(false);
    click(opener);
    expect(remote().open).toBe(true);
    click(remote().querySelector('[data-nowo-ui-dialog-close]')!);
    expect(remote().open).toBe(false);
    // A click elsewhere in the dialog does nothing.
    click(opener);
    click(remote().querySelector('h2')!);
    expect(remote().open).toBe(true);

    const foreign = document.createElement('a');
    foreign.href = '/x';
    foreign.setAttribute('data-nowo-ui-dialog-open', 'other');
    foreign.setAttribute('data-nowo-ui-dialog-src', '/x');
    foreign.setAttribute('data-nowo-ui-dialog-form', '');
    document.body.append(foreign);
    expect(click(foreign).defaultPrevented).toBe(false);
    const plain = document.createElement('a');
    plain.setAttribute('data-nowo-ui-dialog-open', 'member-edit');
    document.body.append(plain);
    expect(click(plain).defaultPrevented).toBe(false);
  });

  it('reads the settings from the dialog unless the host set the value', async () => {
    remote().setAttribute('data-remote-form-dialog-loading-value', 'Host loading');
    await tick();
    vi.stubGlobal('fetch', vi.fn(() => new Promise<Response>(() => undefined)));
    click(document.querySelector('[data-testid="edit-1"]')!);
    expect(body().textContent).toBe('Host loading');
    remote().removeAttribute('data-remote-form-dialog-loading-value');
    remote().close();
    click(document.querySelector('[data-testid="edit-1"]')!);
    expect(body().textContent).toBe('Loading…');
  });
});

describe('confirm-shared / remote-form-dialog edge cases (2.70)', () => {
  let application: Application;
  const start = async (html: string): Promise<void> => {
    document.body.innerHTML = html;
    document.querySelectorAll('dialog').forEach((d) => polyfill(d));
    application = Application.start();
    application.register('confirm-shared', ConfirmSharedController);
    application.register('remote-form-dialog', RemoteFormDialogController);
    await tick();
  };

  afterEach(() => {
    application.unload(['confirm-shared', 'remote-form-dialog']);
    application.stop();
    document.body.innerHTML = '';
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    window.NowoUiOverlay?.releaseAll();
  });

  it('works from a wrapper around a plain confirm dialog, and does nothing without one', async () => {
    await start(`
      <button id="open" type="button" data-nowo-confirm-open data-nowo-confirm-target="plain">Open</button>
      <div data-controller="confirm-shared"><dialog id="plain" data-nowo-ui-confirm><p id="text">Sure?</p>
        <div class="nowo-ui-confirm__footer"><button type="button" id="cancel" data-nowo-confirm-close>Cancel</button></div></dialog></div>
      <div id="empty" data-controller="confirm-shared"></div>`);
    const dialog = document.getElementById('plain') as HTMLDialogElement;
    click(document.getElementById('open')!);
    expect(dialog.open).toBe(true);
    click(document.getElementById('text')!);
    expect(dialog.open).toBe(true);
    // A close event that finds the dialog open again (reopened in the same task) is ignored.
    dialog.dispatchEvent(new Event('close'));
    expect(dialog.open).toBe(true);
    click(document.getElementById('cancel')!);
    expect(dialog.open).toBe(false);
    // An already open dialog is not reopened.
    click(document.getElementById('open')!);
    click(document.getElementById('open')!);
    expect(dialog.open).toBe(true);
    const empty = application.getControllerForElementAndIdentifier(document.getElementById('empty')!, 'confirm-shared') as ConfirmSharedController;
    expect(empty.openFor(null)).toBe(false);
    document.dispatchEvent(new MouseEvent('click', { bubbles: true }));
  });

  it('ignores toggles while its dialog has no id, and a dialog without showModal', async () => {
    await start(`
      <button id="open" type="button" data-nowo-confirm-open data-nowo-confirm-target="">Open</button>
      <dialog data-nowo-ui-confirm data-controller="confirm-shared"></dialog>`);
    expect(click(document.getElementById('open')!).defaultPrevented).toBe(false);
    const dialog = document.querySelector('dialog') as HTMLDialogElement;
    Object.defineProperty(dialog, 'showModal', { value: undefined, configurable: true });
    const controller = application.getControllerForElementAndIdentifier(dialog, 'confirm-shared') as ConfirmSharedController;
    expect(controller.openFor(document.getElementById('open'))).toBe(false);
  });

  it('a delegated shell without an id, title or body target opens nothing', async () => {
    await start(`
      <a id="o" href="/x" data-nowo-ui-dialog-open="" data-nowo-ui-dialog-src="/x" data-nowo-ui-dialog-form>Open</a>
      <dialog data-controller="remote-form-dialog" data-remote-form-dialog-target="dialog" data-remote-form-dialog-delegate-value="true"></dialog>`);
    vi.stubGlobal('fetch', vi.fn());
    expect(click(document.getElementById('o')!).defaultPrevented).toBe(false);
    document.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    const dialog = document.querySelector('dialog') as HTMLDialogElement;
    dialog.id = 'shell';
    document.getElementById('o')!.setAttribute('data-nowo-ui-dialog-open', 'shell');
    // No body target: the session cannot open, the link is followed.
    expect(click(document.getElementById('o')!).defaultPrevented).toBe(false);
    expect(dialog.open).toBe(false);
  });

  it('a shell without a title target still opens', async () => {
    await start(`
      <a id="o" href="/x" data-nowo-ui-dialog-open="shell" data-nowo-ui-dialog-src="/x" data-nowo-ui-dialog-form>Edit</a>
      <dialog id="shell" data-controller="remote-form-dialog" data-remote-form-dialog-target="dialog" data-remote-form-dialog-delegate-value="true">
        <div data-remote-form-dialog-target="body"></div></dialog>`);
    vi.stubGlobal('fetch', vi.fn(() => new Promise<Response>(() => undefined)));
    expect(click(document.getElementById('o')!).defaultPrevented).toBe(true);
    expect((document.getElementById('shell') as HTMLDialogElement).open).toBe(true);
  });

  it('an opener without data-nowo-ui-dialog-title takes its text', async () => {
    await start(`
      <a id="o" href="/x" data-nowo-ui-dialog-open="shell" data-nowo-ui-dialog-src="/x" data-nowo-ui-dialog-form>Edit Ann</a>
      <dialog id="shell" data-controller="remote-form-dialog" data-remote-form-dialog-target="dialog" data-remote-form-dialog-delegate-value="true">
        <h2 data-remote-form-dialog-target="title"></h2><div data-remote-form-dialog-target="body"></div></dialog>`);
    vi.stubGlobal('fetch', vi.fn(() => new Promise<Response>(() => undefined)));
    expect(click(document.getElementById('o')!).defaultPrevented).toBe(true);
    expect(document.querySelector('h2')!.textContent).toBe('Edit Ann');
  });
});
