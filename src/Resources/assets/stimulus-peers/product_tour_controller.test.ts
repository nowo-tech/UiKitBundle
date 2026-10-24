import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type { Config } from 'driver.js';

import ProductTourController, { markTourSeen, PRODUCT_TOUR_STORAGE_KEY, seenTours, type DriverFactory } from './product_tour_controller';

type FakeDriver = {
  config: Config;
  index: number;
  activeFlag: boolean;
  drive: () => void;
  isActive: () => boolean;
  hasNextStep: () => boolean;
  destroy: () => void;
  /** What the popover's close button / Esc does: the onDestroyStarted hook decides. */
  close: () => void;
  next: () => void;
};

const drivers: FakeDriver[] = [];

/** driver.js hook options (`config`, `state`, `driver`, …) as the fake passes them. */
const hookOpts = (config: Config, driver: FakeDriver): never => ({ config, state: {}, driver }) as never;

function fakeDriver(config: Config): FakeDriver {
  const fake: FakeDriver = {
    config,
    index: 0,
    activeFlag: false,
    drive() {
      fake.activeFlag = true;
      const step = config.steps?.[0];
      const element = step?.element ? document.querySelector(step.element as string) ?? undefined : undefined;
      config.onHighlightStarted?.(element, step ?? {}, hookOpts(config, fake));
      const closeButton = document.createElement('button');
      config.onPopoverRender?.({ closeButton } as never, hookOpts(config, fake));
      document.body.append(closeButton);
      closeButton.dataset.fakeClose = '';
    },
    isActive: () => fake.activeFlag,
    hasNextStep: () => fake.index < (config.steps?.length ?? 0) - 1,
    destroy() {
      fake.activeFlag = false;
      config.onDestroyed?.(undefined, {}, hookOpts(config, fake));
    },
    close() {
      if (config.onDestroyStarted) {
        config.onDestroyStarted(undefined, {}, hookOpts(config, fake));
      } else {
        fake.destroy();
      }
    },
    next() {
      fake.index += 1;
    },
  };
  drivers.push(fake);

  return fake;
}

vi.mock('driver.js', () => ({ driver: (config: Config) => fakeDriver(config) }));

const STEPS = [
  { popover: { title: 'Welcome <b>', description: 'Hi & bye' } },
  { element: '[data-tour="nav"]', popover: { title: 'Nav', description: 'The nav', side: 'right', align: 'start' } },
  { element: '[data-tour="missing"]', popover: { title: 'Gone', description: 'x' } },
  { element: '[data-tour="menu-item"]', popover: { title: 'Menu', description: 'In a menu', side: 'diagonal', align: 'middle' } },
  { element: 'p[', popover: { title: 'Bad selector' } },
  { element: '[data-tour="nav"]' },
];
const LABELS = { next: 'Next!', previous: 'Prev!', done: 'Done!', close: 'Close tour', progress: '{{current}} of {{total}}' };

let app: Application;
let reduced = false;

const tick = (): Promise<void> => new Promise((r) => setTimeout(r, 0));
const frame = (): Promise<void> => new Promise((r) => requestAnimationFrame(() => setTimeout(r, 0)));

function mount(attrs: Record<string, string> = {}, steps: unknown = STEPS): HTMLElement {
  const data = { 'data-product-tour-key-value': 'dashboard', 'data-product-tour-steps-value': JSON.stringify(steps), 'data-product-tour-labels-value': JSON.stringify(LABELS), ...attrs };
  const attributes = Object.entries(data)
    .map(([name, value]) => `${name}='${value}'`)
    .join(' ');
  document.body.innerHTML = `
    <nav data-tour="nav">Nav</nav>
    <details id="outer"><summary>User</summary><details id="inner"><summary>More</summary><a data-tour="menu-item" href="#">Item</a></details></details>
    <div id="tour" data-controller="product-tour" data-action="nowo-ui-tour:start@window->product-tour#start" ${attributes}>
      <button type="button" data-product-tour-target="tab" data-action="product-tour#start">Tour</button>
    </div>`;

  return document.getElementById('tour')!;
}

function controller(): ProductTourController {
  return app.getControllerForElementAndIdentifier(document.getElementById('tour')!, 'product-tour') as ProductTourController;
}

