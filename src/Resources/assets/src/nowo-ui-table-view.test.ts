import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as mod from './nowo-ui-table-view';

const MARKUP = (persist = 'invoices', density = 'comfortable') => `
  <div data-nowo-ui-table-view data-nowo-ui-table-view-table="t" data-nowo-ui-table-view-persist="${persist}" hidden>
    <button type="button" data-nowo-ui-density="comfortable" aria-pressed="${density === 'comfortable'}">Comfortable</button>
    <button type="button" data-nowo-ui-density="compact" aria-pressed="${density === 'compact'}">Compact</button>
    <details data-nowo-ui-table-view-columns><summary id="sum">Columns</summary>
      <button type="button" data-nowo-ui-column="number" aria-pressed="true" disabled id="b-number">Number</button>
      <button type="button" data-nowo-ui-column="customer" aria-pressed="true" id="b-customer">Customer</button>
      <button type="button" data-nowo-ui-column="due" aria-pressed="false" id="b-due">Due</button>
    </details>
  </div>
  <table id="t" class="nowo-ui-data-table">
    <thead><tr><th data-nowo-ui-column="number">N</th><th data-nowo-ui-column="customer">C</th><th data-nowo-ui-column="due">D</th></tr></thead>
    <tbody><tr><td>1</td><td>Ada</td><td>Mon</td></tr><tr><td>2</td><td>Bob</td><td>Tue</td></tr></tbody>
  </table>`;

describe('nowo-ui-table-view', () => {
  beforeEach(() => {
    window.localStorage.clear();
    document.body.innerHTML = MARKUP();
    mod.bindNowoUiTableView();
  });

  afterEach(() => {
    document.body.innerHTML = '';
    vi.restoreAllMocks();
  });

  const table = () => document.getElementById('t') as HTMLTableElement;
  const cells = (index: number) => Array.from(table().rows).map((r) => r.cells[index].hidden);

  it('shows the controls and applies the server-rendered columns', () => {
    expect((document.querySelector('[data-nowo-ui-table-view]') as HTMLElement).hidden).toBe(false);
    expect(cells(2)).toEqual([true, true, true]);
    expect(cells(1)).toEqual([false, false, false]);
  });

  it('toggles the density and remembers it', () => {
    (document.querySelector('[data-nowo-ui-density="compact"]') as HTMLElement).click();
    expect(table().classList.contains('nowo-ui-data-table--compact')).toBe(true);
    expect(document.querySelector('[data-nowo-ui-density="compact"]')!.getAttribute('aria-pressed')).toBe('true');
    expect(JSON.parse(window.localStorage.getItem(`${mod.STORAGE_PREFIX}invoices`)!)).toEqual({ density: 'compact', hidden: ['due'] });
  });

  it('shows and hides columns, never the last visible one, never a locked one', () => {
    document.getElementById('b-due')!.click();
    expect(cells(2)).toEqual([false, false, false]);
    document.getElementById('b-customer')!.click();
    document.getElementById('b-due')!.click();
    expect(cells(1)).toEqual([true, true, true]);
    expect(cells(2)).toEqual([true, true, true]);
    // number is locked (disabled): clicking does nothing, and it is the last visible column.
    document.getElementById('b-number')!.click();
    expect(cells(0)).toEqual([false, false, false]);
    const root = document.querySelector('[data-nowo-ui-table-view]')!;
    root.querySelectorAll('[data-nowo-ui-column]').forEach((b) => b.removeAttribute('disabled'));
    document.getElementById('b-number')!.click();
    expect(cells(0)).toEqual([false, false, false]);
  });

  it('restores a stored view on load', () => {
    window.localStorage.setItem(`${mod.STORAGE_PREFIX}saved`, JSON.stringify({ density: 'compact', hidden: ['customer'] }));
    document.body.innerHTML = MARKUP('saved');
    mod.upgradeTableViews();
    expect(table().classList.contains('nowo-ui-data-table--compact')).toBe(true);
    expect(cells(1)).toEqual([true, true, true]);
    expect(cells(2)).toEqual([false, false, false]);
  });

  it('survives blocked or corrupt storage and works without persistence', () => {
    window.localStorage.setItem(`${mod.STORAGE_PREFIX}bad`, '{nope');
    document.body.innerHTML = MARKUP('bad', 'compact');
    mod.upgradeTableViews();
    expect(table().classList.contains('nowo-ui-data-table--compact')).toBe(true);
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('blocked');
    });
    expect(() => (document.querySelector('[data-nowo-ui-density="comfortable"]') as HTMLElement).click()).not.toThrow();
    document.body.innerHTML = MARKUP('').replace(' data-nowo-ui-table-view-persist=""', '');
    mod.upgradeTableViews();
    const root = document.querySelector('[data-nowo-ui-table-view]')!;
    expect(mod.load(root)).toBeNull();
    (document.querySelector('[data-nowo-ui-density="compact"]') as HTMLElement).click();
    expect(table().classList.contains('nowo-ui-data-table--compact')).toBe(true);
  });

  it('Escape closes the columns menu and focuses its summary', () => {
    const menu = document.querySelector('details') as HTMLDetailsElement;
    menu.open = true;
    document.getElementById('b-customer')!.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    expect(menu.open).toBe(false);
    expect(document.activeElement?.id).toBe('sum');
    document.getElementById('b-customer')!.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
  });

  it('ignores unknown columns, missing tables and outside clicks', () => {
    expect(() => mod.setColumn(table(), 'nope', false)).not.toThrow();
    document.body.insertAdjacentHTML('beforeend', '<div data-nowo-ui-table-view data-nowo-ui-table-view-table="missing" hidden><button data-nowo-ui-density="compact" id="x">x</button></div>');
    mod.upgradeTableViews();
    document.getElementById('x')!.click();
    document.body.click();
    document.dispatchEvent(new MouseEvent('click'));
    expect((document.querySelectorAll('[data-nowo-ui-table-view]')[1] as HTMLElement).hidden).toBe(true);
  });

  it('XJ-8: Escape in the columns menu does not reach a surrounding dialog', () => {
    const menu = document.querySelector('details') as HTMLDetailsElement;
    menu.open = true;
    const outer = vi.fn();
    document.body.addEventListener('keydown', outer);
    document.getElementById('b-customer')!.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    expect(menu.open).toBe(false);
    expect(outer).not.toHaveBeenCalled();
  });
});
