/**
 * Focus return of the kit dialogs (2.52.2, WCAG 2.4.3 Focus Order): when a dialog closes — Escape, Cancel, Confirm,
 * the close button, a backdrop click, a remote form saved or closed — focus goes back to the element that opened it.
 *
 * The opener is not always focusable: a page-actions menu item often forwards its click to the page's own dialog
 * trigger, which the page keeps `hidden` (symfony-beacon's `opens:` items), and the item itself sits in a menu panel
 * that is closed by then. So a dialog remembers, when it opens, an ordered list of candidates:
 *   1. its opener (the trigger the click reached),
 *   2. the menu item that invoked it (recorded by a capture-phase click listener on menu panels, or by a host script
 *      through `window.nowoUiRecordOpener(item, fallback?)`), within INVOKER_TTL_MS of the click,
 *   3. the toggles of the menus around that item, innermost first (a nested menu's toggle, then the page-actions
 *      toggle), and the fallback a host passed;
 * and on close focuses the first one that is connected and focusable (not hidden, inert, disabled, inside a closed
 * popover or <details>, nor rendered `display: none`). A plain visible trigger is the first candidate, so its
 * behaviour is unchanged. When none qualifies the browser's own dialog focus restoration stands. Focus that another
 * open modal dialog holds (a replacing or nested dialog) is never taken away.
 *
 * The state lives on `window` under Symbol.for('nowo-ui.focus-return'), shared by every IIFE (dialog, confirm,
 * popover) and the Stimulus peers, which each bundle a copy of this module. No inline handlers (CSP).
 */

declare global {
  interface Window {
    /** Records the element that is about to open a dialog by forwarding a click (2.52.2). */
    nowoUiRecordOpener?: (item: Element | null, fallback?: Element | null) => void;
  }
}

/** How long a recorded menu item stays the invoker of the next dialog that opens (ms). */
export const INVOKER_TTL_MS = 1000;

/** Menu items whose click is recorded as the invoker of a dialog. */
export const MENU_ITEM = '[data-nowo-ui-popover-panel] a[href], [data-nowo-ui-popover-panel] button, [data-nowo-ui-popover-panel] [role="menuitem"], details[data-nowo-ui-menu] a[href], details[data-nowo-ui-menu] button, [role="menu"] [role="menuitem"]';

type Invocation = { chain: HTMLElement[]; at: number };

type FocusReturnState = {
  pending: Invocation | null;
  targets: WeakMap<HTMLDialogElement, HTMLElement[]>;
  tracking: boolean;
};

const KEY = Symbol.for('nowo-ui.focus-return');

function state(): FocusReturnState {
  const holder = window as unknown as Record<symbol, FocusReturnState | undefined>;
  let current = holder[KEY];
  if (!current) {
    current = { pending: null, targets: new WeakMap(), tracking: false };
    holder[KEY] = current;
  }
  return current;
}

/** The toggle of a popover panel: its menu root's trigger, else any `[popovertarget="<id>"]`. */
function popoverToggle(panel: Element): HTMLElement | null {
  const root = panel.closest('[data-nowo-ui-popover-menu]');
  const fromRoot = root?.querySelector<HTMLElement>('[data-nowo-ui-popover-trigger]');
  if (fromRoot && !panel.contains(fromRoot)) {
    return fromRoot;
  }
  if (!panel.id) {
    return null;
  }
  return [...document.querySelectorAll<HTMLElement>('[popovertarget]')].find((el) => el.getAttribute('popovertarget') === panel.id) ?? null;
}

/** The toggles of the menus around `el`, innermost first: popover panels' toggles and closed-able <details> summaries. */
export function menuToggles(el: Element): HTMLElement[] {
  const toggles: HTMLElement[] = [];
  let from: Element = el;
  let node: Element | null = el.parentElement;
  while (node) {
    if (node.hasAttribute('popover')) {
      const toggle = popoverToggle(node);
      if (toggle && !toggles.includes(toggle)) {
        toggles.push(toggle);
        from = toggle;
        node = toggle.parentElement;
        continue;
      }
    } else if (node instanceof HTMLDetailsElement) {
      const summary = node.querySelector(':scope > summary');
      if (summary instanceof HTMLElement && !summary.contains(from) && !toggles.includes(summary)) {
        toggles.push(summary);
      }
    }
    from = node;
    node = node.parentElement;
  }
  return toggles;
}

/**
 * Records `item` (and its menus' toggles, then `fallback`) as the invoker of the next dialog that opens within
 * INVOKER_TTL_MS. Hosts that forward a click to a hidden trigger may call it (window.nowoUiRecordOpener) before the
 * forwarded click; the kit records menu items by itself.
 */
export function recordInvoker(item: Element | null, fallback: Element | null = null): void {
  if (!(item instanceof HTMLElement)) {
    return;
  }
  const chain: HTMLElement[] = [item, ...menuToggles(item)];
  if (fallback instanceof HTMLElement && !chain.includes(fallback)) {
    chain.push(fallback);
  }
  state().pending = { chain, at: Date.now() };
}

/** The recorded invoker chain when still fresh (consumed), else an empty list. */
export function takeInvoker(): HTMLElement[] {
  const current = state();
  const pending = current.pending;
  current.pending = null;
  if (!pending || Date.now() - pending.at > INVOKER_TTL_MS) {
    return [];
  }
  return pending.chain;
}

