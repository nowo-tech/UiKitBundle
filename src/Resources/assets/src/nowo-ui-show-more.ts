/**
 * Show more (`js/nowo-ui-show-more.js`, opt-in: add `show-more` to `script_names`, partial `show_more`): text folded after a number of
 * lines, with a real button. Without this script the whole text shows and the button stays hidden. The fold is CSS only (`line-clamp`
 * under `[data-nowo-ui-show-more-ready]`), so there is no style attribute. The button appears only when the text really overflows, and
 * goes when the width changes so that it fits. Focus entering a clipped part (a link in the text) opens the fold, so nothing focusable
 * is out of sight. Event: bubbling `nowo-ui:show-more:toggle` {expanded}.
 *
 * Delegated listeners; one `MutationObserver` for content inserted later and one `ResizeObserver`. Importing touches no DOM.
 */
export const ROOT = '[data-nowo-ui-show-more]';
const CONTENT = '[data-nowo-ui-show-more-content]';
const TOGGLE = '[data-nowo-ui-show-more-toggle]';
const READY = 'data-nowo-ui-show-more-ready';

const closest = (el: Element, selector: string): HTMLElement | null => Element.prototype.closest.call(el, selector) as HTMLElement | null;
const query = (el: Element, selector: string): HTMLElement | null => Element.prototype.querySelector.call(el, selector) as HTMLElement | null;
const listen = (target: EventTarget, type: string, handler: (event: never) => void): void => {
  EventTarget.prototype.addEventListener.call(target, type, handler as EventListener);
};

let resizes: ResizeObserver | null = null;

function isExpanded(root: Element): boolean {
  return query(root, TOGGLE)?.getAttribute('aria-expanded') === 'true';
}

/** Folds or opens one block, and says so on its button. */
export function setExpanded(root: HTMLElement, expanded: boolean, announce = true): void {
  const toggle = query(root, TOGGLE);
  if (!toggle) {
    return;
  }
  toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
  root.classList.toggle('is-expanded', expanded);
  const label = toggle.getAttribute(expanded ? 'data-nowo-ui-show-more-less' : 'data-nowo-ui-show-more-more');
  if (label !== null) {
    toggle.textContent = label;
  }
  if (announce) {
    root.dispatchEvent(new CustomEvent('nowo-ui:show-more:toggle', { bubbles: true, detail: { expanded } }));
  }
}

/** Marks one block as driven by this script, and shows its button only if there is more to show. */
export function syncBlock(root: HTMLElement): void {
  const content = query(root, CONTENT);
  const toggle = query(root, TOGGLE);
  if (!content || !toggle) {
    return;
  }
  root.setAttribute(READY, '');
  if (isExpanded(root)) {
    toggle.hidden = false;
    return;
  }
  const overflows = content.scrollHeight > content.clientHeight + 1;
  toggle.hidden = !overflows;
  if (!overflows) {
    // Nothing is clipped, so the fold can stay on: it changes nothing.
    root.classList.remove('is-expanded');
  }
  resizes?.observe(content);
}

export function syncAll(scope: ParentNode = document): void {
  if (scope instanceof Element && Element.prototype.matches.call(scope, ROOT)) {
    syncBlock(scope as HTMLElement);
  }
  scope.querySelectorAll<HTMLElement>(ROOT).forEach(syncBlock);
}

function onClick(event: MouseEvent): void {
  const toggle = event.target instanceof Element ? closest(event.target, TOGGLE) : null;
  const root = toggle ? closest(toggle, ROOT) : null;
  if (root) {
    event.preventDefault();
    setExpanded(root, !isExpanded(root));
  }
}

function onFocusIn(event: FocusEvent): void {
  const content = event.target instanceof Element ? closest(event.target, CONTENT) : null;
  const root = content ? closest(content, ROOT) : null;
  const toggle = root ? query(root, TOGGLE) : null;
  if (root && toggle && !toggle.hidden && !isExpanded(root)) {
    setExpanded(root, true);
  }
}

let bound = false;

export function bindNowoUiShowMore(): void {
  if (bound) {
    return;
  }
  bound = true;
  listen(document, 'click', onClick);
  listen(document, 'focusin', onFocusIn);
  if (typeof ResizeObserver === 'function') {
    resizes = new ResizeObserver((entries) => {
      entries.forEach((entry) => {
        const root = closest(entry.target, ROOT);
        if (root) {
          syncBlock(root);
        }
      });
    });
  }
  if (Reflect.get(Document.prototype, 'readyState', document) === 'loading') {
    listen(document, 'DOMContentLoaded', () => syncAll());
  } else {
    syncAll();
  }
  new MutationObserver((records) => {
    for (const record of records) {
      record.addedNodes.forEach((node) => {
        if (node instanceof Element) {
          syncAll(node);
        }
      });
    }
  }).observe(document, { childList: true, subtree: true });
}

const BOUND_KEY = Symbol.for('nowo-ui.show-more.bound');
if (typeof document !== 'undefined' && !(window as unknown as Record<symbol, unknown>)[BOUND_KEY]) {
  (window as unknown as Record<symbol, unknown>)[BOUND_KEY] = true;
  bindNowoUiShowMore();
}
