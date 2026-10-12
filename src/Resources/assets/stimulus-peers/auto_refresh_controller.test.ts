import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import AutoRefreshController from './auto_refresh_controller';

let app: Application;

const page = (count: string): string => `
  <div data-controller="auto-refresh" data-auto-refresh-interval-value="2000">
    <button data-auto-refresh-target="toggle" data-action="auto-refresh#toggle">auto</button>
    <span data-auto-refresh-target="status"></span>
    <div id="region-a" data-auto-refresh-region><span class="count">${count}</span></div>
    <div data-auto-refresh-region>no id — never replaced</div>
  </div>`;

const mount = async (markup: string = page('1')): Promise<HTMLElement> => {
  document.body.innerHTML = markup;
  app = Application.start();
  app.register('auto-refresh', AutoRefreshController);
  await vi.advanceTimersByTimeAsync(0);
  return document.querySelector('[data-controller="auto-refresh"]') as HTMLElement;
};

const htmlResponse = (body: string, ok = true): Response =>
  ({ ok, text: () => Promise.resolve(body) }) as unknown as Response;

beforeEach(() => {
  vi.useFakeTimers();
});

afterEach(() => {
  app?.stop();
  document.body.innerHTML = '';
  vi.unstubAllGlobals();
  vi.useRealTimers();
});

describe('auto-refresh', () => {
  it('replaces regions by id on each tick and stamps the status', async () => {
    const fetchMock = vi.fn().mockResolvedValue(htmlResponse(page('7')));
    vi.stubGlobal('fetch', fetchMock);
    await mount();

    await vi.advanceTimersByTimeAsync(2_000);

    expect(fetchMock).toHaveBeenCalledWith(window.location.href, expect.objectContaining({ credentials: 'same-origin' }));
    expect(document.querySelector('#region-a .count')?.textContent).toBe('7');
    expect(document.querySelector('[data-auto-refresh-target="status"]')?.textContent).not.toBe('');
    expect(document.querySelector('[data-auto-refresh-target="toggle"]')?.getAttribute('aria-pressed')).toBe('true');
  });

  it('pauses and resumes from the toggle', async () => {
    const fetchMock = vi.fn().mockResolvedValue(htmlResponse(page('2')));
    vi.stubGlobal('fetch', fetchMock);
    await mount();
    const toggle = document.querySelector('[data-auto-refresh-target="toggle"]') as HTMLButtonElement;

    toggle.click();
    expect(toggle.getAttribute('aria-pressed')).toBe('false');
    await vi.advanceTimersByTimeAsync(4_000);
    expect(fetchMock).not.toHaveBeenCalled();

    toggle.click();
    await vi.advanceTimersByTimeAsync(0);
    expect(toggle.getAttribute('aria-pressed')).toBe('true');
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it('skips hidden tabs, ignores failed responses and network errors', async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(htmlResponse('', false))
      .mockRejectedValueOnce(new Error('offline'));
    vi.stubGlobal('fetch', fetchMock);
    await mount();

    const visibility = vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('hidden');
    await vi.advanceTimersByTimeAsync(2_000);
    expect(fetchMock).not.toHaveBeenCalled();
    visibility.mockReturnValue('visible');

    await vi.advanceTimersByTimeAsync(2_000);
    await vi.advanceTimersByTimeAsync(2_000);
    expect(fetchMock).toHaveBeenCalledTimes(2);
    expect(document.querySelector('#region-a .count')?.textContent).toBe('1');
  });

  it('stops ticking on disconnect', async () => {
    const fetchMock = vi.fn().mockResolvedValue(htmlResponse(page('3')));
    vi.stubGlobal('fetch', fetchMock);
    await mount();
    document.body.innerHTML = '';
    await vi.advanceTimersByTimeAsync(0);

    await vi.advanceTimersByTimeAsync(10_000);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('uses the url value, dispatches refreshed and works without toggle / status targets', async () => {
    const fetchMock = vi.fn().mockResolvedValue(htmlResponse('<div id="r">new</div>'));
    vi.stubGlobal('fetch', fetchMock);
    const root = await mount(`
      <div data-controller="auto-refresh" data-auto-refresh-interval-value="10"
           data-auto-refresh-url-value="/fragment">
        <div id="r" data-auto-refresh-region>old</div>
      </div>`);
    const events: unknown[] = [];
    root.addEventListener('auto-refresh:refreshed', (e) => events.push((e as CustomEvent).detail));

    // Interval floor: 10 ms is raised to 2 s.
    await vi.advanceTimersByTimeAsync(1_000);
    expect(fetchMock).not.toHaveBeenCalled();
    await vi.advanceTimersByTimeAsync(1_000);

    expect(fetchMock).toHaveBeenCalledWith('/fragment', expect.anything());
    expect(document.getElementById('r')?.textContent).toBe('new');
    expect(events).toEqual([{ regions: 1 }]);
  });

  it('skips a tick while a request is still in flight', async () => {
    let resolve: (r: Response) => void = () => {};
    const fetchMock = vi.fn().mockReturnValue(new Promise<Response>((r) => (resolve = r)));
    vi.stubGlobal('fetch', fetchMock);
    await mount();

    await vi.advanceTimersByTimeAsync(2_000);
    await vi.advanceTimersByTimeAsync(2_000);
    expect(fetchMock).toHaveBeenCalledTimes(1);
    resolve(htmlResponse(page('9')));
    await vi.advanceTimersByTimeAsync(0);
    expect(document.querySelector('#region-a .count')?.textContent).toBe('9');
  });
});
