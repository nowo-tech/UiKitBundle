import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import FiltersCollapseController, { FILTERS_COLLAPSE_COOKIE, activeFilterCount, foldedKeys } from './filters_collapse_controller';
import { setIconStateHandler } from './icon_state';

const MESSAGES = { collapse: 'Hide filters', expand: 'Show filters', none: 'Filters', one: '1 active filter', many: '%count% active filters' };

function mount(fields: string, collapsed = false, key = 'items_index', extra = ''): HTMLFormElement {
  document.body.innerHTML = `
    <form class="nowo-ui-card">
      <div class="nowo-ui-filters-collapse${collapsed ? ' is-collapsed' : ''}" data-controller="filters-collapse" data-filters-collapse-key-value="${key}"
           data-filters-collapse-messages-value='${JSON.stringify(MESSAGES)}' ${extra}>
        <button type="button" data-filters-collapse-target="toggle" data-action="filters-collapse#toggle" aria-expanded="${collapsed ? 'false' : 'true'}"></button>
        <p data-filters-collapse-target="summary"${collapsed ? '' : ' hidden'}></p>
      </div>
      ${fields}
    </form>`;

  return document.querySelector('form') as HTMLFormElement;
}

const tick = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));
let app: Application;
const iconCalls: [string, string, boolean][] = [];

function clearCookie(name = FILTERS_COLLAPSE_COOKIE): void {
  document.cookie = `${name}=; path=/; max-age=0`;
}

beforeEach(() => {
  clearCookie();
  clearCookie('app_filters');
  iconCalls.length = 0;
  setIconStateHandler((kind, _host, state, animate) => iconCalls.push([kind, state, animate]));
  app = Application.start();
  app.register('filters-collapse', FiltersCollapseController);
});

afterEach(() => {
  app.stop();
  document.body.innerHTML = '';
  setIconStateHandler(null);
  clearCookie();
  clearCookie('app_filters');
});

