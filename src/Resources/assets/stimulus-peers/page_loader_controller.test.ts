import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import PageLoaderController from './page_loader_controller';

let app: Application;

const transitionEnd = (target: Element, propertyName: string): void => {
  target.dispatchEvent(Object.assign(new Event('transitionend', { bubbles: true }), { propertyName }));
};

const mount = async (): Promise<HTMLElement> => {
  document.body.innerHTML = `
    <div class="page-loader is-active" data-controller="page-loader"
         data-page-loader-min-visible-value="0" data-page-loader-leave-ms-value="400">
      <span class="child"></span>
    </div>`;
  // Stimulus connects on a microtask; the min-visible wait (0 ms) then starts the leave.
  await vi.advanceTimersByTimeAsync(0);
  return document.querySelector('[data-controller="page-loader"]') as HTMLElement;
};

beforeEach(() => {
  vi.useFakeTimers();
  app = Application.start();
  app.register('page-loader', PageLoaderController);
});

afterEach(() => {
  app.stop();
  document.body.innerHTML = '';
  vi.useRealTimers();
});

describe('page-loader peer', () => {
  it('ignores transitionend bubbling from children or other properties', async () => {
    const overlay = await mount();
    expect(overlay.classList.contains('is-leaving')).toBe(true);

    transitionEnd(overlay.querySelector('.child')!, 'opacity');
    expect(overlay.hidden).toBe(false);

    transitionEnd(overlay, 'visibility');
    expect(overlay.hidden).toBe(false);

    transitionEnd(overlay, 'opacity');
    expect(overlay.hidden).toBe(true);
    expect(overlay.classList.contains('is-leaving')).toBe(false);
  });

  it('falls back to the leave timeout when no transition fires', async () => {
    const overlay = await mount();
    await vi.advanceTimersByTimeAsync(479);
    expect(overlay.hidden).toBe(false);
    await vi.advanceTimersByTimeAsync(1);
    expect(overlay.hidden).toBe(true);
  });

  it('does not hide an overlay shown again before the leave finished', async () => {
    const overlay = await mount();
    const controller = app.getControllerForElementAndIdentifier(overlay, 'page-loader') as unknown as {
      show(): void;
    };
    controller.show();
    await vi.advanceTimersByTimeAsync(1000);
    transitionEnd(overlay, 'opacity');
    expect(overlay.hidden).toBe(false);
    expect(overlay.classList.contains('is-leaving')).toBe(false);
  });
});
