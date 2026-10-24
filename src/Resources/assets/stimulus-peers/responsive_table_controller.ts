/**
 * Optional Stimulus peer (1.17) — a table in *Scroll* mode (every column, horizontal scroll) or *Responsive* mode
 * (the columns that fit, no horizontal scroll, a row detail dialog for the rest).
 *
 * Markup: `partials/_responsive_table.html.twig` (root, toggle, scroller, dialog) with header cells carrying
 * `data-column` (+ `data-required="1"` for columns that never hide) and row cells `data-column`; each row ends with
 * the detail cell (`partials/_row_detail_cell.html.twig`, `[data-responsive-detail]`) and the header with
 * `partials/_row_detail_head.html.twig`.
 *
 * Fit: in Responsive mode every column is shown, then the last non-required ones (document order) are hidden until
 * the table fits its scroller — first measured without the detail column, then with it (2.6: with its buttons shown,
 * their final layout). Recomputed on resize.
 * The detail dialog MOVES the row's cell contents (forms, ids and listeners intact) and puts them back on close.
 *
 * Persistence is host-owned: with a `url` the toggle sends `{"mode": "scroll"|"responsive"}` (JSON, `X-CSRF-TOKEN`
 * header, `method` default PUT); a 403 refreshes the token once from `tokenUrl` (`{"token": "…"}`). Without a `url`
 * but with a `cookie` (listing customizer: the listing cookie name) the mode is merged into that cookie (`mode`
 * member, `listing_cookie.ts`), read by the server on the next render. Without either the mode only lives on the
 * page. A failed save switches back and dispatches `responsive-table:save-failed`.
 *
 * Identifier: `responsive-table`
 * Targets: `scroller`, `table`, `toggle` (optional), `dialog`, `dialogTitle`, `dialogBody`.
 * Values: `mode` (`scroll` | `responsive` | empty = default), `url`, `token`, `tokenUrl`, `method` (default `PUT`),
 *         `cookie` (listing cookie name, used without `url`), `cookiePath` (default `/`),
 *         `phoneQuery` (default `(max-width: 767px)`: no stored mode → responsive there, scroll elsewhere),
 *         `partSelector` (stacked header parts joined in the dialog labels, default `[data-cell-part]`),
 *         `messages` (`{toResponsive, toScroll, details, saveError}`; `saveError` since 1.17.1).
 * Actions: `responsive-table#toggle`, `responsive-table#open` (detail button), `responsive-table#close`.
 * Root attributes: `data-table-mode`, `data-rt-has-hidden`, `data-rt-overflow` (required columns still too wide:
 *         words may break), `data-rt-measuring` (only during a fit); hidden cells: `data-rt-hidden`.
 * Events: `responsive-table:changed` (`detail.mode`), `responsive-table:save-failed` (`detail.mode`,
 *         `detail.previous`, `detail.message` = `messages.saveError` or empty).
 */

import { Controller } from '@hotwired/stimulus';

import { syncIconState } from './icon_state';
import { patchListingCookie } from './listing_cookie';

export type TableMode = 'scroll' | 'responsive';

export const RESPONSIVE_TABLE_PHONE_QUERY = '(max-width: 767px)';

/** Elements a tap on a row must leave alone (they act on their own). */
const INTERACTIVE = 'a, button, input, select, textarea, label, summary, [role="button"], form';

type Messages = {
  toResponsive?: string;
  toScroll?: string;
  details?: string;
  /** Text for the host's error toast (1.17.1), passed in the `save-failed` event detail. */
  saveError?: string;
};

export default class ResponsiveTableController extends Controller<HTMLElement> {
  static targets = ['scroller', 'table', 'toggle', 'dialog', 'dialogTitle', 'dialogBody'];

  static values = {
    mode: { type: String, default: '' },
    url: { type: String, default: '' },
    token: { type: String, default: '' },
    tokenUrl: { type: String, default: '' },
    method: { type: String, default: 'PUT' },
    cookie: { type: String, default: '' },
    cookiePath: { type: String, default: '/' },
    phoneQuery: { type: String, default: RESPONSIVE_TABLE_PHONE_QUERY },
    partSelector: { type: String, default: '[data-cell-part]' },
    messages: { type: Object, default: {} },
  };

