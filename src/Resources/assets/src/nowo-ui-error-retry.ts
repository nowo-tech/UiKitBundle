/**
 * Kit runtime of the error page self-reload (2.6, `@NowoUiKit/error/status.html.twig`): every
 * `[data-nowo-ui-error-retry]` element (`data-nowo-ui-error-retry-url`, `data-nowo-ui-error-retry-seconds`) counts
 * down into its `[data-nowo-ui-error-retry-remaining]` child and reloads the URL at zero. Loaded by the kit error
 * layout with the CSP nonce; idempotent (an element binds once). Hosts with Stimulus may use the
 * `error-retry-countdown` peer on their own markup instead.
 */
import { RetryCountdown, parseSeconds } from './nowo-ui-error-retry-core';

export const ROOT = '[data-nowo-ui-error-retry]';

const BOUND = 'data-nowo-ui-error-retry-bound';

/** Starts the countdown of every unbound error-retry element under `root`; returns the started countdowns. */
export function bindNowoUiErrorRetry(root: ParentNode = document, navigate?: (url: string) => void): RetryCountdown[] {
  const started: RetryCountdown[] = [];
  root.querySelectorAll<HTMLElement>(ROOT).forEach((el) => {
    if (el.hasAttribute(BOUND)) {
      return;
    }
    el.setAttribute(BOUND, '');
    const remaining = el.querySelector<HTMLElement>('[data-nowo-ui-error-retry-remaining]');
    const countdown = new RetryCountdown({
      url: el.getAttribute('data-nowo-ui-error-retry-url') ?? '',
      seconds: parseSeconds(el.getAttribute('data-nowo-ui-error-retry-seconds')),
      render: (text) => {
        if (remaining) {
          remaining.textContent = text;
        }
      },
      navigate,
    });
    countdown.start();
    started.push(countdown);
  });

  return started;
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => bindNowoUiErrorRetry());
} else {
  bindNowoUiErrorRetry();
}
