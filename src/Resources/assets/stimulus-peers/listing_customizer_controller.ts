/**
 * Optional Stimulus peer — listing customizer (`partials/_listing_customizer.html.twig`): show / hide / reorder the
 * columns and filters of a listing, stack a column under the previous one, reset to the defaults.
 *
 * The state comes from the server (`Listing\ListingCustomizerView::clientState()`); the editor lists are rendered
 * here. Reordering works with drag and drop and with the up / down buttons (or Alt+ArrowUp / Alt+ArrowDown on a row):
 * focus stays on the moved row and the move is announced in the live status. Apply is enabled once something
 * changed.
 *
 * Persistence: with a `cookie` (default store) Apply writes the listing cookie (`listing_cookie.ts`: path `/`,
 * SameSite=Lax, one year; the table mode already stored there is kept) and Reset removes the layout from it; with a
 * `url` (host store) Apply sends `{"columns": […], "filters": […]}` (JSON, `X-CSRF-TOKEN`, `method` default PUT) and
 * Reset sends `resetMethod` (default DELETE) without a body; a 403 refreshes the token once from `tokenUrl`. Then the
 * page reloads so the server renders the new layout. Reset asks first (`askConfirm`).
 *
 * Scopes (2.33, UI-3): with a `scopes` value (`[{key, label, stored, columns, filters, saveUrl?, resetUrl?, params?,
 * saveConfirm?, resetConfirm?}]`) the editor holds one layout per scope (e.g. "My layout" / "Project default") and a
 * `scope` target (`<select>`, action `listing-customizer#changeScope`) switches between them; switching with unapplied
 * changes asks `messages.discardConfirm` first. Apply / Reset send to the scope's `saveUrl` / `resetUrl` (else `url`)
 * with `{"scope": key, ...params}` in the body (Reset too), after the scope's `saveConfirm` / `resetConfirm` when set.
 * The `scopeState` target shows `messages.stored` / `messages.inherited`. Without scopes nothing changes.
 *
 * Identifier: `listing-customizer`
 * Targets: `dialog` (the side panel `<dialog>`), `toggle` (opener, optional), `tabButton`, `panel`, `list`
 *          (`data-kind="columns|filters"`), `status`, `resetButton`, `applyButton`, `scope`, `scopeState` (2.33).
 * Values: `state` (`{listingKey, stored, mode, columns, filters}`), `cookie`, `cookiePath` (default `/`),
 *         `cookieMaxAge` (default one year), `url`, `token`, `tokenUrl`, `method` (default `PUT`), `resetMethod`
 *         (default `DELETE`), `messages` (`{required, moveUp, moveDown, stack, dragHandle, moved, unsaved, saving,
 *         saveError, tooLarge, sessionExpired, resetConfirm, confirmOk, cancel}`).
 * Actions: `listing-customizer#open`, `#openFilters`, `#close`, `#toggle` (2.33), `#selectTab`, `#tabKeydown`, `#apply`,
 *          `#reset`, `#changeScope` (2.33).
 * Root attribute: `data-listing-dirty` while the editor holds unapplied changes.
 * Events: `listing-customizer:saved` (`detail.layout`, `detail.scope` with scopes; before the reload; cancelable:
 *         preventDefault() skips the reload), `listing-customizer:save-failed` (`detail.message`),
 *         `listing-customizer:scope-changed` (`detail.scope`, 2.33).
 */

import { Controller } from '@hotwired/stimulus';

import { askConfirm } from './ask_confirm';
import { deleteListingCookie, LISTING_COOKIE_MAX_AGE, readListingCookie, writeListingCookie } from './listing_cookie';

export type ListingItem = {
  key: string;
  label: string;
  required: boolean;
  visible: boolean;
  merged?: boolean;
};

export type ListingPayloadItem = { key: string; visible: boolean; merged?: true };

export type ListingClientState = {
  listingKey: string;
  stored: boolean;
  mode: string | null;
  columns: ListingItem[];
  filters: ListingItem[];
};

/** One editable layout of a scoped customizer (2.33). */
export type ListingScope = {
  key: string;
  label?: string;
  stored?: boolean;
  columns?: ListingItem[];
  filters?: ListingItem[];
  saveUrl?: string;
  resetUrl?: string;
  params?: Record<string, unknown>;
  saveConfirm?: string;
  resetConfirm?: string;
};

