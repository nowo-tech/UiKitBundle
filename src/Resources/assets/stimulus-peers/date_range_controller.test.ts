import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import DateRangeController, { type CalendarRangeElement, formatRange, parseDay, toLanguageTag } from './date_range_controller';

const settle = async (): Promise<void> => {
  for (let i = 0; i < 5; i++) {
    await new Promise((r) => setTimeout(r, 0));
  }
};

/** A stand-in for Cally's `<calendar-range>` (value "from/to", focus()). */
class FakeCalendarRange extends HTMLElement {
  value = '';
  focusCalls = 0;

  override focus(): void {
    this.focusCalls++;
  }
}

if (!customElements.get('fake-calendar-range-defined')) {
  customElements.define('calendar-range', FakeCalendarRange);
  customElements.define('calendar-month', class extends HTMLElement {});
  customElements.define('fake-calendar-range-defined', class extends HTMLElement {});
}

class FakeCalendarController extends DateRangeController {
  protected override calendarFactory(): Promise<void> {
    return Promise.resolve();
  }
}

class BrokenCalendarController extends DateRangeController {
  protected override calendarFactory(): Promise<void> {
    return Promise.reject(new Error('no cally'));
  }
}

const proto = HTMLElement.prototype as HTMLElement & { showPopover?: () => void; hidePopover?: () => void };
const original = { show: proto.showPopover, hide: proto.hidePopover };

/** Popover API stub: show / hide toggle `:popover-open` as a data flag and fire `toggle` like browsers (async). */
function stubPopover(): void {
  proto.showPopover = function (this: HTMLElement): void {
    this.dataset.open = '1';
    setTimeout(() => this.dispatchEvent(Object.assign(new Event('toggle'), { newState: 'open' })), 0);
  };
  proto.hidePopover = function (this: HTMLElement): void {
    delete this.dataset.open;
    setTimeout(() => this.dispatchEvent(Object.assign(new Event('toggle'), { newState: 'closed' })), 0);
  };
}

function noPopover(): void {
  Reflect.deleteProperty(proto, 'showPopover');
  Reflect.deleteProperty(proto, 'hidePopover');
}

const field = (identifier = 'date-range', values = '', from = '', to = ''): string => `
  <form>
    <div id="collected" class="nowo-ui-date-range" role="group" aria-label="Collection date" data-nowo-ui-filter-group
         data-controller="${identifier}" data-${identifier}-placeholder-value="Collection date"
         data-${identifier}-labels-value='{"from":"Desde","to":"Hasta","clear":"Borrar","previous":"Anterior","next":"Siguiente","done":"Listo"}'
         ${values}>
      <div class="nowo-ui-date-range__parts" data-${identifier}-target="parts">
        <label for="collected_from"><span>Desde</span><input type="date" id="collected_from" name="collected_from" class="nowo-ui-input" value="${from}" data-${identifier}-target="from"></label>
        <label for="collected_to"><span>Hasta</span><input type="date" id="collected_to" name="collected_to" class="nowo-ui-input" value="${to}" data-${identifier}-target="to"></label>
      </div>
    </div>
  </form>`;

