import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import ListPaginationController, { APPENDED_EVENT, findRowsRegion, normaliseMode, resolveMode, sameOriginUrl } from './list_pagination_controller';

const flush = async (): Promise<void> => {
  for (let i = 0; i < 6; i += 1) {
    await new Promise((resolve) => setTimeout(resolve, 0));
  }
};

/** IntersectionObserver stand-in: `trigger()` reports every observed element as intersecting. */
class FakeObserver {
  static instances: FakeObserver[] = [];

  elements: Element[] = [];

  disconnected = false;

  constructor(private readonly callback: IntersectionObserverCallback) {
    FakeObserver.instances.push(this);
  }

  observe(element: Element): void {
    this.elements.push(element);
  }

  disconnect(): void {
    this.disconnected = true;
  }

  static trigger(): void {
    FakeObserver.instances
      .filter((o) => !o.disconnected)
      .forEach((o) => o.callback(o.elements.map((target) => ({ isIntersecting: true, target }) as unknown as IntersectionObserverEntry), o as unknown as IntersectionObserver));
  }
}

/** matchMedia stand-in with a switchable `matches`. */
function fakeMedia(initial: boolean): { set: (matches: boolean) => void } {
  const listeners: (() => void)[] = [];
  const mql = {
    matches: initial,
    addEventListener: (_: string, listener: () => void) => listeners.push(listener),
    removeEventListener: vi.fn(),
  };
  vi.stubGlobal('matchMedia', () => mql);
  window.matchMedia = (() => mql) as unknown as typeof window.matchMedia;

  return {
    set: (matches: boolean) => {
      mql.matches = matches;
      listeners.forEach((listener) => listener());
    },
  };
}

function pageHtml(page: number, last: number): string {
  const rows = [1, 2].map((i) => `<tr class="row"><td data-label="Name">p${page}-r${i}</td><td><form data-controller="confirm-submit"><button>Delete</button></form></td></tr>`).join('');
  const next = page < last ? ` data-nowo-ui-pagination-next="/monitors?status=up&amp;per_page=2&amp;page=${page + 1}"` : '';

  return `
    <section data-nowo-ui-page="list">
      <div data-nowo-ui-region="table"><table><tbody data-nowo-ui-list-rows>${rows}<script>window.__pwned = true</script></tbody></table></div>
      <div data-nowo-ui-region="confirms"><dialog id="confirm-p${page}"></dialog></div>
      <div class="pagination" data-controller="list-pagination" data-nowo-ui-pagination-mode="infinite" data-nowo-ui-pagination-narrow="infinite"
           data-nowo-ui-pagination-wide="infinite" data-nowo-ui-pagination-query="(max-width: 900px)" data-nowo-ui-pagination-auto="2"${next}
           data-nowo-ui-pagination-loaded-text="%count% more rows loaded" data-nowo-ui-pagination-error-text="Could not load.">
        <p class="nowo-ui-pagination__info">Showing page ${page}</p>
        <nav class="nowo-ui-pagination__nav"><a href="/monitors?page=${page + 1}">${page + 1}</a></nav>
        <div class="nowo-ui-pagination__more" data-nowo-ui-pagination-more-region hidden>
          <span data-nowo-ui-pagination-sentinel></span>
          <button type="button" data-nowo-ui-pagination-more>Load more</button>
        </div>
        <p data-nowo-ui-pagination-status></p>
      </div>
    </section>`;
}

let app: Application | undefined;
const fetchMock = vi.fn();

async function mount(html: string): Promise<HTMLElement> {
  document.body.innerHTML = html;
  app = Application.start();
  app.register('list-pagination', ListPaginationController);
  await flush();

  return document.querySelector<HTMLElement>('[data-controller="list-pagination"]')!;
}

const rowTexts = (): string[] => Array.from(document.querySelectorAll('[data-nowo-ui-list-rows] > tr > td:first-child')).map((td) => td.textContent ?? '');

beforeEach(() => {
  FakeObserver.instances = [];
  vi.stubGlobal('IntersectionObserver', FakeObserver);
  fetchMock.mockReset();
  fetchMock.mockImplementation(async (url: string) => {
    const page = Number(new URL(url, 'http://localhost').searchParams.get('page'));

    return { ok: true, status: 200, text: async () => `<html><body>${pageHtml(page, 3)}</body></html>` };
  });
  vi.stubGlobal('fetch', fetchMock);
  history.replaceState(null, '', '/monitors?status=up');
});

