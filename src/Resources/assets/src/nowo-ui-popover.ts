/**
 * Popover menus (2.4, `popover: true` on the `menu` / `kebab` / `row_actions` partials, or theme.popover_menus).
 * The panel is a native `[popover]` in the browser top layer, so table scrollers and cards never clip it; the
 * toggle is a `<button popovertarget>` (the browser opens / closes it, light-dismisses it on an outside click, no JS
 * needed). This runtime adds, by delegation:
 *  - placement: under the toggle, end edges aligned (2.27: start edges for a root with `nowo-ui-menu--start` or
 *    `data-nowo-ui-popover-align="start"`, the right edge in RTL), flipped above when the viewport has no room below,
 *    kept inside the viewport (`placePopover`);
 *  - `aria-expanded` on the toggle;
 *  - close on scroll (outside the panel) and resize — no stale position — and after choosing an item (a link, a
 *    submit button that does not open a dialog, or 2.27 a `button[data-nowo-ui-menu-button]` hash item);
 *  - Escape closes the open menu first and returns focus to its toggle, without reaching a surrounding dialog (a
 *    dialog opened from inside the menu still closes first).
 * 2.28 `menu_semantics: 'menu'` (root `data-nowo-ui-menu-semantics="menu"`): APG menu button — the panel's entries
 * get `role="none"` / `role="menuitem"` (Action entries rendered by the `action` partial too), opening focuses the
 * first item (the last when opened with ArrowUp from the toggle), ArrowDown / ArrowUp / Home / End move a roving
 * focus, Tab closes the menu.
 * Markup: `[data-nowo-ui-popover-menu]` root, `[data-nowo-ui-popover-trigger]` toggle, `[data-nowo-ui-popover-panel]`
 * panel. Ported from the symfony-beacon `popover_menu` Stimulus controller. No inline handlers (CSP).
 */

export const ROOT = '[data-nowo-ui-popover-menu]';
export const TRIGGER = '[data-nowo-ui-popover-trigger]';
export const PANEL = '[data-nowo-ui-popover-panel]';
/** Gap between the toggle and the panel (px). */
export const GAP_PX = 4;
/** Minimum distance between the panel and the viewport edges (px). */
export const EDGE_PX = 8;

/** Root marker of APG menus (2.28). */
export const MENU_SEMANTICS = '[data-nowo-ui-menu-semantics="menu"]';

/** Whether a panel follows the APG menu pattern. */
export function isMenu(panel: HTMLElement): boolean {
  return panel.closest(ROOT)?.matches(MENU_SEMANTICS) === true;
}

/** The menu items of a panel (one control per entry), with their roles set; disabled controls are skipped. */
export function menuItems(panel: HTMLElement): HTMLElement[] {
  const items: HTMLElement[] = [];
  panel.querySelectorAll<HTMLElement>(':scope > li').forEach((li) => {
    li.setAttribute('role', 'none');
    const control = li.querySelector<HTMLElement>('a[href], button:not([disabled]), input[type="submit"]:not([disabled])');
    if (!control) {
      return;
    }
    control.setAttribute('role', 'menuitem');
    control.setAttribute('tabindex', '-1');
    items.push(control);
  });
  return items;
}

/** Moves the roving focus of a menu: `first`, `last`, or one step `next` / `prev` (wrapping). */
export function moveFocus(panel: HTMLElement, where: 'first' | 'last' | 'next' | 'prev'): HTMLElement | null {
  const items = menuItems(panel);
  if (items.length === 0) {
    return null;
  }
  const current = items.indexOf(document.activeElement as HTMLElement);
  let index = 0;
  if (where === 'last') {
    index = items.length - 1;
  } else if (where === 'next') {
    index = current === -1 ? 0 : (current + 1) % items.length;
  } else if (where === 'prev') {
    index = current === -1 ? items.length - 1 : (current - 1 + items.length) % items.length;
  }
  const target = items[index] ?? null;
  target?.focus();
  return target;
}

/** Where to put focus when a menu opens (ArrowUp on the toggle = the last item). */
let openFocus: 'first' | 'last' = 'first';

/** Panels opened through this runtime (the `toggle` event), most recent last. */
const openPanels: HTMLElement[] = [];

/** The toggle of a panel: the root's trigger, else any `[popovertarget="<id>"]`. */
export function triggerOf(panel: HTMLElement): HTMLElement | null {
  const fromRoot = panel.closest(ROOT)?.querySelector<HTMLElement>(TRIGGER);
  if (fromRoot) {
    return fromRoot;
  }
  if (!panel.id) {
    return null;
  }
  return [...document.querySelectorAll<HTMLElement>('[popovertarget]')].find((el) => el.getAttribute('popovertarget') === panel.id) ?? null;
}

/** Root selector of the menus whose panel aligns with the toggle's start edge (2.27). */
export const ALIGN_START = '.nowo-ui-menu--start, [data-nowo-ui-popover-align="start"]';

/** Whether the panel's left edge follows the toggle's left edge: start-aligned menus in left-to-right text. */
function alignsLeft(trigger: HTMLElement, panel: HTMLElement): boolean {
  if (!panel.closest(ROOT)?.matches(ALIGN_START)) {
    return false;
  }
  return getComputedStyle(trigger).direction !== 'rtl';
}

/** Below the trigger, end edges aligned (start edges for start-aligned menus); above it when the viewport has no room
 * below; kept inside the viewport. */
