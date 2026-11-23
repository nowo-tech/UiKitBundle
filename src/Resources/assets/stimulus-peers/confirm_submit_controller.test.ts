import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { CONFIRM_SUBMIT_PEER_MARK, createConfirmSubmitController } from './confirm_submit_controller';

const ConfirmSubmitController = createConfirmSubmitController({ mode: 'dialog', classes: { dialog: 'confirm-dialog', confirm: 'btn-primary', danger: 'btn-danger' } });
type ConfirmSubmit = InstanceType<typeof ConfirmSubmitController>;

// vitest 4 + happy-dom no longer expose window.confirm; give vi.spyOn() a function to wrap.
if (typeof window.confirm !== 'function') {
  Object.defineProperty(window, 'confirm', { value: (): boolean => true, configurable: true, writable: true });
}

const dialogButton = (text: string): HTMLButtonElement =>
  Array.from(document.querySelectorAll<HTMLButtonElement>('dialog.confirm-dialog button')).find((b) => b.textContent === text)!;
const flush = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));

describe('confirm-submit peer, dialog mode', () => {
  let application: Application;

  const start = async (html: string): Promise<void> => {
    document.body.innerHTML = html;
    application = Application.start();
    application.register('confirm-submit', ConfirmSubmitController);
    await Promise.resolve();
  };

  beforeEach(() => {
    document.documentElement.dataset.confirmOk = 'Yes';
    document.documentElement.dataset.confirmCancel = 'No';
  });

  afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
    delete document.documentElement.dataset.confirmOk;
    delete document.documentElement.dataset.confirmCancel;
    vi.restoreAllMocks();
  });

  it('holds the submit, asks in the app dialog and replays it with its submitter on confirm', async () => {
    await start(`
      <form data-controller="confirm-submit" data-confirm-submit-message-value="Delete?" data-action="submit->confirm-submit#confirm">
        <button type="submit" name="op" value="delete">Delete</button>
      </form>`);
    const form = document.querySelector('form')!;
    expect((form as unknown as Record<symbol, unknown>)[CONFIRM_SUBMIT_PEER_MARK]).toBe(true);
    const submitted: (HTMLElement | null)[] = [];
    form.addEventListener('submit', (event) => {
      if (!event.defaultPrevented) {
        submitted.push(event.submitter);
      }
      event.preventDefault();
    });
    const nativeConfirm = vi.spyOn(window, 'confirm');

    form.querySelector('button')!.click();
    expect(submitted).toEqual([]);
    expect(nativeConfirm).not.toHaveBeenCalled();
    expect(document.querySelector('dialog h2')!.textContent).toBe('Delete?');
    expect(dialogButton('Yes').className).toBe('btn-danger');

    dialogButton('Yes').click();
    await flush();
    expect(submitted).toEqual([form.querySelector('button')]);
  });

  it('keeps the submit cancelled when the dialog is dismissed', async () => {
    await start(`
      <form data-controller="confirm-submit" data-confirm-submit-message-value="Delete?" data-confirm-submit-danger-value="false"
            data-action="submit->confirm-submit#confirm"></form>`);
    const form = document.querySelector('form')!;
    const submit = vi.fn((event: Event) => event.preventDefault());
    form.addEventListener('submit', submit);
    form.requestSubmit();
    expect(dialogButton('Yes').className).toBe('btn-primary');
    dialogButton('No').click();
    await flush();
    expect(submit).toHaveBeenCalledTimes(1);
  });

  it('replays a confirmed button click', async () => {
    await start(`
      <button type="button" data-controller="confirm-submit" data-confirm-submit-message-value="Reset?"
              data-action="click->confirm-submit#confirm">Reset</button>`);
    const button = document.querySelector<HTMLButtonElement>('[data-controller]')!;
    const clicks = vi.fn();
    button.addEventListener('click', (event) => {
      if (!event.defaultPrevented) {
        clicks();
      }
    });
    button.click();
    expect(clicks).not.toHaveBeenCalled();
    dialogButton('Yes').click();
    await flush();
    expect(clicks).toHaveBeenCalledTimes(1);
  });

  it('always blocks when blocked, and lets an empty message through without asking', async () => {
    await start(`
      <form data-controller="confirm-submit" data-confirm-submit-message-value="Delete?" data-confirm-submit-blocked-value="true"></form>
      <form data-controller="confirm-submit" data-confirm-submit-message-value="  "></form>`);
    const [blocked, empty] = Array.from(document.querySelectorAll('form'));
    const controller = (form: Element): ConfirmSubmit =>
      application.getControllerForElementAndIdentifier(form, 'confirm-submit') as ConfirmSubmit;

    const first = new Event('submit', { cancelable: true });
    controller(blocked).confirm(first);
    expect(first.defaultPrevented).toBe(true);

    const second = new Event('submit', { cancelable: true });
    controller(empty).confirm(second);
    expect(second.defaultPrevented).toBe(false);
    expect(document.querySelector('dialog')).toBeNull();
  });

  // 2.23 (audit CK L2): a replayed submit that never reaches the peer must not leave it approved
  it('clears the approval when the replayed submit is stopped before the peer, so the next submit asks again', async () => {
    await start(`
      <form data-controller="confirm-submit" data-confirm-submit-message-value="Delete?" data-action="submit->confirm-submit#confirm"></form>`);
    const form = document.querySelector('form')!;
    let stopNext = false;
    form.addEventListener(
      'submit',
      (event) => {
        if (stopNext) {
          stopNext = false;
          event.preventDefault();
          event.stopImmediatePropagation();
        }
      },
      true,
    );
    const sent = vi.fn();
    form.addEventListener('submit', (event) => {
      if (!event.defaultPrevented) {
        sent();
      }
      event.preventDefault();
    });

    form.requestSubmit();
    expect(document.querySelectorAll('dialog').length).toBe(1);
    stopNext = true;
    dialogButton('Yes').click();
    await flush();
    expect(sent).not.toHaveBeenCalled();

    // the next submit is held for confirmation again instead of going through
    document.querySelectorAll('dialog').forEach((dialog) => dialog.remove());
    form.requestSubmit();
    expect(sent).not.toHaveBeenCalled();
    expect(document.querySelectorAll('dialog').length).toBe(1);
  });

  // 2.23 (audit CS L2): a removed controller no longer marks the form, so the kit runtime's fallback confirms it
  it('clears the peer mark on disconnect', async () => {
    await start(`<form data-controller="confirm-submit" data-confirm-submit-message-value="Delete?"></form>`);
    const form = document.querySelector('form')!;
    expect((form as unknown as Record<symbol, unknown>)[CONFIRM_SUBMIT_PEER_MARK]).toBe(true);
    (application.getControllerForElementAndIdentifier(form, 'confirm-submit') as ConfirmSubmit).disconnect();
    expect(CONFIRM_SUBMIT_PEER_MARK in form).toBe(false);
  });

  it('asks in the dialog when one element sets mode=dialog on a native-mode app', async () => {
    document.body.innerHTML = `
      <button type="button" data-controller="native-confirm" data-native-confirm-message-value="Go?" data-native-confirm-mode-value="dialog"
              data-action="click->native-confirm#confirm">Go</button>`;
    application = Application.start();
    application.register('native-confirm', createConfirmSubmitController());
    await Promise.resolve();
    const nativeConfirm = vi.spyOn(window, 'confirm');
    document.querySelector<HTMLButtonElement>('button')!.click();
    expect(nativeConfirm).not.toHaveBeenCalled();
    expect(document.querySelector('dialog.nowo-ui-confirm h2')!.textContent).toBe('Go?');
  });
});

