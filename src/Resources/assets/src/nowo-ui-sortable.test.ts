import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { LONG_PRESS_MS, announceSortable, axisOf, bindNowoUiSortable, formatSortable, isReachable, itemsOf } from './nowo-ui-sortable';

const MARKUP = `
<div data-testid="host">
  <div id="board" data-nowo-ui-sortable="cards" data-nowo-ui-sortable-labels='{"moved":"%label%: %position%/%count%","unknown":"x","edge":5}'>
    <section id="zone-a" data-nowo-ui-sortable-zone>
      <h2 id="head-a">A</h2>
      <ul id="a" data-nowo-ui-sortable-list="a" data-nowo-ui-sortable-list-label="To do">
        <li id="a1" data-nowo-ui-sortable-item="a1" tabindex="0">One <a id="link" href="#x">link</a> <button id="btn" type="button">b</button></li>
        <li id="a2" data-nowo-ui-sortable-item="a2" tabindex="0" data-nowo-ui-sortable-label="Second">Two</li>
        <li id="a3" data-nowo-ui-sortable-item="a3" tabindex="0">Three <input id="field"></li>
      </ul>
      <button id="add-a" type="button">Add</button>
    </section>
    <ul id="b" data-nowo-ui-sortable-list="b">
      <li id="b1" data-nowo-ui-sortable-item="b1" tabindex="0">B one</li>
    </ul>
    <ul id="c" data-nowo-ui-sortable-list="c"></ul>
    <ul id="off" data-nowo-ui-sortable-list="off" data-nowo-ui-sortable-disabled>
      <li id="off1" data-nowo-ui-sortable-item="off1" tabindex="0">Locked</li>
    </ul>
  </div>
  <ol id="h" data-nowo-ui-sortable="chips" data-nowo-ui-sortable-list="h" data-nowo-ui-sortable-axis="x">
    <li id="h1" data-nowo-ui-sortable-item="h1"><button id="h1-handle" type="button" data-nowo-ui-sortable-handle>≡</button><button id="h1-edit" type="button">edit</button> text</li>
    <li id="h2" data-nowo-ui-sortable-item="h2"><span id="h2-handle" data-nowo-ui-sortable-handle><button id="h2-inner" type="button">x</button></span></li>
  </ol>
</div>`;

const el = (id: string): HTMLElement => document.getElementById(id)!;
const ids = (list: string): string[] => itemsOf(el(list)).map((i) => i.id);
const rect = (left: number, top: number, width: number, height: number): DOMRect =>
  ({ left, top, width, height, right: left + width, bottom: top + height, x: left, y: top, toJSON: () => ({}) }) as DOMRect;

// The module calls the prototype (GT-L3), so a spy on the element would never run.
const rects = new Map<Element, DOMRect>();

function layout(): void {
  rects.clear();
  vi.spyOn(Element.prototype, 'getBoundingClientRect').mockImplementation(function (this: Element) {
    return rects.get(this) ?? rect(0, 0, 0, 0);
  });
  // Rows 40 px high: lists at x 0 / 200 / 400, items stacked.
  const place = (id: string, x: number, y: number, w = 100, h = 40): void => {
    rects.set(el(id), rect(x, y, w, h));
  };
  place('a1', 0, 100);
  place('a2', 0, 140);
  place('a3', 0, 180);
  place('b1', 200, 100);
  place('h1', 500, 100, 50, 40);
  place('h2', 550, 100, 50, 40);
}

let under: () => Element | null;

// On the prototype: the module reads them there (HH-L4). happy-dom has no layout, and not all of them.
const SIZES: Record<string, number> = { scrollHeight: 1000, clientHeight: 400, scrollWidth: 400, clientWidth: 400 };
const realSizes = new Map<string, PropertyDescriptor | undefined>();

function fakeSizes(scroller: Element): void {
  for (const [name, value] of Object.entries(SIZES)) {
    if (!realSizes.has(name)) {
      realSizes.set(name, Object.getOwnPropertyDescriptor(Element.prototype, name));
    }
    Object.defineProperty(Element.prototype, name, {
      configurable: true,
      get(this: Element) {
        return this === scroller ? value : 0;
      },
    });
  }
}

function restoreSizes(): void {
  for (const [name, descriptor] of realSizes) {
    if (descriptor) {
      Object.defineProperty(Element.prototype, name, descriptor);
    } else {
      delete (Element.prototype as unknown as Record<string, unknown>)[name];
    }
  }
  realSizes.clear();
}

function pointer(type: string, target: Element, x: number, y: number, init: Record<string, unknown> = {}): PointerEvent {
  const event = new PointerEvent(type, { bubbles: true, cancelable: true, clientX: x, clientY: y, pointerId: 1, pointerType: 'mouse', button: 0, buttons: 1, isPrimary: true, ...init } as PointerEventInit);
  target.dispatchEvent(event);
  return event;
}

/** Presses on `target`, drags to `over` at (x, y). Leaves the drag running. */
function dragTo(target: Element, over: Element, x: number, y: number): void {
  pointer('pointerdown', target, 10, 110);
  under = () => over;
  pointer('pointermove', target, x, y);
}

function drop(target: Element, x: number, y: number): void {
  pointer('pointerup', target, x, y);
}

function alt(target: Element, key: string, init: KeyboardEventInit = {}): KeyboardEvent {
  const event = new KeyboardEvent('keydown', { key, altKey: true, bubbles: true, cancelable: true, ...init });
  target.dispatchEvent(event);
  return event;
}

const live = (): string => document.querySelector('[data-nowo-ui-sortable-live]')?.textContent ?? '';
const leftovers = (): number => document.querySelectorAll('.nowo-ui-sortable__ghost, .nowo-ui-sortable__placeholder').length;

