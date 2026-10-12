/**
 * Deep-link "open modal on load" (e.g. `?new=1` → create-in-modal) without inline script (CSP-safe).
 *
 * The page renders a non-executable JSON island only when the deep link is requested
 * (`partials/_open_modal_on_load.html.twig`):
 *   <script type="application/json" id="nowo-ui-open-modal-on-load">
 *     {"modal":"modal-item-form","trigger":"button.js-new-item","clearParam":"new"}
 *   </script>
 * We click the trigger when given (so kit JS can load the form partial), fall back to opening the
 * modal directly, retry briefly while kit scripts finish booting, then drop the query param from the URL.
 *
 * Side-effect free: Vite / Encore hosts import these helpers; the IIFE entry is `nowo-ui-open-modal.ts`.
 */

export const OPEN_MODAL_ISLAND_ID = 'nowo-ui-open-modal-on-load';

export interface OpenModalOnLoadConfig {
  /** Element id of the modal (`<dialog>` or `.nowo-ui-modal` root). */
  modal: string;
  /** Optional CSS selector of the button that normally opens the modal. */
  trigger?: string;
  /** Optional query parameter removed from the URL once handled. */
  clearParam?: string;
}

export function readOpenModalConfig(doc: Document, islandId: string = OPEN_MODAL_ISLAND_ID): OpenModalOnLoadConfig | null {
  const el = doc.getElementById(islandId);
  if (!el) {
    return null;
  }
  try {
    const parsed: unknown = JSON.parse(String(el.textContent));
    if (parsed === null || typeof parsed !== 'object') {
      return null;
    }
    const data = parsed as Record<string, unknown>;
    if (typeof data.modal !== 'string' || data.modal === '') {
      return null;
    }
    return {
      modal: data.modal,
      trigger: typeof data.trigger === 'string' && data.trigger !== '' ? data.trigger : undefined,
      clearParam: typeof data.clearParam === 'string' && data.clearParam !== '' ? data.clearParam : undefined,
    };
  } catch {
    return null;
  }
}

export function isModalOpen(dialog: HTMLElement): boolean {
  return dialog instanceof HTMLDialogElement ? dialog.open : dialog.classList.contains('nowo-ui-modal-open');
}

function forceOpen(doc: Document, dialog: HTMLElement, trigger: HTMLElement | null): void {
  dialog.classList.add('nowo-ui-modal-open');
  dialog.removeAttribute('hidden');
  dialog.setAttribute('aria-hidden', 'false');
  if (dialog instanceof HTMLDialogElement) {
    if (!dialog.open) {
      try {
        dialog.showModal();
      } catch {
        dialog.setAttribute('open', '');
      }
    }
  } else {
    doc.body.classList.add('nowo-modal-open');
  }
  // Kit / Bootstrap-style listeners load remote form bodies on show.bs.modal (relatedTarget = trigger).
  const ev = new CustomEvent('show.bs.modal', { bubbles: true, cancelable: true });
  Object.defineProperty(ev, 'relatedTarget', { value: trigger });
  dialog.dispatchEvent(ev);
}

/** One attempt; returns true when the modal is open (the retry loop then stops). */
export function tryOpenModal(doc: Document, config: OpenModalOnLoadConfig): boolean {
  const dialog = doc.getElementById(config.modal);
  const trigger = config.trigger ? doc.querySelector<HTMLElement>(config.trigger) : null;
  if (!dialog || (config.trigger && !trigger)) {
    return false;
  }
  if (!isModalOpen(dialog)) {
    trigger?.click();
    if (!isModalOpen(dialog)) {
      forceOpen(doc, dialog, trigger);
    }
  }
  return isModalOpen(dialog);
}

export function clearQueryParam(win: Window, param: string | undefined): void {
  if (!param) {
    return;
  }
  const url = new URL(win.location.href);
  if (!url.searchParams.has(param)) {
    return;
  }
  url.searchParams.delete(param);
  const qs = url.searchParams.toString();
  win.history.replaceState(win.history.state, '', url.pathname + (qs ? `?${qs}` : '') + url.hash);
}

/**
 * Reads the island and opens the modal, retrying every `intervalMs` up to `maxTries` while kit
 * markup / scripts boot. No-op without a (valid) island.
 */
export function openModalOnLoad(doc: Document = document, win: Window = window, maxTries = 40, intervalMs = 50): void {
  const config = readOpenModalConfig(doc);
  if (!config) {
    return;
  }
  let tries = 0;
  const timer = win.setInterval(() => {
    tries += 1;
    const done = tryOpenModal(doc, config);
    if (done || tries >= maxTries) {
      win.clearInterval(timer);
      clearQueryParam(win, config.clearParam);
    }
  }, intervalMs);
}
