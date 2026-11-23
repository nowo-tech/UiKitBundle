/**
 * Optional Stimulus peer — confirm before a submit, without inline `onsubmit=` / `onclick=` handlers (blocked by a
 * strict CSP `script-src`).
 *
 * Identifier: `confirm-submit`
 *
 * On the form (every submit is confirmed):
 *
 *   <form data-controller="confirm-submit"
 *         data-confirm-submit-message-value="Delete?"
 *         data-action="submit->confirm-submit#confirm">
 *
 * On one submit button (only that button is confirmed — e.g. a "Reset" next to "Save"):
 *
 *   <button type="submit" name="reset" value="1"
 *           data-controller="confirm-submit"
 *           data-confirm-submit-message-value="Reset to defaults?"
 *           data-action="click->confirm-submit#confirm">
 *
 * Modes (2.8): `native` (default) asks with `window.confirm()`; `dialog` asks in the kit dialog (`askConfirm`, see
 * ask_confirm.ts): the first event is held and, on Confirm, the same submit (with its submitter) or click is replayed
 * once, so the server sees exactly what the user pressed. Pick the mode per element
 * (`data-confirm-submit-mode-value="dialog"`) or for the whole app with the factory:
 *
 *   export default createConfirmSubmitController({ mode: 'dialog', classes: { dialog: 'confirm-dialog', … } });
 *
 * `data-confirm-submit-blocked-value="true"` always cancels (e.g. last owner cannot be removed); an empty message
 * lets the event through unchanged; `data-confirm-submit-danger-value="false"` keeps the primary button in the dialog
 * for a non-destructive confirmation.
 *
 * Title and button labels (2.69): `data-confirm-submit-title-value` (or the plain `data-confirm-title`) is the dialog
 * heading, the message its body; `data-confirm-submit-confirm-label-value` / `data-confirm-label` and
 * `data-confirm-submit-cancel-label-value` / `data-confirm-cancel-label` name the buttons (default the page's
 * `<html data-confirm-ok / data-confirm-cancel>`). `native` mode shows the title, a blank line and the message in
 * `window.confirm()`. Without them the 2.8 behaviour is unchanged (the message is the heading); with a title and no
 * message the title alone is asked.
 */

import { Controller } from '@hotwired/stimulus';

import { askConfirm, confirmTexts, nativeQuestion, pageConfirmLabels, type ConfirmClasses } from './ask_confirm';

export type ConfirmMode = 'native' | 'dialog';

/**
 * Set (as a property, never an attribute) on every element the peer connects to (2.22). The kit confirm runtime
 * (nowo-ui-confirm.js) reads it to warn in the console when a `confirm-submit` form submits with no peer registered.
 */
export const CONFIRM_SUBMIT_PEER_MARK: unique symbol = Symbol.for('nowo-ui.confirm-submit');

export interface ConfirmSubmitOptions {
  mode?: ConfirmMode;
  classes?: Partial<ConfirmClasses>;
}

export function createConfirmSubmitController(options: ConfirmSubmitOptions = {}) {
  return class extends Controller {
    static override values = {
      message: String,
      /** When true, always prevent submit (e.g. last owner cannot be removed). */
      blocked: { type: Boolean, default: false },
      danger: { type: Boolean, default: true },
      mode: { type: String, default: '' },
      /** 2.69: dialog heading (fallback attribute data-confirm-title). */
      title: { type: String, default: '' },
      /** 2.69: Confirm button label (fallback attribute data-confirm-label). */
      confirmLabel: { type: String, default: '' },
      /** 2.69: Cancel button label (fallback attribute data-confirm-cancel-label). */
      cancelLabel: { type: String, default: '' },
    };

    declare readonly messageValue: string;
    declare readonly blockedValue: boolean;
    declare readonly dangerValue: boolean;
    declare readonly modeValue: string;

    private approved = false;

    override connect(): void {
      (this.element as unknown as Record<symbol, unknown>)[CONFIRM_SUBMIT_PEER_MARK] = true;
    }

    /** 2.23 (audit CS L2): an element whose controller is gone falls back to the kit runtime's window.confirm(). */
    override disconnect(): void {
      delete (this.element as unknown as Record<symbol, unknown>)[CONFIRM_SUBMIT_PEER_MARK];
    }

    confirm(event: Event): void {
      if (this.blockedValue) {
        event.preventDefault();
        return;
      }
      const texts = { ...confirmTexts(this.element, this.identifier), message: this.messageValue.trim() };
      const question = nativeQuestion(texts);
      if (question === '' || this.approved) {
        this.approved = false;
        return;
      }
      if ((this.modeValue || options.mode) !== 'dialog') {
        if (!window.confirm(question)) {
          event.preventDefault();
        }
        return;
      }
      event.preventDefault();
      const submitter = event instanceof SubmitEvent ? event.submitter : null;
      const labels = pageConfirmLabels();
      const asked = texts.message !== '' ? texts.message : texts.title;
      const title = texts.message !== '' && texts.title !== '' ? texts.title : undefined;
      void askConfirm(asked, {
        confirm: texts.confirm || labels.confirm,
        cancel: texts.cancel || labels.cancel,
        title,
        danger: this.dangerValue,
        classes: options.classes,
      }).then((ok) => {
        if (ok) {
          this.replay(submitter);
        }
      });
    }

    /**
     * Replays the held event once with `approved` set. `requestSubmit()` / `click()` dispatch synchronously, so the
     * flag is cleared in `finally` (2.23, audit CK L2): when the replayed event never reaches `confirm()` (another
     * listener stopped it, constraint validation blocked it) the next submit is confirmed again.
     */
    private replay(submitter: HTMLElement | null): void {
      const element = this.element;
      this.approved = true;
      try {
        if (element instanceof HTMLFormElement) {
          element.requestSubmit(submitter instanceof HTMLButtonElement || submitter instanceof HTMLInputElement ? submitter : undefined);
          return;
        }
        (element as HTMLElement).click();
      } finally {
        this.approved = false;
      }
    }
  };
}

export default createConfirmSubmitController();
