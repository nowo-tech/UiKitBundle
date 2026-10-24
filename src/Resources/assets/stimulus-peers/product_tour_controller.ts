/**
 * Optional Stimulus peer (2.13) — a guided product tour of the current page (driver.js, ported from Symfony Beacon).
 *
 * Markup: `partials/_tour.html.twig` (`nowo_ui_partial('tour')`): the root with the steps and a right-edge
 * "Tour this page" tab (`product-tour#start`). Steps are `{element?, popover: {title, description, side?, align?}}`;
 * a step whose `element` selector matches nothing on the page is skipped, one without `element` is centred.
 *
 * driver.js (`driver.js` ^1.3, host dependency; checked against 1.8 and 1.10) is imported on demand, the first time a
 * tour starts; the host loads its stylesheet (`import 'driver.js/dist/driver.css'`). Reduced motion (`prefers-reduced-motion: reduce` or
 * `[data-motion="reduce"]` on <html>) turns driver.js animation off. A step inside a closed <details> (a user menu)
 * opens it while highlighted; the tour closes what it opened when it ends.
 *
 * "Seen" state lives in the browser: localStorage `storageKey` (default `nowo-ui-tour-seen`) holds a JSON map
 * `{"<tour key>": <timestamp>}`. The tour starts on connect when `autoStart` is on and its key is not in the map (or
 * always with `force`); finishing or closing it writes the key — also when it is closed during driver.js's opening
 * animation (driver.js then never calls `onDestroyed`, so the peer does not rely on it). Storage errors (private mode, quota) are ignored:
 * the tour then simply auto-starts again next time. With a `seenUrl` the peer also sends `{"key": "…"}` once (JSON,
 * `X-CSRF-TOKEN` header, `seenMethod` default POST, keepalive); a 403 refreshes the token once from `seenTokenUrl`
 * (`{"token": "…"}`) — hosts that keep the state server-side (and decide `autoStart` themselves) use it.
 *
 * Identifier: `product-tour`
 * Targets: `tab` (optional).
 * Values: `key`, `steps` (array), `autoStart` (default true), `force`, `forceParam` (query parameter removed from the
 *         URL when the tour ends), `storageKey`, `seenUrl`, `seenToken`, `seenTokenUrl`, `seenMethod` (default POST),
 *         `labels` (`{next, previous, done, close, progress}`), `html` (titles / descriptions are trusted HTML;
 *         default: escaped text), `popoverClass` (default `nowo-ui-tour-popover`).
 * Actions: `product-tour#start` (the tab; replays a seen tour; no-op while one runs), `product-tour#stop`.
 * Root attributes: `data-tour-active` while a tour runs.
 * Events: `product-tour:started` (`detail.key`, `detail.steps` = shown steps), `product-tour:ended` (`detail.key`,
 *         `detail.completed`: the last step was reached), `product-tour:save-failed` (`detail.key`),
 *         `product-tour:unavailable` (`detail.key`, `detail.error`: driver.js could not be loaded).
 */

import { Controller } from '@hotwired/stimulus';
import type { Config, DriveStep, Driver } from 'driver.js';

export const PRODUCT_TOUR_STORAGE_KEY = 'nowo-ui-tour-seen';

export type TourPopover = {
  title?: string;
  description?: string;
  side?: string;
  align?: string;
};

export type TourStepData = {
  element?: string | null;
  popover?: TourPopover;
};

type Labels = {
  next?: string;
  previous?: string;
  done?: string;
  close?: string;
  progress?: string;
};

type Side = 'top' | 'right' | 'bottom' | 'left';
type Align = 'start' | 'center' | 'end';

const SIDES: readonly string[] = ['top', 'right', 'bottom', 'left'];
const ALIGNS: readonly string[] = ['start', 'center', 'end'];

/** Loads driver.js on demand (overridable in tests / by a subclass). */
export type DriverFactory = (config: Config) => Driver;

/** One running tour: its driver, the user "close" (counts as seen) and the idempotent end. */
type TourSession = {
  driver: Driver;
  close: () => void;
  end: (seen: boolean) => void;
};

/** Keys of the seen map in `storageKey` (an empty map when storage is unavailable or holds garbage). */
export function seenTours(storageKey: string = PRODUCT_TOUR_STORAGE_KEY): Record<string, number> {
  try {
    const raw = window.localStorage.getItem(storageKey);
    const parsed: unknown = raw ? JSON.parse(raw) : {};

    return parsed !== null && typeof parsed === 'object' && !Array.isArray(parsed) ? (parsed as Record<string, number>) : {};
  } catch {
    return {};
  }
}

/** Adds (or with `seen: false` removes) a tour key in the seen map; false when storage is unavailable. */
export function markTourSeen(key: string, seen = true, storageKey: string = PRODUCT_TOUR_STORAGE_KEY): boolean {
  try {
    const map = seenTours(storageKey);
    if (seen) {
      map[key] = Date.now();
    } else {
      delete map[key];
    }
    window.localStorage.setItem(storageKey, JSON.stringify(map));

    return true;
  } catch {
    return false;
  }
}

