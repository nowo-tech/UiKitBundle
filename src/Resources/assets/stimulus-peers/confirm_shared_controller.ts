/**
 * Optional Stimulus peer (2.70) — the shared confirm <dialog> (partial `confirm_shared`, 2.68) without the IIFE runtime
 * js/nowo-ui-confirm.js: for pages that load only the Stimulus peers.
 *
 * Identifier: `confirm-shared`. Put it on the shared <dialog> itself (the partial prints it with `runtime: 'stimulus'`,
 * or with nowo_ui_kit.dialogs.runtime: stimulus):
 *
 *   <dialog id="nowo-ui-table-confirm" data-nowo-ui-confirm data-nowo-confirm-shared data-controller="confirm-shared">
 *
 * The row toggles keep the markup of the IIFE runtime (`data-nowo-confirm-open`, `data-nowo-confirm-target="<id>"`,
 * the content as data-nowo-confirm-* and the row form id as data-nowo-confirm-form): a click on a toggle of this
 * dialog fills it (textContent only), binds the confirm button to that row's hidden POST form with `form="…"` (its
 * own target and CSRF token) and opens it — the same code as the IIFE runtime (src/nowo-ui-confirm-core.ts). A
 * toggle without a form (disabled action) opens nothing. `[data-nowo-confirm-close]` closes it; type-to-confirm keeps
 * the confirm button disabled until the input matches; closing drops the row binding and returns focus to the toggle
 * (else its menu item / menu toggle, never a hidden element). Also works on a plain (not shared) kit confirm dialog
 * with an id.
 *
 * Runtime ownership: on connect the dialog is claimed (`data-nowo-ui-runtime-owner="stimulus"`), so
 * js/nowo-ui-confirm.js, when also loaded, leaves it to this controller (no double open or double binding).
 */

import { Controller } from '@hotwired/stimulus';
import { EXPECTED, openConfirmDialog, syncExpected, unbindSharedConfirm } from '../src/nowo-ui-confirm-core';
import { bindFocusReturn, returnFocus } from '../src/nowo-ui-focus-return';
import { bindMotion, closeDialogWithEffect } from '../src/nowo-ui-motion-core';
import { claim, release } from '../src/nowo-ui-runtime-owner';

export default class extends Controller<HTMLElement> {
  private dialog: HTMLDialogElement | null = null;

  private readonly onDocumentClick = (event: MouseEvent): void => {
    const target = event.target;
    const dialog = this.dialog;
    if (!(target instanceof Element) || !dialog || dialog.id === '') {
      return;
    }
    const opener = target.closest('[data-nowo-confirm-open]');
    if (opener && opener.getAttribute('data-nowo-confirm-target') === dialog.id) {
      event.preventDefault();
      this.openFor(opener);
    }
  };

  private readonly onDialogClick = (event: MouseEvent): void => {
    const target = event.target;
    if (target instanceof Element && target.closest('[data-nowo-confirm-close]')) {
      event.preventDefault();
      this.close();
    }
  };

  private readonly onInput = (event: Event): void => {
    const target = event.target;
    if (target instanceof HTMLInputElement && target.matches(EXPECTED)) {
      syncExpected(target);
    }
  };

  private readonly onClose = (): void => {
    const dialog = this.dialog;
    if (!dialog || dialog.open) {
      return;
    }
    if (dialog.hasAttribute('data-nowo-confirm-shared')) {
      unbindSharedConfirm(dialog);
    }
    returnFocus(dialog);
  };

  override connect(): void {
    const dialog = this.element instanceof HTMLDialogElement ? this.element : this.element.querySelector('dialog[data-nowo-ui-confirm]');
    if (!(dialog instanceof HTMLDialogElement)) {
      return;
    }
    this.dialog = dialog;
    bindMotion();
    bindFocusReturn();
    claim(dialog);
    document.addEventListener('click', this.onDocumentClick);
    dialog.addEventListener('click', this.onDialogClick);
    dialog.addEventListener('input', this.onInput);
    dialog.addEventListener('close', this.onClose);
  }

  override disconnect(): void {
    document.removeEventListener('click', this.onDocumentClick);
    this.dialog?.removeEventListener('click', this.onDialogClick);
    this.dialog?.removeEventListener('input', this.onInput);
    this.dialog?.removeEventListener('close', this.onClose);
    release(this.dialog);
    this.dialog = null;
  }

  /** Opens the dialog for a toggle (shared: filled from it first; nothing when it names no row form). */
  openFor(opener: Element | null): boolean {
    return this.dialog !== null && openConfirmDialog(this.dialog, opener);
  }

  /** Action: `click->confirm-shared#close`, or from script. */
  close(event?: Event): void {
    event?.preventDefault();
    if (this.dialog?.open) {
      closeDialogWithEffect(this.dialog);
    }
  }
}