describe('filters-collapse peer', () => {
  it('folds the card without touching the fields, counts active filters and remembers the page in a cookie', async () => {
    const form = mount(`
      <select name="project"><option value="">Any</option><option value="p1" selected>P1</option></select>
      <select name="period"><option value="">Any</option></select>
      <input name="q" value="  ">
      <input type="checkbox" name="unread" checked>
      <input type="radio" name="kind" value="a">
      <textarea name="note"></textarea>
      <input type="hidden" name="secret" value="x">
      <select name="per_page"><option value="25" selected>25</option></select>
      <input name="filter[page]" value="2">
      <input name="disabled" value="x" disabled>
      <input value="nameless">
      <button type="submit">Filter</button>`);
    await tick();
    expect(iconCalls).toEqual([['filters-collapse', 'expanded', false]]);
    const root = form.querySelector<HTMLElement>('.nowo-ui-filters-collapse') as HTMLElement;
    const toggle = form.querySelector<HTMLButtonElement>('[data-filters-collapse-target="toggle"]') as HTMLButtonElement;
    const summary = form.querySelector<HTMLElement>('[data-filters-collapse-target="summary"]') as HTMLElement;
    const events: unknown[] = [];
    root.addEventListener('filters-collapse:toggled', (event) => events.push((event as CustomEvent).detail));

    toggle.click();
    expect(root.classList.contains('is-collapsed')).toBe(true);
    expect(toggle.getAttribute('aria-expanded')).toBe('false');
    expect(toggle.getAttribute('aria-label')).toBe('Show filters');
    expect(toggle.title).toBe('Show filters');
    expect(summary.hidden).toBe(false);
    expect(summary.textContent).toBe('2 active filters');
    expect(new FormData(form).get('project')).toBe('p1');
    expect(foldedKeys()).toEqual(['items_index']);
    expect(events).toEqual([{ collapsed: true, key: 'items_index', count: 2 }]);
    expect(iconCalls[iconCalls.length - 1]).toEqual(['filters-collapse', 'collapsed', true]);

    // Another page folds too; unfolding one keeps the other.
    const other = mount('<input name="q" value="error">', false, 'issues_index');
    await tick();
    other.querySelector<HTMLButtonElement>('[data-filters-collapse-target="toggle"]')?.click();
    expect(other.querySelector('[data-filters-collapse-target="summary"]')?.textContent).toBe('1 active filter');
    expect(foldedKeys()).toEqual(['items_index', 'issues_index']);

    const again = mount('<input name="q" value="">', true);
    await tick();
    expect(iconCalls[iconCalls.length - 1]).toEqual(['filters-collapse', 'collapsed', false]);
    again.querySelector<HTMLButtonElement>('[data-filters-collapse-target="toggle"]')?.click();
    expect(again.querySelector('.nowo-ui-filters-collapse')?.classList.contains('is-collapsed')).toBe(false);
    expect(again.querySelector('[data-filters-collapse-target="toggle"]')?.getAttribute('aria-label')).toBe('Hide filters');
    expect(again.querySelector('[data-filters-collapse-target="summary"]')?.hasAttribute('hidden')).toBe(true);
    expect(foldedKeys()).toEqual(['issues_index']);

    const empty = mount('<input name="q" value="">');
    await tick();
    empty.querySelector<HTMLButtonElement>('[data-filters-collapse-target="toggle"]')?.click();
    expect(empty.querySelector('[data-filters-collapse-target="summary"]')?.textContent).toBe('Filters');
  });

  it('uses a custom cookie, skips persistence without a key and works without summary or messages', async () => {
    const form = mount('<input name="q" value="a">', false, 'k1', 'data-filters-collapse-cookie-value="app_filters"');
    await tick();
    form.querySelector<HTMLButtonElement>('[data-filters-collapse-target="toggle"]')?.click();
    expect(foldedKeys('app_filters')).toEqual(['k1']);
    expect(foldedKeys()).toEqual([]);

    document.body.innerHTML = `<form><div class="nowo-ui-filters-collapse" data-controller="filters-collapse">
      <button type="button" data-filters-collapse-target="toggle" data-action="filters-collapse#toggle"></button></div></form>`;
    await tick();
    const toggle = document.querySelector<HTMLButtonElement>('button') as HTMLButtonElement;
    toggle.click();
    expect(toggle.hasAttribute('aria-label')).toBe(false);
    expect(toggle.getAttribute('aria-expanded')).toBe('false');
    expect(foldedKeys()).toEqual([]);
  });

  it('counts the fields of one filter group (a date range) once', () => {
    const form = mount(`
      <div role="group" data-nowo-ui-filter-group>
        <input type="date" name="created_from" value="2026-02-01"><input type="date" name="created_to" value="2026-02-15">
      </div>
      <div data-nowo-ui-filter-group><input type="date" name="paid_from" value=""><input type="date" name="paid_to" value="2026-03-01"></div>
      <div data-nowo-ui-filter-group><input type="date" name="due_from"><input type="date" name="due_to"></div>
      <input name="q" value="acme">`);

    expect(activeFilterCount(form)).toBe(3);
  });

  it('counts nothing outside a form', async () => {
    document.body.innerHTML = `<div data-controller="filters-collapse" data-filters-collapse-key-value="x" data-filters-collapse-messages-value='${JSON.stringify(MESSAGES)}'>
      <button type="button" data-filters-collapse-target="toggle" data-action="filters-collapse#toggle"></button><p data-filters-collapse-target="summary"></p></div>`;
    await tick();
    document.querySelector<HTMLButtonElement>('button')?.click();
    const controller = app.getControllerForElementAndIdentifier(document.querySelector('[data-controller]') as HTMLElement, 'filters-collapse') as FiltersCollapseController;
    expect(controller.activeCount()).toBe(0);
    expect(activeFilterCount(null)).toBe(0);
    expect(foldedKeys()).toEqual(['x']);
  });
});
