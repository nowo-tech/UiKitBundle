/**
 * Lazy release-version dialog ([data-nowo-ui-release]) — optional `release_check` feature.
 *
 * Fetches `data-nowo-ui-release-url` (JSON) once, the first time the dialog opens
 * (`toggle` event, captured on document). Open/close is handled by `nowo-ui-confirm.js`.
 * Exposes `window.nowoUiLoadReleaseStatus(dialog)` for manual triggers.
 */

import {
  fetchReleaseStatus,
  parseReleaseMessages,
  renderReleaseStatus,
  resolveReleaseParts,
  showReleaseError,
  showReleaseLoading,
} from './nowo-ui-release-core';

declare global {
  interface Window {
    nowoUiLoadReleaseStatus?: (dialog: HTMLElement | null) => Promise<void>;
  }
}

const inflight = new WeakMap<HTMLElement, Promise<void>>();

export function loadReleaseStatus(dialog: HTMLElement | null): Promise<void> {
  if (!dialog || !dialog.hasAttribute('data-nowo-ui-release')) {
    return Promise.resolve();
  }
  if (dialog.getAttribute('data-nowo-ui-release-state') === 'loaded') {
    return Promise.resolve();
  }
  const pending = inflight.get(dialog);
  if (pending) {
    return pending;
  }

  const url = dialog.getAttribute('data-nowo-ui-release-url') ?? '';
  const messages = parseReleaseMessages(dialog.getAttribute('data-nowo-ui-release-messages'));
  const parts = resolveReleaseParts(dialog);
  if (url === '') {
    showReleaseError(parts, messages);
    return Promise.resolve();
  }

  showReleaseLoading(parts);
  dialog.setAttribute('data-nowo-ui-release-state', 'loading');
  const promise = fetchReleaseStatus(url)
    .then((payload) => {
      renderReleaseStatus(parts, payload, messages);
      dialog.setAttribute('data-nowo-ui-release-state', 'loaded');
    })
    .catch(() => {
      showReleaseError(parts, messages);
      dialog.setAttribute('data-nowo-ui-release-state', 'error');
    })
    .finally(() => {
      inflight.delete(dialog);
    });
  inflight.set(dialog, promise);
  return promise;
}

function onToggle(event: Event): void {
  const dialog = event.target;
  if (!(dialog instanceof HTMLDialogElement) || !dialog.open) {
    return;
  }
  const controllers = (dialog.getAttribute('data-controller') ?? '').split(/\s+/);
  if (!dialog.hasAttribute('data-nowo-ui-release') || controllers.indexOf('release-status') !== -1) {
    // Stimulus peer `release-status` owns dialogs that declare a controller.
    return;
  }
  void loadReleaseStatus(dialog);
}

let releaseBound = false;

export function bindNowoUiRelease(): void {
  if (releaseBound) {
    return;
  }
  releaseBound = true;
  // `toggle` does not bubble — capture it at document level.
  document.addEventListener('toggle', onToggle, true);
  window.nowoUiLoadReleaseStatus = loadReleaseStatus;
}

bindNowoUiRelease();