  declare readonly scrollerTarget: HTMLElement;
  declare readonly tableTarget: HTMLTableElement;
  declare readonly hasToggleTarget: boolean;
  declare readonly toggleTarget: HTMLButtonElement;
  declare readonly dialogTarget: HTMLDialogElement;
  declare readonly dialogTitleTarget: HTMLElement;
  declare readonly dialogBodyTarget: HTMLElement;
  declare modeValue: string;
  declare readonly urlValue: string;
  declare tokenValue: string;
  declare readonly tokenUrlValue: string;
  declare readonly methodValue: string;
  declare readonly cookieValue: string;
  declare readonly cookiePathValue: string;
  declare readonly phoneQueryValue: string;
  declare readonly partSelectorValue: string;
  declare readonly messagesValue: Messages;

  private observer: ResizeObserver | null = null;
  /** Mode value the markup reflects (a programmatic change of `mode` re-applies only when it differs). */
  private applied: string | null = null;
  private frame = 0;
  /** Cells moved into the open dialog, put back on close. */
  private moved: { cell: HTMLElement; nodes: Node[] }[] = [];
  private openedFrom: HTMLElement | null = null;
  private readonly onRowClick = (event: MouseEvent): void => this.rowClick(event);
  private readonly onDialogClose = (): void => this.restore();
  private readonly onDialogClick = (event: MouseEvent): void => {
    // A click on the backdrop (outside the dialog box) closes it.
    if (event.target === this.dialogTarget) {
      this.dialogTarget.close();
    }
  };

  connect(): void {
    this.apply(false);
    this.tableTarget.addEventListener('click', this.onRowClick);
    this.dialogTarget.addEventListener('close', this.onDialogClose);
    this.dialogTarget.addEventListener('click', this.onDialogClick);
    if (typeof ResizeObserver !== 'undefined') {
      this.observer = new ResizeObserver(() => this.schedule());
      this.observer.observe(this.scrollerTarget);
    }
  }

  /** The `mode` value changed from outside (another script, a test): apply it without animation. */
  modeValueChanged(value: string): void {
    if (null !== this.applied && value !== this.applied) {
      this.apply(false);
    }
  }

  disconnect(): void {
    this.observer?.disconnect();
    this.observer = null;
    cancelAnimationFrame(this.frame);
    this.tableTarget.removeEventListener('click', this.onRowClick);
    this.dialogTarget.removeEventListener('close', this.onDialogClose);
    this.dialogTarget.removeEventListener('click', this.onDialogClick);
    if (this.dialogTarget.open) {
      this.dialogTarget.close();
    }
    this.restore();
  }

  /** Effective mode: the stored choice, else responsive on phones and scroll elsewhere. */
  mode(): TableMode {
    if ('scroll' === this.modeValue || 'responsive' === this.modeValue) {
      return this.modeValue;
    }

    return window.matchMedia?.(this.phoneQueryValue).matches ? 'responsive' : 'scroll';
  }

  /** Toggle: switch modes at once, then save; a failed save switches back and reports it. */
  async toggle(): Promise<void> {
    const previous = this.modeValue;
    const next: TableMode = 'responsive' === this.mode() ? 'scroll' : 'responsive';
    this.modeValue = next;
    this.apply(true);
    this.dispatch('changed', { detail: { mode: next } });
    if (!(await this.save(next))) {
      this.modeValue = previous;
      this.apply(true);
      this.saveFailed(next, previous);
    }
  }

  /** Detail button of a row. */
  open(event: Event): void {
    const row = (event.currentTarget as HTMLElement).closest('tr');
    if (row instanceof HTMLTableRowElement) {
      this.showRow(row, event.currentTarget as HTMLElement);
    }
  }

  close(): void {
    this.dialogTarget.close();
  }

  /**
   * A save failed (the mode is already switched back). Dispatches `responsive-table:save-failed` with the
   * `saveError` message; hosts show it from a listener or override this method in a subclass.
   */
  protected saveFailed(mode: TableMode, previous: string): void {
    this.dispatch('save-failed', { detail: { mode, previous, message: this.messagesValue.saveError ?? '' } });
  }

