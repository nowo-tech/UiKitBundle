/**
 * One-time secret runtime (2.69, partial `secret_once`; opt-in runtime `secret-once`).
 *
 * The secret is printed ONCE, as the text of `[data-nowo-ui-secret-once-value]`. This script never copies it anywhere
 * else in the document: no attribute, no hidden field, no second text node, no temporary <textarea>. It reads the
 * node's text only when the copy button is pressed and hands it to the Clipboard API.
 *
 * - Hide / reveal: the root's `data-nowo-ui-secret-once-state` (`masked` | `revealed` | `cleared`) drives CSS. Masked,
 *   the value node is `display: none` (out of the rendering, the accessibility tree, selection and find-in-page) and
 *   a static bullet mask is shown instead. The toggle is a toggle button (`aria-pressed`, constant label).
 * - Copy: `navigator.clipboard.writeText()` from the node's text. Without the Async Clipboard API (insecure context)
 *   or when it is refused, a `copy` event listener feeds `execCommand('copy')` the text straight from the node (still
 *   no DOM copy). The result is shown and announced in the `role="status"` region (VoiceOver repeat guard).
 * - Clear: `data-nowo-ui-secret-once-clear-on-hide` wipes the node's text in place on hide;
 *   `data-nowo-ui-secret-once-clear-after="<ms>"` wipes it after that delay. Then the state is `cleared` and both
 *   buttons are `aria-disabled` (they keep the focus; the clicks are ignored).
 * - Leaving (audit ZAB S3-L1): by default the text is also wiped when the enclosing <dialog> closes (`close`) and on
 *   `pagehide`, so a closed dialog or a page restored from the back/forward cache never shows it again. Opt out per
 *   root with `data-nowo-ui-secret-once-keep-on-leave`.
 * - `data-nowo-ui-secret-once-revealed`: start revealed. Without it the secret starts masked.
 * - Without this script the secret stays visible as plain text (`user-select: all`), the buttons stay `hidden`.
 */

export type SecretOnceState = 'masked' | 'revealed' | 'cleared';

const ROOT = '[data-nowo-ui-secret-once]';
const PREFIX = 'data-nowo-ui-secret-once';
const STATE = `${PREFIX}-state`;
const READY = `${PREFIX}-ready`;
const FEEDBACK_MS = 2000;
const NBSP = ' ';

const feedbackTimers = new WeakMap<HTMLElement, number>();
const clearTimers = new WeakMap<HTMLElement, number>();
let lastMessage = '';

function part(root: HTMLElement, name: string): HTMLElement | null {
  return root.querySelector<HTMLElement>(`[${PREFIX}-${name}]`);
}

function label(root: HTMLElement, name: string, fallback: string): string {
  return root.getAttribute(`${PREFIX}-${name}-label`) ?? fallback;
}

export function stateOf(root: HTMLElement): SecretOnceState | null {
  const state = root.getAttribute(STATE);

  return 'masked' === state || 'revealed' === state || 'cleared' === state ? state : null;
}

function setState(root: HTMLElement, state: SecretOnceState): void {
  root.setAttribute(STATE, state);
  part(root, 'toggle')?.setAttribute('aria-pressed', 'revealed' === state ? 'true' : 'false');
}

/** Writes a short message in the status region (visible and announced), then empties it. */
export function report(root: HTMLElement, message: string, button: HTMLElement | null = null, ok = true): void {
  const status = part(root, 'status');
  const previous = feedbackTimers.get(root);
  if (undefined !== previous) {
    window.clearTimeout(previous);
  }
  root.querySelectorAll<HTMLElement>('[data-copied], [data-failed]').forEach((el) => {
    el.removeAttribute('data-copied');
    el.removeAttribute('data-failed');
  });
  button?.setAttribute(ok ? 'data-copied' : 'data-failed', '');
  if (!status) {
    return;
  }
  status.textContent = '';
  // VoiceOver skips a message identical to the last one from the same region.
  const text = message === lastMessage ? message + NBSP : message;
  lastMessage = text;
  window.requestAnimationFrame(() => {
    status.textContent = text;
  });
  feedbackTimers.set(
    root,
    window.setTimeout(() => {
      status.textContent = '';
      button?.removeAttribute('data-copied');
      button?.removeAttribute('data-failed');
      feedbackTimers.delete(root);
    }, FEEDBACK_MS),
  );
}

/** Wipes the secret's text in place (no copy kept), disables the controls and shows the cleared state. */
export function clear(root: HTMLElement, announce = true): void {
  const value = part(root, 'value');
  value?.replaceChildren();
  const timer = clearTimers.get(root);
  if (undefined !== timer) {
    window.clearTimeout(timer);
    clearTimers.delete(root);
  }
  setState(root, 'cleared');
  for (const name of ['toggle', 'copy']) {
    part(root, name)?.setAttribute('aria-disabled', 'true');
  }
  if (announce) {
    report(root, label(root, 'cleared', 'Cleared'));
  }
}

