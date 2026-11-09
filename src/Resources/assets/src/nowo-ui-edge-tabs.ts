/**
 * Page-edge tabs launcher (`js/nowo-ui-edge-tabs.js`, 2.41, MUX-01; opt-in: add `edge-tabs` to `script_names` and render the
 * partial `edge_tabs_launcher` once per page). On phones (or always, per the partial's `when`) the CSS folds every
 * `partials/_edge_tab` of the page behind one launcher tab; this script only opens and closes the fold. Without it the
 * launcher stays hidden and the tabs keep their 2.40 places (the CSS needs `data-nowo-ui-edge-tabs-ready` on <html>).
 *
 * Open: `data-nowo-ui-edge-tabs-open` on <html> + `aria-expanded="true"` on the launcher; the tabs show under it. Opening
 * from the keyboard moves the focus to the first tab; ArrowDown / ArrowUp walk the launcher and the tabs. It closes on
 * Escape (focus back on the launcher), on a click elsewhere, once a tab is used, and when the focus leaves the launcher
 * and the tabs. Tab / Shift+Tab are never captured (no focus trap). Event: bubbling `nowo-ui:edge-tabs:toggle` {open}.
 *
 * Focus return (2.41.1, JC-L1): a folded tab is `display: none`, so when a tab used from the fold opens a dialog (the
 * listing customizer, a tour) the dialog's focus return to it would land on <body>. After any dialog closes, if the
 * focus is lost (on <body>, or on that tab while it is hidden) it goes to the launcher instead.
 *
 * Expanded / pinned tabs (2.42, MUX-01): a tab with `aria-expanded="true"` (its drawer or panel is open) or
 * `data-nowo-ui-edge-tab-pinned` never folds (CSS), so a click outside the launcher folds the others and leaves it
 * where it is. When such a tab folds while it has the focus (its panel closed: aria-expanded back to false), the focus
 * goes to the launcher instead of staying on a hidden element.
 *
 * Delegated listeners on the document; importing touches no DOM. Members are read through the prototypes.
 */
export const LAUNCHER = '[data-nowo-ui-edge-tabs-launcher]';
export const TAB = '.nowo-ui-edge-tab__link';
export const OPEN = 'data-nowo-ui-edge-tabs-open';
export const READY = 'data-nowo-ui-edge-tabs-ready';
export const PINNED = 'data-nowo-ui-edge-tab-pinned';

const closest = (el: Element, selector: string): HTMLElement | null => Element.prototype.closest.call(el, selector) as HTMLElement | null;
const queryAll = (root: ParentNode, selector: string): HTMLElement[] =>
  [...(Document.prototype.querySelectorAll.call(root, selector) as NodeListOf<HTMLElement>)];
const listen = (target: EventTarget, type: string, handler: (event: never) => void): void => {
  EventTarget.prototype.addEventListener.call(target, type, handler as EventListener);
};
const root = (): HTMLElement => document.documentElement;
const elementOf = (target: EventTarget | null): Element | null => (target instanceof Element ? target : null);

/** Whether an element takes part in layout (a folded tab or a hidden launcher does not). */
export function isShown(el: Element): boolean {
  return el.isConnected && Element.prototype.getClientRects.call(el).length > 0;
}

/** The tab last used from the open fold (JC-L1): the opener a dialog may give the focus back to. */
let usedTab: HTMLElement | null = null;

export function isOpen(): boolean {
  return root().hasAttribute(OPEN);
}

/** The launcher of the page (the first one; a page renders one). */
export function launcher(): HTMLElement | null {
  return queryAll(document, LAUNCHER)[0] ?? null;
}

/** The tabs the launcher shows: every edge tab link of the page, in document order. */
export function tabs(): HTMLElement[] {
  return queryAll(document, TAB);
}

let ids = 0;

/** `aria-controls` of the launcher (2.41.1, JC-I15): the ids of the tabs it shows (a tab without one gets one). */
export function linkTabs(button: HTMLElement): void {
  const list = tabs().map((tab) => {
    if ('' === tab.id) {
      do {
        ids += 1;
        tab.id = `nowo-ui-edge-tab-${ids}`;
      } while (document.querySelectorAll(`#${tab.id}`).length > 1);
    }
    return tab.id;
  });
  if (list.length > 0) {
    button.setAttribute('aria-controls', list.join(' '));
  }
}

/** Opens or closes the fold; `focusFirst` moves the focus to the first tab (keyboard opening). */
export function setOpen(open: boolean, focusFirst = false): void {
  const button = launcher();
  const was = isOpen();
  root().toggleAttribute(OPEN, open);
  button?.setAttribute('aria-expanded', open ? 'true' : 'false');
  if (open && button) {
    usedTab = null;
    linkTabs(button);
  }
  if (open && focusFirst) {
    tabs()[0]?.focus();
  }
  if (was !== open) {
    (button ?? root()).dispatchEvent(new CustomEvent('nowo-ui:edge-tabs:toggle', { bubbles: true, detail: { open } }));
  }
}

