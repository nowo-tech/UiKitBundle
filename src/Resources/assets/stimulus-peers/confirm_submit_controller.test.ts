import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createConfirmSubmitController } from './confirm_submit_controller';

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
