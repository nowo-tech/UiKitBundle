/**
 * Optional Stimulus peer (2.6) — error page self-reload countdown (the apps' `error_retry_countdown` controller).
 * The kit error page uses the IIFE runtime `nowo-ui-error-retry.js` instead; this peer serves host markup:
 *
 *   <p data-controller="error-retry-countdown" data-error-retry-countdown-url-value="/orders"
 *      data-error-retry-countdown-seconds-value="600">Reloads in <span data-error-retry-countdown-target="remaining">10:00</span></p>
 *
 * Identifier: `error-retry-countdown` · Values: `url` (default the current location), `seconds` (default 600) ·
 * Target: `remaining` (`m:ss`). No live region: announcing every second would flood screen readers.
 */
import { Controller } from '@hotwired/stimulus';

import { DEFAULT_SECONDS, RetryCountdown, parseSeconds } from '../src/nowo-ui-error-retry-core';

export default class ErrorRetryCountdownController extends Controller<HTMLElement> {
  static override values = { url: String, seconds: { type: Number, default: DEFAULT_SECONDS } };

  static override targets = ['remaining'];

  declare readonly urlValue: string;

  declare readonly secondsValue: number;

  declare readonly hasRemainingTarget: boolean;

  declare readonly remainingTarget: HTMLElement;

  private countdown: RetryCountdown | null = null;

  override connect(): void {
    this.countdown = new RetryCountdown({
      url: this.urlValue,
      seconds: parseSeconds(this.secondsValue),
      render: (text) => {
        if (this.hasRemainingTarget) {
          this.remainingTarget.textContent = text;
        }
      },
      navigate: (url) => this.navigate(url),
    });
    this.countdown.start();
  }

  override disconnect(): void {
    this.countdown?.stop();
    this.countdown = null;
  }

  /** Loads the URL at zero (overridable in a subclass / spied in tests). */
  protected navigate(url: string): void {
    window.location.assign(url);
  }
}
