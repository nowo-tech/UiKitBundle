/**
 * Combobox (2.53, `js/nowo-ui-combobox.js`, opt-in: add `combobox` to `script_names`): a text input that searches a host URL as the
 * user types and lists the answers in a grouped listbox ("This wiki", "Other wikis"). Without this script the field is a plain text
 * input that submits what was typed. See the partial `combobox` and `ComboboxType`.
 *
 * Request: `GET <data-nowo-ui-combobox-url>?<param>=<term>` (param `q` by default), only on this origin and only http(s), with
 * `mode: 'same-origin'` (a redirect to another origin fails), credentials same-origin, `Accept: application/json`. It starts
 * `debounce` ms after the last keystroke (250 by default) once the trimmed term has `min-length` characters (2 by default); a newer
 * term aborts the request in flight, and an answer for an older term is dropped.
 *
 * Answer (`application/json`, or any `+json`): `{"groups": [{"label": "This wiki", "options": [{"value": "42", "label": "Home",
 * "description": "/home"}]}]}`, or `{"options": [...]}` / a bare list for one unnamed group. Strings only (a finite number becomes a
 * string); `value` defaults to `label`, an option without a label is dropped; at most 20 groups, 100 options and 300 characters per
 * text. Everything is inserted with `textContent`, never as HTML.
 *
 * Picking: a click or Enter on the active option fires the bubbling, cancelable `nowo-ui:combobox:pick` {value, label, description,
 * group} on the root. Then the field gets `value`; with `data-nowo-ui-combobox-value-input="<id>"` (a hidden input of the same form)
 * the field gets `label` and that input gets `value`, and typing again empties it. Native `input` and `change` follow on the inputs
 * that changed. `nowo-ui:combobox:results` {term, count} and `nowo-ui:combobox:error` {term} report each answer.
 *
 * Accessibility: WAI-ARIA 1.2 combobox, list autocomplete without automatic selection. The script gives the field `role="combobox"`,
 * `aria-autocomplete="list"`, `aria-expanded` and `aria-controls`; focus stays in the field and `aria-activedescendant` names the
 * active option. Down and Up move through the options (and open the list again), Alt+Down opens it without moving, Enter picks,
 * Escape closes the list only (not a dialog around it), Tab closes it. Groups are `role="group"` labelled by their heading. A polite
 * status gives the number of results, "no results", the minimum length, or the failure.
 *
 * Position: the listbox is a `[popover]` in the top layer, as wide as the field at least, under it or above it when there is no room
 * below, aligned with the field's start edge (the right one in RTL) and kept inside the viewport. No style attribute: coordinates go
 * through the CSSOM. DOM APIs only; delegated listeners, safe with morphing and fields inserted later. Importing touches no DOM.
 */
import { closeWithEffect, finishClosing, isClosing } from './nowo-ui-motion-core';
import { placeBeside } from './nowo-ui-popover-core';
import { announceScript } from './script-banner';

declare const __UI_KIT_BUILD_TIME__: string;

announceScript('ui-kit/combobox', typeof __UI_KIT_BUILD_TIME__ !== 'undefined' ? __UI_KIT_BUILD_TIME__ : undefined);

export const ROOT = '[data-nowo-ui-combobox]';
const LISTBOX = '[data-nowo-ui-combobox-listbox]';
const STATUS = '[data-nowo-ui-combobox-status]';
const OPTION = '[data-nowo-ui-combobox-option]';

export const DEFAULT_MIN_LENGTH = 2;
export const DEFAULT_DEBOUNCE_MS = 250;
export const MAX_DEBOUNCE_MS = 2000;
export const MAX_GROUPS = 20;
export const MAX_OPTIONS = 100;
export const MAX_TEXT = 300;
const PARAM = /^[A-Za-z0-9_.-]{1,64}$/;

