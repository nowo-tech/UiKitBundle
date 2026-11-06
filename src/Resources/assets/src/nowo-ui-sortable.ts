/**
 * Sortable (2.35, `js/nowo-ui-sortable.js`, opt-in: add `sortable` to `script_names`): reorder by pointer, touch and
 * keyboard, within a list and between lists, over markup the host owns. No partial and no third-party library.
 *
 * Markup: `[data-nowo-ui-sortable="<scope>"]` (root), `[data-nowo-ui-sortable-list="<id>"]`,
 * `[data-nowo-ui-sortable-item="<id>"]`, optional `[data-nowo-ui-sortable-handle]`. Lists move items with every list
 * whose root has the same scope, so cards and columns nest on one board. A root and a list may be the same element.
 * Optional: `data-nowo-ui-sortable-axis="x|y"` (root or list), `data-nowo-ui-sortable-labels` (JSON, root),
 * `data-nowo-ui-sortable-label` (item name), `data-nowo-ui-sortable-list-label` (list name),
 * `data-nowo-ui-sortable-zone` (a wider drop area around one list), `data-nowo-ui-sortable-disabled`,
 * `data-nowo-ui-sortable-ignore` (no drag starts here).
 *
 * Events (bubbling CustomEvents on the root of the destination list; the host does the write):
 * - `nowo-ui:sortable:start` (cancelable, pointer only) {scope, id, listId, index, source}
 * - `nowo-ui:sortable:move` (cancelable, before the DOM changes)
 *   {scope, id, listId, fromListId, index, fromIndex, source: pointer|keyboard, item}
 * - `nowo-ui:sortable:moved` (after) the same, plus `revert(): boolean` for a failed write
 * - `nowo-ui:sortable:cancel` {scope, id, listId, index, source, reason: escape|refused|lost|same-place}
 * - `nowo-ui:sortable:reverted` {scope, id, listId, fromListId, index, fromIndex, source, item}
 *
 * The dragged item stays where it is until the commit: a placeholder shows the destination and a clone follows the
 * pointer, so a cancel only has to remove those two. Touch: a handle (`touch-action: none`) drags at once; a whole item
 * drags after a long press, so a swipe that starts on it still scrolls the page.
 *
 * Attributes and DOM calls go through the prototypes: a `<form>` used as an item may have a control named `closest` or
 * `getAttribute` (GT-L3). Importing touches no DOM (GT-I8). Delegated listeners on the document only; no state is kept
 * on the markup, so Turbo and morphing can replace it at any time. Size and position go through the CSSOM (strict CSP).
 */

export const ROOT = '[data-nowo-ui-sortable]';
export const LIST = '[data-nowo-ui-sortable-list]';
export const ITEM = '[data-nowo-ui-sortable-item]';
export const HANDLE = '[data-nowo-ui-sortable-handle]';
export const ZONE = '[data-nowo-ui-sortable-zone]';
const DISABLED = '[data-nowo-ui-sortable-disabled]';
const LIVE = 'data-nowo-ui-sortable-live';
const ACTIVE = 'data-nowo-ui-sortable-active';
const CONTROLS = 'button, input, select, textarea, label, summary, [role="button"], [contenteditable]:not([contenteditable="false"]), [data-nowo-ui-sortable-ignore]';
const EDITABLE = 'input, select, textarea, [contenteditable]:not([contenteditable="false"])';
const GHOST_DROP = 'script, iframe, object, embed, video, audio, template, dialog, link';
const GHOST_STRIP = /^(id|name|form|for|autofocus|tabindex|draggable|popover|popovertarget|on.*|data-(controller|action|turbo.*|nowo-ui-.*|.*-target|.*-outlet))$/;

/** Movement before a mouse or a handle starts a drag. */
export const DRAG_THRESHOLD = 4;
/** Long press before a touch on a whole item starts a drag. */
export const LONG_PRESS_MS = 250;
/** Finger movement that turns a long press into a scroll. */
export const TOUCH_SLOP = 8;
const SCROLL_EDGE = 48;
const SCROLL_MAX = 14;
/** How long a click is swallowed after a drag (the click the release itself produces). */
const CLICK_GUARD_MS = 400;

export type SortableSource = 'pointer' | 'keyboard';
export type SortableCancelReason = 'escape' | 'refused' | 'lost' | 'same-place';
export type SortableMoveDetail = {
  scope: string;
  id: string;
  listId: string;
  fromListId: string;
  index: number;
  fromIndex: number;
  source: SortableSource;
  item: HTMLElement;
};
export type SortableMovedDetail = SortableMoveDetail & { revert: () => boolean };

