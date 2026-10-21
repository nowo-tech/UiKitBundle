/**
 * Auto-submit (2.6): a `<select>` / `<input>` with `data-nowo-ui-autosubmit` submits its form when it changes — the
 * per-page select of the compact pagination (`partials/_pagination_compact.html.twig`), filter selects… One delegated
 * `change` listener on the document (markup re-rendered by Turbo / Live Components keeps working), no inline handler
 * (CSP). `requestSubmit()` runs the form's validation and `submit` listeners; older browsers fall back to `submit()`.
 * Without JavaScript the markup keeps a `<noscript>` submit button.
 */

export const AUTOSUBMIT = '[data-nowo-ui-autosubmit]';

/** Submits the form of a changed control (no-op for controls outside a form). */
export function autosubmit(control: Element): boolean {
  const form = control instanceof HTMLSelectElement || control instanceof HTMLInputElement ? control.form : control.closest('form');
  if (!(form instanceof HTMLFormElement)) {
    return false;
  }
  if (typeof form.requestSubmit === 'function') {
    form.requestSubmit();
  } else {
    form.submit();
  }

  return true;
}

function onChange(event: Event): void {
  const target = event.target;
  if (target instanceof Element && target.matches(AUTOSUBMIT)) {
    autosubmit(target);
  }
}

let bound = false;

export function bindNowoUiAutosubmit(): void {
  if (bound) {
    return;
  }
  bound = true;
  document.addEventListener('change', onChange);
}

bindNowoUiAutosubmit();