export function placePopover(trigger: HTMLElement, panel: HTMLElement): void {
  const rect = trigger.getBoundingClientRect();
  const width = panel.offsetWidth;
  const height = panel.offsetHeight;
  const below = rect.bottom + GAP_PX;
  const above = rect.top - GAP_PX - height;
  const top = below + height > window.innerHeight - EDGE_PX && above >= EDGE_PX ? above : below;
  const start = alignsLeft(trigger, panel) ? rect.left : rect.right - width;
  const left = Math.min(Math.max(EDGE_PX, start), window.innerWidth - EDGE_PX - width);
  panel.style.top = `${Math.round(top)}px`;
  panel.style.left = `${Math.round(Math.max(EDGE_PX, left))}px`;
}

/** Hides a panel (no-op when it is not open or the browser lacks the Popover API). */
export function closePopover(panel: HTMLElement): void {
  const index = openPanels.indexOf(panel);
  if (index === -1) {
    return;
  }
  if (typeof panel.hidePopover === 'function') {
    try {
      panel.hidePopover();
    } catch {
      // already hidden (InvalidStateError)
    }
  }
  forget(panel);
}

function forget(panel: HTMLElement): void {
  const index = openPanels.indexOf(panel);
  if (index !== -1) {
    openPanels.splice(index, 1);
  }
  triggerOf(panel)?.setAttribute('aria-expanded', 'false');
  if (openPanels.length === 0) {
    window.removeEventListener('scroll', onMove, true);
    window.removeEventListener('resize', onMove);
  }
}

/** Whether a panel is currently open through this runtime. */
export function isOpen(panel: HTMLElement): boolean {
  return openPanels.includes(panel);
}

function onToggle(event: Event): void {
  const panel = event.target;
  if (!(panel instanceof HTMLElement) || !panel.matches(PANEL)) {
    return;
  }
  if ((event as Event & { newState?: string }).newState === 'open') {
    if (!openPanels.includes(panel)) {
      openPanels.push(panel);
    }
    const trigger = triggerOf(panel);
    trigger?.setAttribute('aria-expanded', 'true');
    if (trigger) {
      placePopover(trigger, panel);
    }
    if (isMenu(panel)) {
      moveFocus(panel, openFocus);
    }
    openFocus = 'first';
    window.addEventListener('scroll', onMove, true);
    window.addEventListener('resize', onMove);
  } else {
    forget(panel);
  }
}

function onMove(event: Event): void {
  [...openPanels].forEach((panel) => {
    if (event.type === 'scroll' && event.target instanceof Node && panel.contains(event.target)) {
      return;
    }
    closePopover(panel);
  });
}

/** 2.28 APG menus: arrow keys on the toggle open the menu; inside it they move the roving focus; Tab closes. */
function onMenuKeydown(event: KeyboardEvent): boolean {
  const active = document.activeElement;
  if (!(active instanceof HTMLElement)) {
    return false;
  }
  const trigger = active.closest<HTMLElement>(TRIGGER);
  const triggerPanel = trigger ? (trigger.closest(ROOT)?.querySelector<HTMLElement>(PANEL) ?? null) : null;
  if (trigger && triggerPanel && isMenu(triggerPanel) && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
    event.preventDefault();
    if (isOpen(triggerPanel)) {
      moveFocus(triggerPanel, event.key === 'ArrowDown' ? 'first' : 'last');
    } else if (typeof triggerPanel.showPopover === 'function') {
      openFocus = event.key === 'ArrowDown' ? 'first' : 'last';
      triggerPanel.showPopover();
    }
    return true;
  }
  const panel = active.closest<HTMLElement>(PANEL);
  if (!panel || !isMenu(panel) || !isOpen(panel)) {
    return false;
  }
  const moves: Record<string, 'first' | 'last' | 'next' | 'prev'> = { ArrowDown: 'next', ArrowUp: 'prev', Home: 'first', End: 'last' };
  const move = moves[event.key];
  if (move) {
    event.preventDefault();
    moveFocus(panel, move);
    return true;
  }
  if (event.key === 'Tab') {
    closePopover(panel);
  }
  return false;
}

function onKeydown(event: KeyboardEvent): void {
  if (onMenuKeydown(event)) {
    return;
  }
  if (event.key !== 'Escape' || openPanels.length === 0) {
    return;
  }
  const panel = openPanels[openPanels.length - 1];
  const active = document.activeElement;
  // A dialog opened from inside the menu (confirm) is on top: let it close first.
  const innerDialog = active instanceof Element ? active.closest('dialog[open]') : null;
  if (innerDialog && panel.contains(innerDialog)) {
    return;
  }
  event.preventDefault();
  event.stopImmediatePropagation();
  closePopover(panel);
  triggerOf(panel)?.focus();
}

function onClick(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) {
    return;
  }
  const item = target.closest('a[href], button[type="submit"], input[type="submit"], button[data-nowo-ui-menu-button]');
  const panel = item?.closest<HTMLElement>(PANEL);
  if (!item || !panel || item.hasAttribute('data-nowo-ui-dialog-open')) {
    return;
  }
  // A submit inside a dialog opened from the menu (confirm): the dialog handles it.
  const dialog = item.closest('dialog');
  if (dialog && panel.contains(dialog)) {
    return;
  }
  closePopover(panel);
}

let bound = false;

export function bindNowoUiPopover(): void {
  if (bound) {
    return;
  }
  bound = true;
  // `toggle` does not bubble: listen in the capture phase. Escape in the capture phase too, so the menu closes
  // before a surrounding dialog handles the key.
  document.addEventListener('toggle', onToggle, true);
  document.addEventListener('keydown', onKeydown, true);
  document.addEventListener('click', onClick);
}

bindNowoUiPopover();
