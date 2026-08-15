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
    expect(document.querySelector('[data-nowo-ui-tab-id="b"][data-nowo-ui-tabs-panel]')!.hidden).toBe(false);
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
    expect(document.querySelector('[data-nowo-ui-tabs-panel]')!.hidden).toBe(false);
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
    expect(document.querySelector('[data-nowo-ui-tab-id="b"][data-nowo-ui-tabs-panel]')!.hidden).toBe(false);
    expect(document.querySelector('[data-nowo-ui-tab-id="a"][data-nowo-ui-tabs-panel]')!.hidden).toBe(true);
  });

  it('activateTab ignores unknown selector', () => {
    mod.activateTab('#missing-tabs', 'a');
    expect(document.getElementById('tabs')!.getAttribute('data-nowo-ui-tabs-active')).toBe('a');
  });
});