const DEFAULT_LABELS = {
  grabbed: '%label% grabbed.',
  moved: '%label% moved to position %position% of %count%.',
  movedToList: '%label% moved to %list%, position %position% of %count%.',
  cancelled: 'Move of %label% cancelled.',
  refused: '%label% cannot be moved there.',
  reverted: 'Move of %label% undone.',
  edge: '%label% cannot move further.',
};
type LabelKey = keyof typeof DEFAULT_LABELS;

const getAttr = (el: Element, name: string): string | null => Element.prototype.getAttribute.call(el, name);
const closest = (el: Element, selector: string): HTMLElement | null => Element.prototype.closest.call(el, selector) as HTMLElement | null;
const matches = (el: Element, selector: string): boolean => Element.prototype.matches.call(el, selector);
const queryAll = (el: Element, selector: string): HTMLElement[] => [...Element.prototype.querySelectorAll.call(el, selector)] as HTMLElement[];
const contains = (outer: Node, inner: Node | null): boolean => Node.prototype.contains.call(outer, inner);
const parentOf = (el: Node): HTMLElement | null => Reflect.get(Node.prototype, 'parentElement', el) as HTMLElement | null;
const nextOf = (el: Node): ChildNode | null => Reflect.get(Node.prototype, 'nextSibling', el) as ChildNode | null;
const tagOf = (el: Element): string => (Reflect.get(Element.prototype, 'tagName', el) as string).toUpperCase();
const isConnected = (el: Node): boolean => Reflect.get(Node.prototype, 'isConnected', el) as boolean;
const box = (el: Element): DOMRect => Element.prototype.getBoundingClientRect.call(el);
const classes = (el: Element): DOMTokenList => Reflect.get(Element.prototype, 'classList', el) as DOMTokenList;
const scrollElementBy = (el: Element, left: number, top: number): void => {
  (Element.prototype.scrollBy as (options: ScrollToOptions) => void).call(el, { left, top, behavior: 'instant' });
};
const removeNode = (el: Element): void => Element.prototype.remove.call(el);
const insert = (parent: Node, node: Node, before: Node | null): void => {
  Node.prototype.insertBefore.call(parent, node, before);
};
const emit = (target: Element, name: string, detail: unknown, cancelable = false): boolean =>
  EventTarget.prototype.dispatchEvent.call(target, new CustomEvent(name, { bubbles: true, cancelable, detail }));

const listOf = (item: Element): HTMLElement | null => {
  const parent = parentOf(item);
  return parent ? closest(parent, LIST) : null;
};
const rootOf = (el: Element): HTMLElement | null => closest(el, ROOT);
const scopeOf = (list: Element): string | null => {
  const root = rootOf(list);
  return root ? getAttr(root, 'data-nowo-ui-sortable') : null;
};
const isDisabled = (el: Element): boolean => closest(el, DISABLED) !== null;
const idOf = (item: Element): string => getAttr(item, 'data-nowo-ui-sortable-item') ?? '';
const listIdOf = (list: Element): string => getAttr(list, 'data-nowo-ui-sortable-list') ?? '';
const isRtl = (el: Element): boolean => window.getComputedStyle(el).direction === 'rtl';

/** The items of one list in document order, without those of lists nested inside it. */
export function itemsOf(list: HTMLElement): HTMLElement[] {
  return queryAll(list, ITEM).filter((item) => listOf(item) === list);
}

/** The axis of a list: its own attribute, else its root's, else `y`. */
export function axisOf(list: HTMLElement): 'x' | 'y' {
  // Not the nearest ancestor with the attribute: a card list inside an x board of columns is still a y list.
  const root = rootOf(list);
  return (getAttr(list, 'data-nowo-ui-sortable-axis') ?? (root ? getAttr(root, 'data-nowo-ui-sortable-axis') : null)) === 'x' ? 'x' : 'y';
}

function labelsOf(root: Element | null): typeof DEFAULT_LABELS {
  const labels = { ...DEFAULT_LABELS };
  const raw = root ? getAttr(root, 'data-nowo-ui-sortable-labels') : null;
  if (!raw) {
    return labels;
  }
  try {
    const parsed: unknown = JSON.parse(raw);
    if (parsed && typeof parsed === 'object') {
      for (const key of Object.keys(DEFAULT_LABELS) as LabelKey[]) {
        const value = Object.prototype.hasOwnProperty.call(parsed, key) ? (parsed as Record<string, unknown>)[key] : undefined;
        if (typeof value === 'string') {
          labels[key] = value;
        }
      }
    }
  } catch {
    // A broken attribute leaves the default wording.
  }
  return labels;
}

/** Fills `%name%` placeholders in one pass, so a value holding `%count%` stays text. */
export function formatSortable(template: string, params: Record<string, string | number>): string {
  return template.replace(/%(\w+)%/g, (whole, name: string) => (Object.prototype.hasOwnProperty.call(params, name) ? String(params[name]) : whole));
}

