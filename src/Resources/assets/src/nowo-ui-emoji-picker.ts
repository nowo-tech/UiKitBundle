/**
 * Emoji picker (`js/nowo-ui-emoji-picker.js`, opt-in: add `emoji-picker` to `script_names`): a text input plus a toggle that opens
 * a search box and a grid. Without this script the field is a plain text input and the toggle stays hidden. The input stays
 * the form's value; the panel adds nothing to the submit. See the partial `emoji_picker` and `EmojiPickerType`.
 *
 * Accessibility: APG combobox with a grid popup. Focus stays in the search box and `aria-activedescendant` names the active cell, so
 * the panel is one tab stop. Arrows move in the grid (Left and Right as they look, in RTL too), Home and End in the row, Enter picks the
 * active cell (with none, the first result), Escape and Tab close, and focus goes back to the toggle. `aria-selected` marks the
 * field's current value. A polite status says how many results there are.
 *
 * Position: the panel is a `[popover]` in the top layer, so no ancestor's overflow clips it, the remote form dialog's body included.
 * It is measured on open and on scroll or resize, flipped above the field when there is no room below, and kept inside the viewport.
 *
 * Events: bubbling, cancelable `nowo-ui:emoji-picker:pick` {emoji, name} before the value is set; then native `input` and `change`
 * on the input. One grapheme: picking sets exactly one, and typed or pasted text is cut to its first on `change`.
 *
 * DOM APIs only, `textContent` only; delegated listeners, safe with morphing and fragments inserted later. No style attribute:
 * coordinates go through the CSSOM. Importing touches no DOM. Members are read through the prototypes (GT-L3, HH-L4).
 */
import { EMOJI } from './nowo-ui-emoji-table';
import { EDGE_PX, placeBeside } from './nowo-ui-popover-core';

export const ROOT = '[data-nowo-ui-emoji-picker]';
const TOGGLE = '[data-nowo-ui-emoji-picker-toggle]';
const PANEL = '[data-nowo-ui-emoji-picker-panel]';
const SEARCH = '[data-nowo-ui-emoji-picker-search]';
const GRID = '[data-nowo-ui-emoji-picker-grid]';
const STATUS = '[data-nowo-ui-emoji-picker-status]';
const CELL = '[data-nowo-ui-emoji-picker-cell]';
/** Cells per row. The CSS grid has the same number. */
export const COLUMNS = 8;

const DEFAULT_LABELS = { results: '%count% results', none: 'No results' };

const closest = (el: Element, selector: string): HTMLElement | null => Element.prototype.closest.call(el, selector) as HTMLElement | null;
const query = (el: Element, selector: string): HTMLElement | null => Element.prototype.querySelector.call(el, selector) as HTMLElement | null;
const queryAll = (el: Element, selector: string): HTMLElement[] => [...Element.prototype.querySelectorAll.call(el, selector)] as HTMLElement[];
const getAttr = (el: Element, name: string): string | null => Element.prototype.getAttribute.call(el, name);
const createElement = (tag: string): HTMLElement => Document.prototype.createElement.call(document, tag);
const listen = (target: EventTarget, type: string, handler: (event: never) => void, options?: boolean): void => {
  EventTarget.prototype.addEventListener.call(target, type, handler as EventListener, options);
};
const emit = (target: Element, name: string, detail: unknown, cancelable = false): boolean =>
  EventTarget.prototype.dispatchEvent.call(target, new CustomEvent(name, { bubbles: true, cancelable, detail }));
const inputValue = (input: HTMLInputElement): string => Reflect.get(HTMLInputElement.prototype, 'value', input) as string;
const setInputValue = (input: HTMLInputElement, value: string): void => {
  Reflect.set(HTMLInputElement.prototype, 'value', value, input);
};

/** `Intl.Segmenter`, which this project's TypeScript target does not declare. */
type GraphemeSegmenter = new (locale: undefined, options: { granularity: 'grapheme' }) => { segment(text: string): Iterable<{ segment: string }> };

/** The first user-perceived character of a text, with no whitespace around it. Without `Intl.Segmenter`, the text as it is. */
export function firstGrapheme(text: string): string {
  const value = text.trim();
  const Segmenter = typeof Intl === 'undefined' ? undefined : (Intl as unknown as { Segmenter?: GraphemeSegmenter }).Segmenter;
  if (value === '' || typeof Segmenter !== 'function') {
    return value;
  }
  const first = new Segmenter(undefined, { granularity: 'grapheme' }).segment(value)[Symbol.iterator]().next();
  return first.done ? value : first.value.segment;
}

type Row = readonly [string, string];