function onClick(event: MouseEvent): void {
  const target = elementOf(event.target);
  const button = target ? closest(target, LAUNCHER) : null;
  if (button) {
    // detail 0: activated from the keyboard (Enter / Space) — the focus goes to the first tab.
    setOpen(!isOpen(), !isOpen() && 0 === event.detail);

    return;
  }
  if (isOpen()) {
    // A click on a tab reaches the tab first (its own link / button acts), then folds; anywhere else folds too.
    usedTab = target ? closest(target, TAB) : null;
    setOpen(false);
  }
}

/** After the dialog runtimes gave the focus back (same task), move a lost focus to the launcher. */
export function recoverFocus(): void {
  const tab = usedTab;
  usedTab = null;
  const button = launcher();
  if (!tab || !button || !isShown(button)) {
    return;
  }
  const active = document.activeElement;
  const lost = null === active || active === document.body || active === document.documentElement || (active === tab && !isShown(tab));
  if (lost && !isShown(tab)) {
    button.focus();
  }
}

/** Whether a tab stays out of the fold (2.42): its panel is open (`aria-expanded="true"`) or it is pinned. */
export function isPinned(tab: Element): boolean {
  return 'true' === tab.getAttribute('aria-expanded') || tab.hasAttribute(PINNED);
}

/**
 * 2.42: after the state of tabs changed (aria-expanded / pinned), a focused tab that the fold hid now gives the focus to
 * the launcher (never left on an element that is not rendered).
 */
export function onTabsChanged(changed: Element[]): void {
  const active = document.activeElement;
  if (isOpen() || !(active instanceof HTMLElement) || !changed.includes(active) || isPinned(active) || isShown(active)) {
    return;
  }
  const button = launcher();
  if (button && isShown(button)) {
    button.focus();
  }
}

function onMutations(records: MutationRecord[]): void {
  const changed = records.map((record) => record.target).filter((node): node is Element => node instanceof Element && null !== closest(node, TAB));
  if (changed.length > 0) {
    onTabsChanged(changed);
  }
}

function onDialogClose(event: Event): void {
  if (usedTab && event.target instanceof HTMLDialogElement) {
    setTimeout(recoverFocus, 0);
  }
}

function onKeyDown(event: KeyboardEvent): void {
  if (!isOpen()) {
    return;
  }
  const button = launcher();
  if ('Escape' === event.key) {
    setOpen(false);
    button?.focus();

    return;
  }
  if ('ArrowDown' !== event.key && 'ArrowUp' !== event.key) {
    return;
  }
  const active = elementOf(document.activeElement);
  const ring = [...(button ? [button] : []), ...tabs()];
  const index = active ? ring.indexOf(active as HTMLElement) : -1;
  if (-1 === index) {
    return;
  }
  event.preventDefault();
  const next = ring[(index + ('ArrowDown' === event.key ? 1 : ring.length - 1)) % ring.length];
  next?.focus();
}

function onFocusOut(event: FocusEvent): void {
  if (!isOpen()) {
    return;
  }
  const from = elementOf(event.target);
  if (!from || !(closest(from, LAUNCHER) || closest(from, TAB))) {
    return;
  }
  const to = elementOf(event.relatedTarget);
  // Focus leaving for the page (not another tab, not the launcher) folds; a null relatedTarget (window blur) does not.
  if (to && !closest(to, LAUNCHER) && !closest(to, TAB)) {
    // A tab that opened a dialog loses the focus to it before its click reaches the document: remember it (JC-L1).
    usedTab = closest(from, TAB);
    setOpen(false);
  }
}

let bound = false;

export function bindNowoUiEdgeTabs(): void {
  if (bound) {
    return;
  }
  bound = true;
  listen(document, 'click', onClick);
  listen(document, 'keydown', onKeyDown);
  listen(document, 'focusout', onFocusOut);
  // `close` does not bubble: capture phase.
  EventTarget.prototype.addEventListener.call(document, 'close', onDialogClose as EventListener, true);
  if (typeof MutationObserver !== 'undefined') {
    new MutationObserver(onMutations).observe(root(), { subtree: true, attributes: true, attributeFilter: ['aria-expanded', PINNED] });
  }
  root().setAttribute(READY, '');
}

const BOUND_KEY = Symbol.for('nowo-ui.edge-tabs.bound');
if (typeof document !== 'undefined' && !(window as unknown as Record<symbol, unknown>)[BOUND_KEY]) {
  (window as unknown as Record<symbol, unknown>)[BOUND_KEY] = true;
  bindNowoUiEdgeTabs();
}
