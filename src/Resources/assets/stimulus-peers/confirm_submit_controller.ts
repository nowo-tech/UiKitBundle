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
 */

import { Controller } from '@hotwired/stimulus';

import { askConfirm, pageConfirmLabels, type ConfirmClasses } from './ask_confirm';

export type ConfirmMode = 'native' | 'dialog';

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
    };

    declare readonly messageValue: string;
    declare readonly blockedValue: boolean;
    declare readonly dangerValue: boolean;
    declare readonly modeValue: string;

    private approved = false;

    confirm(event: Event): void {
      if (this.blockedValue) {
        event.preventDefault();
        return;
      }
      const message = this.messageValue.trim();
      if (message === '' || this.approved) {
        this.approved = false;
        return;
      }
      if ((this.modeValue || options.mode) !== 'dialog') {
        if (!window.confirm(message)) {
          event.preventDefault();
        }
        return;
      }
      event.preventDefault();
      const submitter = event instanceof SubmitEvent ? event.submitter : null;
      void askConfirm(message, { ...pageConfirmLabels(), danger: this.dangerValue, classes: options.classes }).then((ok) => {
        if (ok) {
          this.approved = true;
          this.replay(submitter);
        }
      });
    }

    private replay(submitter: HTMLElement | null): void {
      const element = this.element;
      if (element instanceof HTMLFormElement) {
        element.requestSubmit(submitter instanceof HTMLButtonElement || submitter instanceof HTMLInputElement ? submitter : undefined);
        return;
      }
      (element as HTMLElement).click();
    }
  };
}

export default createConfirmSubmitController();
