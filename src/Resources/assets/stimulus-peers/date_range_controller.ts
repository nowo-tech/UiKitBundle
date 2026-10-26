/**
 * Optional Stimulus peer (2.15) — date-range filter field: turns the two native date inputs of a
 * `Nowo\UiKitBundle\Form\DateRangeType` field ("From" / "To") into one control of the filter grid that shows the
 * chosen range in the viewer's locale (`Intl.DateTimeFormat`, e.g. `01/02/2026 – 15/02/2026`), opens a range calendar
 * in a popover and has a clear button. The native inputs keep their names: they move into the popover (typed entry
 * stays possible) and submit the same GET query (`created_from=2026-02-01&created_to=2026-02-15`). Without
 * JavaScript, or when the calendar cannot be loaded, the native inputs do the job.
 *
 * Calendar: Cally (`cally` ^0.9, host dependency, MIT, web components `<calendar-range>` / `<calendar-month>`;
 * keyboard: arrows, PageUp / PageDown, Home / End, Enter), imported the first time a popover opens. Popover: the
 * Popover API (`popover="auto"`: top layer, light dismiss, Escape), positioned under (or above) the control and kept
 * whole inside the viewport (over the control when neither side has room); without it, the panel opens inline under
 * the field.
 *
 * Markup: the `nowo_ui_date_range_widget` block (`form/nowo_ui_date_range.html.twig`) — a `role="group"` element with
 * `data-controller="date-range"`, an `aria-label`, and the targets `parts` (the two labelled inputs), `from`, `to`.
 *
 * Identifier: `date-range`
 * Targets: `parts`, `from`, `to`.
 * Values: `placeholder` (text of the empty control, also its accessible name), `labels` (`{from, to, clear, previous,
 *         next, done}`), `locale` (BCP 47 or Symfony locale; default `<html lang>`), `months` (`0` auto: two months
 *         from 40rem, else one), `firstDayOfWeek` (`0` Sunday … `6`, default `1`).
 * Root classes: `is-enhanced`, `is-empty`, `is-open`.
 * Events: `date-range:change` (`detail.from`, `detail.to`: `Y-m-d` or `''`), `date-range:ready` (calendar loaded),
 *         `date-range:unavailable` (`detail.error`: the calendar could not be loaded; the native inputs stay).
 */

import { Controller } from '@hotwired/stimulus';

type Labels = {
  from?: string;
  to?: string;
  clear?: string;
  previous?: string;
  next?: string;
  done?: string;
};

/** The part of Cally's `<calendar-range>` the peer uses. */
export type CalendarRangeElement = HTMLElement & {
  value: string;
  focusedDate?: string | undefined;
  focus: (options?: FocusOptions) => void;
};

const DATE = /^(\d{4})-(\d{2})-(\d{2})$/;

const CALENDAR_ICON =
  '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>';

const chevron = (path: string): string =>
  `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="${path}"/></svg>`;

let sequence = 0;

/** Popover API call that tolerates a state the browser already reached (older engines throw InvalidStateError). */
const popover = (call: () => void): void => {
  try {
    call();
  } catch {
    // already shown / hidden (e.g. by the invoker or a light dismiss)
  }
};

/** A `Y-m-d` day as a UTC date (null when it is not one). */
export function parseDay(value: string): Date | null {
  const match = DATE.exec(value.trim());
  if (!match) {
    return null;
  }
  const date = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])));

  return Number.isNaN(date.getTime()) || date.getUTCDate() !== Number(match[3]) ? null : date;
}

/** BCP 47 tag of a Symfony / BCP 47 locale (`en_GB` → `en-GB`); undefined when empty or not valid. */
export function toLanguageTag(locale: string | null | undefined): string | undefined {
  const tag = (locale ?? '').trim().replace(/_/g, '-');
  if ('' === tag) {
    return undefined;
  }
  try {
    return Intl.getCanonicalLocales(tag)[0];
  } catch {
    return undefined;
  }
}

