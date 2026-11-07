/**
 * Closing with an effect. Chromium can keep an element painted after its close through CSS (`transition-behavior: allow-discrete`), but
 * WebKit cannot for the top layer and Firefox cannot at all. So the kit's own closes wait here: `data-nowo-ui-closing` starts the fade
 * (`--nowo-ui-motion-*`), and the real `close()`, `hidePopover()` or `hidden` runs when it ends, so the fade shows in every engine. With
 * no transition (`0ms`, or a page without the kit's CSS) it all happens at once. If `transitionend` never fires, a timer does it.
 * A modal dialog takes no keys while it fades, and no fade reaches a Turbo snapshot. Importing touches no DOM.
 */
const SLACK_MS = 50;

const seconds = (value: string): number => {
  const number = Number.parseFloat(value);
  if (Number.isNaN(number)) {
    return 0;
  }
  return value.trim().endsWith('ms') ? number : number * 1000;
};

/** The longest transition of an element in its current state, in ms. */
export function effectMs(element: Element): number {
  const style = window.getComputedStyle(element);
  const durations = (style.transitionDuration ?? '').split(',');
  const delays = (style.transitionDelay ?? '').split(',');
  return durations.reduce((longest, duration, index) => Math.max(longest, seconds(duration) + seconds(delays[index % delays.length] ?? '0')), 0);
}

/** Runs `done` once the element's closing effect ended, once only. Returns a function that runs it now if it has not run. */
export function afterEffect(element: Element, done: () => void): () => void {
  let ran = false;
  let timer: number | undefined;
  const finish = (): void => {
    if (ran) {
      return;
    }
    ran = true;
    window.clearTimeout(timer);
    EventTarget.prototype.removeEventListener.call(element, 'transitionend', onEnd as EventListener);
    done();
  };
  const onEnd = (event: Event): void => {
    if (event.target === element) {
      finish();
    }
  };
  const ms = effectMs(element);
  if (ms <= 0) {
    finish();
    return finish;
  }
  EventTarget.prototype.addEventListener.call(element, 'transitionend', onEnd as EventListener);
  timer = window.setTimeout(finish, ms + SLACK_MS);
  return finish;
}

export const CLOSING_ATTR = 'data-nowo-ui-closing';

const RUNNING_KEY = Symbol.for('nowo-ui.motion.running');
const TURBO_KEY = Symbol.for('nowo-ui.motion.turbo');

/** Running fades, one list per page: each script of the kit bundles its own copy of this module. */
function running(): Map<Element, () => void> {
  const page = window as unknown as Record<symbol, Map<Element, () => void> | undefined>;
  page[RUNNING_KEY] ??= new Map();
  return page[RUNNING_KEY];
}

/** Whether an element is fading out: closed as far as a user is concerned, though not yet in the DOM's state. */
export function isClosing(element: Element): boolean {
  return running().has(element);
}

/** Ends every fade at once. */
function finishAllClosing(): void {
  [...running().values()].forEach((finish) => finish());
}

/** A snapshot taken during a fade would bring back, on Back, an open dialog that no longer has a fade to end it. */
function bindTurbo(): void {
  const page = window as unknown as Record<symbol, unknown>;
  if (!page[TURBO_KEY]) {
    page[TURBO_KEY] = true;
    EventTarget.prototype.addEventListener.call(document, 'turbo:before-cache', finishAllClosing);
  }
}

function isModal(element: Element): boolean {
  try {
    return element instanceof HTMLDialogElement && Element.prototype.matches.call(element, ':modal');
  } catch {
    return false;
  }
}

/**
 * A modal dialog takes no keys during its fade: Enter must not submit what Escape just cancelled. `close()` still returns focus, as it
 * does for any modal dialog wherever focus is. A dialog opened with `show()` keeps its focus, because only its focus brings it back.
 */
function takeNoKeys(dialog: Element): void {
  Element.prototype.setAttribute.call(dialog, 'inert', '');
  const active = document.activeElement;
  if (active instanceof HTMLElement && Node.prototype.contains.call(dialog, active)) {
    active.blur();
  }
}

/** Starts the fade, then runs `close`. A second call during the fade does nothing. */
export function closeWithEffect(element: Element, close: () => void): void {
  const fades = running();
  if (fades.has(element)) {
    return;
  }
  bindTurbo();
  const modal = isModal(element);
  Element.prototype.setAttribute.call(element, CLOSING_ATTR, '');
  let done = false;
  const now = afterEffect(element, () => {
    done = true;
    fades.delete(element);
    Element.prototype.removeAttribute.call(element, CLOSING_ATTR);
    if (modal) {
      Element.prototype.removeAttribute.call(element, 'inert');
    }
    close();
  });
  if (!done) {
    fades.set(element, now);
    if (modal) {
      takeNoKeys(element);
    }
  }
}

/** Ends a fade at once, for an element about to open again. */
export function finishClosing(element: Element): void {
  running().get(element)?.();
}

const DIALOGS = ':is(.nowo-ui-dialog:not(.nowo-ui-dialog--end, .nowo-ui-dialog--start), .nowo-ui-confirm-dialog, .nowo-ui-confirm)';

/** `dialog.close()` after its fade. Side panel dialogs have their own transition, and close as before. */
export function closeDialogWithEffect(dialog: HTMLDialogElement): void {
  if (Element.prototype.matches.call(dialog, DIALOGS)) {
    closeWithEffect(dialog, () => dialog.close());
  } else {
    dialog.close();
  }
}

/**
 * Escape on a kit dialog: `cancel` closes it natively and at once, so it is turned into the same fade, unless a listener on the dialog
 * refused it. `cancel` does not bubble: seen in the capture phase, decided last on the target, after that dialog's own listeners.
 */
function onCancel(event: Event): void {
  const dialog = event.target;
  if (!(dialog instanceof HTMLDialogElement) || !Element.prototype.matches.call(dialog, DIALOGS)) {
    return;
  }
  EventTarget.prototype.addEventListener.call(
    dialog,
    'cancel',
    (cancel: Event) => {
      if (!cancel.defaultPrevented && effectMs(dialog) > 0) {
        cancel.preventDefault();
        closeDialogWithEffect(dialog);
      }
    },
    { once: true },
  );
}

const BOUND_KEY = Symbol.for('nowo-ui.motion.bound');

/** Binds the Escape path once per page, however many scripts of the kit load this module. */
export function bindMotion(): void {
  const page = window as unknown as Record<symbol, unknown>;
  if (!page[BOUND_KEY]) {
    page[BOUND_KEY] = true;
    EventTarget.prototype.addEventListener.call(document, 'cancel', onCancel, true);
  }
}