/** The table of a picker: its `data-nowo-ui-emoji-picker-table` when valid, else the built-in one. Strings only. */
function tableOf(root: Element): ReadonlyArray<Row> {
  const raw = getAttr(root, 'data-nowo-ui-emoji-picker-table');
  if (raw) {
    try {
      const parsed: unknown = JSON.parse(raw);
      if (Array.isArray(parsed)) {
        const rows = parsed.filter((row): row is [string, string] => Array.isArray(row) && typeof row[0] === 'string' && typeof row[1] === 'string' && row[1] !== '');
        if (rows.length > 0) {
          return rows;
        }
      }
    } catch {
      // A broken attribute leaves the built-in table.
    }
  }
  return EMOJI;
}

function labelsOf(root: Element): typeof DEFAULT_LABELS {
  const labels = { ...DEFAULT_LABELS };
  try {
    const parsed: unknown = JSON.parse(getAttr(root, 'data-nowo-ui-emoji-picker-labels') ?? '{}');
    if (parsed && typeof parsed === 'object') {
      for (const key of ['results', 'none'] as const) {
        const value = Object.prototype.hasOwnProperty.call(parsed, key) ? (parsed as Record<string, unknown>)[key] : undefined;
        if (typeof value === 'string') {
          labels[key] = value;
        }
      }
    }
  } catch {
    // The default wording.
  }
  return labels;
}

/** The rows of a table matching a search: by name, or by the character itself. */
export function matching(table: ReadonlyArray<Row>, search: string): ReadonlyArray<Row> {
  const term = search.trim().toLowerCase();
  return term === '' ? table : table.filter(([name, emoji]) => name.toLowerCase().includes(term) || emoji === term);
}

/** The field a picker fills: its first input that is not the search box. */
function fieldOf(root: Element): HTMLInputElement | null {
  return (queryAll(root, 'input').find((input) => !Element.prototype.matches.call(input, SEARCH)) as HTMLInputElement | undefined) ?? null;
}

const isUsable = (input: HTMLInputElement | null): input is HTMLInputElement => input !== null && !Reflect.get(HTMLInputElement.prototype, 'disabled', input) && !Reflect.get(HTMLInputElement.prototype, 'readOnly', input);

function renderGrid(root: HTMLElement): void {
  const search = query(root, SEARCH) as HTMLInputElement | null;
  const grid = query(root, GRID);
  if (!search || !grid) {
    return;
  }
  const rows = matching(tableOf(root), inputValue(search));
  const current = fieldOf(root);
  const selected = current ? inputValue(current) : '';
  const base = grid.id;
  const lines: HTMLElement[] = [];
  rows.forEach(([name, emoji], index) => {
    if (index % COLUMNS === 0) {
      const line = createElement('div');
      line.setAttribute('role', 'row');
      line.className = 'nowo-ui-emoji-picker__row';
      lines.push(line);
    }
    const cell = createElement('div');
    cell.id = `${base}-${index}`;
    cell.className = emoji === selected ? 'nowo-ui-emoji-picker__cell is-selected' : 'nowo-ui-emoji-picker__cell';
    cell.setAttribute('role', 'gridcell');
    cell.setAttribute('aria-label', name);
    cell.setAttribute('aria-selected', emoji === selected ? 'true' : 'false');
    cell.setAttribute('title', name);
    cell.setAttribute('data-nowo-ui-emoji-picker-cell', name);
    cell.textContent = emoji;
    lines[lines.length - 1]!.append(cell);
  });
  grid.replaceChildren(...lines);
  search.removeAttribute('aria-activedescendant');
  const status = query(root, STATUS);
  if (status) {
    const labels = labelsOf(root);
    status.textContent = rows.length === 0 ? labels.none : labels.results.replace('%count%', String(rows.length));
  }
}

function setActive(root: HTMLElement, cell: HTMLElement | null): void {
  queryAll(root, `${CELL}.is-active`).forEach((other) => {
    other.classList.remove('is-active');
  });
  const search = query(root, SEARCH);
  if (!cell) {
    search?.removeAttribute('aria-activedescendant');
    return;
  }
  cell.classList.add('is-active');
  search?.setAttribute('aria-activedescendant', cell.id);
  cell.scrollIntoView?.({ block: 'nearest' });
}

/** The picker whose panel is open and whose position follows the page. One at a time. */
let placed: HTMLElement | null = null;

function place(root: HTMLElement): void {
  const panel = query(root, PANEL);
  if (panel) {
    panel.style.setProperty('max-height', `${Math.max(0, window.innerHeight - 2 * EDGE_PX)}px`);
    placeBeside(root, panel, { keepInside: true });
  }
}

function onViewportChange(event: Event): void {
  // The grid's own scrolling moves nothing.
  if (placed && !(event.target instanceof Element && closest(event.target, PANEL))) {
    place(placed);
  }
}