type Kind = 'columns' | 'filters';

type IconName = 'grip' | 'up' | 'down' | 'stack';

/** Fills `%name%` placeholders of a translated message. */
export function formatMessage(template: string, params: Record<string, string | number>): string {
  return Object.entries(params).reduce((text, [name, value]) => text.split(`%${name}%`).join(String(value)), template);
}

/** Moves the item at `from` to `to` (a new array; the same array when a position is out of range). */
export function moveItem<T>(items: T[], from: number, to: number): T[] {
  if (from === to || from < 0 || to < 0 || from >= items.length || to >= items.length) {
    return items;
  }
  const next = items.slice();
  // `from` is in range: splice removes exactly one item.
  const [moved] = next.splice(from, 1) as [T];
  next.splice(to, 0, moved);

  return next;
}

/**
 * Storable form of a working list: required items are always visible; `merged` only when set, never on the first
 * item and never on filters.
 */
export function toPayload(items: ListingItem[], mergeable = true): ListingPayloadItem[] {
  return items.map((item, index) => ({
    key: item.key,
    visible: item.required || item.visible,
    ...(mergeable && item.merged && index > 0 ? { merged: true as const } : {}),
  }));
}

/**
 * The URL a scope saves to (2.33): its own URL when it is on this origin (the request carries the CSRF token), the
 * listing `url` when it has none, null for another origin.
 */
export function scopeUrl(own: string | undefined, fallback: string): string | null {
  if (typeof own !== 'string' || own === '') {
    return fallback;
  }
  try {
    const url = new URL(own, window.location.href);
    return url.origin === window.location.origin ? url.href : null;
  } catch {
    return null;
  }
}

export default class ListingCustomizerController extends Controller<HTMLElement> {
  static override targets = ['dialog', 'toggle', 'tabButton', 'panel', 'list', 'status', 'resetButton', 'applyButton', 'scope', 'scopeState'];

  static override values = {
    state: { type: Object, default: {} },
    cookie: { type: String, default: '' },
    cookiePath: { type: String, default: '/' },
    cookieMaxAge: { type: Number, default: LISTING_COOKIE_MAX_AGE },
    url: { type: String, default: '' },
    token: { type: String, default: '' },
    tokenUrl: { type: String, default: '' },
    method: { type: String, default: 'PUT' },
    resetMethod: { type: String, default: 'DELETE' },
    messages: { type: Object, default: {} },
    scopes: { type: Array, default: [] },
    scope: { type: String, default: '' },
  };

  declare readonly hasDialogTarget: boolean;
  declare readonly dialogTarget: HTMLDialogElement;
  declare readonly hasToggleTarget: boolean;
  declare readonly toggleTarget: HTMLElement;
  declare readonly tabButtonTargets: HTMLButtonElement[];
  declare readonly panelTargets: HTMLElement[];
  declare readonly listTargets: HTMLUListElement[];
  declare readonly hasStatusTarget: boolean;
  declare readonly statusTarget: HTMLElement;
  declare readonly hasResetButtonTarget: boolean;
  declare readonly resetButtonTarget: HTMLButtonElement;
  declare readonly hasApplyButtonTarget: boolean;
  declare readonly applyButtonTarget: HTMLButtonElement;
  declare readonly hasScopeTarget: boolean;
  declare readonly scopeTarget: HTMLSelectElement;
  declare readonly hasScopeStateTarget: boolean;
  declare readonly scopeStateTarget: HTMLElement;

  declare readonly stateValue: Partial<ListingClientState>;
  declare readonly cookieValue: string;
  declare readonly cookiePathValue: string;
  declare readonly cookieMaxAgeValue: number;
  declare readonly urlValue: string;
  declare tokenValue: string;
  declare readonly tokenUrlValue: string;
  declare readonly methodValue: string;
  declare readonly resetMethodValue: string;
  declare readonly messagesValue: Record<string, string>;
  declare readonly scopesValue: ListingScope[];
  declare readonly scopeValue: string;

  private working: Record<Kind, ListingItem[]> = { columns: [], filters: [] };
  private pristine = '';
  private dragging: { kind: Kind; index: number } | null = null;
  private busy = false;
  /** The scope loaded in the editor (2.33), '' without scopes. */
  private loadedScope = '';

