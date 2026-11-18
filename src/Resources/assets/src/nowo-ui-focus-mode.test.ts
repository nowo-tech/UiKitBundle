import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as core from './nowo-ui-focus-mode-core';
import * as mod from './nowo-ui-focus-mode';

const el = (id: string): HTMLElement => document.getElementById(id) as HTMLElement;
function click(target: Element, init: MouseEventInit = {}): MouseEvent {
  const event = new MouseEvent('click', { bubbles: true, cancelable: true, ...init });
  target.dispatchEvent(event);
  return event;
}
function key(target: Element, name: string, init: KeyboardEventInit = {}): KeyboardEvent {
  const event = new KeyboardEvent('keydown', { key: name, bubbles: true, cancelable: true, ...init });
  target.dispatchEvent(event);
  return event;
}
const pressF = (target: Element = document.body, init: KeyboardEventInit = {}): KeyboardEvent => key(target, 'f', { code: 'KeyF', ...init });

const MARKUP = `
  <div data-nowo-ui-shell class="nowo-ui-shell" id="shell" data-nowo-ui-focus-regions="sidebar header footer side_panels edge_tabs dock" data-nowo-ui-focus-keep-list="search">
    <header id="hdr"><button type="button" id="burger">menu</button><div id="hdr-end"><input id="search" data-nowo-ui-focus-keep="search"><span id="chip" data-nowo-ui-focus-keep="timer_chip">00:10</span></div></header>
    <aside id="aside" data-nowo-ui-aside><a href="/x" id="nav">x</a></aside>
    <div class="nowo-ui-main" id="main">
      <div data-nowo-ui-focus-bar id="bar"><button type="button" id="fs" data-nowo-ui-focus-mode="fullscreen">fs</button><button type="button" id="exit" data-nowo-ui-focus-mode="exit">exit</button></div>
      <p id="status" role="status" aria-live="polite" data-nowo-ui-focus-status data-nowo-ui-focus-msg-on="Modo enfoque activado"></p>
      <div id="area" data-nowo-ui-focus-area="wiki">
        <button type="button" id="toggle" data-nowo-ui-focus-mode>Focus</button>
        <a href="#focus" id="link-toggle" data-nowo-ui-focus-mode="toggle">Focus link</a>
        <textarea id="editor"></textarea><div id="ce" contenteditable="true">x</div><select id="sel"><option>a</option></select>
        <aside id="panel" data-nowo-ui-side-panel inert>comments</aside>
        <button type="button" id="panel-toggle" data-nowo-ui-side-panel-toggle>c</button>
      </div>
    </div>
    <footer id="ftr">footer</footer>
    <div id="backdrop" data-nowo-ui-aside-backdrop></div>
  </div>
  <div id="dock" data-nowo-ui-focus-region="dock">dock</div>
  <div id="edge" data-nowo-ui-edge-tabs-launcher></div>`;

type Detail = { level: string; previous: string; area: string | null; source: string };

function fullscreen(enabled: boolean, request?: (this: HTMLElement) => Promise<void> | void): { exit: ReturnType<typeof vi.fn>; setElement: (value: Element | null) => void } {
  let current: Element | null = null;
  Object.defineProperty(document, 'fullscreenEnabled', { configurable: true, get: () => enabled });
  Object.defineProperty(document, 'fullscreenElement', { configurable: true, get: () => current });
  const exit = vi.fn(() => {
    current = null;
    return Promise.resolve();
  });
  Object.defineProperty(document, 'exitFullscreen', { configurable: true, writable: true, value: exit });
  if (request) {
    Object.defineProperty(HTMLElement.prototype, 'requestFullscreen', { configurable: true, writable: true, value: request });
  }

  return { exit, setElement: (value) => (current = value) };
}

