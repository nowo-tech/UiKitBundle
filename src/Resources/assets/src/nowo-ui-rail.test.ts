import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as mod from './nowo-ui-rail';

const el = (id: string): HTMLElement => document.getElementById(id) as HTMLElement;
function click(target: Element): MouseEvent {
  const event = new MouseEvent('click', { bubbles: true, cancelable: true });
  target.dispatchEvent(event);
  return event;
}
function key(target: Element, name: string, init: KeyboardEventInit = {}): KeyboardEvent {
  const event = new KeyboardEvent('keydown', { key: name, bubbles: true, cancelable: true, ...init });
  target.dispatchEvent(event);
  return event;
}

const toggle = (id: string, extra = '', tag: 'button' | 'a' = 'button'): string =>
  `<${tag} ${tag === 'a' ? 'href="?tree=rail" role="button"' : 'type="button"'} id="${id}" aria-controls="${id}-body" aria-expanded="true" data-nowo-ui-rail-toggle data-nowo-ui-rail-label-collapse="Collapse Pages" data-nowo-ui-rail-label-expand="Expand Pages" ${extra}><span data-nowo-ui-rail-toggle-label>Collapse Pages</span></${tag}>`;

const MARKUP = `
  <aside id="tree" data-nowo-ui-rail-host data-nowo-ui-rail="expanded">
    ${toggle('t', 'data-nowo-ui-rail-persist="wiki-tree" data-nowo-ui-rail-shortcut="Alt+Shift+P"', 'a')}
    <nav id="t-body" data-nowo-ui-rail-body><a href="/p" id="page-link">Page</a></nav>
  </aside>
  <aside id="srv" data-nowo-ui-rail-host data-nowo-ui-rail="collapsed">
    ${toggle('s', 'data-nowo-ui-rail-persist="srv-key" data-nowo-ui-rail-state-url="/state" data-nowo-ui-rail-state-token="tok"')}
    <div id="s-body" data-nowo-ui-rail-body></div>
  </aside>
  <aside id="plain" data-nowo-ui-rail-host data-nowo-ui-rail="expanded"><div id="p-body"></div></aside>
  ${toggle('p')}
  <button type="button" id="orphan" data-nowo-ui-rail-toggle aria-controls="nope" data-nowo-ui-rail-shortcut="Alt+Shift+O">o</button>
  <button type="button" id="broken" data-nowo-ui-rail-toggle aria-controls="nope" data-nowo-ui-rail-shortcut="+">b</button>
  <input id="field">
  <div id="not-a-host"></div>`;

