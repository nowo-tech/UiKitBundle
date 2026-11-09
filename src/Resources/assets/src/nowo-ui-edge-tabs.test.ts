import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { bindNowoUiEdgeTabs, isOpen, launcher, OPEN, READY, setOpen, tabs } from './nowo-ui-edge-tabs';

const MARKUP = `
<main><p><a id="page-link" href="#p">page</a></p></main>
<div class="nowo-ui-edge-tab" data-nowo-ui-edge-tab-slot="0"><a id="t1" class="nowo-ui-edge-tab__link" href="#manual" aria-label="Manual">m</a></div>
<div class="nowo-ui-edge-tab" data-nowo-ui-edge-tab-slot="1"><button id="t2" type="button" class="nowo-ui-edge-tab__link" aria-label="Tour">t</button></div>
<button id="launcher" type="button" class="nowo-ui-edge-tabs-launcher" data-nowo-ui-edge-tabs-launcher aria-expanded="false" aria-label="Page tools">+</button>`;

const el = (id: string): HTMLElement => document.getElementById(id) as HTMLElement;
const key = (k: string): KeyboardEvent => new KeyboardEvent('keydown', { key: k, bubbles: true, cancelable: true });

describe('nowo-ui-edge-tabs', () => {
  beforeEach(() => {
    document.body.innerHTML = MARKUP;
    bindNowoUiEdgeTabs();
  });

  afterEach(() => {
    setOpen(false);
    document.body.innerHTML = '';
    vi.restoreAllMocks();
  });

  it('marks the document ready once (the CSS folds the tabs only then)', () => {
    bindNowoUiEdgeTabs();
    expect(document.documentElement.hasAttribute(READY)).toBe(true);
    expect(launcher()).toBe(el('launcher'));
    expect(tabs().map((t) => t.id)).toEqual(['t1', 't2']);
  });

  it('opens and closes from the launcher, with aria-expanded and a toggle event', () => {
    const events: boolean[] = [];
    document.addEventListener('nowo-ui:edge-tabs:toggle', (e) => events.push((e as CustomEvent<{ open: boolean }>).detail.open));
    el('launcher').dispatchEvent(new MouseEvent('click', { bubbles: true, detail: 1 }));
    expect(isOpen()).toBe(true);
    expect(document.documentElement.hasAttribute(OPEN)).toBe(true);
    expect(el('launcher').getAttribute('aria-expanded')).toBe('true');
    // A pointer opening leaves the focus where it is.
    expect(document.activeElement).not.toBe(el('t1'));

    el('launcher').dispatchEvent(new MouseEvent('click', { bubbles: true, detail: 1 }));
    expect(isOpen()).toBe(false);
    expect(el('launcher').getAttribute('aria-expanded')).toBe('false');
    expect(events).toEqual([true, false]);

    // Setting the same state again dispatches nothing.
    setOpen(false);
    expect(events).toEqual([true, false]);
  });

  it('a keyboard opening focuses the first tab; arrows walk the ring; Escape closes back on the launcher', () => {
    el('launcher').focus();
    el('launcher').dispatchEvent(new MouseEvent('click', { bubbles: true, detail: 0 }));
    expect(isOpen()).toBe(true);
    expect(document.activeElement).toBe(el('t1'));

    const down = key('ArrowDown');
    el('t1').dispatchEvent(down);
    expect(down.defaultPrevented).toBe(true);
    expect(document.activeElement).toBe(el('t2'));
    el('t2').dispatchEvent(key('ArrowDown'));
    expect(document.activeElement).toBe(el('launcher'));
    el('launcher').dispatchEvent(key('ArrowUp'));
    expect(document.activeElement).toBe(el('t2'));

    // Other keys (Tab included) are left alone: no focus trap.
    const tab = key('Tab');
    el('t2').dispatchEvent(tab);
    expect(tab.defaultPrevented).toBe(false);

    // Arrows from outside the ring do nothing (moving the focus there folded the tabs; open them again).
    el('page-link').focus();
    setOpen(true);
    const outside = key('ArrowDown');
    el('page-link').dispatchEvent(outside);
    expect(outside.defaultPrevented).toBe(false);

    el('t2').focus();
    setOpen(true);
    el('t2').dispatchEvent(key('Escape'));
    expect(isOpen()).toBe(false);
    expect(document.activeElement).toBe(el('launcher'));

    // Keys while closed do nothing.
    const closed = key('ArrowDown');
    el('launcher').dispatchEvent(closed);
    expect(closed.defaultPrevented).toBe(false);
  });

  it('closes once a tab is used and on a click elsewhere', () => {
    setOpen(true);
    el('t2').dispatchEvent(new MouseEvent('click', { bubbles: true, detail: 1 }));
    expect(isOpen()).toBe(false);

    setOpen(true);
    el('page-link').dispatchEvent(new MouseEvent('click', { bubbles: true, detail: 1 }));
    expect(isOpen()).toBe(false);

    // A click elsewhere while closed changes nothing.
    el('page-link').dispatchEvent(new MouseEvent('click', { bubbles: true, detail: 1 }));
    expect(isOpen()).toBe(false);
  });

  it('closes when the focus leaves the launcher and the tabs, not when it moves between them', () => {
    setOpen(true);
    el('t1').dispatchEvent(new FocusEvent('focusout', { bubbles: true, relatedTarget: el('t2') }));
    expect(isOpen()).toBe(true);
    el('t2').dispatchEvent(new FocusEvent('focusout', { bubbles: true, relatedTarget: null }));
    expect(isOpen()).toBe(true);
    el('page-link').dispatchEvent(new FocusEvent('focusout', { bubbles: true, relatedTarget: el('t1') }));
    expect(isOpen()).toBe(true);
    el('t2').dispatchEvent(new FocusEvent('focusout', { bubbles: true, relatedTarget: el('page-link') }));
    expect(isOpen()).toBe(false);

    // Focus moves while closed change nothing.
    el('t2').dispatchEvent(new FocusEvent('focusout', { bubbles: true, relatedTarget: el('page-link') }));
    expect(isOpen()).toBe(false);
  });

  it('works without a launcher on the page (state on <html> only)', () => {
    el('launcher').remove();
    expect(launcher()).toBeNull();
    setOpen(true, true);
    expect(isOpen()).toBe(true);
    expect(document.activeElement).toBe(el('t1'));
    document.body.dispatchEvent(key('Escape'));
    expect(isOpen()).toBe(false);
  });
});
