import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { closeWithEffect } from './nowo-ui-motion-core';
import * as mod from './nowo-ui-popover';

function toggle(panel: HTMLElement, newState: 'open' | 'closed'): void {
  const event = new Event('toggle');
  Object.defineProperty(event, 'newState', { value: newState });
  panel.dispatchEvent(event);
}

function rect(el: HTMLElement, box: { top: number; bottom: number; right: number }): void {
  el.getBoundingClientRect = () => ({ ...box, left: box.right - 32, width: 32, height: box.bottom - box.top, x: 0, y: 0, toJSON: () => ({}) }) as DOMRect;
}

function size(el: HTMLElement, width: number, height: number): void {
  Object.defineProperty(el, 'offsetWidth', { configurable: true, value: width });
  Object.defineProperty(el, 'offsetHeight', { configurable: true, value: height });
}

describe('nowo-ui-popover', () => {
  let trigger: HTMLElement;
  let panel: HTMLElement;

  beforeEach(() => {
    document.body.innerHTML = `
      <dialog id="outer" open>
        <div data-nowo-ui-popover-menu>
          <button type="button" id="t" popovertarget="m1" aria-expanded="false" data-nowo-ui-popover-trigger>⋮</button>
          <ul id="m1" popover data-nowo-ui-popover-panel>
            <li><a id="link" href="/x">Go</a></li>
            <li><button type="button" id="confirm" data-nowo-ui-dialog-open="c1">Delete</button></li>
            <li><dialog id="c1" open><button type="submit" id="inner-submit">OK</button><input id="inner-input"></dialog></li>
          </ul>
        </div>
      </dialog>
      <button type="button" id="loose" popovertarget="m2">x</button>
      <div id="m2" popover data-nowo-ui-popover-panel><a id="m2-link" href="/y">Y</a></div>
      <div id="m3" popover data-nowo-ui-popover-panel></div>
      <p id="other" popover>not a menu</p>
    `;
    trigger = document.getElementById('t')!;
    panel = document.getElementById('m1')!;
    panel.hidePopover = vi.fn();
    vi.stubGlobal('innerWidth', 400);
    vi.stubGlobal('innerHeight', 300);
    mod.bindNowoUiPopover();
  });

  afterEach(() => {
    document.querySelectorAll<HTMLElement>(mod.PANEL).forEach((p) => {
      if (mod.isOpen(p)) {
        toggle(p, 'closed');
      }
    });
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
  });

  it('places the panel under the trigger, end-aligned, and flips it above without room', () => {
    size(panel, 120, 80);
    rect(trigger, { top: 40, bottom: 72, right: 200 });
    mod.placePopover(trigger, panel);
    expect([panel.style.top, panel.style.left]).toEqual(['76px', '80px']);

    rect(trigger, { top: 250, bottom: 282, right: 60 });
    mod.placePopover(trigger, panel);
    expect([panel.style.top, panel.style.left]).toEqual(['166px', '8px']);

    // No room above either: stays below; kept inside the right edge.
    size(panel, 120, 290);
    rect(trigger, { top: 100, bottom: 132, right: 600 });
    mod.placePopover(trigger, panel);
    expect([panel.style.top, panel.style.left]).toEqual(['136px', '272px']);
  });

  it('aligns start edges for start-aligned menus (the right edge in RTL)', () => {
    const root = trigger.closest<HTMLElement>(mod.ROOT)!;
    root.setAttribute('data-nowo-ui-popover-align', 'start');
    size(panel, 120, 80);
    rect(trigger, { top: 40, bottom: 72, right: 100 });
    mod.placePopover(trigger, panel);
    expect([panel.style.top, panel.style.left]).toEqual(['76px', '68px']);

    root.removeAttribute('data-nowo-ui-popover-align');
    root.classList.add('nowo-ui-menu--start');
    trigger.style.direction = 'rtl';
    mod.placePopover(trigger, panel);
    expect(panel.style.left).toBe('8px');

    // A panel outside any root keeps the end alignment.
    const m2 = document.getElementById('m2')!;
    size(m2, 120, 80);
    mod.placePopover(trigger, m2);
    expect(m2.style.left).toBe('8px');
  });

  it('closes after choosing a hash item button (2.27), not after other buttons', () => {
    panel.insertAdjacentHTML('beforeend', '<li><button type="button" id="menu-button" data-nowo-ui-menu-button>Open</button></li><li><button type="button" id="plain">Plain</button></li>');
    toggle(panel, 'open');
    document.getElementById('plain')!.click();
    expect(mod.isOpen(panel)).toBe(true);
    document.getElementById('menu-button')!.click();
    expect(mod.isOpen(panel)).toBe(false);
  });

  it('tracks aria-expanded and places on open; ignores other popovers', () => {
    size(panel, 100, 50);
    rect(trigger, { top: 10, bottom: 40, right: 150 });
    toggle(panel, 'open');
    toggle(panel, 'open');
    expect(mod.isOpen(panel)).toBe(true);
    expect(trigger.getAttribute('aria-expanded')).toBe('true');
    expect(panel.style.top).toBe('44px');
    toggle(document.getElementById('other')!, 'open');
    toggle(panel, 'closed');
    expect(mod.isOpen(panel)).toBe(false);
    expect(trigger.getAttribute('aria-expanded')).toBe('false');
  });

  it('finds a loose trigger by popovertarget and handles a panel without one', () => {
    const m2 = document.getElementById('m2')!;
    expect(mod.triggerOf(m2)).toBe(document.getElementById('loose'));
    const m3 = document.getElementById('m3')!;
    expect(mod.triggerOf(m3)).toBeNull();
    m3.removeAttribute('id');
    expect(mod.triggerOf(m3)).toBeNull();
    toggle(m3, 'open');
    expect(mod.isOpen(m3)).toBe(true);
    // Without the Popover API the runtime still forgets the panel.
    mod.closePopover(m3);
    expect(mod.isOpen(m3)).toBe(false);
    mod.closePopover(m3);
  });

  it('closes on resize and on scroll outside the panel, not on scroll inside it', () => {
    toggle(panel, 'open');
    panel.dispatchEvent(new Event('scroll'));
    expect(mod.isOpen(panel)).toBe(true);
    window.dispatchEvent(new Event('resize'));
    expect(panel.hidePopover).toHaveBeenCalledTimes(1);
    expect(mod.isOpen(panel)).toBe(false);

    toggle(panel, 'open');
    document.body.dispatchEvent(new Event('scroll'));
    expect(mod.isOpen(panel)).toBe(false);
  });

  it('swallows a hidePopover error (already hidden)', () => {
    panel.hidePopover = () => {
      throw new DOMException('hidden', 'InvalidStateError');
    };
    toggle(panel, 'open');
    mod.closePopover(panel);
    expect(mod.isOpen(panel)).toBe(false);
  });

  it('Escape closes the menu before the surrounding dialog and refocuses the trigger', () => {
    const outerKeydown = vi.fn();
    document.getElementById('outer')!.addEventListener('keydown', outerKeydown);
    document.addEventListener('keydown', outerKeydown);

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', cancelable: true }));
    expect(outerKeydown).toHaveBeenCalledTimes(1); // nothing open: untouched

    toggle(panel, 'open');
    document.getElementById('link')!.focus();
    const event = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
    document.getElementById('link')!.dispatchEvent(event);
    expect(event.defaultPrevented).toBe(true);
    expect(outerKeydown).toHaveBeenCalledTimes(1);
    expect(mod.isOpen(panel)).toBe(false);
    expect(document.activeElement).toBe(trigger);

    toggle(panel, 'open');
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter' }));
    expect(mod.isOpen(panel)).toBe(true);
  });

  it('a panel the browser closed without a toggle event does not take the Escape (WebKit, a modal opening from the menu)', () => {
    panel.showPopover = vi.fn();
    toggle(panel, 'open');
    expect(mod.isOpen(panel)).toBe(true);
    trigger.focus();
    const event = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
    document.dispatchEvent(event);
    expect(event.defaultPrevented).toBe(false);
    expect(mod.isOpen(panel)).toBe(false);
  });

  it('lets a dialog opened from the menu close first on Escape', () => {
    toggle(panel, 'open');
    const input = document.getElementById('inner-input') as HTMLInputElement;
    input.focus();
    const event = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
    input.dispatchEvent(event);
    expect(event.defaultPrevented).toBe(false);
    expect(mod.isOpen(panel)).toBe(true);
  });

  it('closes after choosing an item, not when the item opens a dialog', () => {
    toggle(panel, 'open');
    document.getElementById('confirm')!.click();
    expect(mod.isOpen(panel)).toBe(true);
    document.getElementById('inner-submit')!.click();
    expect(mod.isOpen(panel)).toBe(true);
    const link = document.getElementById('link')!;
    link.addEventListener('click', (e) => e.preventDefault());
    link.click();
    expect(mod.isOpen(panel)).toBe(false);

    // Clicks elsewhere are ignored.
    document.body.click();
    document.dispatchEvent(new MouseEvent('click'));
  });
});

