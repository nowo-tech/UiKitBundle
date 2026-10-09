/**
 * Optional Stimulus peer — `window.confirm()` before a submit, without inline
 * `onsubmit=` / `onclick=` handlers (blocked by a strict CSP `script-src`).
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
 * `data-confirm-submit-blocked-value="true"` always cancels (e.g. last owner cannot be removed).
 * An empty message lets the event through unchanged.
 */

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static values = {
    message: String,
    /** When true, always prevent submit (e.g. last owner cannot be removed). */
    blocked: { type: Boolean, default: false },
  };

  declare readonly messageValue: string;
  declare readonly blockedValue: boolean;

  confirm(event: Event): void {
    if (this.blockedValue) {
      event.preventDefault();
      return;
    }
    const message = this.messageValue.trim();
    if (message === '') {
      return;
    }
    if (!window.confirm(message)) {
      event.preventDefault();
    }
  }
}
