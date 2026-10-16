import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import DisclosureIconController from './disclosure_icon_controller';
import { setIconStateHandler, syncIconState } from './icon_state';

const tick = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));
let app: Application;
const handler = vi.fn();

beforeEach(() => {
  handler.mockClear();
  setIconStateHandler(handler);
  app = Application.start();
  app.register('disclosure-icon', DisclosureIconController);
});

afterEach(() => {
  app.stop();
  document.body.innerHTML = '';
  setIconStateHandler(null);
});

describe('disclosure-icon peer', () => {
  it('follows aria-expanded on a toggle button', async () => {
    document.body.innerHTML = '<button type="button" data-controller="disclosure-icon" aria-expanded="true">Section</button>';
    await tick();
    const button = document.querySelector('button') as HTMLButtonElement;
    expect(handler).toHaveBeenLastCalledWith('disclosure', button, 'open', false);
    button.setAttribute('aria-expanded', 'false');
    await tick();
    expect(handler).toHaveBeenLastCalledWith('disclosure', button, 'closed', true);
    button.remove();
    await tick();
  });

  it('follows the open state of a details element from its summary', async () => {
    document.body.innerHTML = '<details><summary data-controller="disclosure-icon">Frame</summary><p>Code</p></details>';
    await tick();
    const summary = document.querySelector('summary') as HTMLElement;
    const details = document.querySelector('details') as HTMLDetailsElement;
    expect(handler).toHaveBeenLastCalledWith('disclosure', summary, 'closed', false);
    details.open = true;
    details.dispatchEvent(new Event('toggle'));
    expect(handler).toHaveBeenLastCalledWith('disclosure', summary, 'open', true);
    details.remove();
    await tick();
  });
});

describe('icon state bridge', () => {
  it('is a no-op without a handler', () => {
    setIconStateHandler(null);
    expect(() => syncIconState('disclosure', document.body, 'open', true)).not.toThrow();
    expect(handler).not.toHaveBeenCalled();
  });
});