  override connect(): void {
    const initial = this.scopes().length > 0 ? this.initialScope() : null;
    if (initial !== null) {
      this.loadScope(initial);
      return;
    }
    this.load(this.stateValue.columns ?? [], this.stateValue.filters ?? []);
  }

  /** Edge tab or button: opens the side panel, or closes it when it is open (2.33). */
  toggle(event?: Event): void {
    if (this.hasDialogTarget && this.dialogTarget.open) {
      this.close();
    } else {
      this.open(event);
    }
  }

  /** The `<select>` of scopes changed (2.33): unapplied edits are discarded only after `messages.discardConfirm`. */
  async changeScope(): Promise<void> {
    const next = this.findScope(this.hasScopeTarget ? this.scopeTarget.value : '');
    if (next === null || next.key === this.loadedScope) {
      return;
    }
    if (this.isDirty() && !(await this.ask(this.messagesValue.discardConfirm ?? ''))) {
      if (this.hasScopeTarget) {
        this.scopeTarget.value = this.loadedScope;
      }
      return;
    }
    this.loadScope(next);
    this.dispatch('scope-changed', { detail: { scope: next.key } });
  }

  /** The scopes of a scoped customizer (entries without a key are ignored). */
  private scopes(): ListingScope[] {
    return Array.isArray(this.scopesValue) ? this.scopesValue.filter((scope) => typeof scope?.key === 'string' && scope.key !== '') : [];
  }

  private findScope(key: string): ListingScope | null {
    return this.scopes().find((scope) => scope.key === key) ?? null;
  }

  private initialScope(): ListingScope | null {
    return this.findScope(this.scopeValue) ?? this.findScope(this.hasScopeTarget ? this.scopeTarget.value : '') ?? this.scopes()[0] ?? null;
  }

  private currentScope(): ListingScope | null {
    return this.loadedScope === '' ? null : this.findScope(this.loadedScope);
  }

  private loadScope(scope: ListingScope): void {
    this.loadedScope = scope.key;
    if (this.hasScopeTarget) {
      this.scopeTarget.value = scope.key;
    }
    if (this.hasScopeStateTarget) {
      this.scopeStateTarget.textContent = (scope.stored ? this.messagesValue.stored : this.messagesValue.inherited) ?? '';
    }
    this.load(scope.columns ?? [], scope.filters ?? []);
    this.setStatus('', false);
  }

  private load(columns: ListingItem[], filters: ListingItem[]): void {
    this.working = {
      columns: columns.map((item) => ({ ...item })),
      filters: filters.map((item) => ({ ...item })),
    };
    this.pristine = this.serialize();
    this.renderLists();
    this.refresh();
  }

  /** Whether a layout is stored for what the editor shows (the loaded scope, else the listing state). */
  private stored(): boolean {
    const scope = this.currentScope();
    return scope === null ? Boolean(this.stateValue.stored) : Boolean(scope.stored);
  }

  private ask(message: string): Promise<boolean> {
    return askConfirm(message, { confirm: this.messagesValue.confirmOk, cancel: this.messagesValue.cancel });
  }

  /** Opens the side panel (through `nowo-ui-dialog.js` when loaded, so focus returns to the opener). */
  open(event?: Event): void {
    if (!this.hasDialogTarget || this.dialogTarget.open) {
      return;
    }
    const opener = event?.currentTarget instanceof Element ? event.currentTarget : this.hasToggleTarget ? this.toggleTarget : null;
    const kit = (window as Window & { nowoUiOpenDialog?: (dialog: HTMLDialogElement, opener?: Element | null) => void }).nowoUiOpenDialog;
    if (typeof kit === 'function') {
      kit(this.dialogTarget, opener);
    } else if (typeof this.dialogTarget.showModal === 'function') {
      this.dialogTarget.showModal();
    }
  }

  /** Opens the side panel on the Filters tab (hidden filters notice). */
  openFilters(event?: Event): void {
    this.open(event);
    this.activateTab('filters', true);
  }

  close(): void {
    if (this.hasDialogTarget && this.dialogTarget.open) {
      this.dialogTarget.close();
    }
  }

  selectTab(event: Event): void {
    const button = event.currentTarget;
    if (button instanceof HTMLElement) {
      this.activateTab(button.dataset.panel ?? 'columns', false);
    }
  }

