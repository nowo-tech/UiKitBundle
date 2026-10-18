import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
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