afterEach(() => {
  app?.stop();
  app = undefined;
  document.body.innerHTML = '';
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

describe('list-pagination helpers', () => {
  it('normalises and resolves modes', () => {
    expect(normaliseMode('pager', 'infinite')).toBe('pager');
    expect(normaliseMode('bogus', 'infinite')).toBe('infinite');
    const root = document.createElement('div');
    root.setAttribute('data-nowo-ui-pagination-mode', 'infinite');
    expect(resolveMode(root, false)).toBe('infinite');
    root.setAttribute('data-nowo-ui-pagination-mode', 'responsive');
    expect(resolveMode(root, true)).toBe('infinite');
    expect(resolveMode(root, false)).toBe('pager');
    root.setAttribute('data-nowo-ui-pagination-narrow', 'pager');
    root.setAttribute('data-nowo-ui-pagination-wide', 'infinite');
    expect(resolveMode(root, true)).toBe('pager');
    expect(resolveMode(root, false)).toBe('infinite');
  });

  it('accepts same-origin next URLs only', () => {
    expect(sameOriginUrl('/monitors?page=2', 'https://app.example/monitors')?.toString()).toBe('https://app.example/monitors?page=2');
    expect(sameOriginUrl('//evil.example/x', 'https://app.example/monitors')).toBeNull();
    expect(sameOriginUrl('https://evil.example/', 'https://app.example/monitors')).toBeNull();
    expect(sameOriginUrl('http://[', 'https://app.example/')).toBeNull();
    expect(sameOriginUrl(null)).toBeNull();
    expect(sameOriginUrl('  ')).toBeNull();
  });

  it('finds the rows region in the nearest ancestor that has one', () => {
    document.body.innerHTML = '<div><ul data-nowo-ui-list-rows></ul><div><nav id="p"></nav></div></div>';
    expect(findRowsRegion(document.getElementById('p')!)?.tagName).toBe('UL');
    document.body.innerHTML = '<nav id="p"></nav>';
    expect(findRowsRegion(document.getElementById('p')!)).toBeNull();
  });
});

describe('list-pagination peer, infinite mode', () => {
  it('hides the links, shows the button, appends the next page rows on scroll and stops after the automatic loads', async () => {
    const root = await mount(pageHtml(1, 3));
    const appended = vi.fn();
    document.addEventListener(APPENDED_EVENT, appended);

    expect(root.querySelector<HTMLElement>('.nowo-ui-pagination__nav')!.hidden).toBe(true);
    expect(root.querySelector<HTMLElement>('[data-nowo-ui-pagination-more-region]')!.hidden).toBe(false);

    FakeObserver.trigger();
    await flush();
    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(fetchMock.mock.calls[0]![0]).toBe(`${window.location.origin}/monitors?status=up&per_page=2&page=2`);
    expect(fetchMock.mock.calls[0]![1]).toMatchObject({ credentials: 'same-origin' });
    expect(rowTexts()).toEqual(['p1-r1', 'p1-r2', 'p2-r1', 'p2-r2']);
    expect(document.querySelector('[data-nowo-ui-list-rows] script')).toBeNull();
    expect((window as unknown as Record<string, unknown>).__pwned).toBeUndefined();
    expect(window.location.search).toBe('?status=up&per_page=2&page=2');
    expect(root.querySelector('[data-nowo-ui-pagination-status]')!.textContent).toBe('2 more rows loaded');
    expect(root.querySelector('.nowo-ui-pagination__info')!.textContent).toBe('Showing page 2');
    expect(root.getAttribute('data-nowo-ui-pagination-next')).toContain('page=3');
    expect(document.getElementById('confirm-p2')).not.toBeNull();
    expect(document.querySelectorAll('#confirm-p1').length).toBe(1);
    expect(appended).toHaveBeenCalledTimes(1);
    expect((appended.mock.calls[0]![0] as CustomEvent).detail).toMatchObject({ page: 2 });
    expect(document.querySelector('[data-nowo-ui-list-rows]')!.getAttribute('aria-busy')).toBe('false');

    // second automatic load (auto = 2), then the observer is not re-armed
    FakeObserver.trigger();
    await flush();
    expect(rowTexts()).toHaveLength(6);
    expect(root.hasAttribute('data-nowo-ui-pagination-next')).toBe(false);
    expect(root.querySelector<HTMLElement>('[data-nowo-ui-pagination-more-region]')!.hidden).toBe(true);
    FakeObserver.trigger();
    await flush();
    expect(fetchMock).toHaveBeenCalledTimes(2);
    document.removeEventListener(APPENDED_EVENT, appended);
  });

  it('loads with the button only after the automatic loads, and focuses the first new row on the last page', async () => {
    const root = await mount(pageHtml(1, 2).replace('data-nowo-ui-pagination-auto="2"', 'data-nowo-ui-pagination-auto="0"'));
    expect(FakeObserver.instances).toHaveLength(0);
    fetchMock.mockImplementation(async () => ({ ok: true, status: 200, text: async () => pageHtml(2, 2) }));
    const button = root.querySelector<HTMLButtonElement>('[data-nowo-ui-pagination-more]')!;
    button.focus();
    button.click();
    expect(root.hasAttribute('data-nowo-ui-pagination-loading')).toBe(true);
    expect(button.getAttribute('aria-disabled')).toBe('true');
    button.click(); // ignored while loading
    await flush();
    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(rowTexts()).toEqual(['p1-r1', 'p1-r2', 'p2-r1', 'p2-r2']);
    const firstNew = document.querySelectorAll<HTMLElement>('[data-nowo-ui-list-rows] > tr')[2]!;
    expect(document.activeElement).toBe(firstNew);
    expect(firstNew.tabIndex).toBe(-1);
  });

  it('announces a failed load, stops loading automatically and retries from the button', async () => {
    const root = await mount(pageHtml(1, 3));
    fetchMock.mockResolvedValueOnce({ ok: false, status: 500, text: async () => '' });
    FakeObserver.trigger();
    await flush();
    expect(root.querySelector('[data-nowo-ui-pagination-status]')!.textContent).toBe('Could not load.');
    expect(rowTexts()).toHaveLength(2);
    const armed = FakeObserver.instances.filter((o) => !o.disconnected).length;
    expect(armed).toBe(0);
    root.querySelector<HTMLButtonElement>('[data-nowo-ui-pagination-more]')!.click();
    await flush();
    expect(rowTexts()).toHaveLength(4);
  });

  it('never fetches an off-site next URL', async () => {
    const root = await mount(pageHtml(1, 3).replace('/monitors?status=up&amp;per_page=2&amp;page=2', 'https://evil.example/x'));
    expect(root.querySelector<HTMLElement>('[data-nowo-ui-pagination-more-region]')!.hidden).toBe(true);
    root.querySelector<HTMLButtonElement>('[data-nowo-ui-pagination-more]')!.click();
    await flush();
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('creates the confirms region when the page had none and ignores a response without rows', async () => {
    await mount(pageHtml(1, 3).replace('<div data-nowo-ui-region="confirms"><dialog id="confirm-p1"></dialog></div>', ''));
    FakeObserver.trigger();
    await flush();
    expect(document.querySelector('[data-nowo-ui-region="table"] + [data-nowo-ui-region="confirms"] #confirm-p2')).not.toBeNull();
    fetchMock.mockResolvedValueOnce({ ok: true, status: 200, text: async () => '<p>nothing</p>' });
    FakeObserver.trigger();
    await flush();
    expect(rowTexts()).toHaveLength(4);
  });
});

describe('list-pagination peer, responsive mode', () => {
  it('switches between pager and infinite on media change and keeps the appended rows', async () => {
    const media = fakeMedia(true);
    const root = await mount(pageHtml(1, 3).replace('data-nowo-ui-pagination-mode="infinite"', 'data-nowo-ui-pagination-mode="responsive"').replace('data-nowo-ui-pagination-wide="infinite"', 'data-nowo-ui-pagination-wide="pager"'));
    const controller = app!.getControllerForElementAndIdentifier(root, 'list-pagination') as ListPaginationController;
    expect(controller.currentMode()).toBe('infinite');
    FakeObserver.trigger();
    await flush();
    expect(rowTexts()).toHaveLength(4);

    media.set(false);
    expect(controller.currentMode()).toBe('pager');
    expect(root.getAttribute('data-nowo-ui-pagination-active')).toBe('pager');
    expect(root.querySelector<HTMLElement>('.nowo-ui-pagination__nav')!.hidden).toBe(false);
    expect(root.querySelector<HTMLElement>('[data-nowo-ui-pagination-more-region]')!.hidden).toBe(true);
    expect(FakeObserver.instances.every((o) => o.disconnected)).toBe(true);
    expect(rowTexts()).toHaveLength(4);
    expect(await controller.loadMore(true)).toBe(0);

    media.set(true);
    expect(controller.currentMode()).toBe('infinite');
    expect(root.querySelector<HTMLElement>('.nowo-ui-pagination__nav')!.hidden).toBe(true);
  });

  it('works without IntersectionObserver (button only) and cleans up on disconnect', async () => {
    vi.stubGlobal('IntersectionObserver', undefined);
    const root = await mount(pageHtml(1, 3));
    expect(FakeObserver.instances).toHaveLength(0);
    root.querySelector<HTMLButtonElement>('[data-nowo-ui-pagination-more]')!.click();
    await flush();
    expect(rowTexts()).toHaveLength(4);
    root.remove();
    await flush();
  });
});