function itemLabel(item: Element): string {
  const explicit = getAttr(item, 'data-nowo-ui-sortable-label');
  if (explicit !== null && explicit.trim() !== '') {
    return explicit.trim();
  }
  const text = (Reflect.get(Node.prototype, 'textContent', item) as string | null ?? '').replace(/\s+/g, ' ').trim();
  return text.length > 80 ? `${text.slice(0, 80)}…` : text;
}

const listLabel = (list: Element): string => {
  const explicit = getAttr(list, 'data-nowo-ui-sortable-list-label');
  return explicit !== null && explicit.trim() !== '' ? explicit.trim() : listIdOf(list);
};

function liveRegion(root: HTMLElement): HTMLElement {
  // A <div> is not valid inside a table or a list, so the region goes after the outermost such parent.
  let anchor = root;
  for (let parent = parentOf(anchor); parent && /^(TABLE|THEAD|TBODY|TFOOT|TR|UL|OL|DL)$/.test(tagOf(parent)); parent = parentOf(anchor)) {
    anchor = parent;
  }
  const parent = parentOf(anchor);
  let live = parent ? queryAll(parent, `:scope > [${LIVE}]`)[0] : undefined;
  if (!live) {
    live = document.createElement('div');
    live.className = 'visually-hidden nowo-ui-sortable__live';
    live.setAttribute(LIVE, '');
    live.setAttribute('role', 'status');
    live.setAttribute('aria-atomic', 'true');
    insert(parent ?? document.body, live, parent ? nextOf(anchor) : null);
  }
  return live;
}

/** Says `text` in the polite live region next to the root (created on first use). */
export function announceSortable(root: HTMLElement, text: string): void {
  const live = liveRegion(root);
  // A repeated message must change, or a screen reader stays silent.
  live.textContent = live.textContent === text ? `${text} ` : text;
}

function say(root: HTMLElement | null, key: LabelKey, item: HTMLElement, extra: Record<string, string | number> = {}): void {
  if (root) {
    announceSortable(root, formatSortable(labelsOf(root)[key], { label: itemLabel(item), ...extra }));
  }
}

/** Puts `item` at `index` among the other items of `list`. */
function insertAt(list: HTMLElement, item: HTMLElement, index: number): void {
  const others = itemsOf(list).filter((other) => other !== item);
  const target = others[index];
  const last = others[others.length - 1];
  if (target) {
    insert(parentOf(target)!, item, target);
  } else if (last) {
    insert(parentOf(last)!, item, nextOf(last));
  } else {
    insert(list, item, null);
  }
}

/** Runs a DOM move and gives focus back to whatever had it inside the item (a move blurs it). */
function keepingFocus(item: HTMLElement, move: () => void, preventScroll: boolean): void {
  const active = document.activeElement;
  const had = active instanceof HTMLElement && contains(item, active);
  move();
  if (had && document.activeElement !== active) {
    HTMLElement.prototype.focus.call(active, { preventScroll });
  }
}

type Origin = { list: HTMLElement; index: number; parent: Node; next: Node | null };

/** The latest committed move of each item: a late `revert()` of an older one must not undo the newer one. */
const latestMove = new WeakMap<HTMLElement, number>();
let moveCounter = 0;

function commit(item: HTMLElement, detail: SortableMoveDetail, origin: Origin, toList: HTMLElement, place: () => void): void {
  const seq = ++moveCounter;
  latestMove.set(item, seq);
  keepingFocus(item, place, detail.source === 'pointer');
  let reverted = false;
  const revert = (): boolean => {
    if (reverted || latestMove.get(item) !== seq || !isConnected(item) || !isConnected(origin.list)) {
      return false;
    }
    reverted = true;
    latestMove.delete(item);
    keepingFocus(
      item,
      () => {
        const next = origin.next && parentOf(origin.next) === origin.parent ? origin.next : null;
        if (isConnected(origin.parent)) {
          insert(origin.parent, item, next);
        }
        if (listOf(item) !== origin.list || itemsOf(origin.list).indexOf(item) !== origin.index) {
          insertAt(origin.list, item, origin.index);
        }
      },
      false,
    );
    const root = rootOf(origin.list);
    if (root) {
      emit(root, 'nowo-ui:sortable:reverted', { ...detail });
    }
    say(root, 'reverted', item);
    return true;
  };
  const root = rootOf(toList);
  if (root) {
    emit(root, 'nowo-ui:sortable:moved', { ...detail, revert } satisfies SortableMovedDetail);
  }
  const count = itemsOf(toList).length;
  const same = toList === origin.list;
  say(root, same ? 'moved' : 'movedToList', item, { position: detail.index + 1, count, list: listLabel(toList) });
}

