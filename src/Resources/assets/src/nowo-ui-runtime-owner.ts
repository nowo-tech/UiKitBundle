/**
 * Dialog runtime ownership (2.70): which runtime drives a kit dialog when both the IIFE runtimes (js/nowo-ui-dialog.js,
 * js/nowo-ui-confirm.js) and the Stimulus peers (`remote-form-dialog`, `confirm-shared`) are on the page.
 *
 * A Stimulus peer claims its <dialog> when it connects (`data-nowo-ui-runtime-owner="stimulus"`, set by script, never
 * printed by Twig) and releases it when it disconnects. The IIFE runtimes leave a claimed dialog alone: they neither
 * open it from its openers, close it from its close buttons, nor react to its `close` event. Before a peer connects
 * (or without the peers) the IIFE runtime drives it as before, so either runtime alone works and both together never
 * handle one click twice.
 *
 * `markBound(name)` makes a runtime's document listeners idempotent across copies of the same script (two bundles each
 * loading js/nowo-ui-dialog.js): the flag lives on `window` under a `Symbol.for()` key shared by every copy.
 */

export const OWNER_ATTR = 'data-nowo-ui-runtime-owner';

/** Marks `element` as driven by a Stimulus peer. */
export function claim(element: Element): void {
  element.setAttribute(OWNER_ATTR, 'stimulus');
}

/** Hands `element` back to the IIFE runtime (peer disconnected). */
export function release(element: Element | null): void {
  element?.removeAttribute(OWNER_ATTR);
}

/** Whether a Stimulus peer drives `element` (null: not owned). */
export function ownedByStimulus(element: Element | null | undefined): boolean {
  return element instanceof Element && element.getAttribute(OWNER_ATTR) === 'stimulus';
}

/**
 * True the first time `name` is bound in this window (any copy of the script), false afterwards. The runtimes call it
 * before adding their document listeners.
 */
export function markBound(name: string): boolean {
  const key = Symbol.for(`nowo-ui.bound.${name}`);
  const registry = window as unknown as Record<symbol, unknown>;
  if (registry[key] === true) {
    return false;
  }
  registry[key] = true;
  return true;
}