const DEFAULT_LABELS = { results: '%count% results', none: 'No results', min_length: 'Type at least %count% characters', error: 'The search failed. Try again.' };
type Labels = typeof DEFAULT_LABELS;

export type ComboboxOption = { value: string; label: string; description: string };
export type ComboboxGroup = { label: string; options: ComboboxOption[] };

type State = {
  timer: ReturnType<typeof setTimeout> | undefined;
  controller: AbortController | null;
  seq: number;
  term: string;
  options: Array<ComboboxOption & { group: string }>;
  quiet: boolean;
};

const closest = (el: Element, selector: string): HTMLElement | null => Element.prototype.closest.call(el, selector) as HTMLElement | null;
const query = (el: Element, selector: string): HTMLElement | null => Element.prototype.querySelector.call(el, selector) as HTMLElement | null;
const queryAll = (el: Element, selector: string): HTMLElement[] => [...Element.prototype.querySelectorAll.call(el, selector)] as HTMLElement[];
const matches = (el: Element, selector: string): boolean => Element.prototype.matches.call(el, selector);
const getAttr = (el: Element, name: string): string | null => Element.prototype.getAttribute.call(el, name);
const setAttr = (el: Element, name: string, value: string): void => {
  if (Element.prototype.getAttribute.call(el, name) !== value) {
    Element.prototype.setAttribute.call(el, name, value);
  }
};
const removeAttr = (el: Element, name: string): void => Element.prototype.removeAttribute.call(el, name);
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
const own = (object: object, key: string): unknown => (Object.prototype.hasOwnProperty.call(object, key) ? (object as Record<string, unknown>)[key] : undefined);
const isRecord = (value: unknown): value is object => typeof value === 'object' && value !== null && !Array.isArray(value);
const length = (text: string): number => [...text].length;

/** A string of the answer, cut to {@see MAX_TEXT} characters; a finite number as a string; anything else null. */
function textOf(value: unknown): string | null {
  const text = typeof value === 'string' ? value : typeof value === 'number' && Number.isFinite(value) ? String(value) : null;
  return text !== null && text.length > MAX_TEXT ? [...text].slice(0, MAX_TEXT).join('') : text;
}

function optionOf(raw: unknown): ComboboxOption | null {
  if (!isRecord(raw)) {
    return null;
  }
  const label = textOf(own(raw, 'label'));
  if (label === null || label.trim() === '') {
    return null;
  }
  return { value: textOf(own(raw, 'value')) ?? label, label, description: textOf(own(raw, 'description')) ?? '' };
}

/** The groups of an answer, checked: own properties only, strings only, within the limits. Anything else gives no groups. */
export function normalise(payload: unknown): ComboboxGroup[] {
  let raw: unknown[] = [];
  if (Array.isArray(payload)) {
    raw = [{ options: payload }];
  } else if (isRecord(payload)) {
    const groups = own(payload, 'groups');
    const options = own(payload, 'options');
    raw = Array.isArray(groups) ? groups : Array.isArray(options) ? [{ options }] : [];
  }
  const result: ComboboxGroup[] = [];
  let total = 0;
  for (const group of raw.slice(0, MAX_GROUPS)) {
    const list = isRecord(group) ? own(group, 'options') : undefined;
    if (!Array.isArray(list)) {
      continue;
    }
    const options: ComboboxOption[] = [];
    for (const item of list) {
      if (total >= MAX_OPTIONS) {
        break;
      }
      const option = optionOf(item);
      if (option) {
        options.push(option);
        total++;
      }
    }
    if (options.length > 0) {
      result.push({ label: textOf(own(group as object, 'label')) ?? '', options });
    }
  }
  return result;
}