beforeEach(() => {
  drivers.length = 0;
  reduced = false;
  window.localStorage.clear();
  window.matchMedia = vi.fn().mockImplementation((query: string) => ({ matches: query === '(prefers-reduced-motion: reduce)' && reduced, media: query })) as unknown as typeof window.matchMedia;
  app = Application.start();
  app.register('product-tour', ProductTourController);
});

afterEach(() => {
  app.stop();
  document.body.innerHTML = '';
  delete document.documentElement.dataset.motion;
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
});

describe('seen map helpers', () => {
  it('reads, writes and removes keys and survives broken storage', () => {
    expect(seenTours()).toEqual({});
    expect(markTourSeen('a')).toBe(true);
    expect(Object.keys(seenTours(PRODUCT_TOUR_STORAGE_KEY))).toEqual(['a']);
    expect(markTourSeen('a', false)).toBe(true);
    expect(seenTours()).toEqual({});

    window.localStorage.setItem(PRODUCT_TOUR_STORAGE_KEY, '[1]');
    expect(seenTours()).toEqual({});
    window.localStorage.setItem(PRODUCT_TOUR_STORAGE_KEY, '{oops');
    expect(seenTours()).toEqual({});
    window.localStorage.setItem(PRODUCT_TOUR_STORAGE_KEY, 'null');
    expect(seenTours()).toEqual({});

    vi.spyOn(window, 'localStorage', 'get').mockImplementation(() => {
      throw new Error('blocked');
    });
    expect(markTourSeen('b')).toBe(false);
    expect(seenTours()).toEqual({});
  });
});

