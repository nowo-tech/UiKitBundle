/**
 * IIFE entry for the "open modal on load" deep link (`js/nowo-ui-open-modal.js`).
 *
 * Runs once the document is interactive (after kit scripts loaded earlier in the page had a chance
 * to bind) and exposes `window.nowoUiOpenModalOnLoad()` for hosts that render the island late.
 * Vite / Encore hosts can import `openModalOnLoad` from `nowo-ui-open-modal-core.ts` instead.
 */

import { openModalOnLoad } from './nowo-ui-open-modal-core';

declare global {
  interface Window {
    nowoUiOpenModalOnLoad?: () => void;
  }
}

let openModalBound = false;

export function bindNowoUiOpenModal(doc: Document = document, win: Window = window): void {
  if (openModalBound) {
    return;
  }
  openModalBound = true;
  const run = (): void => openModalOnLoad(doc, win);
  win.nowoUiOpenModalOnLoad = run;
  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', run, { once: true });
  } else {
    run();
  }
}

/** Test helper: allow re-binding in a fresh document. */
export function resetNowoUiOpenModalForTests(): void {
  openModalBound = false;
}

bindNowoUiOpenModal();
