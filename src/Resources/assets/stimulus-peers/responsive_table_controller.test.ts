import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { setIconStateHandler } from './icon_state';
import { readListingCookie } from './listing_cookie';
import ResponsiveTableController, { RESPONSIVE_TABLE_PHONE_QUERY } from './responsive_table_controller';

const MESSAGES = { toResponsive: 'To responsive', toScroll: 'To scroll', details: 'Details', saveError: 'Not saved' };

/** Column widths of the fake layout (px); the scroller is `width` wide. */
let widths: Record<string, number> = {};
let width = 1000;
let coarse = false;
let phone = false;
/** Simulates `overflow-wrap: anywhere` on the cells: outside the measuring state the table can always shrink. */
let breakWords = false;
/** The detail column is only as wide as an empty cell while its buttons are hidden (2.6 regression). */
let buttonSizedDetail = false;

function setMatchMedia(): void {
  window.matchMedia = vi.fn().mockImplementation((query: string) => ({
    matches: (query === RESPONSIVE_TABLE_PHONE_QUERY && phone) || (query === '(pointer: coarse)' && coarse),
    media: query,
  })) as unknown as typeof window.matchMedia;
}

function mount(mode = '', url = '/prefs/items/mode', messages: Record<string, string> = MESSAGES): HTMLElement {
  document.body.innerHTML = `
    <div class="nowo-ui-card">
      <div data-controller="responsive-table" data-responsive-table-mode-value="${mode}" data-responsive-table-url-value="${url}"
           data-responsive-table-token-value="tok" data-responsive-table-token-url-value="/prefs/csrf-token"
           data-responsive-table-messages-value='${JSON.stringify(messages)}'>
        <button type="button" data-responsive-table-target="toggle" data-action="responsive-table#toggle"></button>
        <div data-responsive-table-target="scroller">
          <table data-responsive-table-target="table">
            <thead><tr>
              <th data-column="when" data-required="1">When</th>
              <th data-column="action" data-required="1">Action</th>
              <th data-column="project"><span data-cell-part>Project</span><span data-cell-part>Issue</span></th>
              <th data-column="detail" data-required="0">Detail</th>
              <th class="nowo-ui-row-detail-col" data-responsive-detail></th>
            </tr></thead>
            <tbody>
              <tr>
                <td data-column="when"> 2026-10-16 </td><td data-column="action">Opened</td>
                <td data-column="project"><a href="/p">Acme</a></td><td data-column="detail"><form><button type="submit">Mark</button></form></td>
                <td data-responsive-detail><button type="button" data-action="responsive-table#open" hidden>eye</button></td>
              </tr>
              <tr>
                <td data-column="when">—</td><td data-column="action">${'x'.repeat(130)}</td><td data-column="project"></td><td data-column="detail">d</td>
                <td data-responsive-detail><button type="button" data-action="responsive-table#open" hidden>eye</button></td>
              </tr>
            </tbody>
          </table>
        </div>
        <dialog data-responsive-table-target="dialog">
          <h2 data-responsive-table-target="dialogTitle"></h2>
          <button type="button" data-action="responsive-table#close">x</button>
          <div data-responsive-table-target="dialogBody"></div>
        </dialog>
      </div>
    </div>`;

  return document.querySelector<HTMLElement>('[data-controller="responsive-table"]') as HTMLElement;
}

function tableWidth(): number {
  const table = document.querySelector('table');
  const root = table?.closest('[data-table-mode]');
  let total = 0;
  for (const th of table?.querySelectorAll<HTMLElement>('thead th') ?? []) {
    if (th.hasAttribute('data-rt-hidden')) {
      continue;
    }
    if (th.hasAttribute('data-responsive-detail')) {
      // the detail column takes room only with data-rt-has-hidden; its width is the button's when one is shown (the
      // kit hides a [hidden] button with display: none !important, leaving an empty 12px cell)
      const shown = table?.querySelector<HTMLButtonElement>('tbody [data-responsive-detail] button:not([hidden])');
      total += th.closest('[data-rt-has-hidden]') ? (buttonSizedDetail && !shown ? 12 : 44) : 0;
      continue;
    }
    total += widths[th.dataset.column ?? ''] ?? 0;
  }

  return breakWords && !root?.hasAttribute('data-rt-measuring') ? Math.min(total, width) : total;
}

