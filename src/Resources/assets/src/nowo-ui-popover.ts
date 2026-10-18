/**
 * Popover menus (2.4, `popover: true` on the `menu` / `kebab` / `row_actions` partials, or theme.popover_menus).
 * The panel is a native `[popover]` in the browser top layer, so table scrollers and cards never clip it; the
 * toggle is a `<button popovertarget>` (the browser opens / closes it, light-dismisses it on an outside click, no JS
 * needed). This runtime adds, by delegation:
 *  - placement: under the toggle, end edges aligned, flipped above when the viewport has no room below, kept inside
 *    the viewport (`placePopover`);
 *  - `aria-expanded` on the toggle;
 *  - close on scroll (outside the panel) and resize — no stale position — and after choosing an item (a link or a
 *    submit button that does not open a dialog);
 *  - Escape closes the open menu first and returns focus to its toggle, without reaching a surrounding dialog (a
 *    dialog opened from inside the menu still closes first).
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

/** Below the trigger, end edges aligned; above it when the viewport has no room below; kept inside the viewport. */
export function placePopover(trigger: HTMLElement, panel: HTMLElement): void {
  const rect = trigger.getBoundingClientRect();
  const width = panel.offsetWidth;
  const height = panel.offsetHeight;
  const below = rect.bottom + GAP_PX;
  const above = rect.top - GAP_PX - height;
  const top = below + height > window.innerHeight - EDGE_PX && above >= EDGE_PX ? above : below;
  const left = Math.min(Math.max(EDGE_PX, rect.right - width), window.innerWidth - EDGE_PX - width);
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

function onKeydown(event: KeyboardEvent): void {
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
  const item = target.closest('a[href], button[type="submit"], input[type="submit"]');
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
