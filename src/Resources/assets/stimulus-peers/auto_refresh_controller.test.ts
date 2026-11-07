import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import AutoRefreshController, { isPollableUrl } from './auto_refresh_controller';

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

  describe('same origin only (GT-L4)', () => {
    const withUrl = (url: string): string => `
      <div data-controller="auto-refresh" data-auto-refresh-interval-value="2000" data-auto-refresh-url-value="${url}">
        <div id="r" data-auto-refresh-region>old</div>
      </div>`;

    it('decides as fetch resolves', () => {
      const here = window.location.origin;
      expect(isPollableUrl('/x')).toBe(true);
      expect(isPollableUrl(`${here}/x`)).toBe(true);
      expect(isPollableUrl('/x', `${here}/deep/`)).toBe(true);
      expect(isPollableUrl('/x', 'https://evil.example/')).toBe(false);
      for (const bad of ['https://evil.example/x', '//evil.example/x', 'javascript:alert(1)', 'data:text/html,x', `blob:${here}/uuid`, 'http://[']) {
        expect(isPollableUrl(bad)).toBe(false);
      }
    });

    it.each(['https://evil.example/x', '//evil.example/x', 'javascript:alert(1)'])('sends nothing for %s, warns once, keeps the view', async (url) => {
      const fetchMock = vi.fn();
      const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
      vi.stubGlobal('fetch', fetchMock);
      await mount(withUrl(url));

      await vi.advanceTimersByTimeAsync(4_000);

      expect(fetchMock).not.toHaveBeenCalled();
      expect(warn).toHaveBeenCalledTimes(1);
      expect(document.getElementById('r')?.textContent).toBe('old');
      warn.mockRestore();
    });

    it('asks the browser to fail a redirect to another origin, for the url value and the current location', async () => {
      const fetchMock = vi.fn().mockRejectedValue(new TypeError('redirect refused'));
      vi.stubGlobal('fetch', fetchMock);
      await mount(withUrl('/fragment'));

      await vi.advanceTimersByTimeAsync(2_000);
      expect(fetchMock).toHaveBeenCalledWith('/fragment', expect.objectContaining({ mode: 'same-origin', credentials: 'same-origin' }));
      expect(document.getElementById('r')?.textContent).toBe('old');

      app.stop();
      fetchMock.mockClear();
      await mount();
      await vi.advanceTimersByTimeAsync(2_000);
      expect(fetchMock).toHaveBeenCalledWith(window.location.href, expect.objectContaining({ mode: 'same-origin' }));
    });
  });

  describe('fragment filter on each region (GT-L4)', () => {
    const swap = async (fresh: string): Promise<void> => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue(htmlResponse(`<!doctype html><body>${fresh}</body>`)));
      await mount(`<div data-controller="auto-refresh" data-auto-refresh-interval-value="2000"><div id="r" data-auto-refresh-region>old</div></div>`);
      await vi.advanceTimersByTimeAsync(2_000);
    };

    it('removes handlers, scripts and whatever acts by itself', async () => {
      await swap('<div id="r" onclick="x()"><img src="/i.png" onerror="x()"><script>x()</script><object data="/o"></object><meta http-equiv="refresh" content="0;url=/x"><a href="javascript:x()">a</a><template><img src="/j.png" onerror="x()"></template></div>');
      const region = document.getElementById('r') as HTMLElement;
      expect(region.getAttributeNames()).toEqual(['id']);
      expect(region.querySelector('img')?.getAttributeNames()).toEqual(['src']);
      expect(region.querySelector('script, object, meta')).toBeNull();
      expect(region.querySelector('a')?.hasAttribute('href')).toBe(false);
      expect((region.querySelector('template') as HTMLTemplateElement).content.querySelector('img')?.getAttributeNames()).toEqual(['src']);
    });

    it('never builds the region in the live document before filtering', async () => {
      const importNode = vi.spyOn(Document.prototype, 'importNode');
      await swap('<div id="r"><img src="/i.png" onerror="x()"></div>');
      expect(importNode).toHaveBeenCalledTimes(1);
      expect((importNode.mock.calls[0]?.[0] as Element).querySelector('img')?.hasAttribute('onerror')).toBe(false);
      importNode.mockRestore();
    });

    it('keeps the old region when the fresh element itself is dropped, and does not count it', async () => {
      const seen: unknown[] = [];
      document.addEventListener('auto-refresh:refreshed', (e) => seen.push((e as CustomEvent).detail));
      await swap('<script id="r">x()</script>');
      expect(document.getElementById('r')?.textContent).toBe('old');
      expect(seen).toEqual([{ regions: 0 }]);
    });

    it('leaves legitimate regions as the server wrote them', async () => {
      const fresh = '<section id="r" class="panel" data-auto-refresh-region="" data-testid="x"><a href="/row/1?x=1">Row</a><form method="post" action="/row/1"><input type="hidden" name="_token" value="placeholder"><button>Go</button></form><img src="/i.png" alt=""><svg viewBox="0 0 1 1"><use href="#i"></use></svg><div data-controller="x"></div></section>';
      await swap(fresh);
      expect(document.getElementById('r')?.outerHTML).toBe(fresh);
    });

    it('reads a region id that a form control shadows', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue(htmlResponse('<form id="r"><input name="id" value="new"></form>')));
      await mount('<div data-controller="auto-refresh" data-auto-refresh-interval-value="2000"><form id="r" data-auto-refresh-region><input name="id" value="old"></form></div>');
      const form = document.getElementById('r') as HTMLFormElement;
      Object.defineProperty(form, 'id', { configurable: true, value: form.querySelector('input') });
      Object.defineProperty(form, 'replaceWith', { configurable: true, value: form.querySelector('input') });
      await vi.advanceTimersByTimeAsync(2_000);
      expect(document.querySelector<HTMLInputElement>('#r input')?.value).toBe('new');
    });
  });
});