/** The text of a range in a locale: both days, "From d", "To d" or '' (days as `Y-m-d`). */
export function formatRange(from: string, to: string, locale: string | undefined, labels: Labels = {}): string {
  const format = new Intl.DateTimeFormat(locale, { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC' }) as Intl.DateTimeFormat & {
    formatRange?: (start: Date, end: Date) => string;
  };
  const start = parseDay(from);
  const end = parseDay(to);
  if (start && end) {
    return start.getTime() <= end.getTime() && typeof format.formatRange === 'function'
      ? format.formatRange(start, end)
      : `${format.format(start)} – ${format.format(end)}`;
  }
  if (start) {
    return `${labels.from ?? 'From'} ${format.format(start)}`;
  }
  if (end) {
    return `${labels.to ?? 'To'} ${format.format(end)}`;
  }

  return '';
}

export default class DateRangeController extends Controller<HTMLElement> {
  static override targets = ['parts', 'from', 'to'];

  static override values = {
    placeholder: { type: String, default: '' },
    labels: { type: Object, default: {} },
    locale: { type: String, default: '' },
    months: { type: Number, default: 0 },
    firstDayOfWeek: { type: Number, default: 1 },
  };

  declare readonly partsTarget: HTMLElement;
  declare readonly fromTarget: HTMLInputElement;
  declare readonly toTarget: HTMLInputElement;
  declare readonly hasPartsTarget: boolean;
  declare readonly hasFromTarget: boolean;
  declare readonly hasToTarget: boolean;
  declare readonly placeholderValue: string;
  declare readonly labelsValue: Labels;
  declare readonly localeValue: string;
  declare readonly monthsValue: number;
  declare readonly firstDayOfWeekValue: number;

  private trigger: HTMLButtonElement | null = null;
  private valueText: HTMLElement | null = null;
  private clearButton: HTMLButtonElement | null = null;
  private icon: HTMLElement | null = null;
  private panel: HTMLElement | null = null;
  private calendarHost: HTMLElement | null = null;
  private calendar: CalendarRangeElement | null = null;
  private loading: Promise<void> | null = null;
  /** Repositions the open panel when its size changes (Cally renders its months after the first measure). */
  private sizeObserver: ResizeObserver | null = null;
  private readonly cleanups: Array<() => void> = [];

  override connect(): void {
    if (!this.hasPartsTarget || !this.hasFromTarget || !this.hasToTarget || this.trigger) {
      return;
    }
    const id = this.element.id || `nowo-ui-date-range-${++sequence}`;
    const labels = this.labels();

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = `${this.fromTarget.className} nowo-ui-date-range__trigger`.trim();
    trigger.setAttribute('aria-haspopup', 'dialog');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', `${id}-panel`);
    trigger.dataset.dateRangeTrigger = '';
    const valueText = document.createElement('span');
    valueText.className = 'nowo-ui-date-range__value';
    trigger.append(valueText);

    const icon = document.createElement('span');
    icon.className = 'nowo-ui-date-range__icon';
    icon.innerHTML = CALENDAR_ICON;

    const clear = document.createElement('button');
    clear.type = 'button';
    clear.className = 'nowo-ui-date-range__clear';
    clear.textContent = '×';
    clear.dataset.dateRangeClear = '';

    const panel = document.createElement('div');
    panel.id = `${id}-panel`;
    panel.className = 'nowo-ui-date-range__panel';
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-label', this.name());
    panel.tabIndex = -1;
    const calendarHost = document.createElement('div');
    calendarHost.className = 'nowo-ui-date-range__calendar';
    const footer = document.createElement('div');
    footer.className = 'nowo-ui-date-range__footer';
    const panelClear = document.createElement('button');
    panelClear.type = 'button';
    panelClear.className = 'nowo-ui-btn nowo-ui-btn--ghost nowo-ui-btn--sm';
    panelClear.textContent = labels.clear;
    panelClear.dataset.dateRangePanelClear = '';
    const done = document.createElement('button');
    done.type = 'button';
    done.className = 'nowo-ui-btn nowo-ui-btn--primary nowo-ui-btn--sm';
    done.textContent = labels.done;
    done.dataset.dateRangeDone = '';
    footer.append(panelClear, done);
    panel.append(calendarHost, this.partsTarget, footer);
    if (this.supportsPopover(panel)) {
      panel.setAttribute('popover', 'auto');
      // The trigger is the popover's invoker: it toggles the panel natively and a click on it is not a light dismiss.
      trigger.popoverTargetElement = panel;
    } else {
      panel.hidden = true;
    }

    this.element.append(trigger, icon, clear, panel);
    this.element.classList.add('is-enhanced');
    this.trigger = trigger;
    this.valueText = valueText;
    this.clearButton = clear;
    this.icon = icon;
    this.panel = panel;
    this.calendarHost = calendarHost;

    this.listen(trigger, 'click', () => {
      if (!panel.hasAttribute('popover')) {
        this.toggle();
      }
    });
    this.listen(trigger, 'keydown', (event) => {
      if ('ArrowDown' === (event as KeyboardEvent).key) {
        event.preventDefault();
        this.open();
      }
    });
    this.listen(clear, 'click', () => {
      this.clear();
      trigger.focus();
    });
    this.listen(panelClear, 'click', () => this.clear());
    this.listen(done, 'click', () => this.close(true));
    this.listen(panel, 'keydown', (event) => {
      if ('Escape' === (event as KeyboardEvent).key) {
        event.preventDefault();
        this.close(true);
      }
    });
    this.listen(panel, 'toggle', (event) => {
      if ('open' === (event as Event & { newState?: string }).newState) {
        this.opened();
      } else {
        this.closed(panel.contains(document.activeElement));
      }
    });
    for (const input of [this.fromTarget, this.toTarget]) {
      this.listen(input, 'change', () => this.inputsChanged());
      this.listen(input, 'input', () => this.inputsChanged());
    }
    this.render();
  }

  override disconnect(): void {
    while (this.cleanups.length > 0) {
      this.cleanups.pop()?.();
    }
    this.sizeObserver?.disconnect();
    this.sizeObserver = null;
    if (!this.trigger) {
      return;
    }
    if (this.hasPartsTarget) {
      this.element.prepend(this.partsTarget);
    }
    for (const node of [this.trigger, this.icon, this.clearButton, this.panel]) {
      node?.remove();
    }
    this.element.classList.remove('is-enhanced', 'is-empty', 'is-open');
    this.trigger = null;
    this.valueText = null;
    this.clearButton = null;
    this.icon = null;
    this.panel = null;
    this.calendarHost = null;
    this.calendar = null;
  }

  /** Opens the popover, loading the calendar the first time. */
  open(): void {
    const panel = this.panel;
    if (!panel || this.isOpen()) {
      return;
    }
    if (panel.hasAttribute('popover')) {
      popover(() => panel.showPopover());
    } else {
      panel.hidden = false;
    }
    this.opened();
  }

  /** Open state, listeners, position and the calendar (once per opening; the popover's `toggle` calls it too). */
  private opened(): void {
    if (this.isOpen()) {
      return;
    }
    if (!this.panel?.hasAttribute('popover')) {
      this.listen(document, 'pointerdown', this.outside, true);
    }
    this.element.classList.add('is-open');
    this.trigger?.setAttribute('aria-expanded', 'true');
    this.position();
    this.listen(window, 'resize', this.reposition);
    this.listen(window, 'scroll', this.reposition, true);
    if (this.panel && typeof ResizeObserver !== 'undefined') {
      this.sizeObserver ??= new ResizeObserver(this.reposition);
      this.sizeObserver.observe(this.panel);
    }
    this.loading ??= this.loadCalendar().finally(() => {
      this.loading = null;
    });
    void this.loading.then(() => this.focusInside());
  }

  /** Closes the popover; `focusTrigger` returns the focus to the control. */
  close(focusTrigger = false): void {
    const panel = this.panel;
    if (!panel || !this.isOpen()) {
      return;
    }
    if (panel.hasAttribute('popover')) {
      popover(() => panel.hidePopover());
      // Browsers fire `toggle` asynchronously; finish now so the state is right at once (closed() is idempotent).
    } else {
      panel.hidden = true;
    }
    this.closed(focusTrigger);
  }

  toggle(): void {
    if (this.isOpen()) {
      this.close(true);
    } else {
      this.open();
    }
  }

  /** Empties both days (and tells the form: `input` / `change` on each input). */
  clear(): void {
    this.setDays('', '');
  }

  /** The chosen days (`Y-m-d` or ''). */
  get range(): { from: string; to: string } {
    return { from: this.hasFromTarget ? this.fromTarget.value : '', to: this.hasToTarget ? this.toTarget.value : '' };
  }

  /** Loads Cally (dynamic import, so pages without an open date range never fetch it). */
  protected async calendarFactory(): Promise<void> {
    await import('cally');
  }

  private isOpen(): boolean {
    return this.element.classList.contains('is-open');
  }

  private closed(focusTrigger: boolean): void {
    if (!this.isOpen()) {
      return;
    }
    this.element.classList.remove('is-open');
    this.trigger?.setAttribute('aria-expanded', 'false');
    this.unlisten(this.reposition);
    this.unlisten(this.outside);
    this.sizeObserver?.disconnect();
    if (focusTrigger) {
      this.trigger?.focus();
    }
  }

  private readonly outside = (event: Event): void => {
    const target = event.target;
    if (target instanceof Node && !this.element.contains(target)) {
      this.close();
    }
  };

  private readonly reposition = (): void => {
    this.position();
  };

  /**
   * Fixed position under the control, above it when only the room above fits; when neither side fits, the panel is
   * moved up until it is whole inside the viewport (it may cover the control). Its height is capped to the viewport
   * (minus the margins) so a panel taller than that scrolls inside; horizontally it is kept between the margins.
   */
  private position(): void {
    const panel = this.panel;
    const trigger = this.trigger;
    if (!panel || !trigger || !panel.hasAttribute('popover')) {
      return;
    }
    const gap = 4;
    const margin = 8;
    const viewportWidth = document.documentElement.clientWidth || window.innerWidth;
    const viewportHeight = window.innerHeight;
    panel.style.maxHeight = `${Math.max(0, viewportHeight - 2 * margin)}px`;
    const box = trigger.getBoundingClientRect();
    const width = panel.offsetWidth;
    const height = Math.min(panel.offsetHeight, viewportHeight - 2 * margin);
    const below = box.bottom + gap;
    const above = box.top - gap - height;
    let top = below;
    if (below + height > viewportHeight - margin) {
      top = above >= margin ? above : viewportHeight - margin - height;
    }
    panel.style.left = `${Math.round(clamp(box.left, margin, viewportWidth - margin - width))}px`;
    panel.style.top = `${Math.round(clamp(top, margin, viewportHeight - margin - height))}px`;
  }

  private async loadCalendar(): Promise<void> {
    if (this.calendar || !this.calendarHost) {
      return;
    }
    try {
      await this.calendarFactory();
    } catch (error) {
      this.dispatch('unavailable', { detail: { error } });

      return;
    }
    const host = this.calendarHost;
    if (!host || !host.isConnected || this.calendar) {
      return;
    }
    const labels = this.labels();
    const calendar = document.createElement('calendar-range') as CalendarRangeElement;
    const months = this.monthCount();
    calendar.setAttribute('months', String(months));
    calendar.setAttribute('first-day-of-week', String(this.firstDayOfWeekValue));
    const locale = this.locale();
    if (locale) {
      calendar.setAttribute('locale', locale);
    }
    calendar.innerHTML =
      `<span slot="previous" class="nowo-ui-date-range__nav">${chevron('M15 18l-6-6 6-6')}<span class="visually-hidden"></span></span>` +
      `<span slot="next" class="nowo-ui-date-range__nav">${chevron('M9 18l6-6-6-6')}<span class="visually-hidden"></span></span>`;
    const [previous, next] = [...calendar.querySelectorAll<HTMLElement>('.visually-hidden')];
    if (previous) {
      previous.textContent = labels.previous;
    }
    if (next) {
      next.textContent = labels.next;
    }
    const grid = document.createElement('div');
    grid.className = 'nowo-ui-date-range__months';
    for (let offset = 0; offset < months; offset++) {
      const month = document.createElement('calendar-month');
      if (offset > 0) {
        month.setAttribute('offset', String(offset));
      }
      grid.append(month);
    }
    calendar.append(grid);
    this.syncCalendar(calendar);
    calendar.addEventListener('change', () => {
      const [from = '', to = ''] = calendar.value.split('/');
      this.setDays(from, to);
      this.close(true);
    });
    host.append(calendar);
    this.calendar = calendar;
    this.position();
    this.dispatch('ready', { detail: { calendar } });
  }

  private focusInside(): void {
    if (!this.isOpen()) {
      return;
    }
    if (this.calendar) {
      this.calendar.focus();

      return;
    }
    (this.fromTarget.value ? this.toTarget : this.fromTarget).focus();
  }

  private setDays(from: string, to: string): void {
    const changed = this.fromTarget.value !== from || this.toTarget.value !== to;
    this.fromTarget.value = from;
    this.toTarget.value = to;
    if (changed) {
      for (const input of [this.fromTarget, this.toTarget]) {
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }
    this.inputsChanged();
  }

  private inputsChanged(): void {
    if (this.calendar) {
      this.syncCalendar(this.calendar);
    }
    this.render();
    this.dispatch('change', { detail: this.range });
  }

  private syncCalendar(calendar: CalendarRangeElement): void {
    const { from, to } = this.range;
    const start = parseDay(from);
    const end = parseDay(to);
    calendar.value = start && end && start.getTime() <= end.getTime() ? `${from}/${to}` : '';
    const focused = start ? from : end ? to : '';
    if ('' !== focused) {
      calendar.setAttribute('focused-date', focused);
    }
  }

  /** Trigger text, accessible names and the `is-empty` state. */
  private render(): void {
    if (!this.trigger || !this.valueText || !this.clearButton) {
      return;
    }
    const name = this.name();
    const labels = this.labels();
    const { from, to } = this.range;
    const text = formatRange(from, to, this.locale(), labels);
    this.valueText.textContent = '' === text ? name : text;
    this.trigger.setAttribute('aria-label', '' === text ? name : `${name}: ${text}`);
    this.trigger.title = '' === text ? name : `${name}: ${text}`;
    this.clearButton.setAttribute('aria-label', `${labels.clear}: ${name}`);
    this.clearButton.title = labels.clear;
    this.clearButton.hidden = '' === text;
    this.element.classList.toggle('is-empty', '' === text);
  }

  private name(): string {
    return this.placeholderValue || this.element.getAttribute('aria-label') || '';
  }

  private labels(): Required<Labels> {
    const labels = this.labelsValue ?? {};

    return {
      from: labels.from ?? 'From',
      to: labels.to ?? 'To',
      clear: labels.clear ?? 'Clear',
      previous: labels.previous ?? 'Previous month',
      next: labels.next ?? 'Next month',
      done: labels.done ?? 'Done',
    };
  }

  private locale(): string | undefined {
    return toLanguageTag(this.localeValue) ?? toLanguageTag(document.documentElement.lang);
  }

  private monthCount(): number {
    const months = Math.trunc(this.monthsValue);
    if (months >= 1 && months <= 3) {
      return months;
    }

    return typeof window.matchMedia === 'function' && window.matchMedia('(min-width: 40rem)').matches ? 2 : 1;
  }

  private supportsPopover(element: HTMLElement): boolean {
    return typeof element.showPopover === 'function' && typeof element.hidePopover === 'function';
  }

  private listen(target: EventTarget, type: string, handler: (event: Event) => void, capture = false): void {
    target.addEventListener(type, handler, capture);
    const cleanup = (): void => target.removeEventListener(type, handler, capture);
    (cleanup as (() => void) & { handler?: unknown }).handler = handler;
    this.cleanups.push(cleanup);
  }

  private unlisten(handler: (event: Event) => void): void {
    for (let index = this.cleanups.length - 1; index >= 0; index--) {
      const cleanup = this.cleanups[index] as (() => void) & { handler?: unknown };
      if (cleanup.handler === handler) {
        cleanup();
        this.cleanups.splice(index, 1);
      }
    }
  }
}

/** `value` kept between `min` and `max`; `min` wins when the range is empty (a box larger than the room). */
function clamp(value: number, min: number, max: number): number {
  return Math.max(min, Math.min(value, max));
}