type Pending = {
  pointerId: number;
  pointerType: string;
  item: HTMLElement;
  list: HTMLElement;
  root: HTMLElement;
  immediate: boolean;
  startX: number;
  startY: number;
  timer: number | undefined;
};

type Drag = {
  pointerId: number;
  item: HTMLElement;
  root: HTMLElement;
  scope: string;
  origin: Origin;
  ghost: HTMLElement;
  placeholder: HTMLElement;
  list: HTMLElement | null;
  x: number;
  y: number;
  offsetX: number;
  offsetY: number;
  originX: number;
  originY: number;
  frame: number;
  lastFrame: number;
};

let pending: Pending | null = null;
let drag: Drag | null = null;
let swallowClicksUntil = 0;

/** Prevents scrolling once a drag runs. Non-passive, and only attached for a gesture on an item. */
function onTouchMove(event: TouchEvent): void {
  if (drag && event.cancelable) {
    event.preventDefault();
  }
}

function clearPending(): void {
  if (pending) {
    window.clearTimeout(pending.timer);
    pending = null;
  }
  if (!drag) {
    document.removeEventListener('touchmove', onTouchMove);
  }
}

/** A copy of the item to follow the pointer: nothing in it can act, submit, or be found by a script. */
function buildGhost(item: HTMLElement, rect: DOMRect): HTMLElement {
  const ghost = Node.prototype.cloneNode.call(item, true) as HTMLElement;
  queryAll(ghost, GHOST_DROP).forEach(removeNode);
  [ghost, ...queryAll(ghost, '*')].forEach((el) => {
    Element.prototype.getAttributeNames.call(el).forEach((name) => {
      if (GHOST_STRIP.test(name)) {
        Element.prototype.removeAttribute.call(el, name);
      }
    });
  });
  // The names are gone, so a former control named `style` no longer hides these on a form.
  classes(ghost).add('nowo-ui-sortable__ghost');
  ghost.setAttribute('aria-hidden', 'true');
  ghost.setAttribute('inert', '');
  const { style } = ghost;
  style.setProperty('position', 'fixed');
  style.setProperty('left', '0');
  style.setProperty('top', '0');
  style.setProperty('margin', '0');
  style.setProperty('box-sizing', 'border-box');
  style.setProperty('width', `${rect.width}px`);
  style.setProperty('height', `${rect.height}px`);
  style.setProperty('pointer-events', 'none');
  return ghost;
}

function buildPlaceholder(item: HTMLElement, rect: DOMRect, axis: 'x' | 'y'): HTMLElement {
  const tag = tagOf(item);
  const placeholder = document.createElement(tag === 'LI' || tag === 'TR' ? tag : 'DIV');
  placeholder.className = 'nowo-ui-sortable__placeholder';
  placeholder.setAttribute('aria-hidden', 'true');
  if (tag === 'TR') {
    const cell = document.createElement('td');
    cell.colSpan = 100;
    placeholder.append(cell);
  }
  const margins = window.getComputedStyle(item);
  placeholder.style.setProperty('margin-top', margins.marginTop);
  placeholder.style.setProperty('margin-right', margins.marginRight);
  placeholder.style.setProperty('margin-bottom', margins.marginBottom);
  placeholder.style.setProperty('margin-left', margins.marginLeft);
  placeholder.style.setProperty('height', `${rect.height}px`);
  if (axis === 'x') {
    placeholder.style.setProperty('width', `${rect.width}px`);
    placeholder.style.setProperty('flex', '0 0 auto');
  }
  return placeholder;
}

function positionGhost(state: Drag): void {
  state.ghost.style.setProperty('transform', `translate3d(${state.x - state.offsetX - state.originX}px, ${state.y - state.offsetY - state.originY}px, 0)`);
}

function setTargetList(state: Drag, list: HTMLElement | null): void {
  if (state.list !== list) {
    if (state.list) {
      classes(state.list).remove('is-drop-target');
    }
    if (list) {
      classes(list).add('is-drop-target');
    }
    state.list = list;
  }
}

/** The list of this scope under a point. A zone stands for the list inside it. */
function listAt(state: Drag): HTMLElement | null {
  const accepts = (list: HTMLElement): boolean => scopeOf(list) === state.scope && !contains(state.item, list);
  for (let el: HTMLElement | null = document.elementFromPoint(state.x, state.y) as HTMLElement | null; el; el = parentOf(el)) {
    if (matches(el, LIST) && accepts(el)) {
      return isDisabled(el) ? null : el;
    }
    if (matches(el, ZONE)) {
      const inner = queryAll(el, LIST).find(accepts);
      if (inner) {
        return isDisabled(inner) ? null : inner;
      }
    }
  }
  return null;
}

