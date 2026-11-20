/**
 * Tooltip runtime (2.61, partial `tooltip`): [data-nowo-ui-tooltip] roots hold the trigger and a role="tooltip"
 * bubble the trigger names with aria-describedby. Without this script CSS shows the bubble on :hover / :focus-within.
 * With it (data-nowo-ui-tooltip-ready on each root):
 * - the bubble opens on pointer enter and on focus, and stays while the pointer is over the trigger or the bubble
 *   (WCAG 1.4.13 hoverable / persistent); it closes when both pointer and focus have left;
 * - Escape closes every open bubble (dismissible) without moving the focus;
 * - a tap on a touch screen toggles it;
 * - the bubble is `position: fixed` and placed above the trigger (below when there is no room), kept inside the
 *   viewport, so scroll containers never clip it; coordinates go through the CSSOM (strict CSP).
 */

const ROOT = '[data-nowo-ui-tooltip]';
const BUBBLE = '.nowo-ui-tooltip__bubble';
const GAP = 6;
const EDGE = 8;

function bubbleOf(root: Element): HTMLElement | null {
  const bubble = Array.from(root.children).find((child) => child.matches(BUBBLE));
  return bubble instanceof HTMLElement ? bubble : null;
}

function triggerOf(root: Element): Element | null {
  return Array.from(root.children).find((child) => !child.matches(BUBBLE)) ?? null;
}

/** Above the trigger, centred; below it when the top has no room; inside the viewport horizontally. */
export function place(root: Element): void {
  const bubble = bubbleOf(root);
  const trigger = triggerOf(root) ?? root;
  if (!bubble) {
    return;
  }
  const rect = trigger.getBoundingClientRect();
  const width = bubble.offsetWidth;
  const height = bubble.offsetHeight;
  const above = rect.top - GAP - height;
  const top = above >= EDGE ? above : rect.bottom + GAP;
  const centred = rect.left + rect.width / 2 - width / 2;
  const left = Math.max(EDGE, Math.min(centred, window.innerWidth - EDGE - width));
  bubble.style.top = `${Math.round(top)}px`;
  bubble.style.left = `${Math.round(left)}px`;
  bubble.setAttribute('data-placement', above >= EDGE ? 'top' : 'bottom');
}

export function openTooltip(root: Element): void {
  document.querySelectorAll(`${ROOT}.is-open`).forEach((other) => {
    if (other !== root) {
      other.classList.remove('is-open');
    }
  });
  root.classList.add('is-open');
  place(root);
}

export function closeTooltip(root: Element): void {
  root.classList.remove('is-open');
}

function rootFrom(target: EventTarget | null): Element | null {
  return target instanceof Element ? target.closest(ROOT) : null;
}

function onOver(event: Event): void {
  const root = rootFrom(event.target);
  if (root && !root.classList.contains('is-open')) {
    openTooltip(root);
  }
}

function onOut(event: Event): void {
  const root = rootFrom(event.target);
  const next = (event as MouseEvent | FocusEvent).relatedTarget;
  if (!root || (next instanceof Node && root.contains(next))) {
    return;
  }
  if (event.type === 'mouseout' && root.contains(document.activeElement)) {
    return;
  }
  closeTooltip(root);
}

function onKeydown(event: KeyboardEvent): void {
  const open = document.querySelectorAll(`${ROOT}.is-open`);
  if (event.key === 'Escape' && open.length > 0) {
    // 2.61 (audit XJ-8): Escape hides the tooltip only; a surrounding dialog stays open (preventDefault: a native
    // modal <dialog> would otherwise get its cancel from the same key press).
    event.preventDefault();
    event.stopPropagation();
    open.forEach(closeTooltip);
  }
}

function onPointerDown(event: PointerEvent): void {
  if (event.pointerType !== 'touch') {
    return;
  }
  const root = rootFrom(event.target);
  document.querySelectorAll(`${ROOT}.is-open`).forEach((other) => {
    if (other !== root) {
      closeTooltip(other);
    }
  });
  if (root) {
    if (root.classList.contains('is-open')) {
      closeTooltip(root);
    } else {
      openTooltip(root);
    }
  }
}

function onViewportChange(): void {
  document.querySelectorAll(`${ROOT}.is-open`).forEach(place);
}

/** Marks the roots enhanced (idempotent; new roots are marked on their first event too). */
export function upgradeTooltips(scope: ParentNode = document): void {
  scope.querySelectorAll(ROOT).forEach((root) => {
    if (!root.hasAttribute('data-nowo-ui-tooltip-ready')) {
      root.setAttribute('data-nowo-ui-tooltip-ready', '');
    }
  });
}

let bound = false;

export function bindNowoUiTooltip(): void {
  upgradeTooltips();
  if (bound) {
    return;
  }
  bound = true;
  document.addEventListener('mouseover', (event) => {
    upgradeTooltips();
    onOver(event);
  });
  document.addEventListener('focusin', (event) => {
    upgradeTooltips();
    onOver(event);
  });
  document.addEventListener('mouseout', onOut);
  document.addEventListener('focusout', onOut);
  document.addEventListener('keydown', onKeydown, true);
  document.addEventListener('pointerdown', onPointerDown);
  window.addEventListener('scroll', onViewportChange, { capture: true, passive: true });
  window.addEventListener('resize', onViewportChange, { passive: true });
}

bindNowoUiTooltip();
