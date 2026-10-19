import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import * as mod from './nowo-ui-tabs';

describe('nowo-ui-tabs', () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <div id="tabs" data-nowo-ui-tabs data-nowo-ui-tabs-active="a">
        <button type="button" data-nowo-ui-tabs-trigger data-nowo-ui-tab-id="a">A</button>
        <button type="button" data-nowo-ui-tabs-trigger data-nowo-ui-tab-id="b">B</button>
        <div data-nowo-ui-tabs-panel data-nowo-ui-tab-id="a">Panel A</div>
        <div data-nowo-ui-tabs-panel data-nowo-ui-tab-id="b">Panel B</div>
      </div>
    `;
    mod.bindNowoUiTabs();
  });

  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('activates initial tab on bind', () => {
    const panelA = document.querySelector('[data-nowo-ui-tab-id="a"][data-nowo-ui-tabs-panel]') as HTMLElement;
    const panelB = document.querySelector('[data-nowo-ui-tab-id="b"][data-nowo-ui-tabs-panel]') as HTMLElement;
    expect(panelA.hidden).toBe(false);
    expect(panelB.hidden).toBe(true);
    expect(window.nowoUiActivateTab).toBe(mod.activateTab);
  });

  it('switches panels on trigger click', () => {
    document.querySelector('[data-nowo-ui-tab-id="b"][data-nowo-ui-tabs-trigger]')!
      .dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    const panelA = document.querySelector('[data-nowo-ui-tab-id="a"][data-nowo-ui-tabs-panel]') as HTMLElement;
    const panelB = document.querySelector('[data-nowo-ui-tab-id="b"][data-nowo-ui-tabs-panel]') as HTMLElement;
    expect(panelA.hidden).toBe(true);
    expect(panelB.hidden).toBe(false);
    expect(document.getElementById('tabs')!.getAttribute('data-nowo-ui-tabs-active')).toBe('b');
  });

  it('activateTab accepts selector and ignores empty id', () => {
    mod.activateTab('#tabs', '');
    mod.activateTab(null, 'b');
    mod.activateTab('#tabs', 'b');
    expect((document.querySelector('[data-nowo-ui-tab-id="b"][data-nowo-ui-tabs-panel]') as HTMLElement).hidden).toBe(false);
  });

  it('bindNowoUiTabs is idempotent', () => {
    mod.bindNowoUiTabs();
    mod.bindNowoUiTabs();
    expect(window.nowoUiActivateTab).toBe(mod.activateTab);
  });

  it('ignores clicks outside tab triggers', () => {
    document.body.appendChild(document.createElement('button')).dispatchEvent(
      new MouseEvent('click', { bubbles: true, cancelable: true }),
    );
    expect(document.getElementById('tabs')!.getAttribute('data-nowo-ui-tabs-active')).toBe('a');
  });

  it('ignores triggers without root or tab id', () => {
    document.body.innerHTML = `
      <button type="button" data-nowo-ui-tabs-trigger data-nowo-ui-tab-id="z">Z</button>
      <div data-nowo-ui-tabs>
        <button type="button" data-nowo-ui-tabs-trigger>No id</button>
        <div data-nowo-ui-tabs-panel data-nowo-ui-tab-id="x">X</div>
      </div>
    `;
    mod.bindNowoUiTabs();
    document.querySelector('[data-nowo-ui-tab-id="z"]')!
      .dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    document.querySelector('[data-nowo-ui-tabs] [data-nowo-ui-tabs-trigger]')!
      .dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect((document.querySelector('[data-nowo-ui-tabs-panel]') as HTMLElement).hidden).toBe(false);
  });

  it('syncs from data-active when active attr missing', () => {
    document.body.innerHTML = `
      <div data-nowo-ui-tabs>
        <button type="button" data-nowo-ui-tabs-trigger data-nowo-ui-tab-id="a">A</button>
        <button type="button" data-nowo-ui-tabs-trigger data-nowo-ui-tab-id="b" data-active>B</button>
        <div data-nowo-ui-tabs-panel data-nowo-ui-tab-id="a">A</div>
        <div data-nowo-ui-tabs-panel data-nowo-ui-tab-id="b">B</div>
      </div>
    `;
    mod.bindNowoUiTabs();
    expect((document.querySelector('[data-nowo-ui-tab-id="b"][data-nowo-ui-tabs-panel]') as HTMLElement).hidden).toBe(false);
    expect((document.querySelector('[data-nowo-ui-tab-id="a"][data-nowo-ui-tabs-panel]') as HTMLElement).hidden).toBe(true);
  });

  it('activateTab ignores unknown selector', () => {
    mod.activateTab('#missing-tabs', 'a');
    expect(document.getElementById('tabs')!.getAttribute('data-nowo-ui-tabs-active')).toBe('a');
  });
});

const flush = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));

function localeTabs(id: string, attrs = ''): string {
  return `
    <div id="${id}" data-nowo-ui-tabs data-nowo-ui-tabs-upgrade data-nowo-ui-tabs-active="${id}-es"
      data-nowo-ui-tabs-tab-class="tab" data-nowo-ui-tabs-tab-active-class="tab tab--on" ${attrs}>
      <div role="tablist" data-nowo-ui-tabs-list hidden>
        <button type="button" role="tab" id="tab-${id}-es" class="tab tab--on" data-nowo-ui-tabs-trigger data-nowo-ui-tab-id="${id}-es">ES</button>
        <button type="button" role="tab" id="tab-${id}-en" class="tab" data-nowo-ui-tabs-trigger data-nowo-ui-tab-id="${id}-en">EN</button>
        <button type="button" role="tab" id="tab-${id}-fr" class="tab" disabled data-nowo-ui-tabs-trigger data-nowo-ui-tab-id="${id}-fr">FR</button>
        <button type="button" role="tab" id="tab-${id}-ar" class="tab" data-nowo-ui-tabs-trigger data-nowo-ui-tab-id="${id}-ar">AR</button>
      </div>
      <div id="tab-${id}-es-panel" data-nowo-ui-tabs-panel data-nowo-ui-tab-id="${id}-es"><input name="${id}-es" required></div>
      <div id="tab-${id}-en-panel" data-nowo-ui-tabs-panel data-nowo-ui-tab-id="${id}-en"><input name="${id}-en" required></div>
      <div id="tab-${id}-fr-panel" data-nowo-ui-tabs-panel data-nowo-ui-tab-id="${id}-fr"></div>
      <div id="tab-${id}-ar-panel" data-nowo-ui-tabs-panel data-nowo-ui-tab-id="${id}-ar"><input name="${id}-ar"></div>
    </div>`;
}

const $ = (selector: string): HTMLElement => document.querySelector(selector) as HTMLElement;
const key = (el: HTMLElement, k: string, init: KeyboardEventInit = {}): KeyboardEvent => {
  const event = new KeyboardEvent('keydown', { key: k, bubbles: true, cancelable: true, ...init });
  el.dispatchEvent(event);
  return event;
};

describe('nowo-ui-tabs 2.5', () => {
  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('upgrades a progressive root: tab list shown, tab panels, active classes, roving tabindex', () => {
    document.body.innerHTML = localeTabs('lt');
    mod.bindNowoUiTabs();
    const root = $('#lt');
    expect(root.hasAttribute('data-nowo-ui-tabs-ready')).toBe(true);
    expect($('#lt [role="tablist"]').hidden).toBe(false);
    expect($('#tab-lt-es-panel').getAttribute('role')).toBe('tabpanel');
    expect($('#tab-lt-es-panel').getAttribute('aria-labelledby')).toBe('tab-lt-es');
    expect($('#tab-lt-en-panel').hidden).toBe(true);
    expect($('#tab-lt-es').tabIndex).toBe(0);
    expect($('#tab-lt-en').tabIndex).toBe(-1);

    $('#tab-lt-en').click();
    expect($('#tab-lt-en').className).toBe('tab tab--on');
    expect($('#tab-lt-es').className).toBe('tab');
    expect($('#tab-lt-en').getAttribute('tabindex')).toBe('0');
    expect($('#tab-lt-es').getAttribute('tabindex')).toBe('-1');
    expect($('#tab-lt-en-panel').hidden).toBe(false);
  });

  it('moves with arrows / Home / End (wraps, skips disabled tabs) and focuses the new tab', () => {
    document.body.innerHTML = localeTabs('k');
    mod.bindNowoUiTabs();
    const es = $('#tab-k-es');
    expect(key(es, 'ArrowRight').defaultPrevented).toBe(true);
    expect(document.activeElement).toBe($('#tab-k-en'));
    expect($('#k').getAttribute('data-nowo-ui-tabs-active')).toBe('k-en');
    key($('#tab-k-en'), 'ArrowRight');
    expect($('#k').getAttribute('data-nowo-ui-tabs-active')).toBe('k-ar');
    key($('#tab-k-ar'), 'ArrowRight');
    expect($('#k').getAttribute('data-nowo-ui-tabs-active')).toBe('k-es');
    key($('#tab-k-es'), 'ArrowLeft');
    expect($('#k').getAttribute('data-nowo-ui-tabs-active')).toBe('k-ar');
    key($('#tab-k-ar'), 'Home');
    expect($('#k').getAttribute('data-nowo-ui-tabs-active')).toBe('k-es');
    key($('#tab-k-es'), 'End');
    expect($('#k').getAttribute('data-nowo-ui-tabs-active')).toBe('k-ar');
    // Other keys, modifiers and non-tab triggers are ignored.
    expect(key($('#tab-k-ar'), 'Enter').defaultPrevented).toBe(false);
    expect(key($('#tab-k-ar'), 'ArrowRight', { ctrlKey: true }).defaultPrevented).toBe(false);
    $('#tab-k-ar').removeAttribute('role');
    expect(key($('#tab-k-ar'), 'ArrowRight').defaultPrevented).toBe(false);
    expect(key(document.body, 'ArrowRight').defaultPrevented).toBe(false);
  });

  it('mirrors the arrows in RTL and uses up / down for a vertical list', () => {
    document.body.innerHTML = `<div dir="rtl">${localeTabs('r')}</div>${localeTabs('v')}`;
    mod.bindNowoUiTabs();
    key($('#tab-r-es'), 'ArrowLeft');
    expect($('#r').getAttribute('data-nowo-ui-tabs-active')).toBe('r-en');
    key($('#tab-r-en'), 'ArrowRight');
    expect($('#r').getAttribute('data-nowo-ui-tabs-active')).toBe('r-es');

    $('#v [role="tablist"]').setAttribute('aria-orientation', 'vertical');
    expect(key($('#tab-v-es'), 'ArrowRight').defaultPrevented).toBe(false);
    key($('#tab-v-es'), 'ArrowDown');
    expect($('#v').getAttribute('data-nowo-ui-tabs-active')).toBe('v-en');
    key($('#tab-v-en'), 'ArrowUp');
    expect($('#v').getAttribute('data-nowo-ui-tabs-active')).toBe('v-es');
  });

  it('dispatches nowo-ui:tabs:change with the reason, only on a real change', () => {
    document.body.innerHTML = localeTabs('c');
    mod.bindNowoUiTabs();
    const events: mod.TabsChangeDetail[] = [];
    document.addEventListener(mod.TABS_CHANGE_EVENT, (e) => events.push((e as CustomEvent<mod.TabsChangeDetail>).detail));
    $('#tab-c-es').click();
    $('#tab-c-en').click();
    key($('#tab-c-en'), 'ArrowRight');
    mod.activateTab('c', 'c-es');
    mod.activateTab('c', 'c-missing');
    expect(events.map((d) => [d.tabId, d.previousTabId, d.reason])).toEqual([
      ['c-en', 'c-es', 'click'],
      ['c-ar', 'c-en', 'keyboard'],
      ['c-es', 'c-ar', 'api'],
      ['c-missing', 'c-es', 'api'],
    ]);
    expect(events[0].trigger).toBe($('#tab-c-en'));
    expect(events[0].panel).toBe($('#tab-c-en-panel'));
    expect(events[3].trigger).toBeNull();
    expect(events[3].panel).toBeNull();
  });

  it('opens the hidden panel of the first invalid control of a validation pass', async () => {
    document.body.innerHTML = `<form>${localeTabs('i')}</form>`;
    mod.bindNowoUiTabs();
    const events: string[] = [];
    document.addEventListener(mod.TABS_CHANGE_EVENT, (e) => events.push((e as CustomEvent<mod.TabsChangeDetail>).detail.reason));
    $('#tab-i-ar').click();
    ($('#tab-i-en-panel input') as HTMLInputElement).dispatchEvent(new Event('invalid', { cancelable: true }));
    ($('#tab-i-es-panel input') as HTMLInputElement).dispatchEvent(new Event('invalid', { cancelable: true }));
    expect($('#i').getAttribute('data-nowo-ui-tabs-active')).toBe('i-en');
    expect($('#tab-i-en-panel').hidden).toBe(false);
    expect(events).toEqual(['click', 'invalid']);

    await flush();
    // Visible panel: nothing changes; a control outside any panel is ignored.
    ($('#tab-i-en-panel input') as HTMLInputElement).dispatchEvent(new Event('invalid', { cancelable: true }));
    await flush();
    document.body.insertAdjacentHTML('beforeend', '<input id="loose" required>');
    $('#loose').dispatchEvent(new Event('invalid'));
    await flush();
    document.dispatchEvent(new Event('invalid'));
    expect(events).toEqual(['click', 'invalid']);
  });

  it('opens every hidden panel around an invalid control of nested tab sets', () => {
    document.body.innerHTML = `
      <div id="outer" data-nowo-ui-tabs data-nowo-ui-tabs-active="o1">
        <button type="button" role="tab" data-nowo-ui-tabs-trigger data-nowo-ui-tab-id="o1">O1</button>
        <button type="button" role="tab" data-nowo-ui-tabs-trigger data-nowo-ui-tab-id="o2">O2</button>
        <div id="o1" data-nowo-ui-tabs-panel data-nowo-ui-tab-id="o1">one</div>
        <div id="o2" data-nowo-ui-tabs-panel data-nowo-ui-tab-id="o2">
          <div id="inner" data-nowo-ui-tabs data-nowo-ui-tabs-active="i1">
            <button type="button" role="tab" data-nowo-ui-tabs-trigger data-nowo-ui-tab-id="i1">I1</button>
            <button type="button" role="tab" data-nowo-ui-tabs-trigger data-nowo-ui-tab-id="i2">I2</button>
            <div id="i1" data-nowo-ui-tabs-panel data-nowo-ui-tab-id="i1">a</div>
            <div id="i2" data-nowo-ui-tabs-panel data-nowo-ui-tab-id="i2"><input id="deep" required></div>
          </div>
        </div>
      </div>`;
    mod.bindNowoUiTabs();
    expect($('#o2').hidden).toBe(true);
    expect($('#i1').hidden).toBe(false);

    // Clicking an outer tab leaves the inner set alone, and the other way round.
    ($('#outer > [data-nowo-ui-tab-id="o2"]')).click();
    expect($('#i1').hidden).toBe(false);
    expect($('#i2').hidden).toBe(true);
    ($('#inner > [data-nowo-ui-tab-id="i2"]')).click();
    expect($('#o2').hidden).toBe(false);
    expect($('#outer').getAttribute('data-nowo-ui-tabs-active')).toBe('o2');
    key($('#inner > [data-nowo-ui-tab-id="i2"]'), 'ArrowRight');
    expect($('#inner').getAttribute('data-nowo-ui-tabs-active')).toBe('i1');
    expect($('#outer').getAttribute('data-nowo-ui-tabs-active')).toBe('o2');

    ($('#outer > [data-nowo-ui-tab-id="o1"]')).click();
    $('#deep').dispatchEvent(new Event('invalid'));
    expect($('#outer').getAttribute('data-nowo-ui-tabs-active')).toBe('o2');
    expect($('#inner').getAttribute('data-nowo-ui-tabs-active')).toBe('i2');
    expect($('#i2').hidden).toBe(false);
  });

  it('keeps the chosen tab when a morph resets the server-rendered state and syncs inserted roots', async () => {
    document.body.innerHTML = `<div id="live">${localeTabs('m')}</div>`;
    mod.bindNowoUiTabs();
    $('#tab-m-en').click();
    await flush();

    // A Live Component / Turbo morph writes the server attributes back on the same elements.
    const root = $('#m');
    root.setAttribute('data-nowo-ui-tabs-active', 'm-es');
    $('#tab-m-es').setAttribute('aria-selected', 'true');
    $('#tab-m-en').setAttribute('aria-selected', 'false');
    $('#tab-m-en-panel').hidden = true;
    $('#m [role="tablist"]').hidden = true;
    await flush();
    expect(root.getAttribute('data-nowo-ui-tabs-active')).toBe('m-en');
    expect($('#tab-m-en').getAttribute('aria-selected')).toBe('true');
    expect($('#tab-m-en-panel').hidden).toBe(false);
    expect($('#tab-m-es-panel').hidden).toBe(true);
    expect($('#m [role="tablist"]').hidden).toBe(false);

    // A chosen tab that no longer exists falls back to the server state.
    $('#tab-m-en').remove();
    $('#tab-m-en-panel').remove();
    root.setAttribute('data-nowo-ui-tabs-active', 'm-ar');
    await flush();
    expect($('#tab-m-ar-panel').hidden).toBe(false);

    // New content (remote dialog, replaced component) is upgraded.
    $('#live').insertAdjacentHTML('beforeend', localeTabs('n'));
    await flush();
    expect($('#n').hasAttribute('data-nowo-ui-tabs-ready')).toBe(true);
    expect($('#tab-n-en-panel').hidden).toBe(true);
    $('#live').insertAdjacentHTML('beforeend', '<p>text</p>');
    await flush();
    expect($('#tab-n-es-panel').hidden).toBe(false);
  });

  it('accepts an element root and ignores stray events', () => {
    document.body.innerHTML = `${localeTabs('e')}<button id="stray" role="tab" data-nowo-ui-tabs-trigger data-nowo-ui-tab-id="x">X</button>`;
    mod.bindNowoUiTabs();
    mod.activateTab($('#e'), 'e-en');
    expect($('#tab-e-en-panel').hidden).toBe(false);
    document.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));
    expect(key($('#stray'), 'ArrowRight').defaultPrevented).toBe(false);
    expect($('#e').getAttribute('data-nowo-ui-tabs-active')).toBe('e-en');
  });

  it('targetIndex and isRtl helpers', () => {
    expect(mod.targetIndex('ArrowRight', 2, 3, false, false)).toBe(0);
    expect(mod.targetIndex('ArrowLeft', 0, 3, false, false)).toBe(2);
    expect(mod.targetIndex('ArrowLeft', 0, 3, true, false)).toBe(1);
    expect(mod.targetIndex('ArrowDown', 0, 3, true, true)).toBe(1);
    expect(mod.targetIndex('x', 0, 3, false, false)).toBeNull();
    document.body.innerHTML = '<p id="a" dir="LTR"><span id="b"></span></p><p id="c"></p>';
    expect(mod.isRtl($('#b'))).toBe(false);
    document.documentElement.style.direction = 'rtl';
    expect(mod.isRtl($('#c'))).toBe(true);
    document.documentElement.style.direction = '';
    expect(mod.isRtl($('#c'))).toBe(false);
  });
  it('2.5.2: hides its own panel legends visually without nowo-ui.css (still in the accessibility tree)', async () => {
    const legend = (id: string, extra = ''): string =>
      `<fieldset><legend class="nowo-ui-locale-tabs__legend" id="lg-${id}"${extra}>English <span>EN</span></legend><input></fieldset>`;
    document.body.innerHTML = `
      <div id="v" data-nowo-ui-tabs data-nowo-ui-tabs-upgrade data-nowo-ui-tabs-active="v-es">
        <div role="tablist" data-nowo-ui-tabs-list hidden>
          <button type="button" role="tab" id="tab-v-es" data-nowo-ui-tabs-trigger data-nowo-ui-tab-id="v-es">ES</button>
          <button type="button" role="tab" id="tab-v-en" data-nowo-ui-tabs-trigger data-nowo-ui-tab-id="v-en">EN</button>
        </div>
        <div id="tab-v-es-panel" data-nowo-ui-tabs-panel data-nowo-ui-tab-id="v-es">${legend('v-es')}
          <div id="w" data-nowo-ui-tabs data-nowo-ui-tabs-upgrade data-nowo-ui-tabs-active="w-a" hidden-root>
            <div data-nowo-ui-tabs-panel data-nowo-ui-tab-id="w-a"><fieldset><legend id="lg-plain">Plain legend</legend></fieldset></div>
          </div>
        </div>
        <div id="tab-v-en-panel" data-nowo-ui-tabs-panel data-nowo-ui-tab-id="v-en"><fieldset><legend id="lg-v-en" data-nowo-ui-tabs-legend>English</legend></fieldset></div>
      </div>
      <fieldset><legend class="nowo-ui-locale-tabs__legend" id="lg-outside">Outside</legend></fieldset>`;
    mod.bindNowoUiTabs();
    for (const id of ['#lg-v-es', '#lg-v-en']) {
      const el = $(id);
      expect(el.classList.contains('visually-hidden')).toBe(true);
      expect(el.hasAttribute('data-nowo-ui-visually-hidden')).toBe(true);
      expect(el.hasAttribute('hidden')).toBe(false);
      expect(el.getAttribute('aria-hidden')).toBeNull();
      Object.entries(mod.VISUALLY_HIDDEN).forEach(([name, value]) => {
        expect(el.style.getPropertyValue(name)).toBe(value);
        expect(el.style.getPropertyPriority(name)).toBe('important');
      });
    }
    // Plain legends of other markup and legends outside a tab set are left alone.
    expect($('#lg-plain').hasAttribute('data-nowo-ui-visually-hidden')).toBe(false);
    expect($('#lg-outside').hasAttribute('data-nowo-ui-visually-hidden')).toBe(false);

    // A morph that resets the legend is hidden again on the next sync.
    const es = $('#lg-v-es');
    es.style.cssText = '';
    es.className = 'nowo-ui-locale-tabs__legend';
    es.removeAttribute('data-nowo-ui-visually-hidden');
    $('#v').setAttribute('data-nowo-ui-tabs-active', 'v-en');
    await flush();
    expect(es.classList.contains('visually-hidden')).toBe(true);
    expect(es.style.getPropertyValue('position')).toBe('absolute');
  });

  it('2.5.2: hideVisually is idempotent and keeps existing classes', () => {
    document.body.innerHTML = '<legend id="h" class="x"></legend>';
    const el = $('#h');
    mod.hideVisually(el);
    const css = el.style.cssText;
    mod.hideVisually(el);
    expect(el.style.cssText).toBe(css);
    expect(el.className).toBe('x visually-hidden');
  });
});
