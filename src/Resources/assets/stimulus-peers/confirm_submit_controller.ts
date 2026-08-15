/**
 * Optional Stimulus peer — `window.confirm()` on form submit (CSP-safe, no inline onsubmit).
 *
 * Identifier: `confirm-submit`
 *
 *   <form data-controller="confirm-submit"
 *         data-confirm-submit-message-value="Delete?"
 *         data-action="submit->confirm-submit#confirm">
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
