import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import ConfirmSubmitController from './confirm_submit_controller';
import ReleaseStatusController from './release_status_controller';
import TabsController from './tabs_controller';

const tick = (): Promise<void> => new Promise((r) => setTimeout(r, 0));

let app: Application;

beforeEach(() => {
  app = Application.start();
  app.register('confirm-submit', ConfirmSubmitController);
  app.register('tabs', TabsController);
  app.register('release-status', ReleaseStatusController);
});

afterEach(() => {
  app.stop();
  document.body.innerHTML = '';
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
});

describe('confirm-submit peer', () => {
  it('confirms form submit and cancels on refusal', async () => {
    document.body.innerHTML = `
      <form data-controller="confirm-submit" data-confirm-submit-message-value=" Delete? "
            data-action="submit->confirm-submit#confirm"></form>`;
    await tick();
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(false);
    const event = new Event('submit', { cancelable: true });
    document.querySelector('form')!.dispatchEvent(event);
    expect(confirmSpy).toHaveBeenCalledWith('Delete?');
    expect(event.defaultPrevented).toBe(true);

    confirmSpy.mockReturnValue(true);
    const ok = new Event('submit', { cancelable: true });
    document.querySelector('form')!.dispatchEvent(ok);
    expect(ok.defaultPrevented).toBe(false);
  });

  it('works on a single submit button click', async () => {
    document.body.innerHTML = `
      <form><button type="button" name="reset" data-controller="confirm-submit"
              data-confirm-submit-message-value="Reset?"
              data-action="click->confirm-submit#confirm">Reset</button></form>`;
    await tick();
    vi.spyOn(window, 'confirm').mockReturnValue(false);
    const click = new MouseEvent('click', { bubbles: true, cancelable: true });
    document.querySelector('button')!.dispatchEvent(click);
    expect(click.defaultPrevented).toBe(true);
  });

  it('blocked value always cancels; empty message passes through', async () => {
    document.body.innerHTML = `
      <form id="b" data-controller="confirm-submit" data-confirm-submit-blocked-value="true"
            data-confirm-submit-message-value="x" data-action="submit->confirm-submit#confirm"></form>
      <form id="e" data-controller="confirm-submit" data-action="submit->confirm-submit#confirm"></form>`;
    await tick();
    const confirmSpy = vi.spyOn(window, 'confirm');
    const blocked = new Event('submit', { cancelable: true });
    document.getElementById('b')!.dispatchEvent(blocked);
    expect(blocked.defaultPrevented).toBe(true);
    const empty = new Event('submit', { cancelable: true });
    document.getElementById('e')!.dispatchEvent(empty);
    expect(empty.defaultPrevented).toBe(false);
    expect(confirmSpy).not.toHaveBeenCalled();
  });
});

describe('tabs peer', () => {
  it('switches panels via kit and legacy tab ids', async () => {
    document.body.innerHTML = `
      <div data-controller="tabs" data-tabs-active-tab-value="a">
        <button data-tabs-target="trigger" data-nowo-ui-tab-id="a" data-action="tabs#open">A</button>
        <button data-tabs-target="trigger" data-tab-id="b" data-action="tabs#open">B</button>
        <div data-tabs-target="tab" data-nowo-ui-tab-id="a">A</div>
        <div data-tabs-target="tab" data-tab-id="b">B</div>
      </div>`;
    await tick();
    const [panelA, panelB] = Array.from(document.querySelectorAll<HTMLElement>('[data-tabs-target="tab"]'));
    expect(panelA.hidden).toBe(false);
    expect(panelB.hidden).toBe(true);

    document.querySelectorAll<HTMLElement>('[data-tabs-target="trigger"]')[1].click();
    await tick();
    expect(panelA.hidden).toBe(true);
    expect(panelB.hidden).toBe(false);
    expect(panelB.dataset.state).toBe('active');
  });
});

describe('release-status peer', () => {
  it('loads on toggle via kit part attributes and stays idle until opened', async () => {
    const fetchFn = vi.fn(async () => ({
      ok: true,
      json: async () => ({
        status: 'ok',
        current: 'v1.0.0',
        latest: 'v1.0.0',
        updateAvailable: false,
        versionsBehind: 0,
        releaseUrl: null,
        compareUrl: 'https://github.com/acme/app/compare/v1.0.0...main',
        checkedAt: null,
      }),
    }));
    vi.stubGlobal('fetch', fetchFn);
    document.body.innerHTML = `
      <dialog data-controller="release-status" data-action="toggle->release-status#onToggle"
              data-nowo-ui-release data-nowo-ui-release-url="/s"
              data-nowo-ui-release-messages='{"up_to_date":"OK","latest_label":"L %version%"}'>
        <p data-nowo-ui-release-part="loading"></p>
        <div data-nowo-ui-release-part="content">
          <p data-release-status-target="summary"></p>
          <p data-nowo-ui-release-part="detail"></p>
        </div>
        <p data-nowo-ui-release-part="error"></p>
        <a data-nowo-ui-release-part="compare" hidden></a>
      </dialog>`;
    await tick();
    const dialog = document.querySelector('dialog') as HTMLDialogElement;
    expect((dialog.querySelector('[data-nowo-ui-release-part="loading"]') as HTMLElement).hidden).toBe(true);

    dialog.dispatchEvent(new Event('toggle'));
    expect(fetchFn).not.toHaveBeenCalled();

    dialog.setAttribute('open', '');
    dialog.dispatchEvent(new Event('toggle'));
    await tick();
    await tick();
    expect(fetchFn).toHaveBeenCalledTimes(1);
    expect(dialog.querySelector('[data-release-status-target="summary"]')!.textContent).toBe('OK');
    expect(dialog.querySelector('[data-nowo-ui-release-part="detail"]')!.textContent).toBe('L v1.0.0');

    dialog.dispatchEvent(new Event('toggle'));
    await tick();
    expect(fetchFn).toHaveBeenCalledTimes(1);
  });

  it('prefers Stimulus values and shows error on failure or missing url', async () => {
    const fetchFn = vi.fn(async () => ({ ok: false, status: 503 }));
    vi.stubGlobal('fetch', fetchFn);
    document.body.innerHTML = `
      <dialog id="v" data-controller="release-status" data-release-status-url-value="/v"
              data-release-status-messages-value='{"check_unavailable":"Nope"}' open>
        <p data-release-status-target="error"></p>
      </dialog>
      <dialog id="n" data-controller="release-status" open>
        <p data-nowo-ui-release-part="error"></p>
      </dialog>`;
    await tick();
    const controllerFor = (id: string): { load(): Promise<void> } =>
      app.getControllerForElementAndIdentifier(document.getElementById(id)!, 'release-status') as unknown as {
        load(): Promise<void>;
      };
    const c = controllerFor('v');
    const first = c.load();
    expect(c.load()).toBe(first);
    await first;
    expect(fetchFn).toHaveBeenCalledWith('/v', expect.anything());
    expect(document.querySelector('#v [data-release-status-target="error"]')!.textContent).toBe('Nope');

    await controllerFor('n').load();
    expect((document.querySelector('#n [data-nowo-ui-release-part="error"]') as HTMLElement).hidden).toBe(false);
  });
});
