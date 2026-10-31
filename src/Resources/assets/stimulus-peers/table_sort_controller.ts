/**
 * Optional Stimulus peer (2.20) — client-side sorting of a table rendered whole on the page (never a paginated one:
 * those sort on the server through `Listing\SortRequest` header links).
 *
 * Markup (printed by the `table` partial / list scaffold with `Table::$clientSort`, or by hand with the `sort_header`
 * partial): `data-controller="table-sort"` on the `<table>`, `data-sortable` on each sortable `<th>` holding a
 * `<button data-action="table-sort#sort">`. The header's `aria-sort` is the state: CSS shows the matching arrow and
 * the visually hidden hint (no script-built markup, CSP friendly). Buttons are native, so Enter / Space sort too.
 *
 * - A cell sorts by its `data-sort-value` when present, else by its text.
 * - `data-sort-type` on the `<th>`: `number`, `date` or `text` (default automatic: numbers when both values are
 *   numeric, else a natural, case-insensitive comparison in the document language).
 * - `data-sort-first="desc"` on the `<th>`: the first click sorts descending.
 * - Empty values stay last in both directions; equal values keep their original order (stable).
 * - Rows marked `data-sort-pin` (totals, "load more") stay at the end of their `<tbody>`.
 *
 * Identifier: `table-sort`. Action: `table-sort#sort`. Event: `table-sort:sorted` (detail `{column, direction}`).
 */

import { Controller } from '@hotwired/stimulus';

export type SortDirection = 'ascending' | 'descending';

const NUMERIC = /^[-+]?\d+(?:[.,]\d+)?$/;

/** Number or timestamp of a value for the numeric types (NaN when it has none). */
export function parseSortValue(value: string, type: string): number {
  if (type === 'date') {
    return Date.parse(value);
  }

  return Number.parseFloat(value.replace(/[^\d.eE+-]/g, ''));
}

/** Order of two non-empty values; null when exactly one of them is not a number / date for those types. */
export function compareSortValues(a: string, b: string, type: string, locale?: string): number | null {
  if (type === 'number' || type === 'date') {
    const left = parseSortValue(a, type);
    const right = parseSortValue(b, type);
    if (Number.isNaN(left) || Number.isNaN(right)) {
      return Number.isNaN(left) && Number.isNaN(right) ? 0 : null;
    }

    return left - right;
  }
  if (type === 'auto' && NUMERIC.test(a) && NUMERIC.test(b)) {
    return Number.parseFloat(a.replace(',', '.')) - Number.parseFloat(b.replace(',', '.'));
  }

  return a.localeCompare(b, locale, { numeric: true, sensitivity: 'base' });
}

/** Columns a cell spans (its `colspan`, at least 1). */
function span(cell: Element): number {
  const value = Number.parseInt(cell.getAttribute('colspan') ?? '1', 10);

  return Number.isNaN(value) || value < 1 ? 1 : value;
}

export default class extends Controller<HTMLTableElement> {
  private readonly original = new WeakMap<HTMLTableRowElement, number>();

  sort(event: Event): void {
    const header = (event.currentTarget as HTMLElement | null)?.closest('th');
    if (!header || !this.element.contains(header)) {
      return;
    }
    const direction = this.nextDirection(header);
    this.element.querySelectorAll<HTMLTableCellElement>('thead th[data-sortable]').forEach((th) => th.removeAttribute('aria-sort'));
    header.setAttribute('aria-sort', direction);

    const column = this.columnIndex(header);
    const type = header.dataset.sortType ?? 'auto';
    const sign = direction === 'ascending' ? 1 : -1;
    const locale = document.documentElement.lang || undefined;
    for (const body of Array.from(this.element.querySelectorAll<HTMLTableSectionElement>(':scope > tbody'))) {
      const rows = Array.from(body.querySelectorAll<HTMLTableRowElement>(':scope > tr'));
      rows.forEach((row, index) => {
        if (!this.original.has(row)) {
          this.original.set(row, index);
        }
      });
      const pinned = rows.filter((row) => row.dataset.sortPin !== undefined);
      const sortable = rows
        .filter((row) => row.dataset.sortPin === undefined)
        .map((row) => ({ row, value: this.value(row, column), index: this.original.get(row) ?? 0 }));
      sortable.sort((a, b) => {
        if (a.value === '' || b.value === '') {
          return a.value === b.value ? a.index - b.index : a.value === '' ? 1 : -1;
        }
        const order = compareSortValues(a.value, b.value, type, locale);
        if (order === null) {
          // Values that are not numbers / dates go last, like empty ones.
          return Number.isNaN(parseSortValue(a.value, type)) ? 1 : -1;
        }

        return order !== 0 ? sign * order : a.index - b.index;
      });
      body.append(...sortable.map((entry) => entry.row), ...pinned);
    }
    this.dispatch('sorted', { detail: { column, direction } });
  }

  private nextDirection(header: HTMLElement): SortDirection {
    const current = header.getAttribute('aria-sort');
    if (current === 'ascending') {
      return 'descending';
    }
    if (current === 'descending') {
      return 'ascending';
    }

    return header.dataset.sortFirst === 'desc' ? 'descending' : 'ascending';
  }

  /** Visual column of a header cell, counting the column spans before it. */
  private columnIndex(header: HTMLTableCellElement): number {
    let index = 0;
    let previous = header.previousElementSibling as HTMLTableCellElement | null;
    while (previous !== null) {
      index += span(previous);
      previous = previous.previousElementSibling as HTMLTableCellElement | null;
    }

    return index;
  }

  private value(row: HTMLTableRowElement, column: number): string {
    let index = 0;
    for (const cell of Array.from(row.querySelectorAll<HTMLTableCellElement>(':scope > td, :scope > th'))) {
      if (index === column) {
        return (cell.dataset.sortValue ?? cell.textContent ?? '').trim().replace(/\s+/g, ' ');
      }
      index += span(cell);
    }

    return '';
  }
}
