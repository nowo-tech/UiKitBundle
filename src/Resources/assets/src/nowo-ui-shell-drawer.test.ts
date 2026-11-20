import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as mod from './nowo-ui-shell';

/** 2.60 accessible drawer (data-nowo-ui-drawer-a11y): inert hidden aside, real burger state, focus in / out, scroll lock. */
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

function shell(): HTMLElement {
  return document.querySelector('[data-nowo-ui-shell]') as HTMLElement;
}

function burger(): HTMLElement {
  return document.querySelector('[data-nowo-ui-burger]') as HTMLElement;
}

describe('nowo-ui-shell accessible drawer', () => {
  beforeEach(() => {
    mockMatchMedia(false);
    document.body.innerHTML = `
      <div data-nowo-ui-shell data-nowo-ui-drawer-a11y class="nowo-ui-shell">
        <header class="nowo-ui-header">
          <div class="nowo-ui-header__start"><button type="button" data-nowo-ui-burger aria-expanded="true" data-label-expand="Expand navigation" data-label-hide="Hide navigation" aria-label="Hide navigation">Menu</button></div>
          <div class="nowo-ui-header__end"><button type="button" id="theme">Theme</button></div>
        </header>
        <aside data-nowo-ui-aside><a href="/a" id="first">A</a><a href="/b" id="current" aria-current="page">B</a></aside>
        <div class="nowo-ui-main"><main><a href="/x" id="content-link">X</a></main></div>
        <footer class="nowo-ui-footer"><a href="/f">F</a></footer>
        <div data-nowo-ui-aside-backdrop></div>
      </div>`;
    document.documentElement.className = '';
    mod.bindNowoUiShell();
  });

  afterEach(() => {
    document.body.innerHTML = '';
    document.documentElement.className = '';
  });

  it('starts closed on phones: inert aside, burger says "expand"', () => {
    const aside = shell().querySelector('[data-nowo-ui-aside]') as HTMLElement;
    expect(aside.hasAttribute('inert')).toBe(true);
    expect(burger().getAttribute('aria-expanded')).toBe('false');
    expect(burger().getAttribute('aria-label')).toBe('Expand navigation');
    expect(burger().getAttribute('data-nowo-ui-burger-glyph')).toBe('open');
    expect(document.documentElement.classList.contains(mod.DRAWER_OPEN_ROOT_CLASS)).toBe(false);
    expect(shell().querySelector('.nowo-ui-main')?.hasAttribute('inert')).toBe(false);
  });

  it('opening moves the focus to the current link, makes the page inert and locks the scroll', () => {
    burger().focus();
    burger().click();
    const aside = shell().querySelector('[data-nowo-ui-aside]') as HTMLElement;
    expect(shell().classList.contains('is-aside-open-mobile')).toBe(true);
    expect(aside.hasAttribute('inert')).toBe(false);
    expect(document.activeElement?.id).toBe('current');
    expect(burger().getAttribute('aria-expanded')).toBe('true');
    expect(burger().getAttribute('aria-label')).toBe('Hide navigation');
    expect(document.documentElement.classList.contains(mod.DRAWER_OPEN_ROOT_CLASS)).toBe(true);
    for (const sel of ['.nowo-ui-main', '.nowo-ui-footer', '.nowo-ui-header__end']) {
      expect(shell().querySelector(sel)?.hasAttribute('inert')).toBe(true);
    }
    expect(shell().querySelector('.nowo-ui-header__start')?.hasAttribute('inert')).toBe(false);
  });

  it('Escape closes it and returns the focus to the burger', () => {
    burger().click();
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    expect(shell().classList.contains('is-aside-open-mobile')).toBe(false);
    expect(document.activeElement).toBe(burger());
    expect(shell().querySelector('[data-nowo-ui-aside]')?.hasAttribute('inert')).toBe(true);
    expect(shell().querySelector('.nowo-ui-main')?.hasAttribute('inert')).toBe(false);
    expect(document.documentElement.classList.contains(mod.DRAWER_OPEN_ROOT_CLASS)).toBe(false);
  });

  it('the backdrop and a drawer link close it too; Escape with an open dialog is left to the dialog', () => {
    burger().click();
    (shell().querySelector('[data-nowo-ui-aside-backdrop]') as HTMLElement).click();
    expect(shell().classList.contains('is-aside-open-mobile')).toBe(false);
    burger().click();
    (document.getElementById('first') as HTMLElement).addEventListener('click', (e) => e.preventDefault());
    (document.getElementById('first') as HTMLElement).click();
    expect(shell().classList.contains('is-aside-open-mobile')).toBe(false);
    burger().click();
    const dialog = document.createElement('dialog');
    dialog.setAttribute('open', '');
    document.body.appendChild(dialog);
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    expect(shell().classList.contains('is-aside-open-mobile')).toBe(true);
  });

  it('desktop: the aside is shown and focusable; collapsing makes it inert', () => {
    mockMatchMedia(true);
    mod.syncDrawer(shell());
    const aside = shell().querySelector('[data-nowo-ui-aside]') as HTMLElement;
    expect(aside.hasAttribute('inert')).toBe(false);
    expect(burger().getAttribute('aria-expanded')).toBe('true');
    mod.toggleAside(shell());
    expect(aside.hasAttribute('inert')).toBe(true);
    expect(burger().getAttribute('aria-expanded')).toBe('false');
    expect(document.documentElement.classList.contains(mod.DRAWER_OPEN_ROOT_CLASS)).toBe(false);
  });

  it('shells without the attribute keep the 2.59 behaviour', () => {
    shell().removeAttribute('data-nowo-ui-drawer-a11y');
    shell().querySelector('[data-nowo-ui-aside]')?.removeAttribute('inert');
    mod.toggleAside(shell(), true);
    expect(shell().querySelector('[data-nowo-ui-aside]')?.hasAttribute('inert')).toBe(false);
    expect(document.documentElement.classList.contains(mod.DRAWER_OPEN_ROOT_CLASS)).toBe(false);
  });

  it('an aria-disabled button link does not navigate (keyboard Enter dispatches a click)', () => {
    document.body.insertAdjacentHTML('beforeend', '<a href="/x" class="nowo-ui-btn" aria-disabled="true" id="off">Off</a>');
    const event = new MouseEvent('click', { bubbles: true, cancelable: true });
    (document.getElementById('off') as HTMLElement).dispatchEvent(event);
    expect(event.defaultPrevented).toBe(true);
  });

  it('a dismiss button removes its dismissible box', () => {
    document.body.insertAdjacentHTML('beforeend', '<div data-nowo-ui-dismissible id="box"><button type="button" data-nowo-ui-dismiss>x</button></div>');
    (document.querySelector('[data-nowo-ui-dismiss]') as HTMLElement).click();
    expect(document.getElementById('box')).toBeNull();
  });
});