/** Moves the placeholder under the pointer, touching the DOM only when its place changes. */
function placePlaceholder(state: Drag): void {
  const list = listAt(state);
  if (!list) {
    setTargetList(state, null);
    return;
  }
  setTargetList(state, list);
  const others = itemsOf(list).filter((item) => item !== state.item);
  const horizontal = axisOf(list) === 'x';
  const rtl = horizontal && isRtl(list);
  const before =
    others.find((item) => {
      const rect = box(item);
      if (!horizontal) {
        return state.y < rect.top + rect.height / 2;
      }
      const middle = rect.left + rect.width / 2;
      return rtl ? state.x > middle : state.x < middle;
    }) ?? null;
  const { placeholder } = state;
  const skip = (el: Element | null, step: 'nextElementSibling' | 'previousElementSibling'): Element | null => {
    let current = el;
    while (current && (current === state.item || current === state.ghost)) {
      current = Reflect.get(Element.prototype, step, current) as Element | null;
    }
    return current;
  };
  const inPlace = before
    ? skip(placeholder.nextElementSibling, 'nextElementSibling') === before
    : others.length > 0
      ? skip(placeholder.previousElementSibling, 'previousElementSibling') === others[others.length - 1] && isConnected(placeholder)
      : parentOf(placeholder) === list;
  if (inPlace && isConnected(placeholder)) {
    return;
  }
  if (before) {
    insert(parentOf(before)!, placeholder, before);
  } else if (others.length > 0) {
    const last = others[others.length - 1]!;
    insert(parentOf(last)!, placeholder, nextOf(last));
  } else {
    insert(list, placeholder, null);
  }
}

function update(state: Drag): void {
  if (!isConnected(state.item)) {
    cancelDrag('lost');
    return;
  }
  if (!isConnected(state.ghost)) {
    const parent = parentOf(state.item);
    if (parent) {
      insert(parent, state.ghost, null);
    }
  }
  positionGhost(state);
  placePlaceholder(state);
}

function scrollAmount(pointer: number, start: number, end: number, ms: number): number {
  const edge = Math.min(SCROLL_EDGE, (end - start) / 4);
  if (edge <= 0) {
    return 0;
  }
  const speed = (distance: number): number => (SCROLL_MAX * (1 - Math.max(distance, 0) / edge) * ms) / 16;
  if (pointer < start + edge) {
    return -speed(pointer - start);
  }
  return pointer > end - edge ? speed(end - pointer) : 0;
}

const ancestorsOf = (start: Element | null): HTMLElement[] => {
  const chain: HTMLElement[] = [];
  for (let el = start as HTMLElement | null; el; el = parentOf(el)) {
    chain.push(el);
  }
  return chain;
};

/**
 * Scrolls the nearest container that can still scroll towards the edge the pointer is near. Containers under the pointer
 * come first, then those around the placeholder: a finger at the screen edge is often just past a padded board, and the
 * board must still bring in the next column.
 */
function autoScroll(state: Drag, ms: number): boolean {
  const page = document.scrollingElement;
  let doneX = false;
  let doneY = false;
  const near = (pointer: number, start: number, end: number): boolean => pointer >= start - SCROLL_EDGE && pointer <= end + SCROLL_EDGE;
  const candidates = new Set([...ancestorsOf(document.elementFromPoint(state.x, state.y)), ...ancestorsOf(state.placeholder)]);
  for (const el of candidates) {
    if (doneX && doneY) {
      break;
    }
    if (el === page || el === document.body) {
      continue;
    }
    const style = window.getComputedStyle(el);
    const rect = box(el);
    const alongX = near(state.x, rect.left, rect.right) && state.y >= rect.top && state.y <= rect.bottom;
    const alongY = near(state.y, rect.top, rect.bottom) && state.x >= rect.left && state.x <= rect.right;
    const dx: number = !doneX && alongX && /auto|scroll|overlay/.test(style.overflowX) && el.scrollWidth > el.clientWidth ? scrollAmount(state.x, rect.left, rect.right, ms) : 0;
    const dy: number = !doneY && alongY && /auto|scroll|overlay/.test(style.overflowY) && el.scrollHeight > el.clientHeight ? scrollAmount(state.y, rect.top, rect.bottom, ms) : 0;
    const canX: boolean = dx < 0 ? el.scrollLeft > 0 : el.scrollLeft + el.clientWidth < el.scrollWidth;
    const canY: boolean = dy < 0 ? el.scrollTop > 0 : el.scrollTop + el.clientHeight < el.scrollHeight;
    if ((dx !== 0 && canX) || (dy !== 0 && canY)) {
      scrollElementBy(el, canX ? dx : 0, canY ? dy : 0);
      doneX ||= dx !== 0 && canX;
      doneY ||= dy !== 0 && canY;
    }
  }
  let scrolled = doneX || doneY;
  if (page && !(doneX && doneY)) {
    const locked = (el: Element): boolean => window.getComputedStyle(el).overflowY === 'hidden';
    if (!locked(page) && !locked(document.body)) {
      const dx: number = doneX ? 0 : scrollAmount(state.x, 0, window.innerWidth, ms);
      const dy: number = doneY ? 0 : scrollAmount(state.y, 0, window.innerHeight, ms);
      const canX = dx !== 0 && (dx < 0 ? page.scrollLeft > 0 : page.scrollLeft + page.clientWidth < page.scrollWidth);
      const canY = dy !== 0 && (dy < 0 ? page.scrollTop > 0 : page.scrollTop + page.clientHeight < page.scrollHeight);
      if (canX || canY) {
        scrollElementBy(page, canX ? dx : 0, canY ? dy : 0);
        scrolled = true;
      }
    }
  }
  return scrolled;
}