const escapeHtml = (text: string): string =>
  text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');

export default class ProductTourController extends Controller<HTMLElement> {
  static override targets = ['tab'];

  static override values = {
    key: { type: String, default: '' },
    steps: { type: Array, default: [] },
    autoStart: { type: Boolean, default: true },
    force: { type: Boolean, default: false },
    forceParam: { type: String, default: '' },
    storageKey: { type: String, default: PRODUCT_TOUR_STORAGE_KEY },
    seenUrl: { type: String, default: '' },
    seenToken: { type: String, default: '' },
    seenTokenUrl: { type: String, default: '' },
    seenMethod: { type: String, default: 'POST' },
    labels: { type: Object, default: {} },
    html: { type: Boolean, default: false },
    popoverClass: { type: String, default: 'nowo-ui-tour-popover' },
  };

  declare readonly keyValue: string;
  declare readonly stepsValue: TourStepData[];
  declare readonly autoStartValue: boolean;
  declare readonly forceValue: boolean;
  declare readonly forceParamValue: string;
  declare readonly storageKeyValue: string;
  declare readonly seenUrlValue: string;
  declare seenTokenValue: string;
  declare readonly seenTokenUrlValue: string;
  declare readonly seenMethodValue: string;
  declare readonly labelsValue: Labels;
  declare readonly htmlValue: boolean;
  declare readonly popoverClassValue: string;

  private active: TourSession | null = null;
  private starting: Promise<void> | null = null;
  private frame = 0;
  /** The server was told already (one request per page). */
  private posted = false;
  /** Closed <details> the tour opened for a highlight, closed again at the end. */
  private opened: HTMLDetailsElement[] = [];
  /** disconnect() tears the tour down: not a user "close", nothing is marked seen. */
  private tearingDown = false;

  override connect(): void {
    if (this.forceValue || (this.autoStartValue && !this.isSeen())) {
      this.frame = requestAnimationFrame(() => {
        void this.start();
      });
    }
  }

  override disconnect(): void {
    cancelAnimationFrame(this.frame);
    const active = this.active;
    if (active) {
      this.tearingDown = true;
      active.driver.destroy();
      active.end(false);
      this.tearingDown = false;
    }
    this.finish();
  }

  /** Whether the tour key is in the browser's seen map. */
  isSeen(): boolean {
    return '' !== this.keyValue && undefined !== seenTours(this.storageKeyValue)[this.keyValue];
  }

  /** Starts (or replays) the tour; no-op while it runs. Resolves once driver.js is showing the first step. */
  start(): Promise<void> {
    if (this.active?.driver.isActive()) {
      return Promise.resolve();
    }
    this.starting ??= this.run().finally(() => {
      this.starting = null;
    });

    return this.starting;
  }

  /** Closes a running tour (as the close button does: it counts as seen). */
  stop(): void {
    this.active?.close();
  }

  /** The steps with an element on the page (or none: centred), normalised for driver.js. */
  steps(): DriveStep[] {
    const steps: DriveStep[] = [];
    for (const step of this.stepsValue) {
      if (!step?.popover) {
        continue;
      }
      const popover: NonNullable<DriveStep['popover']> = {
        title: this.text(step.popover.title),
        description: this.text(step.popover.description),
      };
      if (step.popover.side && SIDES.includes(step.popover.side)) {
        popover.side = step.popover.side as Side;
      }
      if (step.popover.align && ALIGNS.includes(step.popover.align)) {
        popover.align = step.popover.align as Align;
      }
      if (step.element) {
        if (!this.exists(step.element)) {
          continue;
        }
        steps.push({ element: step.element, popover });
        continue;
      }
      steps.push({ popover });
    }

    return steps;
  }

  /** Loads driver.js (dynamic import, so pages without a tour never fetch it). */
  protected async driverFactory(): Promise<DriverFactory> {
    const module = await import('driver.js');

    return module.driver;
  }

