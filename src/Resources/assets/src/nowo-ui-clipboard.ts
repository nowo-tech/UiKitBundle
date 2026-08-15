/**
 * Clipboard copy via [data-nowo-ui-clipboard] (or [data-nowo-ui-clipboard-copy]).
 * Text: data-nowo-ui-clipboard-text
 * URL body: data-nowo-ui-clipboard-url (fetch same-origin, then copy)
 * Flash label: data-nowo-ui-clipboard-label / data-nowo-ui-clipboard-done-label
 */

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
  document.execCommand('copy');
  area.remove();
}

function flashDone(target: HTMLElement | null, label: string, doneLabel: string): void {
  if (!target) {
    return;
  }
  const prev = target.getAttribute('data-nowo-ui-clipboard-flash-timer');
  if (prev) {
    window.clearTimeout(Number.parseInt(prev, 10));
  }
  target.textContent = doneLabel;
  target.setAttribute('aria-label', doneLabel);
  const id = window.setTimeout(() => {
    target.textContent = label;
    target.setAttribute('aria-label', label);
    target.removeAttribute('data-nowo-ui-clipboard-flash-timer');
  }, FLASH_MS);
  target.setAttribute('data-nowo-ui-clipboard-flash-timer', String(id));
}

export async function copyText(value: string, flashTarget: HTMLElement | null = null): Promise<boolean> {
  const trimmed = value.trim();
  if (trimmed === '') {
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

async function copyFromUrl(url: string, flashTarget: HTMLElement | null): Promise<void> {
  const trimmed = url.trim();
  if (trimmed === '') {
    return;
  }
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
  const text = host.getAttribute('data-nowo-ui-clipboard-text') ?? '';
  const url = host.getAttribute('data-nowo-ui-clipboard-url') ?? '';

  if (text.trim() !== '') {
    void copyText(text, flashTarget);
    return;
  }
  if (url.trim() !== '') {
    void copyFromUrl(url, flashTarget);
  }
}

let clipboardBound = false;

export function bindNowoUiClipboard(): void {
  if (clipboardBound) {
    return;
  }
  clipboardBound = true;
  document.addEventListener('click', onDocumentClick);
  window.nowoUiCopyText = copyText;
}

bindNowoUiClipboard();
