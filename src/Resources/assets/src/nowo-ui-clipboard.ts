/**
 * Clipboard copy via [data-nowo-ui-clipboard] (or [data-nowo-ui-clipboard-copy]).
 * Text: data-nowo-ui-clipboard-text
 * URL body: data-nowo-ui-clipboard-url (fetch same-origin, then copy)
 * Flash label: data-nowo-ui-clipboard-label / data-nowo-ui-clipboard-done-label
 * 2.61: element text: data-nowo-ui-clipboard-target (the id of an element whose textContent is copied, e.g. the
 * <code> of the `code_block` partial); controls with data-nowo-ui-clipboard-reveal are rendered `hidden` (they can
 * do nothing without this script) and shown once it runs.
 */

import { setButtonLabel } from './nowo-ui-button-label';
import { announceScript } from './script-banner';

declare const __UI_KIT_BUILD_TIME__: string;

announceScript('ui-kit/clipboard', typeof __UI_KIT_BUILD_TIME__ !== 'undefined' ? __UI_KIT_BUILD_TIME__ : undefined);

declare global {
  interface Window {
    nowoUiCopyText?: (value: string, flashTarget?: HTMLElement | null) => Promise<boolean>;
  }
}

const FLASH_MS = 1600;

function fallbackCopy(value: string): void {
  const area = document.createElement('textarea');
  area.value = value;
  area.setAttribute('readonly', '');
  area.style.position = 'fixed';
  area.style.left = '-9999px';
  document.body.appendChild(area);
  area.select();
  // 2.61 (audit XJ-8): a refused copy (execCommand returns false) is a failure, not a silent success.
  const copied = document.execCommand('copy');
  area.remove();
  if (!copied) {
    throw new Error('copy refused');
  }
}

function flashDone(target: HTMLElement, label: string, doneLabel: string): void {
  const prev = target.getAttribute('data-nowo-ui-clipboard-flash-timer');
  if (prev) {
    window.clearTimeout(Number.parseInt(prev, 10));
  }
  setButtonLabel(target, doneLabel);
  target.setAttribute('aria-label', doneLabel);
  const id = window.setTimeout(() => {
    setButtonLabel(target, label);
    target.setAttribute('aria-label', label);
    target.removeAttribute('data-nowo-ui-clipboard-flash-timer');
  }, FLASH_MS);
  target.setAttribute('data-nowo-ui-clipboard-flash-timer', String(id));
}

export async function copyText(value: string, flashTarget: HTMLElement | null = null, trim = true): Promise<boolean> {
  // 2.61 (audit XJ-4): an element's text (data-nowo-ui-clipboard-target, e.g. code) is copied as it is, not trimmed.
  const trimmed = trim ? value.trim() : value;
  if (trimmed.trim() === '') {
    return false;
  }
  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(trimmed);
    } else {
      fallbackCopy(trimmed);
    }
    if (flashTarget) {
      const label = flashTarget.getAttribute('data-nowo-ui-clipboard-label') ?? 'Copy';
      const done = flashTarget.getAttribute('data-nowo-ui-clipboard-done-label') ?? 'Copied';
      flashDone(flashTarget, label, done);
    }
    return true;
  } catch {
    try {
      fallbackCopy(trimmed);
      if (flashTarget) {
        const label = flashTarget.getAttribute('data-nowo-ui-clipboard-label') ?? 'Copy';
        const done = flashTarget.getAttribute('data-nowo-ui-clipboard-done-label') ?? 'Copied';
        flashDone(flashTarget, label, done);
      }
      return true;
    } catch {
      return false;
    }
  }
}

/** `url` is the trimmed, non-empty `data-nowo-ui-clipboard-url` (checked by the click handler). */
async function copyFromUrl(trimmed: string, flashTarget: HTMLElement | null): Promise<void> {
  try {
    const response = await fetch(trimmed, {
      headers: { Accept: 'text/markdown, application/json, text/plain' },
      credentials: 'same-origin',
    });
    if (!response.ok) {
      return;
    }
    const body = (await response.text()).trim();
    if (body === '') {
      return;
    }
    await copyText(body, flashTarget);
  } catch {
    // Ignore network / clipboard failures.
  }
}

function resolveHost(el: Element): HTMLElement | null {
  const host = el.closest('[data-nowo-ui-clipboard], [data-nowo-ui-clipboard-copy]');
  return host instanceof HTMLElement ? host : null;
}

function onDocumentClick(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) {
    return;
  }
  const host = resolveHost(target);
  if (!host) {
    return;
  }

  event.preventDefault();
  event.stopPropagation();

  const copyBtn = target.closest('[data-nowo-ui-clipboard-copy]');
  const flashTarget = copyBtn instanceof HTMLElement ? copyBtn : host;
  const targetId = host.getAttribute('data-nowo-ui-clipboard-target') ?? '';
  const text = targetId !== '' ? (document.getElementById(targetId)?.textContent ?? '') : (host.getAttribute('data-nowo-ui-clipboard-text') ?? '');
  const url = host.getAttribute('data-nowo-ui-clipboard-url') ?? '';

  if (text.trim() !== '') {
    void copyText(text, flashTarget, targetId === '');
    return;
  }
  if (url.trim() !== '') {
    void copyFromUrl(url.trim(), flashTarget);
  }
}

let clipboardBound = false;

/** Shows the copy controls that wait for this script (idempotent). */
export function revealClipboardControls(root: ParentNode = document): void {
  root.querySelectorAll<HTMLElement>('[data-nowo-ui-clipboard-reveal][hidden]').forEach((el) => {
    el.hidden = false;
  });
}

export function bindNowoUiClipboard(): void {
  revealClipboardControls();
  if (clipboardBound) {
    return;
  }
  clipboardBound = true;
  document.addEventListener('click', onDocumentClick);
  window.nowoUiCopyText = copyText;
}

bindNowoUiClipboard();
