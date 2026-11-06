import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import ListPaginationController, { APPENDED_EVENT, findRowsRegion, isSameOriginResponse, normaliseMode, resolveMode, sameOriginUrl, sanitizeImported } from './list_pagination_controller';

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
    expect(fetchMock.mock.calls[0]![1]).toMatchObject({ credentials: 'same-origin', redirect: 'error' });
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

// 2.23 (audit CS L1): a redirected / cross-origin response is never imported; imported markup loses inline handlers
describe('list-pagination peer, hostile responses', () => {
  it('rejects a cross-origin or CORS response (open redirect) and imports nothing', async () => {
    const root = await mount(pageHtml(1, 3));
    fetchMock.mockResolvedValueOnce({ ok: true, status: 200, type: 'cors', url: 'https://evil.example/', redirected: true, text: async () => pageHtml(2, 3) });
    FakeObserver.trigger();
    await flush();
    expect(rowTexts()).toHaveLength(2);
    expect(root.querySelector('[data-nowo-ui-pagination-status]')!.textContent).toBe('Could not load.');
    expect(isSameOriginResponse({ type: 'basic', url: `${window.location.origin}/monitors?page=2` })).toBe(true);
    expect(isSameOriginResponse({ type: 'basic', url: 'https://evil.example/' })).toBe(false);
    expect(isSameOriginResponse({ type: 'opaqueredirect', url: '' })).toBe(false);
    expect(isSameOriginResponse({})).toBe(true);
  });

  it('strips on* attributes, srcdoc and javascript: URLs from imported rows but keeps the confirm dialogs working', async () => {
    await mount(pageHtml(1, 3));
    const hostile = pageHtml(2, 3)
      .replace('<td data-label="Name">p2-r1</td>', '<td data-label="Name" onclick="alert(1)"><a href=" JaVaScRiPt:alert(1)">p2-r1</a><img src="x" onerror="alert(2)"><iframe srcdoc="<b>x</b>"></iframe></td>')
      .replace('<dialog id="confirm-p2"></dialog>', '<dialog id="confirm-p2" data-nowo-ui-confirm><button type="button" data-nowo-confirm-close onmouseover="x()">Cancel</button><a href="/delete/2">Go</a></dialog>');
    fetchMock.mockResolvedValueOnce({ ok: true, status: 200, type: 'basic', url: `${window.location.origin}/monitors?page=2`, text: async () => hostile });
    FakeObserver.trigger();
    await flush();
    const cell = Array.from(document.querySelectorAll('[data-nowo-ui-list-rows] td')).find((td) => td.textContent === 'p2-r1')!;
    expect(cell.hasAttribute('onclick')).toBe(false);
    expect(cell.querySelector('a')!.hasAttribute('href')).toBe(false);
    expect(cell.querySelector('img')!.hasAttribute('onerror')).toBe(false);
    expect(cell.querySelector('img')!.getAttribute('src')).toBe('x');
    expect(cell.querySelector('iframe')!.hasAttribute('srcdoc')).toBe(false);
    const dialog = document.getElementById('confirm-p2')!;
    expect(dialog.hasAttribute('data-nowo-ui-confirm')).toBe(true);
    expect(dialog.querySelector('[data-nowo-confirm-close]')!.hasAttribute('onmouseover')).toBe(false);
    expect(dialog.querySelector('a')!.getAttribute('href')).toBe('/delete/2');
  });

  it('sanitizes the root element itself', () => {
    const el = document.createElement('div');
    el.setAttribute('onfocus', 'x()');
    el.innerHTML = '<form action="vbscript:x"><button formaction="/ok">ok</button></form>';
    sanitizeImported(el);
    expect(el.outerHTML).toBe('<div><form><button formaction="/ok">ok</button></form></div>');
  });

  it.each(['attributes', 'removeAttribute', 'getAttributeNames'])('a form control named %s does not hide the form (GT-L3)', (name) => {
    // happy-dom has no named properties on a form: behave as browsers do.
    Object.defineProperty(HTMLFormElement.prototype, name, {
      configurable: true,
      get(this: HTMLFormElement) {
        return Element.prototype.querySelector.call(this, `[name="${name}"]`);
      },
    });
    try {
      const el = document.createElement('div');
      el.innerHTML = `<form class="c" onsubmit="x()" action="javascript:x()"><input name="${name}"></form>`;
      sanitizeImported(el);
      expect(Element.prototype.getAttributeNames.call(el.querySelector('.c') as Element)).toEqual(['class']);
    } finally {
      delete (HTMLFormElement.prototype as unknown as Record<string, unknown>)[name];
    }
  });
});

