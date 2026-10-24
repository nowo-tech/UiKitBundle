import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi, type Mock } from 'vitest';

import ListingCustomizerController, { formatMessage, moveItem, toPayload, type ListingClientState } from './listing_customizer_controller';
import { deleteListingCookie, patchListingCookie, readListingCookie, writeListingCookie } from './listing_cookie';

const COOKIE = 'nowo_ui_listing_invoices_index';

const MESSAGES = {
  required: 'Always shown',
  moveUp: 'Move up',
  moveDown: 'Move down',
  stack: 'Stack',
  dragHandle: 'Drag',
  moved: '%label% moved to %position% of %count%.',
  unsaved: 'Unsaved.',
  saving: 'Saving…',
  saveError: 'Save failed',
  tooLarge: 'Too large',
  sessionExpired: 'Expired',
  resetConfirm: 'Reset?',
  confirmOk: 'Reset',
  cancel: 'Cancel',
};

function state(overrides: Partial<ListingClientState> = {}): ListingClientState {
  return {
    listingKey: 'invoices.index',
    stored: false,
    mode: null,
    columns: [
      { key: 'number', label: 'Number', required: true, visible: true, merged: false },
      { key: 'customer', label: 'Customer', required: false, visible: true, merged: false },
      { key: 'total', label: 'Total', required: false, visible: true, merged: false },
    ],
    filters: [
      { key: 'q', label: 'Search', required: false, visible: true },
      { key: 'tag', label: 'Tag', required: false, visible: false },
    ],
    ...overrides,
  };
}

let app: Application;
let reload: Mock<() => void>;

function clearCookies(): void {
  for (const part of document.cookie.split(';')) {
    const name = part.split('=')[0]?.trim();
    if (name) {
      document.cookie = `${name}=; path=/; max-age=0`;
    }
  }
}

async function mount(attrs: Record<string, string> = {}, value: ListingClientState = state(), withTabs = true): Promise<HTMLElement> {
  const extra = Object.entries(attrs)
    .map(([name, v]) => ` data-listing-customizer-${name}-value='${v}'`)
    .join('');
  document.body.innerHTML = `
    <div data-controller="listing-customizer" data-listing-customizer-state-value='${JSON.stringify(value)}'
         data-listing-customizer-messages-value='${JSON.stringify(MESSAGES)}'${extra}>
      <button type="button" data-listing-customizer-target="toggle" data-action="listing-customizer#open">open</button>
      <button type="button" id="filters-link" data-action="listing-customizer#openFilters">filters</button>
      <dialog data-listing-customizer-target="dialog">
        ${
          withTabs
            ? `<div role="tablist">
          <button type="button" role="tab" data-panel="columns" data-listing-customizer-target="tabButton" data-action="listing-customizer#selectTab keydown->listing-customizer#tabKeydown">C</button>
          <button type="button" role="tab" data-panel="filters" data-listing-customizer-target="tabButton" data-action="listing-customizer#selectTab keydown->listing-customizer#tabKeydown">F</button>
        </div>`
            : ''
        }
        <section data-panel="columns" data-listing-customizer-target="panel"><ul data-kind="columns" data-listing-customizer-target="list"></ul></section>
        <section data-panel="filters" data-listing-customizer-target="panel" hidden><ul data-kind="filters" data-listing-customizer-target="list"></ul></section>
        <template data-listing-customizer-icon="grip"><svg class="grip"></svg></template>
        <template data-listing-customizer-icon="up"><svg class="up"></svg></template>
        <p data-listing-customizer-target="status"></p>
        <button type="button" data-listing-customizer-target="resetButton" data-action="listing-customizer#reset">Reset</button>
        <button type="button" data-listing-customizer-target="applyButton" data-action="listing-customizer#apply">Apply</button>
      </dialog>
    </div>`;
  await tick();

  return document.querySelector('[data-controller]') as HTMLElement;
}

const tick = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));

