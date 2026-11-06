import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

type ShellModule = typeof import('./nowo-ui-shell');

let mobile = false;

function installMatchMedia(): void {
  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    configurable: true,
    value: vi.fn().mockImplementation((query: string) => ({
      matches: query.includes('max-width') ? mobile : !mobile,
      media: query,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
    })),
  });
}

/** 2.32 full-height layout: a burger in the header and one in the aside head; hover expand on the rail. */
const RAIL = `
  <div data-nowo-ui-shell class="nowo-ui-shell is-aside-rail" data-nowo-ui-sidebar-layout="full-height"
       data-nowo-ui-sidebar-rail data-nowo-ui-sidebar-storage="fh-sidebar" data-nowo-ui-sidebar-default="rail"
       data-nowo-ui-sidebar-hover-expand>
    <header><div class="start"><button type="button" id="header-burger" data-nowo-ui-burger data-label-expand="Expand" data-label-rail="Icons" data-label-hide="Hide">Menu</button></div></header>
    <aside data-nowo-ui-aside>
      <div data-nowo-ui-aside-head><button type="button" id="aside-burger" data-nowo-ui-burger data-label-expand="Expand" data-label-rail="Icons" data-label-hide="Hide">Menu</button></div>
      <a id="link" class="nowo-ui-aside__link" href="#x">Projects</a>
    </aside>
    <a id="outside" href="#y">Outside</a>
  </div>`;

const TWO_MODE = `
  <div data-nowo-ui-shell class="nowo-ui-shell" data-nowo-ui-sidebar-layout="full-height">
    <header><button type="button" id="header-burger" data-nowo-ui-burger aria-expanded="true">Menu</button></header>
    <aside data-nowo-ui-aside><div data-nowo-ui-aside-head><button type="button" id="aside-burger" data-nowo-ui-burger aria-expanded="true">Menu</button></div></aside>
  </div>`;

let mod: ShellModule;

function shell(): HTMLElement {
  return document.querySelector('[data-nowo-ui-shell]') as HTMLElement;
}

function aside(): HTMLElement {
  return document.querySelector('[data-nowo-ui-aside]') as HTMLElement;
}

function pointer(type: 'pointerenter' | 'pointerleave', pointerType = 'mouse'): void {
  const event = new Event(type) as Event & { pointerType: string };
  Object.defineProperty(event, 'pointerType', { value: pointerType });
  aside().dispatchEvent(event);
}