  private async run(): Promise<void> {
    const steps = this.steps();
    if (0 === steps.length) {
      // Nothing to show here (every anchor missing): mark it seen so it does not try again on every visit.
      this.markSeen();
      this.clearForceParam();

      return;
    }
    let factory: DriverFactory;
    try {
      factory = await this.driverFactory();
    } catch (error) {
      this.dispatch('unavailable', { detail: { key: this.keyValue, error } });

      return;
    }
    if (!this.element.isConnected || this.active?.driver.isActive()) {
      return;
    }
    const labels = this.labelsValue ?? {};
    let completed = false;
    let closing = false;
    let ended = false;
    // Ends the tour once, whichever comes first: our own close() or driver.js's onDestroyed. driver.js (1.8 to 1.10)
    // skips onDestroyed when the tour is destroyed before its first highlight settled (closed during the opening
    // animation), so a close never depends on it.
    const end = (seen: boolean): void => {
      if (ended) {
        return;
      }
      ended = true;
      if (this.active === session) {
        this.active = null;
      }
      this.finish();
      if (this.tearingDown || !seen) {
        return;
      }
      this.markSeen();
      this.clearForceParam();
      this.dispatch('ended', { detail: { key: this.keyValue, completed } });
    };
    // A user close (button, Esc, overlay, Done, stop()): destroy without hooks (driver.destroy() never re-runs
    // onDestroyStarted; the guard covers a driver that would), then end as seen.
    const close = (instance: Driver): void => {
      if (closing || ended) {
        return;
      }
      closing = true;
      completed = !instance.hasNextStep();
      instance.destroy();
      end(true);
    };
    const config: Config = {
      steps,
      animate: !this.reducedMotion(),
      smoothScroll: !this.reducedMotion(),
      allowClose: true,
      showProgress: steps.length > 1,
      popoverClass: this.popoverClassValue,
      nextBtnText: labels.next || 'Next',
      prevBtnText: labels.previous || 'Previous',
      doneBtnText: labels.done || 'Done',
      progressText: labels.progress || '{{current}} / {{total}}',
      onPopoverRender: (popover) => {
        const close = labels.close ?? '';
        if ('' !== close) {
          popover.closeButton.setAttribute('aria-label', close);
          popover.closeButton.title = close;
        }
      },
      onHighlightStarted: (element) => this.reveal(element),
      onDestroyStarted: (_element, _step, { driver }) => close(driver),
      // Reached without onDestroyStarted only when driver.js destroys the tour itself; 1.9+ does that when another
      // driver instance starts (1.10 reason "replaced": not a user close, not seen).
      onDestroyed: (_element, _step, options) => end('replaced' !== (options as { reason?: unknown }).reason),
    };
    const driver = factory(config);
    const session: TourSession = { driver, close: () => close(driver), end };
    this.active = session;
    this.element.setAttribute('data-tour-active', '');
    driver.drive();
    this.dispatch('started', { detail: { key: this.keyValue, steps } });
  }

  private text(value: string | undefined): string {
    const text = value ?? '';

    return this.htmlValue ? text : escapeHtml(text);
  }

  private exists(selector: string): boolean {
    try {
      return null !== document.querySelector(selector);
    } catch {
      return false;
    }
  }

  private reducedMotion(): boolean {
    return (
      'reduce' === document.documentElement.dataset.motion ||
      true === window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
    );
  }

  /** Opens the closed <details> ancestors of a highlighted element (e.g. a user menu). */
  private reveal(element: Element | undefined): void {
    let details = element?.closest('details');
    while (details instanceof HTMLDetailsElement) {
      if (!details.open) {
        details.open = true;
        this.opened.push(details);
      }
      details = details.parentElement?.closest('details');
    }
  }

  private finish(): void {
    this.element.removeAttribute('data-tour-active');
    for (const details of this.opened.reverse()) {
      details.open = false;
    }
    this.opened = [];
  }

  private markSeen(): void {
    if ('' === this.keyValue) {
      return;
    }
    markTourSeen(this.keyValue, true, this.storageKeyValue);
    void this.save();
  }

  /** Drops `forceParam` from the URL so a reload does not force the tour again. */
  private clearForceParam(): void {
    if ('' === this.forceParamValue) {
      return;
    }
    try {
      const url = new URL(window.location.href);
      if (!url.searchParams.has(this.forceParamValue)) {
        return;
      }
      url.searchParams.delete(this.forceParamValue);
      window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}${url.hash}`);
    } catch {
      // Not worth failing the tour for.
    }
  }

  /** Tells the host once per page; a stale CSRF token is refreshed once. */
  private async save(): Promise<void> {
    if ('' === this.seenUrlValue || this.posted) {
      return;
    }
    this.posted = true;
    try {
      let response = await this.send();
      if (403 === response.status && (await this.refreshToken())) {
        response = await this.send();
      }
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
    } catch {
      this.posted = false;
      this.dispatch('save-failed', { detail: { key: this.keyValue } });
    }
  }

  private send(): Promise<Response> {
    return fetch(this.seenUrlValue, {
      method: this.seenMethodValue,
      credentials: 'same-origin',
      keepalive: true,
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': this.seenTokenValue,
      },
      body: JSON.stringify({ key: this.keyValue }),
    });
  }

  private async refreshToken(): Promise<boolean> {
    if ('' === this.seenTokenUrlValue) {
      return false;
    }
    try {
      const response = await fetch(this.seenTokenUrlValue, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const token = response.ok ? ((await response.json()) as { token?: unknown }).token : null;
      if ('string' !== typeof token || '' === token) {
        return false;
      }
      this.seenTokenValue = token;

      return true;
    } catch {
      return false;
    }
  }
}