const viewListeners = (add: boolean): void => {
  const change = add ? EventTarget.prototype.addEventListener : EventTarget.prototype.removeEventListener;
  const view = Reflect.get(Document.prototype, 'defaultView', document) as Window;
  change.call(view, 'scroll', onViewportChange as EventListener, true);
  change.call(view, 'resize', onViewportChange as EventListener);
};

export function closePicker(root: HTMLElement, refocus: boolean): void {
  const panel = query(root, PANEL);
  const toggle = query(root, TOGGLE);
  if (!panel || panel.hidden) {
    return;
  }
  if (typeof panel.hidePopover === 'function' && panel.hasAttribute('popover')) {
    try {
      panel.hidePopover();
    } catch {
      // Already hidden: a morph took it out of the top layer.
    }
  }
  panel.hidden = true;
  if (placed === root) {
    placed = null;
    viewListeners(false);
  }
  toggle?.setAttribute('aria-expanded', 'false');
  query(root, SEARCH)?.setAttribute('aria-expanded', 'false');
  if (refocus) {
    toggle?.focus();
  }
}

function closeAll(except: Element | null): void {
  (Document.prototype.querySelectorAll.call(document, `${PANEL}:not([hidden])`) as NodeListOf<Element>).forEach((panel) => {
    const root = closest(panel, ROOT);
    if (root && root !== except) {
      closePicker(root, false);
    }
  });
}

export function openPicker(root: HTMLElement): void {
  const panel = query(root, PANEL);
  const search = query(root, SEARCH) as HTMLInputElement | null;
  if (!panel || !search || !isUsable(fieldOf(root))) {
    return;
  }
  closeAll(root);
  setInputValue(search, '');
  panel.hidden = false;
  query(root, TOGGLE)?.setAttribute('aria-expanded', 'true');
  search.setAttribute('aria-expanded', 'true');
  renderGrid(root);
  // Top layer where there is one; without it, the panel is `position: fixed` under the page's other layers.
  if (typeof panel.showPopover === 'function' && panel.hasAttribute('popover')) {
    try {
      panel.showPopover();
    } catch {
      // Not connected, or already open after a morph.
    }
  }
  place(root);
  placed = root;
  viewListeners(true);
  search.focus();
}

function pick(root: HTMLElement, cell: HTMLElement): void {
  const input = fieldOf(root);
  const emoji = firstGrapheme(cell.textContent ?? '');
  if (!isUsable(input) || !emit(root, 'nowo-ui:emoji-picker:pick', { emoji, name: getAttr(cell, 'data-nowo-ui-emoji-picker-cell') ?? '' }, true)) {
    return;
  }
  setInputValue(input, emoji);
  input.dispatchEvent(new Event('input', { bubbles: true }));
  input.dispatchEvent(new Event('change', { bubbles: true }));
  closePicker(root, true);
}

/** The cell an arrow key leads to, or the same one at an edge. */
export function nextIndex(key: string, index: number, count: number, columns = COLUMNS): number {
  if (count === 0) {
    return -1;
  }
  if (index < 0) {
    return key === 'ArrowUp' || key === 'End' ? count - 1 : 0;
  }
  switch (key) {
    case 'ArrowRight':
      return Math.min(index + 1, count - 1);
    case 'ArrowLeft':
      return Math.max(index - 1, 0);
    case 'ArrowDown':
      return index + columns < count ? index + columns : index;
    case 'ArrowUp':
      return index - columns >= 0 ? index - columns : index;
    case 'Home':
      return index - (index % columns);
    case 'End':
      return Math.min(index - (index % columns) + columns - 1, count - 1);
    default:
      return index;
  }
}

function openRootFor(target: EventTarget | null): HTMLElement | null {
  const inside = target instanceof Element ? closest(target, ROOT) : null;
  if (inside) {
    const panel = query(inside, PANEL);
    return panel && !panel.hidden ? inside : null;
  }
  // Focus fell out of the panel, to the page itself: the open picker still owns Escape.
  const onPage = target === document || target === Reflect.get(Document.prototype, 'body', document) || target === Reflect.get(Document.prototype, 'documentElement', document);
  return onPage ? placed : null;
}