function onFrame(now: number): void {
  const state = drag;
  if (!state) {
    return;
  }
  const ms = Math.min(now - state.lastFrame, 50);
  state.lastFrame = now;
  if (ms > 0 && autoScroll(state, ms)) {
    update(state);
  }
  if (drag === state) {
    state.frame = window.requestAnimationFrame(onFrame);
  }
}

function startDrag(gesture: Pending, x: number, y: number): void {
  const { item, list, root, pointerId, pointerType } = gesture;
  const scope = getAttr(root, 'data-nowo-ui-sortable') ?? '';
  const fromIndex = itemsOf(list).indexOf(item);
  // Not clearPending(): the scroll guard must stay attached for the rest of this gesture.
  window.clearTimeout(gesture.timer);
  pending = null;
  if (!isConnected(item) || !emit(root, 'nowo-ui:sortable:start', { scope, id: idOf(item), listId: listIdOf(list), index: fromIndex, source: 'pointer' }, true)) {
    document.removeEventListener('touchmove', onTouchMove);
    return;
  }
  const rect = box(item);
  const parent = parentOf(item)!;
  const origin: Origin = { list, index: fromIndex, parent, next: nextOf(item) };
  const ghost = buildGhost(item, rect);
  const placeholder = buildPlaceholder(item, rect, axisOf(list));
  insert(parent, placeholder, item);
  insert(parent, ghost, null);
  classes(item).add('is-dragging');
  document.documentElement.setAttribute(ACTIVE, '');
  if (pointerType === 'mouse') {
    try {
      Element.prototype.setPointerCapture.call(item, pointerId);
    } catch {
      // The pointer is already gone: the release still reaches the document.
    }
  }
  // Where a fixed box at 0,0 really lands (an ancestor with a transform moves its containing block).
  const fixedAt = box(ghost);
  drag = {
    pointerId,
    item,
    root,
    scope,
    origin,
    ghost,
    placeholder,
    list: null,
    x,
    y,
    offsetX: gesture.startX - rect.left,
    offsetY: gesture.startY - rect.top,
    originX: fixedAt.left,
    originY: fixedAt.top,
    frame: 0,
    lastFrame: performance.now(),
  };
  drag.frame = window.requestAnimationFrame(onFrame);
  say(root, 'grabbed', item);
  update(drag);
}

/** Removes everything the drag added. The item is where it was, unless a commit already moved it. */
function cleanup(state: Drag): void {
  window.cancelAnimationFrame(state.frame);
  setTargetList(state, null);
  removeNode(state.ghost);
  removeNode(state.placeholder);
  classes(state.item).remove('is-dragging');
  document.documentElement.removeAttribute(ACTIVE);
  document.removeEventListener('touchmove', onTouchMove);
  drag = null;
  swallowClicksUntil = performance.now() + CLICK_GUARD_MS;
}

function cancelDrag(reason: SortableCancelReason): void {
  const state = drag;
  if (!state) {
    return;
  }
  cleanup(state);
  emit(state.root, 'nowo-ui:sortable:cancel', {
    scope: state.scope,
    id: idOf(state.item),
    listId: listIdOf(state.origin.list),
    index: state.origin.index,
    source: 'pointer',
    reason,
  });
  if (reason === 'escape') {
    // The button is still down: the click its release produces must not follow a link in the item.
    swallowClicksUntil = Number.POSITIVE_INFINITY;
    say(state.root, 'cancelled', state.item);
  } else if (reason === 'refused') {
    say(state.root, 'refused', state.item);
  }
}