describe('product-tour peer', () => {
  it('auto-starts once with the steps on the page, escaped, and marks the key seen on close', async () => {
    const root = mount();
    const events: string[] = [];
    root.addEventListener('product-tour:started', (e) => events.push(`started:${(e as CustomEvent).detail.steps.length}`));
    root.addEventListener('product-tour:ended', (e) => events.push(`ended:${(e as CustomEvent).detail.completed}`));
    await frame();
    await tick();

    expect(drivers).toHaveLength(1);
    const { config } = drivers[0];
    expect(config.steps).toEqual([
      { popover: { title: 'Welcome &lt;b&gt;', description: 'Hi &amp; bye' } },
      { element: '[data-tour="nav"]', popover: { title: 'Nav', description: 'The nav', side: 'right', align: 'start' } },
      { element: '[data-tour="menu-item"]', popover: { title: 'Menu', description: 'In a menu' } },
    ]);
    expect(config).toMatchObject({ animate: true, smoothScroll: true, showProgress: true, allowClose: true, popoverClass: 'nowo-ui-tour-popover', nextBtnText: 'Next!', prevBtnText: 'Prev!', doneBtnText: 'Done!', progressText: '{{current}} of {{total}}' });
    expect(root.hasAttribute('data-tour-active')).toBe(true);
    const close = document.querySelector<HTMLButtonElement>('[data-fake-close]')!;
    expect(close.getAttribute('aria-label')).toBe('Close tour');
    expect(close.title).toBe('Close tour');
    expect(events).toEqual(['started:3']);

    // Highlighting the menu item opened both closed <details>.
    config.onHighlightStarted?.(document.querySelector('[data-tour="menu-item"]')!, {}, hookOpts(config, drivers[0]));
    expect((document.getElementById('outer') as HTMLDetailsElement).open).toBe(true);
    expect((document.getElementById('inner') as HTMLDetailsElement).open).toBe(true);

    // A replay while it runs does nothing.
    await controller().start();
    expect(drivers).toHaveLength(1);

    drivers[0].close();
    expect(events).toEqual(['started:3', 'ended:false']);
    expect(root.hasAttribute('data-tour-active')).toBe(false);
    expect((document.getElementById('outer') as HTMLDetailsElement).open).toBe(false);
    expect(Object.keys(seenTours())).toEqual(['dashboard']);
    expect(controller().isSeen()).toBe(true);
  });

  it('does not auto-start a seen tour, replays it from the tab and reports completion', async () => {
    markTourSeen('dashboard');
    const root = mount();
    await frame();
    expect(drivers).toHaveLength(0);

    const ended = vi.fn();
    root.addEventListener('product-tour:ended', (e) => ended((e as CustomEvent).detail));
    root.querySelector<HTMLButtonElement>('[data-product-tour-target="tab"]')!.click();
    await tick();
    await tick();
    expect(drivers).toHaveLength(1);
    drivers[0].next();
    drivers[0].next();
    drivers[0].close();
    expect(ended).toHaveBeenCalledWith({ key: 'dashboard', completed: true });

    // A window event starts it too (host menu item outside the root).
    window.dispatchEvent(new Event('nowo-ui-tour:start'));
    await tick();
    await tick();
    expect(drivers).toHaveLength(2);
    controller().stop();
    expect(drivers[1].isActive()).toBe(false);
  });

  it('honours autoStart false, force, a custom storage key and reduced motion', async () => {
    mount({ 'data-product-tour-auto-start-value': 'false' });
    await frame();
    expect(drivers).toHaveLength(0);
    app.stop();

    markTourSeen('dashboard', true, 'my-tours');
    document.documentElement.dataset.motion = 'reduce';
    app = Application.start();
    app.register('product-tour', ProductTourController);
    mount({ 'data-product-tour-force-value': 'true', 'data-product-tour-storage-key-value': 'my-tours', 'data-product-tour-popover-class-value': 'app-pop' }, [{ popover: { title: '<i>T</i>' } }]);
    await frame();
    await tick();
    expect(drivers).toHaveLength(1);
    expect(drivers[0].config).toMatchObject({ animate: false, smoothScroll: false, showProgress: false, popoverClass: 'app-pop' });
    expect(controller().isSeen()).toBe(true);
    expect(seenTours()).toEqual({});
  });

  it('uses the OS reduced-motion preference, trusted HTML and default labels', async () => {
    reduced = true;
    mount({ 'data-product-tour-html-value': 'true', 'data-product-tour-labels-value': '{}' }, [{ popover: { title: '<i>T</i>' } }, { popover: { description: 'D' } }]);
    await frame();
    await tick();
    expect(drivers[0].config).toMatchObject({ animate: false, nextBtnText: 'Next', prevBtnText: 'Previous', doneBtnText: 'Done', progressText: '{{current}} / {{total}}' });
    expect(drivers[0].config.steps).toEqual([{ popover: { title: '<i>T</i>', description: '' } }, { popover: { title: '', description: 'D' } }]);
    expect(document.querySelector('[data-fake-close]')!.hasAttribute('aria-label')).toBe(false);
  });

  it('marks a tour without visible steps seen and drops the force query parameter', async () => {
    window.history.replaceState(null, '', '/page?tour=1&x=2#h');
    mount({ 'data-product-tour-force-value': 'true', 'data-product-tour-force-param-value': 'tour' }, [{ element: '[data-tour="missing"]', popover: { title: 'x' } }, null, 'junk']);
    await frame();
    await tick();
    expect(drivers).toHaveLength(0);
    expect(Object.keys(seenTours())).toEqual(['dashboard']);
    expect(window.location.pathname + window.location.search + window.location.hash).toBe('/page?x=2#h');

    // No parameter in the URL: nothing to drop.
    await controller().start();
    expect(window.location.search).toBe('?x=2');
  });

  it('keeps an empty key out of storage', async () => {
    mount({ 'data-product-tour-key-value': '' }, []);
    await frame();
    await tick();
    expect(controller().steps()).toEqual([]);
    expect(controller().isSeen()).toBe(false);
    expect(seenTours()).toEqual({});
  });

  it('tears a running tour down on disconnect without marking it seen', async () => {
    const root = mount();
    const ended = vi.fn();
    root.addEventListener('product-tour:ended', ended);
    await frame();
    await tick();
    expect(drivers).toHaveLength(1);
    root.remove();
    await tick();
    expect(drivers[0].isActive()).toBe(false);
    expect(ended).not.toHaveBeenCalled();
    expect(seenTours()).toEqual({});
  });

  it('ignores a driver that resolves after the root left the page', async () => {
    let resolve: (factory: DriverFactory) => void = () => undefined;
    class Slow extends ProductTourController {
      protected override driverFactory(): Promise<DriverFactory> {
        return new Promise((r) => {
          resolve = r;
        });
      }
    }
    app.register('slow-tour', Slow);
    document.body.innerHTML = `<div id="slow" data-controller="slow-tour" data-slow-tour-key-value="k" data-slow-tour-auto-start-value="false" data-slow-tour-steps-value='[{"popover":{"title":"T"}}]'></div>`;
    await tick();
    const slow = app.getControllerForElementAndIdentifier(document.getElementById('slow')!, 'slow-tour') as Slow;
    const first = slow.start();
    expect(slow.start()).toBe(first);
    document.body.innerHTML = '';
    resolve(fakeDriver as unknown as DriverFactory);
    await first;
    expect(drivers).toHaveLength(0);
  });

  it('dispatches unavailable when driver.js cannot be loaded', async () => {
    class Broken extends ProductTourController {
      protected override driverFactory(): Promise<DriverFactory> {
        return Promise.reject(new Error('no driver.js'));
      }
    }
    app.register('broken-tour', Broken);
    document.body.innerHTML = `<div id="b" data-controller="broken-tour" data-broken-tour-key-value="k" data-broken-tour-steps-value='[{"popover":{"title":"T"}}]'></div>`;
    const unavailable = vi.fn();
    document.getElementById('b')!.addEventListener('broken-tour:unavailable', (e) => unavailable((e as CustomEvent).detail.key));
    await frame();
    await tick();
    expect(unavailable).toHaveBeenCalledWith('k');
  });

  describe('server persistence', () => {
    const seenAttrs = { 'data-product-tour-seen-url-value': '/tour/seen', 'data-product-tour-seen-token-value': 'tok', 'data-product-tour-seen-token-url-value': '/tour/token' };

    it('posts the key once with the CSRF header', async () => {
      const fetchFn = vi.fn(async () => ({ ok: true, status: 204 }));
      vi.stubGlobal('fetch', fetchFn);
      mount(seenAttrs);
      await frame();
      await tick();
      drivers[0].close();
      await tick();
      expect(fetchFn).toHaveBeenCalledTimes(1);
      expect(fetchFn).toHaveBeenCalledWith('/tour/seen', {
        method: 'POST',
        credentials: 'same-origin',
        keepalive: true,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': 'tok' },
        body: JSON.stringify({ key: 'dashboard' }),
      });

      await controller().start();
      drivers[1].close();
      await tick();
      expect(fetchFn).toHaveBeenCalledTimes(1);
    });

    it('refreshes a stale token once, with a custom method', async () => {
      const fetchFn = vi
        .fn()
        .mockResolvedValueOnce({ ok: false, status: 403 })
        .mockResolvedValueOnce({ ok: true, status: 200, json: async () => ({ token: 'fresh' }) })
        .mockResolvedValueOnce({ ok: true, status: 204 });
      vi.stubGlobal('fetch', fetchFn);
      mount({ ...seenAttrs, 'data-product-tour-seen-method-value': 'PUT' });
      await frame();
      await tick();
      drivers[0].close();
      await tick();
      await tick();
      expect(fetchFn).toHaveBeenCalledTimes(3);
      expect(fetchFn.mock.calls[1][0]).toBe('/tour/token');
      expect(fetchFn.mock.calls[2][1]).toMatchObject({ method: 'PUT', headers: { 'X-CSRF-TOKEN': 'fresh' } });
    });

    it.each([
      ['no token url', { 'data-product-tour-seen-token-url-value': '' }, [{ ok: false, status: 403 }]],
      ['an empty token', {}, [{ ok: false, status: 403 }, { ok: true, status: 200, json: async () => ({ token: '' }) }]],
      ['a failing token request', {}, [{ ok: false, status: 403 }, new Error('offline')]],
      ['a server error', {}, [{ ok: false, status: 500 }]],
      ['a network error', {}, [new Error('offline')]],
    ])('dispatches save-failed on %s and retries on the next end', async (_name, extra, responses) => {
      const fetchFn = vi.fn();
      for (const response of responses) {
        if (response instanceof Error) {
          fetchFn.mockRejectedValueOnce(response);
        } else {
          fetchFn.mockResolvedValueOnce(response);
        }
      }
      fetchFn.mockResolvedValue({ ok: true, status: 204 });
      vi.stubGlobal('fetch', fetchFn);
      const root = mount({ ...seenAttrs, ...extra });
      const failed = vi.fn();
      root.addEventListener('product-tour:save-failed', (e) => failed((e as CustomEvent).detail.key));
      await frame();
      await tick();
      drivers[0].close();
      await tick();
      await tick();
      expect(failed).toHaveBeenCalledWith('dashboard');
      expect(Object.keys(seenTours())).toEqual(['dashboard']);

      const calls = fetchFn.mock.calls.length;
      await controller().start();
      drivers[1].close();
      await tick();
      expect(fetchFn.mock.calls.length).toBe(calls + 1);
    });
  });
});
