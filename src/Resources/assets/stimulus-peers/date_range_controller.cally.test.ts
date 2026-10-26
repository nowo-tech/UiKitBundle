import { Application } from '@hotwired/stimulus';
import { afterEach, describe, expect, it, vi } from 'vitest';

import DateRangeController from './date_range_controller';

/** The default calendar factory imports Cally itself (its own file: Cally defines the real custom elements). */
const settle = async (): Promise<void> => {
  for (let i = 0; i < 10; i++) {
    await new Promise((r) => setTimeout(r, 0));
  }
};

afterEach(() => {
  document.body.innerHTML = '';
  vi.unstubAllGlobals();
});

describe('date-range peer with Cally', () => {
  it('imports Cally on the first opening (one month on a narrow viewport)', async () => {
    vi.stubGlobal('matchMedia', (query: string) => ({ matches: false, media: query }));
    document.documentElement.lang = 'es';
    const app = Application.start();
    app.register('date-range', DateRangeController);
    document.body.innerHTML = `
      <form><div id="d" class="nowo-ui-date-range" data-controller="date-range" data-date-range-placeholder-value="Fecha">
        <div data-date-range-target="parts">
          <input type="date" name="d_from" value="2026-02-01" data-date-range-target="from">
          <input type="date" name="d_to" value="2026-02-10" data-date-range-target="to">
        </div>
      </div></form>`;
    await settle();
    const ready = vi.fn();
    document.addEventListener('date-range:ready', ready);

    (app.getControllerForElementAndIdentifier(document.getElementById('d') as HTMLElement, 'date-range') as DateRangeController).open();
    await settle();

    expect(ready).toHaveBeenCalledTimes(1);
    expect(customElements.get('calendar-range')).toBeDefined();
    const calendar = document.querySelector('calendar-range') as HTMLElement & { value: string };
    expect(calendar.getAttribute('months')).toBe('1');
    expect(calendar.getAttribute('locale')).toBe('es');
    expect(calendar.value).toBe('2026-02-01/2026-02-10');
    app.stop();
  });
});
