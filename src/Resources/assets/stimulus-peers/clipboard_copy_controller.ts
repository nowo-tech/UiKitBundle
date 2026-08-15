/**
 * Optional Stimulus peer — clipboard copy.
 *
 * Prefer kit IIFE `nowo-ui-clipboard.js` with `data-nowo-ui-clipboard*` when no Stimulus.
 * Hosts that already use Symfony UX Stimulus can import this controller instead.
 *
 * Identifier: `clipboard-copy`
 * Values: text, url, label, doneLabel (Stimulus `data-clipboard-copy-*-value`)
 *
 * Kit IIFE attrs (no-build path): data-nowo-ui-clipboard-text / -url / -label / -done-label
 */

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static values = {
    text: String,
    url: String,
    label: { type: String, default: 'Copy' },
    doneLabel: { type: String, default: 'Copied' },
  };

  declare readonly textValue: string;
  declare readonly hasTextValue: boolean;
  declare readonly urlValue: string;
  declare readonly hasUrlValue: boolean;
  declare readonly labelValue: string;
  declare readonly doneLabelValue: string;

  private resetTimer: number | null = null;

  async copy(event: Event): Promise<void> {
    event.preventDefault();
    event.stopPropagation();

    const value = this.resolveText().trim();
    if (value === '') {
      return;
    }

    await this.writeClipboard(value, event.currentTarget);
  }

  async copyFromUrl(event: Event): Promise<void> {
    event.preventDefault();
    event.stopPropagation();

    const url = this.resolveUrl().trim();
    if (url === '') {
      return;
    }

    try {
      const response = await fetch(url, {
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
      await this.writeClipboard(body, event.currentTarget);
    } catch {
      // Ignore network / clipboard failures.
    }
  }

  private resolveText(): string {
    if (this.hasTextValue && this.textValue !== '') {
      return this.textValue;
    }
    if (this.element instanceof HTMLElement) {
      return this.element.getAttribute('data-nowo-ui-clipboard-text') ?? '';
    }
    return '';
  }

  private resolveUrl(): string {
    if (this.hasUrlValue && this.urlValue !== '') {
      return this.urlValue;
    }
    if (this.element instanceof HTMLElement) {
      return this.element.getAttribute('data-nowo-ui-clipboard-url') ?? '';
    }
    return '';
  }

  private async writeClipboard(value: string, target: EventTarget | null): Promise<void> {
    try {
      if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(value);
      } else {
        this.fallbackCopy(value);
      }
      this.flashDone(target);
    } catch {
      try {
        this.fallbackCopy(value);
        this.flashDone(target);
      } catch {
        // Ignore clipboard failures (permissions / insecure context).
      }
    }
  }

  private flashDone(target: EventTarget | null): void {
    if (!(target instanceof HTMLElement)) {
      return;
    }

    target.textContent = this.doneLabelValue;
    target.setAttribute('aria-label', this.doneLabelValue);

    if (this.resetTimer !== null) {
      window.clearTimeout(this.resetTimer);
    }

    this.resetTimer = window.setTimeout(() => {
      target.textContent = this.labelValue;
      target.setAttribute('aria-label', this.labelValue);
      this.resetTimer = null;
    }, 1600);
  }

  private fallbackCopy(value: string): void {
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

  disconnect(): void {
    if (this.resetTimer !== null) {
      window.clearTimeout(this.resetTimer);
      this.resetTimer = null;
    }
  }
}