function controller(root: HTMLElement): ListingCustomizerController {
  return app.getControllerForElementAndIdentifier(root, 'listing-customizer') as ListingCustomizerController;
}

function rows(kind: string): HTMLLIElement[] {
  return [...document.querySelectorAll<HTMLLIElement>(`ul[data-kind="${kind}"] li`)];
}

function row(kind: string, key: string): HTMLLIElement {
  return document.querySelector(`ul[data-kind="${kind}"] li[data-key="${key}"]`) as HTMLLIElement;
}

function keys(kind: string): string[] {
  return rows(kind).map((li) => li.dataset.key ?? '');
}

function button(selector: string): HTMLButtonElement {
  return document.querySelector(selector) as HTMLButtonElement;
}

/** Answers the next askConfirm dialog. */
async function answer(confirm: boolean): Promise<void> {
  await tick();
  const dialogs = [...document.querySelectorAll('dialog.nowo-ui-confirm')];
  const dialog = dialogs[dialogs.length - 1] as HTMLDialogElement;
  const buttons = [...dialog.querySelectorAll('button')];
  (confirm ? buttons[buttons.length - 1] : buttons[0]).click();
  await tick();
}

beforeEach(() => {
  clearCookies();
  if (typeof globalThis.CSS?.escape !== 'function') {
    globalThis.CSS = { escape: (value: string) => value } as unknown as typeof CSS;
  }
  if (typeof HTMLDialogElement !== 'undefined') {
    HTMLDialogElement.prototype.showModal ??= function (this: HTMLDialogElement) {
      this.setAttribute('open', '');
    };
  }
  reload = vi.fn<() => void>();
  class TestController extends ListingCustomizerController {
    protected reload(): void {
      reload();
    }
  }
  app = Application.start();
  app.register('listing-customizer', TestController);
});

afterEach(() => {
  app.stop();
  document.body.innerHTML = '';
  clearCookies();
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
  delete (window as Window & { nowoUiOpenDialog?: unknown }).nowoUiOpenDialog;
});

describe('helpers', () => {
  it('formats, moves and builds payloads', () => {
    expect(formatMessage('%a% of %b% (%a%)', { a: 1, b: 'x' })).toBe('1 of x (1)');
    expect(moveItem([1, 2, 3], 0, 2)).toEqual([2, 3, 1]);
    const same = [1, 2];
    expect(moveItem(same, 0, 0)).toBe(same);
    expect(moveItem(same, -1, 0)).toBe(same);
    expect(moveItem(same, 0, 5)).toBe(same);
    expect(
      toPayload([
        { key: 'a', label: 'A', required: false, visible: true, merged: true },
        { key: 'b', label: 'B', required: true, visible: false, merged: true },
        { key: 'c', label: 'C', required: false, visible: false },
      ]),
    ).toEqual([
      { key: 'a', visible: true },
      { key: 'b', visible: true, merged: true },
      { key: 'c', visible: false },
    ]);
    expect(toPayload([{ key: 'a', label: 'A', required: false, visible: true }, { key: 'b', label: 'B', required: false, visible: true, merged: true }], false)).toEqual([
      { key: 'a', visible: true },
      { key: 'b', visible: true },
    ]);
  });

  it('reads, writes, patches and deletes the listing cookie', () => {
    expect(readListingCookie(COOKIE)).toBeNull();
    expect(writeListingCookie(COOKIE, { columns: [{ key: 'a', visible: true }] })).toBe(true);
    expect(readListingCookie(COOKIE)).toEqual({ columns: [{ key: 'a', visible: true }], v: 1 });
    expect(patchListingCookie(COOKIE, { mode: 'scroll' })).toBe(true);
    expect(readListingCookie(COOKIE)).toEqual({ columns: [{ key: 'a', visible: true }], v: 1, mode: 'scroll' });
    expect(writeListingCookie(COOKIE, { big: 'x'.repeat(50) }, { maxBytes: 20 })).toBe(false);
    expect(readListingCookie(COOKIE)?.mode).toBe('scroll');
    deleteListingCookie(COOKIE);
    expect(readListingCookie(COOKIE)).toBeNull();
    expect(patchListingCookie(COOKIE, { mode: 'responsive' }, { path: '/', maxAge: 10 })).toBe(true);
    expect(readListingCookie(COOKIE)).toEqual({ mode: 'responsive', v: 1 });

    document.cookie = `${COOKIE}=%7Bbroken; path=/`;
    expect(readListingCookie(COOKIE)).toBeNull();
    document.cookie = `${COOKIE}=${encodeURIComponent('[1]')}; path=/`;
    expect(readListingCookie(COOKIE)).toBeNull();
    document.cookie = `${COOKIE}=null; path=/`;
    expect(readListingCookie(COOKIE)).toBeNull();
  });

  it('writes the cookie with SameSite=Lax, path, max-age and Secure on https', () => {
    const writes: string[] = [];
    Object.defineProperty(document, 'cookie', { configurable: true, get: () => '', set: (v: string) => writes.push(v) });
    vi.stubGlobal('location', { protocol: 'https:' });
    writeListingCookie('n', { a: 1 }, { path: '/app', maxAge: 60 });
    deleteListingCookie('n', '/app');
    vi.unstubAllGlobals();
    writeListingCookie('n', {});
    Reflect.deleteProperty(document, 'cookie');
    expect(writes[0]).toBe(`n=${encodeURIComponent('{"a":1,"v":1}')}; path=/app; max-age=60; SameSite=Lax; Secure`);
    expect(writes[1]).toBe('n=; path=/app; max-age=0; SameSite=Lax; Secure');
    expect(writes[2]).toBe(`n=${encodeURIComponent('{"v":1}')}; path=/; max-age=31536000; SameSite=Lax`);
  });
});

