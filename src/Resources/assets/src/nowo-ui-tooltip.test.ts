import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import * as mod from './nowo-ui-tooltip';

function rect(el: Element, box: { top: number; bottom: number; left: number; width: number }): void {
  (el as HTMLElement).getBoundingClientRect = () =>
    ({ ...box, right: box.left + box.width, height: box.bottom - box.top, x: box.left, y: box.top, toJSON: () => ({}) }) as DOMRect;
}

function size(el: Element, width: number, height: number): void {
  Object.defineProperty(el, 'offsetWidth', { configurable: true, value: width });
  Object.defineProperty(el, 'offsetHeight', { configurable: true, value: height });
}

describe('nowo-ui-tooltip', () => {
  let root: HTMLElement;
  let trigger: HTMLElement;
  let bubble: HTMLElement;

  beforeEach(() => {
    document.body.innerHTML = `
      <span class="nowo-ui-tooltip" data-nowo-ui-tooltip id="r1">
        <button type="button" id="t1" aria-describedby="tip1">Export</button>
        <span class="nowo-ui-tooltip__bubble" id="tip1" role="tooltip">Exports the rows</span>
      </span>
      <span class="nowo-ui-tooltip" data-nowo-ui-tooltip id="r2">
        <button type="button" id="t2" aria-describedby="tip2">Other</button>
        <span class="nowo-ui-tooltip__bubble" id="tip2" role="tooltip">Other help</span>
      </span>
      <button type="button" id="outside">Out</button>`;
    root = document.getElementById('r1')!;
    trigger = document.getElementById('t1')!;
    bubble = document.getElementById('tip1')!;
    rect(trigger, { top: 100, bottom: 130, left: 200, width: 80 });
    size(bubble, 120, 30);
    mod.bindNowoUiTooltip();
  });

  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('marks the roots enhanced', () => {
    expect(root.hasAttribute('data-nowo-ui-tooltip-ready')).toBe(true);
  });

  it('opens on hover above the trigger, centred, and closes when the pointer leaves', () => {
    trigger.dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));
    expect(root.classList.contains('is-open')).toBe(true);
    expect(bubble.style.top).toBe('64px');
    expect(bubble.style.left).toBe('180px');
    expect(bubble.getAttribute('data-placement')).toBe('top');
    // Moving onto the bubble keeps it open (hoverable).
    trigger.dispatchEvent(new MouseEvent('mouseout', { bubbles: true, relatedTarget: bubble }));
    expect(root.classList.contains('is-open')).toBe(true);
    bubble.dispatchEvent(new MouseEvent('mouseout', { bubbles: true, relatedTarget: document.getElementById('outside') }));
    expect(root.classList.contains('is-open')).toBe(false);
  });

  it('opens on focus, stays while focused when the pointer leaves, Escape dismisses', () => {
    trigger.focus();
    expect(root.classList.contains('is-open')).toBe(true);
    trigger.dispatchEvent(new MouseEvent('mouseout', { bubbles: true, relatedTarget: document.body }));
    expect(root.classList.contains('is-open')).toBe(true);
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    expect(root.classList.contains('is-open')).toBe(false);
    expect(document.activeElement).toBe(trigger);
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'a' }));
    document.getElementById('outside')!.focus();
    expect(root.classList.contains('is-open')).toBe(false);
  });

  it('goes below the trigger when the top has no room and stays inside the viewport', () => {
    rect(trigger, { top: 10, bottom: 40, left: 0, width: 40 });
    mod.openTooltip(root);
    expect(bubble.style.top).toBe('46px');
    expect(bubble.style.left).toBe('8px');
    expect(bubble.getAttribute('data-placement')).toBe('bottom');
  });

  it('only one bubble is open at a time', () => {
    mod.openTooltip(root);
    mod.openTooltip(document.getElementById('r2')!);
    expect(root.classList.contains('is-open')).toBe(false);
    window.dispatchEvent(new Event('resize'));
    expect(document.getElementById('r2')!.classList.contains('is-open')).toBe(true);
  });

  it('a tap toggles it on touch screens; mouse pointerdown does nothing', () => {
    const tap = (target: Element, pointerType: string): void => {
      const event = new Event('pointerdown', { bubbles: true }) as PointerEvent;
      Object.defineProperty(event, 'pointerType', { value: pointerType });
      target.dispatchEvent(event);
    };
    tap(trigger, 'mouse');
    expect(root.classList.contains('is-open')).toBe(false);
    tap(trigger, 'touch');
    expect(root.classList.contains('is-open')).toBe(true);
    tap(trigger, 'touch');
    expect(root.classList.contains('is-open')).toBe(false);
    mod.openTooltip(root);
    tap(document.getElementById('outside')!, 'touch');
    expect(root.classList.contains('is-open')).toBe(false);
  });

  it('place() ignores a root without bubble; events outside roots are ignored', () => {
    const lone = document.createElement('span');
    lone.setAttribute('data-nowo-ui-tooltip', '');
    document.body.appendChild(lone);
    expect(() => mod.place(lone)).not.toThrow();
    document.getElementById('outside')!.dispatchEvent(new MouseEvent('mouseout', { bubbles: true }));
    document.dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));
    expect(root.classList.contains('is-open')).toBe(false);
  });
});