function drop(state: Drag): void {
  update(state);
  if (drag !== state) {
    return;
  }
  const { item, placeholder, origin } = state;
  const parent = parentOf(placeholder);
  const toList = parent ? closest(parent, LIST) : null;
  if (!toList || !parent || !isConnected(placeholder)) {
    cancelDrag('lost');
    return;
  }
  const others = itemsOf(toList).filter((other) => other !== item);
  const following = others.findIndex((other) => Node.prototype.compareDocumentPosition.call(placeholder, other) & Node.DOCUMENT_POSITION_FOLLOWING);
  const index = following === -1 ? others.length : following;
  if (toList === origin.list && index === origin.index) {
    cancelDrag('same-place');
    return;
  }
  const detail: SortableMoveDetail = {
    scope: state.scope,
    id: idOf(item),
    listId: listIdOf(toList),
    fromListId: listIdOf(origin.list),
    index,
    fromIndex: origin.index,
    source: 'pointer',
    item,
  };
  if (!emit(rootOf(toList) ?? state.root, 'nowo-ui:sortable:move', detail, true)) {
    cancelDrag('refused');
    return;
  }
  // A listener may have replaced the markup.
  if (!isConnected(item) || !isConnected(placeholder)) {
    cancelDrag('lost');
    return;
  }
  commit(item, detail, origin, toList, () => {
    insert(parentOf(placeholder)!, item, placeholder);
    cleanup(state);
  });
}

function onPointerDown(event: PointerEvent): void {
  swallowClicksUntil = 0;
  if (pending || drag || event.isPrimary === false || event.button !== 0 || event.ctrlKey || !(event.target instanceof Element)) {
    return;
  }
  const target = event.target;
  const item = closest(target, ITEM);
  const list = item ? listOf(item) : null;
  const root = list ? rootOf(list) : null;
  if (!item || !list || !root || isDisabled(item)) {
    return;
  }
  const ownHandles = queryAll(item, HANDLE).filter((handle) => closest(handle, ITEM) === item);
  const handle = closest(target, HANDLE);
  const onHandle = handle !== null && closest(handle, ITEM) === item;
  if (ownHandles.length > 0 && !onHandle) {
    return;
  }
  // A control inside the item or the handle keeps its job; a handle that is itself a button still drags.
  const control = closest(target, CONTROLS);
  if (control && control !== item && contains(item, control) && !(onHandle && contains(control, handle))) {
    return;
  }
  pending = {
    pointerId: event.pointerId,
    pointerType: event.pointerType,
    item,
    list,
    root,
    immediate: event.pointerType === 'mouse' || onHandle,
    startX: event.clientX,
    startY: event.clientY,
    timer: undefined,
  };
  if (event.pointerType !== 'mouse') {
    document.addEventListener('touchmove', onTouchMove, { passive: false });
  }
  if (!pending.immediate) {
    const gesture = pending;
    gesture.timer = window.setTimeout(() => {
      if (pending === gesture) {
        startDrag(gesture, gesture.startX, gesture.startY);
      }
    }, LONG_PRESS_MS);
  }
}

function onPointerMove(event: PointerEvent): void {
  if (drag) {
    if (event.pointerId === drag.pointerId) {
      drag.x = event.clientX;
      drag.y = event.clientY;
      update(drag);
    }
    return;
  }
  if (!pending || event.pointerId !== pending.pointerId) {
    return;
  }
  const distance = Math.hypot(event.clientX - pending.startX, event.clientY - pending.startY);
  if (pending.immediate) {
    if (distance >= DRAG_THRESHOLD) {
      startDrag(pending, event.clientX, event.clientY);
    }
  } else if (distance > TOUCH_SLOP) {
    // The finger moved before the long press ended: this gesture is a scroll.
    clearPending();
  }
}

function onPointerUp(event: PointerEvent): void {
  if (pending && event.pointerId === pending.pointerId) {
    clearPending();
  } else if (drag && event.pointerId === drag.pointerId) {
    drag.x = event.clientX;
    drag.y = event.clientY;
    drop(drag);
  } else if (swallowClicksUntil === Number.POSITIVE_INFINITY) {
    swallowClicksUntil = performance.now() + CLICK_GUARD_MS;
  }
}

function onPointerCancel(event: PointerEvent): void {
  if (pending && event.pointerId === pending.pointerId) {
    clearPending();
  } else if (drag && event.pointerId === drag.pointerId) {
    cancelDrag('lost');
  }
}

function onEscape(event: KeyboardEvent): void {
  if (event.key === 'Escape' && drag) {
    // Capture phase: Escape ends the drag, not the dialog around it.
    event.preventDefault();
    event.stopPropagation();
    cancelDrag('escape');
  }
}

/** Where Alt+arrow sends an item: a step within its list, or into the next or previous list. */
function moveByKey(key: string, list: HTMLElement): { within: number } | { across: number } | null {
  const horizontal = axisOf(list) === 'x';
  const rtl = key === 'ArrowLeft' || key === 'ArrowRight' ? isRtl(list) : false;
  const step: Record<string, number> = { ArrowUp: -1, ArrowDown: 1, ArrowLeft: rtl ? 1 : -1, ArrowRight: rtl ? -1 : 1 };
  const value = step[key];
  if (value === undefined) {
    return null;
  }
  const vertical = key === 'ArrowUp' || key === 'ArrowDown';
  return vertical !== horizontal ? { within: value } : { across: value };
}