describe('nowo-ui-focus-mode (2.55)', () => {
  let events: Detail[];
  const listener = (e: Event): void => {
    events.push((e as CustomEvent<Detail>).detail);
  };

  beforeEach(() => {
    localStorage.clear();
    document.body.innerHTML = MARKUP;
    document.documentElement.removeAttribute(core.HTML_STATE_ATTR);
    fullscreen(false);
    events = [];
    document.addEventListener('nowo-ui:focus-mode:change', listener);
    mod.bindNowoUiFocusMode();
    mod.start();
  });

  afterEach(() => {
    document.removeEventListener('nowo-ui:focus-mode:change', listener);
    if (document.getElementById('shell')) mod.exit(el('shell'));
    document.body.innerHTML = '';
    vi.restoreAllMocks();
    delete (HTMLElement.prototype as { requestFullscreen?: unknown }).requestFullscreen;
  });

  it('enters level 1 from the toggle: state, inert regions, kept elements, aria-pressed, storage, live region, event', () => {
    el('toggle').focus();
    const event = click(el('toggle'));

    expect(event.defaultPrevented).toBe(true);
    expect(el('shell').getAttribute('data-focus-mode')).toBe('on');
    expect(document.documentElement.getAttribute('data-nowo-ui-focus-state')).toBe('on');
    expect(el('toggle').getAttribute('aria-pressed')).toBe('true');
    expect(el('fs').getAttribute('aria-pressed')).toBe('false');
    for (const id of ['aside', 'ftr', 'backdrop', 'dock', 'edge', 'panel-toggle', 'burger', 'chip']) {
      expect(el(id).getAttribute('data-nowo-ui-focus-hidden'), id).toBe('inert');
      expect(el(id).hasAttribute('inert'), id).toBe(true);
    }
    // The header holds a kept element (search): only its other branches go.
    expect(el('hdr').hasAttribute('data-nowo-ui-focus-hidden')).toBe(false);
    expect(el('hdr').hasAttribute('data-nowo-ui-focus-partial')).toBe(true);
    expect(el('hdr-end').hasAttribute('data-nowo-ui-focus-partial')).toBe(true);
    expect(el('search').hasAttribute('inert')).toBe(false);
    // Already inert before: recorded as such.
    expect(el('panel').getAttribute('data-nowo-ui-focus-hidden')).toBe('');
    // The work area, the bar and the live region are never hidden.
    for (const id of ['main', 'bar', 'status', 'toggle']) expect(el(id).closest('[data-nowo-ui-focus-hidden]'), id).toBeNull();
    expect(localStorage.getItem('nowo-ui-focus:wiki')).toBe('on');
    expect(el('status').textContent).toBe('Modo enfoque activado');
    expect(document.activeElement).toBe(el('toggle'));
    expect(events).toEqual([{ level: 'on', previous: 'off', area: 'wiki', source: 'toggle' }]);
  });

  it('leaves level 1 from the toggle and restores exactly what it hid', () => {
    click(el('toggle'));
    click(el('toggle'));

    expect(el('shell').hasAttribute('data-focus-mode')).toBe(false);
    expect(document.documentElement.hasAttribute('data-nowo-ui-focus-state')).toBe(false);
    expect(document.querySelectorAll('[data-nowo-ui-focus-hidden], [data-nowo-ui-focus-partial]')).toHaveLength(0);
    expect(el('aside').hasAttribute('inert')).toBe(false);
    expect(el('panel').hasAttribute('inert')).toBe(true);
    expect(el('toggle').getAttribute('aria-pressed')).toBe('false');
    expect(localStorage.getItem('nowo-ui-focus:wiki')).toBeNull();
    expect(el('status').textContent).toBe(core.MESSAGES.off);
    expect(events.map((e) => e.level)).toEqual(['on', 'off']);
  });

  it('moves the focus to the exit control only when the focused element was hidden, and back to the toggle on exit', () => {
    el('burger').focus();
    expect(mod.api.enter()).toBe(true);
    expect(mod.api.enter()).toBe(false);
    expect(document.activeElement).toBe(el('exit'));

    click(el('exit'));
    expect(mod.api.level()).toBe('off');
    expect(document.activeElement).toBe(el('toggle'));
    expect(mod.api.exit()).toBe(false);
  });

  it('keeps the focus in place when nothing focused was hidden (editor)', () => {
    el('editor').focus();
    mod.api.toggle();
    expect(document.activeElement).toBe(el('editor'));
    expect(mod.api.toggle()).toBe('off');
    expect(document.activeElement).toBe(el('editor'));
  });

  it('F toggles level 1 only with nothing typed, no modifier, no repeat and a toggle on the page', () => {
    expect(pressF().defaultPrevented).toBe(true);
    expect(mod.api.level()).toBe('on');
    expect(events[0]?.source).toBe('shortcut');
    pressF();
    expect(mod.api.level()).toBe('off');

    // Text fields and (I6, WCAG 2.1.4) links, buttons and other controls keep the plain key.
    for (const id of ['editor', 'ce', 'sel', 'search', 'toggle', 'link-toggle', 'nav']) {
      el(id).focus();
      expect(pressF(el(id)).defaultPrevented, id).toBe(false);
    }
    (document.activeElement as HTMLElement | null)?.blur();
    for (const init of [{ ctrlKey: true }, { metaKey: true }, { altKey: true }, { shiftKey: true }, { repeat: true }, { isComposing: true }]) {
      expect(pressF(document.body, init).defaultPrevented, JSON.stringify(init)).toBe(false);
    }
    expect(key(document.body, 'g', { code: 'KeyG' }).defaultPrevented).toBe(false);
    expect(mod.api.level()).toBe('off');

    // A key handled before (defaultPrevented) is left alone.
    const stop = (e: Event): void => e.preventDefault();
    el('main').addEventListener('keydown', stop);
    pressF(el('main'));
    el('main').removeEventListener('keydown', stop);
    expect(mod.api.level()).toBe('off');

    // No page toggle (the bar's own controls do not count): F does nothing.
    el('toggle').remove();
    el('link-toggle').remove();
    expect(pressF().defaultPrevented).toBe(false);
  });

  it('F leaves dialogs and menus alone; Ctrl+K is never touched', () => {
    document.body.insertAdjacentHTML('beforeend', '<dialog id="dlg" open><button id="in-dlg">x</button></dialog>');
    expect(pressF(el('in-dlg')).defaultPrevented).toBe(false);
    el('dlg').remove();
    document.body.insertAdjacentHTML('beforeend', '<div role="menu" id="menu"><button id="mi">x</button></div>');
    expect(pressF(el('mi')).defaultPrevented).toBe(false);
    expect(key(document.body, 'k', { code: 'KeyK', ctrlKey: true }).defaultPrevented).toBe(false);
    expect(mod.api.level()).toBe('off');
  });

  it('honours a configured shortcut (with modifiers also while typing) and a disabled one', () => {
    el('shell').setAttribute('data-nowo-ui-focus-shortcut', 'Alt+Shift+F');
    el('editor').focus();
    expect(pressF(el('editor')).defaultPrevented).toBe(false);
    expect(pressF(el('editor'), { altKey: true, shiftKey: true }).defaultPrevented).toBe(true);
    expect(mod.api.level()).toBe('on');
    mod.api.exit();

    el('shell').setAttribute('data-nowo-ui-focus-shortcut', 'none');
    (document.activeElement as HTMLElement | null)?.blur();
    expect(pressF().defaultPrevented).toBe(false);
    expect(mod.api.level()).toBe('off');
  });

  it('Escape leaves focus mode only when nothing else owns it', () => {
    expect(key(document.body, 'Escape').defaultPrevented).toBe(false);
    mod.api.enter();

    document.body.insertAdjacentHTML('beforeend', '<dialog id="dlg" open><button id="in-dlg">x</button></dialog>');
    expect(key(el('in-dlg'), 'Escape').defaultPrevented).toBe(false);
    expect(key(document.body, 'Escape').defaultPrevented).toBe(false);
    el('dlg').remove();

    document.body.insertAdjacentHTML('beforeend', '<button id="combo" role="combobox" aria-expanded="true">c</button>');
    el('combo').focus();
    expect(key(el('combo'), 'Escape').defaultPrevented).toBe(false);
    el('combo').remove();

    document.body.insertAdjacentHTML('beforeend', '<details id="menu" data-nowo-ui-menu open><summary id="sum">m</summary></details>');
    expect(key(el('sum'), 'Escape').defaultPrevented).toBe(false);
    el('menu').remove();

    el('shell').classList.add('is-aside-open-mobile');
    expect(key(document.body, 'Escape').defaultPrevented).toBe(false);
    el('shell').classList.remove('is-aside-open-mobile');

    expect(key(document.body, 'Escape', { shiftKey: true }).defaultPrevented).toBe(false);
    const stop = (e: Event): void => e.preventDefault();
    el('main').addEventListener('keydown', stop);
    key(el('main'), 'Escape');
    el('main').removeEventListener('keydown', stop);
    expect(mod.api.level()).toBe('on');

    // A text field keeps its Escape (SK-L3): the editor, the kept search field.
    for (const id of ['editor', 'search', 'ce']) {
      el(id).focus();
      expect(key(el(id), 'Escape').defaultPrevented, id).toBe(false);
    }
    expect(mod.api.level()).toBe('on');

    // From a button (or nothing focused) Escape leaves focus mode and the focus stays.
    el('toggle').focus();
    expect(key(el('toggle'), 'Escape').defaultPrevented).toBe(true);
    expect(mod.api.level()).toBe('off');
    expect(document.activeElement).toBe(el('toggle'));
    expect(events[events.length - 1]?.source).toBe('escape');
  });

  it('works without storage (every access throws)', () => {
    vi.spyOn(window, 'localStorage', 'get').mockImplementation(() => {
      throw new DOMException('denied', 'SecurityError');
    });

    expect(() => mod.start()).not.toThrow();
    click(el('toggle'));
    expect(mod.api.level()).toBe('on');
    click(el('toggle'));
    expect(mod.api.level()).toBe('off');
    expect(core.readRemembered('wiki')).toBe(false);
    expect(core.remember('wiki', true)).toBe(false);
  });

  it('restores the remembered area quietly on start and after a Turbo visit', () => {
    localStorage.setItem('nowo-ui-focus:wiki', 'on');
    mod.start();
    expect(mod.api.level()).toBe('on');
    expect(el('status').textContent).toBe('');
    expect(el('aside').hasAttribute('inert')).toBe(true);
    expect(events).toEqual([{ level: 'on', previous: 'off', area: 'wiki', source: 'restore' }]);

    // Turbo renders a new body: the state comes back from storage.
    document.body.innerHTML = MARKUP;
    document.dispatchEvent(new Event('turbo:load'));
    expect(mod.api.level()).toBe('on');
    expect(el('aside').hasAttribute('inert')).toBe(true);

    // Another area is not affected.
    document.body.innerHTML = MARKUP.replace('data-nowo-ui-focus-area="wiki"', 'data-nowo-ui-focus-area="tasks"');
    mod.start();
    expect(mod.api.level()).toBe('off');
    expect(document.documentElement.hasAttribute('data-nowo-ui-focus-state')).toBe(false);
  });

  it('applies a server-rendered state and never claims fullscreen without the browser', () => {
    document.body.innerHTML = MARKUP.replace('id="shell"', 'id="shell" data-focus-mode="fullscreen"');
    mod.start();
    expect(el('shell').getAttribute('data-focus-mode')).toBe('on');
    expect(document.documentElement.getAttribute('data-nowo-ui-focus-state')).toBe('on');
    expect(el('aside').hasAttribute('inert')).toBe(true);
    expect(el('toggle').getAttribute('aria-pressed')).toBe('true');
    // A frame load inside focus mode hides its new regions too.
    el('main').insertAdjacentHTML('beforeend', '<aside id="late" data-nowo-ui-side-panel>late</aside>');
    document.dispatchEvent(new Event('turbo:frame-load'));
    expect(el('late').hasAttribute('inert')).toBe(true);
  });

  it('stays in level 1 without an error when fullscreen is not supported, and hides the fullscreen controls', () => {
    mod.start();
    expect(el('fs').hidden).toBe(true);
    click(el('fs'));
    expect(mod.api.level()).toBe('on');
    expect(el('status').textContent).toBe(core.MESSAGES.fullscreen_unavailable);
    expect(mod.api.fullscreen()).toBe(false);
  });

  it('enters fullscreen on the shell and follows the browser out of it (Escape / F11)', async () => {
    const fs = fullscreen(true, function request(this: HTMLElement) {
      fs.setElement(this);
      document.dispatchEvent(new Event('fullscreenchange'));
      return Promise.resolve();
    });
    document.body.innerHTML = MARKUP;
    mod.start();
    expect(el('fs').hidden).toBe(false);

    click(el('fs'));
    expect(el('shell').getAttribute('data-focus-mode')).toBe('fullscreen');
    expect(document.documentElement.getAttribute('data-nowo-ui-focus-state')).toBe('fullscreen');
    expect(el('fs').getAttribute('aria-pressed')).toBe('true');
    expect(el('toggle').getAttribute('aria-pressed')).toBe('true');
    expect(el('status').textContent).toBe(core.MESSAGES.fullscreen);
    expect(events.map((e) => [e.level, e.source])).toEqual([
      ['on', 'toggle'],
      ['fullscreen', 'fullscreenchange'],
    ]);

    // The browser leaves fullscreen by itself: back to level 1, not out of focus mode.
    fs.setElement(null);
    document.dispatchEvent(new Event('fullscreenchange'));
    expect(mod.api.level()).toBe('on');
    expect(el('status').textContent).toBe(core.MESSAGES.fullscreen_off);
    expect(el('aside').hasAttribute('inert')).toBe(true);

    // Escape in fullscreen (where the page sees it) leaves fullscreen first, then focus mode.
    click(el('fs'));
    expect(mod.api.level()).toBe('fullscreen');
    expect(key(document.body, 'Escape').defaultPrevented).toBe(true);
    expect(fs.exit).toHaveBeenCalledTimes(1);
    document.dispatchEvent(new Event('fullscreenchange'));
    expect(mod.api.level()).toBe('on');
    key(document.body, 'Escape');
    expect(mod.api.level()).toBe('off');

    // The fullscreen control leaves fullscreen; exiting focus mode from fullscreen leaves both.
    click(el('fs'));
    click(el('fs'));
    expect(fs.exit).toHaveBeenCalledTimes(2);
    document.dispatchEvent(new Event('fullscreenchange'));
    click(el('fs'));
    click(el('exit'));
    expect(fs.exit).toHaveBeenCalledTimes(3);
    expect(mod.api.level()).toBe('off');
    await Promise.resolve();
  });

  it('a fullscreen request the browser refuses (rejected or thrown) keeps level 1', async () => {
    fullscreen(true, () => Promise.reject(new Error('denied')));
    mod.start();
    click(el('fs'));
    await Promise.resolve();
    await Promise.resolve();
    expect(mod.api.level()).toBe('on');
    expect(el('status').textContent).toBe(core.MESSAGES.fullscreen_unavailable);
    mod.api.exit();

    fullscreen(true, () => {
      throw new Error('not allowed');
    });
    expect(mod.api.fullscreen()).toBe(false);
    expect(mod.api.level()).toBe('on');
    expect(el('status').textContent).toBe(core.MESSAGES.fullscreen_unavailable);
    mod.api.exit();

    // A browser whose request returns nothing (old engines) is accepted as is.
    fullscreen(true, () => undefined);
    expect(mod.api.fullscreen()).toBe(true);
    // The config can switch level 2 off.
    mod.api.exit();
    el('shell').setAttribute('data-nowo-ui-focus-fullscreen', 'false');
    expect(mod.api.fullscreen()).toBe(false);
  });

  it('syncs a fullscreen entered without the kit, and ignores shells without focus mode', () => {
    const fs = fullscreen(true);
    document.body.insertAdjacentHTML('beforeend', '<div data-nowo-ui-shell id="plain"></div>');
    fs.setElement(el('shell'));
    document.dispatchEvent(new Event('fullscreenchange'));
    expect(mod.api.level()).toBe('fullscreen');
    expect(el('aside').hasAttribute('inert')).toBe(true);
    expect(el('plain').hasAttribute('data-focus-mode')).toBe(false);
    expect(mod.leaveFullscreen(el('shell'))).toBe(true);
    document.dispatchEvent(new Event('fullscreenchange'));
    expect(mod.leaveFullscreen(el('shell'))).toBe(false);
  });

  it('announces a repeated text again and creates the live region when the page has none', () => {
    expect(mod.announce(el('shell'), 'off')).toBe(core.MESSAGES.off);
    mod.announce(el('shell'), 'off');
    expect(el('status').textContent).toBe(`${core.MESSAGES.off} `);
    mod.announce(el('shell'), 'off');
    expect(el('status').textContent).toBe(core.MESSAGES.off);

    el('status').remove();
    mod.announce(el('shell'), 'on');
    const created = el('shell').querySelector('[data-nowo-ui-focus-status]') as HTMLElement;
    expect(created.getAttribute('role')).toBe('status');
    expect(created.getAttribute('aria-live')).toBe('polite');
    expect(created.className).toBe('visually-hidden');
    expect(created.textContent).toBe(core.MESSAGES.on);
  });

  it('link toggles: a modified click keeps the browser behaviour; toggles without a focus-mode shell do nothing', () => {
    expect(click(el('link-toggle'), { ctrlKey: true }).defaultPrevented).toBe(false);
    expect(click(el('link-toggle'), { button: 1 }).defaultPrevented).toBe(false);
    expect(click(el('link-toggle')).defaultPrevented).toBe(true);
    expect(mod.api.level()).toBe('on');
    click(el('main'));
    expect(mod.api.level()).toBe('on');

    document.body.innerHTML = '<div data-nowo-ui-shell id="plain"><button id="t" data-nowo-ui-focus-mode>t</button></div>';
    expect(click(el('t')).defaultPrevented).toBe(false);
    expect(pressF().defaultPrevented).toBe(false);
    expect(mod.api.level()).toBe('off');
    expect(() => mod.api.enter()).toThrow('No focus-mode shell');
    document.body.dispatchEvent(new MouseEvent('click', { bubbles: true }));
  });
});