export function reveal(root: HTMLElement): void {
  if ('cleared' !== stateOf(root)) {
    setState(root, 'revealed');
  }
}

export function hide(root: HTMLElement): void {
  if ('cleared' === stateOf(root)) {
    return;
  }
  if (root.hasAttribute(`${PREFIX}-clear-on-hide`)) {
    clear(root);
    return;
  }
  setState(root, 'masked');
}

export function toggle(root: HTMLElement): void {
  if ('revealed' === stateOf(root)) {
    hide(root);
    return;
  }
  reveal(root);
}

/** `execCommand('copy')` fed by a `copy` listener: the text goes from the node to the clipboard, never into the DOM. */
export function legacyCopy(text: string): boolean {
  let written = false;
  const onCopy = (event: ClipboardEvent): void => {
    if (event.clipboardData) {
      event.clipboardData.setData('text/plain', text);
      event.preventDefault();
      written = true;
    }
  };
  document.addEventListener('copy', onCopy, true);
  try {
    return document.execCommand('copy') && written;
  } catch {
    return false;
  } finally {
    document.removeEventListener('copy', onCopy, true);
  }
}

export async function writeClipboard(text: string): Promise<boolean> {
  if (!navigator.clipboard?.writeText) {
    return legacyCopy(text);
  }
  try {
    await navigator.clipboard.writeText(text);

    return true;
  } catch {
    return legacyCopy(text);
  }
}

export async function copy(root: HTMLElement, button: HTMLElement | null = part(root, 'copy')): Promise<boolean> {
  if ('cleared' === stateOf(root)) {
    return false;
  }
  const text = (part(root, 'value')?.textContent ?? '').trim();
  if ('' === text) {
    return false;
  }
  const ok = await writeClipboard(text);
  report(root, ok ? label(root, 'copied', 'Copied') : label(root, 'copy-failed', 'Copy failed'), button, ok);

  return ok;
}

/** Upgrades one root (idempotent): shows the controls, masks the secret (unless revealed), arms `clear-after`. */
export function upgrade(root: HTMLElement): void {
  if (root.hasAttribute(READY)) {
    return;
  }
  root.setAttribute(READY, '');
  const controls = part(root, 'controls');
  if (controls) {
    controls.hidden = false;
  }
  if ('' === (part(root, 'value')?.textContent ?? '').trim()) {
    clear(root, false);
    return;
  }
  setState(root, root.hasAttribute(`${PREFIX}-revealed`) ? 'revealed' : 'masked');
  const after = Number.parseInt(root.getAttribute(`${PREFIX}-clear-after`) ?? '', 10);
  if (after > 0) {
    clearTimers.set(
      root,
      window.setTimeout(() => clear(root), after),
    );
  }
}

export function upgradeAll(scope: ParentNode = document): void {
  scope.querySelectorAll<HTMLElement>(ROOT).forEach(upgrade);
}

function wipesOnLeave(root: HTMLElement): boolean {
  return !root.hasAttribute(`${PREFIX}-keep-on-leave`) && 'cleared' !== stateOf(root);
}

/** Wipes the roots inside `scope` that do not opt out (also roots the runtime has not upgraded yet). */
export function wipeOnLeave(scope: ParentNode = document): void {
  scope.querySelectorAll<HTMLElement>(ROOT).forEach((root) => {
    if (wipesOnLeave(root)) {
      clear(root, false);
    }
  });
}

/** `close` does not bubble: listened in the capture phase on document. */
function onDialogClose(event: Event): void {
  if (event.target instanceof HTMLDialogElement) {
    wipeOnLeave(event.target);
  }
}

function onClick(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) {
    return;
  }
  const button = target.closest<HTMLElement>(`[${PREFIX}-toggle], [${PREFIX}-copy]`);
  const root = button?.closest<HTMLElement>(ROOT);
  if (!button || !root) {
    return;
  }
  upgrade(root);
  if ('true' === button.getAttribute('aria-disabled')) {
    event.preventDefault();
    return;
  }
  if (button.hasAttribute(`${PREFIX}-toggle`)) {
    toggle(root);
    return;
  }
  void copy(root, button);
}

let bound = false;

export function bindNowoUiSecretOnce(): void {
  upgradeAll();
  if (bound) {
    return;
  }
  bound = true;
  document.addEventListener('click', onClick);
  document.addEventListener('close', onDialogClose, true);
  window.addEventListener('pagehide', () => wipeOnLeave());
  // Turbo / fragment swaps: upgrade the roots a new body brings.
  document.addEventListener('turbo:load', () => upgradeAll());
}

/** Test helper: forget the VoiceOver repeat guard. */
export function resetLastMessage(): void {
  lastMessage = '';
}

bindNowoUiSecretOnce();