describe('confirm-submit peer, title and labels (2.70)', () => {
  let application: Application;

  const start = async (html: string, mode: 'dialog' | 'native' = 'dialog'): Promise<HTMLFormElement> => {
    document.body.innerHTML = html;
    application = Application.start();
    application.register('confirm-submit', createConfirmSubmitController({ mode }));
    await Promise.resolve();
    return document.querySelector('form')!;
  };

  afterEach(() => {
    application.unload('confirm-submit');
    application.stop();
    document.body.innerHTML = '';
    vi.restoreAllMocks();
  });

  it('shows the title as the heading, the message as the body and the confirm / cancel labels (plain attributes)', async () => {
    const form = await start(`
      <form data-controller="confirm-submit" data-confirm-submit-message-value="Ann loses access." data-confirm-title="Remove Ann?"
            data-confirm-label="Remove" data-confirm-cancel-label="Keep" data-action="submit->confirm-submit#confirm"><button type="submit">x</button></form>`);
    form.querySelector('button')!.click();
    const dialog = document.querySelector('dialog')!;
    expect(dialog.querySelector('h2')!.textContent).toBe('Remove Ann?');
    expect(dialog.querySelector('.nowo-ui-confirm__body')!.textContent).toBe('Ann loses access.');
    const buttons = Array.from(dialog.querySelectorAll<HTMLButtonElement>('.nowo-ui-confirm__footer button')).map((b) => b.textContent);
    expect(buttons).toEqual(['Keep', 'Remove']);
  });

  it('Stimulus values win over the plain attributes; a title alone is asked', async () => {
    const form = await start(`
      <form data-controller="confirm-submit" data-confirm-submit-title-value="Value title" data-confirm-title="Plain title"
            data-confirm-submit-confirm-label-value="Go" data-action="submit->confirm-submit#confirm"><button type="submit">x</button></form>`);
    form.querySelector('button')!.click();
    const dialog = document.querySelector('dialog')!;
    expect(dialog.querySelector('h2')!.textContent).toBe('Value title');
    expect(dialog.querySelector('.nowo-ui-confirm__body')).toBeNull();
    expect(Array.from(dialog.querySelectorAll('.nowo-ui-confirm__footer button')).map((b) => b.textContent)).toEqual(['Cancel', 'Go']);
  });

  it('native mode asks the title, a blank line and the message', async () => {
    const form = await start(
      `<form data-controller="confirm-submit" data-confirm-submit-message-value="Gone." data-confirm-submit-title-value="Delete?" data-action="submit->confirm-submit#confirm"></form>`,
      'native',
    );
    const ask = vi.spyOn(window, 'confirm').mockReturnValue(false);
    const event = new Event('submit', { cancelable: true });
    form.dispatchEvent(event);
    expect(ask).toHaveBeenCalledWith('Delete?\n\nGone.');
    expect(event.defaultPrevented).toBe(true);
  });

  it('without message and title the submit goes through unasked (BC)', async () => {
    const form = await start(`<form data-controller="confirm-submit" data-confirm-submit-message-value="  " data-action="submit->confirm-submit#confirm"></form>`, 'native');
    const ask = vi.spyOn(window, 'confirm');
    const event = new Event('submit', { cancelable: true });
    form.dispatchEvent(event);
    expect(ask).not.toHaveBeenCalled();
    expect(event.defaultPrevented).toBe(false);
  });
});