  /** Applies the mode to the markup (and the toggle) and fits the columns. */
  private apply(animate: boolean): void {
    this.applied = this.modeValue;
    const mode = this.mode();
    const responsive = 'responsive' === mode;
    this.element.dataset.tableMode = mode;
    if (this.hasToggleTarget) {
      this.toggleTarget.setAttribute('aria-pressed', responsive ? 'true' : 'false');
      const label = (responsive ? this.messagesValue.toScroll : this.messagesValue.toResponsive) ?? '';
      if ('' !== label) {
        this.toggleTarget.setAttribute('aria-label', label);
        this.toggleTarget.title = label;
      }
      syncIconState('table-mode', this.toggleTarget, mode, animate);
    }
    this.fit();
  }

  private schedule(): void {
    cancelAnimationFrame(this.frame);
    this.frame = requestAnimationFrame(() => this.fit());
  }

  /**
   * Responsive mode: show every column, then hide the last non-required ones until the table fits its scroller.
   * Scroll mode: nothing hidden.
   *
   * The measurement runs under `data-rt-measuring` (CSS: words unbreakable, `white-space: normal`), so a cell's
   * min-content is its longest word: wrapping at spaces may shrink the table, breaking words may not — otherwise any
   * table "fits" by splitting words letter by letter and no column is ever hidden. Only when the required columns
   * still overflow does the root get `data-rt-overflow` (CSS: `overflow-wrap: anywhere` on the visible cells), the
   * last resort that keeps 320px screens free of horizontal scroll.
   */
  private fit(): void {
    const headers = this.headers();
    this.element.setAttribute('data-rt-measuring', '');
    this.element.removeAttribute('data-rt-overflow');
    headers.forEach((header) => this.setHidden(header, false));
    // Phase 1: without the detail column. Phase 2 (something must go): with it, since it takes room too.
    this.element.removeAttribute('data-rt-has-hidden');
    if ('responsive' === this.mode() && !this.fits()) {
      this.element.setAttribute('data-rt-has-hidden', '');
      // 2.6: measure the detail column with its final layout — buttons shown (markRows() shows them afterwards; a
      // still-hidden button measured as an empty cell left the table one button wider than its scroller).
      this.detailButtons().forEach((button) => {
        button.hidden = false;
      });
      const candidates = headers.filter((header) => '1' !== header.dataset.required).reverse();
      for (const header of candidates) {
        if (this.fits()) {
          break;
        }
        this.setHidden(header, true);
      }
      this.element.toggleAttribute('data-rt-overflow', !this.fits());
    }
    this.element.removeAttribute('data-rt-measuring');
    this.markRows();
  }

  private fits(): boolean {
    return this.tableTarget.scrollWidth <= this.scrollerTarget.clientWidth;
  }

  /** Column header cells (the detail column has no `data-column`). */
  private headers(): HTMLTableCellElement[] {
    return [...this.tableTarget.querySelectorAll<HTMLTableCellElement>('thead th[data-column]')];
  }

  /** The row detail buttons (one per row). */
  private detailButtons(): HTMLButtonElement[] {
    return [...this.tableTarget.querySelectorAll<HTMLButtonElement>('tbody [data-responsive-detail] button')];
  }

  /** Hides or shows a header and the row cells of its column. */
  private setHidden(header: HTMLTableCellElement, hidden: boolean): void {
    const key = header.dataset.column ?? '';
    header.toggleAttribute('data-rt-hidden', hidden);
    this.tableTarget.querySelectorAll<HTMLElement>(`tbody td[data-column="${CSS.escape(key)}"]`).forEach((cell) => {
      cell.toggleAttribute('data-rt-hidden', hidden);
    });
  }

  /** Shows the detail buttons (and names them after their row) when a column is hidden. */
  private markRows(): void {
    const anyHidden = this.headers().some((header) => header.hasAttribute('data-rt-hidden'));
    this.element.toggleAttribute('data-rt-has-hidden', anyHidden);
    const details = this.messagesValue.details ?? '';
    this.detailButtons().forEach((button) => {
      button.hidden = !anyHidden;
      const row = button.closest('tr');
      const title = row ? this.rowTitle(row) : '';
      const label = '' === details ? title : `${details}: ${title}`;
      if ('' !== label.trim()) {
        button.setAttribute('aria-label', label.trim());
      }
    });
  }

