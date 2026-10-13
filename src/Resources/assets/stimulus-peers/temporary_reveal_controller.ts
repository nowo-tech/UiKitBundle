import { Controller } from '@hotwired/stimulus';

const COPY_ATTRIBUTES = ['data-clipboard-copy-text-value', 'data-nowo-ui-clipboard-text'];
const COPY_SELECTOR = COPY_ATTRIBUTES.map((name) => `[${name}]`).join(', ');
const URL_CREDENTIALS = /(:\/\/[^:/@]+:)[^@]+(@)/;

/** Mask URL credentials (`scheme://user:••••••••@host`) or, for any other value, the whole value. */
export function maskSecret(value: string): string {
  if (URL_CREDENTIALS.test(value)) {
    return value.replace(URL_CREDENTIALS, '$1••••••••$2');
  }

  return '•'.repeat(Math.min(Math.max(value.length, 8), 24));
}

/**
 * Optional Stimulus peer (1.15) — show a secret value temporarily (masked by default), then auto-hide.
 *
 * Masking: credentials inside a URL (`scheme://user:secret@host`) keep the URL readable and replace
 * the password; any other value is fully replaced by bullets.
 *
 * With `clearOnHide`, the plaintext is stripped from the DOM after hide (the secret value attribute,
 * copy buttons inside the root — `data-clipboard-copy-text-value` / `data-nowo-ui-clipboard-text` —
 * are emptied and disabled) so a shoulder-surfer / later XSS cannot re-read it.
 *
 * Identifier: `temporary-reveal`
 * Values: `secret`, `duration` (ms, default 30000; 0 = no auto-hide), `showLabel` / `hideLabel`
 *         (default Show / Hide), `startRevealed`, `clearOnHide`, `clearedLabel` (default Hidden).
 * Targets: `display` (required), `toggle` (optional button).
 * Actions: `temporary-reveal#toggle`, `#reveal`, `#hide`.
 */
export default class extends Controller {
  static targets = ['display', 'toggle'];

  static values = {
    secret: { type: String, default: '' },
    duration: { type: Number, default: 30_000 },
    showLabel: { type: String, default: 'Show' },
    hideLabel: { type: String, default: 'Hide' },
    startRevealed: { type: Boolean, default: false },
    clearOnHide: { type: Boolean, default: false },
    clearedLabel: { type: String, default: 'Hidden' },
  };

  declare readonly displayTarget: HTMLElement;
  declare readonly hasToggleTarget: boolean;
  declare readonly toggleTarget: HTMLElement;
  declare secretValue: string;
  declare readonly durationValue: number;
  declare readonly showLabelValue: string;
  declare readonly hideLabelValue: string;
  declare readonly startRevealedValue: boolean;
  declare readonly clearOnHideValue: boolean;
  declare readonly clearedLabelValue: string;

  private hideTimer: number | null = null;
  private revealed = false;
  private cleared = false;

  connect(): void {
    if (this.startRevealedValue) {
      this.reveal();
      return;
    }
    this.applyMasked();
  }

  disconnect(): void {
    this.clearTimer();
  }

  toggle(): void {
    if (this.cleared) {
      return;
    }
    if (this.revealed) {
      this.hide();
      return;
    }
    this.reveal();
  }

  reveal(): void {
    if (this.cleared) {
      return;
    }
    const secret = this.secretValue.trim();
    if ('' === secret) {
      return;
    }

    this.revealed = true;
    this.displayTarget.textContent = secret;
    this.displayTarget.dataset.revealed = 'true';
    this.syncToggleLabel();
    this.clearTimer();
    if (this.durationValue > 0) {
      this.hideTimer = window.setTimeout(() => this.hide(), this.durationValue);
    }
  }

  hide(): void {
    this.clearTimer();
    this.revealed = false;
    if (this.clearOnHideValue) {
      this.purgeSecretFromDom();
      return;
    }
    this.applyMasked();
    this.syncToggleLabel();
  }

  private applyMasked(): void {
    const secret = this.secretValue.trim();
    this.displayTarget.textContent = '' === secret ? '' : this.mask(secret);
    delete this.displayTarget.dataset.revealed;
  }

  private purgeSecretFromDom(): void {
    this.cleared = true;
    this.secretValue = '';
    this.element.removeAttribute(`data-${this.identifier}-secret-value`);
    this.displayTarget.textContent = this.clearedLabelValue;
    delete this.displayTarget.dataset.revealed;
    this.displayTarget.dataset.cleared = 'true';

    this.element.querySelectorAll<HTMLElement>(COPY_SELECTOR).forEach((el) => {
      COPY_ATTRIBUTES.forEach((name) => {
        if (el.hasAttribute(name)) {
          el.setAttribute(name, '');
        }
      });
      if (el instanceof HTMLButtonElement) {
        el.disabled = true;
      }
    });

    if (this.hasToggleTarget) {
      this.toggleTarget.setAttribute('disabled', 'true');
      this.toggleTarget.setAttribute('aria-disabled', 'true');
      this.toggleTarget.setAttribute('aria-pressed', 'false');
      this.toggleTarget.textContent = this.clearedLabelValue;
      this.toggleTarget.setAttribute('aria-label', this.clearedLabelValue);
    }
  }

  private syncToggleLabel(): void {
    if (!this.hasToggleTarget || this.cleared) {
      return;
    }
    const label = this.revealed ? this.hideLabelValue : this.showLabelValue;
    this.toggleTarget.textContent = label;
    this.toggleTarget.setAttribute('aria-label', label);
    this.toggleTarget.setAttribute('aria-pressed', this.revealed ? 'true' : 'false');
  }

  private mask(value: string): string {
    return maskSecret(value);
  }

  private clearTimer(): void {
    if (null === this.hideTimer) {
      return;
    }
    window.clearTimeout(this.hideTimer);
    this.hideTimer = null;
  }
}
