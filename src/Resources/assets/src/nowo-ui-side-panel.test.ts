import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as mod from './nowo-ui-side-panel';

function key(el: Element, name: string, init: KeyboardEventInit = {}): KeyboardEvent {
  const event = new KeyboardEvent('keydown', { key: name, bubbles: true, cancelable: true, ...init });
  el.dispatchEvent(event);
  return event;
}
function click(el: Element): MouseEvent {
  const event = new MouseEvent('click', { bubbles: true, cancelable: true });
  el.dispatchEvent(event);
  return event;
}

const MARKUP = `
  <a href="?comments=1" role="button" id="t1" aria-controls="comments" aria-expanded="false" data-nowo-ui-side-panel-toggle data-nowo-ui-side-panel-shortcut="Alt+Shift+C">
    <span>Comments</span> <span data-nowo-ui-side-panel-badge hidden></span> <span data-nowo-ui-side-panel-badge-suffix hidden>open</span></a>
  <button type="button" id="t2" aria-controls="comments" aria-expanded="false" data-nowo-ui-side-panel-toggle><span data-nowo-ui-side-panel-badge hidden></span></button>
  <button type="button" id="orphan" aria-controls="nope" data-nowo-ui-side-panel-toggle data-nowo-ui-side-panel-shortcut="Alt+Shift+O">x</button>
  <button type="button" id="uncontrolled" data-nowo-ui-side-panel-toggle data-nowo-ui-side-panel-shortcut="Alt+Shift+U">u</button>
  <button type="button" id="digit" aria-controls="comments" data-nowo-ui-side-panel-toggle data-nowo-ui-side-panel-shortcut="Control+1">1</button>
  <button type="button" id="named" aria-controls="comments" data-nowo-ui-side-panel-toggle data-nowo-ui-side-panel-shortcut="F2">F2</button>
  <button type="button" id="plain" aria-controls="comments" data-nowo-ui-side-panel-toggle data-nowo-ui-side-panel-shortcut="c">c</button>
  <button type="button" id="broken" aria-controls="comments" data-nowo-ui-side-panel-toggle data-nowo-ui-side-panel-shortcut="+">b</button>
  <input id="field">
  <div data-nowo-ui-side-panel-container>
    <div data-nowo-ui-side-panel-main><p id="content">Content</p></div>
    <aside id="comments" data-nowo-ui-side-panel data-layout="inline" aria-labelledby="comments-title"
           data-nowo-ui-side-panel-opened="Comments opened" data-nowo-ui-side-panel-closed="Comments closed" hidden>
      <div><h2 id="comments-title" tabindex="-1">Comments</h2>
        <a href="?comments=0" role="button" id="close" data-nowo-ui-side-panel-close aria-label="Close comments">x</a></div>
      <div data-nowo-ui-side-panel-body><button type="button" id="first-thread">Thread</button><span hidden><button id="hidden-btn">h</button></span><textarea id="reply"></textarea></div>
    </aside>
    <div data-nowo-ui-side-panel-backdrop data-for="comments" hidden></div>
    <p role="status" data-nowo-ui-side-panel-status data-for="comments"></p>
  </div>
  <aside id="bare" data-nowo-ui-side-panel data-layout="overlay" hidden><div id="bare-body"></div></aside>
  <aside id="empty" data-nowo-ui-side-panel data-layout="overlay" hidden><h2 id="empty-title" tabindex="-1">Empty</h2></aside>
  <div id="not-a-panel"></div>`;

const last = <T>(list: T[]): T | undefined => list[list.length - 1];
const el = (id: string) => document.getElementById(id) as HTMLElement;
const status = () => document.querySelector('[data-nowo-ui-side-panel-status]')!.textContent;

function setDrawer(drawer: boolean): void {
  window.matchMedia = vi.fn().mockReturnValue({ matches: drawer }) as unknown as typeof window.matchMedia;
}

