import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { animationTime, bindNowoUiEdgeTabs, CLOSING, finishClosing, FOLDING, foldingTabs, isClosing, isOpen, isPinned, isShown, launcher, linkTabs, onTabsChanged, OPEN, PINNED, ORDER, READY, recoverFocus, reducedMotion, setOpen, tabs } from './nowo-ui-edge-tabs';

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

describe('nowo-ui-edge-tabs unfold / fold (2.45)', () => {
  const MORE = `
<div class="nowo-ui-edge-tab" data-nowo-ui-edge-tab-slot="0"><a id="u1" class="nowo-ui-edge-tab__link" href="#a">a</a></div>
<div class="nowo-ui-edge-tab" data-nowo-ui-edge-tab-slot="1"><a id="u2" class="nowo-ui-edge-tab__link" href="#b">b</a></div>
<div class="nowo-ui-edge-tab" data-nowo-ui-edge-tab-slot="2"><button id="u3" type="button" class="nowo-ui-edge-tab__link" data-nowo-ui-edge-tab-pinned>c</button></div>
<div class="nowo-ui-edge-tab" data-nowo-ui-edge-tab-slot="3"><a id="u4" class="nowo-ui-edge-tab__link" href="#d">d</a></div>
<button id="go" type="button" class="nowo-ui-edge-tabs-launcher" data-nowo-ui-edge-tabs-launcher aria-expanded="false" aria-label="Page tools">+</button>`;
  const get = (id: string): HTMLElement => document.getElementById(id) as HTMLElement;
  const order = (id: string): string => get(id).style.getPropertyValue(ORDER);

  /** The CSS of the kit as a browser resolves it: 200 ms per tab, 30 ms more per --nowo-ui-edge-tabs-order. */
  function animated(): void {
    const real = window.getComputedStyle.bind(window);
    vi.spyOn(window, 'getComputedStyle').mockImplementation((elt: Element) => {
      const style = real(elt);
      const tab = elt as HTMLElement;
      const isTab = tab.matches('.nowo-ui-edge-tab__link');
      const n = Number(tab.style?.getPropertyValue(ORDER) || '0');
      return new Proxy(style, {
        get(target, prop) {
          if ('animationName' === prop) {
            return isTab ? 'nowo-ui-edge-tab-fold' : 'none';
          }
          if ('animationDuration' === prop) {
            return isTab ? '0.2s' : '0s';
          }
          if ('animationDelay' === prop) {
            return isTab ? `${n * 30}ms` : '0s';
          }
          const value = Reflect.get(target, prop);
          return typeof value === 'function' ? value.bind(target) : value;
        },
      });
    });
  }

  function rendered(): void {
    vi.spyOn(Element.prototype, 'getClientRects').mockImplementation(function (this: Element) {
      return [{}] as unknown as DOMRectList;
    });
  }

  beforeEach(() => {
    document.body.innerHTML = MORE;
    bindNowoUiEdgeTabs();
    vi.useFakeTimers();
  });

  afterEach(() => {
    setOpen(false);
    finishClosing();
    vi.useRealTimers();
    vi.restoreAllMocks();
    document.documentElement.removeAttribute('data-motion');
    document.body.innerHTML = '';
  });

  it('staggers the opening: every folding tab gets its order, pinned tabs none', () => {
    rendered();
    animated();
    setOpen(true);
    expect(foldingTabs().map((t) => t.id)).toEqual(['u1', 'u2', 'u4']);
    expect(['u1', 'u2', 'u4'].map(order)).toEqual(['0', '1', '2']);
    expect(order('u3')).toBe('');
    expect(isClosing()).toBe(false);
  });

  it('closing runs the reverse: state at once, tabs painted, inert and aria-hidden until the animations end', () => {
    rendered();
    animated();
    const events: boolean[] = [];
    document.addEventListener('nowo-ui:edge-tabs:toggle', (e) => events.push((e as CustomEvent<{ open: boolean }>).detail.open));
    setOpen(true);
    setOpen(false);

    // The state changed at once.
    expect(isOpen()).toBe(false);
    expect(get('go').getAttribute('aria-expanded')).toBe('false');
    expect(events).toEqual([true, false]);

    // The tabs fold back, the last one first; they take no focus and leave the accessibility tree.
    expect(document.documentElement.hasAttribute(CLOSING)).toBe(true);
    expect(['u1', 'u2', 'u4'].map(order)).toEqual(['2', '1', '0']);
    for (const id of ['u1', 'u2', 'u4']) {
      expect(get(id).hasAttribute(FOLDING)).toBe(true);
      expect(get(id).hasAttribute('inert')).toBe(true);
      expect(get(id).getAttribute('aria-hidden')).toBe('true');
    }
    // A pinned tab never folds.
    expect(get('u3').hasAttribute(FOLDING)).toBe(false);
    expect(get('u3').hasAttribute('inert')).toBe(false);

    // 200 ms + 2 × 30 ms stagger.
    expect(animationTime(foldingTabs())).toBe(260);
    vi.advanceTimersByTime(250);
    expect(isClosing()).toBe(true);
    vi.advanceTimersByTime(40);
    expect(isClosing()).toBe(false);
    for (const id of ['u1', 'u2', 'u4']) {
      expect(get(id).hasAttribute(FOLDING)).toBe(false);
      expect(get(id).hasAttribute('inert')).toBe(false);
      expect(get(id).hasAttribute('aria-hidden')).toBe(false);
      expect(order(id)).toBe('');
    }
  });

  it('opening again while closing cancels the closing phase and restarts the unfold', () => {
    rendered();
    animated();
    setOpen(true);
    setOpen(false);
    expect(isClosing()).toBe(true);
    setOpen(true);
    expect(isClosing()).toBe(false);
    expect(isOpen()).toBe(true);
    expect(get('u1').hasAttribute('inert')).toBe(false);
    expect(['u1', 'u2', 'u4'].map(order)).toEqual(['0', '1', '2']);
    vi.advanceTimersByTime(1000);
    expect(isOpen()).toBe(true);
  });

  it('Escape closes, the focus goes to the launcher, never to a folding tab', () => {
    rendered();
    animated();
    get('go').focus();
    get('go').dispatchEvent(new MouseEvent('click', { bubbles: true, detail: 0 }));
    expect(document.activeElement).toBe(get('u1'));
    get('u1').dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    expect(isOpen()).toBe(false);
    expect(document.activeElement).toBe(get('go'));
    expect(get('u1').hasAttribute('inert')).toBe(true);
  });

  it('a dialog closed while its tab still folds gives the focus to the launcher (JC-L1)', () => {
    rendered();
    animated();
    document.body.insertAdjacentHTML('beforeend', '<dialog id="d2"><button id="in-d2" type="button">ok</button></dialog>');
    setOpen(true);
    get('u2').dispatchEvent(new MouseEvent('click', { bubbles: true, detail: 1 }));
    expect(isClosing()).toBe(true);
    // A folding tab counts as hidden: it is inert and about to be display: none.
    expect(isShown(get('u2'))).toBe(false);
    expect(isShown(get('u3'))).toBe(true);
    get('d2').dispatchEvent(new Event('close'));
    vi.advanceTimersByTime(1);
    expect(document.activeElement).toBe(get('go'));
  });

  it('no closing phase under reduced motion (media query or data-motion), unless data-motion="full"', () => {
    rendered();
    animated();
    const media = vi.fn().mockReturnValue({ matches: true });
    vi.stubGlobal('matchMedia', media);
    try {
      expect(reducedMotion()).toBe(true);
      setOpen(true);
      setOpen(false);
      expect(isClosing()).toBe(false);
      expect(get('u1').hasAttribute('inert')).toBe(false);

      document.documentElement.setAttribute('data-motion', 'full');
      expect(reducedMotion()).toBe(false);
      setOpen(true);
      setOpen(false);
      expect(isClosing()).toBe(true);
      finishClosing();

      media.mockReturnValue({ matches: false });
      document.documentElement.setAttribute('data-motion', 'reduce');
      expect(reducedMotion()).toBe(true);
      setOpen(true);
      setOpen(false);
      expect(isClosing()).toBe(false);
    } finally {
      vi.unstubAllGlobals();
    }
  });

  it('no closing phase when the launcher is not shown or the tabs have no animation', () => {
    // Launcher not rendered (desktop with `when: phone`).
    setOpen(true);
    setOpen(false);
    expect(isClosing()).toBe(false);

    // Rendered, but no animation resolves (host CSS turned it off): the fold ends at once.
    rendered();
    setOpen(true);
    setOpen(false);
    expect(isClosing()).toBe(false);
    expect(get('u1').hasAttribute(FOLDING)).toBe(false);
  });

  it('2.46 (KV-L1): turbo:before-cache ends a running fold, so the snapshot keeps no inert / aria-hidden tab', () => {
    rendered();
    animated();
    setOpen(true);
    setOpen(false);
    expect(isClosing()).toBe(true);
    document.dispatchEvent(new Event('turbo:before-cache'));
    expect(isClosing()).toBe(false);
    expect(document.querySelectorAll(`[${FOLDING}], [inert], [aria-hidden]`).length).toBe(0);
    // Idle: nothing to do.
    document.dispatchEvent(new Event('turbo:before-cache'));
    expect(isClosing()).toBe(false);
  });

  it('2.46 (KV-I1): a style read that throws ends the fold at once', () => {
    rendered();
    setOpen(true);
    vi.spyOn(window, 'getComputedStyle').mockImplementation(() => {
      throw new Error('boom');
    });
    setOpen(false);
    expect(isOpen()).toBe(false);
    expect(isClosing()).toBe(false);
    expect(get('u1').hasAttribute('inert')).toBe(false);
    expect(get('u1').hasAttribute('aria-hidden')).toBe(false);
  });

  it('2.46 (KV-I2): a focused tab gives the focus to the launcher before it becomes aria-hidden', () => {
    rendered();
    animated();
    setOpen(true);
    get('u2').focus();
    const focusWhenHidden: (Element | null)[] = [];
    const real = Element.prototype.setAttribute;
    vi.spyOn(Element.prototype, 'setAttribute').mockImplementation(function (this: Element, name: string, value: string) {
      if ('aria-hidden' === name && this.id === 'u2') {
        focusWhenHidden.push(document.activeElement);
      }
      real.call(this, name, value);
    });
    setOpen(false);
    expect(focusWhenHidden).toEqual([get('go')]);
    expect(document.activeElement).toBe(get('go'));
    expect(get('u2').getAttribute('aria-hidden')).toBe('true');
  });

  it('reads delays and durations in s and ms, the first of a list', () => {
    const tab = get('u1');
    vi.spyOn(window, 'getComputedStyle').mockReturnValue({ animationName: 'x', animationDuration: '180ms, 1s', animationDelay: '0.03s' } as unknown as CSSStyleDeclaration);
    expect(animationTime([tab])).toBe(210);
    vi.spyOn(window, 'getComputedStyle').mockReturnValue({ animationName: 'x', animationDuration: 'auto', animationDelay: '' } as unknown as CSSStyleDeclaration);
    expect(animationTime([tab])).toBe(0);
  });
});