describe('nowo-ui-sortable', () => {
  let events: { type: string; detail: Record<string, unknown> }[];
  const listeners: [string, EventListener][] = [];

  const listen = (type: string, fn: EventListener): void => {
    document.addEventListener(type, fn);
    listeners.push([type, fn]);
  };

  beforeEach(() => {
    document.body.innerHTML = MARKUP;
    bindNowoUiSortable();
    layout();
    events = [];
    under = () => null;
    // On the prototype, as a browser has it: the module never reads it from the document itself (HH-L4).
    Object.defineProperty(Document.prototype, 'elementFromPoint', { configurable: true, value: () => under() });
    // happy-dom does no layout: rendered means no [hidden] around it. The fallback has its own test.
    Object.defineProperty(Element.prototype, 'checkVisibility', {
      configurable: true,
      value(this: Element) {
        return this.closest('[hidden]') === null;
      },
    });
    el('a1').dispatchEvent(new FocusEvent('focusin', { bubbles: true })); // the live region exists, as after a first focus
    for (const type of ['start', 'move', 'moved', 'cancel', 'reverted']) {
      listen(`nowo-ui:sortable:${type}`, (e) => events.push({ type, detail: (e as CustomEvent).detail }));
    }
  });

  afterEach(() => {
    document.dispatchEvent(new Event('turbo:before-render'));
    listeners.splice(0).forEach(([type, fn]) => document.removeEventListener(type, fn));
    pointer('pointercancel', document.body, 0, 0);
    pointer('pointerdown', document.body, 0, 0); // a press clears a click guard left by this test's drag, as on a real page
    pointer('pointerup', document.body, 0, 0);
    restoreSizes();
    document.body.innerHTML = '';
    delete (Element.prototype as unknown as { checkVisibility?: unknown }).checkVisibility;
    vi.restoreAllMocks();
    vi.useRealTimers();
  });

  describe('pointer', () => {
    it('does not touch the DOM before the threshold, then shows a placeholder and an inert copy', () => {
      pointer('pointerdown', el('a2'), 10, 150);
      under = () => el('a');
      pointer('pointermove', el('a2'), 11, 151);
      expect(leftovers()).toBe(0);
      expect(events).toEqual([]);

      pointer('pointermove', el('a2'), 10, 160);
      expect(events.map((e) => e.type)).toEqual(['start']);
      expect(el('a2').classList.contains('is-dragging')).toBe(true);
      expect(document.documentElement.hasAttribute('data-nowo-ui-sortable-active')).toBe(true);
      const ghost = document.querySelector<HTMLElement>('.nowo-ui-sortable__ghost')!;
      expect(ghost.getAttribute('aria-hidden')).toBe('true');
      expect(ghost.hasAttribute('inert')).toBe(true);
      expect(ghost.hasAttribute('id')).toBe(false);
      expect(ghost.hasAttribute('data-nowo-ui-sortable-item')).toBe(false);
      expect(ghost.style.position).toBe('fixed');
      expect(ghost.style.transform).toContain('translate3d');
      const placeholder = document.querySelector<HTMLElement>('.nowo-ui-sortable__placeholder')!;
      expect(placeholder.tagName).toBe('LI');
      expect(placeholder.getAttribute('aria-hidden')).toBe('true');
      expect(placeholder.style.height).toBe('40px');
      expect(el('a').classList.contains('is-drop-target')).toBe(true);
      expect(live()).toContain('Second grabbed');
      // Style goes through the CSSOM: the markup attribute only exists on script-made nodes.
      expect(el('a2').hasAttribute('style')).toBe(false);
    });

    it('fires move before the DOM changes, then moves the item and fires moved', () => {
      const seenAtMove: string[][] = [];
      listen('nowo-ui:sortable:move', () => seenAtMove.push(ids('a')));
      dragTo(el('a1'), el('a'), 10, 170); // below the middle of a2 (160), above the middle of a3 (200)
      expect(ids('a')).toEqual(['a1', 'a2', 'a3']);
      drop(el('a1'), 10, 170);
      expect(seenAtMove).toEqual([['a1', 'a2', 'a3']]);
      expect(ids('a')).toEqual(['a2', 'a1', 'a3']);
      expect(events.map((e) => e.type)).toEqual(['start', 'move', 'moved']);
      const moved = events[2]!.detail;
      expect(moved).toMatchObject({ scope: 'cards', id: 'a1', listId: 'a', fromListId: 'a', index: 1, fromIndex: 0, source: 'pointer', item: el('a1') });
      expect(typeof moved.revert).toBe('function');
      expect(leftovers()).toBe(0);
      expect(el('a1').classList.contains('is-dragging')).toBe(false);
      expect(document.documentElement.hasAttribute('data-nowo-ui-sortable-active')).toBe(false);
      expect(el('a').classList.contains('is-drop-target')).toBe(false);
      expect(live()).toContain('2/3');
    });

    it('moves between lists, and into an empty list', () => {
      dragTo(el('a1'), el('b'), 210, 90);
      drop(el('a1'), 210, 90);
      expect(ids('b')).toEqual(['a1', 'b1']);
      expect(events[2]!.detail).toMatchObject({ listId: 'b', fromListId: 'a', index: 0, fromIndex: 0 });
      expect(live()).toContain('moved to b, position 1 of 2');

      dragTo(el('a1'), el('c'), 410, 110);
      drop(el('a1'), 410, 110);
      expect(ids('c')).toEqual(['a1']);
    });

    it('a zone stands for its list, so a drop on a column header lands in the column', () => {
      dragTo(el('b1'), el('head-a'), 10, 400);
      drop(el('b1'), 10, 400);
      expect(ids('a')).toEqual(['a1', 'a2', 'a3', 'b1']);
      // After the last item, before the add button that is not an item: it stays outside the list.
      expect(el('add-a').parentElement!.id).toBe('zone-a');
    });

    it('keeps the last place when the pointer leaves every list', () => {
      dragTo(el('a1'), el('b'), 210, 90);
      under = () => null;
      pointer('pointermove', el('a1'), 900, 900);
      expect(el('b').classList.contains('is-drop-target')).toBe(false);
      drop(el('a1'), 900, 900);
      expect(ids('b')).toEqual(['a1', 'b1']);
    });

    it('a drop on the same place changes nothing and reports it', () => {
      dragTo(el('a1'), el('a'), 10, 116);
      drop(el('a1'), 10, 116);
      expect(ids('a')).toEqual(['a1', 'a2', 'a3']);
      expect(events.map((e) => e.type)).toEqual(['start', 'cancel']);
      expect(events[1]!.detail.reason).toBe('same-place');
      expect(leftovers()).toBe(0);
    });

    it('a listener refusing move restores everything', () => {
      listen('nowo-ui:sortable:move', (e) => e.preventDefault());
      dragTo(el('a1'), el('b'), 210, 90);
      drop(el('a1'), 210, 90);
      expect(ids('a')).toEqual(['a1', 'a2', 'a3']);
      expect(ids('b')).toEqual(['b1']);
      expect(events.map((e) => e.type)).toEqual(['start', 'move', 'cancel']);
      expect(events[2]!.detail.reason).toBe('refused');
      expect(leftovers()).toBe(0);
      expect(live()).toContain('cannot be moved there');
    });

    it('a listener refusing start stops the drag', () => {
      listen('nowo-ui:sortable:start', (e) => e.preventDefault());
      dragTo(el('a1'), el('b'), 210, 90);
      expect(leftovers()).toBe(0);
      expect(el('a1').classList.contains('is-dragging')).toBe(false);
      drop(el('a1'), 210, 90);
      expect(events.map((e) => e.type)).toEqual(['start']);
    });

    it('Escape cancels, does not reach the page, restores the DOM and swallows the release click', () => {
      const outer = vi.fn();
      document.body.addEventListener('keydown', outer);
      dragTo(el('a1'), el('b'), 210, 90);
      const escape = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
      el('a1').dispatchEvent(escape);
      document.body.removeEventListener('keydown', outer);
      expect(escape.defaultPrevented).toBe(true);
      expect(outer).not.toHaveBeenCalled();
      expect(ids('a')).toEqual(['a1', 'a2', 'a3']);
      expect(ids('b')).toEqual(['b1']);
      expect(leftovers()).toBe(0);
      expect(events[events.length - 1]).toMatchObject({ type: 'cancel', detail: { reason: 'escape' } });
      expect(live()).toContain('cancelled');

      pointer('pointerup', el('a1'), 210, 90);
      const click = new MouseEvent('click', { bubbles: true, cancelable: true, detail: 1 });
      el('link').dispatchEvent(click);
      expect(click.defaultPrevented).toBe(true);
      // Only that one click.
      const next = new MouseEvent('click', { bubbles: true, cancelable: true, detail: 1 });
      el('link').dispatchEvent(next);
      expect(next.defaultPrevented).toBe(false);
    });

    it('a browser taking the pointer, Turbo, hiding the page and a blur all clean up', () => {
      for (const end of [
        () => pointer('pointercancel', el('a1'), 0, 0),
        () => document.dispatchEvent(new Event('turbo:before-cache')),
        () => window.dispatchEvent(new Event('blur')),
        () => window.dispatchEvent(new Event('pagehide')),
        () => {
          const spy = vi.spyOn(Document.prototype, 'visibilityState', 'get').mockReturnValue('hidden');
          document.dispatchEvent(new Event('visibilitychange'));
          spy.mockRestore();
        },
      ]) {
        events.length = 0;
        dragTo(el('a1'), el('b'), 210, 90);
        expect(leftovers()).toBe(2);
        end();
        expect(leftovers()).toBe(0);
        expect(events[events.length - 1]).toMatchObject({ type: 'cancel', detail: { reason: 'lost' } });
        expect(ids('a')).toEqual(['a1', 'a2', 'a3']);
      }
    });

    it('survives morphing: a replaced item aborts, a removed placeholder or copy comes back', () => {
      dragTo(el('a1'), el('b'), 210, 90);
      document.querySelector('.nowo-ui-sortable__placeholder')!.remove();
      document.querySelector('.nowo-ui-sortable__ghost')!.remove();
      pointer('pointermove', el('a1'), 210, 95);
      expect(leftovers()).toBe(2);

      const item = el('a1');
      item.remove();
      pointer('pointermove', document.body, 210, 96);
      expect(events[events.length - 1]).toMatchObject({ type: 'cancel', detail: { reason: 'lost' } });
      expect(leftovers()).toBe(0);
    });

    it('a listener replacing the markup during move leaves no half commit', () => {
      listen('nowo-ui:sortable:move', () => el('a1').remove());
      dragTo(el('a1'), el('b'), 210, 90);
      drop(el('a1'), 210, 90);
      expect(events[events.length - 1]).toMatchObject({ type: 'cancel', detail: { reason: 'lost' } });
      expect(leftovers()).toBe(0);
    });

    it('does not start on controls, disabled items, other buttons or modified clicks', () => {
      for (const id of ['btn', 'field', 'off1', 'h1', 'h1-edit', 'h2-inner']) {
        pointer('pointerdown', el(id), 10, 110);
        under = () => el('a');
        pointer('pointermove', el(id), 50, 170);
        expect(leftovers(), id).toBe(0);
        pointer('pointerup', el(id), 50, 170);
      }
      pointer('pointerdown', el('a1'), 10, 110, { button: 2 });
      pointer('pointermove', el('a1'), 50, 170);
      pointer('pointerdown', el('a1'), 10, 110, { ctrlKey: true });
      pointer('pointermove', el('a1'), 50, 170);
      pointer('pointerdown', el('a1'), 10, 110, { isPrimary: false });
      pointer('pointermove', el('a1'), 50, 170);
      expect(leftovers()).toBe(0);
    });

    it.each(['audio', 'video'])('HH-EL1: pressing and moving on a native <%s> scrubs, it starts no drag', (tag) => {
      el('a1').insertAdjacentHTML('beforeend', `<${tag} id="media" controls></${tag}>`);
      pointer('pointerdown', el('media'), 10, 110);
      under = () => el('b');
      pointer('pointermove', el('media'), 60, 110);
      expect(leftovers()).toBe(0);
      expect(events).toEqual([]);
      pointer('pointerup', el('media'), 60, 110);
    });

    it('a link in the item drags, and the click after the drop is swallowed', () => {
      dragTo(el('link'), el('a'), 10, 170);
      drop(el('link'), 10, 170);
      expect(ids('a')).toEqual(['a2', 'a1', 'a3']);
      const click = new MouseEvent('click', { bubbles: true, cancelable: true, detail: 1 });
      el('link').dispatchEvent(click);
      expect(click.defaultPrevented).toBe(true);
    });

    it('a handle that is a button drags; with handles, the rest of the item does not', () => {
      under = () => el('h');
      pointer('pointerdown', el('h1-handle'), 510, 110);
      pointer('pointermove', el('h1-handle'), 590, 110);
      expect(leftovers()).toBe(2);
      drop(el('h1-handle'), 590, 110);
      expect(ids('h')).toEqual(['h2', 'h1']);
      expect(events.find((e) => e.type === 'moved')!.detail).toMatchObject({ scope: 'chips', listId: 'h', index: 1 });
    });

    it('reads the x axis on horizontal lists, mirrored in rtl', () => {
      expect(axisOf(el('h'))).toBe('x');
      expect(axisOf(el('a'))).toBe('y');
      el('h').setAttribute('dir', 'rtl');
      vi.spyOn(window, 'getComputedStyle').mockImplementation(() => ({ direction: 'rtl', overflowX: 'visible', overflowY: 'visible', marginTop: '0px', marginRight: '0px', marginBottom: '0px', marginLeft: '0px' }) as CSSStyleDeclaration);
      under = () => el('h');
      pointer('pointerdown', el('h2-handle'), 560, 110);
      pointer('pointermove', el('h2-handle'), 590, 110); // right of h1's middle: before it when rtl
      drop(el('h2-handle'), 590, 110);
      expect(ids('h')).toEqual(['h2', 'h1']);
    });

    it('ignores a disabled list and lists of another scope under the pointer', () => {
      dragTo(el('a1'), el('off'), 10, 500);
      expect(el('off').classList.contains('is-drop-target')).toBe(false);
      under = () => el('h');
      pointer('pointermove', el('a1'), 10, 501);
      expect(el('h').classList.contains('is-drop-target')).toBe(false);
      drop(el('a1'), 10, 501);
      expect(ids('a')).toEqual(['a1', 'a2', 'a3']);
      expect(ids('off')).toEqual(['off1']);
    });

    it('stops native gestures while a drag can start, and lets them be otherwise', () => {
      const fire = (type: string): boolean => {
        const event = new Event(type, { bubbles: true, cancelable: true });
        el('a1').dispatchEvent(event);
        return event.defaultPrevented;
      };
      expect(fire('dragstart')).toBe(false);
      expect(fire('selectstart')).toBe(false);
      expect(fire('contextmenu')).toBe(false);
      pointer('pointerdown', el('a1'), 10, 110);
      expect(fire('dragstart')).toBe(true);
      expect(fire('selectstart')).toBe(true);
      expect(fire('contextmenu')).toBe(false); // a mouse keeps its menu until it drags
      under = () => el('a');
      pointer('pointermove', el('a1'), 10, 170);
      expect(fire('contextmenu')).toBe(true);
    });

    it('a second pointer changes nothing', () => {
      dragTo(el('a1'), el('b'), 210, 90);
      pointer('pointerdown', el('a2'), 10, 150, { pointerId: 2, isPrimary: false });
      pointer('pointermove', el('a2'), 400, 400, { pointerId: 2 });
      pointer('pointerup', el('a2'), 400, 400, { pointerId: 2 });
      pointer('pointercancel', el('a2'), 400, 400, { pointerId: 2 });
      expect(leftovers()).toBe(2);
      drop(el('a1'), 210, 90);
      expect(ids('b')).toEqual(['a1', 'b1']);
    });
  });

  describe('touch', () => {
    const touch = { pointerType: 'touch' };

    /** Whether a `touchmove` is refused (no scrolling). */
    const touchMove = (target: Element): boolean => {
      const event = new Event('touchmove', { bubbles: true, cancelable: true });
      target.dispatchEvent(event);
      return event.defaultPrevented;
    };

    it('whole item: a long press starts the drag, then scrolling is refused', () => {
      vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
      under = () => el('a');
      pointer('pointerdown', el('a1'), 10, 110, touch);
      expect(touchMove(el('a1'))).toBe(false);
      expect(leftovers()).toBe(0);
      vi.advanceTimersByTime(LONG_PRESS_MS - 1);
      expect(leftovers()).toBe(0);
      vi.advanceTimersByTime(1);
      expect(leftovers()).toBe(2);
      expect(touchMove(el('a1'))).toBe(true);
      under = () => el('b');
      pointer('pointermove', el('a1'), 210, 90, touch);
      drop(el('a1'), 210, 90);
      expect(ids('b')).toEqual(['a1', 'b1']);
      expect(touchMove(el('a1'))).toBe(false);
    });

    it('whole item: a finger that moves first scrolls, and no drag follows', () => {
      vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
      pointer('pointerdown', el('a1'), 10, 110, touch);
      pointer('pointermove', el('a1'), 10, 125, touch);
      vi.advanceTimersByTime(LONG_PRESS_MS * 2);
      expect(leftovers()).toBe(0);
      expect(touchMove(el('a1'))).toBe(false);
      expect(events).toEqual([]);
    });

    it('whole item: the browser taking the gesture for a scroll ends it', () => {
      vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
      pointer('pointerdown', el('a1'), 10, 110, touch);
      pointer('pointercancel', el('a1'), 10, 110, touch);
      vi.advanceTimersByTime(LONG_PRESS_MS * 2);
      expect(leftovers()).toBe(0);
    });

    it('whole item: a tap is a tap', () => {
      vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
      pointer('pointerdown', el('a1'), 10, 110, touch);
      pointer('pointerup', el('a1'), 10, 110, touch);
      vi.advanceTimersByTime(LONG_PRESS_MS * 2);
      expect(leftovers()).toBe(0);
      const click = new MouseEvent('click', { bubbles: true, cancelable: true, detail: 1 });
      el('a1').dispatchEvent(click);
      expect(click.defaultPrevented).toBe(false);
    });

    it('a handle drags at once, and the long-press menu is stopped', () => {
      under = () => el('h');
      pointer('pointerdown', el('h1-handle'), 510, 110, touch);
      const menu = new Event('contextmenu', { bubbles: true, cancelable: true });
      el('h1-handle').dispatchEvent(menu);
      expect(menu.defaultPrevented).toBe(true);
      pointer('pointermove', el('h1-handle'), 590, 110, touch);
      expect(leftovers()).toBe(2);
      expect(touchMove(el('h1-handle'))).toBe(true);
      drop(el('h1-handle'), 590, 110);
    });
  });

  describe('auto-scroll', () => {
    const frames: FrameRequestCallback[] = [];

    beforeEach(() => {
      frames.length = 0;
      vi.spyOn(window, 'requestAnimationFrame').mockImplementation((cb) => {
        frames.push(cb);
        return frames.length;
      });
    });

    const frame = (at: number): void => {
      const cb = frames.shift();
      cb?.(at);
    };

    const scrollable = (scroller: HTMLElement, top: number, scrollBy: ReturnType<typeof vi.fn>): void => {
      rects.set(scroller, rect(0, 0, 400, 400));
      fakeSizes(scroller);
      scroller.scrollTop = top;
      Element.prototype.scrollBy = scrollBy as unknown as typeof Element.prototype.scrollBy;
      const real = window.getComputedStyle.bind(window);
      vi.spyOn(window, 'getComputedStyle').mockImplementation((target) => {
        const style = real(target);
        return target === scroller ? ({ ...style, overflowX: 'visible', overflowY: 'auto', direction: 'ltr' } as CSSStyleDeclaration) : style;
      });
    };

    it('scrolls the container near its bottom edge, not in the middle, and stops with the drag', () => {
      const scrollBy = vi.fn();
      const original = Element.prototype.scrollBy;
      scrollable(el('zone-a'), 0, scrollBy);
      dragTo(el('a1'), el('a'), 10, 200);
      const start = performance.now();
      frame(start + 16);
      expect(scrollBy).not.toHaveBeenCalled();

      under = () => el('a');
      pointer('pointermove', el('a1'), 200, 395);
      frame(start + 32);
      expect(scrollBy).toHaveBeenCalledTimes(1);
      expect(scrollBy.mock.instances[0]).toBe(el('zone-a'));
      const options = scrollBy.mock.calls[0]![0] as ScrollToOptions;
      expect(options.top!).toBeGreaterThan(0);
      expect(options.behavior).toBe('instant');

      drop(el('a1'), 200, 395);
      const before = scrollBy.mock.calls.length;
      frame(start + 48);
      expect(scrollBy.mock.calls.length).toBe(before);
      Element.prototype.scrollBy = original;
    });

    it('still scrolls the container of the placeholder when the pointer is just past its edge, not when far away', () => {
      const scrollBy = vi.fn();
      const original = Element.prototype.scrollBy;
      scrollable(el('zone-a'), 0, scrollBy);
      dragTo(el('a1'), el('a'), 10, 200);
      const start = performance.now();
      under = () => document.body; // a padded page: the finger is outside the scroller
      pointer('pointermove', el('a1'), 200, 420);
      frame(start + 16);
      expect(scrollBy).toHaveBeenCalledTimes(1);
      pointer('pointermove', el('a1'), 200, 600);
      frame(start + 32);
      expect(scrollBy).toHaveBeenCalledTimes(1);
      drop(el('a1'), 200, 600);
      Element.prototype.scrollBy = original;
    });

    it('scrolls up near the top edge only while it can', () => {
      const scrollBy = vi.fn();
      const original = Element.prototype.scrollBy;
      scrollable(el('zone-a'), 0, scrollBy);
      dragTo(el('a1'), el('a'), 200, 5);
      const start = performance.now();
      frame(start + 16);
      expect(scrollBy.mock.calls.filter(([o]) => (o as ScrollToOptions).top! < 0)).toHaveLength(0);
      el('zone-a').scrollTop = 50;
      frame(start + 32);
      expect((scrollBy.mock.calls[scrollBy.mock.calls.length - 1]![0] as ScrollToOptions).top!).toBeLessThan(0);
      drop(el('a1'), 200, 5);
      Element.prototype.scrollBy = original;
    });
  });

  describe('keyboard', () => {
    it('Alt+Down and Alt+Up move within the list, keep focus and announce', () => {
      el('a1').focus();
      const event = alt(el('a1'), 'ArrowDown');
      expect(event.defaultPrevented).toBe(true);
      expect(ids('a')).toEqual(['a2', 'a1', 'a3']);
      expect(document.activeElement).toBe(el('a1'));
      expect(events.map((e) => e.type)).toEqual(['move', 'moved']);
      expect(events[0]!.detail).toMatchObject({ scope: 'cards', id: 'a1', listId: 'a', fromListId: 'a', index: 1, fromIndex: 0, source: 'keyboard' });
      expect(live()).toContain('2/3');
      alt(el('a1'), 'ArrowUp');
      expect(ids('a')).toEqual(['a1', 'a2', 'a3']);
      expect(document.activeElement).toBe(el('a1'));
    });

    it('keeps focus on a control inside the item', () => {
      el('btn').focus();
      alt(el('btn'), 'ArrowDown');
      expect(ids('a')).toEqual(['a2', 'a1', 'a3']);
      expect(document.activeElement).toBe(el('btn'));
    });

    it('Alt+Right and Alt+Left go to the next and previous list, clamping the position', () => {
      alt(el('a3'), 'ArrowRight');
      expect(ids('b')).toEqual(['b1', 'a3']);
      expect(events[0]!.detail).toMatchObject({ listId: 'b', fromListId: 'a', index: 1, fromIndex: 2 });
      expect(live()).toContain('moved to b, position 2 of 2');
      alt(el('a3'), 'ArrowRight');
      expect(ids('c')).toEqual(['a3']);
      alt(el('a3'), 'ArrowLeft');
      expect(ids('b')).toEqual(['a3', 'b1']);
    });

    it('skips disabled lists, and says so at the edges without moving', () => {
      el('b1').remove();
      alt(el('a1'), 'ArrowRight');
      alt(el('a1'), 'ArrowRight');
      expect(ids('c')).toEqual(['a1']);
      events.length = 0;
      alt(el('a1'), 'ArrowRight');
      expect(ids('c')).toEqual(['a1']);
      expect(events).toEqual([]);
      expect(live()).toContain('cannot move further');

      alt(el('a2'), 'ArrowUp');
      expect(ids('a')).toEqual(['a2', 'a3']);
      expect(live()).toContain('cannot move further');
      alt(el('a2'), 'ArrowLeft');
      expect(ids('a')).toEqual(['a2', 'a3']);
    });

    it('on an x list the keys swap: Left and Right move within it, in rtl mirrored', () => {
      alt(el('h1-handle'), 'ArrowRight');
      expect(ids('h')).toEqual(['h2', 'h1']);
      alt(el('h1-handle'), 'ArrowDown'); // no other list in this scope
      expect(ids('h')).toEqual(['h2', 'h1']);
      expect(live()).toContain('cannot move further');
      vi.spyOn(window, 'getComputedStyle').mockImplementation(() => ({ direction: 'rtl' }) as CSSStyleDeclaration);
      alt(el('h1-handle'), 'ArrowRight');
      expect(ids('h')).toEqual(['h1', 'h2']);
    });

    it('a listener refusing move leaves the DOM and focus, and announces', () => {
      listen('nowo-ui:sortable:move', (e) => e.preventDefault());
      el('a1').focus();
      alt(el('a1'), 'ArrowDown');
      expect(ids('a')).toEqual(['a1', 'a2', 'a3']);
      expect(document.activeElement).toBe(el('a1'));
      expect(events.map((e) => e.type)).toEqual(['move']);
      expect(live()).toContain('cannot be moved there');
    });

    it('leaves text fields, other keys, other modifiers, disabled items and handled events alone', () => {
      expect(alt(el('field'), 'ArrowUp').defaultPrevented).toBe(false);
      expect(alt(el('a1'), 'a').defaultPrevented).toBe(false);
      expect(alt(el('a1'), 'ArrowDown', { shiftKey: true }).defaultPrevented).toBe(false);
      expect(alt(el('a1'), 'ArrowDown', { altKey: false }).defaultPrevented).toBe(false);
      expect(alt(el('off1'), 'ArrowUp').defaultPrevented).toBe(false);
      const stop = (e: Event): void => e.preventDefault();
      el('a1').addEventListener('keydown', stop);
      alt(el('a1'), 'ArrowDown');
      el('a1').removeEventListener('keydown', stop);
      expect(ids('a')).toEqual(['a1', 'a2', 'a3']);
      expect(events).toEqual([]);
    });
  });

  describe('revert', () => {
    const revertOf = (n = -1): (() => boolean) => (events.filter((e) => e.type === 'moved').slice(n)[0]!.detail.revert as () => boolean);

    it('puts the item back after a failed write, once, keeps focus and reports it', () => {
      el('a1').focus();
      alt(el('a1'), 'ArrowRight');
      expect(ids('b')).toEqual(['a1', 'b1']);
      expect(revertOf()()).toBe(true);
      expect(ids('a')).toEqual(['a1', 'a2', 'a3']);
      expect(ids('b')).toEqual(['b1']);
      expect(document.activeElement).toBe(el('a1'));
      expect(events[events.length - 1]).toMatchObject({ type: 'reverted', detail: { id: 'a1', listId: 'b', fromListId: 'a', fromIndex: 0 } });
      expect(live()).toContain('undone');
      expect(revertOf()()).toBe(false);
    });

    it('works after a pointer move, in an empty origin list too', () => {
      el('b1').remove();
      el('b').append(el('a1'));
      dragTo(el('a1'), el('c'), 410, 110);
      drop(el('a1'), 410, 110);
      expect(ids('c')).toEqual(['a1']);
      expect(revertOf()()).toBe(true);
      expect(ids('b')).toEqual(['a1']);
      expect(ids('c')).toEqual([]);
    });

    it('a late revert of an older move does not undo a newer one', () => {
      alt(el('a1'), 'ArrowDown');
      const older = revertOf();
      alt(el('a1'), 'ArrowDown');
      expect(older()).toBe(false);
      expect(ids('a')).toEqual(['a2', 'a3', 'a1']);
      expect(revertOf()()).toBe(true);
      expect(ids('a')).toEqual(['a2', 'a1', 'a3']);
    });

    it('falls back to the index when the old neighbour left, and refuses when the markup is gone', () => {
      alt(el('a1'), 'ArrowRight');
      el('a2').remove();
      expect(revertOf()()).toBe(true);
      expect(ids('a')).toEqual(['a1', 'a3']);

      alt(el('a1'), 'ArrowRight');
      el('a').remove();
      expect(revertOf()()).toBe(false);
      expect(el('b').contains(el('a1'))).toBe(true);
    });
  });

  describe('forms as items (GT-L3)', () => {
    it('controls named like DOM members do not hide the form', () => {
      el('b').innerHTML = `
        <form id="f1" data-nowo-ui-sortable-item="f1" tabindex="0">
          ${['closest', 'getAttribute', 'hasAttribute', 'matches', 'parentElement', 'nextSibling', 'isConnected', 'classList', 'style', 'remove', 'focus', 'textContent', 'tagName', 'cloneNode', 'getBoundingClientRect', 'dispatchEvent', 'contains', 'attributes']
            .map((name) => `<input type="hidden" name="${name}">`)
            .join('')}
          Form item
        </form>
        <li id="b2" data-nowo-ui-sortable-item="b2" tabindex="0">B2</li>`;
      alt(el('f1'), 'ArrowDown');
      expect(ids('b')).toEqual(['b2', 'f1']);
      expect(events[events.length - 1]).toMatchObject({ type: 'moved', detail: { id: 'f1' } });

      rects.set(el('f1'), rect(200, 100, 100, 40));
      dragTo(el('f1'), el('c'), 410, 110);
      const ghost = document.querySelector('.nowo-ui-sortable__ghost')!;
      expect(ghost.querySelector('[name]')).toBeNull();
      drop(el('f1'), 410, 110);
      expect(ids('c')).toEqual(['f1']);
    });

    it('the copy drops scripts, frames and hooks that could act', () => {
      el('b1').innerHTML = '<iframe></iframe><script type="text/plain">1</script><span id="inner" data-controller="x" data-action="click->x#y" data-x-target="t" onclick="1" data-keep="yes">t</span>';
      dragTo(el('b1'), el('c'), 410, 110);
      const ghost = document.querySelector('.nowo-ui-sortable__ghost')!;
      expect(ghost.querySelector('iframe, script')).toBeNull();
      const span = ghost.querySelector('span')!;
      expect(span.getAttributeNames()).toEqual(['data-keep']);
      expect(document.querySelectorAll('#inner')).toHaveLength(1);
    });
  });

  describe('a gesture always ends (HH-M1)', () => {
    const touch = { pointerType: 'touch' };
    const scrollRefused = (target: Element = document.body): boolean => {
      const event = new Event('touchmove', { bubbles: true, cancelable: true });
      target.dispatchEvent(event);
      return event.defaultPrevented;
    };

    it.each(['pointerup', 'pointermove', 'pointercancel'])('%s stopped on the way to the document still reaches it', (type) => {
      const stop = (e: Event): void => e.stopPropagation();
      el('host' as string)?.addEventListener(type, stop);
      document.body.addEventListener(type, stop);
      dragTo(el('a1'), el('b'), 210, 90);
      if (type === 'pointermove') {
        expect(leftovers()).toBe(2);
      }
      if (type === 'pointercancel') {
        pointer('pointercancel', el('a1'), 210, 90);
      } else {
        drop(el('a1'), 210, 90);
      }
      document.body.removeEventListener(type, stop);
      expect(leftovers()).toBe(0);
      expect(scrollRefused()).toBe(false);
    });

    it('a stopped pointerup: the drop commits and the page scrolls again', () => {
      const stop = (e: Event): void => e.stopPropagation();
      el('a1').addEventListener('pointerup', stop);
      pointer('pointerdown', el('a1'), 10, 110, touch);
      vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
      pointer('pointerup', el('a1'), 10, 110, touch);
      vi.useRealTimers();
      expect(leftovers()).toBe(0);
      expect(scrollRefused()).toBe(false);
      el('a1').removeEventListener('pointerup', stop);
    });

    it('a tap whose pointerup is stopped starts no drag after the long press time', () => {
      vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
      const stop = (e: Event): void => e.stopPropagation();
      el('a1').addEventListener('pointerup', stop);
      pointer('pointerdown', el('a1'), 10, 110, touch);
      pointer('pointerup', el('a1'), 10, 110, touch);
      vi.advanceTimersByTime(LONG_PRESS_MS * 2);
      expect(leftovers()).toBe(0);
      expect(events).toEqual([]);
      expect(scrollRefused()).toBe(false);
    });

    it('the last finger lifted with no pointerup at all ends a pending press and a drag', () => {
      vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
      const lift = (touches: unknown[]): void => {
        const event = new Event('touchend', { bubbles: true });
        Object.defineProperty(event, 'touches', { value: touches });
        window.dispatchEvent(event);
      };
      pointer('pointerdown', el('a1'), 10, 110, touch);
      lift([]);
      vi.advanceTimersByTime(LONG_PRESS_MS * 2);
      expect(leftovers()).toBe(0);

      under = () => el('b');
      pointer('pointerdown', el('h1-handle'), 510, 110, touch);
      under = () => el('h');
      pointer('pointermove', el('h1-handle'), 590, 110, touch);
      expect(leftovers()).toBe(2);
      lift([{}]); // another finger is still down
      vi.advanceTimersByTime(1000);
      expect(leftovers()).toBe(2);
      lift([]);
      expect(leftovers()).toBe(2); // the pointerup may still be on its way
      vi.advanceTimersByTime(1000);
      expect(leftovers()).toBe(0);
      expect(events[events.length - 1]).toMatchObject({ type: 'cancel', detail: { reason: 'lost' } });
      expect(scrollRefused()).toBe(false);
    });

    it('a pointerup right before touchend is a normal drop, not a cancel', () => {
      vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
      under = () => el('h');
      pointer('pointerdown', el('h1-handle'), 510, 110, touch);
      pointer('pointermove', el('h1-handle'), 590, 110, touch);
      const event = new Event('touchend', { bubbles: true });
      Object.defineProperty(event, 'touches', { value: [] });
      window.dispatchEvent(event); // an engine that sends them the other way round
      drop(el('h1-handle'), 590, 110);
      vi.advanceTimersByTime(1000);
      expect(events[events.length - 1]!.type).toBe('moved');
    });

    it('a mouse moving with no button down was released elsewhere: the drag cancels', () => {
      dragTo(el('a1'), el('b'), 210, 90);
      pointer('pointermove', el('a1'), 220, 90, { buttons: 0 });
      expect(leftovers()).toBe(0);
      expect(events[events.length - 1]).toMatchObject({ type: 'cancel', detail: { reason: 'lost' } });
      const click = new MouseEvent('click', { bubbles: true, cancelable: true, detail: 1 });
      el('link').dispatchEvent(click);
      expect(click.defaultPrevented).toBe(false);
      pointer('pointerdown', el('a1'), 10, 110);
      pointer('pointermove', el('a1'), 10, 170, { buttons: 0 });
      expect(leftovers()).toBe(0);
    });

    it('a new primary press recovers from a release that never came, and starts its own gesture', () => {
      dragTo(el('a1'), el('b'), 210, 90);
      expect(leftovers()).toBe(2);
      pointer('pointerdown', el('a2'), 10, 150, { pointerId: 9 });
      expect(leftovers()).toBe(0);
      expect(events[events.length - 1]).toMatchObject({ type: 'cancel', detail: { id: 'a1', reason: 'lost' } });
      under = () => el('b');
      pointer('pointermove', el('a2'), 210, 90, { pointerId: 9 });
      expect(leftovers()).toBe(2);
      drop(el('a2'), 210, 90);
      pointer('pointerup', el('a2'), 210, 90, { pointerId: 9 });
      expect(ids('b')).toEqual(['a2', 'b1']);
    });

    it('a mouse press does not end a touch drag, or the other way round', () => {
      under = () => el('h');
      pointer('pointerdown', el('h1-handle'), 510, 110, touch);
      pointer('pointermove', el('h1-handle'), 590, 110, touch);
      pointer('pointerdown', el('a2'), 10, 150, { pointerId: 9 });
      expect(leftovers()).toBe(2);
      drop(el('h1-handle'), 590, 110);
      pointer('pointerdown', el('a1'), 10, 110, { pointerId: 3 });
      pointer('pointerdown', el('a2'), 10, 150, { ...touch, pointerId: 4 });
      pointer('pointerup', el('a1'), 10, 110, { pointerId: 3 });
      expect(leftovers()).toBe(0);
    });

    it('a pending press is dropped by a new press of its type', () => {
      vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
      pointer('pointerdown', el('a1'), 10, 110, touch);
      pointer('pointerdown', document.body, 10, 110, { ...touch, pointerId: 2 });
      vi.advanceTimersByTime(LONG_PRESS_MS * 2);
      expect(leftovers()).toBe(0);
      expect(scrollRefused()).toBe(false);
    });

    it('an error during a drag ends it, and the page scrolls again', () => {
      const errors = vi.fn();
      window.addEventListener('error', errors);
      vi.spyOn(console, 'error').mockImplementation(() => undefined);
      dragTo(el('a1'), el('b'), 210, 90);
      under = () => {
        throw new Error('boom');
      };
      try {
        pointer('pointermove', el('a1'), 215, 90);
      } catch {
        // Depends on how the DOM reports a listener's error.
      }
      window.removeEventListener('error', errors);
      expect(leftovers()).toBe(0);
      expect(events[events.length - 1]).toMatchObject({ type: 'cancel', detail: { reason: 'lost' } });
      expect(scrollRefused()).toBe(false);
    });

    it('Escape during a press ends it: nothing starts on what Escape closes', () => {
      vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
      pointer('pointerdown', el('a1'), 10, 110, touch);
      const escape = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
      el('a1').dispatchEvent(escape);
      expect(escape.defaultPrevented).toBe(false);
      vi.advanceTimersByTime(LONG_PRESS_MS * 2);
      expect(leftovers()).toBe(0);
    });

    it.each(['elementFromPoint', 'removeEventListener', 'addEventListener', 'createElement', 'documentElement', 'scrollingElement', 'body', 'activeElement', 'visibilityState', 'implementation', 'importNode'])(
      '<img name="%s"> in the page does not stop anything (HH-L4)',
      (name) => {
        vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
        const img = document.createElement('img');
        Object.defineProperty(document, name, { configurable: true, value: img });
        try {
          pointer('pointerdown', el('a1'), 10, 110, touch);
          vi.advanceTimersByTime(LONG_PRESS_MS);
          expect(leftovers()).toBe(2);
          expect(scrollRefused(el('a1'))).toBe(true);
          under = () => el('b');
          pointer('pointermove', el('a1'), 210, 90, touch);
          pointer('pointerup', el('a1'), 210, 90, touch);
          expect(ids('b')).toEqual(['a1', 'b1']);
          expect(events[events.length - 1]!.type).toBe('moved');
          expect(scrollRefused(el('a1'))).toBe(false);
          // The keyboard route across lists.
          alt(el('a2'), 'ArrowRight');
          expect(ids('b')).toContain('a2');
        } finally {
          delete (document as unknown as Record<string, unknown>)[name];
        }
      },
    );

    it('a form used as a scroll container still auto-scrolls (HH-L4)', () => {
      el('zone-a').remove();
      document.querySelector('[data-testid="host"]')!.insertAdjacentHTML(
        'beforeend',
        `<div data-nowo-ui-sortable="forms"><form id="scroller" data-nowo-ui-sortable-list="f">
          <input name="scrollTop"><input name="scrollHeight"><input name="clientHeight"><input name="scrollLeft"><input name="scrollWidth"><input name="clientWidth">
          <div id="f1" data-nowo-ui-sortable-item="f1">f</div>
        </form></div>`,
      );
      rects.set(el('scroller'), rect(0, 0, 400, 400));
      fakeSizes(el('scroller'));
      const real = window.getComputedStyle.bind(window);
      vi.spyOn(window, 'getComputedStyle').mockImplementation((target) => (target === el('scroller') ? ({ ...real(target), overflowX: 'visible', overflowY: 'auto', direction: 'ltr' } as CSSStyleDeclaration) : real(target)));
      const scrollBy = vi.fn();
      const original = Element.prototype.scrollBy;
      Element.prototype.scrollBy = scrollBy as unknown as typeof Element.prototype.scrollBy;
      const frames: FrameRequestCallback[] = [];
      vi.spyOn(window, 'requestAnimationFrame').mockImplementation((cb) => frames.push(cb));
      under = () => el('scroller');
      pointer('pointerdown', el('f1'), 200, 200);
      pointer('pointermove', el('f1'), 200, 395);
      frames.shift()!(performance.now() + 16);
      expect(scrollBy).toHaveBeenCalled();
      drop(el('f1'), 200, 395);
      Element.prototype.scrollBy = original;
    });
  });

  describe('only lists the user can reach (HH-M2)', () => {
    it('a keyboard move skips a hidden list and goes on to the next one', () => {
      el('b').setAttribute('hidden', '');
      el('a1').focus();
      alt(el('a1'), 'ArrowRight');
      expect(ids('b')).toEqual(['b1']);
      expect(ids('c')).toEqual(['a1']);
      expect(document.activeElement).toBe(el('a1'));
    });

    it('with no list left, it says so and writes nothing', () => {
      el('b').setAttribute('hidden', '');
      el('c').closest('div')!.setAttribute('data-keep', '');
      el('c').setAttribute('hidden', '');
      el('a1').focus();
      alt(el('a1'), 'ArrowRight');
      expect(ids('a')).toEqual(['a1', 'a2', 'a3']);
      expect(events).toEqual([]);
      expect(live()).toContain('cannot move further');
      expect(document.activeElement).toBe(el('a1'));
    });

    it('skips an inert list, and one inside an inert part', () => {
      el('b').setAttribute('inert', '');
      alt(el('a1'), 'ArrowRight');
      expect(ids('b')).toEqual(['b1']);
      expect(ids('c')).toEqual(['a1']);
      el('b').removeAttribute('inert');
      const wrapper = document.createElement('div');
      wrapper.setAttribute('inert', '');
      el('b').before(wrapper);
      wrapper.append(el('b'));
      alt(el('a1'), 'ArrowLeft');
      expect(ids('a')).toContain('a1');
      expect(ids('b')).toEqual(['b1']);
    });

    it('without checkVisibility, it falls back to boxes and the visibility property', () => {
      delete (Element.prototype as unknown as { checkVisibility?: unknown }).checkVisibility;
      const spy = vi.spyOn(Element.prototype, 'getClientRects').mockImplementation(function (this: Element) {
        return (this.id === 'b' ? [] : [rect(0, 0, 1, 1)]) as unknown as DOMRectList;
      });
      alt(el('a1'), 'ArrowRight');
      expect(ids('b')).toEqual(['b1']);
      expect(ids('c')).toEqual(['a1']);
      spy.mockRestore();
    });

    it('a zone does not stand for a hidden list', () => {
      el('a').setAttribute('hidden', '');
      dragTo(el('b1'), el('head-a'), 10, 400);
      expect(el('a').classList.contains('is-drop-target')).toBe(false);
      drop(el('b1'), 10, 400);
      expect(ids('a')).toEqual(['a1', 'a2', 'a3']);
    });

    it('the module exports the check, modal rule included where the DOM can tell', () => {
      expect(isReachable(el('b'), el('a1'))).toBe(true);
      const closestSpy = vi.spyOn(Element.prototype, 'closest').mockImplementation(function (this: Element, selector: string) {
        if (selector === 'dialog:modal') {
          return this.id === 'a1' ? el('board') : null;
        }
        return null;
      });
      expect(isReachable(el('b'), el('a1'))).toBe(false);
      closestSpy.mockRestore();
    });
  });

  describe('the copy runs nothing (HH-L1, HH-L2)', () => {
    const ghostOf = (): HTMLElement => document.querySelector<HTMLElement>('.nowo-ui-sortable__ghost')!;

    it('a custom element in the item does not run again, and its look stays as a plain span', () => {
      let connected = 0;
      if (!customElements.get('x-sortable-probe')) {
        customElements.define(
          'x-sortable-probe',
          class extends HTMLElement {
            connectedCallback(): void {
              connected++;
            }
          },
        );
      }
      el('b1').insertAdjacentHTML('beforeend', '<x-sortable-probe class="kept"><b>text</b></x-sortable-probe>');
      const before = connected;
      dragTo(el('b1'), el('c'), 410, 110);
      expect(connected).toBe(before);
      expect(ghostOf().querySelector('x-sortable-probe')).toBeNull();
      expect(ghostOf().querySelector('span.kept b')!.textContent).toBe('text');
    });

    it('an item that is itself a custom element or media becomes a plain box', () => {
      el('b').insertAdjacentHTML('beforeend', '<video id="v1" data-nowo-ui-sortable-item="v1" src="/x.mp4" autoplay><source src="/y.mp4"></video><x-other id="x1" data-nowo-ui-sortable-item="x1" class="kept">z</x-other>');
      for (const id of ['v1', 'x1']) {
        dragTo(el(id), el('c'), 410, 110);
        expect(ghostOf().tagName).toBe('DIV');
        expect(ghostOf().querySelector('source')).toBeNull();
        expect(ghostOf().hasAttribute('src')).toBe(false);
        pointer('pointercancel', el(id), 0, 0);
      }
    });

    it('strips the hooks other libraries look for', () => {
      el('b1').innerHTML = '<span id="hooked" x-init="a" x-data="b" hx-get="/c" data-hx-get="/d" command="e" commandfor="f" formaction="/g" ping="/h" data-bs-toggle="i" data-turbo-frame="j" data-live-id="k" is="l" data-keep="yes" class="kept">t</span>';
      dragTo(el('b1'), el('c'), 410, 110);
      expect(ghostOf().querySelector('span')!.getAttributeNames().sort()).toEqual(['class', 'data-keep']);
    });

    it('declarations go through the CSSOM: the attribute itself never reaches the copy', () => {
      el('b1').style.setProperty('--card-accent', 'red');
      el('b1').insertAdjacentHTML('beforeend', '<i id="styled"></i>');
      el('styled').style.setProperty('color', 'blue', 'important');
      dragTo(el('b1'), el('c'), 410, 110);
      expect(ghostOf().style.getPropertyValue('--card-accent')).toBe('red');
      const copy = ghostOf().querySelector('i')!;
      expect(copy.style.getPropertyValue('color')).toBe('blue');
      expect(copy.style.getPropertyPriority('color')).toBe('important');
    });

    it('a style attribute with no applied declaration leaves the copy without it', () => {
      el('b1').insertAdjacentHTML('beforeend', '<i id="blocked" style="position:fixed;background:url(/exfil.png)"></i>');
      // What a browser reports for an attribute its CSP blocked: the attribute is there, the declarations are not.
      const real = Object.getOwnPropertyDescriptor(HTMLElement.prototype, 'style')!;
      Object.defineProperty(HTMLElement.prototype, 'style', {
        configurable: true,
        get(this: HTMLElement) {
          const style = real.get!.call(this) as CSSStyleDeclaration;
          return this.id === 'blocked' ? ({ length: 0, item: () => '' } as unknown as CSSStyleDeclaration) : style;
        },
      });
      try {
        dragTo(el('b1'), el('c'), 410, 110);
        const copy = ghostOf().querySelector('i')!;
        expect(copy.getAttribute('style') ?? '').toBe('');
      } finally {
        Object.defineProperty(HTMLElement.prototype, 'style', real);
      }
    });
  });

  describe('planted attributes (HH-L3)', () => {
    beforeEach(() => {
      el('a2').insertAdjacentHTML('beforeend', '<span id="planted" data-nowo-ui-sortable-item="planted" tabindex="0">p</span>');
    });

    it('an item planted inside a card is not counted, so the positions stay true', () => {
      expect(ids('a')).toEqual(['a1', 'a2', 'a3']);
      alt(el('a3'), 'ArrowUp');
      expect(events[0]!.detail).toMatchObject({ id: 'a3', index: 1, fromIndex: 2 });
      expect(el('a').children[1]!.id).toBe('a3');
    });

    it('nothing lands inside a card', () => {
      dragTo(el('a1'), el('a'), 10, 170);
      drop(el('a1'), 10, 170);
      expect(el('a1').parentElement!.id).toBe('a');
      alt(el('a1'), 'ArrowDown');
      expect(el('a1').parentElement!.id).toBe('a');
    });

    it('pressing or focusing it acts on the real card', () => {
      dragTo(el('planted'), el('b'), 210, 90);
      drop(el('planted'), 210, 90);
      expect(events.find((e) => e.type === 'moved')!.detail.id).toBe('a2');
      expect(ids('b')).toEqual(['a2', 'b1']);
      events.length = 0;
      alt(el('planted'), 'ArrowDown');
      expect(events[0]!.detail.id).toBe('a2');
    });

    it('a list planted in the dragged item is never a destination, by pointer or key', () => {
      el('a1').insertAdjacentHTML('beforeend', '<ul id="own" data-nowo-ui-sortable-list="own"></ul>');
      dragTo(el('a1'), el('own'), 10, 110);
      expect(el('own').classList.contains('is-drop-target')).toBe(false);
      drop(el('a1'), 10, 110);
      expect(el('own').contains(el('a1'))).toBe(false);
      alt(el('a1'), 'ArrowLeft');
      expect(el('own').contains(el('a1'))).toBe(false);
    });
  });

  describe('the click guard (HH-L5)', () => {
    const bareClick = (target: Element): boolean => {
      const event = new MouseEvent('click', { bubbles: true, cancelable: true, detail: 0 });
      target.dispatchEvent(event);
      return event.defaultPrevented;
    };
    const pointerClick = (target: Element): boolean => {
      const event = new MouseEvent('click', { bubbles: true, cancelable: true, detail: 1 });
      target.dispatchEvent(event);
      return event.defaultPrevented;
    };

    it('Enter on a button or a scripted click after Escape is not swallowed', () => {
      dragTo(el('a1'), el('b'), 210, 90);
      el('a1').dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }));
      expect(bareClick(el('btn'))).toBe(false);
      expect(pointerClick(el('btn'))).toBe(true); // the release click is still swallowed
    });

    it('button.click() in a moved listener works after a pointer drop', () => {
      const clicked = vi.fn();
      el('add-a').addEventListener('click', clicked);
      listen('nowo-ui:sortable:moved', () => el('add-a').click());
      dragTo(el('a1'), el('a'), 10, 170);
      drop(el('a1'), 10, 170);
      expect(clicked).toHaveBeenCalledTimes(1);
    });

    it.each([
      ['blur', () => window.dispatchEvent(new Event('blur'))],
      ['Turbo', () => document.dispatchEvent(new Event('turbo:before-render'))],
    ])('a cancel by %s with the button down: the late release does not follow a link that is the item', (_name, end) => {
      const now = vi.spyOn(performance, 'now');
      now.mockReturnValue(1000);
      dragTo(el('a1'), el('b'), 210, 90);
      end();
      now.mockReturnValue(3000);
      pointer('pointerup', el('a1'), 210, 90);
      expect(pointerClick(el('link'))).toBe(true);
      expect(pointerClick(el('link'))).toBe(false); // only that one
    });

    it('the guard ends by itself when the release never comes', () => {
      const now = vi.spyOn(performance, 'now');
      now.mockReturnValue(1000);
      dragTo(el('a1'), el('b'), 210, 90);
      window.dispatchEvent(new Event('blur'));
      now.mockReturnValue(1000 + 11_000);
      expect(pointerClick(el('link'))).toBe(false);
    });

    it('a pointer cancelled by the browser leaves no guard', () => {
      dragTo(el('a1'), el('b'), 210, 90);
      pointer('pointercancel', el('a1'), 210, 90);
      expect(pointerClick(el('link'))).toBe(false);
    });
  });

  describe('revert() edge cases (HH-L6)', () => {
    const lastRevert = (): (() => boolean) => events.filter((e) => e.type === 'moved').slice(-1)[0]!.detail.revert as () => boolean;

    it('refuses while the same item is being dragged or pressed, and the drop then tells the truth', () => {
      alt(el('a1'), 'ArrowRight');
      const revert = lastRevert();
      dragTo(el('a1'), el('c'), 410, 110);
      expect(revert()).toBe(false);
      drop(el('a1'), 410, 110);
      expect(events.filter((e) => e.type === 'moved').slice(-1)[0]!.detail).toMatchObject({ listId: 'c', fromListId: 'b' });

      alt(el('a2'), 'ArrowRight');
      const pressed = lastRevert();
      pointer('pointerdown', el('a2'), 10, 110);
      expect(pressed()).toBe(false);
      pointer('pointerup', el('a2'), 10, 110);
      expect(pressed()).toBe(true);
    });

    it('another item being dragged does not stop it', () => {
      alt(el('a1'), 'ArrowRight');
      const revert = lastRevert();
      dragTo(el('a2'), el('c'), 410, 110);
      expect(revert()).toBe(true);
      expect(ids('a')[0]).toBe('a1');
    });

    it('returns false, never throws, when its origin list is now inside the item', () => {
      alt(el('a1'), 'ArrowRight');
      const revert = lastRevert();
      el('a1').append(el('a'));
      expect(() => revert()).not.toThrow();
      expect(revert()).toBe(false);
    });

    it('a synchronous revert has the last word in the live region', () => {
      listen('nowo-ui:sortable:moved', (e) => (e as CustomEvent).detail.revert());
      alt(el('a1'), 'ArrowDown');
      expect(ids('a')).toEqual(['a1', 'a2', 'a3']);
      expect(live()).toContain('undone');
    });

    it('refuses when the page moved the item elsewhere, or the origin was disabled since', () => {
      alt(el('a1'), 'ArrowRight');
      const moved = lastRevert();
      el('c').append(el('a1'));
      expect(moved()).toBe(false);
      expect(ids('c')).toEqual(['a1']);

      alt(el('a2'), 'ArrowRight');
      const disabled = lastRevert();
      el('a').setAttribute('data-nowo-ui-sortable-disabled', '');
      expect(disabled()).toBe(false);
    });
  });

  describe('events reach their listeners (HH-L7)', () => {
    it('cancel reaches a listener on the document when the root has left the page', () => {
      dragTo(el('a1'), el('b'), 210, 90);
      const item = el('a1');
      el('board').remove();
      pointer('pointermove', document.body, 220, 90);
      expect(item.isConnected).toBe(false);
      expect(events[events.length - 1]).toMatchObject({ type: 'cancel', detail: { id: 'a1', scope: 'cards', reason: 'lost' } });
      expect(leftovers()).toBe(0);
    });

    it('with a still pointer, the next frame notices that the item has gone', () => {
      const frames: FrameRequestCallback[] = [];
      vi.spyOn(window, 'requestAnimationFrame').mockImplementation((cb) => frames.push(cb));
      dragTo(el('a1'), el('b'), 210, 90);
      el('a1').remove();
      expect(document.documentElement.hasAttribute('data-nowo-ui-sortable-active')).toBe(true);
      frames.shift()!(performance.now() + 16);
      expect(document.documentElement.hasAttribute('data-nowo-ui-sortable-active')).toBe(false);
      expect(leftovers()).toBe(0);
      expect(events[events.length - 1]).toMatchObject({ type: 'cancel', detail: { reason: 'lost' } });
    });

    it('a failure while building the copy still ends in cancel, and the page scrolls', () => {
      vi.spyOn(Document.prototype, 'createElementNS').mockImplementation(() => {
        throw new Error('boom');
      });
      vi.spyOn(console, 'error').mockImplementation(() => undefined);
      try {
        pointer('pointerdown', el('h1-handle'), 510, 110, { pointerType: 'touch' });
        pointer('pointermove', el('h1-handle'), 590, 110, { pointerType: 'touch' });
      } catch {
        // Depends on how the DOM reports a listener's error.
      }
      expect(events.map((e) => e.type)).toEqual(['start', 'cancel']);
      const touch = new Event('touchmove', { bubbles: true, cancelable: true });
      document.body.dispatchEvent(touch);
      expect(touch.defaultPrevented).toBe(false);
    });
  });

  describe('live regions (HH-L9)', () => {
    it('focus prepares the region of every root of the scope, before the first move there', () => {
      document.body.innerHTML = `
        <div id="p1"><ul id="l1" data-nowo-ui-sortable="s" data-nowo-ui-sortable-list="l1"><li id="i1" data-nowo-ui-sortable-item="i1" tabindex="0">x</li></ul></div>
        <div id="p2"><ul id="l2" data-nowo-ui-sortable="s" data-nowo-ui-sortable-list="l2"></ul></div>
        <div id="p3"><ul id="l3" data-nowo-ui-sortable="other" data-nowo-ui-sortable-list="l3"></ul></div>`;
      el('i1').dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
      expect(el('p1').querySelector(':scope > [data-nowo-ui-sortable-live]')).not.toBeNull();
      expect(el('p2').querySelector(':scope > [data-nowo-ui-sortable-live]')!.textContent).toBe('');
      expect(el('p3').querySelector('[data-nowo-ui-sortable-live]')).toBeNull();
      alt(el('i1'), 'ArrowRight');
      expect(el('p2').querySelector('[data-nowo-ui-sortable-live]')!.textContent).toContain('moved to l2');
    });

    it('a region created right now speaks a moment later, and says the latest message', () => {
      vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
      document.querySelector('[data-nowo-ui-sortable-live]')!.remove();
      announceSortable(el('board'), 'first');
      const region = document.querySelector('[data-nowo-ui-sortable-live]')!;
      expect(region.textContent).toBe('');
      announceSortable(el('board'), 'second');
      expect(region.textContent).toBe('');
      vi.advanceTimersByTime(200);
      expect(region.textContent).toBe('second');
      announceSortable(el('board'), 'third');
      expect(region.textContent).toBe('third');
    });
  });

  describe('move buttons (WCAG 2.5.7)', () => {
    const buttons = (id: string): void => {
      el(id).insertAdjacentHTML(
        'beforeend',
        ['up', 'down', 'first', 'last', 'prev-list', 'next-list', 'sideways'].map((m) => `<button type="button" id="${id}-${m}" data-nowo-ui-sortable-move="${m}">${m}</button>`).join(''),
      );
    };
    const press = (id: string): MouseEvent => {
      const event = new MouseEvent('click', { bubbles: true, cancelable: true, detail: 1 });
      el(id).dispatchEvent(event);
      return event;
    };

    it('up, down, first and last move within the list and keep focus', () => {
      buttons('a2');
      el('a2-down').focus();
      expect(press('a2-down').defaultPrevented).toBe(true);
      expect(ids('a')).toEqual(['a1', 'a3', 'a2']);
      expect(document.activeElement).toBe(el('a2-down'));
      expect(events[0]!.detail).toMatchObject({ source: 'button', id: 'a2', index: 2, fromIndex: 1 });
      expect(typeof events[1]!.detail.revert).toBe('function');
      expect(live()).toContain('3/3');
      press('a2-first');
      expect(ids('a')).toEqual(['a2', 'a1', 'a3']);
      press('a2-last');
      expect(ids('a')).toEqual(['a1', 'a3', 'a2']);
      press('a2-up');
      expect(ids('a')).toEqual(['a1', 'a2', 'a3']);
    });

    it('prev-list and next-list go across, skipping lists it cannot reach', () => {
      buttons('a2');
      el('b').setAttribute('hidden', '');
      press('a2-next-list');
      expect(ids('c')).toEqual(['a2']);
      press('a2-prev-list');
      expect(ids('a')).toContain('a2');
    });

    it('at an edge it says so and fires nothing', () => {
      buttons('a1');
      press('a1-up');
      press('a1-first');
      press('a1-prev-list');
      expect(events).toEqual([]);
      expect(ids('a')).toEqual(['a1', 'a2', 'a3']);
      expect(live()).toContain('cannot move further');
    });

    it('a listener can refuse, and revert() undoes it', () => {
      buttons('a1');
      const refuse = (e: Event): void => e.preventDefault();
      document.addEventListener('nowo-ui:sortable:move', refuse);
      press('a1-down');
      document.removeEventListener('nowo-ui:sortable:move', refuse);
      expect(ids('a')).toEqual(['a1', 'a2', 'a3']);
      expect(live()).toContain('cannot be moved there');
      press('a1-down');
      (events[events.length - 1]!.detail.revert as () => boolean)();
      expect(ids('a')).toEqual(['a1', 'a2', 'a3']);
    });

    it('an unknown value, a disabled item, a handled click or a running drag do nothing', () => {
      buttons('a1');
      expect(press('a1-sideways').defaultPrevented).toBe(false);
      const stop = (e: Event): void => e.preventDefault();
      el('a1-down').addEventListener('click', stop);
      press('a1-down');
      el('a1-down').removeEventListener('click', stop);
      expect(ids('a')).toEqual(['a1', 'a2', 'a3']);
      buttons('b1');
      el('b1').setAttribute('data-nowo-ui-sortable-disabled', '');
      press('b1-next-list');
      expect(ids('b')).toEqual(['b1']);
      el('b1').removeAttribute('data-nowo-ui-sortable-disabled');
      dragTo(el('a2'), el('b'), 210, 90);
      el('a1-down').click();
      expect(ids('a')).toEqual(['a1', 'a2', 'a3']);
    });

    it('pressing one never starts a drag', () => {
      buttons('a1');
      pointer('pointerdown', el('a1-down'), 10, 110);
      pointer('pointermove', el('a1-down'), 10, 170);
      expect(leftovers()).toBe(0);
    });
  });

  describe('the copy is built by hand (HH-M3)', () => {
    const ghostOf = (): HTMLElement => document.querySelector<HTMLElement>('.nowo-ui-sortable__ghost')!;

    it('never clones: a clone is what makes a blocked style live on the original', () => {
      const clone = vi.spyOn(Node.prototype, 'cloneNode');
      const importNode = vi.spyOn(Document.prototype, 'importNode');
      el('b1').insertAdjacentHTML('beforeend', '<i class="x"><b>deep</b></i>');
      clone.mockClear();
      importNode.mockClear();
      dragTo(el('b1'), el('c'), 410, 110);
      expect(leftovers()).toBe(2);
      expect(clone).not.toHaveBeenCalled();
      expect(importNode).not.toHaveBeenCalled();
      expect(ghostOf().querySelector('i.x b')!.textContent).toBe('deep');
    });

    it('customized built-ins stay plain: the is attribute never reaches the copy', () => {
      el('b1').insertAdjacentHTML('beforeend', '<div is="x-li" class="kept">z</div>');
      dragTo(el('b1'), el('c'), 410, 110);
      const copy = ghostOf().querySelector('div.kept')!;
      expect(copy.hasAttribute('is')).toBe(false);
    });

    it('on a large card every declaration lands on its own element', () => {
      const parts: string[] = [];
      for (let i = 0; i < 300; i++) {
        parts.push(`<div class="row" data-n="${i}"><span class="c" data-n="${i}">t${i}</span>${i % 7 === 0 ? '<script type="text/plain">x</script><x-part>y</x-part>' : ''}</div>`);
      }
      el('b1').insertAdjacentHTML('beforeend', parts.join(''));
      el('b1').querySelectorAll<HTMLElement>('.c').forEach((c) => c.style.setProperty('--n', c.getAttribute('data-n')!));
      dragTo(el('b1'), el('c'), 410, 110);
      const copies = [...ghostOf().querySelectorAll<HTMLElement>('.c')];
      expect(copies).toHaveLength(300);
      copies.forEach((c) => expect(c.style.getPropertyValue('--n')).toBe(c.getAttribute('data-n')));
      expect(ghostOf().querySelector('script, x-part')).toBeNull();
    });

    it('keeps what a field shows, svg namespaces and text, and skips an attribute the DOM refuses', () => {
      el('b1').innerHTML = `<input id="typed" value="old"><input id="box" type="checkbox"><input id="up" type="file"><textarea id="ta"></textarea>
        <select id="sel"><option>a</option><option>b</option></select><svg viewBox="0 0 1 1"><use href="#x"></use></svg>text<!-- no comment -->`;
      (el('typed') as HTMLInputElement).value = 'typed';
      (el('box') as HTMLInputElement).checked = true;
      (el('ta') as HTMLTextAreaElement).value = 'long text';
      (el('sel') as HTMLSelectElement).selectedIndex = 1;
      const refused = vi.spyOn(Element.prototype, 'setAttributeNS').mockImplementationOnce(() => {
        throw new DOMException('bad name', 'InvalidCharacterError');
      });
      dragTo(el('b1'), el('c'), 410, 110);
      refused.mockRestore();
      const ghost = ghostOf();
      const inputs = ghost.querySelectorAll('input');
      expect(inputs[0]!.value).toBe('typed');
      expect(inputs[1]!.checked).toBe(true);
      expect(ghost.querySelector('textarea')!.value).toBe('long text');
      expect(ghost.querySelector('select')!.selectedIndex).toBe(1);
      expect(ghost.querySelector('svg')!.namespaceURI).toBe('http://www.w3.org/2000/svg');
      expect(ghost.textContent).toContain('text');
      expect(ghost.innerHTML).not.toContain('no comment');
    });
  });

  describe('delta fixes', () => {
    const press = (id: string): MouseEvent => {
      const event = new MouseEvent('click', { bubbles: true, cancelable: true, detail: 1 });
      el(id).dispatchEvent(event);
      return event;
    };

    it.each(['a', 'div', 'span'])('HH-DL1: the move attribute on a <%s> does nothing', (tag) => {
      el('a1').insertAdjacentHTML('beforeend', `<${tag} id="planted-move" href="#x" data-nowo-ui-sortable-move="down">p</${tag}>`);
      const event = press('planted-move');
      expect(event.defaultPrevented).toBe(false);
      expect(ids('a')).toEqual(['a1', 'a2', 'a3']);
      expect(events).toEqual([]);
    });

    it('HH-DL1: a click inside a real move button still moves', () => {
      el('a1').insertAdjacentHTML('beforeend', '<button type="button" data-nowo-ui-sortable-move="down"><i id="in-button">v</i></button>');
      press('in-button');
      expect(ids('a')).toEqual(['a2', 'a1', 'a3']);
    });

    it('HH-DL2: keys and buttons wait while a press is pending, so the gesture stays true', () => {
      el('a1').insertAdjacentHTML('beforeend', '<button type="button" id="next" data-nowo-ui-sortable-move="next-list">n</button>');
      pointer('pointerdown', el('a1'), 10, 110);
      alt(el('a1'), 'ArrowRight');
      el('next').click();
      expect(ids('a')[0]).toBe('a1');
      under = () => el('b');
      pointer('pointermove', el('a1'), 210, 90);
      drop(el('a1'), 210, 90);
      expect(events.find((e) => e.type === 'moved')!.detail).toMatchObject({ fromListId: 'a', fromIndex: 0, listId: 'b' });
    });

    it('HH-DL2: an item moved away during a press starts nothing', () => {
      pointer('pointerdown', el('a1'), 10, 110);
      el('b').append(el('a1'));
      under = () => el('b');
      pointer('pointermove', el('a1'), 210, 90);
      expect(events).toEqual([]);
      expect(leftovers()).toBe(0);
      pointer('pointerup', el('a1'), 210, 90);
    });

    it('HH-DI1: a component stopping pointerdown loses no real click, and a stale drag still ends', () => {
      const stop = (e: Event): void => e.stopPropagation();
      dragTo(el('a1'), el('b'), 210, 90);
      window.dispatchEvent(new Event('blur')); // the long guard, with no release seen
      el('add-a').addEventListener('pointerdown', stop);
      pointer('pointerdown', el('add-a'), 0, 0);
      const click = press('add-a');
      expect(click.defaultPrevented).toBe(false);

      dragTo(el('a2'), el('b'), 210, 90);
      expect(leftovers()).toBe(2);
      pointer('pointerdown', el('add-a'), 0, 0, { pointerId: 9 });
      el('add-a').removeEventListener('pointerdown', stop);
      expect(leftovers()).toBe(0);
    });

    it('a morph that strips is-dragging: the next move puts it back', () => {
      dragTo(el('a1'), el('b'), 210, 90);
      el('a1').classList.remove('is-dragging');
      pointer('pointermove', el('a1'), 212, 90);
      expect(el('a1').classList.contains('is-dragging')).toBe(true);
    });

    it('HH-DI5: an unrelated finger lifting does not end a pen drag', () => {
      vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
      under = () => el('h');
      const pen = { pointerType: 'pen' };
      pointer('pointerdown', el('h1-handle'), 510, 110, pen);
      pointer('pointermove', el('h1-handle'), 590, 110, pen);
      expect(leftovers()).toBe(2);
      const event = new Event('touchend', { bubbles: true });
      Object.defineProperty(event, 'touches', { value: [] });
      window.dispatchEvent(event);
      vi.advanceTimersByTime(1000);
      expect(leftovers()).toBe(2);
      drop(el('h1-handle'), 590, 110);
    });
  });

  describe('helpers', () => {
    it('itemsOf leaves out items of nested lists', () => {
      el('b1').innerHTML = '<ul id="nested" data-nowo-ui-sortable-list="n"><li id="n1" data-nowo-ui-sortable-item="n1">n</li></ul>';
      expect(ids('b')).toEqual(['b1']);
      expect(ids('nested')).toEqual(['n1']);
    });

    it('a nested item moves alone, with its own list', () => {
      el('b1').innerHTML = '<ul id="nested" data-nowo-ui-sortable-list="n"><li id="n1" data-nowo-ui-sortable-item="n1">n</li></ul>';
      alt(el('n1'), 'ArrowLeft');
      expect(el('b').contains(el('n1'))).toBe(true);
      expect(ids('b')).toEqual(['n1', 'b1']);
      expect(ids('nested')).toEqual([]);
    });

    it('an item cannot move into its own nested list', () => {
      el('b1').innerHTML = '<ul id="nested" data-nowo-ui-sortable-list="n"></ul>';
      alt(el('b1'), 'ArrowRight');
      expect(el('nested').contains(el('b1'))).toBe(false);
      expect(ids('c')).toEqual(['b1']);
    });

    it('axis: the list, then its own root, never an outer board of another scope', () => {
      document.body.innerHTML = `
        <div data-nowo-ui-sortable="columns" data-nowo-ui-sortable-axis="x">
          <div id="board" data-nowo-ui-sortable-list="board">
            <section data-nowo-ui-sortable-item="c1">
              <ul id="cards" data-nowo-ui-sortable="cards" data-nowo-ui-sortable-list="c1"></ul>
              <div data-nowo-ui-sortable="own" data-nowo-ui-sortable-axis="x"><ul id="own" data-nowo-ui-sortable-list="o"></ul></div>
            </section>
          </div>
        </div>`;
      expect(axisOf(el('board'))).toBe('x');
      expect(axisOf(el('cards'))).toBe('y');
      expect(axisOf(el('own'))).toBe('x');
    });

    it('formatSortable fills once and keeps unknown names', () => {
      expect(formatSortable('%label% %x% %position%', { label: '%position%', position: 2 })).toBe('%position% %x% 2');
    });

    it('labels: bad JSON and non-strings keep the default wording', () => {
      el('board').setAttribute('data-nowo-ui-sortable-labels', '{bad');
      alt(el('a1'), 'ArrowDown');
      expect(live()).toContain('moved to position 2 of 3');
      el('board').setAttribute('data-nowo-ui-sortable-labels', '{"moved":5,"__proto__":"x"}');
      alt(el('a1'), 'ArrowDown');
      expect(live()).toContain('moved to position 3 of 3');
    });

    it('announceSortable makes one region, as text, and changes a repeated message', () => {
      const root = el('board');
      announceSortable(root, '<b>same</b>');
      const region = document.querySelector<HTMLElement>('[data-nowo-ui-sortable-live]')!;
      expect(region.textContent).toBe('<b>same</b>');
      expect(region.getAttribute('role')).toBe('status');
      expect(region.querySelector('b')).toBeNull();
      const first = region.textContent;
      announceSortable(root, '<b>same</b>');
      expect(region.textContent).not.toBe(first);
      expect(document.querySelectorAll('[data-nowo-ui-sortable-live]')).toHaveLength(1);
    });

    it('the region goes after a list or table parent, not inside it', () => {
      document.body.innerHTML = '<div id="w"><table><tbody id="t" data-nowo-ui-sortable="rows" data-nowo-ui-sortable-list="t"><tr id="r1" data-nowo-ui-sortable-item="r1"><td>1</td></tr></tbody></table></div>';
      announceSortable(el('t'), 'x');
      expect(document.querySelector('[data-nowo-ui-sortable-live]')!.parentElement!.id).toBe('w');
    });

    it('focus on an item prepares the region before the first message', () => {
      document.querySelector('[data-nowo-ui-sortable-live]')!.remove();
      el('a1').dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
      expect(document.querySelector('[data-nowo-ui-sortable-live]')).not.toBeNull();
    });

    it('a table row gets a row placeholder', () => {
      document.body.innerHTML = '<table><tbody id="t" data-nowo-ui-sortable="rows" data-nowo-ui-sortable-list="t"><tr id="r1" data-nowo-ui-sortable-item="r1"><td>1</td></tr><tr id="r2" data-nowo-ui-sortable-item="r2"><td>2</td></tr></tbody></table>';
      under = () => el('t');
      pointer('pointerdown', el('r1'), 0, 0);
      pointer('pointermove', el('r1'), 0, 50);
      const placeholder = document.querySelector('.nowo-ui-sortable__placeholder')!;
      expect(placeholder.tagName).toBe('TR');
      expect(placeholder.querySelector('td')).not.toBeNull();
      drop(el('r1'), 0, 50);
    });

    it('items without a root or a list do nothing', () => {
      document.body.innerHTML = '<ul data-nowo-ui-sortable-list="x"><li id="x1" data-nowo-ui-sortable-item="x1">x</li></ul><div id="plain">p</div>';
      pointer('pointerdown', el('x1'), 0, 0);
      pointer('pointermove', el('x1'), 0, 50);
      expect(alt(el('x1'), 'ArrowDown').defaultPrevented).toBe(false);
      el('plain').dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
      expect(leftovers()).toBe(0);
    });

    it('a second copy of the script does not bind again', async () => {
      vi.resetModules();
      const again = await import('./nowo-ui-sortable');
      expect(again.announceSortable).not.toBe(announceSortable);
      alt(el('a1'), 'ArrowDown');
      expect(ids('a')).toEqual(['a2', 'a1', 'a3']);
      expect(events.filter((e) => e.type === 'move')).toHaveLength(1);
    });
  });
});
