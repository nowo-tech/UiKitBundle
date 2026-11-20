/**
 * Bulk selection runtime (2.61, partials `bulk_bar` and `bulk_toggle`). A bar [data-nowo-ui-bulk-bar] names its form
 * (data-nowo-ui-bulk-scope = form id); its rows are the checkboxes of that form with data-nowo-ui-bulk-item (inside
 * it or linked with form=). The script:
 * - writes the count in the bar's status line (role="status": announced politely) from the -none / -one / -many
 *   labels (%count%);
 * - shows the "select every row on this page" boxes (data-nowo-ui-bulk-all, in the form or with
 *   data-nowo-ui-bulk-for) and keeps them checked / mixed (indeterminate) / clear;
 * - marks the selected rows (tr[data-nowo-ui-bulk-selected]);
 * - keeps the bar's actions aria-disabled (clicks ignored) while nothing is selected, and shows "Clear selection";
 * - with data-nowo-ui-bulk-total + -all-field: once every row of the page is selected, offers "Select all N rows",
 *   which sets that hidden field to 1; any change of a row box clears it again.
 * - 2.61 (audit XJ-1): the "every page" field is cleared whenever the page is not fully selected (a bound form
 *   re-rendered after a POST, or a browser restoring hidden inputs, could leave it at 1 while the bar shows fewer
 *   rows); hosts render it empty with autocomplete="off".
 * - Danger actions with data-nowo-ui-bulk-confirm="<question>" ask first (window.nowoUiAskConfirm when the confirm
 *   runtime is loaded, else window.confirm) and submit only on yes.
 * Without the script the boxes and the action buttons are a plain form.
 */

const BAR = '[data-nowo-ui-bulk-bar]';
const ITEM = 'input[type="checkbox"][data-nowo-ui-bulk-item]';
const ALL = 'input[type="checkbox"][data-nowo-ui-bulk-all]';

function scopeOf(bar: Element): HTMLFormElement | null {
  const el = document.getElementById(bar.getAttribute('data-nowo-ui-bulk-scope') ?? '');
  return el instanceof HTMLFormElement ? el : null;
}

export function itemsOf(form: HTMLFormElement): HTMLInputElement[] {
  return Array.from(document.querySelectorAll<HTMLInputElement>(ITEM)).filter((input) => input.form === form && !input.disabled);
}

export function togglesOf(form: HTMLFormElement): HTMLInputElement[] {
  return Array.from(document.querySelectorAll<HTMLInputElement>(ALL)).filter(
    (input) => form.contains(input) || input.getAttribute('data-nowo-ui-bulk-for') === form.id,
  );
}

function allField(bar: Element): HTMLInputElement | null {
  const el = document.getElementById(bar.getAttribute('data-nowo-ui-bulk-all-field') ?? '');
  return el instanceof HTMLInputElement ? el : null;
}

function label(bar: Element, count: number): string {
  const key = count === 0 ? 'none' : count === 1 ? 'one' : 'many';
  return (bar.getAttribute(`data-nowo-ui-bulk-${key}`) ?? '').split('%count%').join(String(count));
}

/** Brings the bar, the toggles and the rows in line with the boxes. */
export function update(bar: Element): void {
  const form = scopeOf(bar);
  if (!form) {
    return;
  }
  const items = itemsOf(form);
  const count = items.filter((i) => i.checked).length;
  const full = items.length > 0 && count === items.length;
  togglesOf(form).forEach((toggle) => {
    toggle.checked = full;
    toggle.indeterminate = count > 0 && !full;
  });
  items.forEach((item) => {
    const row = item.closest('tr');
    if (row) {
      row.toggleAttribute('data-nowo-ui-bulk-selected', item.checked);
    }
  });
  const field = allField(bar);
  if (field !== null && field.value === '1' && !full) {
    field.value = '';
  }
  const total = Number.parseInt(bar.getAttribute('data-nowo-ui-bulk-total') ?? '', 10);
  const everything = field !== null && field.value === '1' && full;
  const status = bar.querySelector('[data-nowo-ui-bulk-status]');
  if (status) {
    status.textContent = everything ? (bar.getAttribute('data-nowo-ui-bulk-all-pages') ?? '') : label(bar, count);
  }
  bar.toggleAttribute('data-nowo-ui-bulk-active', count > 0);
  bar.querySelectorAll('[data-nowo-ui-bulk-action]').forEach((action) => action.setAttribute('aria-disabled', count > 0 ? 'false' : 'true'));
  bar.querySelectorAll<HTMLElement>('[data-nowo-ui-bulk-clear]').forEach((clear) => {
    clear.hidden = count === 0;
  });
  bar.querySelectorAll<HTMLElement>('[data-nowo-ui-bulk-select-all-pages]').forEach((more) => {
    more.hidden = !(full && field !== null && !everything && Number.isFinite(total) && total > items.length);
  });
}

