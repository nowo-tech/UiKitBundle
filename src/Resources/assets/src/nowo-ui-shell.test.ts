import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import * as mod from './nowo-ui-shell';

function mockMatchMedia(desktop: boolean): void {
  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    configurable: true,
    value: vi.fn().mockImplementation((query: string) => ({
      matches: desktop && query.includes('min-width: 901px'),
      media: query,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
    })),
  });
}

describe('nowo-ui-shell', () => {
  beforeAll(() => {
    mockMatchMedia(true);
    expect(mod.toggleAside).toBeTypeOf('function');
  });

  beforeEach(() => {
    mockMatchMedia(true);
    document.body.innerHTML = `
      <div data-nowo-ui-shell class="nowo-ui-shell">
        <button type="button" data-nowo-ui-burger aria-expanded="true">Menu</button>
        <aside data-nowo-ui-aside></aside>
        <div data-nowo-ui-aside-backdrop></div>
      </div>
    `;
    document.body.className = '';
  });

  afterEach(() => {
    document.body.innerHTML = '';
    document.body.className = '';
  });

  it('collapses aside on desktop toggle', () => {
    const shell = document.querySelector('[data-nowo-ui-shell]');
    expect(shell).toBeInstanceOf(HTMLElement);
    mod.toggleAside(shell as HTMLElement);
    expect(shell?.classList.contains('is-aside-collapsed')).toBe(true);
    const btn = shell?.querySelector('[data-nowo-ui-burger]');
    expect(btn?.getAttribute('aria-expanded')).toBe('false');
  });

  it('toggleAside no-ops on null and forceOpen on desktop', () => {
    mod.toggleAside(null);
    const shell = document.querySelector('[data-nowo-ui-shell]') as HTMLElement;
    mod.toggleAside(shell, true);
    expect(shell.classList.contains('is-aside-collapsed')).toBe(false);
    mod.toggleAside(shell, false);
    expect(shell.classList.contains('is-aside-collapsed')).toBe(true);
  });

  it('opens mobile drawer when not desktop', () => {
    mockMatchMedia(false);
    const shell = document.querySelector('[data-nowo-ui-shell]') as HTMLElement;
    mod.toggleAside(shell);
    expect(shell.classList.contains('is-aside-open-mobile')).toBe(true);
    expect(document.body.classList.contains('nowo-ui-aside-open')).toBe(true);
    mod.toggleAside(shell, false);
    expect(shell.classList.contains('is-aside-open-mobile')).toBe(false);
  });

  it('toggles via burger click', () => {
    const burger = document.querySelector('[data-nowo-ui-burger]')!;
    burger.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    const shell = document.querySelector('[data-nowo-ui-shell]');
    expect(shell?.classList.contains('is-aside-collapsed')).toBe(true);
    expect(window.nowoUiToggleAside).toBeTypeOf('function');
  });

  it('closes via backdrop click on mobile', () => {
    mockMatchMedia(false);
    const shell = document.querySelector('[data-nowo-ui-shell]') as HTMLElement;
    mod.toggleAside(shell, true);
    const backdrop = document.querySelector('[data-nowo-ui-aside-backdrop]')!;
    backdrop.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect(shell.classList.contains('is-aside-open-mobile')).toBe(false);
  });

  it('closes mobile drawer on Escape', () => {
    mockMatchMedia(false);
    const shell = document.querySelector('[data-nowo-ui-shell]') as HTMLElement;
    mod.toggleAside(shell, true);
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    expect(shell.classList.contains('is-aside-open-mobile')).toBe(false);
  });

  it('ignores non-Escape keydown', () => {
    mockMatchMedia(false);
    const shell = document.querySelector('[data-nowo-ui-shell]') as HTMLElement;
    mod.toggleAside(shell, true);
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true }));
    expect(shell.classList.contains('is-aside-open-mobile')).toBe(true);
  });

  it('bindNowoUiShell is idempotent', () => {
    mod.bindNowoUiShell();
    mod.bindNowoUiShell();
    expect(window.nowoUiToggleAside).toBe(mod.toggleAside);
  });

  it('toggles nested nav groups', () => {
    document.body.innerHTML = `
      <div data-nowo-ui-shell>
        <div data-nowo-ui-nav-group>
          <button type="button" data-nowo-ui-nav-group-toggle aria-expanded="false">Group</button>
        </div>
      </div>
    `;
    const group = document.querySelector('[data-nowo-ui-nav-group]')!;
    const btn = document.querySelector('[data-nowo-ui-nav-group-toggle]')!;
    btn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect(group.classList.contains('is-open')).toBe(true);
    expect(btn.getAttribute('aria-expanded')).toBe('true');
    btn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect(group.classList.contains('is-open')).toBe(false);
  });

  it('resolves shell from document when toggle has no closest shell', () => {
    document.body.innerHTML = `
      <div data-nowo-ui-shell class="nowo-ui-shell"></div>
      <button type="button" data-nowo-ui-burger>Orphan</button>
    `;
    document.querySelector('[data-nowo-ui-burger]')!
      .dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect(document.querySelector('[data-nowo-ui-shell]')?.classList.contains('is-aside-collapsed')).toBe(true);
  });

  it('toggles main width full ↔ content', () => {
    document.body.innerHTML = `
      <div data-nowo-ui-shell class="nowo-ui-shell">
        <button
          type="button"
          data-nowo-ui-width-toggle
          data-label-full="Full"
          data-label-content="Content"
          data-aria-to-full="To full"
          data-aria-to-content="To content"
        >
          <span data-nowo-ui-width-label>Full</span>
        </button>
      </div>
    `;
    const shell = document.querySelector('[data-nowo-ui-shell]') as HTMLElement;
    const btn = shell.querySelector('[data-nowo-ui-width-toggle]') as HTMLElement;
    btn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect(shell.classList.contains('is-main-content')).toBe(true);
    expect(btn.getAttribute('aria-pressed')).toBe('true');
    expect(btn.querySelector('[data-nowo-ui-width-label]')?.textContent).toBe('Content');
    mod.toggleMainWidth(shell);
    expect(shell.classList.contains('is-main-content')).toBe(false);
    mod.setMainWidth('content', shell);
    expect(shell.classList.contains('is-main-content')).toBe(true);
    expect(window.nowoUiSetMainWidth).toBeTypeOf('function');
    expect(window.nowoUiToggleMainWidth).toBeTypeOf('function');
  });

  it('restores main width from localStorage on bind', () => {
    window.localStorage.setItem('nowo-ui-main-width', 'content');
    document.body.innerHTML = `
      <div data-nowo-ui-shell class="nowo-ui-shell">
        <button type="button" data-nowo-ui-width-toggle></button>
      </div>
    `;
    mod.bindNowoUiShell();
    expect(document.querySelector('[data-nowo-ui-shell]')?.classList.contains('is-main-content')).toBe(true);
    window.localStorage.removeItem('nowo-ui-main-width');
  });

  it('keeps the page #fragment on a locale switcher link or form (2.8)', () => {
    mod.bindNowoUiShell();
    document.body.innerHTML = `
      <div data-nowo-ui-locale-switcher>
        <a id="lk" href="/es/login#x"><span id="in">ES</span></a>
        <form id="fm" action="/locale/es" method="post"></form>
      </div>
      <a id="out" href="/elsewhere">out</a>`;
    document.getElementById('lk')!.addEventListener('click', (e) => e.preventDefault());
    document.getElementById('out')!.addEventListener('click', (e) => e.preventDefault());
    document.getElementById('fm')!.addEventListener('submit', (e) => e.preventDefault());

    window.history.replaceState(null, '', '/manual/03-account');
    document.getElementById('in')!.click();
    expect(document.getElementById('lk')!.getAttribute('href')).toBe('/es/login#x');

    window.history.replaceState(null, '', '/manual/03-account#tours');
    document.getElementById('in')!.click();
    expect((document.getElementById('lk') as HTMLAnchorElement).href).toMatch(/\/es\/login#tours$/);
    document.getElementById('fm')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    expect((document.getElementById('fm') as HTMLFormElement).action).toMatch(/\/locale\/es#tours$/);
    document.getElementById('out')!.click();
    expect(document.getElementById('out')!.getAttribute('href')).toBe('/elsewhere');
    mod.keepLocaleHash(new Event('click'));
    document.querySelector('[data-nowo-ui-locale-switcher]')!.dispatchEvent(new Event('click', { bubbles: true }));
    window.history.replaceState(null, '', '/');
  });
});