let app: Application;
const iconCalls: [string, string, boolean][] = [];

beforeEach(() => {
  widths = { when: 150, action: 150, project: 300, detail: 300 };
  width = 1000;
  coarse = false;
  phone = false;
  breakWords = false;
  buttonSizedDetail = false;
  iconCalls.length = 0;
  setIconStateHandler((kind, _host, state, animate) => iconCalls.push([kind, state, animate]));
  setMatchMedia();
  Object.defineProperty(HTMLElement.prototype, 'scrollWidth', {
    configurable: true,
    get(this: HTMLElement) {
      return 'TABLE' === this.tagName ? tableWidth() : 0;
    },
  });
  Object.defineProperty(HTMLElement.prototype, 'clientWidth', {
    configurable: true,
    get() {
      return width;
    },
  });
  HTMLDialogElement.prototype.showModal = vi.fn(function (this: HTMLDialogElement) {
    this.setAttribute('open', '');
  });
  HTMLDialogElement.prototype.close = vi.fn(function (this: HTMLDialogElement) {
    this.removeAttribute('open');
    this.dispatchEvent(new Event('close'));
  });
  globalThis.CSS = { escape: (value: string) => value } as unknown as typeof CSS;
  vi.stubGlobal('requestAnimationFrame', (cb: FrameRequestCallback) => {
    cb(0);
    return 1;
  });
  vi.stubGlobal('cancelAnimationFrame', vi.fn());
  app = Application.start();
  app.register('responsive-table', ResponsiveTableController);
});

afterEach(() => {
  app.stop();
  document.body.innerHTML = '';
  setIconStateHandler(null);
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

const tick = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));