  /** Arrow keys move between the tabs (roving tabindex). */
  tabKeydown(event: KeyboardEvent): void {
    if ('ArrowLeft' !== event.key && 'ArrowRight' !== event.key) {
      return;
    }
    event.preventDefault();
    const tabs = this.tabButtonTargets;
    const index = tabs.indexOf(event.currentTarget as HTMLButtonElement);
    // The key came from one of the tab buttons, so the list is not empty.
    const next = tabs[(index + ('ArrowRight' === event.key ? 1 : -1) + tabs.length) % tabs.length]!;
    this.activateTab(next.dataset.panel ?? 'columns', true);
  }

  async apply(): Promise<void> {
    if (this.busy) {
      return;
    }
    const layout = { columns: toPayload(this.working.columns), filters: toPayload(this.working.filters, false) };
    const scope = this.currentScope();
    if (scope !== null) {
      if (scope.saveConfirm && !(await this.ask(scope.saveConfirm))) {
        return;
      }
      const url = scopeUrl(scope.saveUrl, this.urlValue);
      if (null === url) {
        this.failed(this.messagesValue.saveError ?? '');

        return;
      }
      if ('' !== url) {
        await this.send(this.methodValue, { ...(scope.params ?? {}), scope: scope.key, ...layout }, layout, url);

        return;
      }
    }
    if ('' !== this.urlValue) {
      await this.send(this.methodValue, layout, layout);

      return;
    }
    if ('' !== this.cookieValue) {
      const existing = readListingCookie(this.cookieValue) ?? {};
      if (!this.writeCookie({ ...existing, ...layout })) {
        this.failed(this.messagesValue.tooLarge ?? '');

        return;
      }
    }
    this.done(layout);
  }

  async reset(): Promise<void> {
    if (this.busy || !(await this.confirmReset())) {
      return;
    }
    const scope = this.currentScope();
    const resetUrl = scope === null ? '' : scopeUrl(scope.resetUrl || scope.saveUrl, this.urlValue);
    if (null === resetUrl) {
      this.failed(this.messagesValue.saveError ?? '');

      return;
    }
    if (scope !== null && '' !== resetUrl) {
      await this.send(this.resetMethodValue, { ...(scope.params ?? {}), scope: scope.key }, null, resetUrl);

      return;
    }
    if ('' !== this.urlValue) {
      await this.send(this.resetMethodValue, null, null);

      return;
    }
    if ('' !== this.cookieValue) {
      const mode = readListingCookie(this.cookieValue)?.mode;
      if ('string' === typeof mode) {
        this.writeCookie({ mode });
      } else {
        deleteListingCookie(this.cookieValue, this.cookiePathValue);
      }
    }
    this.done(null);
  }

  /** Reloads the page so the server renders the new layout (protected: hosts may navigate elsewhere). */
  protected reload(): void {
    window.location.reload();
  }

  private writeCookie(data: Record<string, unknown>): boolean {
    return writeListingCookie(this.cookieValue, data, { path: this.cookiePathValue, maxAge: this.cookieMaxAgeValue });
  }

  private done(layout: { columns: ListingPayloadItem[]; filters: ListingPayloadItem[] } | null): void {
    const detail = this.loadedScope === '' ? { layout } : { layout, scope: this.loadedScope };
    const event = this.dispatch('saved', { detail, cancelable: true });
    if (!event.defaultPrevented) {
      this.reload();
    }
  }

  private failed(message: string): void {
    this.setBusy(false);
    this.setStatus(message, true);
    this.dispatch('save-failed', { detail: { message } });
  }

  private async send(
    method: string,
    body: unknown,
    layout: { columns: ListingPayloadItem[]; filters: ListingPayloadItem[] } | null,
    url: string = this.urlValue,
  ): Promise<void> {
    this.setBusy(true);
    this.setStatus(this.messagesValue.saving ?? '', false);
    try {
      let response = await this.request(method, body, url);
      if (403 === response.status && (await this.refreshToken())) {
        response = await this.request(method, body, url);
      }
      if (!response.ok) {
        this.failed((403 === response.status ? this.messagesValue.sessionExpired : this.messagesValue.saveError) ?? '');

        return;
      }
    } catch {
      this.failed(this.messagesValue.saveError ?? '');

      return;
    }
    this.setBusy(false);
    this.done(layout);
  }

