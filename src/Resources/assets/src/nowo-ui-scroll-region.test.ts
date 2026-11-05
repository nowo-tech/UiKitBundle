import { afterEach, describe, expect, it, vi } from 'vitest';
import { MARK, overflows, regionName, schedule, syncRegion, syncRegions } from './nowo-ui-scroll-region';

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
});
