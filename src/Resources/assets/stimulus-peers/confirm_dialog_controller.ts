/**
 * Optional Stimulus peer — accessible confirm `<dialog>`.
 *
 * Prefer kit IIFE `nowo-ui-confirm.js` + `_confirm.html.twig` for open/close via
 * `data-nowo-confirm-open` / `data-nowo-confirm-close`.
 * This peer adds typed confirmation, body portal, and Stimulus actions.
 *
 * Identifier: `confirm-dialog`
 * Close attrs (either): data-nowo-confirm-close | data-confirm-dialog-close
 */

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['dialog', 'confirmInput', 'submit'];

  static values = {
    expected: String,
    openOnConnect: Boolean,
  };

  declare readonly dialogTarget: HTMLDialogElement;
  declare readonly hasDialogTarget: boolean;
  declare readonly hasConfirmInputTarget: boolean;
  declare readonly confirmInputTarget: HTMLInputElement;
  declare readonly hasSubmitTarget: boolean;
  declare readonly submitTarget: HTMLButtonElement;
  declare readonly hasExpectedValue: boolean;
  declare readonly expectedValue: string;
  declare readonly openOnConnectValue: boolean;

  /** Ignore backdrop clicks that belong to the same gesture that opened the dialog. */
  private ignoreBackdropUntil = 0;

  private dialogEl: HTMLDialogElement | null = null;
  private confirmInputEl: HTMLInputElement | null = null;
  private submitEl: HTMLButtonElement | null = null;
  private portaled = false;
  private readonly onDialogClick = (event: MouseEvent): void => this.handleDialogClick(event);
  private readonly onConfirmInput = (): void => this.syncSubmit();

  connect(): void {
    this.cacheElements();
    if (this.openOnConnectValue) {
      this.open();
    }
  }

  disconnect(): void {
    this.teardownPortaledListeners();
    const dialog = this.resolveDialog();
    if (dialog?.open) {
      dialog.close();
    }
    if (this.portaled && dialog && !this.element.isConnected) {
      dialog.remove();
    }
    this.dialogEl = null;
    this.confirmInputEl = null;
    this.submitEl = null;
    this.portaled = false;
  }

  open(event?: Event): void {
    event?.preventDefault();
    event?.stopPropagation();

    this.cacheElements();
    const dialog = this.resolveDialog();
    if (!(dialog instanceof HTMLDialogElement)) {
      return;
    }

    this.portalDialog(dialog);

    if (this.confirmInputEl) {
      this.confirmInputEl.value = '';
      this.confirmInputEl.disabled = false;
    }
    this.syncSubmit();

    this.ignoreBackdropUntil = Date.now() + 500;
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        try {
          if (!dialog.open) {
            if (typeof window.nowoUiOpenConfirm === 'function') {
              window.nowoUiOpenConfirm(dialog);
            } else {
              dialog.showModal();
            }
          }
        } catch {
          // InvalidStateError if already open / not connected — ignore.
        }
        this.confirmInputEl?.focus();
      });
    });
  }

  close(event?: Event): void {
    event?.preventDefault();
    const dialog = this.resolveDialog();
    if (!dialog) {
      return;
    }
    if (typeof window.nowoUiCloseConfirm === 'function') {
      window.nowoUiCloseConfirm(dialog);
      return;
    }
    dialog.close();
  }

  syncSubmit(): void {
    const submit = this.submitEl ?? (this.hasSubmitTarget ? this.submitTarget : null);
    if (!(submit instanceof HTMLButtonElement)) {
      return;
    }
    this.submitEl = submit;

    if (!this.hasExpectedValue || this.expectedValue === '') {
      submit.disabled = false;
      return;
    }
    const input = this.confirmInputEl;
    if (!(input instanceof HTMLInputElement)) {
      submit.disabled = true;
      return;
    }
    submit.disabled = input.value !== this.expectedValue;
  }

  private cacheElements(): void {
    const dialog = this.resolveDialog();
    if (dialog) {
      this.dialogEl = dialog;
    }
    if (this.hasConfirmInputTarget) {
      this.confirmInputEl = this.confirmInputTarget;
    } else if (dialog) {
      const input = dialog.querySelector<HTMLInputElement>(
        "[data-confirm-dialog-target='confirmInput'], [data-nowo-ui-confirm-input]",
      );
      this.confirmInputEl = input;
    }
    if (this.hasSubmitTarget) {
      this.submitEl = this.submitTarget;
    } else if (dialog) {
      const submit = dialog.querySelector<HTMLButtonElement>(
        "[data-confirm-dialog-target='submit'], [data-nowo-ui-confirm-submit]",
      );
      this.submitEl = submit;
    }
  }

  private resolveDialog(): HTMLDialogElement | null {
    if (this.dialogEl instanceof HTMLDialogElement) {
      return this.dialogEl;
    }
    if (this.hasDialogTarget) {
      return this.dialogTarget;
    }
    if (this.element instanceof HTMLDialogElement && this.element.hasAttribute('data-nowo-ui-confirm')) {
      return this.element;
    }
    const nested =
      this.element.querySelector('dialog[data-nowo-ui-confirm]') ??
      this.element.querySelector('dialog.confirm-dialog');
    return nested instanceof HTMLDialogElement ? nested : null;
  }

  private portalDialog(dialog: HTMLDialogElement): void {
    if (dialog.parentElement !== document.body) {
      document.body.appendChild(dialog);
      this.portaled = true;
    }
    dialog.removeEventListener('click', this.onDialogClick);
    dialog.addEventListener('click', this.onDialogClick);
    if (this.confirmInputEl) {
      this.confirmInputEl.removeEventListener('input', this.onConfirmInput);
      this.confirmInputEl.addEventListener('input', this.onConfirmInput);
    }
  }

  private teardownPortaledListeners(): void {
    this.dialogEl?.removeEventListener('click', this.onDialogClick);
    this.confirmInputEl?.removeEventListener('input', this.onConfirmInput);
  }

  private handleDialogClick(event: MouseEvent): void {
    const target = event.target;
    if (!(target instanceof Element)) {
      return;
    }

    if (target.closest('[data-nowo-confirm-close], [data-confirm-dialog-close]')) {
      this.close(event);
      return;
    }

    if (Date.now() < this.ignoreBackdropUntil) {
      return;
    }
    if (event.target === this.resolveDialog()) {
      this.close();
    }
  }
}

declare global {
  interface Window {
    nowoUiOpenConfirm?: (id: string | HTMLDialogElement | null) => void;
    nowoUiCloseConfirm?: (el: HTMLDialogElement | null) => void;
  }
}