/** Whether a popover element is shown; true when the engine cannot tell (the computed display decides then). */
function popoverShown(el: Element): boolean {
  try {
    return el.matches(':popover-open');
  } catch {
    return true;
  }
}

/** Whether an element can take focus back: connected, enabled, rendered, not hidden / inert / in a closed menu. */
export function isFocusable(el: Element | null | undefined): el is HTMLElement {
  if (!(el instanceof HTMLElement) || !el.isConnected || el === document.body || el === document.documentElement) {
    return false;
  }
  if ((el as HTMLElement & { disabled?: unknown }).disabled === true || el.closest('[hidden], [inert], fieldset[disabled]') !== null) {
    return false;
  }
  const check = (el as HTMLElement & { checkVisibility?: (options?: Record<string, boolean>) => boolean }).checkVisibility;
  if (typeof check === 'function' && !check.call(el, { visibilityProperty: true })) {
    return false;
  }
  for (let node: Element | null = el; node; node = node.parentElement) {
    if (node instanceof HTMLDetailsElement && !node.open) {
      const summary: Element | null = node.querySelector(':scope > summary');
      if (!(summary instanceof HTMLElement && summary.contains(el))) {
        return false;
      }
    }
    if (node !== el && node.hasAttribute('popover') && !popoverShown(node)) {
      return false;
    }
    const style = window.getComputedStyle(node);
    if (style.display === 'none' || (node === el && style.visibility === 'hidden')) {
      return false;
    }
  }
  return true;
}

/**
 * Remembers where focus returns when `dialog` closes: `opener` first, then the recorded invoker chain. Without an
 * opener nor a recorded invoker, the focused element. A call without an opener keeps a list an earlier call (a peer
 * opening through the IIFE) already set.
 */
export function rememberOpener(dialog: HTMLDialogElement, opener: Element | null = null): void {
  const current = state();
  const invoker = takeInvoker();
  if (!(opener instanceof HTMLElement) && invoker.length === 0 && current.targets.has(dialog)) {
    return;
  }
  const candidates: HTMLElement[] = [];
  const add = (el: Element | null): void => {
    if (el instanceof HTMLElement && el !== document.body && !dialog.contains(el) && !candidates.includes(el)) {
      candidates.push(el);
    }
  };
  add(opener);
  invoker.forEach(add);
  if (candidates.length === 0) {
    add(document.activeElement);
  }
  if (candidates.length > 0) {
    current.targets.set(dialog, candidates);
  } else {
    current.targets.delete(dialog);
  }
}

/** Moves the focus-return list of `from` to `to` (a dialog replacing another). */
export function handOverOpener(from: HTMLDialogElement, to: HTMLDialogElement): void {
  const current = state();
  const list = current.targets.get(from);
  current.targets.delete(from);
  if (list) {
    current.targets.set(to, list);
  }
}

/** Forgets the focus-return list of a dialog. */
export function forgetOpener(dialog: HTMLDialogElement): void {
  state().targets.delete(dialog);
}

/** The focus-return list of a dialog (tests, hosts). */
export function openerCandidates(dialog: HTMLDialogElement): HTMLElement[] {
  return [...(state().targets.get(dialog) ?? [])];
}

/**
 * After `dialog` closed: focuses the first remembered candidate that is connected and focusable (the list is used
 * once). Returns the focused element, or null when nothing was remembered, another open modal dialog holds the
 * focus, or no candidate could take it.
 */
export function returnFocus(dialog: HTMLDialogElement): HTMLElement | null {
  const current = state();
  const candidates = current.targets.get(dialog);
  if (!candidates || dialog.open) {
    return null;
  }
  current.targets.delete(dialog);
  const active = document.activeElement;
  const holder = active instanceof Element ? active.closest('dialog[open]') : null;
  if (holder && holder !== dialog) {
    return null;
  }
  for (const candidate of candidates) {
    if (!isFocusable(candidate) || dialog.contains(candidate)) {
      continue;
    }
    candidate.focus();
    if (document.activeElement === candidate) {
      return candidate;
    }
  }
  return null;
}

/** Clears the shared state and unbinds the click recorder (tests). */
export function resetFocusReturn(): void {
  const current = state();
  current.pending = null;
  current.targets = new WeakMap();
  if (current.tracking) {
    document.removeEventListener('click', onClickCapture, true);
    current.tracking = false;
  }
}

function onClickCapture(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) {
    return;
  }
  const item = target.closest(MENU_ITEM);
  if (item instanceof HTMLElement && !item.closest('dialog')) {
    recordInvoker(item);
  }
}

/**
 * Records menu item clicks (capture phase, before a host's own handler forwards the click to a dialog trigger) and
 * publishes window.nowoUiRecordOpener. Bound once per page, whichever kit script runs first.
 */
export function bindFocusReturn(): void {
  if (typeof window === 'undefined' || typeof document === 'undefined') {
    return;
  }
  window.nowoUiRecordOpener ??= recordInvoker;
  const current = state();
  if (current.tracking) {
    return;
  }
  current.tracking = true;
  document.addEventListener('click', onClickCapture, true);
}