function onKeydown(event: KeyboardEvent): void {
  if (!event.altKey || event.ctrlKey || event.metaKey || event.shiftKey || event.defaultPrevented || drag || !(event.target instanceof Element)) {
    return;
  }
  const target = event.target;
  const item = closest(target, ITEM);
  const list = item ? listOf(item) : null;
  const root = list ? rootOf(list) : null;
  const action = list ? moveByKey(event.key, list) : null;
  if (!item || !list || !root || !action || isDisabled(item) || closest(target, EDITABLE)) {
    return;
  }
  event.preventDefault();
  const scope = getAttr(root, 'data-nowo-ui-sortable') ?? '';
  const fromIndex = itemsOf(list).indexOf(item);
  let toList: HTMLElement | undefined = list;
  let index = fromIndex;
  if ('within' in action) {
    index = fromIndex + action.within;
    if (index < 0 || index >= itemsOf(list).length) {
      say(root, 'edge', item);
      return;
    }
  } else {
    const lists = [...document.querySelectorAll<HTMLElement>(LIST)].filter((other) => other === list || (scopeOf(other) === scope && !isDisabled(other) && !contains(item, other)));
    toList = lists[lists.indexOf(list) + action.across];
    if (!toList) {
      say(root, 'edge', item);
      return;
    }
    index = Math.min(fromIndex, itemsOf(toList).length);
  }
  const destination = toList;
  const detail: SortableMoveDetail = {
    scope,
    id: idOf(item),
    listId: listIdOf(destination),
    fromListId: listIdOf(list),
    index,
    fromIndex,
    source: 'keyboard',
    item,
  };
  const destinationRoot = rootOf(destination) ?? root;
  if (!emit(destinationRoot, 'nowo-ui:sortable:move', detail, true)) {
    say(destinationRoot, 'refused', item);
    return;
  }
  const parent = parentOf(item)!;
  commit(item, detail, { list, index: fromIndex, parent, next: nextOf(item) }, destination, () => insertAt(destination, item, index));
}

function onFocusIn(event: FocusEvent): void {
  // The region must exist before the first message, or a screen reader may miss it.
  const item = event.target instanceof Element ? closest(event.target, ITEM) : null;
  const root = item ? rootOf(item) : null;
  if (root) {
    liveRegion(root);
  }
}

function onClick(event: MouseEvent): void {
  if (performance.now() < swallowClicksUntil) {
    swallowClicksUntil = 0;
    event.preventDefault();
    event.stopPropagation();
  }
}

/** A native drag of a link, an image or a `draggable` item would take the pointer away; selecting text too. */
function onNativeGesture(event: Event): void {
  if (pending || drag) {
    event.preventDefault();
  }
}

function onContextMenu(event: Event): void {
  // Android opens the menu on a long press.
  if (drag || (pending && pending.pointerType !== 'mouse')) {
    event.preventDefault();
  }
}

function onLeave(): void {
  clearPending();
  cancelDrag('lost');
}

function onVisibility(): void {
  if (document.visibilityState === 'hidden') {
    onLeave();
  }
}

let bound = false;

/** Binds the delegated listeners once. Content inserted later works without rebinding. */
export function bindNowoUiSortable(): void {
  if (bound) {
    return;
  }
  bound = true;
  document.addEventListener('pointerdown', onPointerDown);
  document.addEventListener('pointermove', onPointerMove);
  document.addEventListener('pointerup', onPointerUp);
  document.addEventListener('pointercancel', onPointerCancel);
  document.addEventListener('keydown', onEscape, true);
  document.addEventListener('keydown', onKeydown);
  document.addEventListener('focusin', onFocusIn);
  document.addEventListener('click', onClick, true);
  document.addEventListener('dragstart', onNativeGesture);
  document.addEventListener('selectstart', onNativeGesture);
  document.addEventListener('contextmenu', onContextMenu);
  document.addEventListener('visibilitychange', onVisibility);
  // A Turbo snapshot must not keep the copy or the placeholder.
  document.addEventListener('turbo:before-cache', onLeave);
  document.addEventListener('turbo:before-render', onLeave);
  window.addEventListener('pagehide', onLeave);
  window.addEventListener('blur', onLeave);
}

declare global {
  interface Window {
    nowoUiAnnounceSortable?: typeof announceSortable;
  }
}

// The window property also stops a second copy of the script from binding again.
if (typeof document !== 'undefined' && !window.nowoUiAnnounceSortable) {
  window.nowoUiAnnounceSortable = announceSortable;
  bindNowoUiSortable();
}
