/**
 * Form busy runtime (2.61): the loading button and the error summary focus.
 * - A form with data-nowo-ui-busy-on-submit: once the browser has validated it and nothing cancelled the submit, the
 *   submit button (the submitter, else the first one) gets aria-busy="true" (CSS spinner) and aria-disabled, and a
 *   second submit is ignored (no double post). A page restored from the back / forward cache is cleared, and so is a
 *   form still busy after data-nowo-ui-busy-timeout ms (default 15000; 0 = never), e.g. a submit that answered with a
 *   download and never left the page (audit XJ-6).
 * - The first [data-nowo-ui-error-summary] of the page takes the focus on load (once), so the errors are announced;
 *   its links focus their field instead of only scrolling to it.
 */

import { announceScript } from './script-banner';

declare const __UI_KIT_BUILD_TIME__: string;

announceScript('ui-kit/form-busy', typeof __UI_KIT_BUILD_TIME__ !== 'undefined' ? __UI_KIT_BUILD_TIME__ : undefined);

const BUSY_FORM = 'form[data-nowo-ui-busy-on-submit]';

function submitterOf(form: HTMLFormElement, event: SubmitEvent): HTMLElement | null {
  const submitter = event.submitter;
  if (submitter instanceof HTMLElement) {
    return submitter;
  }
  return form.querySelector<HTMLElement>('button[type="submit"], button:not([type]), input[type="submit"]');
}

function onSubmit(event: Event): void {
  const form = event.target;
  if (!(form instanceof HTMLFormElement) || !form.matches(BUSY_FORM)) {
    return;
  }
  if (form.hasAttribute('data-nowo-ui-busy')) {
    event.preventDefault();
    return;
  }
  // Let other listeners (validation peers, confirm dialogs) cancel first; mark busy only when the post goes ahead.
  window.setTimeout(() => {
    if (event.defaultPrevented) {
      return;
    }
    form.setAttribute('data-nowo-ui-busy', '');
    const button = submitterOf(form, event as SubmitEvent);
    if (button) {
      button.setAttribute('aria-busy', 'true');
      button.setAttribute('aria-disabled', 'true');
    }
    const limit = Number.parseInt(form.getAttribute('data-nowo-ui-busy-timeout') ?? '15000', 10);
    if (Number.isFinite(limit) && limit > 0) {
      window.setTimeout(() => clearBusy(form.parentElement ?? document), limit);
    }
  }, 0);
}

export function clearBusy(scope: ParentNode = document): void {
  scope.querySelectorAll('form[data-nowo-ui-busy]').forEach((form) => {
    form.removeAttribute('data-nowo-ui-busy');
    form.querySelectorAll('[aria-busy="true"]').forEach((button) => {
      button.removeAttribute('aria-busy');
      button.removeAttribute('aria-disabled');
    });
  });
}

export function focusErrorSummary(scope: ParentNode = document): void {
  const summary = scope.querySelector<HTMLElement>('[data-nowo-ui-error-summary]:not([data-nowo-ui-error-summary-seen])');
  if (summary) {
    summary.setAttribute('data-nowo-ui-error-summary-seen', '');
    summary.focus();
  }
}

function onClick(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) {
    return;
  }
  const link = target.closest<HTMLAnchorElement>('[data-nowo-ui-error-summary] a[href^="#"]');
  if (!link) {
    return;
  }
  const field = document.getElementById(decodeURIComponent((link.getAttribute('href') ?? '').slice(1)));
  if (field) {
    event.preventDefault();
    field.focus();
    if (typeof field.scrollIntoView === 'function') {
      field.scrollIntoView({ block: 'center' });
    }
  }
}

let bound = false;

export function bindNowoUiFormBusy(): void {
  focusErrorSummary();
  if (bound) {
    return;
  }
  bound = true;
  document.addEventListener('submit', onSubmit);
  document.addEventListener('click', onClick);
  window.addEventListener('pageshow', () => clearBusy());
}

bindNowoUiFormBusy();