  /** On touch screens a tap on a row (outside its links / buttons / fields) opens its details. */
  private rowClick(event: MouseEvent): void {
    if ('responsive' !== this.mode() || !this.element.hasAttribute('data-rt-has-hidden')) {
      return;
    }
    if (!window.matchMedia?.('(pointer: coarse)').matches) {
      return;
    }
    const target = event.target as Element | null;
    if (target?.closest(INTERACTIVE)) {
      return;
    }
    const row = target?.closest('tbody tr');
    if (row instanceof HTMLTableRowElement) {
      this.showRow(row, row.querySelector<HTMLElement>('[data-responsive-detail] button') ?? row);
    }
  }

  /**
   * Opens the dialog with every column of the row as label / value pairs. The cell contents move into the dialog
   * (forms, links and their listeners keep working, ids stay unique) and go back when it closes.
   */
  private showRow(row: HTMLTableRowElement, from: HTMLElement): void {
    if (this.dialogTarget.open) {
      return;
    }
    this.openedFrom = from;
    this.dialogTitleTarget.textContent = this.rowTitle(row);
    const list = document.createElement('dl');
    list.className = 'nowo-ui-row-detail__list';
    for (const header of this.headers()) {
      const cell = row.querySelector<HTMLElement>(`td[data-column="${CSS.escape(header.dataset.column ?? '')}"]`);
      if (!cell) {
        continue;
      }
      const term = document.createElement('dt');
      term.textContent = this.label(header);
      const value = document.createElement('dd');
      const nodes = [...cell.childNodes];
      value.append(...nodes);
      this.moved.push({ cell, nodes });
      list.append(term, value);
    }
    this.dialogBodyTarget.replaceChildren(list);
    this.dialogTarget.showModal();
  }

  /** Puts the moved cell contents back and returns focus to where the dialog was opened from. */
  private restore(): void {
    for (const { cell, nodes } of this.moved) {
      cell.append(...nodes);
    }
    this.moved = [];
    this.dialogBodyTarget.replaceChildren();
    this.openedFrom?.focus?.();
    this.openedFrom = null;
  }

  /** Header text of a column (stacked parts joined). */
  private label(header: HTMLTableCellElement): string {
    const parts = [...header.querySelectorAll(this.partSelectorValue)].map((part) => part.textContent?.trim() ?? '');

    return (parts.length > 0 ? parts.join(' · ') : (header.textContent ?? '')).replace(/\s+/g, ' ').trim();
  }

  /** Row title: the text of its first two columns (e.g. "date · action"), at most 120 characters. */
  private rowTitle(row: HTMLTableRowElement): string {
    const title = [...row.querySelectorAll<HTMLElement>('td[data-column]')]
      .slice(0, 2)
      .map((cell) => (cell.textContent ?? '').replace(/\s+/g, ' ').trim())
      .filter((text) => '' !== text && '—' !== text)
      .join(' · ');

    return title.length > 120 ? `${title.slice(0, 119)}…` : title;
  }

  /**
   * Saves the mode; a stale CSRF token is refreshed once. Without a URL the mode goes to the listing cookie when one
   * is set, else it stays client-side.
   */
  private async save(mode: TableMode): Promise<boolean> {
    if ('' === this.urlValue) {
      return '' === this.cookieValue || patchListingCookie(this.cookieValue, { mode }, { path: this.cookiePathValue });
    }
    try {
      let response = await this.send(mode);
      if (403 === response.status && (await this.refreshToken())) {
        response = await this.send(mode);
      }

      return response.ok;
    } catch {
      return false;
    }
  }

  private send(mode: TableMode): Promise<Response> {
    return fetch(this.urlValue, {
      method: this.methodValue,
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': this.tokenValue,
      },
      body: JSON.stringify({ mode }),
    });
  }

  private async refreshToken(): Promise<boolean> {
    if ('' === this.tokenUrlValue) {
      return false;
    }
    try {
      const response = await fetch(this.tokenUrlValue, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const token = response.ok ? ((await response.json()) as { token?: unknown }).token : null;
      if ('string' !== typeof token || '' === token) {
        return false;
      }
      this.tokenValue = token;

      return true;
    } catch {
      return false;
    }
  }
}
