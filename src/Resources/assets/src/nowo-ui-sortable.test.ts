import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { LONG_PRESS_MS, announceSortable, axisOf, bindNowoUiSortable, formatSortable, itemsOf } from './nowo-ui-sortable';

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

function pointer(type: string, target: Element, x: number, y: number, init: Record<string, unknown> = {}): PointerEvent {
  const event = new PointerEvent(type, { bubbles: true, cancelable: true, clientX: x, clientY: y, pointerId: 1, pointerType: 'mouse', button: 0, isPrimary: true, ...init } as PointerEventInit);
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
    (document as unknown as { elementFromPoint: () => Element | null }).elementFromPoint = () => under();
    for (const type of ['start', 'move', 'moved', 'cancel', 'reverted']) {
      listen(`nowo-ui:sortable:${type}`, (e) => events.push({ type, detail: (e as CustomEvent).detail }));
    }
  });

  afterEach(() => {
    document.dispatchEvent(new Event('turbo:before-render'));
    listeners.splice(0).forEach(([type, fn]) => document.removeEventListener(type, fn));
    document.body.innerHTML = '';
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
      const click = new MouseEvent('click', { bubbles: true, cancelable: true });
      el('link').dispatchEvent(click);
      expect(click.defaultPrevented).toBe(true);
      // Only that one click.
      const next = new MouseEvent('click', { bubbles: true, cancelable: true });
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
          vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('hidden');
          document.dispatchEvent(new Event('visibilitychange'));
          vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible');
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

    it('a link in the item drags, and the click after the drop is swallowed', () => {
      dragTo(el('link'), el('a'), 10, 170);
      drop(el('link'), 10, 170);
      expect(ids('a')).toEqual(['a2', 'a1', 'a3']);
      const click = new MouseEvent('click', { bubbles: true, cancelable: true });
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
      pointer('pointerdown', el('a2'), 10, 150, { pointerId: 2 });
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
      const click = new MouseEvent('click', { bubbles: true, cancelable: true });
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
      Object.defineProperty(scroller, 'scrollHeight', { configurable: true, value: 1000 });
      Object.defineProperty(scroller, 'clientHeight', { configurable: true, value: 400 });
      Object.defineProperty(scroller, 'scrollWidth', { configurable: true, value: 400 });
      Object.defineProperty(scroller, 'clientWidth', { configurable: true, value: 400 });
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
      expect(document.querySelector('[data-nowo-ui-sortable-live]')).toBeNull();
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
