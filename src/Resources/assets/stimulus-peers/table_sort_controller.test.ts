import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import TableSortController, { compareSortValues, parseSortValue } from './table_sort_controller';

const tick = (): Promise<void> => new Promise((r) => setTimeout(r, 0));

let app: Application;

beforeEach(() => {
  app = Application.start();
  app.register('table-sort', TableSortController);
});

afterEach(() => {
  app.stop();
  document.body.innerHTML = '';
  document.documentElement.removeAttribute('lang');
});

const button = (label: string): string => `<button type="button" data-action="table-sort#sort">${label}</button>`;

function mount(): HTMLTableElement {
  document.body.innerHTML = `
    <table data-controller="table-sort">
      <thead><tr>
        <th data-sortable>${button('Name')}</th>
        <th data-sortable data-sort-first="desc">${button('Count')}</th>
        <th data-sortable data-sort-type="date">${button('When')}</th>
        <th>Notes</th>
      </tr></thead>
      <tbody>
        <tr><td>beta</td><td>10</td><td data-sort-value="2026-01-02">Jan 2</td><td>b</td></tr>
        <tr><td>Alpha</td><td>9</td><td data-sort-value="2025-12-31">Dec 31</td><td>a</td></tr>
        <tr><td></td><td>100</td><td data-sort-value="">—</td><td>e</td></tr>
        <tr><td>alpha 2</td><td>9</td><td data-sort-value="2026-03-01">Mar 1</td><td>c</td></tr>
        <tr data-sort-pin><td>Total</td><td>128</td><td></td><td></td></tr>
      </tbody>
    </table>`;

  return document.querySelector('table')!;
}

const names = (table: HTMLTableElement): string[] => Array.from(table.querySelectorAll('tbody > tr')).map((row) => row.querySelector('td')?.textContent ?? '');
const headers = (table: HTMLTableElement): HTMLTableCellElement[] => Array.from(table.querySelectorAll<HTMLTableCellElement>('thead > tr > th'));
const click = (table: HTMLTableElement, column: number): void => headers(table)[column].querySelector('button')!.click();

describe('table-sort peer', () => {
  it('sorts text naturally, toggles the direction and keeps empty values and pinned rows last', async () => {
    const table = mount();
    await tick();

    click(table, 0);
    expect(headers(table)[0].getAttribute('aria-sort')).toBe('ascending');
    expect(names(table)).toEqual(['Alpha', 'alpha 2', 'beta', '', 'Total']);

    click(table, 0);
    expect(headers(table)[0].getAttribute('aria-sort')).toBe('descending');
    expect(names(table)).toEqual(['beta', 'alpha 2', 'Alpha', '', 'Total']);

    click(table, 0);
    expect(headers(table)[0].getAttribute('aria-sort')).toBe('ascending');
  });

  it('starts descending with data-sort-first, sorts numbers by value and keeps equal values stable', async () => {
    const table = mount();
    await tick();
    let detail: unknown = null;
    table.addEventListener('table-sort:sorted', (event) => {
      detail = (event as CustomEvent).detail;
    });

    click(table, 0);
    click(table, 1);
    expect(headers(table)[0].hasAttribute('aria-sort')).toBe(false);
    expect(headers(table)[1].getAttribute('aria-sort')).toBe('descending');
    expect(names(table)).toEqual(['', 'beta', 'Alpha', 'alpha 2', 'Total']);
    expect(detail).toEqual({ column: 1, direction: 'descending' });

    click(table, 1);
    // 9 and 9 keep the order the rows were first rendered in.
    expect(names(table)).toEqual(['Alpha', 'alpha 2', 'beta', '', 'Total']);
  });

  it('sorts dates by data-sort-value and leaves the empty value last', async () => {
    const table = mount();
    await tick();

    click(table, 2);
    expect(names(table)).toEqual(['Alpha', 'beta', 'alpha 2', '', 'Total']);
    click(table, 2);
    expect(names(table)).toEqual(['alpha 2', 'beta', 'Alpha', '', 'Total']);
  });

  it('counts column spans and ignores clicks outside a header', async () => {
    document.documentElement.lang = 'en';
    document.body.innerHTML = `
      <table data-controller="table-sort">
        <thead><tr><th colspan="2">Group</th><th data-sortable data-sort-type="number">${button('Size')}</th></tr></thead>
        <tbody>
          <tr><td colspan="2">x</td><td>2 kB</td></tr>
          <tr><td>y</td><td>z</td><td>n/a</td></tr>
          <tr><td colspan="2">w</td><td>1 kB</td></tr>
        </tbody>
        <tfoot><tr><td><button type="button" data-action="table-sort#sort">Stray</button></td></tr></tfoot>
      </table>`;
    const table = document.querySelector('table')!;
    await tick();

    table.querySelector<HTMLButtonElement>('tfoot button')!.click();
    expect(names(table)).toEqual(['x', 'y', 'w']);

    table.querySelector<HTMLButtonElement>('thead button')!.click();
    expect(names(table)).toEqual(['w', 'x', 'y']);
    expect(headers(table)[1].getAttribute('aria-sort')).toBe('ascending');
  });

  it('reads an empty value for a row shorter than the header', async () => {
    document.body.innerHTML = `
      <table data-controller="table-sort">
        <thead><tr><th>A</th><th data-sortable>${button('B')}</th></tr></thead>
        <tbody><tr><td>short</td></tr><tr><td>full</td><td>b</td></tr></tbody>
      </table>`;
    const table = document.querySelector('table')!;
    await tick();

    table.querySelector<HTMLButtonElement>('thead button')!.click();
    expect(names(table)).toEqual(['full', 'short']);
  });
});

describe('table-sort comparisons', () => {
  it('parses numbers and dates', () => {
    expect(parseSortValue('1,234.5 €', 'number')).toBe(1234.5);
    expect(parseSortValue('2026-01-01', 'date')).toBe(Date.parse('2026-01-01'));
    expect(Number.isNaN(parseSortValue('n/a', 'number'))).toBe(true);
  });

  it('compares numbers, invalid values and text', () => {
    expect(compareSortValues('2', '10', 'auto')).toBeLessThan(0);
    expect(compareSortValues('2,5', '2.4', 'auto')).toBeGreaterThan(0);
    expect(compareSortValues('n/a', 'x', 'number')).toBe(0);
    expect(compareSortValues('n/a', '3', 'number')).toBeNull();
    expect(compareSortValues('3', '1', 'number')).toBe(2);
    expect(compareSortValues('item 2', 'item 10', 'text')).toBeLessThan(0);
    expect(compareSortValues('a', 'A', 'auto', 'en')).toBe(0);
  });
});