/** The day as the runtime's Intl formats it (the container may ship without full ICU data). */
const day = (value: string, locale = 'es'): string =>
  new Intl.DateTimeFormat(locale, { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${value}T00:00:00Z`));
const span = (from: string, to: string, locale = 'es'): string =>
  (new Intl.DateTimeFormat(locale, { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC' }) as Intl.DateTimeFormat & { formatRange: (a: Date, b: Date) => string }).formatRange(new Date(`${from}T00:00:00Z`), new Date(`${to}T00:00:00Z`));

let app: Application;
const root = (): HTMLElement => document.querySelector('.nowo-ui-date-range') as HTMLElement;
const trigger = (): HTMLButtonElement => root().querySelector('[data-date-range-trigger]') as HTMLButtonElement;
const panel = (): HTMLElement => root().querySelector('.nowo-ui-date-range__panel') as HTMLElement;
const input = (name: string): HTMLInputElement => document.querySelector(`input[name="${name}"]`) as HTMLInputElement;
const calendar = (): CalendarRangeElement & FakeCalendarRange => root().querySelector('calendar-range') as unknown as CalendarRangeElement & FakeCalendarRange;
const controller = (identifier = 'date-range'): DateRangeController =>
  app.getControllerForElementAndIdentifier(root(), identifier) as DateRangeController;

beforeEach(() => {
  document.documentElement.lang = 'es';
  app = Application.start();
  app.register('date-range', FakeCalendarController);
  app.register('broken-date-range', BrokenCalendarController);
});

afterEach(() => {
  app.stop();
  document.body.innerHTML = '';
  document.documentElement.lang = '';
  proto.showPopover = original.show;
  proto.hidePopover = original.hide;
  vi.restoreAllMocks();
});

describe('date-range helpers', () => {
  it('parses Y-m-d days only', () => {
    expect(parseDay('2026-02-01')?.toISOString()).toBe('2026-02-01T00:00:00.000Z');
    expect(parseDay(' 2026-02-30 ')).toBeNull();
    expect(parseDay('01/02/2026')).toBeNull();
    expect(parseDay('')).toBeNull();
  });

  it('turns Symfony locales into language tags', () => {
    expect(toLanguageTag('en_GB')).toBe('en-GB');
    expect(toLanguageTag(' es ')).toBe('es');
    expect(toLanguageTag('')).toBeUndefined();
    expect(toLanguageTag(null)).toBeUndefined();
    expect(toLanguageTag('not a locale!')).toBeUndefined();
  });

  it('formats a range, an open end or nothing in the locale', () => {
    expect(formatRange('2026-02-01', '2026-02-15', 'es')).toBe(span('2026-02-01', '2026-02-15'));
    expect(formatRange('2026-02-01', '2026-02-15', 'en-US')).toBe(span('2026-02-01', '2026-02-15', 'en-US'));
    expect(formatRange('2026-02-15', '2026-02-01', 'es')).toBe(`${day('2026-02-15')} – ${day('2026-02-01')}`);
    expect(formatRange('2026-02-01', '', 'es', { from: 'Desde' })).toBe(`Desde ${day('2026-02-01')}`);
    expect(formatRange('', '2026-02-15', 'es', { to: 'Hasta' })).toBe(`Hasta ${day('2026-02-15')}`);
    expect(formatRange('2026-02-01', '', 'es')).toBe(`From ${day('2026-02-01')}`);
    expect(formatRange('', '2026-02-15', 'es')).toBe(`To ${day('2026-02-15')}`);
    expect(formatRange('x', '', 'es')).toBe('');
  });

  it('falls back to two formatted days without Intl formatRange', () => {
    const descriptor = Object.getOwnPropertyDescriptor(Intl.DateTimeFormat.prototype, 'formatRange') as PropertyDescriptor;
    Object.defineProperty(Intl.DateTimeFormat.prototype, 'formatRange', { value: undefined, configurable: true, writable: true });
    try {
      expect(formatRange('2026-02-01', '2026-02-15', 'es')).toBe(`${day('2026-02-01')} – ${day('2026-02-15')}`);
    } finally {
      Object.defineProperty(Intl.DateTimeFormat.prototype, 'formatRange', descriptor);
    }
  });
});

describe('date-range peer', () => {
  it('enhances the two native inputs into one control, inputs kept in the panel with their names', async () => {
    stubPopover();
    document.body.innerHTML = field('date-range', '', '2026-02-01', '2026-02-15');
    await settle();

    expect(root().classList.contains('is-enhanced')).toBe(true);
    expect(root().classList.contains('is-empty')).toBe(false);
    expect(trigger().className).toBe('nowo-ui-input nowo-ui-date-range__trigger');
    expect(trigger().getAttribute('aria-haspopup')).toBe('dialog');
    expect(trigger().getAttribute('aria-expanded')).toBe('false');
    expect(trigger().getAttribute('aria-controls')).toBe('collected-panel');
    expect(trigger().textContent).toBe(span('2026-02-01', '2026-02-15'));
    expect(trigger().getAttribute('aria-label')).toBe(`Collection date: ${span('2026-02-01', '2026-02-15')}`);
    expect(panel().getAttribute('popover')).toBe('auto');
    expect(panel().getAttribute('role')).toBe('dialog');
    expect(panel().getAttribute('aria-label')).toBe('Collection date');
    expect(panel().contains(input('collected_from'))).toBe(true);
    expect(new FormData(document.querySelector('form') as HTMLFormElement).getAll('collected_to')).toEqual(['2026-02-15']);
    const clear = root().querySelector<HTMLButtonElement>('[data-date-range-clear]') as HTMLButtonElement;
    expect(clear.hidden).toBe(false);
    expect(clear.getAttribute('aria-label')).toBe('Borrar: Collection date');
    expect(controller().range).toEqual({ from: '2026-02-01', to: '2026-02-15' });
  });

  it('shows the placeholder when empty and the open end with its short label', async () => {
    stubPopover();
    document.body.innerHTML = field();
    await settle();

    expect(root().classList.contains('is-empty')).toBe(true);
    expect(trigger().textContent).toBe('Collection date');
    expect(trigger().getAttribute('aria-label')).toBe('Collection date');
    expect((root().querySelector('[data-date-range-clear]') as HTMLButtonElement).hidden).toBe(true);

    input('collected_from').value = '2026-03-05';
    input('collected_from').dispatchEvent(new Event('change', { bubbles: true }));
    expect(trigger().textContent).toBe(`Desde ${day('2026-03-05')}`);
  });

  it('opens the calendar in the popover, picks a range, closes and returns the focus', async () => {
    stubPopover();
    document.body.innerHTML = field('date-range', `data-date-range-months-value="2" data-date-range-first-day-of-week-value="0" data-date-range-locale-value="en_GB"`, '2026-01-10', '');
    await settle();
    const ready = vi.fn();
    const changed = vi.fn();
    root().addEventListener('date-range:ready', ready);
    root().addEventListener('date-range:change', (event) => changed((event as CustomEvent).detail));
    const inputChanges = vi.fn();
    input('collected_to').addEventListener('change', inputChanges);

    controller().open();
    await settle();
    expect(panel().dataset.open).toBe('1');
    expect(root().classList.contains('is-open')).toBe(true);
    expect(trigger().getAttribute('aria-expanded')).toBe('true');
    expect(ready).toHaveBeenCalledTimes(1);
    const cal = calendar();
    expect(cal.getAttribute('months')).toBe('2');
    expect(cal.getAttribute('first-day-of-week')).toBe('0');
    expect(cal.getAttribute('locale')).toBe('en-GB');
    expect(cal.getAttribute('focused-date')).toBe('2026-01-10');
    expect(cal.value).toBe('');
    expect(cal.querySelectorAll('calendar-month')).toHaveLength(2);
    expect(cal.querySelectorAll('calendar-month')[1]?.getAttribute('offset')).toBe('1');
    expect(cal.querySelector('[slot="previous"]')?.textContent).toBe('Anterior');
    expect(cal.querySelector('[slot="next"]')?.textContent).toBe('Siguiente');
    expect(cal.focusCalls).toBe(1);
    expect(panel().style.top).toMatch(/px$/);

    cal.value = '2026-01-10/2026-01-20';
    cal.dispatchEvent(new Event('change'));
    expect(input('collected_from').value).toBe('2026-01-10');
    expect(input('collected_to').value).toBe('2026-01-20');
    expect(inputChanges).toHaveBeenCalledTimes(1);
    expect(changed).toHaveBeenLastCalledWith({ from: '2026-01-10', to: '2026-01-20' });
    expect(root().classList.contains('is-open')).toBe(false);
    expect(document.activeElement).toBe(trigger());
    expect(trigger().textContent).toBe(span('2026-01-10', '2026-01-20', 'en-GB'));

    // A typed day updates the calendar too; a reopened panel reuses it.
    input('collected_to').value = '2026-01-25';
    input('collected_to').dispatchEvent(new Event('input', { bubbles: true }));
    expect(cal.value).toBe('2026-01-10/2026-01-25');
    controller().open();
    await settle();
    expect(root().querySelectorAll('calendar-range')).toHaveLength(1);
    controller().close();
    await settle();
    expect(root().classList.contains('is-open')).toBe(false);
  });

  it('closes on Escape, Done, a light dismiss or a second toggle', async () => {
    stubPopover();
    document.body.innerHTML = field();
    await settle();

    controller().toggle();
    await settle();
    panel().dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    expect(root().classList.contains('is-open')).toBe(false);
    expect(document.activeElement).toBe(trigger());

    trigger().dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
    await settle();
    expect(root().classList.contains('is-open')).toBe(true);
    trigger().dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
    (root().querySelector('[data-date-range-done]') as HTMLButtonElement).click();
    expect(root().classList.contains('is-open')).toBe(false);

    // The browser opens it from the invoker and light-dismisses it: only `toggle` events.
    trigger().click();
    panel().dispatchEvent(Object.assign(new Event('toggle'), { newState: 'open' }));
    expect(root().classList.contains('is-open')).toBe(true);
    window.dispatchEvent(new Event('resize'));
    panel().dispatchEvent(Object.assign(new Event('toggle'), { newState: 'closed' }));
    expect(root().classList.contains('is-open')).toBe(false);

    controller().toggle();
    controller().toggle();
    expect(root().classList.contains('is-open')).toBe(false);
  });

  it('clears both days from the control and from the panel', async () => {
    stubPopover();
    document.body.innerHTML = field('date-range', '', '2026-02-01', '2026-02-15');
    await settle();
    const changes = vi.fn();
    input('collected_from').addEventListener('change', changes);

    (root().querySelector('[data-date-range-clear]') as HTMLButtonElement).click();
    expect(input('collected_from').value).toBe('');
    expect(input('collected_to').value).toBe('');
    expect(changes).toHaveBeenCalledTimes(1);
    expect(root().classList.contains('is-empty')).toBe(true);
    expect(document.activeElement).toBe(trigger());

    input('collected_to').value = '2026-04-01';
    (root().querySelector('[data-date-range-panel-clear]') as HTMLButtonElement).click();
    expect(input('collected_to').value).toBe('');
    expect(changes).toHaveBeenCalledTimes(2);
    controller().clear();
    expect(changes).toHaveBeenCalledTimes(2);
  });

  it('tolerates a popover the browser already showed or hid', async () => {
    stubPopover();
    document.body.innerHTML = field();
    await settle();
    proto.showPopover = (): void => {
      throw new DOMException('already open', 'InvalidStateError');
    };
    proto.hidePopover = (): void => {
      throw new DOMException('already hidden', 'InvalidStateError');
    };

    controller().open();
    expect(root().classList.contains('is-open')).toBe(true);
    controller().close();
    expect(root().classList.contains('is-open')).toBe(false);
  });

  it('opens inline without the Popover API and closes on a click outside', async () => {
    noPopover();
    document.body.innerHTML = `${field()}<button id="outside" type="button">x</button>`;
    await settle();

    expect(panel().hasAttribute('popover')).toBe(false);
    expect(panel().hidden).toBe(true);
    trigger().click();
    await settle();
    expect(panel().hidden).toBe(false);
    expect(panel().style.top).toBe('');
    panel().dispatchEvent(new Event('pointerdown', { bubbles: true }));
    expect(panel().hidden).toBe(false);
    document.getElementById('outside')?.dispatchEvent(new Event('pointerdown', { bubbles: true }));
    expect(panel().hidden).toBe(true);
    expect(root().classList.contains('is-open')).toBe(false);

    trigger().click();
    trigger().click();
    expect(panel().hidden).toBe(true);
  });

  it('keeps the native inputs and focuses the first one when the calendar cannot be loaded', async () => {
    stubPopover();
    const unavailable = vi.fn();
    document.addEventListener('broken-date-range:unavailable', unavailable);
    document.body.innerHTML = field('broken-date-range');
    await settle();

    controller('broken-date-range').open();
    await settle();
    expect(unavailable).toHaveBeenCalledTimes(1);
    expect(root().querySelector('calendar-range')).toBeNull();
    expect(document.activeElement).toBe(input('collected_from'));
    input('collected_from').value = '2026-05-01';
    controller('broken-date-range').close();
    controller('broken-date-range').open();
    await settle();
    expect(document.activeElement).toBe(input('collected_to'));
    document.removeEventListener('broken-date-range:unavailable', unavailable);
  });

  it('restores the native markup on disconnect and enhances again on reconnect', async () => {
    stubPopover();
    document.body.innerHTML = field('date-range', '', '2026-02-01', '');
    await settle();
    controller().open();
    await settle();

    const element = root();
    const form = element.parentElement as HTMLElement;
    element.remove();
    await settle();
    expect(element.classList.contains('is-enhanced')).toBe(false);
    expect(element.querySelector('[data-date-range-trigger]')).toBeNull();
    expect(element.firstElementChild?.classList.contains('nowo-ui-date-range__parts')).toBe(true);
    expect((element.querySelector('input[name="collected_from"]') as HTMLInputElement).value).toBe('2026-02-01');

    form.append(element);
    await settle();
    expect(element.querySelectorAll('[data-date-range-trigger]')).toHaveLength(1);
  });

  it('does nothing without its targets', async () => {
    document.body.innerHTML = '<div class="nowo-ui-date-range" data-controller="date-range"></div>';
    await settle();

    expect(root().classList.contains('is-enhanced')).toBe(false);
    controller().open();
    controller().close();
    expect(controller().range).toEqual({ from: '', to: '' });
    root().remove();
    await settle();
  });

  it('places the panel above the control when there is no room below', async () => {
    stubPopover();
    document.body.innerHTML = field('date-range', 'data-date-range-months-value="1"');
    await settle();
    vi.spyOn(trigger(), 'getBoundingClientRect').mockReturnValue({ top: 700, bottom: 738, left: 900, right: 1100, width: 200, height: 38, x: 900, y: 700, toJSON: () => ({}) });
    Object.defineProperty(panel(), 'offsetHeight', { value: 320, configurable: true });
    Object.defineProperty(panel(), 'offsetWidth', { value: 300, configurable: true });
    vi.stubGlobal('innerHeight', 768);

    controller().open();
    await settle();
    expect(panel().style.top).toBe(`${700 - 4 - 320}px`);
    expect(Number.parseInt(panel().style.left, 10)).toBeLessThanOrEqual(900);
    vi.unstubAllGlobals();
  });
});
