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

  describe('link interception', () => {
    const mountWithLinks = async (): Promise<HTMLElement> => {
      document.body.innerHTML = `
        <div data-controller="page-loader" data-nowo-ui-page-loader hidden
             data-page-loader-min-visible-value="0" data-page-loader-leave-ms-value="0"
             data-page-loader-link-delay-value="0"></div>
        <a id="toggle" href="/menu">Menu</a>
        <a id="next" href="/next">Next</a>
        <a id="opt-out" href="/next" data-no-page-loader>Opt out</a>
        <a id="opt-out-true" href="/next" data-no-page-loader="true">Opt out</a>`;
      await vi.advanceTimersByTimeAsync(0);
      const overlay = document.querySelector('[data-controller="page-loader"]') as HTMLElement;
      // Let the initial show / leave finish so the overlay starts hidden.
      await vi.advanceTimersByTimeAsync(1000);
      expect(overlay.hidden).toBe(true);
      return overlay;
    };

    const click = (id: string): MouseEvent => {
      const event = new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 });
      document.getElementById(id)!.dispatchEvent(event);
      return event;
    };

    it('does not show the overlay when a later handler cancels the click', async () => {
      const overlay = await mountWithLinks();
      // A toggle handler on the link itself, after the capture-phase listener (the Beacon report).
      document.getElementById('toggle')!.addEventListener('click', (event) => event.preventDefault());

      const event = click('toggle');
      expect(event.defaultPrevented).toBe(true);
      await vi.advanceTimersByTimeAsync(100);

      expect(overlay.hidden).toBe(true);
      expect(document.documentElement.classList.contains('is-page-loading')).toBe(false);
    });

    it('does not show the overlay when a document bubble handler cancels the click', async () => {
      const overlay = await mountWithLinks();
      const cancel = (event: Event): void => event.preventDefault();
      document.addEventListener('click', cancel);
      try {
        click('next');
        await vi.advanceTimersByTimeAsync(100);
        expect(overlay.hidden).toBe(true);
      } finally {
        document.removeEventListener('click', cancel);
      }
    });

    it('shows the overlay for a same-origin link click that is not cancelled', async () => {
      const overlay = await mountWithLinks();
      const event = click('next');
      expect(event.defaultPrevented).toBe(false);
      await vi.advanceTimersByTimeAsync(0);

      expect(overlay.hidden).toBe(false);
      expect(document.documentElement.classList.contains('is-page-loading')).toBe(true);
    });

    it('keeps data-no-page-loader opting a link out', async () => {
      const overlay = await mountWithLinks();
      click('opt-out');
      click('opt-out-true');
      await vi.advanceTimersByTimeAsync(100);

      expect(overlay.hidden).toBe(true);
    });
  });
});
