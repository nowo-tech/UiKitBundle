import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import * as mod from './nowo-ui-bulk-select';

const BAR = (extra = '') => `
  <div class="nowo-ui-bulk-bar" data-nowo-ui-bulk-bar data-nowo-ui-bulk-scope="bulk"
    data-nowo-ui-bulk-none="No rows selected" data-nowo-ui-bulk-one="1 row selected" data-nowo-ui-bulk-many="%count% rows selected" ${extra}>
    <p data-nowo-ui-bulk-status>No rows selected</p>
    <button type="button" data-nowo-ui-bulk-select-all-pages hidden>Select all 42</button>
    <button type="submit" form="bulk" name="bulk_action" value="archive" data-nowo-ui-bulk-action id="archive">Archive</button>
    <button type="button" data-nowo-ui-bulk-clear hidden>Clear</button>
  </div>`;

describe('nowo-ui-bulk-select', () => {
  let status: Element;

  beforeEach(() => {
    document.body.innerHTML = `
      <form id="bulk">
        <input type="hidden" id="all" name="all" value="">
        <table><thead><tr><th><input type="checkbox" id="toggle" data-nowo-ui-bulk-all hidden></th></tr></thead>
        <tbody>
          <tr id="r1"><td><input type="checkbox" name="ids[]" value="1" data-nowo-ui-bulk-item id="c1"></td></tr>
          <tr id="r2"><td><input type="checkbox" name="ids[]" value="2" data-nowo-ui-bulk-item id="c2"></td></tr>
          <tr id="r3"><td><input type="checkbox" name="ids[]" value="3" data-nowo-ui-bulk-item id="c3"></td></tr>
        </tbody></table>
      </form>
      ${BAR('data-nowo-ui-bulk-total="42" data-nowo-ui-bulk-all-field="all" data-nowo-ui-bulk-all-pages="All 42 rows selected"')}`;
    status = document.querySelector('[data-nowo-ui-bulk-status]')!;
    mod.bindNowoUiBulkSelect();
  });

  afterEach(() => {
    document.body.innerHTML = '';
  });

  const box = (id: string) => document.getElementById(id) as HTMLInputElement;
  const change = (el: HTMLInputElement, checked: boolean) => {
    el.checked = checked;
    el.dispatchEvent(new Event('change', { bubbles: true }));
  };

  it('shows the page toggle and disables the actions while nothing is selected', () => {
    expect(box('toggle').hidden).toBe(false);
    expect(document.getElementById('archive')!.getAttribute('aria-disabled')).toBe('true');
    const click = new MouseEvent('click', { bubbles: true, cancelable: true });
    document.getElementById('archive')!.dispatchEvent(click);
    expect(click.defaultPrevented).toBe(true);
    expect(mod.itemsOf(document.getElementById('bulk') as HTMLFormElement)).toHaveLength(3);
  });

  it('counts the selected rows, marks them and sets the toggle to mixed', () => {
    change(box('c1'), true);
    expect(status.textContent).toBe('1 row selected');
    expect(document.getElementById('r1')!.hasAttribute('data-nowo-ui-bulk-selected')).toBe(true);
    expect(box('toggle').indeterminate).toBe(true);
    expect(document.getElementById('archive')!.getAttribute('aria-disabled')).toBe('false');
    expect((document.querySelector('[data-nowo-ui-bulk-clear]') as HTMLElement).hidden).toBe(false);
    change(box('c2'), true);
    expect(status.textContent).toBe('2 rows selected');
  });

  it('selects the page from the toggle, then every row across pages as a second step', () => {
    change(box('toggle'), true);
    expect(status.textContent).toBe('3 rows selected');
    expect(box('toggle').checked).toBe(true);
    expect(box('toggle').indeterminate).toBe(false);
    const more = document.querySelector('[data-nowo-ui-bulk-select-all-pages]') as HTMLElement;
    expect(more.hidden).toBe(false);
    more.click();
    expect(box('all').value).toBe('1');
    expect(status.textContent).toBe('All 42 rows selected');
    expect(more.hidden).toBe(true);
    // Changing one row drops the "every page" choice.
    change(box('c3'), false);
    expect(box('all').value).toBe('');
    expect(status.textContent).toBe('2 rows selected');
    change(box('toggle'), false);
    expect(status.textContent).toBe('No rows selected');
  });

  it('clears the selection', () => {
    change(box('c1'), true);
    (document.querySelector('[data-nowo-ui-bulk-clear]') as HTMLElement).click();
    expect(box('c1').checked).toBe(false);
    expect(status.textContent).toBe('No rows selected');
  });

  it('accepts a toggle outside the form (data-nowo-ui-bulk-for) and re-syncs on pageshow', () => {
    const outside = document.createElement('input');
    outside.type = 'checkbox';
    outside.setAttribute('data-nowo-ui-bulk-all', '');
    outside.setAttribute('data-nowo-ui-bulk-for', 'bulk');
    document.body.appendChild(outside);
    change(outside, true);
    expect(status.textContent).toBe('3 rows selected');
    box('c1').checked = false;
    window.dispatchEvent(new Event('pageshow'));
    expect(status.textContent).toBe('2 rows selected');
  });

  it('ignores unrelated changes, clicks and bars without a form', () => {
    document.body.insertAdjacentHTML('beforeend', BAR().replace('data-nowo-ui-bulk-scope="bulk"', 'data-nowo-ui-bulk-scope="missing"'));
    const orphan = document.querySelectorAll<HTMLElement>('[data-nowo-ui-bulk-bar]')[1];
    mod.upgradeBulkBars();
    expect(() => mod.update(orphan)).not.toThrow();
    orphan.querySelector<HTMLElement>('[data-nowo-ui-bulk-clear]')!.click();
    const loose = document.createElement('input');
    loose.type = 'checkbox';
    loose.setAttribute('data-nowo-ui-bulk-all', '');
    document.body.appendChild(loose);
    change(loose, true);
    const text = document.createElement('input');
    document.body.appendChild(text);
    text.dispatchEvent(new Event('change', { bubbles: true }));
    document.dispatchEvent(new Event('change'));
    document.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    document.body.click();
    expect(status.textContent).toBe('No rows selected');
  });

  it('without a total offers no "select all pages" step', () => {
    document.body.innerHTML = `<form id="bulk"><input type="checkbox" data-nowo-ui-bulk-item id="only"></form>${BAR()}`;
    mod.upgradeBulkBars();
    change(box('only'), true);
    expect((document.querySelector('[data-nowo-ui-bulk-select-all-pages]') as HTMLElement).hidden).toBe(true);
    (document.querySelector('[data-nowo-ui-bulk-select-all-pages]') as HTMLElement).click();
    expect(document.querySelector('[data-nowo-ui-bulk-status]')!.textContent).toBe('1 row selected');
  });
});
