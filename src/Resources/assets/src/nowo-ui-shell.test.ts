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
});