describe('listing-customizer peer', () => {
  it('renders the editor rows from the server state', async () => {
    const root = await mount({ cookie: COOKIE });

    expect(keys('columns')).toEqual(['number', 'customer', 'total']);
    expect(keys('filters')).toEqual(['q', 'tag']);
    const number = row('columns', 'number');
    const checkbox = number.querySelector('input') as HTMLInputElement;
    expect(checkbox.checked).toBe(true);
    expect(checkbox.disabled).toBe(true);
    expect(number.querySelector('.nowo-ui-listing-customizer__required')?.textContent).toBe('Always shown');
    expect(number.draggable).toBe(true);
    expect(number.querySelector('.nowo-ui-listing-customizer__handle svg.grip')).not.toBeNull();
    expect(number.querySelector('button[data-move="up"] svg.up')).not.toBeNull();
    expect((number.querySelector('button[data-move="up"]') as HTMLButtonElement).disabled).toBe(true);
    expect((number.querySelector('button[data-stack]') as HTMLButtonElement).disabled).toBe(true);
    expect(number.querySelector('button[data-move="up"]')?.getAttribute('aria-label')).toBe('Move up: Number');
    expect((row('columns', 'total').querySelector('button[data-move="down"]') as HTMLButtonElement).disabled).toBe(true);
    expect(row('filters', 'tag').classList.contains('is-hidden')).toBe(true);
    expect(row('filters', 'tag').querySelector('button[data-stack]')).toBeNull();
    expect(button('[data-listing-customizer-target="applyButton"]').disabled).toBe(true);
    expect(button('[data-listing-customizer-target="resetButton"]').disabled).toBe(true);
    expect(root.hasAttribute('data-listing-dirty')).toBe(false);
  });

  it('toggles visibility and stacking, and tracks unsaved edits', async () => {
    const root = await mount({ cookie: COOKIE });
    const tag = row('filters', 'tag').querySelector('input') as HTMLInputElement;
    tag.checked = true;
    tag.dispatchEvent(new Event('change'));
    expect(row('filters', 'tag').classList.contains('is-hidden')).toBe(false);
    expect(root.hasAttribute('data-listing-dirty')).toBe(true);
    expect(button('[data-listing-customizer-target="applyButton"]').disabled).toBe(false);
    expect(document.querySelector('[data-listing-customizer-target="status"]')?.textContent).toBe('Unsaved.');

    tag.checked = false;
    tag.dispatchEvent(new Event('change'));
    expect(root.hasAttribute('data-listing-dirty')).toBe(false);
    expect(document.querySelector('[data-listing-customizer-target="status"]')?.textContent).toBe('');

    (row('columns', 'total').querySelector('button[data-stack]') as HTMLButtonElement).click();
    const stacked = row('columns', 'total');
    expect(stacked.classList.contains('is-stacked')).toBe(true);
    expect(stacked.querySelector('button[data-stack]')?.getAttribute('aria-pressed')).toBe('true');
    expect(document.activeElement).toBe(stacked.querySelector('button[data-stack]'));
    expect(root.hasAttribute('data-listing-dirty')).toBe(true);
    (stacked.querySelector('button[data-stack]') as HTMLButtonElement).click();
    expect(row('columns', 'total').classList.contains('is-stacked')).toBe(false);
  });

  it('reorders with the buttons and Alt+Arrow keys, keeping focus and announcing the move', async () => {
    await mount({ cookie: COOKIE });
    (row('columns', 'customer').querySelector('button[data-move="up"]') as HTMLButtonElement).click();
    expect(keys('columns')).toEqual(['customer', 'number', 'total']);
    // at the top the "up" button is disabled: focus falls back to "down"
    expect(document.activeElement).toBe(row('columns', 'customer').querySelector('button[data-move="down"]'));
    expect(document.querySelector('[data-listing-customizer-target="status"]')?.textContent).toBe('Customer moved to 1 of 3.');

    (row('columns', 'customer').querySelector('button[data-move="down"]') as HTMLButtonElement).click();
    expect(keys('columns')).toEqual(['number', 'customer', 'total']);
    expect(document.activeElement).toBe(row('columns', 'customer').querySelector('button[data-move="down"]'));

    row('columns', 'customer').dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', altKey: true, cancelable: true }));
    expect(keys('columns')).toEqual(['number', 'total', 'customer']);
    row('columns', 'customer').dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', altKey: true }));
    expect(keys('columns')).toEqual(['number', 'total', 'customer']);
    row('columns', 'customer').dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowUp', altKey: true }));
    expect(keys('columns')).toEqual(['number', 'customer', 'total']);
    row('columns', 'customer').dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowUp' }));
    row('columns', 'customer').dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', altKey: true }));
    expect(keys('columns')).toEqual(['number', 'customer', 'total']);
  });

  it('reorders by drag and drop within one list only', async () => {
    await mount({ cookie: COOKIE });
    const drag = (type: string, li: HTMLElement, data?: DataTransfer | null): Event => {
      const event = new Event(type, { bubbles: true, cancelable: true }) as DragEvent;
      Object.defineProperty(event, 'dataTransfer', { value: data ?? null });
      li.dispatchEvent(event);

      return event;
    };
    const transfer = { setData: vi.fn(), effectAllowed: '' } as unknown as DataTransfer;

    drag('dragstart', row('columns', 'total'), transfer);
    expect(row('columns', 'total').classList.contains('is-dragging')).toBe(true);
    expect(transfer.setData).toHaveBeenCalledWith('text/plain', 'total');
    expect(drag('dragover', row('filters', 'q')).defaultPrevented).toBe(false);
    const over = drag('dragover', row('columns', 'number'));
    expect(over.defaultPrevented).toBe(true);
    expect(row('columns', 'number').classList.contains('is-drop-target')).toBe(true);
    drag('dragleave', row('columns', 'number'));
    expect(row('columns', 'number').classList.contains('is-drop-target')).toBe(false);
    drag('drop', row('columns', 'number'));
    expect(keys('columns')).toEqual(['total', 'number', 'customer']);

    drag('dragstart', row('columns', 'total'));
    drag('dragend', row('columns', 'total'));
    expect(row('columns', 'total').classList.contains('is-dragging')).toBe(false);
    drag('drop', row('columns', 'customer'));
    expect(keys('columns')).toEqual(['total', 'number', 'customer']);

    drag('dragstart', row('filters', 'q'));
    drag('drop', row('columns', 'customer'));
    expect(keys('columns')).toEqual(['total', 'number', 'customer']);
  });

  it('switches tabs with clicks and arrow keys', async () => {
    await mount({ cookie: COOKIE });
    const [columns, filters] = [...document.querySelectorAll<HTMLButtonElement>('[role="tab"]')];
    const panels = [...document.querySelectorAll<HTMLElement>('section[data-panel]')];

    filters.click();
    expect(filters.getAttribute('aria-selected')).toBe('true');
    expect(columns.tabIndex).toBe(-1);
    expect(panels[0].hidden).toBe(true);
    expect(panels[1].hidden).toBe(false);

    filters.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true, cancelable: true }));
    expect(columns.getAttribute('aria-selected')).toBe('true');
    expect(document.activeElement).toBe(columns);
    columns.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft', bubbles: true }));
    expect(document.activeElement).toBe(filters);
    filters.dispatchEvent(new KeyboardEvent('keydown', { key: 'Home', bubbles: true }));
    expect(filters.getAttribute('aria-selected')).toBe('true');
  });

  it('opens the dialog (through the kit dialog script when loaded), the Filters tab, and closes it', async () => {
    const root = await mount({ cookie: COOKIE });
    const dialog = root.querySelector('dialog') as HTMLDialogElement;

    button('[data-listing-customizer-target="toggle"]').click();
    expect(dialog.open).toBe(true);
    button('[data-listing-customizer-target="toggle"]').click();
    controller(root).close();
    expect(dialog.open).toBe(false);
    controller(root).close();

    const kit = vi.fn((d: string | HTMLDialogElement | null) => (d as HTMLDialogElement).setAttribute('open', ''));
    (window as Window & { nowoUiOpenDialog?: unknown }).nowoUiOpenDialog = kit;
    button('#filters-link').click();
    expect(kit).toHaveBeenCalledWith(dialog, button('#filters-link'));
    expect(document.querySelectorAll('[role="tab"]')[1].getAttribute('aria-selected')).toBe('true');
    dialog.removeAttribute('open');
    controller(root).open();
    expect(kit).toHaveBeenLastCalledWith(dialog, button('[data-listing-customizer-target="toggle"]'));
  });

  it('applies to the cookie, keeping the stored table mode, then reloads', async () => {
    writeListingCookie(COOKIE, { mode: 'responsive' });
    const root = await mount({ cookie: COOKIE });
    const saved = vi.fn();
    root.addEventListener('listing-customizer:saved', saved);
    const total = row('columns', 'total').querySelector('input') as HTMLInputElement;
    total.checked = false;
    total.dispatchEvent(new Event('change'));

    button('[data-listing-customizer-target="applyButton"]').click();
    await tick();

    expect(readListingCookie(COOKIE)).toEqual({
      mode: 'responsive',
      v: 1,
      columns: [
        { key: 'number', visible: true },
        { key: 'customer', visible: true },
        { key: 'total', visible: false },
      ],
      filters: [
        { key: 'q', visible: true },
        { key: 'tag', visible: false },
      ],
    });
    expect(saved).toHaveBeenCalledTimes(1);
    expect(reload).toHaveBeenCalledTimes(1);
  });

  it('a cancelled saved event skips the reload; a too large layout is refused', async () => {
    const root = await mount({ cookie: COOKIE });
    root.addEventListener('listing-customizer:saved', (event) => event.preventDefault());
    await controller(root).apply();
    expect(reload).not.toHaveBeenCalled();
    expect(readListingCookie(COOKIE)?.columns).toHaveLength(3);
    clearCookies();

    const many = state({
      columns: Array.from({ length: 120 }, (_, i) => ({ key: `column_${i}`, label: `C${i}`, required: false, visible: true })),
    });
    const big = await mount({ cookie: COOKIE }, many);
    const failed = vi.fn();
    big.addEventListener('listing-customizer:save-failed', failed);
    await controller(big).apply();
    expect(readListingCookie(COOKIE)).toBeNull();
    expect(document.querySelector('[data-listing-customizer-target="status"]')?.textContent).toBe('Too large');
    expect(document.querySelector('[data-listing-customizer-target="status"]')?.classList.contains('is-error')).toBe(true);
    expect(failed.mock.calls[0][0].detail.message).toBe('Too large');
    expect(reload).not.toHaveBeenCalled();
  });

  it('applies without any store (page only) and still reloads', async () => {
    const root = await mount();
    await controller(root).apply();
    expect(document.cookie).toBe('');
    expect(reload).toHaveBeenCalledTimes(1);
  });

  it('resets the cookie after confirmation, keeping the table mode', async () => {
    writeListingCookie(COOKIE, { columns: [], mode: 'scroll' });
    const root = await mount({ cookie: COOKIE }, state({ stored: true }));
    expect(button('[data-listing-customizer-target="resetButton"]').disabled).toBe(false);

    const cancelled = controller(root).reset();
    await answer(false);
    await cancelled;
    expect(readListingCookie(COOKIE)).toEqual({ columns: [], mode: 'scroll', v: 1 });
    expect(reload).not.toHaveBeenCalled();

    const confirmed = controller(root).reset();
    await answer(true);
    await confirmed;
    expect(readListingCookie(COOKIE)).toEqual({ mode: 'scroll', v: 1 });
    expect(reload).toHaveBeenCalledTimes(1);

    writeListingCookie(COOKIE, { columns: [] });
    const again = controller(root).reset();
    await answer(true);
    await again;
    expect(readListingCookie(COOKIE)).toBeNull();
  });

  it('sends the layout to a host URL with the CSRF token', async () => {
    const fetchFn = vi.fn(async () => ({ ok: true, status: 204 }));
    vi.stubGlobal('fetch', fetchFn);
    const root = await mount({ url: '/prefs/invoices', token: 'tok', cookie: COOKIE });
    await controller(root).apply();

    expect(fetchFn).toHaveBeenCalledWith('/prefs/invoices', {
      method: 'PUT',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-CSRF-TOKEN': 'tok', 'Content-Type': 'application/json' },
      body: JSON.stringify({ columns: toPayload(state().columns), filters: toPayload(state().filters, false) }),
    });
    expect(readListingCookie(COOKIE)).toBeNull();
    expect(reload).toHaveBeenCalledTimes(1);

    const reset = controller(root).reset();
    await answer(true);
    await reset;
    expect(fetchFn).toHaveBeenLastCalledWith('/prefs/invoices', {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-CSRF-TOKEN': 'tok' },
    });
    expect(reload).toHaveBeenCalledTimes(2);
  });

  it('refreshes a stale token once, reports failures and ignores clicks while busy', async () => {
    let calls = 0;
    const fetchFn = vi.fn(async (url: string) => {
      if ('/prefs/token' === url) {
        return { ok: true, status: 200, json: async () => ({ token: 'fresh' }) };
      }
      calls++;

      return 1 === calls ? { ok: false, status: 403 } : { ok: true, status: 200 };
    });
    vi.stubGlobal('fetch', fetchFn);
    const root = await mount({ url: '/prefs/invoices', token: 'old', 'token-url': '/prefs/token', method: 'POST' });
    const pending = controller(root).apply();
    await controller(root).apply();
    await pending;
    expect(fetchFn).toHaveBeenCalledTimes(3);
    expect((fetchFn.mock.calls[2] as unknown as [string, RequestInit])[1].headers).toMatchObject({ 'X-CSRF-TOKEN': 'fresh' });
    expect(reload).toHaveBeenCalledTimes(1);
  });

  it('shows the session expired / save error messages', async () => {
    const failed = vi.fn();
    vi.stubGlobal('fetch', vi.fn(async () => ({ ok: false, status: 403 })));
    let root = await mount({ url: '/prefs/invoices' });
    root.addEventListener('listing-customizer:save-failed', failed);
    await controller(root).apply();
    expect(document.querySelector('[data-listing-customizer-target="status"]')?.textContent).toBe('Expired');
    expect(button('[data-listing-customizer-target="applyButton"]').disabled).toBe(true);

    vi.stubGlobal('fetch', vi.fn(async () => ({ ok: false, status: 500 })));
    root = await mount({ url: '/prefs/invoices' });
    await controller(root).apply();
    expect(document.querySelector('[data-listing-customizer-target="status"]')?.textContent).toBe('Save failed');

    vi.stubGlobal('fetch', vi.fn(async () => Promise.reject(new Error('offline'))));
    root = await mount({ url: '/prefs/invoices', 'token-url': '/prefs/token' });
    await controller(root).apply();
    expect(document.querySelector('[data-listing-customizer-target="status"]')?.textContent).toBe('Save failed');
    expect(reload).not.toHaveBeenCalled();
    expect(failed).toHaveBeenCalledTimes(1);
  });

  it('gives up when the token endpoint fails or answers without a token', async () => {
    for (const tokenResponse of [
      async () => ({ ok: false, status: 500 }),
      async () => ({ ok: true, status: 200, json: async () => ({}) }),
      async () => Promise.reject(new Error('down')),
    ]) {
      const fetchFn = vi.fn(async (url: string) => ('/prefs/token' === url ? tokenResponse() : { ok: false, status: 403 }));
      vi.stubGlobal('fetch', fetchFn);
      const root = await mount({ url: '/prefs/invoices', 'token-url': '/prefs/token' });
      await controller(root).apply();
      expect(fetchFn).toHaveBeenCalledTimes(2);
      expect(document.querySelector('[data-listing-customizer-target="status"]')?.textContent).toBe('Expired');
    }
  });

  it('works without tabs, status or buttons and with an empty state', async () => {
    document.body.innerHTML = `<div data-controller="listing-customizer"><ul data-kind="columns" data-listing-customizer-target="list"></ul>
      <section data-panel="columns" data-listing-customizer-target="panel"></section></div>`;
    await tick();
    const root = document.querySelector('[data-controller]') as HTMLElement;
    const c = controller(root);
    expect(rows('columns')).toEqual([]);
    c.open();
    c.openFilters();
    c.close();
    expect((root.querySelector('section') as HTMLElement).hidden).toBe(false);
    await c.apply();
    expect(reload).toHaveBeenCalledTimes(1);
  });

  it('uses showModal when the kit script is absent and labels default to empty', async () => {
    const value = state({ columns: [{ key: 'a', label: 'A', required: true, visible: true }] });
    document.body.innerHTML = `<div data-controller="listing-customizer" data-listing-customizer-state-value='${JSON.stringify(value)}'>
      <dialog data-listing-customizer-target="dialog"></dialog><ul data-kind="columns" data-listing-customizer-target="list"></ul>
      <p data-listing-customizer-target="status"></p></div>`;
    await tick();
    const root = document.querySelector('[data-controller]') as HTMLElement;
    const dialog = root.querySelector('dialog') as HTMLDialogElement;
    const showModal = vi.spyOn(dialog, 'showModal');
    controller(root).open(new Event('click'));
    expect(showModal).toHaveBeenCalledTimes(1);
    expect(row('columns', 'a').querySelector('.nowo-ui-listing-customizer__required')?.textContent).toBe('');
    expect(row('columns', 'a').querySelector('button[data-move="up"]')?.getAttribute('aria-label')).toBe(': A');

    const reset = controller(root).reset();
    await answer(true);
    await reset;
    expect(reload).toHaveBeenCalledTimes(1);
  });
});
