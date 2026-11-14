import { afterEach, describe, expect, it, vi } from 'vitest';
import { isEditable, MARK, overflows, regionName, regionRole, regionsOfMutations, schedule, syncRegion, syncRegions } from './nowo-ui-scroll-region';

const size = (el: HTMLElement, scroll: number, client: number): void => {
  Object.defineProperty(el, 'scrollWidth', { configurable: true, get: () => scroll });
  Object.defineProperty(el, 'clientWidth', { configurable: true, get: () => client });
};
const frame = (): Promise<void> => new Promise((r) => setTimeout(r, 40));

describe('nowo-ui-scroll-region (2.28, KIT-71)', () => {
  afterEach(() => {
    document.body.innerHTML = '';
    vi.restoreAllMocks();
  });

  it('makes an overflowing table wrap focusable and named, and undoes it when it fits again', () => {
    document.body.innerHTML = '<div id="w" data-nowo-ui-region="table"><table aria-label="Orders"><tr><td>x</td></tr></table></div>';
    const wrap = document.getElementById('w') as HTMLElement;
    size(wrap, 900, 300);
    expect(overflows(wrap)).toBe(true);
    syncRegion(wrap);
    expect(wrap.getAttribute('tabindex')).toBe('0');
    expect(wrap.getAttribute('role')).toBe('region');
    expect(wrap.getAttribute('aria-label')).toBe('Orders');
    syncRegion(wrap);
    expect(wrap.getAttribute(MARK)).toBe('tabindex role aria-label');
    size(wrap, 300, 300);
    syncRegion(wrap);
    expect(wrap.hasAttribute('tabindex')).toBe(false);
    expect(wrap.hasAttribute('role')).toBe(false);
    expect(wrap.hasAttribute('aria-label')).toBe(false);
    expect(wrap.hasAttribute(MARK)).toBe(false);
    syncRegion(wrap);
  });

  it('names the region from the label attribute, else the caption; no name means no role', () => {
    document.body.innerHTML = `<div id="a" data-nowo-ui-scroll-region data-nowo-ui-scroll-label=" Invoices "><table><caption>Ignored</caption></table></div>
      <div id="b" data-nowo-ui-region="table"><table><caption> Quotes </caption></table></div>
      <div id="c" data-nowo-ui-region="table"><table></table></div>
      <div id="d" data-nowo-ui-region="table"></div>`;
    const el = (id: string): HTMLElement => document.getElementById(id) as HTMLElement;
    expect(regionName(el('a'))).toBe('Invoices');
    expect(regionName(el('b'))).toBe('Quotes');
    expect(regionName(el('c'))).toBe('');
    expect(regionName(el('d'))).toBe('');
    size(el('c'), 500, 100);
    syncRegion(el('c'));
    expect(el('c').getAttribute('tabindex')).toBe('0');
    expect(el('c').hasAttribute('role')).toBe(false);
  });

  it('never touches what the host wrote', () => {
    document.body.innerHTML = `<div id="t" data-nowo-ui-region="table" tabindex="-1"><table aria-label="X"></table></div>
      <div id="r" data-nowo-ui-region="table" role="group" aria-labelledby="h"><table aria-label="X"></table></div>`;
    const t = document.getElementById('t') as HTMLElement;
    const r = document.getElementById('r') as HTMLElement;
    size(t, 500, 100);
    size(r, 500, 100);
    syncRegion(t);
    syncRegion(r);
    expect(t.getAttribute('tabindex')).toBe('-1');
    expect(r.getAttribute('role')).toBe('group');
    expect(r.hasAttribute('aria-label')).toBe(false);
    size(r, 100, 100);
    syncRegion(r);
    expect(r.getAttribute('role')).toBe('group');
    expect(r.hasAttribute('tabindex')).toBe(false);
  });

  it('covers regions inserted later, throttled to one frame', async () => {
    document.body.innerHTML = '<main id="m"></main>';
    const wrap = document.createElement('div');
    wrap.setAttribute('data-nowo-ui-region', 'table');
    wrap.innerHTML = '<table aria-label="Later"></table>';
    size(wrap, 800, 200);
    document.getElementById('m')?.append(wrap);
    await frame();
    expect(wrap.getAttribute('tabindex')).toBe('0');
    schedule();
    schedule();
    await frame();
    syncRegions(document);
    expect(wrap.getAttribute('aria-label')).toBe('Later');
  });

  it('falls back to a timer without requestAnimationFrame', async () => {
    vi.stubGlobal('requestAnimationFrame', undefined);
    try {
      document.body.innerHTML = '<div id="w" data-nowo-ui-region="table"><table aria-label="T"></table></div>';
      size(document.getElementById('w') as HTMLElement, 800, 100);
      schedule();
      await frame();
      expect(document.getElementById('w')?.getAttribute('tabindex')).toBe('0');
    } finally {
      vi.unstubAllGlobals();
    }
  });
  it('keeps a label it set in step with the caption (2.29.1)', async () => {
    document.body.innerHTML = '<div id="w" data-nowo-ui-region="table"><table><caption>Old</caption></table></div>';
    const wrap = document.getElementById('w') as HTMLElement;
    size(wrap, 900, 300);
    syncRegion(wrap);
    expect(wrap.getAttribute('aria-label')).toBe('Old');
    (wrap.querySelector('caption') as HTMLElement).textContent = 'New';
    await frame();
    expect(wrap.getAttribute('aria-label')).toBe('New');
    (wrap.querySelector('caption') as HTMLElement).textContent = '';
    syncRegion(wrap);
    expect(wrap.getAttribute('aria-label')).toBe('New');

    // A label the host wrote is never rewritten.
    document.body.innerHTML = '<div id="h" data-nowo-ui-region="table" aria-label="Host"><table><caption>Caption</caption></table></div>';
    const host = document.getElementById('h') as HTMLElement;
    size(host, 900, 300);
    syncRegion(host);
    (host.querySelector('caption') as HTMLElement).textContent = 'Changed';
    syncRegion(host);
    expect(host.getAttribute('aria-label')).toBe('Host');
  });

  it('lists the regions a batch of mutations concerns', () => {
    document.body.innerHTML = '<div id="r" data-nowo-ui-region="table"><table><caption>C</caption></table></div><p id="p">x</p>';
    const region = document.getElementById('r') as HTMLElement;
    const inserted = document.createElement('section');
    inserted.innerHTML = '<div data-nowo-ui-scroll-region id="inner"></div>';
    const records = [
      { addedNodes: [inserted, document.createTextNode('t')], removedNodes: [], target: document.body },
      { addedNodes: [], removedNodes: [document.createElement('i')], target: region.querySelector('caption') as Node },
      { addedNodes: [region], removedNodes: [], target: (document.getElementById('p') as HTMLElement).firstChild as Node },
    ] as unknown as MutationRecord[];
    const { regions, removed } = regionsOfMutations(records);
    expect(regions.map((r) => r.id)).toEqual(['inner', 'r', 'r']);
    expect(removed).toBe(true);
    expect(regionsOfMutations([]).removed).toBe(false);
  });

  it('unobserves regions that leave the document and follows a new first child (2.29.1, ES-I9)', async () => {
    const observed = new Set<Element>();
    let callback: (entries: Array<{ target: Element }>) => void = () => undefined;
    class FakeResizeObserver {
      constructor(cb: (entries: Array<{ target: Element }>) => void) { callback = cb; }
      observe(el: Element): void { observed.add(el); }
      unobserve(el: Element): void { observed.delete(el); }
      disconnect(): void { observed.clear(); }
    }
    vi.stubGlobal('ResizeObserver', FakeResizeObserver);
    vi.resetModules();
    try {
      const fresh = await import('./nowo-ui-scroll-region');
      document.body.innerHTML = '<main id="m"></main>';
      const wrap = document.createElement('div');
      wrap.setAttribute('data-nowo-ui-region', 'table');
      wrap.innerHTML = '<table aria-label="T"></table>';
      document.getElementById('m')?.append(wrap);
      await frame();
      const table = wrap.firstElementChild as Element;
      expect(observed.has(wrap) && observed.has(table)).toBe(true);
      expect(fresh.watchedCount()).toBe(1);

      // A resize of the child syncs its region only.
      size(wrap, 900, 100);
      callback([{ target: table }, { target: document.body }]);
      await frame();
      expect(wrap.getAttribute('tabindex')).toBe('0');

      // A new first child replaces the old one in the observer.
      const next = document.createElement('table');
      wrap.replaceChildren(next);
      await frame();
      expect(observed.has(table)).toBe(false);
      expect(observed.has(next)).toBe(true);

      wrap.remove();
      await frame();
      expect(observed.size).toBe(0);
      expect(fresh.watchedCount()).toBe(0);
    } finally {
      vi.unstubAllGlobals();
    }
  });
});

