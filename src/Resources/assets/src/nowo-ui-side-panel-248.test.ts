import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

type Mod = typeof import('./nowo-ui-side-panel');

const el = (id: string): HTMLElement => document.getElementById(id) as HTMLElement;
const listeners = new Map<string, () => void>();
let matching: string[] = [];

function installMedia(): void {
  window.matchMedia = vi.fn().mockImplementation((query: string) => ({
    get matches() {
      return matching.includes(query);
    },
    media: query,
    addEventListener: (_type: string, fn: () => void) => listeners.set(query, fn),
  })) as unknown as typeof window.matchMedia;
}

const MARKUP = `
  <button type="button" id="t" aria-controls="c" aria-expanded="false" data-nowo-ui-side-panel-toggle>Comments</button>
  <div data-nowo-ui-side-panel-container data-drawer-below="lg">
    <div data-nowo-ui-side-panel-main><p id="content" tabindex="-1">Content</p></div>
    <aside id="c" data-nowo-ui-side-panel data-layout="inline" data-drawer-below="lg" data-nowo-ui-side-panel-persist="wiki-comments" hidden>
      <h2 id="c-title" tabindex="-1">Comments</h2><button type="button" data-nowo-ui-side-panel-close>x</button>
    </aside>
    <div data-nowo-ui-side-panel-backdrop data-for="c" hidden></div>
  </div>
  <aside id="md" data-nowo-ui-side-panel data-layout="inline" hidden><h2 id="md-title" tabindex="-1">Md</h2></aside>`;

describe('nowo-ui-side-panel 2.48: drawer breakpoint and remembered state', () => {
  let mod: Mod;

  beforeAll(async () => {
    installMedia();
    vi.resetModules();
    mod = await import('./nowo-ui-side-panel');
  });

  beforeEach(() => {
    localStorage.clear();
    matching = [];
    document.body.innerHTML = MARKUP;
    mod.markSidePanelsReady();
  });

  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('is a drawer under its own breakpoint only, and follows the media query change', () => {
    matching = ['(max-width: 1199px)'];
    expect(mod.isDrawer(el('c'))).toBe(true);
    expect(mod.isDrawer(el('md'))).toBe(false);
    mod.openPanel(el('c'));
    expect(el('c').hasAttribute('data-drawer')).toBe(true);
    expect(document.querySelector('[data-nowo-ui-side-panel-main]')!.hasAttribute('inert')).toBe(true);

    matching = [];
    listeners.get('(max-width: 1199px)')!();
    expect(el('c').hasAttribute('data-drawer')).toBe(false);
    expect(document.querySelector('[data-nowo-ui-side-panel-main]')!.hasAttribute('inert')).toBe(false);
    expect([...listeners.keys()].sort()).toEqual(['(max-width: 1199px)', '(max-width: 1439px)', '(max-width: 767px)']);
  });

  it('remembers the inline state only (never a drawer state) with a persist key', () => {
    mod.openPanel(el('c'));
    expect(localStorage.getItem('nowo-ui-panel:wiki-comments')).toBe('open');
    mod.closePanel(el('c'));
    expect(localStorage.getItem('nowo-ui-panel:wiki-comments')).toBe('closed');
    matching = ['(max-width: 1199px)'];
    mod.openPanel(el('c'));
    expect(localStorage.getItem('nowo-ui-panel:wiki-comments')).toBe('closed');
    mod.closePanel(el('c'));
    mod.openPanel(el('md'));
    expect(localStorage.length).toBe(1);
  });

  it('applies the remembered state on start and syncs the toggles', () => {
    localStorage.setItem('nowo-ui-panel:wiki-comments', 'open');
    document.body.innerHTML = MARKUP;
    mod.markSidePanelsReady();
    expect(el('c').hidden).toBe(false);
    expect(el('t').getAttribute('aria-expanded')).toBe('true');

    // A stored "open" never opens a drawer on load.
    matching = ['(max-width: 1199px)'];
    document.body.innerHTML = MARKUP;
    mod.markSidePanelsReady();
    expect(el('c').hidden).toBe(true);
    expect(el('t').getAttribute('aria-expanded')).toBe('false');
  });

  it('starts a server-open lg panel closed under its breakpoint, without events or storage (audit LV-L2)', () => {
    const serverOpen = MARKUP.replace(' data-nowo-ui-side-panel-persist="wiki-comments" hidden>', '>').replace('aria-expanded="false"', 'aria-expanded="true"');
    const events: string[] = [];
    const record = (e: Event): void => {
      events.push(e.type);
    };
    document.addEventListener('nowo-ui:side-panel:open', record);
    document.addEventListener('nowo-ui:side-panel:close', record);

    // 1024 px: under lg, the server-open panel would be a modal drawer, so it starts closed.
    matching = ['(max-width: 1199px)', '(max-width: 1439px)'];
    document.body.innerHTML = serverOpen;
    mod.markSidePanelsReady();
    expect(el('c').hidden).toBe(true);
    expect(el('c').hasAttribute('data-drawer')).toBe(false);
    expect(el('t').getAttribute('aria-expanded')).toBe('false');
    expect(document.querySelector('[data-nowo-ui-side-panel-main]')!.hasAttribute('inert')).toBe(false);
    expect((document.querySelector('[data-nowo-ui-side-panel-backdrop]') as HTMLElement).hidden).toBe(true);
    expect(document.documentElement.hasAttribute('data-nowo-overlay')).toBe(false);
    expect(localStorage.length).toBe(0);
    expect(events).toEqual([]);

    // Closed earlier by js/nowo-ui-layout-state.js in <head>: the toggles still follow on start.
    document.body.innerHTML = serverOpen;
    el('c').hidden = true;
    mod.markSidePanelsReady();
    expect(el('t').getAttribute('aria-expanded')).toBe('false');

    // Only on the first start: once the user opens it, a later markSidePanelsReady() keeps the drawer.
    mod.openPanel(el('c'));
    mod.markSidePanelsReady();
    expect(el('c').hidden).toBe(false);
    expect(el('c').hasAttribute('data-drawer')).toBe(true);
    mod.closePanel(el('c'));

    // Wide screen: the inline column stays open.
    matching = [];
    document.body.innerHTML = serverOpen;
    mod.markSidePanelsReady();
    expect(el('c').hidden).toBe(false);
    expect(el('t').getAttribute('aria-expanded')).toBe('true');

    // The 2.25 default (md) and the overlay layout keep the server state (BC).
    matching = ['(max-width: 767px)', '(max-width: 1199px)', '(max-width: 1439px)'];
    document.body.innerHTML = serverOpen.replace(/ data-drawer-below="lg"/g, '');
    mod.markSidePanelsReady();
    expect(el('c').hidden).toBe(false);
    expect(el('c').hasAttribute('data-drawer')).toBe(true);
    mod.closePanel(el('c'));
    document.body.innerHTML = serverOpen.replace('data-layout="inline" data-drawer-below="lg"', 'data-layout="overlay" data-drawer-below="lg"');
    mod.markSidePanelsReady();
    expect(el('c').hidden).toBe(false);
    mod.closePanel(el('c'));

    document.removeEventListener('nowo-ui:side-panel:open', record);
    document.removeEventListener('nowo-ui:side-panel:close', record);
  });
});