describe('nowo-ui-side-panel', () => {
  let events: { type: string; detail: unknown }[];

  beforeEach(() => {
    document.body.innerHTML = MARKUP;
    setDrawer(false);
    events = [];
    for (const type of ['nowo-ui:side-panel:open', 'nowo-ui:side-panel:close']) {
      document.addEventListener(type, (e) => events.push({ type, detail: (e as CustomEvent).detail }));
    }
    mod.bindNowoUiSidePanel();
  });

  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('opens from a toggle link in place: expanded toggles, focus on the heading, announced, event', () => {
    const event = click(el('t1'));
    expect(event.defaultPrevented).toBe(true);
    expect(el('comments').hidden).toBe(false);
    expect(el('t1').getAttribute('aria-expanded')).toBe('true');
    expect(el('t2').getAttribute('aria-expanded')).toBe('true');
    expect(document.activeElement).toBe(el('comments-title'));
    expect(status()).toBe('Comments opened');
    expect(last(events)).toEqual({ type: 'nowo-ui:side-panel:open', detail: { id: 'comments', source: 'toggle' } });
    expect(el('comments').hasAttribute('data-nowo-ui-side-panel-ready')).toBe(true);
    // Inline: no backdrop, the content stays usable.
    expect(document.querySelector<HTMLElement>('[data-nowo-ui-side-panel-backdrop]')!.hidden).toBe(true);
    expect(document.querySelector('[data-nowo-ui-side-panel-main]')!.hasAttribute('inert')).toBe(false);
  });

  it('closes with the toggle, the close link and Escape; focus returns to the opener', () => {
    click(el('t2'));
    click(el('t2'));
    expect(el('comments').hidden).toBe(true);
    expect(el('t1').getAttribute('aria-expanded')).toBe('false');
    expect(status()).toBe('Comments closed');
    expect(last(events)).toEqual({ type: 'nowo-ui:side-panel:close', detail: { id: 'comments', source: 'toggle' } });

    click(el('t2'));
    el('first-thread').focus();
    const close = click(el('close'));
    expect(close.defaultPrevented).toBe(true);
    expect(document.activeElement).toBe(el('t2'));
    expect(last(events)).toMatchObject({ detail: { source: 'close' } });

    click(el('t1'));
    el('reply').focus();
    const escape = key(el('reply'), 'Escape');
    expect(escape.defaultPrevented).toBe(true);
    expect(el('comments').hidden).toBe(true);
    expect(document.activeElement).toBe(el('t1'));
    expect(last(events)).toMatchObject({ detail: { source: 'escape' } });
  });

  it('Escape outside the panel, or inside an open dialog in it, does not close it', () => {
    click(el('t1'));
    el('field').focus();
    expect(key(el('field'), 'Escape').defaultPrevented).toBe(false);
    expect(el('comments').hidden).toBe(false);

    const dialog = document.createElement('dialog');
    dialog.setAttribute('open', '');
    dialog.innerHTML = '<button id="in-dialog">ok</button>';
    el('comments').querySelector('[data-nowo-ui-side-panel-body]')!.append(dialog);
    el('in-dialog').focus();
    expect(key(el('in-dialog'), 'Escape').defaultPrevented).toBe(false);
    expect(el('comments').hidden).toBe(false);
  });

  it('is an overlay drawer on phones: backdrop, inert content, Tab cycles, backdrop click closes', () => {
    setDrawer(true);
    click(el('t1'));
    const backdrop = document.querySelector<HTMLElement>('[data-nowo-ui-side-panel-backdrop]')!;
    expect(backdrop.hidden).toBe(false);
    expect(el('comments').hasAttribute('data-drawer')).toBe(true);
    expect(document.querySelector('[data-nowo-ui-side-panel-main]')!.hasAttribute('inert')).toBe(true);

    el('reply').focus();
    expect(key(el('reply'), 'Tab').defaultPrevented).toBe(true);
    expect(document.activeElement).toBe(el('close'));
    expect(key(el('close'), 'Tab', { shiftKey: true }).defaultPrevented).toBe(true);
    expect(document.activeElement).toBe(el('reply'));
    el('comments-title').focus();
    key(el('comments-title'), 'Tab', { shiftKey: true });
    expect(document.activeElement).toBe(el('reply'));
    el('first-thread').focus();
    expect(key(el('first-thread'), 'Tab').defaultPrevented).toBe(false);

    click(backdrop);
    expect(el('comments').hidden).toBe(true);
    expect(backdrop.hidden).toBe(true);
    expect(document.querySelector('[data-nowo-ui-side-panel-main]')!.hasAttribute('inert')).toBe(false);
    expect(last(events)).toMatchObject({ detail: { source: 'backdrop' } });
  });

  it('never removes an inert the host set on the main column', () => {
    setDrawer(true);
    const main = document.querySelector<HTMLElement>('[data-nowo-ui-side-panel-main]')!;
    main.setAttribute('inert', '');
    click(el('t1'));
    click(el('close'));
    expect(main.hasAttribute('inert')).toBe(true);
    // A container without a main column: nothing to make inert.
    main.remove();
    click(el('t1'));
    expect(el('comments').hasAttribute('data-drawer')).toBe(true);
  });

  it('keeps the main column inert while any drawer of the container is open', () => {
    document.body.innerHTML = `
      <div data-nowo-ui-side-panel-container>
        <div data-nowo-ui-side-panel-main><p>Content</p></div>
        <aside id="a" data-nowo-ui-side-panel data-layout="overlay" hidden><h2 id="a-title" tabindex="-1">A</h2></aside>
        <aside id="b" data-nowo-ui-side-panel data-layout="overlay" hidden><h2 id="b-title" tabindex="-1">B</h2></aside>
      </div>`;
    const main = document.querySelector<HTMLElement>('[data-nowo-ui-side-panel-main]')!;
    mod.openPanel(el('a'));
    mod.openPanel(el('b'));
    mod.closePanel(el('a'));
    expect(main.hasAttribute('inert')).toBe(true);
    mod.closePanel(el('b'));
    expect(main.hasAttribute('inert')).toBe(false);
    // Closing a panel that never owned it changes nothing.
    mod.closePanel(el('a'));
    expect(main.hasAttribute('inert')).toBe(false);
  });

  it('keeps an inert the host wrote after a drawer added it (sync and after observation)', async () => {
    setDrawer(true);
    const main = document.querySelector<HTMLElement>('[data-nowo-ui-side-panel-main]')!;
    click(el('t1'));
    main.setAttribute('inert', ''); // the host re-asserts it; closed before the observer callback runs
    click(el('close'));
    expect(main.hasAttribute('inert')).toBe(true);

    main.removeAttribute('inert');
    click(el('t1'));
    expect(main.hasAttribute('inert')).toBe(true);
    main.setAttribute('inert', '');
    await Promise.resolve(); // the observer callback has run
    click(el('close'));
    expect(main.hasAttribute('inert')).toBe(true);
  });

  it('an invalid focus selector falls back to the heading, with one consistent open', () => {
    let opens = 0;
    el('comments').addEventListener('nowo-ui:side-panel:open', () => opens++);
    expect(mod.api.open('comments', { focus: '[[nope' })).toBe(true);
    expect(el('comments').hidden).toBe(false);
    expect(document.activeElement).toBe(el('comments-title'));
    expect(opens).toBe(1);
    expect(status()).toBe('Comments opened');
    expect(mod.api.open('comments', { focus: '[[nope' })).toBe(false);
    expect(document.activeElement).toBe(el('comments-title'));
    expect(opens).toBe(1);
  });

  it('Escape still closes where the browser does not know :popover-open', () => {
    const original = Element.prototype.closest;
    const spy = vi.spyOn(Element.prototype, 'closest').mockImplementation(function (this: Element, selector: string) {
      if (selector.includes(':popover-open')) throw new SyntaxError('unknown pseudo-class');
      return original.call(this, selector);
    } as unknown as typeof original);
    click(el('t1'));
    el('reply').focus();
    expect(key(el('reply'), 'Escape').defaultPrevented).toBe(true);
    expect(el('comments').hidden).toBe(true);
    spy.mockRestore();
  });

  it('switches between inline and drawer on resize while open; Tab is free inline and in an empty drawer', () => {
    click(el('t1'));
    el('reply').focus();
    expect(key(el('reply'), 'Tab').defaultPrevented).toBe(false);
    // Focus in the content when the drawer takes over: it moves into the panel instead of being lost to inert.
    el('content').tabIndex = -1;
    el('content').focus();
    setDrawer(true);
    window.dispatchEvent(new Event('resize'));
    expect(el('comments').hasAttribute('data-drawer')).toBe(true);
    expect(document.activeElement).toBe(el('comments-title'));
    setDrawer(false);
    window.dispatchEvent(new Event('resize'));
    expect(el('comments').hasAttribute('data-drawer')).toBe(false);

    // An overlay panel without focusable content: Tab is left alone.
    mod.openPanel(el('bare'), { focus: false });
    const probe = document.createElement('span');
    probe.tabIndex = -1;
    el('bare-body').append(probe);
    probe.focus();
    expect(key(probe, 'Tab').defaultPrevented).toBe(false);
  });

  it('applies the drawer mode to panels rendered open, and works without matchMedia', () => {
    document.body.innerHTML = MARKUP.replace('data-layout="inline" aria-labelledby="comments-title"', 'data-layout="overlay" aria-labelledby="comments-title"').replace('Comments closed" hidden>', 'Comments closed">');
    mod.markSidePanelsReady();
    expect(el('comments').hasAttribute('data-drawer')).toBe(true);
    (window as unknown as { matchMedia: unknown }).matchMedia = undefined;
    expect(mod.isDrawer(el('empty'))).toBe(true);
    el('empty').dataset.layout = 'inline';
    expect(mod.isDrawer(el('empty'))).toBe(false);
  });

  it('Space activates role=button links; the shortcut toggles, not while typing without a modifier', () => {
    el('t1').focus();
    expect(key(el('t1'), ' ').defaultPrevented).toBe(true);
    expect(el('comments').hidden).toBe(false);

    el('field').focus();
    const shortcut = key(el('field'), 'C', { altKey: true, shiftKey: true, code: 'KeyC' });
    expect(shortcut.defaultPrevented).toBe(true);
    expect(el('comments').hidden).toBe(true);
    expect(last(events)).toMatchObject({ detail: { source: 'shortcut' } });

    // "c" alone: ignored while typing, works elsewhere.
    expect(key(el('field'), 'c', { code: 'KeyC' }).defaultPrevented).toBe(false);
    el('content').tabIndex = -1;
    el('content').focus();
    expect(key(el('content'), 'c', { code: 'KeyC' }).defaultPrevented).toBe(true);
    expect(el('comments').hidden).toBe(false);
    // A shortcut whose panel is missing is skipped; other keys do nothing.
    expect(key(el('content'), 'O', { altKey: true, shiftKey: true, code: 'KeyO' }).defaultPrevented).toBe(false);
    expect(key(el('content'), 'U', { altKey: true, shiftKey: true, code: 'KeyU' }).defaultPrevented).toBe(false);
    // No focused element at all.
    const spy = vi.spyOn(document, 'activeElement', 'get').mockReturnValue(null);
    expect(key(document.body, 'Escape').defaultPrevented).toBe(false);
    spy.mockRestore();
    expect(key(el('content'), 'x').defaultPrevented).toBe(false);
  });

  it('parses and matches shortcuts', () => {
    expect(mod.parseShortcut('Alt+Shift+C')).toEqual({ ctrl: false, alt: true, shift: true, meta: false, key: 'c' });
    expect(mod.parseShortcut('Ctrl + Meta + K')).toEqual({ ctrl: true, alt: false, shift: false, meta: true, key: 'k' });
    expect(mod.parseShortcut('')).toBeNull();
    expect(mod.parseShortcut(null)).toBeNull();
    const digit = mod.parseShortcut('Control+1')!;
    expect(mod.matchesShortcut(new KeyboardEvent('keydown', { key: '!', code: 'Digit1', ctrlKey: true }), digit)).toBe(true);
    expect(mod.matchesShortcut(new KeyboardEvent('keydown', { key: '1', ctrlKey: true }), digit)).toBe(true);
    expect(mod.matchesShortcut(new KeyboardEvent('keydown', { key: '2', code: 'Digit2', ctrlKey: true }), digit)).toBe(false);
    expect(mod.matchesShortcut(new KeyboardEvent('keydown', { key: 'F2' }), mod.parseShortcut('F2')!)).toBe(true);
    expect(mod.matchesShortcut(new KeyboardEvent('keydown', { key: 'ç', code: 'KeyC', altKey: true }), mod.parseShortcut('Alt+C')!)).toBe(true);
    expect(mod.matchesShortcut(new KeyboardEvent('keydown', { key: 'c' }), mod.parseShortcut('Alt+C')!)).toBe(false);
  });

  it('the API opens with a focus target, toggles, reports state and updates the count silently', () => {
    const api = (window as unknown as { NowoUiSidePanel: typeof mod.api }).NowoUiSidePanel;
    expect(api).toBe(mod.api);
    expect(api.open('comments', { focus: '#reply' })).toBe(true);
    expect(document.activeElement).toBe(el('reply'));
    expect(last(events)).toMatchObject({ detail: { source: 'api' } });
    // Already open: only the focus moves.
    expect(api.open('comments', { focus: el('first-thread') })).toBe(false);
    expect(document.activeElement).toBe(el('first-thread'));
    expect(api.isOpen('comments')).toBe(true);
    expect(api.toggle('comments')).toBe(false);
    // Focus was inside without an opener: back to the first toggle.
    expect(document.activeElement).toBe(el('t1'));
    expect(api.close('comments')).toBe(false);
    expect(api.toggle('comments')).toBe(true);
    expect(api.close('comments')).toBe(true);

    const before = status();
    api.setCount('comments', 3, 'open');
    expect(el('t1').querySelector<HTMLElement>('[data-nowo-ui-side-panel-badge]')!.textContent).toBe('3');
    expect(el('t1').querySelector<HTMLElement>('[data-nowo-ui-side-panel-badge]')!.hidden).toBe(false);
    expect(el('t1').querySelector<HTMLElement>('[data-nowo-ui-side-panel-badge-suffix]')!.hidden).toBe(false);
    expect(el('t2').querySelector<HTMLElement>('[data-nowo-ui-side-panel-badge]')!.textContent).toBe('3');
    api.setCount('comments', 0);
    expect(el('t1').querySelector<HTMLElement>('[data-nowo-ui-side-panel-badge]')!.hidden).toBe(true);
    expect(el('t1').querySelector<HTMLElement>('[data-nowo-ui-side-panel-badge-suffix]')!.textContent).toBe('open');
    expect(status()).toBe(before);

    expect(() => api.open('not-a-panel')).toThrow('No side panel #not-a-panel.');
    expect(mod.panelById('missing')).toBeNull();
  });

  it('repeats an announcement so it is read again; panels without a status or labels stay silent', () => {
    mod.announce(el('comments'), 'Hello');
    mod.announce(el('comments'), 'Hello');
    expect(status()).toBe('Hello ');
    mod.announce(el('comments'), '');
    expect(status()).toBe('Hello ');
    expect(mod.openPanel(el('empty'))).toBe(true);
    expect(document.activeElement).toBe(el('empty-title'));
    expect(mod.closePanel(el('empty'))).toBe(true);
    // Focus came back to nothing (no toggle): it stays where it was.
    expect(mod.togglePanel(el('bare'))).toBe(true);
    expect(mod.togglePanel(el('bare'))).toBe(false);
  });

  it('ignores clicks elsewhere and toggles for missing panels (the link then navigates)', () => {
    expect(click(el('content')).defaultPrevented).toBe(false);
    expect(click(el('orphan')).defaultPrevented).toBe(false);
    expect(click(el('uncontrolled')).defaultPrevented).toBe(false);
    document.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    const backdropless = document.createElement('div');
    backdropless.setAttribute('data-nowo-ui-side-panel-backdrop', '');
    document.body.append(backdropless);
    expect(click(backdropless).defaultPrevented).toBe(false);
    const stray = document.createElement('a');
    stray.setAttribute('data-nowo-ui-side-panel-close', '');
    document.body.append(stray);
    expect(click(stray).defaultPrevented).toBe(false);
  });

  it('binds once and waits for the DOM when loading', () => {
    const add = vi.spyOn(document, 'addEventListener');
    Object.defineProperty(document, 'readyState', { configurable: true, get: () => 'loading' });
    mod.bindNowoUiSidePanel();
    expect(add).toHaveBeenCalledWith('DOMContentLoaded', expect.any(Function), { once: true });
    expect(add).not.toHaveBeenCalledWith('click', expect.any(Function));
    const ready = add.mock.calls.find((call) => call[0] === 'DOMContentLoaded')![1] as () => void;
    ready();
    Object.defineProperty(document, 'readyState', { configurable: true, get: () => 'complete' });
    add.mockRestore();
  });
});