describe('nowo-ui-popover APG menu (2.28 menu_semantics: menu)', () => {
  const key = (el: Element, name: string): KeyboardEvent => {
    const event = new KeyboardEvent('keydown', { key: name, bubbles: true, cancelable: true });
    el.dispatchEvent(event);
    return event;
  };
  let panel: HTMLElement;
  let trigger: HTMLElement;

  beforeEach(() => {
    document.body.innerHTML = `
      <div data-nowo-ui-popover-menu data-nowo-ui-menu-semantics="menu">
        <button type="button" id="mt" popovertarget="mm" aria-haspopup="menu" aria-expanded="false" aria-controls="mm" data-nowo-ui-popover-trigger>⋮</button>
        <ul id="mm" popover data-nowo-ui-popover-panel role="menu" aria-labelledby="mt">
          <li role="none"><a id="i1" href="/a" role="menuitem" tabindex="-1">A</a></li>
          <li class="nowo-ui-menu__entry"><form method="post"><button type="submit" id="i2">B</button></form></li>
          <li><button type="button" id="i3" disabled>C</button></li>
          <li><button type="button" id="i4">D</button></li>
        </ul>
      </div>
      <div data-nowo-ui-popover-menu><button id="plain-t" data-nowo-ui-popover-trigger popovertarget="pp">x</button><ul id="pp" popover data-nowo-ui-popover-panel><li><a id="p1" href="/p">P</a></li></ul></div>`;
    panel = document.getElementById('mm')!;
    trigger = document.getElementById('mt')!;
    panel.hidePopover = vi.fn();
    panel.showPopover = vi.fn(() => toggle(panel, 'open'));
    mod.bindNowoUiPopover();
  });

  afterEach(() => {
    if (mod.isOpen(panel)) {
      mod.closePopover(panel);
    }
    document.body.innerHTML = '';
  });

  it('sets the item roles, skipping disabled controls', () => {
    const items = mod.menuItems(panel);
    expect(items.map((item) => item.id)).toEqual(['i1', 'i2', 'i4']);
    expect(document.getElementById('i2')?.getAttribute('role')).toBe('menuitem');
    expect(document.getElementById('i2')?.closest('li')?.getAttribute('role')).toBe('none');
    expect(mod.isMenu(panel)).toBe(true);
    expect(mod.isMenu(document.getElementById('pp')!)).toBe(false);
  });

  it('opens with ArrowDown on the toggle (first item) and ArrowUp (last item)', () => {
    trigger.focus();
    expect(key(trigger, 'ArrowDown').defaultPrevented).toBe(true);
    expect(document.activeElement?.id).toBe('i1');
    mod.closePopover(panel);
    trigger.focus();
    key(trigger, 'ArrowUp');
    expect(document.activeElement?.id).toBe('i4');
    trigger.focus();
    key(trigger, 'ArrowDown');
    expect(document.activeElement?.id).toBe('i1');
  });

  it('moves a roving focus with the arrows, Home and End, and closes on Tab', () => {
    toggle(panel, 'open');
    expect(document.activeElement?.id).toBe('i1');
    key(document.activeElement!, 'ArrowDown');
    expect(document.activeElement?.id).toBe('i2');
    key(document.activeElement!, 'ArrowUp');
    key(document.activeElement!, 'ArrowUp');
    expect(document.activeElement?.id).toBe('i4');
    key(document.activeElement!, 'Home');
    expect(document.activeElement?.id).toBe('i1');
    key(document.activeElement!, 'End');
    expect(document.activeElement?.id).toBe('i4');
    key(document.activeElement!, 'ArrowDown');
    expect(document.activeElement?.id).toBe('i1');
    expect(key(document.activeElement!, 'x').defaultPrevented).toBe(false);
    key(document.activeElement!, 'Tab');
    expect(mod.isOpen(panel)).toBe(false);
  });

  it('handles empty menus and focus outside the items', () => {
    expect(mod.moveFocus(document.createElement('ul'), 'first')).toBeNull();
    (document.activeElement as HTMLElement | null)?.blur();
    expect(mod.moveFocus(panel, 'next')?.id).toBe('i1');
    (document.activeElement as HTMLElement | null)?.blur();
    expect(mod.moveFocus(panel, 'prev')?.id).toBe('i4');
  });

  it('leaves plain popover menus alone', () => {
    const plain = document.getElementById('plain-t')!;
    plain.focus();
    expect(key(plain, 'ArrowDown').defaultPrevented).toBe(false);
  });
});

describe('a toggle clicked during the fade', () => {
  it('ends the fade first, so that the browser opens the panel', () => {
    vi.useFakeTimers();
    document.body.innerHTML = '<div data-nowo-ui-popover-menu><button data-nowo-ui-popover-trigger popovertarget="fade-panel"></button><ul id="fade-panel" data-nowo-ui-popover-panel popover></ul></div>';
    const panel = document.getElementById('fade-panel')!;
    const trigger = document.querySelector<HTMLElement>('[popovertarget]')!;
    const hide = vi.fn();
    panel.hidePopover = hide;
    vi.spyOn(window, 'getComputedStyle').mockReturnValue({ transitionDuration: '110ms', transitionDelay: '0s' } as CSSStyleDeclaration);
    closeWithEffect(panel, hide);
    expect(panel.hasAttribute('data-nowo-ui-closing')).toBe(true);
    trigger.click();
    expect(hide).toHaveBeenCalledOnce();
    expect(panel.hasAttribute('data-nowo-ui-closing')).toBe(false);
    vi.restoreAllMocks();
    vi.useRealTimers();
    document.body.innerHTML = '';
  });
});
