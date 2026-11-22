/**
 * Filter sheet runtime (2.61, partial `filter_bar`; 2.65 also the `list_filters` partial with
 * theme.pages.list.filters.sheet): on phones (CSS below 48rem, once this script marks the root
 * data-nowo-ui-filter-sheet-ready) the filter fields fold behind the "Filters" button; a click opens the sheet (a
 * modal <dialog>, always through showModal()) and MOVES the same panel node into it, so values, names and the GET
 * submit are unchanged. Closing (the close button, Escape / the back gesture, a submit) moves the panel back. The
 * browser returns the focus to the button (2.65: Chrome Modern Web Guidance, `dialog`); the runtime only focuses the
 * button itself when moving the panel back would leave the focus nowhere. When the screen grows past the phone width
 * while the sheet is open, it closes.
 *
 * 2.65: no `open` attribute fallback any more. Where showModal() is missing the root is never marked ready, so the
 * fields simply stay inline at every width. No focus trap, inert, Escape handler or scroll lock: the native modal
 * and the stylesheet (overscroll-behavior on the dialog and its ::backdrop) provide them.
 */

const ROOT = '[data-nowo-ui-filter-sheet]';
export const PHONE_QUERY = '(max-width: 47.99rem)';

function parts(root: Element): { panel: HTMLElement; dialog: HTMLDialogElement; slot: HTMLElement } | null {
  const panel = root.querySelector<HTMLElement>('[data-nowo-ui-filter-sheet-panel]');
  const dialog = root.querySelector<HTMLDialogElement>('dialog[data-nowo-ui-filter-sheet-dialog]');
  const slot = root.querySelector<HTMLElement>('[data-nowo-ui-filter-sheet-slot]');
  return panel && dialog && slot ? { panel, dialog, slot } : null;
}

/** The native modal is Baseline; without it the sheet is never enabled (the fields stay inline). */
function canShowModal(dialog: HTMLDialogElement): boolean {
  return typeof dialog.showModal === 'function' && typeof dialog.close === 'function';
}

export function openSheet(root: Element): void {
  const p = parts(root);
  if (!p || p.dialog.open || !canShowModal(p.dialog)) {
    return;
  }
  p.slot.appendChild(p.panel);
  p.dialog.showModal();
  root.querySelector('[data-nowo-ui-filter-sheet-open]')?.setAttribute('aria-expanded', 'true');
  // The first field that can take the focus (a hidden or disabled control, e.g. the card's fold toggle that the sheet
  // hides, refuses it and the next one is tried).
  for (const field of p.panel.querySelectorAll<HTMLElement>('input:not([type="hidden"]), select, textarea, button')) {
    field.focus();
    if (document.activeElement === field) {
      break;
    }
  }
}

export function closeSheet(root: Element): void {
  const p = parts(root);
  if (!p) {
    return;
  }
  if (p.dialog.open && canShowModal(p.dialog)) {
    p.dialog.close();
  }
  restore(root);
}

/**
 * The panel goes back in front of the dialog (also after a native Escape / back-gesture close). The browser has
 * already returned the focus to the invoker when the dialog closed; only when the panel had to be moved and the
 * focus is left on nothing (the body) or inside the moved nodes does the runtime focus the button itself.
 */
function restore(root: Element): void {
  const p = parts(root);
  if (!p) {
    return;
  }
  const toggle = root.querySelector<HTMLElement>('[data-nowo-ui-filter-sheet-open]');
  toggle?.setAttribute('aria-expanded', 'false');
  if (p.panel.parentElement === root) {
    return;
  }
  const active = document.activeElement;
  const lost = !active || active === document.body || p.dialog.contains(active);
  root.insertBefore(p.panel, p.dialog);
  if (lost) {
    toggle?.focus();
  }
}

function onClick(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) {
    return;
  }
  const root = target.closest(ROOT);
  if (!root) {
    return;
  }
  if (target.closest('[data-nowo-ui-filter-sheet-open]')) {
    event.preventDefault();
    openSheet(root);
  } else if (target.closest('[data-nowo-ui-filter-sheet-close]')) {
    event.preventDefault();
    closeSheet(root);
  }
}

export function upgradeFilterSheets(scope: ParentNode = document): void {
  scope.querySelectorAll(ROOT).forEach((root) => {
    const p = parts(root);
    if (!p || !canShowModal(p.dialog) || root.hasAttribute('data-nowo-ui-filter-sheet-ready')) {
      return;
    }
    p.dialog.addEventListener('close', () => restore(root));
    root.querySelector('[data-nowo-ui-filter-sheet-open]')?.setAttribute('aria-expanded', 'false');
    root.setAttribute('data-nowo-ui-filter-sheet-ready', '');
  });
}

let bound = false;

export function bindNowoUiFilterSheet(): void {
  upgradeFilterSheets();
  if (bound) {
    return;
  }
  bound = true;
  document.addEventListener('click', onClick);
  if (typeof window.matchMedia === 'function') {
    const query = window.matchMedia(PHONE_QUERY);
    const onChange = (): void => {
      if (!query.matches) {
        document.querySelectorAll(ROOT).forEach(closeSheet);
      }
    };
    if (typeof query.addEventListener === 'function') {
      query.addEventListener('change', onChange);
    }
  }
}

bindNowoUiFilterSheet();