describe('nowo-ui-side-panel overlay (2.28)', () => {
  const SHELL = `
    <div data-nowo-ui-shell>
      <header id="hdr"><a href="/">Home</a></header>
      <aside id="nav" data-nowo-ui-aside><a href="/x">X</a></aside>
      <div class="nowo-ui-main">
        <button type="button" id="tog" aria-controls="drawer" data-nowo-ui-side-panel-toggle>Open</button>
        <div data-nowo-ui-side-panel-container>
          <div data-nowo-ui-side-panel-main id="main"><p>Main</p></div>
          <aside id="drawer" data-nowo-ui-side-panel data-layout="overlay" hidden><h2 id="drawer-title" tabindex="-1">D</h2><button id="in">x</button></aside>
          <div data-nowo-ui-side-panel-backdrop data-for="drawer" id="bd" hidden></div>
        </div>
        <aside id="keep" data-nowo-ui-side-panel data-layout="overlay" data-nowo-ui-side-panel-backdrop-close="false" hidden><h2 id="keep-title" tabindex="-1">K</h2></aside>
        <div data-nowo-ui-side-panel-backdrop data-for="keep" id="bd2" hidden></div>
      </div>
    </div>`;

  beforeEach(() => {
    document.body.innerHTML = SHELL;
    setDrawer(false);
    mod.bindNowoUiSidePanel();
  });

  afterEach(() => {
    window.NowoUiOverlay?.releaseAll();
    document.body.innerHTML = '';
  });

  it('makes the shell header and aside inert and sets the overlay marker while a drawer (no role on the aside)', () => {
    const opened: number[] = [];
    const onOpen = (e: Event): void => {
      opened.push((e as CustomEvent<{ depth: number }>).detail.depth);
    };
    document.addEventListener('nowo:overlay-open', onOpen);
    mod.openPanel(el('drawer'));
    document.removeEventListener('nowo:overlay-open', onOpen);
    expect(opened).toEqual([1]);
    expect(el('hdr').hasAttribute('inert')).toBe(true);
    expect(el('nav').hasAttribute('inert')).toBe(true);
    expect(el('main').hasAttribute('inert')).toBe(true);
    expect(el('drawer').hasAttribute('role')).toBe(false);
    expect(document.documentElement.getAttribute('data-nowo-overlay')).toBe('1');
    mod.closePanel(el('drawer'));
    expect(el('hdr').hasAttribute('inert')).toBe(false);
    expect(el('nav').hasAttribute('inert')).toBe(false);
    expect(document.documentElement.hasAttribute('data-nowo-overlay')).toBe(false);
  });

  it('releases when an inline panel stops being a drawer on resize', () => {
    el('drawer').setAttribute('data-layout', 'inline');
    setDrawer(true);
    mod.openPanel(el('drawer'));
    expect(el('hdr').hasAttribute('inert')).toBe(true);
    setDrawer(false);
    window.dispatchEvent(new Event('resize'));
    expect(el('hdr').hasAttribute('inert')).toBe(false);
  });

  it('closes on a backdrop click unless closeOnBackdrop is off for the panel', () => {
    expect(mod.closesOnBackdrop(el('drawer'))).toBe(true);
    expect(mod.closesOnBackdrop(el('keep'))).toBe(false);
    mod.openPanel(el('drawer'));
    click(el('bd'));
    expect(el('drawer').hidden).toBe(true);
    mod.openPanel(el('keep'));
    click(el('bd2'));
    expect(el('keep').hidden).toBe(false);
  });
});