describe('nowo-ui-focus-mode-core (2.55)', () => {
  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('parses name lists, shortcuts, area keys and levels', () => {
    expect(core.parseList(' sidebar, header  header Bad 9x dock_2 ')).toEqual(['sidebar', 'header', 'dock_2']);
    expect(core.parseList(null)).toEqual([]);
    document.body.innerHTML = '<div id="s" data-nowo-ui-focus-shortcut=" "></div><div id="n"></div><div id="x" data-focus-mode="weird"></div>';
    expect(core.shortcutOf(el('s'))).toBeNull();
    expect(core.shortcutOf(el('n'))).toBe('F');
    expect(core.levelOf(el('x'))).toBe('on');
    expect(core.levelOf(el('n'))).toBe('off');
    expect(core.areaKey('wiki:space-1')).toBe('wiki:space-1');
    expect(core.areaKey('bad key')).toBeNull();
    expect(core.areaKey(undefined)).toBeNull();
    expect(core.areaOf(null)).toBeNull();
    expect(core.isEnabled(null)).toBe(false);
    expect(core.messageAttr('fullscreen_unavailable')).toBe('data-nowo-ui-focus-msg-fullscreen-unavailable');
    expect(core.DEFAULT_REGIONS).toEqual(['sidebar', 'header', 'footer', 'side_panels', 'edge_tabs', 'dock']);
  });

  it('knows typing targets and overlays', () => {
    document.body.innerHTML = '<input id="i"><div contenteditable="true"><span id="in-ce">x</span></div><div contenteditable="false" id="off">x</div><button id="b">b</button><div popover id="pop"><a id="pl" href="#">x</a></div>';
    expect(core.isTyping(el('i'))).toBe(true);
    expect(core.isTyping(el('in-ce'))).toBe(true);
    expect(core.isTyping(el('off'))).toBe(false);
    expect(core.isTyping(el('b'))).toBe(false);
    expect(core.isTyping(window)).toBe(false);
    expect(core.overlayOwnsKeys(el('pl'))).toBe(true);
    expect(core.overlayOwnsKeys(el('b'))).toBe(false);
    expect(core.overlayOwnsKeys(null)).toBe(false);
  });

  it('never hides the shell inside a marked wrapper, nor a region that is itself kept', () => {
    document.body.innerHTML = `
      <div id="wrap" data-nowo-ui-focus-region="dock"><nav id="side">side</nav>
        <div data-nowo-ui-shell id="shell" data-nowo-ui-focus-regions="dock timer" data-nowo-ui-focus-keep-list="timer"><main id="m">m</main></div>
      </div>
      <span id="timer" data-nowo-ui-focus-region="timer" data-nowo-ui-focus-keep="timer">t</span>
      <div data-nowo-ui-focus-region="unlisted" id="unlisted">u</div>`;
    const hidden = core.hideRegions(el('shell'));
    expect(hidden).toEqual([el('side')]);
    expect(el('wrap').hasAttribute('data-nowo-ui-focus-partial')).toBe(true);
    expect(el('timer').hasAttribute('data-nowo-ui-focus-hidden')).toBe(false);
    expect(el('unlisted').hasAttribute('data-nowo-ui-focus-hidden')).toBe(false);
    // Idempotent: a second pass hides nothing more.
    expect(core.hideRegions(el('shell'))).toEqual([el('side')]);
    core.restoreRegions();
    expect(el('side').hasAttribute('inert')).toBe(false);
    expect(el('wrap').hasAttribute('data-nowo-ui-focus-partial')).toBe(false);
    expect(core.regionElements(el('shell'), 'nothing')).toEqual([]);
    expect(core.regionElements(el('shell'), 'constructor')).toEqual([]);
  });
});

