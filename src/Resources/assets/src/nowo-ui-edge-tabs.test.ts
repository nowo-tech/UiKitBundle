import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { bindNowoUiEdgeTabs, isOpen, isPinned, isShown, launcher, linkTabs, onTabsChanged, OPEN, PINNED, READY, recoverFocus, setOpen, tabs } from './nowo-ui-edge-tabs';

const MARKUP = `
<main><p><a id="page-link" href="#p">page</a></p></main>
<div class="nowo-ui-edge-tab" data-nowo-ui-edge-tab-slot="0"><a id="t1" class="nowo-ui-edge-tab__link" href="#manual" aria-label="Manual">m</a></div>
<div class="nowo-ui-edge-tab" data-nowo-ui-edge-tab-slot="1"><button id="t2" type="button" class="nowo-ui-edge-tab__link" aria-label="Tour">t</button></div>
<dialog id="dlg"><button id="in-dialog" type="button">ok</button></dialog>
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
  /** happy-dom has no layout: say which elements are rendered. */
  function shown(ids: string[]): void {
    vi.spyOn(Element.prototype, 'getClientRects').mockImplementation(function (this: Element) {
      return (ids.includes(this.id) ? [{}] : []) as unknown as DOMRectList;
    });
  }

  function closeDialog(): void {
    el('dlg').dispatchEvent(new Event('close'));
  }

  it('2.41.1 (JC-L1): a dialog opened from a folded tab gives the focus back to the launcher', async () => {
    vi.useFakeTimers();
    try {
      shown(['launcher']);
      setOpen(true);
      el('t2').dispatchEvent(new MouseEvent('click', { bubbles: true, detail: 1 }));
      expect(isOpen()).toBe(false);
      // The dialog runtime returns the focus to the (now hidden) tab, or the browser drops it on <body>.
      el('t2').focus();
      closeDialog();
      vi.runAllTimers();
      expect(document.activeElement).toBe(el('launcher'));

      // The dialog took the focus first (focusout from the tab folds before the click reaches the document).
      setOpen(true);
      el('t2').dispatchEvent(new FocusEvent('focusout', { bubbles: true, relatedTarget: el('in-dialog') }));
      expect(isOpen()).toBe(false);
      el('t2').dispatchEvent(new MouseEvent('click', { bubbles: true, detail: 1 }));
      (document.activeElement as HTMLElement).blur();
      closeDialog();
      vi.runAllTimers();
      expect(document.activeElement).toBe(el('launcher'));

      // Focus already somewhere visible: left alone.
      setOpen(true);
      el('t1').dispatchEvent(new MouseEvent('click', { bubbles: true, detail: 1 }));
      el('page-link').focus();
      closeDialog();
      vi.runAllTimers();
      expect(document.activeElement).toBe(el('page-link'));

      // A dialog that no fold tab opened: nothing to do.
      (document.activeElement as HTMLElement).blur();
      closeDialog();
      vi.runAllTimers();
      expect(document.activeElement).toBe(document.body);
    } finally {
      vi.useRealTimers();
    }
  });

  it('2.41.1: no recovery when the tab is shown or the launcher is not', () => {
    shown(['launcher', 't2']);
    setOpen(true);
    el('t2').dispatchEvent(new MouseEvent('click', { bubbles: true, detail: 1 }));
    (document.activeElement as HTMLElement | null)?.blur();
    recoverFocus();
    expect(document.activeElement).toBe(document.body);

    shown([]);
    setOpen(true);
    el('t2').dispatchEvent(new MouseEvent('click', { bubbles: true, detail: 1 }));
    recoverFocus();
    expect(document.activeElement).toBe(document.body);
    expect(isShown(el('launcher'))).toBe(false);

    // Nothing used: nothing to do.
    recoverFocus();
    expect(document.activeElement).toBe(document.body);
  });

  it('2.41.1 (JC-I15): the launcher controls the tabs (ids given where missing)', () => {
    setOpen(true);
    const controls = el('launcher').getAttribute('aria-controls')?.split(' ') ?? [];
    expect(controls).toHaveLength(2);
    expect(controls[0]).toBe('t1');
    expect(el('t2').id).toBe('t2');
    el('t2').removeAttribute('id');
    linkTabs(el('launcher'));
    const ids = (el('launcher').getAttribute('aria-controls') ?? '').split(' ');
    expect(ids[1]).toMatch(/^nowo-ui-edge-tab-\d+$/);
    expect(document.getElementById(ids[1])?.classList.contains('nowo-ui-edge-tab__link')).toBe(true);

    // No tabs: no attribute change.
    document.querySelectorAll('.nowo-ui-edge-tab').forEach((t) => t.remove());
    el('launcher').removeAttribute('aria-controls');
    linkTabs(el('launcher'));
    expect(el('launcher').hasAttribute('aria-controls')).toBe(false);
  });

  /** Mutation observers run in a microtask; a macrotask flushes them. */
  const flush = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));

  it('2.42 (MUX-01): a tab whose panel is open, or a pinned tab, stays out of the fold', () => {
    expect(isPinned(el('t1'))).toBe(false);
    el('t1').setAttribute('aria-expanded', 'false');
    expect(isPinned(el('t1'))).toBe(false);
    el('t1').setAttribute('aria-expanded', 'true');
    expect(isPinned(el('t1'))).toBe(true);
    el('t2').setAttribute(PINNED, '');
    expect(isPinned(el('t2'))).toBe(true);

    // A click outside the open launcher folds it; the expanded tab is not touched (its state is the host's).
    setOpen(true);
    el('page-link').dispatchEvent(new MouseEvent('click', { bubbles: true, detail: 1 }));
    expect(isOpen()).toBe(false);
    expect(el('t1').getAttribute('aria-expanded')).toBe('true');
  });

  it('2.42 (MUX-01): an expanded tab that folds while focused gives the focus to the launcher', async () => {
    // Folded: the expanded tab and the launcher are rendered.
    shown(['launcher', 't1']);
    el('t1').setAttribute('aria-expanded', 'true');
    el('t1').focus();
    await flush();
    expect(document.activeElement).toBe(el('t1'));

    // Its panel closes: the fold hides it (CSS) and the focus moves to the launcher.
    shown(['launcher']);
    el('t1').setAttribute('aria-expanded', 'false');
    await flush();
    expect(document.activeElement).toBe(el('launcher'));

    // Not focused, or still shown (no fold on this viewport), or the launcher open: nothing moves.
    el('page-link').focus();
    onTabsChanged([el('t1')]);
    expect(document.activeElement).toBe(el('page-link'));
    shown(['launcher', 't2']);
    el('t2').focus();
    onTabsChanged([el('t2')]);
    expect(document.activeElement).toBe(el('t2'));
    shown(['launcher']);
    el('t2').setAttribute(PINNED, '');
    onTabsChanged([el('t2')]);
    expect(document.activeElement).toBe(el('t2'));
    el('t2').removeAttribute(PINNED);
    setOpen(true);
    el('t2').focus();
    onTabsChanged([el('t2')]);
    expect(document.activeElement).toBe(el('t2'));
    setOpen(false);

    // No rendered launcher (folding off): the focus stays.
    shown([]);
    el('t2').focus();
    onTabsChanged([el('t2')]);
    expect(document.activeElement).toBe(el('t2'));

    // Changes on other elements are ignored.
    shown(['launcher']);
    el('t1').focus();
    el('page-link').setAttribute('aria-expanded', 'false');
    await flush();
    expect(document.activeElement).toBe(el('t1'));
  });
});
