import { afterEach, beforeEach, describe, expect, it, vi, type Mock } from 'vitest';
import * as mod from './nowo-ui-segmented';

function key(el: HTMLElement, name: string): KeyboardEvent {
  const event = new KeyboardEvent('keydown', { key: name, bubbles: true, cancelable: true });
  el.dispatchEvent(event);
  return event;
}

describe('nowo-ui-segmented', () => {
  let all: HTMLElement;
  let involved: HTMLElement;
  let input: HTMLInputElement;
  let change: Mock<() => void>;

  beforeEach(() => {
    document.body.innerHTML = `
      <div role="radiogroup" data-nowo-ui-segmented>
        <button type="button" role="radio" id="all" aria-checked="true" tabindex="0" class="is-active" data-nowo-ui-segmented-option data-nowo-ui-segmented-checked="false">All</button>
        <button type="button" role="radio" id="involved" aria-checked="false" tabindex="-1" data-nowo-ui-segmented-option data-nowo-ui-segmented-checked="true">Involved</button>
        <input type="checkbox" id="input" data-nowo-ui-segmented-input>
      </div>
      <button type="button" id="stray" data-nowo-ui-segmented-option>stray</button>
    `;
    all = document.getElementById('all')!;
    involved = document.getElementById('involved')!;
    input = document.getElementById('input') as HTMLInputElement;
    change = vi.fn<() => void>();
    input.addEventListener('change', change);
    mod.bindNowoUiSegmented();
  });

  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('selects an option on click, syncs the checkbox and dispatches change once', () => {
    involved.click();
    expect(input.checked).toBe(true);
    expect(change).toHaveBeenCalledTimes(1);
    expect(involved.getAttribute('aria-checked')).toBe('true');
    expect(involved.classList.contains('is-active')).toBe(true);
    expect(involved.tabIndex).toBe(0);
    expect(all.getAttribute('aria-checked')).toBe('false');
    expect(all.tabIndex).toBe(-1);

    involved.click();
    expect(change).toHaveBeenCalledTimes(1);
    all.click();
    expect(input.checked).toBe(false);
    expect(change).toHaveBeenCalledTimes(2);
  });

  it('moves and selects with the arrow keys, Home and End', () => {
    expect(key(all, 'ArrowRight').defaultPrevented).toBe(true);
    expect(document.activeElement).toBe(involved);
    expect(input.checked).toBe(true);
    key(involved, 'ArrowDown');
    expect(input.checked).toBe(false);
    key(all, 'ArrowLeft');
    expect(input.checked).toBe(true);
    key(involved, 'ArrowUp');
    expect(input.checked).toBe(false);
    key(all, 'End');
    expect(input.checked).toBe(true);
    key(involved, 'Home');
    expect(input.checked).toBe(false);
    expect(key(all, 'a').defaultPrevented).toBe(false);
  });

  it('ignores events outside a group and groups without an input', () => {
    document.getElementById('stray')!.click();
    key(document.getElementById('stray')!, 'ArrowRight');
    document.body.click();
    input.remove();
    expect(() => mod.applySegmented(document.querySelector(mod.GROUP)!, true)).not.toThrow();
    expect(involved.getAttribute('aria-checked')).toBe('true');
    document.dispatchEvent(new MouseEvent('click'));
  });
});