describe('nowo-ui-focus-mode audit SK fixes', () => {
  beforeEach(() => {
    localStorage.clear();
    document.body.innerHTML = MARKUP;
    fullscreen(false);
    mod.bindNowoUiFocusMode();
    mod.start();
  });

  afterEach(() => {
    if (document.getElementById('shell')) mod.exit(el('shell'));
    document.body.innerHTML = '';
    vi.restoreAllMocks();
  });

  it('SK-M2: a second copy of the script binds nothing more, and the toggle still enters once', async () => {
    vi.resetModules();
    const second = await import('./nowo-ui-focus-mode');
    expect(second).not.toBe(mod);
    expect((window as unknown as Record<symbol, unknown>)[Symbol.for('nowo-ui.focus-mode.bound')]).toBe(true);
    expect((window as unknown as { NowoUiFocusMode: unknown }).NowoUiFocusMode).toBe(second.api);

    click(el('toggle'));
    expect(mod.api.level()).toBe('on');
    click(el('toggle'));
    expect(mod.api.level()).toBe('off');

    // A click another handler already took (defaultPrevented) toggles nothing.
    const stop = (e: Event): void => e.preventDefault();
    el('area').addEventListener('click', stop);
    click(el('toggle'));
    el('area').removeEventListener('click', stop);
    expect(mod.api.level()).toBe('off');
  });

  it('SK-D1: a second copy loaded while focus mode is on (remembered area, Turbo) leaves nothing inert behind', async () => {
    mod.api.exit();
    localStorage.setItem('nowo-ui-focus:wiki', 'on');
    document.body.innerHTML = MARKUP;
    document.dispatchEvent(new Event('turbo:load'));
    expect(mod.api.level()).toBe('on');

    vi.resetModules();
    const second = await import('./nowo-ui-focus-mode');
    await Promise.resolve();
    const regions = ['aside', 'ftr', 'backdrop', 'dock', 'edge', 'burger', 'chip'];
    const clean = (): void => {
      for (const id of regions) {
        expect(el(id).hasAttribute('inert'), id).toBe(false);
        expect(el(id).hasAttribute('data-nowo-ui-focus-hidden'), id).toBe(false);
      }
      expect(el('panel').hasAttribute('inert')).toBe(true); // inert before focus mode: stays
    };

    for (const api of [mod.api, second.api]) {
      api.exit();
      await Promise.resolve();
      clean();
      api.enter();
      await Promise.resolve();
      expect(el('aside').getAttribute('data-nowo-ui-focus-hidden')).toBe('inert');
      api.exit();
      await Promise.resolve();
      clean();
      click(el('toggle'));
      await Promise.resolve();
      click(el('toggle'));
      await Promise.resolve();
      clean();
    }
  });

  it('SK-L4: inert written by the host during focus mode is the host\'s after exit', async () => {
    mod.api.enter();
    el('aside').setAttribute('inert', ''); // a modal library making the page inert
    el('ftr').removeAttribute('inert'); // the host wants the footer usable afterwards
    await Promise.resolve();
    expect(el('aside').getAttribute('data-nowo-ui-focus-hidden')).toBe('');
    expect(el('ftr').hasAttribute('inert')).toBe(true);

    el('dock').setAttribute('inert', ''); // still pending when focus mode ends
    mod.api.exit();
    expect(el('aside').hasAttribute('inert')).toBe(true);
    expect(el('dock').hasAttribute('inert')).toBe(true);
    expect(el('ftr').hasAttribute('inert')).toBe(false);
    expect(el('nav').closest('[inert]')).toBe(el('aside'));
  });

  it('I7 / I8: no stale <html> state without a focus-mode shell; the live region exists before the first change', () => {
    el('status').remove();
    mod.start();
    const region = el('shell').querySelector('[data-nowo-ui-focus-status]');
    expect(region?.textContent).toBe('');

    mod.api.enter();
    expect(document.documentElement.getAttribute('data-nowo-ui-focus-state')).toBe('on');
    document.body.innerHTML = '<main>another page</main>';
    document.dispatchEvent(new Event('turbo:load'));
    expect(document.documentElement.hasAttribute('data-nowo-ui-focus-state')).toBe(false);
  });
});

