import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import * as mod from './nowo-ui-popover';

/**
 * 2.61: the `kebab` partial with `popover: true, menu_semantics: 'menu'` (row actions of the admin catalogue) is an
 * APG menu button run by nowo-ui-popover.js: its <a role="menuitem"> items (no <li>) take the roving focus.
 */
function toggle(panel: HTMLElement, newState: 'open' | 'closed'): void {
  const event = new Event('toggle');
  Object.defineProperty(event, 'newState', { value: newState });
  panel.dispatchEvent(event);
}

describe('kebab menu (popover + menu semantics)', () => {
  let trigger: HTMLElement;
  let panel: HTMLElement;

  beforeEach(() => {
    document.body.innerHTML = `
      <div class="nowo-ui-kebab" data-nowo-ui-kebab data-nowo-ui-popover-menu data-nowo-ui-menu-semantics="menu">
        <button type="button" id="t" class="nowo-ui-kebab__summary" popovertarget="row-menu-1" aria-haspopup="menu" aria-expanded="false" aria-label="Actions for INV-1001" aria-controls="row-menu-1" data-nowo-ui-popover-trigger>⋮</button>
        <div class="nowo-ui-kebab__menu" id="row-menu-1" role="menu" popover data-nowo-ui-popover-panel>
          <a class="nowo-ui-kebab__item" href="#v" role="menuitem" id="view">View</a>
          <a class="nowo-ui-kebab__item" href="#e" role="menuitem" id="edit">Edit</a>
          <hr class="nowo-ui-kebab__separator" role="separator">
          <div class="nowo-ui-kebab__row" role="group" data-nowo-ui-menu-row><a href="#r" role="menuitem" id="react">👍</a></div>
          <a class="nowo-ui-kebab__item nowo-ui-kebab__item--danger" href="#d" role="menuitem" id="delete">Delete</a>
        </div>
      </div>`;
    trigger = document.getElementById('t')!;
    panel = document.getElementById('row-menu-1')!;
    mod.bindNowoUiPopover();
  });

  afterEach(() => {
    if (mod.isOpen(panel)) {
      toggle(panel, 'closed');
    }
    document.body.innerHTML = '';
  });

  it('collects the role="menuitem" links in order, skipping the separator', () => {
    expect(mod.menuItems(panel).map((i) => i.id)).toEqual(['view', 'edit', 'react', 'delete']);
    expect(document.getElementById('view')!.getAttribute('tabindex')).toBe('-1');
  });

  it('opening focuses the first item and marks the toggle expanded; arrows move, wrapping', () => {
    trigger.focus();
    toggle(panel, 'open');
    expect(trigger.getAttribute('aria-expanded')).toBe('true');
    expect(document.activeElement?.id).toBe('view');
    document.activeElement!.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
    expect(document.activeElement?.id).toBe('edit');
    document.activeElement!.dispatchEvent(new KeyboardEvent('keydown', { key: 'End', bubbles: true }));
    expect(document.activeElement?.id).toBe('delete');
    document.activeElement!.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
    expect(document.activeElement?.id).toBe('view');
    document.activeElement!.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowUp', bubbles: true }));
    expect(document.activeElement?.id).toBe('delete');
  });

  it('a kebab without menu semantics keeps the 2.58 behaviour (no roving focus)', () => {
    panel.closest('[data-nowo-ui-popover-menu]')!.removeAttribute('data-nowo-ui-menu-semantics');
    trigger.focus();
    toggle(panel, 'open');
    expect(document.activeElement).toBe(trigger);
  });
});