// GT-I6: the same rules as the fragment filter, nested templates included.
describe('list-pagination peer, fragment filter rules', () => {
  const row = (cell: string): string => `<tr class="row"><td data-label="Name">${cell}</td></tr>`;
  const load = async (rows: string, after = ''): Promise<void> => {
    await mount(pageHtml(1, 3));
    const hostile = `<div data-nowo-ui-region="table"><table><tbody data-nowo-ui-list-rows>${rows}</tbody></table></div>${after}`;
    fetchMock.mockResolvedValueOnce({ ok: true, status: 200, type: 'basic', url: `${window.location.origin}/monitors?page=2`, text: async () => hostile });
    FakeObserver.trigger();
    await flush();
  };

  it.each([
    ['a meta refresh', '<meta http-equiv="refresh" content="0;url=https://evil.example/">', 'meta'],
    ['a base', '<base href="https://evil.example/">', 'base'],
    ['an object', '<object data="https://evil.example/"></object>', 'object'],
    ['an embed', '<embed src="https://evil.example/">', 'embed'],
    ['a data: iframe', '<iframe src="data:text/html,x"></iframe>', 'iframe'],
    ['an href animation', '<svg><a><animate attributeName="href" to="javascript:x()"></animate></a></svg>', 'animate'],
  ])('drops %s from a row', async (_name, markup, selector) => {
    await load(row(`kept${markup}`));
    expect(rowTexts()).toContain('kept');
    expect(document.querySelector(`[data-nowo-ui-list-rows] ${selector}`)).toBeNull();
  });

  it('cleans nested templates', () => {
    const root = document.createElement('div');
    root.innerHTML = '<template><img src="x" onerror="x()"><template><a href="javascript:x()" onclick="x()">a</a><iframe srcdoc="x"></iframe></template></template>';
    expect(sanitizeImported(root)).toBe(true);
    const outer = root.querySelector('template') as HTMLTemplateElement;
    expect(outer.content.querySelector('img')?.getAttributeNames()).toEqual(['src']);
    const inner = (outer.content.querySelector('template') as HTMLTemplateElement).content;
    expect(inner.querySelector('a')?.getAttributeNames()).toEqual([]);
    expect(inner.querySelector('iframe')?.getAttributeNames()).toEqual([]);
  });

  it('removes script URLs of the extra attributes it always covered', () => {
    const root = document.createElement('div');
    root.innerHTML = '<video poster=" java\tscript:x()"></video><img src="javascript:x()" alt="a">';
    sanitizeImported(root);
    expect(root.querySelector('video')?.hasAttribute('poster')).toBe(false);
    expect(root.querySelector('img')?.getAttributeNames()).toEqual(['alt']);
  });

  it('does not import a root the filter dropped: rows region, confirm dialog, pager', async () => {
    const after = '<div data-nowo-ui-region="confirms"><base id="confirm-base" href="https://evil.example/"><dialog id="confirm-ok"></dialog></div>';
    await mount(pageHtml(1, 3));
    const hostile = `<object data-nowo-ui-list-rows><tr><td>imported</td></tr></object>${after}<div data-nowo-ui-pagination-mode="infinite"><object class="nowo-ui-pagination__info"><b>imported</b></object></div>`;
    fetchMock.mockResolvedValueOnce({ ok: true, status: 200, type: 'basic', url: `${window.location.origin}/monitors?page=2`, text: async () => hostile });
    FakeObserver.trigger();
    await flush();
    expect(rowTexts()).toEqual(['p1-r1', 'p1-r2']);
    expect(document.getElementById('confirm-base')).toBeNull();
    expect(document.getElementById('confirm-ok')).not.toBeNull();
    expect(document.querySelector('.nowo-ui-pagination__info')?.textContent).toBe('Showing page 1');
  });

  it('leaves the rows, links, forms and dialogs of a real list as the server wrote them', async () => {
    const legit = '<tr class="row" data-id="2"><td data-label="Name"><a href="/monitors/2?tab=a#x" title="t">p2</a> <a href="mailto:a@example.test">m</a></td><td><img src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" alt=""><svg viewBox="0 0 1 1"><use href="#i"></use><animate attributeName="opacity" to="1"></animate></svg></td><td><form method="post" action="/monitors/2/delete" data-controller="confirm-submit"><input type="hidden" name="_token" value="placeholder"><button type="submit" formaction="/monitors/2/archive">Go</button></form><iframe src="https://example.test/embed" title="e"></iframe><template><b>kept</b></template></td></tr>';
    const dialog = '<dialog id="confirm-p2" data-nowo-ui-confirm=""><button type="button" data-nowo-confirm-close="">Cancel</button><a href="/delete/2">Go</a></dialog>';
    await load(legit, `<div data-nowo-ui-region="confirms">${dialog}</div>`);
    expect(document.querySelector('[data-nowo-ui-list-rows] > tr[data-id="2"]')?.outerHTML).toBe(legit);
    expect(document.getElementById('confirm-p2')?.outerHTML).toBe(dialog);
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