describe('nowo-ui-focus-mode bar offset tokens (2.57)', () => {
  const VAR = '--nowo-ui-focus-bar-height';
  const html = (): CSSStyleDeclaration => document.documentElement.style;
  let observers: { cb: () => void; disconnect: ReturnType<typeof vi.fn>; observe: ReturnType<typeof vi.fn> }[];

  const stubObserver = (): void => {
    class FakeObserver {
      disconnect = vi.fn();
      observe = vi.fn();
      constructor(public cb: () => void) {
        observers.push(this);
      }
    }
    vi.stubGlobal('ResizeObserver', FakeObserver);
  };
  const markup = (crumbs: boolean): string => `
    <div data-nowo-ui-shell id="shell" data-nowo-ui-focus-regions="header edge_tabs">
      <div data-nowo-ui-focus-bar id="bar" class="nowo-ui-focus-bar${crumbs ? ' nowo-ui-focus-bar--crumbs' : ''}"><button type="button" data-nowo-ui-focus-mode="exit">x</button></div>
      <button type="button" id="toggle" data-nowo-ui-focus-mode>f</button>
      <div class="nowo-ui-edge-tab" id="tab"><a href="/m" class="nowo-ui-edge-tab__link">m</a></div>
      <p class="nowo-ui-edge-link" id="inline">inline</p>
    </div>`;
  const height = (value: number): void => {
    vi.spyOn(el('bar'), 'getBoundingClientRect').mockReturnValue({ height: value } as DOMRect);
  };

  beforeEach(() => {
    observers = [];
    localStorage.clear();
    html().removeProperty(VAR);
    document.body.innerHTML = markup(true);
    mod.bindNowoUiFocusMode();
    mod.start();
  });

  afterEach(() => {
    if (document.getElementById('shell')) mod.exit(el('shell'));
    document.body.innerHTML = '';
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('sets the measured height of the crumbs bar on enter, updates it on resize and clears it on exit', () => {
    stubObserver();
    height(41.2);
    mod.enter(el('shell'), 'api', el('toggle'));
    expect(html().getPropertyValue(VAR)).toBe('42px');
    expect(observers).toHaveLength(1);
    expect(observers[0].observe).toHaveBeenCalledWith(el('bar'));
    height(64);
    observers[0].cb();
    expect(html().getPropertyValue(VAR)).toBe('64px');
    mod.exit(el('shell'));
    expect(observers[0].disconnect).toHaveBeenCalled();
    expect(html().getPropertyValue(VAR)).toBe('');
  });

  it('is 0px for the floating bar without crumbs', () => {
    document.body.innerHTML = markup(false);
    mod.enter(el('shell'), 'api', el('toggle'));
    expect(html().getPropertyValue(VAR)).toBe('0px');
  });

  it('measures once when ResizeObserver is missing', () => {
    vi.stubGlobal('ResizeObserver', undefined);
    height(30);
    mod.enter(el('shell'), 'api', el('toggle'));
    expect(html().getPropertyValue(VAR)).toBe('30px');
  });

  it('keeps the value for a server-rendered state and clears it when the page has no shell', () => {
    el('shell').setAttribute('data-focus-mode', 'on');
    height(20);
    mod.start();
    expect(html().getPropertyValue(VAR)).toBe('20px');
    document.body.innerHTML = '';
    mod.start();
    expect(html().getPropertyValue(VAR)).toBe('');
  });

  it('clears the value when the shell has no bar', () => {
    html().setProperty(VAR, '9px');
    document.querySelector('#bar')?.remove();
    mod.syncBarHeight(el('shell'), 'on');
    expect(html().getPropertyValue(VAR)).toBe('');
  });

  it('the edge_tabs region hides the tabs of the _edge_tab partial and the launcher, not the inline link', () => {
    mod.enter(el('shell'), 'api', el('toggle'));
    expect(el('tab').hasAttribute('data-nowo-ui-focus-hidden')).toBe(true);
    expect(el('inline').hasAttribute('data-nowo-ui-focus-hidden')).toBe(false);
    mod.exit(el('shell'));
    expect(el('tab').hasAttribute('data-nowo-ui-focus-hidden')).toBe(false);
  });
});