function onKeydown(event: KeyboardEvent): void {
  if (event.isComposing) {
    return; // Enter and the arrows belong to the input method.
  }
  const root = openRootFor(event.target);
  if (!root) {
    return;
  }
  if (event.key === 'Escape') {
    // Capture phase: this Escape closes the panel, not the dialog around it.
    event.preventDefault();
    event.stopPropagation();
    closePicker(root, true);
    return;
  }
  const search = event.target instanceof Element && Element.prototype.matches.call(event.target, SEARCH) ? event.target : null;
  if (!search) {
    return;
  }
  const cells = queryAll(root, CELL);
  const index = cells.findIndex((cell) => cell.classList.contains('is-active'));
  if (event.key === 'Tab') {
    // Explicit: the browser's own move starts from a box about to be hidden, and Firefox then lands on the page.
    event.preventDefault();
    closePicker(root, true);
  } else if (event.key === 'Enter') {
    event.preventDefault(); // never the surrounding form
    const cell = cells[index] ?? cells[0];
    if (cell) {
      pick(root, cell);
    }
  } else if (event.key === 'ArrowUp' || event.key === 'ArrowDown' || (index >= 0 && ['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key))) {
    // Left, Right, Home and End belong to the text in the box until the grid is entered.
    event.preventDefault();
    const rtl = window.getComputedStyle(root).direction === 'rtl';
    const key = rtl && event.key === 'ArrowLeft' ? 'ArrowRight' : rtl && event.key === 'ArrowRight' ? 'ArrowLeft' : event.key;
    setActive(root, cells[nextIndex(key, index, cells.length)] ?? null);
  }
}

function onInput(event: Event): void {
  const target = event.target;
  const root = target instanceof Element && Element.prototype.matches.call(target, SEARCH) ? closest(target, ROOT) : null;
  if (root) {
    renderGrid(root);
  }
}

function onChange(event: Event): void {
  const target = event.target;
  if (!(target instanceof HTMLInputElement) || Element.prototype.matches.call(target, SEARCH)) {
    return;
  }
  const root = closest(target, ROOT);
  if (root && fieldOf(root) === target) {
    const value = inputValue(target);
    const first = firstGrapheme(value);
    if (first !== value) {
      setInputValue(target, first);
    }
  }
}

function onPointerDown(event: Event): void {
  // A press anywhere in the panel but the box itself must not take focus from the box.
  if (event.target instanceof Element && closest(event.target, PANEL) && !Element.prototype.matches.call(event.target, SEARCH)) {
    event.preventDefault();
  }
}

function onClick(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) {
    return;
  }
  const toggle = closest(target, TOGGLE);
  const root = closest(target, ROOT);
  if (toggle && root) {
    event.preventDefault();
    const panel = query(root, PANEL);
    if (panel?.hidden) {
      openPicker(root);
    } else {
      closePicker(root, true);
    }
    return;
  }
  const cell = closest(target, CELL);
  if (cell && root) {
    pick(root, cell);
    return;
  }
  closeAll(root);
}

function revealToggle(toggle: HTMLElement): void {
  const root = closest(toggle, ROOT);
  const hide = !(root && isUsable(fieldOf(root)));
  // Only when it changes: the observer below watches this attribute.
  if (toggle.hidden !== hide) {
    toggle.hidden = hide;
  }
}

/** Shows the toggle of every usable picker: until then the field is a plain input. */
export function revealToggles(scope: ParentNode = document): void {
  const all = scope instanceof Element ? queryAll(scope, TOGGLE) : ([...Document.prototype.querySelectorAll.call(document, TOGGLE)] as HTMLElement[]);
  all.forEach(revealToggle);
}

let bound = false;

export function bindNowoUiEmojiPicker(): void {
  if (bound) {
    return;
  }
  bound = true;
  listen(document, 'keydown', onKeydown, true);
  listen(document, 'input', onInput);
  listen(document, 'change', onChange);
  listen(document, 'pointerdown', onPointerDown);
  listen(document, 'click', onClick);
  if (Reflect.get(Document.prototype, 'readyState', document) === 'loading') {
    listen(document, 'DOMContentLoaded', () => revealToggles());
  } else {
    revealToggles();
  }
  // Fields that arrive later (a remote form dialog, a Turbo frame) and a morph that puts the server's `hidden` back on a toggle.
  new MutationObserver((records) => {
    for (const record of records) {
      if (record.type === 'attributes') {
        if (record.target instanceof Element && Element.prototype.matches.call(record.target, TOGGLE)) {
          revealToggle(record.target as HTMLElement);
        }
        continue;
      }
      record.addedNodes.forEach((node) => {
        if (node instanceof Element) {
          revealToggles((Reflect.get(Node.prototype, 'parentElement', node) as Element | null) ?? node);
        }
      });
    }
  }).observe(document, { childList: true, subtree: true, attributes: true, attributeFilter: ['hidden'] });
}

// A second copy of the script must not bind again. A symbol: an element with some id cannot turn this off.
const BOUND_KEY = Symbol.for('nowo-ui.emoji-picker.bound');
if (typeof document !== 'undefined' && !(window as unknown as Record<symbol, unknown>)[BOUND_KEY]) {
  (window as unknown as Record<symbol, unknown>)[BOUND_KEY] = true;
  bindNowoUiEmojiPicker();
}