/** The request URL for a term: on this origin and http(s) only, else null. The term replaces any `param` already in the URL. */
export function searchUrl(raw: string | null, term: string, param = 'q'): URL | null {
  if (!raw) {
    return null;
  }
  let url: URL;
  try {
    url = new URL(raw, Reflect.get(Document.prototype, 'baseURI', document) as string);
  } catch {
    return null;
  }
  if ((url.protocol !== 'http:' && url.protocol !== 'https:') || url.origin !== window.location.origin) {
    return null;
  }
  url.searchParams.set(PARAM.test(param) ? param : 'q', term);
  return url;
}

const intAttr = (root: Element, name: string, fallback: number, min: number, max: number): number => {
  const raw = getAttr(root, name);
  const value = raw !== null && /^\d{1,6}$/.test(raw.trim()) ? Number(raw.trim()) : NaN;
  return Number.isFinite(value) ? Math.min(max, Math.max(min, value)) : fallback;
};

export const minLengthOf = (root: Element): number => intAttr(root, 'data-nowo-ui-combobox-min-length', DEFAULT_MIN_LENGTH, 1, 50);
export const debounceOf = (root: Element): number => intAttr(root, 'data-nowo-ui-combobox-debounce', DEFAULT_DEBOUNCE_MS, 0, MAX_DEBOUNCE_MS);