describe('responsive-table peer', () => {
  it('defaults to scroll on desktop and to responsive on phones', async () => {
    const root = mount();
    await tick();
    expect(root.dataset.tableMode).toBe('scroll');
    expect(document.querySelector('[data-rt-hidden]')).toBeNull();
    expect(root.querySelector('[data-responsive-table-target="toggle"]')?.getAttribute('aria-label')).toBe('To responsive');
    expect(iconCalls[0]).toEqual(['table-mode', 'scroll', false]);

    phone = true;
    setMatchMedia();
    const second = mount();
    await tick();
    expect(second.dataset.tableMode).toBe('responsive');
  });

  it('hides the last non-required columns until the table fits, then shows the detail buttons', async () => {
    width = 700;
    const root = mount('responsive');
    await tick();
    const hidden = [...root.querySelectorAll('thead [data-rt-hidden]')].map((th) => (th as HTMLElement).dataset.column);
    expect(hidden).toEqual(['detail']);
    expect(root.querySelectorAll('tbody td[data-column="detail"][data-rt-hidden]')).toHaveLength(2);
    expect(root.hasAttribute('data-rt-has-hidden')).toBe(true);
    const button = root.querySelector<HTMLButtonElement>('tbody [data-responsive-detail] button') as HTMLButtonElement;
    expect(button.hidden).toBe(false);
    expect(button.getAttribute('aria-label')).toBe('Details: 2026-10-16 · Opened');
    expect(root.querySelector('[data-responsive-table-target="toggle"]')?.getAttribute('aria-pressed')).toBe('true');

    // Everything fits: nothing hidden, no detail column.
    width = 2000;
    const wide = mount('responsive');
    await tick();
    expect(wide.querySelector('[data-rt-hidden]')).toBeNull();
    expect(wide.hasAttribute('data-rt-has-hidden')).toBe(false);

    // Required columns never hide, even when the table still overflows.
    width = 100;
    const narrow = mount('responsive');
    await tick();
    expect([...narrow.querySelectorAll('thead [data-rt-hidden]')].map((th) => (th as HTMLElement).dataset.column)).toEqual(['project', 'detail']);
  });

  it('measures the detail column with its buttons shown, so the fitted table never ends a button wider (2.6)', async () => {
    // Before 2.6 phase 2 measured the detail column while its buttons were still hidden (an empty 12px cell), then
    // markRows() showed them: at 620px the table "fit" at 612px and ended at 644px, wider than its scroller.
    buttonSizedDetail = true;
    width = 620;
    const root = mount('responsive');
    await tick();
    expect([...root.querySelectorAll('thead [data-rt-hidden]')].map((th) => (th as HTMLElement).dataset.column)).toEqual(['project', 'detail']);
    expect(root.querySelectorAll('tbody [data-responsive-detail] button:not([hidden])')).toHaveLength(2);
    expect(tableWidth()).toBeLessThanOrEqual(width);

    // Wide enough again: no column hidden, the buttons are hidden again and the detail column takes no room.
    width = 1200;
    const wide = mount('responsive');
    await tick();
    expect(wide.querySelectorAll('thead [data-rt-hidden]')).toHaveLength(0);
    expect(wide.querySelectorAll('tbody [data-responsive-detail] button[hidden]')).toHaveLength(2);
  });

  it('measures with unbreakable words, so word breaking can never hide the overflow (1.17.5 regression)', async () => {
    // Before 1.17.5 the cells could break words anywhere while measuring: the table always "fit" and no column hid.
    breakWords = true;
    width = 700;
    const root = mount('responsive');
    await tick();
    expect([...root.querySelectorAll('thead [data-rt-hidden]')].map((th) => (th as HTMLElement).dataset.column)).toEqual(['detail']);
    expect(root.hasAttribute('data-rt-has-hidden')).toBe(true);
    expect(root.hasAttribute('data-rt-measuring')).toBe(false);
    expect(root.hasAttribute('data-rt-overflow')).toBe(false);

    // Only the required columns are left and they still overflow (320px): last resort, words may break.
    width = 250;
    const narrow = mount('responsive');
    await tick();
    expect([...narrow.querySelectorAll('thead [data-rt-hidden]')].map((th) => (th as HTMLElement).dataset.column)).toEqual(['project', 'detail']);
    expect(narrow.hasAttribute('data-rt-overflow')).toBe(true);
    expect(narrow.hasAttribute('data-rt-measuring')).toBe(false);

    // Back to scroll mode: nothing hidden, no overflow flag.
    narrow.setAttribute('data-responsive-table-mode-value', 'scroll');
    await tick();
    expect(narrow.querySelector('[data-rt-hidden]')).toBeNull();
    expect(narrow.hasAttribute('data-rt-overflow')).toBe(false);
  });

  it('works without messages (no labels touched; detail buttons named after the row)', async () => {
    width = 700;
    const root = mount('responsive', '', {});
    await tick();
    const toggle = root.querySelector('[data-responsive-table-target="toggle"]') as HTMLButtonElement;
    expect(toggle.hasAttribute('aria-label')).toBe(false);
    const buttons = root.querySelectorAll<HTMLButtonElement>('tbody [data-responsive-detail] button');
    expect(buttons[0]?.getAttribute('aria-label')).toBe('2026-10-16 · Opened');
    // Second row: "—" and a long action — the title is cut.
    expect(buttons[1]?.getAttribute('aria-label')).toHaveLength(120);

    // A row without any text keeps no label.
    root.querySelectorAll('tbody tr:first-child td[data-column]').forEach((cell) => cell.replaceChildren());
    root.setAttribute('data-responsive-table-mode-value', 'scroll');
    await tick();
    root.setAttribute('data-responsive-table-mode-value', 'responsive');
    await tick();
    expect(buttons[0]?.getAttribute('aria-label')).toBe('2026-10-16 · Opened');
  });

  it('opens a row in the dialog with every column, moves the cells back on close and refocuses the button', async () => {
    width = 700;
    const root = mount('responsive');
    await tick();
    const button = root.querySelector<HTMLButtonElement>('tbody tr [data-responsive-detail] button') as HTMLButtonElement;
    button.click();
    const dialog = root.querySelector('dialog') as HTMLDialogElement;
    expect(dialog.open).toBe(true);
    expect(root.querySelector('[data-responsive-table-target="dialogTitle"]')?.textContent).toBe('2026-10-16 · Opened');
    expect([...dialog.querySelectorAll('dt')].map((dt) => dt.textContent)).toEqual(['When', 'Action', 'Project · Issue', 'Detail']);
    expect(dialog.querySelector('dl')?.className).toBe('nowo-ui-row-detail__list');
    expect(dialog.querySelector('dd form')).not.toBeNull();
    expect(root.querySelector('tbody td[data-column="detail"] form')).toBeNull();

    // A second open while one is open is ignored.
    button.click();
    expect(dialog.querySelectorAll('dl')).toHaveLength(1);

    root.querySelector<HTMLButtonElement>('[data-action="responsive-table#close"]')?.click();
    expect(dialog.open).toBe(false);
    expect(root.querySelector('tbody td[data-column="detail"] form')).not.toBeNull();
    expect(document.activeElement).toBe(button);

    // A click on the backdrop (the dialog itself) closes it; a click inside does not.
    button.click();
    dialog.querySelector('dt')?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    expect(dialog.open).toBe(true);
    dialog.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    expect(dialog.open).toBe(false);

    // Long titles are cut.
    root.querySelectorAll<HTMLButtonElement>('tbody tr [data-responsive-detail] button')[1]?.click();
    expect(root.querySelector('[data-responsive-table-target="dialogTitle"]')?.textContent).toHaveLength(120);
  });

  it('opens a row on tap with a coarse pointer, outside links and buttons only', async () => {
    width = 700;
    coarse = true;
    setMatchMedia();
    const root = mount('responsive');
    await tick();
    const dialog = root.querySelector('dialog') as HTMLDialogElement;
    root.querySelector<HTMLAnchorElement>('tbody a')?.addEventListener('click', (event) => event.preventDefault());
    root.querySelector<HTMLAnchorElement>('tbody a')?.click();
    expect(dialog.open).toBe(false);
    (root.querySelector('tbody td[data-column="action"]') as HTMLElement).click();
    expect(dialog.open).toBe(true);
    dialog.close();

    // A tap outside the body rows does nothing.
    (root.querySelector('thead th') as HTMLElement).click();
    expect(dialog.open).toBe(false);

    // Fine pointer, or nothing hidden, or scroll mode: a row tap does nothing.
    coarse = false;
    setMatchMedia();
    (root.querySelector('tbody td[data-column="action"]') as HTMLElement).click();
    expect(dialog.open).toBe(false);
    width = 2000;
    const wide = mount('responsive');
    await tick();
    (wide.querySelector('tbody td[data-column="action"]') as HTMLElement).click();
    expect((wide.querySelector('dialog') as HTMLDialogElement).open).toBe(false);
    coarse = true;
    setMatchMedia();
    const scroll = mount('scroll');
    await tick();
    (scroll.querySelector('thead th') as HTMLElement).click();
    expect((scroll.querySelector('dialog') as HTMLDialogElement).open).toBe(false);
  });

  it('toggles and saves the mode, refreshes a stale token once, and switches back when the save fails', async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(new Response('{}', { status: 200 }))
      .mockResolvedValueOnce(new Response('{"error":"invalid_csrf"}', { status: 403 }))
      .mockResolvedValueOnce(new Response('{"token":"fresh"}', { status: 200 }))
      .mockResolvedValueOnce(new Response('{}', { status: 200 }))
      .mockResolvedValueOnce(new Response('{}', { status: 500 }));
    vi.stubGlobal('fetch', fetchMock);
    const root = mount('scroll');
    const changed: string[] = [];
    const failed: unknown[] = [];
    root.addEventListener('responsive-table:changed', (event) => changed.push((event as CustomEvent).detail.mode));
    root.addEventListener('responsive-table:save-failed', (event) => failed.push((event as CustomEvent).detail));
    await tick();
    const toggle = root.querySelector<HTMLButtonElement>('[data-responsive-table-target="toggle"]') as HTMLButtonElement;

    toggle.click();
    await tick();
    expect(root.dataset.tableMode).toBe('responsive');
    expect(fetchMock.mock.calls[0]?.[0]).toBe('/prefs/items/mode');
    expect(fetchMock.mock.calls[0]?.[1]?.method).toBe('PUT');
    expect(JSON.parse(String(fetchMock.mock.calls[0]?.[1]?.body))).toEqual({ mode: 'responsive' });
    expect(fetchMock.mock.calls[0]?.[1]?.headers).toMatchObject({ 'X-CSRF-TOKEN': 'tok' });
    expect(iconCalls).toContainEqual(['table-mode', 'responsive', true]);

    toggle.click();
    await tick();
    await tick();
    expect(root.dataset.tableMode).toBe('scroll');
    expect(fetchMock.mock.calls[3]?.[1]?.headers).toMatchObject({ 'X-CSRF-TOKEN': 'fresh' });

    toggle.click();
    await tick();
    await tick();
    expect(root.dataset.tableMode).toBe('scroll');
    expect(changed).toEqual(['responsive', 'scroll', 'responsive']);
    expect(failed).toEqual([{ mode: 'responsive', previous: 'scroll', message: 'Not saved' }]);
  });

  it('treats network errors, a missing token URL or a bad token answer as a failed save, and a page without URL as saved', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));
    const root = mount('scroll');
    await tick();
    root.querySelector<HTMLButtonElement>('[data-responsive-table-target="toggle"]')?.click();
    await tick();
    await tick();
    expect(root.dataset.tableMode).toBe('scroll');

    const fetchMock = vi.fn().mockResolvedValueOnce(new Response('{}', { status: 403 })).mockResolvedValueOnce(new Response('{"token":""}', { status: 200 }));
    vi.stubGlobal('fetch', fetchMock);
    const second = mount('scroll');
    await tick();
    second.querySelector<HTMLButtonElement>('[data-responsive-table-target="toggle"]')?.click();
    await tick();
    await tick();
    expect(second.dataset.tableMode).toBe('scroll');

    vi.stubGlobal('fetch', vi.fn().mockResolvedValueOnce(new Response('{}', { status: 403 })).mockResolvedValueOnce(new Response('{}', { status: 500 })));
    const notOk = mount('scroll');
    await tick();
    notOk.querySelector<HTMLButtonElement>('[data-responsive-table-target="toggle"]')?.click();
    await tick();
    await tick();
    expect(notOk.dataset.tableMode).toBe('scroll');

    vi.stubGlobal('fetch', vi.fn().mockResolvedValueOnce(new Response('{}', { status: 403 })).mockRejectedValueOnce(new Error('offline')));
    const third = mount('scroll');
    await tick();
    third.querySelector<HTMLButtonElement>('[data-responsive-table-target="toggle"]')?.click();
    await tick();
    await tick();
    expect(third.dataset.tableMode).toBe('scroll');

    vi.stubGlobal('fetch', vi.fn().mockResolvedValueOnce(new Response('{}', { status: 403 })));
    const fourth = mount('scroll');
    fourth.dataset.responsiveTableTokenUrlValue = '';
    await tick();
    fourth.querySelector<HTMLButtonElement>('[data-responsive-table-target="toggle"]')?.click();
    await tick();
    await tick();
    expect(fourth.dataset.tableMode).toBe('scroll');

    const noMessages: unknown[] = [];
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('{}', { status: 500 })));
    const sixth = mount('scroll', '/prefs/items/mode', {});
    sixth.addEventListener('responsive-table:save-failed', (event) => noMessages.push((event as CustomEvent).detail.message));
    await tick();
    sixth.querySelector<HTMLButtonElement>('[data-responsive-table-target="toggle"]')?.click();
    await tick();
    await tick();
    expect(noMessages).toEqual(['']);

    const fifth = mount('scroll', '');
    await tick();
    fifth.querySelector<HTMLButtonElement>('[data-responsive-table-target="toggle"]')?.click();
    await tick();
    expect(fifth.dataset.tableMode).toBe('responsive');
  });

  it('refits on resize and cleans up on disconnect (closing an open dialog)', async () => {
    const observed: ResizeObserverCallback[] = [];
    const disconnect = vi.fn();
    vi.stubGlobal(
      'ResizeObserver',
      class {
        constructor(callback: ResizeObserverCallback) {
          observed.push(callback);
        }
        observe = vi.fn();
        disconnect = disconnect;
      },
    );
    width = 2000;
    const root = mount('responsive');
    await tick();
    expect(root.querySelector('[data-rt-hidden]')).toBeNull();
    width = 700;
    observed[0]?.([], {} as ResizeObserver);
    expect(root.querySelector('thead [data-rt-hidden]')).not.toBeNull();

    root.querySelector<HTMLButtonElement>('tbody [data-responsive-detail] button')?.click();
    expect((root.querySelector('dialog') as HTMLDialogElement).open).toBe(true);
    root.remove();
    await tick();
    expect(disconnect).toHaveBeenCalled();
  });

  it('applies a mode set from outside, once, and works without a toggle', async () => {
    width = 700;
    const root = mount('scroll');
    root.querySelector('[data-responsive-table-target="toggle"]')?.remove();
    await tick();
    expect(root.dataset.tableMode).toBe('scroll');
    root.setAttribute('data-responsive-table-mode-value', 'responsive');
    await tick();
    expect(root.dataset.tableMode).toBe('responsive');
    expect(root.querySelector('thead [data-rt-hidden]')).not.toBeNull();
    expect(iconCalls).toEqual([]);
  });

  it('ignores a detail action outside a row and lists only the cells a row has', async () => {
    const root = mount('responsive');
    await tick();
    const loose = document.createElement('button');
    loose.setAttribute('data-action', 'responsive-table#open');
    root.append(loose);
    await tick();
    loose.click();
    expect((root.querySelector('dialog') as HTMLDialogElement).open).toBe(false);

    root.querySelector('tbody tr td[data-column="detail"]')?.remove();
    root.querySelector<HTMLButtonElement>('tbody tr [data-responsive-detail] button')?.click();
    expect([...root.querySelectorAll('dialog dt')].map((dt) => dt.textContent)).toEqual(['When', 'Action', 'Project · Issue']);
  });

  it('lets a subclass handle a failed save', async () => {
    const seen: string[] = [];
    class Custom extends ResponsiveTableController {
      protected saveFailed(mode: 'scroll' | 'responsive', previous: string): void {
        seen.push(`${previous}->${mode}`);
      }
    }
    app.register('custom-table', Custom);
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('{}', { status: 500 })));
    const root = mount('scroll');
    root.setAttribute('data-controller', 'custom-table');
    for (const attr of [...root.attributes]) {
      if (attr.name.startsWith('data-responsive-table-')) {
        root.setAttribute(attr.name.replace('data-responsive-table-', 'data-custom-table-'), attr.value);
      }
    }
    root.querySelectorAll('[data-responsive-table-target]').forEach((el) => {
      el.setAttribute('data-custom-table-target', el.getAttribute('data-responsive-table-target') as string);
    });
    const toggle = root.querySelector('[data-responsive-table-target="toggle"]') as HTMLButtonElement;
    toggle.setAttribute('data-action', 'custom-table#toggle');
    await tick();
    toggle.click();
    await tick();
    await tick();
    expect(seen).toEqual(['scroll->responsive']);
  });
  it('keeps the mode in the listing cookie without a URL, and switches back when the cookie is full', async () => {
    const fetchFn = vi.fn();
    vi.stubGlobal('fetch', fetchFn);
    document.cookie = `nowo_ui_listing_items=${encodeURIComponent('{"v":1,"columns":[]}')}; path=/`;
    const root = mount('', '');
    root.setAttribute('data-responsive-table-cookie-value', 'nowo_ui_listing_items');
    await tick();
    const toggle = root.querySelector('[data-responsive-table-target="toggle"]') as HTMLButtonElement;
    toggle.click();
    await tick();
    expect(readListingCookie('nowo_ui_listing_items')).toEqual({ v: 1, columns: [], mode: 'responsive' });
    expect(root.dataset.tableMode).toBe('responsive');
    expect(fetchFn).not.toHaveBeenCalled();

    document.cookie = `nowo_ui_listing_items=${encodeURIComponent(JSON.stringify({ x: 'y'.repeat(3990) }))}; path=/`;
    const failed = vi.fn();
    root.addEventListener('responsive-table:save-failed', failed);
    toggle.click();
    await tick();
    await tick();
    expect(failed).toHaveBeenCalledTimes(1);
    expect(root.dataset.tableMode).toBe('responsive');
    document.cookie = 'nowo_ui_listing_items=; path=/; max-age=0';
  });
});