function barsFor(form: HTMLFormElement | null): Element[] {
  return form ? Array.from(document.querySelectorAll(BAR)).filter((bar) => scopeOf(bar) === form) : [];
}

function resetAll(form: HTMLFormElement): void {
  barsFor(form).forEach((bar) => {
    const field = allField(bar);
    if (field) {
      field.value = '';
    }
  });
}

function onChange(event: Event): void {
  const target = event.target;
  if (!(target instanceof HTMLInputElement)) {
    return;
  }
  if (target.matches(ALL)) {
    const form = target.form ?? (document.getElementById(target.getAttribute('data-nowo-ui-bulk-for') ?? '') as HTMLFormElement | null);
    if (!form) {
      return;
    }
    itemsOf(form).forEach((item) => {
      item.checked = target.checked;
    });
    resetAll(form);
    barsFor(form).forEach(update);
    return;
  }
  if (target.matches(ITEM) && target.form) {
    resetAll(target.form);
    barsFor(target.form).forEach(update);
  }
}

function onClick(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) {
    return;
  }
  const bar = target.closest(BAR);
  const form = bar ? scopeOf(bar) : null;
  if (!bar || !form) {
    return;
  }
  if (target.closest('[data-nowo-ui-bulk-action][aria-disabled="true"]')) {
    event.preventDefault();
    return;
  }
  const ask = target.closest<HTMLButtonElement>('button[data-nowo-ui-bulk-action][data-nowo-ui-bulk-confirm]');
  if (ask) {
    if (ask.hasAttribute('data-nowo-ui-bulk-confirmed')) {
      ask.removeAttribute('data-nowo-ui-bulk-confirmed');
      return;
    }
    event.preventDefault();
    void confirmThenSubmit(ask, form);
    return;
  }
  if (target.closest('[data-nowo-ui-bulk-clear]')) {
    itemsOf(form).forEach((item) => {
      item.checked = false;
    });
    resetAll(form);
    update(bar);
    return;
  }
  if (target.closest('[data-nowo-ui-bulk-select-all-pages]')) {
    const field = allField(bar);
    if (field) {
      field.value = '1';
    }
    update(bar);
  }
}

/** Asks the action's question, then submits the form with that button as the submitter (name / value kept). */
export async function confirmThenSubmit(button: HTMLButtonElement, form: HTMLFormElement): Promise<boolean> {
  const question = button.getAttribute('data-nowo-ui-bulk-confirm') ?? '';
  const yes = typeof window.nowoUiAskConfirm === 'function' ? await window.nowoUiAskConfirm(question, { danger: true }) : window.confirm(question);
  if (!yes) {
    return false;
  }
  button.setAttribute('data-nowo-ui-bulk-confirmed', '');
  form.requestSubmit(button);
  return true;
}

export function upgradeBulkBars(scope: ParentNode = document): void {
  scope.querySelectorAll(BAR).forEach((bar) => {
    const form = scopeOf(bar);
    if (form) {
      togglesOf(form).forEach((toggle) => {
        toggle.hidden = false;
      });
    }
    bar.setAttribute('data-nowo-ui-bulk-ready', '');
    update(bar);
  });
}

let bound = false;

export function bindNowoUiBulkSelect(): void {
  upgradeBulkBars();
  if (bound) {
    return;
  }
  bound = true;
  document.addEventListener('change', onChange);
  document.addEventListener('click', onClick);
  // Back / forward cache: the browser restores the boxes; bring the bars in line again.
  window.addEventListener('pageshow', () => upgradeBulkBars());
}

bindNowoUiBulkSelect();
