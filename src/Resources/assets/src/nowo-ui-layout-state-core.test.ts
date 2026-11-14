import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  applyStoredPanel,
  applyStoredRail,
  applyStoredStates,
  attributeSuffix,
  closeStartingDrawer,
  DRAWER_QUERIES,
  drawerQueryOf,
  mediaMatches,
  panelIsDrawer,
  persistKey,
  railHostOf,
  railIsCollapsed,
  readStored,
  rememberPanel,
  rememberRail,
  storedPanelOpen,
  storedRailCollapsed,
  watchStoredStates,
  writeStored,
} from './nowo-ui-layout-state-core';

const el = (id: string): HTMLElement => document.getElementById(id) as HTMLElement;

function setMedia(matching: string[]): void {
  window.matchMedia = vi.fn().mockImplementation((query: string) => ({ matches: matching.includes(query), media: query })) as unknown as typeof window.matchMedia;
}

function clearRoot(): void {
  for (const attr of Array.from(document.documentElement.attributes)) {
    if (attr.name.startsWith('data-nowo-ui-')) document.documentElement.removeAttribute(attr.name);
  }
}

describe('nowo-ui-layout-state-core (2.48)', () => {
  beforeEach(() => {
    localStorage.clear();
    setMedia([]);
    clearRoot();
  });

  afterEach(() => {
    document.body.innerHTML = '';
    vi.restoreAllMocks();
    clearRoot();
  });

  it('maps the drawer breakpoints (anything else is md) and reads the media queries', () => {
    document.body.innerHTML = '<aside id="a"></aside><aside id="b" data-drawer-below="lg"></aside><aside id="c" data-drawer-below="xl"></aside><aside id="d" data-drawer-below="2xl"></aside>';
    expect(drawerQueryOf(el('a'))).toBe(DRAWER_QUERIES.md);
    expect(drawerQueryOf(el('b'))).toBe('(max-width: 1199px)');
    expect(drawerQueryOf(el('c'))).toBe('(max-width: 1439px)');
    expect(drawerQueryOf(el('d'))).toBe('(max-width: 767px)');

    setMedia([DRAWER_QUERIES.lg, DRAWER_QUERIES.xl]);
    expect(panelIsDrawer(el('a'))).toBe(false);
    expect(panelIsDrawer(el('b'))).toBe(true);
    expect(panelIsDrawer(el('c'))).toBe(true);
    el('a').setAttribute('data-layout', 'overlay');
    expect(panelIsDrawer(el('a'))).toBe(true);

    (window as unknown as { matchMedia: unknown }).matchMedia = undefined;
    expect(mediaMatches(DRAWER_QUERIES.lg)).toBe(false);
  });

  it('accepts only short safe keys and derives the <html> attribute suffix', () => {
    expect(persistKey('wiki-comments')).toBe('wiki-comments');
    expect(persistKey('Space:1.tree_rail')).toBe('Space:1.tree_rail');
    for (const bad of [null, undefined, '', 'a b', 'x"y', 'a'.repeat(65)]) {
      expect(persistKey(bad as string | null)).toBeNull();
    }
    expect(attributeSuffix('Space:1.tree_rail')).toBe('space-1-tree-rail');
  });

  it('reads and writes storage, and survives a storage that throws (private mode)', () => {
    expect(writeStored('k', 'v')).toBe(true);
    expect(readStored('k')).toBe('v');
    const broken = {
      getItem: () => {
        throw new Error('denied');
      },
      setItem: () => {
        throw new Error('quota');
      },
    };
    vi.stubGlobal('localStorage', broken);
    try {
      expect(readStored('k')).toBeNull();
      expect(writeStored('k', 'w')).toBe(false);
      expect(storedPanelOpen('k')).toBeNull();
      expect(storedRailCollapsed('k')).toBeNull();
    } finally {
      vi.unstubAllGlobals();
    }
  });

  it('parses only the known stored values', () => {
    localStorage.setItem('nowo-ui-panel:a', 'open');
    localStorage.setItem('nowo-ui-panel:b', 'closed');
    localStorage.setItem('nowo-ui-panel:c', 'maybe');
    localStorage.setItem('nowo-ui-rail:a', 'collapsed');
    localStorage.setItem('nowo-ui-rail:b', 'expanded');
    localStorage.setItem('nowo-ui-rail:c', '1');
    expect([storedPanelOpen('a'), storedPanelOpen('b'), storedPanelOpen('c'), storedPanelOpen('none')]).toEqual([true, false, null, null]);
    expect([storedRailCollapsed('a'), storedRailCollapsed('b'), storedRailCollapsed('c')]).toEqual([true, false, null]);
  });

  it('applies a stored panel state, mirrors it on <html>, and never opens a drawer on load', () => {
    document.body.innerHTML = `<aside id="p" data-nowo-ui-side-panel data-nowo-ui-side-panel-persist="wiki-comments" hidden></aside>
      <aside id="q" data-nowo-ui-side-panel data-nowo-ui-side-panel-persist="other"></aside>
      <aside id="n" data-nowo-ui-side-panel data-nowo-ui-side-panel-persist="bad key" hidden></aside>
      <aside id="d" data-nowo-ui-side-panel data-drawer-below="lg" data-nowo-ui-side-panel-persist="drawer" hidden></aside>`;
    expect(applyStoredPanel(el('p'))).toBe(false); // nothing stored
    localStorage.setItem('nowo-ui-panel:wiki-comments', 'open');
    localStorage.setItem('nowo-ui-panel:other', 'closed');
    localStorage.setItem('nowo-ui-panel:drawer', 'open');
    expect(applyStoredPanel(el('p'))).toBe(true);
    expect(el('p').hidden).toBe(false);
    expect(document.documentElement.getAttribute('data-nowo-ui-panel-wiki-comments')).toBe('open');
    expect(applyStoredPanel(el('p'))).toBe(false); // already in that state
    expect(applyStoredPanel(el('q'))).toBe(true);
    expect(el('q').hidden).toBe(true);
    expect(applyStoredPanel(el('n'))).toBe(false);
    setMedia([DRAWER_QUERIES.lg]);
    expect(applyStoredPanel(el('d'))).toBe(false);
    expect(el('d').hidden).toBe(true);
  });

  it('remembers a panel only with a key, and a rail under its key', () => {
    document.body.innerHTML = '<aside id="p" data-nowo-ui-side-panel-persist="c"></aside><aside id="x"></aside>';
    rememberPanel(el('p'), true);
    rememberPanel(el('x'), true);
    expect(localStorage.getItem('nowo-ui-panel:c')).toBe('open');
    expect(localStorage.length).toBe(1);
    rememberPanel(el('p'), false);
    expect(localStorage.getItem('nowo-ui-panel:c')).toBe('closed');
    expect(document.documentElement.getAttribute('data-nowo-ui-panel-c')).toBe('closed');
    rememberRail('tree', true);
    expect(localStorage.getItem('nowo-ui-rail:tree')).toBe('collapsed');
    expect(document.documentElement.getAttribute('data-nowo-ui-rail-tree')).toBe('collapsed');
    rememberRail('tree', false);
    expect(localStorage.getItem('nowo-ui-rail:tree')).toBe('expanded');
  });

  it('finds the rail host from the toggle or from the element it controls', () => {
    document.body.innerHTML = `<aside id="h" data-nowo-ui-rail-host data-nowo-ui-rail="expanded"><button id="in" data-nowo-ui-rail-toggle aria-controls="b"></button><div id="b"></div></aside>
      <button id="out" data-nowo-ui-rail-toggle aria-controls="b"></button>
      <button id="lost" data-nowo-ui-rail-toggle aria-controls="nope"></button>
      <button id="none" data-nowo-ui-rail-toggle></button>`;
    expect(railHostOf(el('in'))).toBe(el('h'));
    expect(railHostOf(el('out'))).toBe(el('h'));
    expect(railHostOf(el('lost'))).toBeNull();
    expect(railHostOf(el('none'))).toBeNull();
    expect(railIsCollapsed(el('h'))).toBe(false);
  });

  it('applies a stored rail state unless a host store owns it, and waits for a host not parsed yet', () => {
    document.body.innerHTML = `<aside id="h" data-nowo-ui-rail-host data-nowo-ui-rail="expanded"><button id="t" data-nowo-ui-rail-toggle aria-expanded="true" data-nowo-ui-rail-persist="tree"></button></aside>
      <aside id="s" data-nowo-ui-rail-host data-nowo-ui-rail="expanded"><button id="srv" data-nowo-ui-rail-toggle data-nowo-ui-rail-persist="tree" data-nowo-ui-rail-state-url="/s"></button></aside>
      <button id="early" data-nowo-ui-rail-toggle aria-controls="later" data-nowo-ui-rail-persist="tree"></button>
      <button id="nokey" data-nowo-ui-rail-toggle></button>`;
    expect(applyStoredRail(el('t'))).toBe(false); // nothing stored
    localStorage.setItem('nowo-ui-rail:tree', 'collapsed');
    expect(applyStoredRail(el('t'))).toBe(true);
    expect(el('h').getAttribute('data-nowo-ui-rail')).toBe('collapsed');
    expect(el('t').getAttribute('aria-expanded')).toBe('false');
    expect(document.documentElement.getAttribute('data-nowo-ui-rail-tree')).toBe('collapsed');
    expect(applyStoredRail(el('srv'))).toBe(false);
    expect(el('s').getAttribute('data-nowo-ui-rail')).toBe('expanded');
    expect(applyStoredRail(el('early'))).toBeNull();
    expect(applyStoredRail(el('nokey'))).toBe(false);
  });

  it('applies every persisted element once per pass, retrying rails whose host is missing', () => {
    localStorage.setItem('nowo-ui-panel:c', 'open');
    localStorage.setItem('nowo-ui-rail:tree', 'collapsed');
    document.body.innerHTML = `<aside id="p" data-nowo-ui-side-panel data-nowo-ui-side-panel-persist="c" hidden></aside>
      <button id="early" data-nowo-ui-rail-toggle aria-controls="body" data-nowo-ui-rail-persist="tree"></button>`;
    applyStoredStates();
    expect(el('p').hidden).toBe(false);
    // Seen: a later change is not overwritten by another pass.
    el('p').hidden = true;
    applyStoredStates();
    expect(el('p').hidden).toBe(true);
    const host = document.createElement('aside');
    host.setAttribute('data-nowo-ui-rail-host', '');
    host.setAttribute('data-nowo-ui-rail', 'expanded');
    host.innerHTML = '<div id="body"></div>';
    document.body.appendChild(host);
    applyStoredStates();
    expect(host.getAttribute('data-nowo-ui-rail')).toBe('collapsed');
  });

  it('pre-paint: a server-open lg / xl panel starts closed under its breakpoint, before paint (audit LV-L2)', () => {
    setMedia([DRAWER_QUERIES.lg, DRAWER_QUERIES.xl]);
    document.body.innerHTML = `<aside id="lg" data-nowo-ui-side-panel data-layout="inline" data-drawer-below="lg"></aside>
      <aside id="xl" data-nowo-ui-side-panel data-layout="inline" data-drawer-below="xl"></aside>
      <aside id="md" data-nowo-ui-side-panel data-layout="inline" data-drawer-below="md"></aside>
      <aside id="ov" data-nowo-ui-side-panel data-layout="overlay" data-drawer-below="lg"></aside>
      <aside id="ready" data-nowo-ui-side-panel data-layout="inline" data-drawer-below="lg" data-nowo-ui-side-panel-ready></aside>
      <aside id="shut" data-nowo-ui-side-panel data-layout="inline" data-drawer-below="lg" hidden></aside>`;
    applyStoredStates();
    expect(el('lg').hidden).toBe(true);
    expect(el('xl').hidden).toBe(true);
    expect(el('md').hidden).toBe(false);
    expect(el('ov').hidden).toBe(false);
    expect(el('ready').hidden).toBe(false);
    expect(el('shut').hidden).toBe(true);
    expect(closeStartingDrawer(el('shut'))).toBe(false);
    expect(localStorage.length).toBe(0);

    // 1300 px: an xl panel is still a drawer, an lg one is an inline column.
    setMedia([DRAWER_QUERIES.xl]);
    document.body.innerHTML = `<aside id="lg" data-nowo-ui-side-panel data-layout="inline" data-drawer-below="lg"></aside>
      <aside id="xl" data-nowo-ui-side-panel data-layout="inline" data-drawer-below="xl"></aside>`;
    expect(closeStartingDrawer(el('lg'))).toBe(false);
    expect(closeStartingDrawer(el('xl'))).toBe(true);
    expect(el('lg').hidden).toBe(false);
  });

  it('pre-paint: applies now, after every parser chunk while loading, and stops at DOMContentLoaded', async () => {
    localStorage.setItem('nowo-ui-panel:c', 'open');
    const doc = document.implementation.createHTMLDocument('t');
    Object.defineProperty(doc, 'readyState', { configurable: true, value: 'loading' });
    const stop = watchStoredStates(doc);
    const panel = doc.createElement('aside');
    panel.setAttribute('data-nowo-ui-side-panel', '');
    panel.setAttribute('data-nowo-ui-side-panel-persist', 'c');
    panel.hidden = true;
    doc.body.appendChild(panel);
    await new Promise((r) => setTimeout(r, 0));
    expect(panel.hidden).toBe(false);

    doc.dispatchEvent(new Event('DOMContentLoaded'));
    const late = panel.cloneNode() as HTMLElement;
    late.hidden = true;
    doc.body.appendChild(late);
    await new Promise((r) => setTimeout(r, 0));
    expect(late.hidden).toBe(true); // observer gone
    stop();

    // Parsed already, or no MutationObserver: one pass, nothing to stop.
    const done = document.implementation.createHTMLDocument('d');
    expect(watchStoredStates(done)()).toBeUndefined();
    const original = globalThis.MutationObserver;
    (globalThis as unknown as { MutationObserver: unknown }).MutationObserver = undefined;
    try {
      Object.defineProperty(done, 'readyState', { configurable: true, value: 'loading' });
      expect(watchStoredStates(done)()).toBeUndefined();
    } finally {
      globalThis.MutationObserver = original;
    }
  });
});
