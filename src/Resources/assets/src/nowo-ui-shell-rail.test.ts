import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

type ShellModule = typeof import('./nowo-ui-shell');

let mobile = false;
let changeListener: (() => void) | null = null;

function installMatchMedia(): void {
  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    configurable: true,
    value: vi.fn().mockImplementation((query: string) => ({
      matches: query.includes('max-width') ? mobile : !mobile,
      media: query,
      addEventListener: (_type: string, cb: () => void) => {
        changeListener = cb;
      },
      removeEventListener: vi.fn(),
    })),
  });
}

const FIXTURE = `
  <div data-nowo-ui-shell class="nowo-ui-shell" data-nowo-ui-sidebar-rail
       data-nowo-ui-sidebar-storage="app-sidebar" data-nowo-ui-sidebar-cookie="app_sidebar"
       data-nowo-ui-sidebar-default="rail">
    <button type="button" data-nowo-ui-burger data-label-expand="Expand" data-label-rail="Icons" data-label-hide="Hide">Menu</button>
    <aside data-nowo-ui-aside>
      <a class="nowo-ui-aside__link" href="#x">Projects</a>
    </aside>
    <div data-nowo-ui-aside-backdrop></div>
  </div>`;

let mod: ShellModule;

/** (Re)initialise the shells in the current DOM — the module binds its document listeners once. */
async function load(): Promise<ShellModule> {
  mod.bindNowoUiShell();
  return mod;
}

function shell(): HTMLElement {
  return document.querySelector('[data-nowo-ui-shell]') as HTMLElement;
}

function click(selector: string): void {
  document.querySelector(selector)!.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
}

describe('nowo-ui-shell sidebar rail', () => {
  beforeAll(async () => {
    installMatchMedia();
    document.body.innerHTML = FIXTURE;
    mod = await import('./nowo-ui-shell');
  });

  beforeEach(() => {
    mobile = false;
    installMatchMedia();
    localStorage.clear();
    sessionStorage.clear();
    document.cookie = 'app_sidebar=; Path=/; Max-Age=0';
    document.body.innerHTML = FIXTURE;
    document.body.className = '';
  });

  afterEach(() => {
    document.body.innerHTML = '';
    document.body.className = '';
  });

  it('starts from the server default and walks the rail cycle, persisting each mode', async () => {
    const mod = await load();
    expect(shell().getAttribute('data-nowo-ui-sidebar-mode')).toBe('rail');
    expect(shell().classList.contains('is-aside-rail')).toBe(true);
    expect(localStorage.getItem('app-sidebar')).toBeNull();
    const burger = document.querySelector('[data-nowo-ui-burger]') as HTMLElement;
    expect(burger.getAttribute('aria-label')).toBe('Expand');

    const events: string[] = [];
    shell().addEventListener('nowo-ui:sidebar-mode', (e) => events.push((e as CustomEvent).detail.mode));

    click('[data-nowo-ui-burger]');
    expect(mod.currentSidebarMode(shell())).toBe('expanded');
    expect(localStorage.getItem('app-sidebar')).toBe('expanded');
    expect(document.cookie).toContain('app_sidebar=expanded');
    click('[data-nowo-ui-burger]');
    click('[data-nowo-ui-burger]');
    expect(mod.currentSidebarMode(shell())).toBe('hidden');
    expect(shell().classList.contains('is-aside-collapsed')).toBe(true);
    expect(burger.getAttribute('aria-expanded')).toBe('false');
    expect(events).toEqual(['expanded', 'rail', 'hidden']);

    click('[data-nowo-ui-aside-backdrop]');
    expect(mod.currentSidebarMode(shell())).toBe('hidden');
    window.nowoUiSetSidebarMode!('expanded');
    expect(mod.currentSidebarMode(shell())).toBe('expanded');
  });

  it('restores the stored mode and resolves it when the attribute is missing', async () => {
    localStorage.setItem('app-sidebar', 'hidden');
    const mod = await load();
    expect(shell().classList.contains('is-aside-collapsed')).toBe(true);
    shell().removeAttribute('data-nowo-ui-sidebar-mode');
    expect(mod.currentSidebarMode(shell())).toBe('hidden');
  });

  it('uses the drawer on phones: open / hidden, no persistence, closes on link, backdrop, Escape', async () => {
    mobile = true;
    await load();
    expect(shell().getAttribute('data-nowo-ui-sidebar-mode')).toBe('hidden');

    click('[data-nowo-ui-burger]');
    expect(shell().classList.contains('is-aside-open-mobile')).toBe(true);
    expect(document.body.classList.contains('nowo-ui-aside-open')).toBe(true);
    expect(localStorage.getItem('app-sidebar')).toBeNull();

    click('.nowo-ui-aside__link');
    expect(shell().classList.contains('is-aside-open-mobile')).toBe(false);

    click('[data-nowo-ui-burger]');
    click('[data-nowo-ui-aside-backdrop]');
    expect(shell().classList.contains('is-aside-open-mobile')).toBe(false);

    click('[data-nowo-ui-burger]');
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    expect(shell().classList.contains('is-aside-open-mobile')).toBe(false);
  });

  it('re-resolves the mode when the viewport crosses the breakpoint', async () => {
    localStorage.setItem('app-sidebar', 'expanded');
    await load();
    expect(changeListener).toBeTypeOf('function');
    mobile = true;
    changeListener!();
    expect(shell().getAttribute('data-nowo-ui-sidebar-mode')).toBe('hidden');
    mobile = false;
    changeListener!();
    expect(shell().getAttribute('data-nowo-ui-sidebar-mode')).toBe('expanded');
  });

  it('ignores shells without the rail opt-in and desktop link clicks', async () => {
    const mod = await load();
    click('.nowo-ui-aside__link');
    expect(mod.currentSidebarMode(shell())).toBe('rail');

    document.body.innerHTML = `<div data-nowo-ui-shell class="nowo-ui-shell"><button data-nowo-ui-burger></button></div>`;
    mod.setSidebarMode(shell(), 'rail');
    mod.advanceSidebar(shell());
    mod.setSidebarMode(null, 'rail');
    expect(shell().hasAttribute('data-nowo-ui-sidebar-mode')).toBe(false);
    window.nowoUiSetSidebarMode!('rail');
    click('[data-nowo-ui-burger]');
    expect(shell().classList.contains('is-aside-collapsed')).toBe(true);
  });

  it('uses a custom rail label selector', async () => {
    shell().setAttribute('data-nowo-ui-sidebar-rail-labels', '[data-nowo-ui-aside] a');
    await load();
    expect(document.querySelector('.nowo-ui-aside__link')!.getAttribute('data-nowo-ui-rail-label')).toBe('Projects');
  });
});