function labelsOf(root: Element): Labels {
  const labels = { ...DEFAULT_LABELS };
  try {
    const parsed: unknown = JSON.parse(getAttr(root, 'data-nowo-ui-combobox-labels') ?? '{}');
    if (isRecord(parsed)) {
      for (const key of Object.keys(DEFAULT_LABELS) as Array<keyof Labels>) {
        const value = own(parsed, key);
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

/** The field of a combobox: its first input that is not hidden. */
export function fieldOf(root: Element): HTMLInputElement | null {
  return (queryAll(root, 'input').find((input) => getAttr(input, 'type') !== 'hidden') as HTMLInputElement | undefined) ?? null;
}

/** The hidden input that takes the picked value: named by id, `type="hidden"`, and in the same form as the field (audit RU-I1: both in a form). */
function valueInputOf(root: Element, field: HTMLInputElement): HTMLInputElement | null {
  const id = getAttr(root, 'data-nowo-ui-combobox-value-input');
  const input = id ? Document.prototype.getElementById.call(document, id) : null;
  if (!(input instanceof HTMLInputElement) || getAttr(input, 'type') !== 'hidden') {
    return null;
  }
  const form = Reflect.get(HTMLInputElement.prototype, 'form', field) as HTMLFormElement | null;
  return form !== null && Reflect.get(HTMLInputElement.prototype, 'form', input) === form ? input : null;
}

const isUsable = (input: HTMLInputElement | null): input is HTMLInputElement =>
  input !== null && !Reflect.get(HTMLInputElement.prototype, 'disabled', input) && !Reflect.get(HTMLInputElement.prototype, 'readOnly', input);

const states = new WeakMap<Element, State>();

function stateOf(root: Element): State {
  let state = states.get(root);
  if (!state) {
    state = { timer: undefined, controller: null, seq: 0, term: '', options: [], quiet: false };
    states.set(root, state);
  }
  return state;
}

let generated = 0;

/** Gives a field its combobox roles (the server leaves them out, so the no-JS input is a plain text box). Idempotent. */
export function enhance(root: HTMLElement): void {
  const field = fieldOf(root);
  const listbox = query(root, LISTBOX);
  if (!field || !listbox) {
    return;
  }
  if (!listbox.id) {
    listbox.id = `nowo-ui-combobox-${++generated}`;
  }
  setAttr(field, 'role', 'combobox');
  setAttr(field, 'aria-autocomplete', 'list');
  setAttr(field, 'aria-controls', listbox.id);
  setAttr(field, 'autocomplete', 'off');
  if (getAttr(field, 'aria-expanded') === null) {
    setAttr(field, 'aria-expanded', 'false');
  }
  setAttr(root, 'data-nowo-ui-combobox-ready', '');
}

/** Enhances every combobox in a scope. */
export function enhanceAll(scope: ParentNode = document): void {
  const roots = scope instanceof Element ? [...(matches(scope, ROOT) ? [scope as HTMLElement] : []), ...queryAll(scope, ROOT)] : ([...Document.prototype.querySelectorAll.call(document, ROOT)] as HTMLElement[]);
  roots.forEach(enhance);
}

function setStatus(root: Element, text: string): void {
  const status = query(root, STATUS);
  if (status && status.textContent !== text) {
    status.textContent = text;
  }
}

const isOpen = (listbox: HTMLElement): boolean => !listbox.hidden && !isClosing(listbox);

/** The combobox whose listbox is open and whose position follows the page. One at a time. */
let placed: HTMLElement | null = null;

function place(root: HTMLElement): void {
  const listbox = query(root, LISTBOX);
  const field = fieldOf(root);
  if (!listbox || !field) {
    return;
  }
  listbox.style.setProperty('min-width', `${Math.round(field.getBoundingClientRect().width)}px`);
  placeBeside(field, listbox, { alignLeft: window.getComputedStyle(root).direction !== 'rtl', keepInside: true });
}

function onViewportChange(event: Event): void {
  // The listbox's own scrolling moves nothing.
  if (placed && !(event.target instanceof Element && closest(event.target, LISTBOX))) {
    place(placed);
  }
}

const viewListeners = (add: boolean): void => {
  const change = add ? EventTarget.prototype.addEventListener : EventTarget.prototype.removeEventListener;
  const view = Reflect.get(Document.prototype, 'defaultView', document) as Window;
  change.call(view, 'scroll', onViewportChange as EventListener, true);
  change.call(view, 'resize', onViewportChange as EventListener);
};

function setActive(root: HTMLElement, option: HTMLElement | null): void {
  queryAll(root, `${OPTION}.is-active`).forEach((other) => {
    other.classList.remove('is-active');
    setAttr(other, 'aria-selected', 'false');
  });
  const field = fieldOf(root);
  if (!option) {
    if (field) {
      removeAttr(field, 'aria-activedescendant');
    }
    return;
  }
  option.classList.add('is-active');
  setAttr(option, 'aria-selected', 'true');
  if (field) {
    setAttr(field, 'aria-activedescendant', option.id);
  }
  option.scrollIntoView?.({ block: 'nearest' });
}

export function closeList(root: HTMLElement): void {
  const listbox = query(root, LISTBOX);
  const field = fieldOf(root);
  setActive(root, null);
  if (field) {
    setAttr(field, 'aria-expanded', 'false');
  }
  if (placed === root) {
    placed = null;
    viewListeners(false);
  }
  if (!listbox || !isOpen(listbox)) {
    return;
  }
  // State for the user changes now; the DOM's after the fade.
  closeWithEffect(listbox, () => {
    if (typeof listbox.hidePopover === 'function' && listbox.hasAttribute('popover')) {
      try {
        listbox.hidePopover();
      } catch {
        // Already hidden: a morph took it out of the top layer.
      }
    }
    listbox.hidden = true;
  });
}

function openList(root: HTMLElement): void {
  const listbox = query(root, LISTBOX);
  const field = fieldOf(root);
  if (!listbox || !field) {
    return;
  }
  closeAll(root);
  finishClosing(listbox);
  if (listbox.hidden) {
    listbox.hidden = false;
    if (typeof listbox.showPopover === 'function' && listbox.hasAttribute('popover')) {
      try {
        listbox.showPopover();
      } catch {
        // Not connected, or already open after a morph.
      }
    }
  }
  setAttr(field, 'aria-expanded', 'true');
  place(root);
  if (placed !== root) {
    placed = root;
    viewListeners(true);
  }
}

function closeAll(except: Element | null): void {
  (Document.prototype.querySelectorAll.call(document, `${LISTBOX}:not([hidden], [data-nowo-ui-closing])`) as NodeListOf<Element>).forEach((listbox) => {
    const root = closest(listbox, ROOT);
    if (root && root !== except) {
      closeList(root);
    }
  });
}

/** Writes the groups into the listbox, as text, and opens it when there is at least one option. */
export function render(root: HTMLElement, groups: ComboboxGroup[]): number {
  const listbox = query(root, LISTBOX);
  if (!listbox) {
    return 0;
  }
  const state = stateOf(root);
  state.options = [];
  const base = listbox.id;
  const children: HTMLElement[] = [];
  groups.forEach((group, g) => {
    let parent: HTMLElement | null = null;
    if (group.label !== '') {
      parent = createElement('div');
      parent.setAttribute('role', 'group');
      parent.className = 'nowo-ui-combobox__group';
      const heading = createElement('div');
      heading.id = `${base}-g${g}`;
      heading.setAttribute('role', 'presentation');
      heading.className = 'nowo-ui-combobox__group-label';
      heading.textContent = group.label;
      parent.setAttribute('aria-labelledby', heading.id);
      parent.append(heading);
      children.push(parent);
    }
    group.options.forEach((item) => {
      const index = state.options.length;
      state.options.push({ ...item, group: group.label });
      const option = createElement('div');
      option.id = `${base}-${index}`;
      option.className = 'nowo-ui-combobox__option';
      option.setAttribute('role', 'option');
      option.setAttribute('aria-selected', 'false');
      option.setAttribute('data-nowo-ui-combobox-option', String(index));
      const label = createElement('span');
      label.className = 'nowo-ui-combobox__option-label';
      label.textContent = item.label;
      option.append(label);
      if (item.description !== '') {
        const description = createElement('span');
        description.className = 'nowo-ui-combobox__option-description';
        description.textContent = item.description;
        option.append(description);
      }
      if (parent) {
        parent.append(option);
      } else {
        children.push(option);
      }
    });
  });
  listbox.replaceChildren(...children);
  const field = fieldOf(root);
  if (field) {
    removeAttr(field, 'aria-activedescendant');
  }
  const count = state.options.length;
  const labels = labelsOf(root);
  setStatus(root, count === 0 ? labels.none : labels.results.replace('%count%', String(count)));
  if (count === 0) {
    closeList(root);
  } else {
    openList(root);
  }
  return count;
}

const isAbort = (error: unknown): boolean => error instanceof Error && error.name === 'AbortError';

/** Asks the host for a term and renders the answer, unless a newer term came first. Resolves when done. */
export async function search(root: HTMLElement, term: string): Promise<void> {
  const state = stateOf(root);
  state.controller?.abort();
  const controller = typeof AbortController === 'function' ? new AbortController() : null;
  state.controller = controller;
  const seq = ++state.seq;
  state.term = term;
  const url = searchUrl(getAttr(root, 'data-nowo-ui-combobox-url'), term, getAttr(root, 'data-nowo-ui-combobox-param') ?? 'q');
  const fail = (): void => {
    state.options = [];
    closeList(root);
    setStatus(root, labelsOf(root).error);
    emit(root, 'nowo-ui:combobox:error', { term });
  };
  if (!url) {
    // Another origin, or not http(s): no request at all.
    fail();
    return;
  }
  setAttr(root, 'aria-busy', 'true');
  try {
    const response = await fetch(url.toString(), {
      credentials: 'same-origin',
      mode: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      ...(controller ? { signal: controller.signal } : {}),
    });
    if (!response.ok || !/^application\/([\w.-]+\+)?json\s*(;|$)/i.test(response.headers.get('content-type') ?? '')) {
      throw new Error(`HTTP ${response.status}`);
    }
    const groups = normalise(await response.json());
    if (seq !== state.seq) {
      return;
    }
    const field = fieldOf(root);
    if (field && inputValue(field).trim() !== term) {
      return; // Typed on meanwhile: the next request answers.
    }
    const count = render(root, groups);
    emit(root, 'nowo-ui:combobox:results', { term, count });
  } catch (error) {
    if (seq === state.seq && !isAbort(error)) {
      fail();
    }
  } finally {
    if (seq === state.seq) {
      removeAttr(root, 'aria-busy');
      state.controller = null;
    }
  }
}

/** Drops the planned search and the request in flight: neither may open the list later (audit RU-L1). */
export function cancel(root: HTMLElement): void {
  const state = stateOf(root);
  clearTimeout(state.timer);
  state.timer = undefined;
  state.controller?.abort();
  state.controller = null;
  state.seq++;
  removeAttr(root, 'aria-busy');
}

/** Plans a search for the field's current text: after the debounce, or none below the minimum length. */
export function schedule(root: HTMLElement, delay = debounceOf(root)): void {
  const field = fieldOf(root);
  if (!field) {
    return;
  }
  const state = stateOf(root);
  clearTimeout(state.timer);
  state.timer = undefined;
  const term = inputValue(field).trim();
  const min = minLengthOf(root);
  if (length(term) < min) {
    cancel(root);
    state.options = [];
    closeList(root);
    setStatus(root, term === '' ? '' : labelsOf(root).min_length.replace('%count%', String(min)));
    return;
  }
  state.timer = setTimeout(() => {
    state.timer = undefined;
    void search(root, term);
  }, delay);
}

function dispatchValue(input: HTMLInputElement): void {
  input.dispatchEvent(new Event('input', { bubbles: true }));
  input.dispatchEvent(new Event('change', { bubbles: true }));
}

/** Picks the option at an index: the event, then the field (and the value input). */
export function pick(root: HTMLElement, index: number): boolean {
  const state = stateOf(root);
  const option = state.options[index];
  const field = fieldOf(root);
  if (!option || !isUsable(field)) {
    return false;
  }
  if (!emit(root, 'nowo-ui:combobox:pick', { value: option.value, label: option.label, description: option.description, group: option.group }, true)) {
    return false;
  }
  const target = valueInputOf(root, field);
  state.quiet = true;
  try {
    setInputValue(field, target ? option.label : option.value);
    dispatchValue(field);
    if (target) {
      setInputValue(target, option.value);
      dispatchValue(target);
    }
  } finally {
    state.quiet = false;
  }
  clearTimeout(state.timer);
  state.timer = undefined;
  state.term = inputValue(field).trim();
  closeList(root);
  setStatus(root, '');
  return true;
}

/** The option an arrow leads to, wrapping at both ends; -1 when there is none. */
export function nextIndex(key: string, index: number, count: number): number {
  if (count === 0) {
    return -1;
  }
  if (key === 'ArrowUp') {
    return index <= 0 ? count - 1 : index - 1;
  }
  return index < 0 || index >= count - 1 ? 0 : index + 1;
}

/** The combobox of an event target, when that target is its field. */
function rootOfField(target: EventTarget | null): HTMLElement | null {
  if (!(target instanceof HTMLInputElement)) {
    return null;
  }
  const root = closest(target, ROOT);
  return root && fieldOf(root) === target ? root : null;
}

function onKeydown(event: KeyboardEvent): void {
  if (event.isComposing) {
    return; // Enter and the arrows belong to the input method.
  }
  const root = rootOfField(event.target);
  const listbox = root ? query(root, LISTBOX) : null;
  if (!root || !listbox) {
    return;
  }
  const open = isOpen(listbox);
  const options = queryAll(listbox, OPTION);
  const index = options.findIndex((option) => option.classList.contains('is-active'));
  const state = stateOf(root);
  const field = event.target as HTMLInputElement;
  const fresh = state.options.length > 0 && inputValue(field).trim() === state.term;
  switch (event.key) {
    case 'ArrowDown':
    case 'ArrowUp':
      event.preventDefault();
      if (event.altKey) {
        if (event.key === 'ArrowUp') {
          closeList(root);
        } else if (!open && fresh) {
          openList(root);
        }
        return;
      }
      if (!open) {
        if (fresh) {
          openList(root);
          setActive(root, options[nextIndex(event.key, -1, options.length)] ?? null);
        } else {
          schedule(root, 0);
        }
        return;
      }
      setActive(root, options[nextIndex(event.key, index, options.length)] ?? null);
      return;
    case 'Enter':
      if (open && index >= 0) {
        event.preventDefault(); // never the surrounding form
        pick(root, Number(getAttr(options[index]!, 'data-nowo-ui-combobox-option')));
      } else {
        closeList(root);
      }
      return;
    case 'Escape':
      if (open) {
        // Capture phase: this Escape closes the list, not the dialog around it.
        event.preventDefault();
        event.stopPropagation();
        closeList(root);
      }
      return;
    case 'Tab':
      closeList(root);
      return;
    case 'ArrowLeft':
    case 'ArrowRight':
    case 'Home':
    case 'End':
      // The caret moves in the text: the field has the focus again, for a screen reader too.
      setActive(root, null);
      return;
    default:
  }
}

function onInput(event: Event): void {
  const root = rootOfField(event.target);
  if (!root || stateOf(root).quiet) {
    return;
  }
  const field = event.target as HTMLInputElement;
  const target = valueInputOf(root, field);
  if (target && inputValue(target) !== '') {
    // The text no longer names the picked option.
    setInputValue(target, '');
    dispatchValue(target);
  }
  schedule(root);
}

function onFocusIn(event: FocusEvent): void {
  const root = rootOfField(event.target);
  if (root) {
    enhance(root);
  }
}

function onFocusOut(event: FocusEvent): void {
  const root = rootOfField(event.target);
  const next = event.relatedTarget;
  if (root && !(next instanceof Node && Node.prototype.contains.call(root, next))) {
    // Leaving the field: a search still planned or in flight must not reopen the list behind the user (audit RU-L1).
    cancel(root);
    closeList(root);
  }
}

function onPointerDown(event: Event): void {
  // A press in the listbox must not take the focus from the field.
  if (event.target instanceof Element && closest(event.target, LISTBOX)) {
    event.preventDefault();
  }
}

function onClick(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) {
    return;
  }
  const root = closest(target, ROOT);
  const option = closest(target, OPTION);
  if (root && option) {
    pick(root, Number(getAttr(option, 'data-nowo-ui-combobox-option')));
    fieldOf(root)?.focus();
    return;
  }
  closeAll(root);
}

let bound = false;

export function bindNowoUiCombobox(): void {
  if (bound) {
    return;
  }
  bound = true;
  listen(document, 'keydown', onKeydown, true);
  listen(document, 'input', onInput);
  listen(document, 'focusin', onFocusIn);
  listen(document, 'focusout', onFocusOut);
  listen(document, 'pointerdown', onPointerDown);
  listen(document, 'click', onClick);
  if (Reflect.get(Document.prototype, 'readyState', document) === 'loading') {
    listen(document, 'DOMContentLoaded', () => enhanceAll());
  } else {
    enhanceAll();
  }
  // Fields that arrive later (a remote form dialog, a Turbo frame). A morph that takes the roles away is undone on focus.
  new MutationObserver((records) => {
    for (const record of records) {
      record.addedNodes.forEach((node) => {
        if (node instanceof Element) {
          enhanceAll(node);
        }
      });
    }
  }).observe(document, { childList: true, subtree: true });
}

// A second copy of the script must not bind again. A symbol: an element with some id cannot turn this off.
const BOUND_KEY = Symbol.for('nowo-ui.combobox.bound');
if (typeof document !== 'undefined' && !(window as unknown as Record<symbol, unknown>)[BOUND_KEY]) {
  (window as unknown as Record<symbol, unknown>)[BOUND_KEY] = true;
  bindNowoUiCombobox();
}