  private request(method: string, body: unknown, url: string): Promise<Response> {
    const headers: Record<string, string> = { Accept: 'application/json', 'X-CSRF-TOKEN': this.tokenValue };
    if (null !== body) {
      headers['Content-Type'] = 'application/json';
    }

    return fetch(url, {
      method,
      credentials: 'same-origin',
      headers,
      ...(null !== body ? { body: JSON.stringify(body) } : {}),
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

  private confirmReset(): Promise<boolean> {
    return this.ask(this.currentScope()?.resetConfirm || this.messagesValue.resetConfirm || '');
  }

  /** One request at a time; Apply only with changes, Reset only with a stored layout. */
  private setBusy(busy: boolean): void {
    this.busy = busy;
    this.refresh();
  }

  private serialize(): string {
    return JSON.stringify({ columns: toPayload(this.working.columns), filters: toPayload(this.working.filters, false) });
  }

  private isDirty(): boolean {
    return this.serialize() !== this.pristine;
  }

  /** Syncs the buttons and the dirty marker with the working lists. */
  private refresh(): void {
    const dirty = this.isDirty();
    this.element.toggleAttribute('data-listing-dirty', dirty);
    if (this.hasApplyButtonTarget) {
      this.applyButtonTarget.disabled = this.busy || !dirty;
    }
    if (this.hasResetButtonTarget) {
      this.resetButtonTarget.disabled = this.busy || !this.stored();
    }
  }

  private changed(): void {
    this.refresh();
    this.setStatus(this.isDirty() ? (this.messagesValue.unsaved ?? '') : '', false);
  }

  private activateTab(panel: string, focus: boolean): void {
    this.tabButtonTargets.forEach((button) => {
      const active = button.dataset.panel === panel;
      button.setAttribute('aria-selected', active ? 'true' : 'false');
      button.tabIndex = active ? 0 : -1;
      if (active && focus) {
        button.focus();
      }
    });
    if (this.tabButtonTargets.length > 0) {
      this.panelTargets.forEach((element) => {
        element.hidden = element.dataset.panel !== panel;
      });
    }
  }

  private list(kind: Kind): HTMLUListElement | undefined {
    return this.listTargets.find((element) => element.dataset.kind === kind);
  }

  private renderLists(): void {
    this.listTargets.forEach((list) => {
      const kind: Kind = 'filters' === list.dataset.kind ? 'filters' : 'columns';
      list.replaceChildren(...this.working[kind].map((item, index) => this.renderItem(kind, item, index)));
    });
  }

  private renderItem(kind: Kind, item: ListingItem, index: number): HTMLLIElement {
    const count = this.working[kind].length;
    const li = document.createElement('li');
    li.className = 'nowo-ui-listing-customizer__item';
    li.classList.toggle('is-hidden', !(item.required || item.visible));
    li.draggable = true;
    li.dataset.key = item.key;
    li.addEventListener('dragstart', (event) => this.dragStart(event, li, kind, index));
    li.addEventListener('dragend', () => this.dragEnd(li));
    li.addEventListener('dragover', (event) => this.dragOver(event, li, kind));
    li.addEventListener('dragleave', () => li.classList.remove('is-drop-target'));
    li.addEventListener('drop', (event) => this.drop(event, kind, index));
    li.addEventListener('keydown', (event) => this.itemKeydown(event, kind, index));

    const handle = document.createElement('span');
    handle.className = 'nowo-ui-listing-customizer__handle';
    handle.title = this.messagesValue.dragHandle ?? '';
    handle.setAttribute('aria-hidden', 'true');
    handle.append(this.icon('grip'));

    const label = document.createElement('label');
    label.className = 'nowo-ui-listing-customizer__label';
    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.className = 'nowo-ui-listing-customizer__check';
    checkbox.checked = item.required || item.visible;
    checkbox.disabled = item.required;
    checkbox.addEventListener('change', () => {
      item.visible = checkbox.checked;
      li.classList.toggle('is-hidden', !item.visible);
      this.changed();
    });
    const text = document.createElement('span');
    text.textContent = item.label;
    label.append(checkbox, text);
    if (item.required) {
      const required = document.createElement('span');
      required.className = 'nowo-ui-listing-customizer__required';
      required.textContent = this.messagesValue.required ?? '';
      label.append(required);
    }

    const controls: HTMLButtonElement[] = [];
    if ('columns' === kind) {
      const stacked = Boolean(item.merged) && index > 0;
      const stack = this.button('stack', `${this.messagesValue.stack ?? ''}: ${item.label}`, 0 === index, () => {
        item.merged = !stacked;
        this.renderLists();
        this.list(kind)?.querySelector<HTMLButtonElement>(`li[data-key="${CSS.escape(item.key)}"] button[data-stack]`)?.focus();
        this.changed();
      });
      stack.classList.add('nowo-ui-listing-customizer__stack');
      stack.dataset.stack = '';
      stack.setAttribute('aria-pressed', stacked ? 'true' : 'false');
      li.classList.toggle('is-stacked', stacked);
      controls.push(stack);
    }
    const up = this.button('up', `${this.messagesValue.moveUp ?? ''}: ${item.label}`, 0 === index, () => this.move(kind, index, index - 1, 'up'));
    const down = this.button('down', `${this.messagesValue.moveDown ?? ''}: ${item.label}`, index === count - 1, () =>
      this.move(kind, index, index + 1, 'down'),
    );
    up.dataset.move = 'up';
    down.dataset.move = 'down';

    li.append(handle, label, ...controls, up, down);

    return li;
  }

  /** A copy of the server-rendered icon (empty when the page has no such template). */
  private icon(name: IconName): Node {
    const template = this.element.querySelector<HTMLTemplateElement>(`template[data-listing-customizer-icon="${name}"]`);

    return template ? template.content.cloneNode(true) : document.createDocumentFragment();
  }

  private button(icon: IconName, label: string, disabled: boolean, onClick: () => void): HTMLButtonElement {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'nowo-ui-listing-customizer__move';
    button.append(this.icon(icon));
    button.setAttribute('aria-label', label);
    button.title = label;
    button.disabled = disabled;
    button.addEventListener('click', onClick);

    return button;
  }

  /** Alt+ArrowUp / Alt+ArrowDown on a row move it. */
  private itemKeydown(event: KeyboardEvent, kind: Kind, index: number): void {
    if (!event.altKey || ('ArrowUp' !== event.key && 'ArrowDown' !== event.key)) {
      return;
    }
    event.preventDefault();
    const up = 'ArrowUp' === event.key;
    this.move(kind, index, index + (up ? -1 : 1), up ? 'up' : 'down');
  }

  private move(kind: Kind, from: number, to: number, direction: 'up' | 'down'): void {
    const items = this.working[kind];
    const key = items[from]?.key ?? '';
    const next = moveItem(items, from, to);
    if (next === items) {
      return;
    }
    this.working[kind] = next;
    this.renderLists();
    // Keep keyboard focus on the moved row's button (or its other one at the edge).
    const row = this.list(kind)?.querySelector<HTMLLIElement>(`li[data-key="${CSS.escape(key)}"]`);
    const button = row?.querySelector<HTMLButtonElement>(`button[data-move="${direction}"]`);
    const fallback = row?.querySelector<HTMLButtonElement>('button[data-move]:not(:disabled)');
    (button && !button.disabled ? button : fallback)?.focus();
    this.refresh();
    this.setStatus(
      formatMessage(this.messagesValue.moved ?? '', { label: next[to]!.label, position: to + 1, count: next.length }),
      false,
    );
  }

  private dragStart(event: DragEvent, li: HTMLLIElement, kind: Kind, index: number): void {
    this.dragging = { kind, index };
    li.classList.add('is-dragging');
    if (event.dataTransfer) {
      event.dataTransfer.setData('text/plain', li.dataset.key ?? '');
      event.dataTransfer.effectAllowed = 'move';
    }
  }

  private dragEnd(li: HTMLLIElement): void {
    this.dragging = null;
    li.classList.remove('is-dragging');
  }

  private dragOver(event: DragEvent, li: HTMLLIElement, kind: Kind): void {
    if (this.dragging?.kind !== kind) {
      return;
    }
    event.preventDefault();
    li.classList.add('is-drop-target');
  }

  private drop(event: DragEvent, kind: Kind, index: number): void {
    event.preventDefault();
    const dragging = this.dragging;
    this.dragging = null;
    if (dragging?.kind !== kind) {
      return;
    }
    this.working[kind] = moveItem(this.working[kind], dragging.index, index);
    this.renderLists();
    this.changed();
  }

  private setStatus(message: string, isError: boolean): void {
    if (!this.hasStatusTarget) {
      return;
    }
    this.statusTarget.textContent = message;
    this.statusTarget.classList.toggle('is-error', isError);
  }
}