describe('nowo-ui-shell full-height layout and hover expand (2.32)', () => {
  beforeAll(async () => {
    installMatchMedia();
    document.body.innerHTML = '';
    mod = await import('./nowo-ui-shell');
  });

  beforeEach(() => {
    vi.useFakeTimers();
    mobile = false;
    installMatchMedia();
    localStorage.clear();
    document.body.innerHTML = RAIL;
  });

  afterEach(() => {
    vi.useRealTimers();
    document.body.innerHTML = '';
  });

  it('keeps both burgers in sync on a rail shell', () => {
    mod.bindNowoUiShell();
    const header = document.getElementById('header-burger')!;
    const inAside = document.getElementById('aside-burger')!;
    expect(header.getAttribute('aria-label')).toBe(inAside.getAttribute('aria-label'));
    inAside.click();
    expect(shell().getAttribute('data-nowo-ui-sidebar-mode')).not.toBe('rail');
    expect(header.getAttribute('aria-expanded')).toBe(inAside.getAttribute('aria-expanded'));
    expect(header.getAttribute('aria-label')).toBe(inAside.getAttribute('aria-label'));
  });

  it('keeps both burgers in sync on a two-mode shell', () => {
    document.body.innerHTML = TWO_MODE;
    mod.bindNowoUiShell();
    document.getElementById('aside-burger')!.click();
    expect(shell().classList.contains('is-aside-collapsed')).toBe(true);
    expect(document.getElementById('header-burger')!.getAttribute('aria-expanded')).toBe('false');
    expect(document.getElementById('aside-burger')!.getAttribute('aria-expanded')).toBe('false');

    mobile = true;
    installMatchMedia();
    mod.toggleAside(shell(), true);
    expect(document.getElementById('header-burger')!.getAttribute('aria-expanded')).toBe('true');
    expect(document.getElementById('aside-burger')!.getAttribute('aria-expanded')).toBe('true');
  });

  it('peeks on pointer hover after a short delay and closes after leaving', () => {
    mod.bindNowoUiShell();
    expect(mod.bindSidebarPeek(shell())).toBe(false);
    pointer('pointerenter');
    expect(shell().classList.contains(mod.PEEK_CLASS)).toBe(false);
    vi.advanceTimersByTime(mod.PEEK_OPEN_DELAY_MS);
    expect(shell().classList.contains(mod.PEEK_CLASS)).toBe(true);
    pointer('pointerleave');
    vi.advanceTimersByTime(mod.PEEK_CLOSE_DELAY_MS);
    expect(shell().classList.contains(mod.PEEK_CLASS)).toBe(false);
  });

  it('never peeks on touch, on phones, outside the rail or without the opt-in', () => {
    mod.bindNowoUiShell();
    pointer('pointerenter', 'touch');
    vi.advanceTimersByTime(500);
    expect(shell().classList.contains(mod.PEEK_CLASS)).toBe(false);

    mobile = true;
    installMatchMedia();
    expect(mod.canPeek(shell())).toBe(false);
    mobile = false;
    installMatchMedia();
    expect(mod.canPeek(shell())).toBe(true);
    shell().classList.remove('is-aside-rail');
    expect(mod.canPeek(shell())).toBe(false);

    document.body.innerHTML = TWO_MODE;
    expect(mod.bindSidebarPeek(shell())).toBe(false);
  });

  it('peeks on keyboard focus, stays while the focus moves inside, closes on Escape and on focus out', () => {
    mod.bindNowoUiShell();
    const link = document.getElementById('link')!;
    link.focus();
    link.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
    vi.advanceTimersByTime(0);
    expect(shell().classList.contains(mod.PEEK_CLASS)).toBe(true);

    // pointer leaving while the focus is inside keeps it open
    pointer('pointerleave');
    vi.advanceTimersByTime(mod.PEEK_CLOSE_DELAY_MS);
    expect(shell().classList.contains(mod.PEEK_CLASS)).toBe(true);

    link.dispatchEvent(new FocusEvent('focusout', { bubbles: true, relatedTarget: document.getElementById('aside-burger') }));
    vi.advanceTimersByTime(mod.PEEK_CLOSE_DELAY_MS);
    expect(shell().classList.contains(mod.PEEK_CLASS)).toBe(true);

    link.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
    expect(shell().classList.contains(mod.PEEK_CLASS)).toBe(true);
    link.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    expect(shell().classList.contains(mod.PEEK_CLASS)).toBe(false);

    link.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
    vi.advanceTimersByTime(0);
    link.dispatchEvent(new FocusEvent('focusout', { bubbles: true, relatedTarget: document.getElementById('outside') }));
    vi.advanceTimersByTime(mod.PEEK_CLOSE_DELAY_MS);
    expect(shell().classList.contains(mod.PEEK_CLASS)).toBe(false);
  });

  it('a mode change ends the peek and cancels a pending one', () => {
    mod.bindNowoUiShell();
    shell().classList.add(mod.PEEK_CLASS);
    document.getElementById('aside-burger')!.click();
    expect(shell().classList.contains(mod.PEEK_CLASS)).toBe(false);

    mod.setSidebarMode(shell(), 'rail', false);
    pointer('pointerenter');
    mod.setSidebarMode(shell(), 'rail', false);
    vi.advanceTimersByTime(mod.PEEK_OPEN_DELAY_MS);
    expect(shell().classList.contains(mod.PEEK_CLASS)).toBe(false);
  });

  it('moves the focus to the burger that is shown when the clicked one gets hidden', () => {
    mod.bindNowoUiShell();
    const header = document.getElementById('header-burger')!;
    const inAside = document.getElementById('aside-burger')!;
    const shown = new Map<Element, boolean>([[header, true], [inAside, false]]);
    const rects = (el: Element) => (shown.get(el) ? [{}] : []) as unknown as DOMRectList;
    vi.spyOn(header, 'getClientRects').mockImplementation(() => rects(header));
    vi.spyOn(inAside, 'getClientRects').mockImplementation(() => rects(inAside));

    inAside.focus();
    mod.keepBurgerFocus(shell(), inAside);
    expect(document.activeElement).toBe(header);

    // nothing to do when the clicked burger is still shown, is not focused, or there is no shell
    shown.set(header, true);
    header.focus();
    mod.keepBurgerFocus(shell(), header);
    expect(document.activeElement).toBe(header);
    mod.keepBurgerFocus(shell(), inAside);
    mod.keepBurgerFocus(null, header);
    expect(document.activeElement).toBe(header);

    // no other burger shown: the focus stays
    shown.set(header, false);
    header.focus();
    mod.keepBurgerFocus(shell(), header);
    expect(document.activeElement).toBe(header);
  });
});