describe('nowo-ui-rail (2.48)', () => {
  let events: { id: string; collapsed: boolean; source: string }[];
  const listener = (e: Event): void => {
    events.push((e as CustomEvent).detail);
  };

  beforeEach(() => {
    localStorage.clear();
    document.body.innerHTML = MARKUP;
    events = [];
    document.addEventListener('nowo-ui:rail:change', listener);
    mod.bindNowoUiRail();
  });

  afterEach(() => {
    document.removeEventListener('nowo-ui:rail:change', listener);
    document.body.innerHTML = '';
    vi.restoreAllMocks();
  });

  it('collapses from the toggle link in place: state, aria-expanded, label, event, localStorage', () => {
    const event = click(el('t'));
    expect(event.defaultPrevented).toBe(true);
    expect(el('tree').getAttribute('data-nowo-ui-rail')).toBe('collapsed');
    expect(el('t').getAttribute('aria-expanded')).toBe('false');
    expect(el('t').textContent).toBe('Expand Pages');
    expect(events).toEqual([{ id: 'tree', collapsed: true, source: 'toggle' }]);
    expect(localStorage.getItem('nowo-ui-rail:wiki-tree')).toBe('collapsed');
    click(el('t'));
    expect(el('tree').getAttribute('data-nowo-ui-rail')).toBe('expanded');
    expect(el('t').getAttribute('aria-expanded')).toBe('true');
    expect(localStorage.getItem('nowo-ui-rail:wiki-tree')).toBe('expanded');
  });

  it('moves focus from the hidden body to the toggle when collapsing', () => {
    el('page-link').focus();
    expect(mod.api.collapse('tree')).toBe(true);
    expect(document.activeElement).toBe(el('t'));
    expect(mod.api.collapse('tree')).toBe(false);
    expect(mod.api.isCollapsed('tree')).toBe(true);
    expect(mod.api.expand('tree')).toBe(true);
    expect(mod.api.toggle('tree')).toBe(true);
    expect(mod.api.toggle('tree')).toBe(false);
    expect(() => mod.api.collapse('not-a-host')).toThrow('No rail host #not-a-host.');
    expect(() => mod.api.collapse('missing')).toThrow();
  });

  it('sends the state to the host store (primary) instead of localStorage', () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 204 }));
    vi.stubGlobal('fetch', fetchMock);
    click(el('s'));
    expect(el('srv').getAttribute('data-nowo-ui-rail')).toBe('expanded');
    expect(fetchMock).toHaveBeenCalledWith('/state', expect.objectContaining({ method: 'POST', credentials: 'same-origin', body: JSON.stringify({ panel: 'srv-key', open: false }) }));
    const init = fetchMock.mock.calls[0][1] as RequestInit;
    expect((init.headers as Record<string, string>)['X-CSRF-Token']).toBe('tok');
    expect(localStorage.getItem('nowo-ui-rail:srv-key')).toBeNull();

    el('s').setAttribute('data-nowo-ui-rail-state-panel', 'tree_rail');
    el('s').removeAttribute('data-nowo-ui-rail-state-token');
    click(el('s'));
    expect(fetchMock.mock.calls[1][1].body).toBe(JSON.stringify({ panel: 'tree_rail', open: true }));
    expect((fetchMock.mock.calls[1][1].headers as Record<string, string>)['X-CSRF-Token']).toBeUndefined();

    // Panel name falls back to the host id; a fetch that throws synchronously is swallowed.
    el('s').removeAttribute('data-nowo-ui-rail-state-panel');
    el('s').removeAttribute('data-nowo-ui-rail-persist');
    vi.stubGlobal('fetch', vi.fn(() => {
      throw new TypeError('bad url');
    }));
    expect(mod.sendToHostStore(el('s'), el('srv'), true)).toBe(true);
    vi.stubGlobal('fetch', undefined);
    expect(mod.sendToHostStore(el('s'), el('srv'), true)).toBe(true);
    vi.unstubAllGlobals();
  });

  it('works for a toggle outside its host and without any persistence', () => {
    click(el('p'));
    expect(el('plain').getAttribute('data-nowo-ui-rail')).toBe('collapsed');
    expect(localStorage.length).toBe(0);
    expect(mod.togglesOf(el('plain'))).toEqual([el('p')]);
    expect(click(el('orphan')).defaultPrevented).toBe(false);
    expect(click(el('not-a-host')).defaultPrevented).toBe(false);
    document.dispatchEvent(new MouseEvent('click', { bubbles: true }));
  });

  it('Space activates the link toggle; the shortcut toggles, but not while typing a plain key', () => {
    el('t').focus();
    expect(key(el('t'), ' ').defaultPrevented).toBe(true);
    expect(el('tree').getAttribute('data-nowo-ui-rail')).toBe('collapsed');
    el('field').focus();
    expect(key(el('field'), 'P', { code: 'KeyP', altKey: true, shiftKey: true }).defaultPrevented).toBe(true);
    expect(el('tree').getAttribute('data-nowo-ui-rail')).toBe('expanded');
    expect(document.activeElement).toBe(el('t'));
    expect(events.map((e) => e.source)).toEqual(['toggle', 'shortcut']);
    // The orphan's host is missing: nothing happens.
    expect(key(document.body, 'O', { code: 'KeyO', altKey: true, shiftKey: true }).defaultPrevented).toBe(false);
    expect(key(document.body, 'x').defaultPrevented).toBe(false);

    el('t').setAttribute('data-nowo-ui-rail-shortcut', 'p');
    el('field').focus();
    expect(key(el('field'), 'p', { code: 'KeyP' }).defaultPrevented).toBe(false);
    expect(el('tree').getAttribute('data-nowo-ui-rail')).toBe('expanded');
  });

  it('applies the remembered state on start (not over a host store) and syncs the labels', () => {
    localStorage.setItem('nowo-ui-rail:wiki-tree', 'collapsed');
    localStorage.setItem('nowo-ui-rail:srv-key', 'expanded');
    document.body.innerHTML = MARKUP;
    mod.markRailsReady();
    expect(el('tree').getAttribute('data-nowo-ui-rail')).toBe('collapsed');
    expect(el('t').textContent).toBe('Expand Pages');
    expect(el('srv').getAttribute('data-nowo-ui-rail')).toBe('collapsed');
    expect(el('s').getAttribute('aria-expanded')).toBe('false');
    expect(el('t').hasAttribute('data-nowo-ui-rail-ready')).toBe(true);
    expect(el('orphan').hasAttribute('data-nowo-ui-rail-ready')).toBe(true);
  });

  it('a toggle without the label attributes keeps its text', () => {
    document.body.innerHTML = '<div data-nowo-ui-rail-host data-nowo-ui-rail="expanded" id="h"><button id="b" data-nowo-ui-rail-toggle><span data-nowo-ui-rail-toggle-label>Keep</span></button></div>';
    mod.syncToggle(el('b'), true);
    expect(el('b').textContent).toBe('Keep');
    expect(mod.setCollapsed(el('h'), true)).toBe(true);
    const lonely = document.createElement('div');
    lonely.setAttribute('data-nowo-ui-rail-host', '');
    document.body.appendChild(lonely);
    expect(mod.setCollapsed(lonely, true)).toBe(true);
  });

  it('swallows a rejected host-store request', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));
    click(el('s'));
    await new Promise((r) => setTimeout(r, 0));
    expect(el('srv').getAttribute('data-nowo-ui-rail')).toBe('expanded');
    vi.unstubAllGlobals();
  });

  it('marks the toggles again at DOMContentLoaded when started while loading', () => {
    Object.defineProperty(document, 'readyState', { configurable: true, value: 'loading' });
    try {
      mod.bindNowoUiRail();
      el('t').removeAttribute('data-nowo-ui-rail-ready');
      document.dispatchEvent(new Event('DOMContentLoaded'));
      expect(el('t').hasAttribute('data-nowo-ui-rail-ready')).toBe(true);
    } finally {
      delete (document as unknown as { readyState?: string }).readyState;
    }
  });

  it('exposes the API and binds once', () => {
    expect((window as unknown as { NowoUiRail: unknown }).NowoUiRail).toBe(mod.api);
    mod.bindNowoUiRail();
    click(el('p'));
    expect(events).toHaveLength(1);
  });
});
