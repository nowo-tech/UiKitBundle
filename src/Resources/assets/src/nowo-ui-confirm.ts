/**
 * Accessible confirm dialog via native <dialog data-nowo-ui-confirm>.
 * Open: [data-nowo-confirm-open][data-nowo-confirm-target="id"]
 * Close: [data-nowo-confirm-close] or Escape / cancel.
 * Host owns destructive POST + CSRF inside the dialog body/footer.
 *
 * Type-to-confirm (2.0, View\Confirm::expectedValue): an `input[data-nowo-confirm-expected="VALUE"]` inside the
 * dialog (or inside a `[data-nowo-confirm-scope]` element, e.g. the full-page confirm) keeps every
 * `[data-nowo-confirm-submit]` button of that scope disabled until the input value is exactly VALUE. Opening a
 * dialog clears the input and focuses it. No inline handlers (CSP).
 */

declare global {
  interface Window {
    nowoUiOpenConfirm?: (id: string | HTMLDialogElement | null) => void;
    nowoUiCloseConfirm?: (el: HTMLDialogElement | null) => void;
  }
}

function resolveDialog(id: string | HTMLDialogElement | null): HTMLDialogElement | null {
  if (id === null) {
    return null;
  }
  if (typeof id === 'string') {
    const el = document.getElementById(id);
    return el instanceof HTMLDialogElement ? el : null;
  }
  return id;
}

const EXPECTED = 'input[data-nowo-confirm-expected]';
const SCOPE = 'dialog[data-nowo-ui-confirm], [data-nowo-confirm-scope]';

/** Enables the scope's confirm buttons only when the input holds exactly the expected value. */
export function syncExpected(input: HTMLInputElement): void {
  const scope = input.closest(SCOPE);
  if (!scope) {
    return;
  }
  const matches = input.value === input.getAttribute('data-nowo-confirm-expected');
  scope.querySelectorAll('[data-nowo-confirm-submit]').forEach((button) => {
    if (button instanceof HTMLButtonElement) {
      button.disabled = !matches;
    }
  });
}

/** Clears the expected-value inputs of a scope (dialog open) and re-locks its confirm buttons. */
export function resetExpected(scope: ParentNode): HTMLInputElement | null {
  let first: HTMLInputElement | null = null;
  scope.querySelectorAll(EXPECTED).forEach((input) => {
    if (input instanceof HTMLInputElement) {
      input.value = '';
      syncExpected(input);
      first ??= input;
    }
  });
  return first;
}

export function openConfirm(id: string | HTMLDialogElement | null): void {
  const dialog = resolveDialog(id);
  if (!dialog || typeof dialog.showModal !== 'function') {
    return;
  }
  if (!dialog.open) {
    const input = resetExpected(dialog);
    dialog.showModal();
    input?.focus();
  }
}

export function closeConfirm(el: HTMLDialogElement | null): void {
  if (!el || typeof el.close !== 'function') {
    return;
  }
  if (el.open) {
    el.close();
  }
}

function onDocumentClick(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) {
    return;
  }

  const openBtn = target.closest('[data-nowo-confirm-open]');
  if (openBtn) {
    const targetId = openBtn.getAttribute('data-nowo-confirm-target');
    if (targetId) {
      event.preventDefault();
      openConfirm(targetId);
    }
    return;
  }

  const closeBtn = target.closest('[data-nowo-confirm-close]');
  if (closeBtn) {
    const dialog = closeBtn.closest('dialog[data-nowo-ui-confirm]');
    if (dialog instanceof HTMLDialogElement) {
      event.preventDefault();
      closeConfirm(dialog);
    }
  }
}

/** Locks the confirm buttons of every type-to-confirm scope under root (page load: the full-page confirm). */
export function syncAllExpected(root: ParentNode): void {
  root.querySelectorAll(EXPECTED).forEach((input) => {
    if (input instanceof HTMLInputElement) {
      syncExpected(input);
    }
  });
}

function onDocumentInput(event: Event): void {
  const target = event.target;
  if (target instanceof HTMLInputElement && target.matches(EXPECTED)) {
    syncExpected(target);
  }
}

let confirmBound = false;

export function bindNowoUiConfirm(): void {
  if (confirmBound) {
    return;
  }
  confirmBound = true;
  document.addEventListener('click', onDocumentClick);
  document.addEventListener('input', onDocumentInput);
  syncAllExpected(document);
  window.nowoUiOpenConfirm = openConfirm;
  window.nowoUiCloseConfirm = closeConfirm;
}

bindNowoUiConfirm();
