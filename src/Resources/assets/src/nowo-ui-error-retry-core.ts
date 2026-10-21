/**
 * Error page self-reload countdown (2.6, REQ-ERROR-001 item 10): a 5xx on GET counts down (default 10 minutes) and
 * reloads the same URL at zero, so a page that failed during a deploy or a short outage recovers without the user.
 * Shared by the kit runtime (`nowo-ui-error-retry.ts`, `[data-nowo-ui-error-retry]`) and the Stimulus peer
 * (`stimulus-peers/error_retry_countdown_controller.ts`, identifier `error-retry-countdown`). The remaining time is
 * written as `m:ss` into a plain element — no live region: announcing every second would flood screen readers.
 */

export const DEFAULT_SECONDS = 600;

/** `m:ss` of a number of seconds (negative → 0:00). */
export function formatClock(seconds: number): string {
  const left = Math.max(0, Math.floor(seconds));
  return `${Math.floor(left / 60)}:${String(left % 60).padStart(2, '0')}`;
}

/** Countdown seconds from a value / attribute (invalid or < 1 → default; fractions floored, at least 1). */
export function parseSeconds(value: unknown, fallback: number = DEFAULT_SECONDS): number {
  const seconds = typeof value === 'number' ? value : Number.parseFloat(String(value ?? ''));
  return Number.isFinite(seconds) && seconds >= 1 ? Math.floor(seconds) : fallback;
}

export interface CountdownOptions {
  /** URL to load at zero ('' = the current location). */
  url: string;
  seconds: number;
  /** Receives the `m:ss` text on start and on every tick. */
  render: (text: string) => void;
  /** Loads the URL (default `window.location.assign`). */
  navigate?: (url: string) => void;
}

export class RetryCountdown {
  private timer: ReturnType<typeof setInterval> | null = null;

  private left = 0;

  constructor(private readonly options: CountdownOptions) {}

  start(): void {
    this.stop();
    this.left = Math.max(1, Math.floor(this.options.seconds));
    this.options.render(formatClock(this.left));
    this.timer = setInterval(() => this.tick(), 1000);
  }

  stop(): void {
    if (this.timer !== null) {
      clearInterval(this.timer);
      this.timer = null;
    }
  }

  running(): boolean {
    return this.timer !== null;
  }

  remaining(): number {
    return this.left;
  }

  tick(): void {
    this.left -= 1;
    this.options.render(formatClock(this.left));
    if (this.left <= 0) {
      this.stop();
      const navigate = this.options.navigate ?? ((url: string) => window.location.assign(url));
      navigate(this.options.url || window.location.href);
    }
  }
}
