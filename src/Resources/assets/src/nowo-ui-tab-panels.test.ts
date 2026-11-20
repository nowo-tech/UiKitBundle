import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { bindNowoUiTabs } from './nowo-ui-tabs';

/** 2.61: the `tab_panels` partial markup (progressive) driven by the tabs runtime. */
const MARKUP = `
  <div id="inv" class="nowo-ui-tab-panels" data-nowo-ui-tabs data-nowo-ui-tabs-upgrade data-nowo-ui-tabs-active="inv-details">
    <div role="tablist" class="nowo-ui-tab-panels__list" aria-label="Invoice" data-nowo-ui-tabs-list hidden>
      <button type="button" role="tab" id="tab-inv-details" aria-controls="tab-inv-details-panel" aria-selected="true" tabindex="0" data-nowo-ui-tabs-trigger data-nowo-ui-tab-id="inv-details" data-active>Details</button>
      <button type="button" role="tab" id="tab-inv-lines" aria-controls="tab-inv-lines-panel" aria-selected="false" tabindex="-1" data-nowo-ui-tabs-trigger data-nowo-ui-tab-id="inv-lines">Lines</button>
      <button type="button" role="tab" id="tab-inv-history" aria-controls="tab-inv-history-panel" aria-selected="false" tabindex="-1" data-nowo-ui-tabs-trigger data-nowo-ui-tab-id="inv-history">History</button>
    </div>
    <section id="tab-inv-details-panel" tabindex="0" aria-labelledby="tab-inv-details-heading" data-nowo-ui-tabs-panel data-nowo-ui-tab-id="inv-details"><h3 id="tab-inv-details-heading" data-nowo-ui-tabs-legend>Details</h3>A</section>
    <section id="tab-inv-lines-panel" tabindex="0" aria-labelledby="tab-inv-lines-heading" data-nowo-ui-tabs-panel data-nowo-ui-tab-id="inv-lines"><h3 id="tab-inv-lines-heading" data-nowo-ui-tabs-legend>Lines</h3>B</section>
    <section id="tab-inv-history-panel" tabindex="0" aria-labelledby="tab-inv-history-heading" data-nowo-ui-tabs-panel data-nowo-ui-tab-id="inv-history"><h3 id="tab-inv-history-heading" data-nowo-ui-tabs-legend>History</h3>C</section>
  </div>`;

describe('tab_panels partial + nowo-ui-tabs', () => {
  beforeEach(() => {
    document.body.innerHTML = MARKUP;
    bindNowoUiTabs();
  });

  afterEach(() => {
    document.body.innerHTML = '';
  });

  const panel = (id: string) => document.getElementById(`tab-inv-${id}-panel`)!;
  const tab = (id: string) => document.getElementById(`tab-inv-${id}`)!;

  it('upgrades: the tab list shows, panels become tab panels labelled by their tab, headings hidden visually', () => {
    expect(document.querySelector('[role="tablist"]')!.hasAttribute('hidden')).toBe(false);
    expect(panel('details').getAttribute('role')).toBe('tabpanel');
    expect(panel('details').getAttribute('aria-labelledby')).toBe('tab-inv-details');
    expect(panel('lines').hidden).toBe(true);
    expect(document.getElementById('tab-inv-details-heading')!.classList.contains('visually-hidden')).toBe(true);
  });

  it('arrow keys move the selection and the focus (roving tabindex), Home / End jump', () => {
    tab('details').focus();
    tab('details').dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));
    expect(tab('lines').getAttribute('aria-selected')).toBe('true');
    expect(tab('lines').getAttribute('tabindex')).toBe('0');
    expect(tab('details').getAttribute('tabindex')).toBe('-1');
    expect(document.activeElement).toBe(tab('lines'));
    expect(panel('lines').hidden).toBe(false);
    tab('lines').dispatchEvent(new KeyboardEvent('keydown', { key: 'End', bubbles: true }));
    expect(tab('history').getAttribute('aria-selected')).toBe('true');
    tab('history').dispatchEvent(new KeyboardEvent('keydown', { key: 'Home', bubbles: true }));
    expect(tab('details').getAttribute('aria-selected')).toBe('true');
    tab('details').dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft', bubbles: true }));
    expect(tab('history').getAttribute('aria-selected')).toBe('true');
  });

  it('a click selects the tab', () => {
    tab('history').click();
    expect(panel('history').hidden).toBe(false);
    expect(panel('details').hidden).toBe(true);
  });
});