describe('nowo-ui-scroll-region 2.48: rendered content, editable areas, role', () => {
  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('treats pre and table inside rendered content as regions; a table keeps its own semantics', () => {
    document.body.innerHTML = `<div class="nowo-ui-rendered-content"><pre id="pre" data-nowo-ui-scroll-label="Code">x</pre><table id="tbl" aria-label="Wide"><tr><td>x</td></tr></table></div>
      <div data-nowo-ui-rendered-content><pre id="pre2">y</pre></div><pre id="outside">z</pre>`;
    const pre = document.getElementById('pre') as HTMLElement;
    const table = document.getElementById('tbl') as HTMLElement;
    const pre2 = document.getElementById('pre2') as HTMLElement;
    const outside = document.getElementById('outside') as HTMLElement;
    [pre, table, pre2, outside].forEach((node) => size(node, 900, 100));
    syncRegions();
    expect(pre.getAttribute('role')).toBe('region');
    expect(pre.getAttribute('aria-label')).toBe('Code');
    expect(table.getAttribute('tabindex')).toBe('0');
    expect(table.hasAttribute('role')).toBe(false);
    expect(table.getAttribute(MARK)).toBe('tabindex');
    expect(pre2.getAttribute('tabindex')).toBe('0');
    expect(outside.hasAttribute('tabindex')).toBe(false);
  });

  it('skips anything inside an editable area and removes what it set before the area became editable', () => {
    document.body.innerHTML = `<div id="ed" contenteditable="true"><div id="in" data-nowo-ui-scroll-region data-nowo-ui-scroll-label="X"></div></div>
      <div contenteditable="false"><div id="ro" data-nowo-ui-scroll-region></div></div>
      <div id="later"><div id="was" data-nowo-ui-scroll-region data-nowo-ui-scroll-label="W"></div></div>`;
    const inside = document.getElementById('in') as HTMLElement;
    const ro = document.getElementById('ro') as HTMLElement;
    const was = document.getElementById('was') as HTMLElement;
    [inside, ro, was].forEach((node) => size(node, 900, 100));
    expect(isEditable(inside)).toBe(true);
    expect(isEditable(ro)).toBe(false);
    syncRegions();
    expect(inside.hasAttribute('tabindex')).toBe(false);
    expect(ro.getAttribute('tabindex')).toBe('0');
    syncRegion(was);
    expect(was.getAttribute('role')).toBe('region');
    (document.getElementById('later') as HTMLElement).setAttribute('contenteditable', '');
    syncRegion(was);
    expect(was.hasAttribute('tabindex')).toBe(false);
    expect(was.hasAttribute('role')).toBe(false);
    expect(was.hasAttribute(MARK)).toBe(false);
    syncRegion(was);
  });

  it('uses role=group when asked, else region', () => {
    document.body.innerHTML = '<div id="g" data-nowo-ui-scroll-region data-nowo-ui-scroll-role="group" data-nowo-ui-scroll-label="Code"></div><div id="r" data-nowo-ui-scroll-region data-nowo-ui-scroll-role="banner"></div>';
    const group = document.getElementById('g') as HTMLElement;
    expect(regionRole(group)).toBe('group');
    expect(regionRole(document.getElementById('r') as HTMLElement)).toBe('region');
    size(group, 900, 100);
    syncRegion(group);
    expect(group.getAttribute('role')).toBe('group');
  });

  it('a contenteditable mutation names the regions inside the element (and the element itself)', () => {
    document.body.innerHTML = '<div id="host" data-nowo-ui-scroll-region><div id="child" data-nowo-ui-scroll-region></div></div>';
    const host = document.getElementById('host') as HTMLElement;
    const record = { type: 'attributes', target: host, addedNodes: [] as unknown as NodeList, removedNodes: [] as unknown as NodeList } as unknown as MutationRecord;
    const { regions } = regionsOfMutations([record]);
    expect(regions).toContain(host);
    expect(regions).toContain(document.getElementById('child'));
  });
});
