/**
 * Filter sheet runtime (2.61, partial `filter_bar`): on phones (CSS below 48rem, once this script marks the root
 * data-nowo-ui-filter-sheet-ready) the filter fields fold behind the "Filters" button; a click opens the sheet (a
 * modal <dialog>) and MOVES the same panel node into it, so values, names and the GET submit are unchanged. Closing
 * (the close button, Escape, a submit) moves the panel back and returns the focus to the button. When the screen
 * grows past the phone width while the sheet is open, it closes.
 */

const ROOT = '[data-nowo-ui-filter-sheet]';
export const PHONE_QUERY = '(max-width: 47.99rem)';

function parts(root: Element): { panel: HTMLElement; dialog: HTMLDialogElement; slot: HTMLElement } | null {
  const panel = root.querySelector<HTMLElement>('[data-nowo-ui-filter-sheet-panel]');
  const dialog = root.querySelector<HTMLDialogElement>('dialog[data-nowo-ui-filter-sheet-dialog]');
  const slot = root.querySelector<HTMLElement>('[data-nowo-ui-filter-sheet-slot]');
  return panel && dialog && slot ? { panel, dialog, slot } : null;
}

export function openSheet(root: Element): void {
  const p = parts(root);
  if (!p || p.dialog.open) {
    return;
  }
  p.slot.appendChild(p.panel);
  if (typeof p.dialog.showModal === 'function') {
    p.dialog.showModal();
  } else {
    p.dialog.setAttribute('open', '');
  }
  root.querySelector('[data-nowo-ui-filter-sheet-open]')?.setAttribute('aria-expanded', 'true');
  const first = p.panel.querySelector<HTMLElement>('input:not([type="hidden"]), select, textarea, button');
  first?.focus();
}

export function closeSheet(root: Element): void {
  const p = parts(root);
  if (!p) {
    return;
  }
  if (p.dialog.open) {
    if (typeof p.dialog.close === 'function') {
      p.dialog.close();
    } else {
      p.dialog.removeAttribute('open');
    }
  }
  restore(root);
}

/** The panel goes back in front of the dialog and the focus to the button (also after a native Escape close). */
function restore(root: Element): void {
  const p = parts(root);
  if (!p || p.panel.parentElement === root) {
    return;
  }
  root.insertBefore(p.panel, p.dialog);
  const toggle = root.querySelector<HTMLElement>('[data-nowo-ui-filter-sheet-open]');
  toggle?.setAttribute('aria-expanded', 'false');
  toggle?.focus();
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
    if (!p || root.hasAttribute('data-nowo-ui-filter-sheet-ready')) {
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