describe('nowo-ui-edge-tabs chevron morph (2.46)', () => {
  // The markup of partials/_edge_tabs_launcher with icon: 'chevron' (tests/Unit/PageContract/EdgeTabsLauncher246Test.php).
  const CHEVRON = `
<div class="nowo-ui-edge-tab" data-nowo-ui-edge-tab-slot="0"><a id="m1" class="nowo-ui-edge-tab__link" href="#a">a</a></div>
<button id="chev" type="button" class="nowo-ui-edge-tabs-launcher nowo-ui-edge-tabs-launcher--chevron" data-nowo-ui-edge-tabs-launcher aria-expanded="false" aria-label="Page tools" title="Page tools">
    <span class="nowo-ui-edge-tab__icon" aria-hidden="true"><svg class="nowo-ui-edge-tabs-chevron" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path class="nowo-ui-edge-tabs-chevron__arm nowo-ui-edge-tabs-chevron__arm--upper" d="M15 6L9 12"/><path class="nowo-ui-edge-tabs-chevron__arm nowo-ui-edge-tabs-chevron__arm--lower" d="M9 12L15 18"/></svg></span>
</button>`;
  const get = (id: string): HTMLElement => document.getElementById(id) as HTMLElement;
  const arms = (): string[] => [...get('chev').querySelectorAll('path')].map((p) => `${p.getAttribute('class')}|${p.getAttribute('d')}`);

  beforeEach(() => {
    document.body.innerHTML = CHEVRON;
    bindNowoUiEdgeTabs();
  });

  afterEach(() => {
    setOpen(false);
    finishClosing();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    document.body.innerHTML = '';
  });

  it('the morph state is the launcher aria-expanded; the SVG stays aria-hidden and its arms untouched (CSS morphs them)', () => {
    const before = arms();
    expect(before).toEqual([
      'nowo-ui-edge-tabs-chevron__arm nowo-ui-edge-tabs-chevron__arm--upper|M15 6L9 12',
      'nowo-ui-edge-tabs-chevron__arm nowo-ui-edge-tabs-chevron__arm--lower|M9 12L15 18',
    ]);
    get('chev').dispatchEvent(new MouseEvent('click', { bubbles: true, detail: 1 }));
    expect(get('chev').getAttribute('aria-expanded')).toBe('true');
    expect(get('chev').classList.contains('nowo-ui-edge-tabs-launcher--chevron')).toBe(true);
    expect(get('chev').querySelector('svg')?.getAttribute('aria-hidden')).toBe('true');
    expect(get('chev').querySelector('svg')?.getAttribute('focusable')).toBe('false');
    expect(get('chev').getAttribute('aria-label')).toBe('Page tools');
    expect(arms()).toEqual(before);
    get('chev').dispatchEvent(new MouseEvent('click', { bubbles: true, detail: 1 }));
    expect(get('chev').getAttribute('aria-expanded')).toBe('false');
    expect(arms()).toEqual(before);
  });

  it('under reduced motion the state flips at once, with no fold phase', () => {
    vi.stubGlobal('matchMedia', vi.fn().mockReturnValue({ matches: true }));
    vi.spyOn(Element.prototype, 'getClientRects').mockImplementation(() => [{}] as unknown as DOMRectList);
    setOpen(true);
    expect(get('chev').getAttribute('aria-expanded')).toBe('true');
    setOpen(false);
    expect(get('chev').getAttribute('aria-expanded')).toBe('false');
    expect(isClosing()).toBe(false);
    expect(get('m1').hasAttribute('inert')).toBe(false);
  });
});
