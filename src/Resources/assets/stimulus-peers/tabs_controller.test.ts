import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import TabsController from './tabs_controller';

const tick = (): Promise<void> => new Promise((r) => setTimeout(r, 0));
const $ = (selector: string): HTMLElement => document.querySelector(selector) as HTMLElement;
const key = (el: HTMLElement, k: string, init: KeyboardEventInit = {}): KeyboardEvent => {
  const event = new KeyboardEvent('keydown', { key: k, bubbles: true, cancelable: true, ...init });
  el.dispatchEvent(event);
  return event;
};

let app: Application;

function markup(id: string, extra = ''): string {
  return `
    <div id="${id}" data-controller="tabs" data-tabs-active-tab-value="${id}-a" data-nowo-ui-tabs-upgrade ${extra}>
      <div role="tablist" data-tabs-target="list" hidden>
        <button type="button" role="tab" id="t-${id}-a" data-tabs-target="trigger" data-tab-id="${id}-a" data-action="click->tabs#open">A</button>
        <button type="button" role="tab" id="t-${id}-b" data-tabs-target="trigger" data-tab-id="${id}-b" data-action="click->tabs#open">B</button>
        <button type="button" role="tab" id="t-${id}-c" data-tabs-target="trigger" data-tab-id="${id}-c" aria-disabled="true">C</button>
        <button type="button" role="tab" data-tabs-target="trigger" data-nowo-ui-tab-id="${id}-d" data-action="click->tabs#open">D</button>
      </div>
      <div id="p-${id}-a" data-tabs-target="tab" data-tab-id="${id}-a"><input required></div>
      <div id="p-${id}-b" data-tabs-target="tab" data-tab-id="${id}-b"><input id="in-${id}-b" required></div>
      <div id="p-${id}-c" data-tabs-target="tab" data-tab-id="${id}-c"></div>
      <div id="p-${id}-d" data-tabs-target="tab" data-nowo-ui-tab-id="${id}-d"></div>
    </div>`;
}

beforeEach(() => {
  app = Application.start();
  app.register('tabs', TabsController);
});

afterEach(() => {
  app.stop();
  document.body.innerHTML = '';
  delete window.nowoUiActivateTab;
  vi.restoreAllMocks();
});

describe('tabs peer 2.5 (standalone)', () => {
  it('upgrades a progressive root, roving tabindex, change event on click', async () => {
    document.body.innerHTML = markup('s');
    await tick();
    expect($('#s').hasAttribute('data-nowo-ui-tabs-ready')).toBe(true);
    expect($('#s [role="tablist"]').hidden).toBe(false);
    expect($('#p-s-a').getAttribute('role')).toBe('tabpanel');
    expect($('#p-s-a').getAttribute('aria-labelledby')).toBe('t-s-a');
    expect($('#p-s-d').hasAttribute('aria-labelledby')).toBe(false);
    expect($('#p-s-b').hidden).toBe(true);
    expect($('#t-s-a').tabIndex).toBe(0);
    expect($('#t-s-b').tabIndex).toBe(-1);

    const events: Array<Record<string, unknown>> = [];
    document.addEventListener('nowo-ui:tabs:change', (e) => events.push((e as CustomEvent).detail));
    $('#t-s-b').click();
    await tick();
    expect($('#p-s-b').hidden).toBe(false);
    expect($('#p-s-a').hidden).toBe(true);
    expect($('#p-s-a').classList.contains('hidden')).toBe(true);
    expect(events).toHaveLength(1);
    expect(events[0]).toMatchObject({ tabId: 's-b', previousTabId: 's-a', reason: 'click', trigger: $('#t-s-b'), panel: $('#p-s-b') });
  });

  it('keyboard: arrows (RTL mirrored), Home / End, skips disabled tabs, ignores other keys', async () => {
    document.body.innerHTML = `${markup('k')}<div dir="rtl">${markup('r')}</div>`;
    await tick();
    const reasons: string[] = [];
    document.addEventListener('nowo-ui:tabs:change', (e) => reasons.push((e as CustomEvent).detail.reason));
    expect(key($('#t-k-a'), 'ArrowRight').defaultPrevented).toBe(true);
    await tick();
    expect(document.activeElement).toBe($('#t-k-b'));
    expect($('#p-k-b').hidden).toBe(false);
    key($('#t-k-b'), 'ArrowRight');
    await tick();
    expect($('#p-k-d').hidden).toBe(false);
    key($('#k [data-nowo-ui-tab-id="k-d"]'), 'Home');
    await tick();
    expect($('#p-k-a').hidden).toBe(false);
    key($('#t-k-a'), 'End');
    await tick();
    expect($('#p-k-d').hidden).toBe(false);
    key($('#k [data-nowo-ui-tab-id="k-d"]'), 'ArrowLeft');
    await tick();
    expect($('#p-k-b').hidden).toBe(false);
    expect(reasons.every((r) => r === 'keyboard')).toBe(true);

    expect(key($('#t-k-b'), 'Enter').defaultPrevented).toBe(false);
    expect(key($('#t-k-b'), 'ArrowRight', { altKey: true }).defaultPrevented).toBe(false);
    expect(key($('#p-k-a input'), 'ArrowRight').defaultPrevented).toBe(false);

    key($('#t-r-a'), 'ArrowLeft');
    await tick();
    expect($('#p-r-b').hidden).toBe(false);
    key($('#t-r-b'), 'ArrowRight');
    await tick();
    expect($('#p-r-a').hidden).toBe(false);
  });

  it('opens the panel of the first invalid control, synchronously', async () => {
    document.body.innerHTML = markup('i');
    await tick();
    $('#in-i-b').dispatchEvent(new Event('invalid'));
    expect($('#p-i-b').hidden).toBe(false);
    $('#p-i-a input').dispatchEvent(new Event('invalid'));
    expect($('#p-i-b').hidden).toBe(false);
    await tick();
    $('#in-i-b').dispatchEvent(new Event('invalid'));
    await tick();
    expect($('#p-i-b').hidden).toBe(false);
    $('#i').dispatchEvent(new Event('invalid'));
    await tick();
    expect($('#p-i-b').hidden).toBe(false);
  });

  it('re-applies its state when a morph resets the targets; nested peers stay independent', async () => {
    document.body.innerHTML = markup('o').replace('<div id="p-o-c" data-tabs-target="tab" data-tab-id="o-c"></div>', `<div id="p-o-c" data-tabs-target="tab" data-tab-id="o-c">${markup('n')}</div>`);
    await tick();
    $('#t-o-b').click();
    await tick();
    $('#p-o-b').hidden = true;
    $('#p-o-a').hidden = false;
    await tick();
    expect($('#p-o-b').hidden).toBe(false);
    expect($('#p-o-a').hidden).toBe(true);

    $('#t-n-b').click();
    await tick();
    expect($('#p-n-b').hidden).toBe(false);
    expect($('#p-o-b').hidden).toBe(false);
    expect($('#p-o-c').hidden).toBe(true);
  });

  it('stops listening on disconnect', async () => {
    document.body.innerHTML = markup('x');
    await tick();
    const root = $('#x');
    root.removeAttribute('data-controller');
    await tick();
    expect(key($('#t-x-a'), 'ArrowRight').defaultPrevented).toBe(false);
    $('#p-x-b').hidden = false;
    await tick();
    expect($('#p-x-b').hidden).toBe(false);
  });

  it('keeps the 1.x behaviour without role="tab" or upgrade attributes', async () => {
    document.body.innerHTML = `
      <div data-controller="tabs" data-tabs-active-tab-value="a">
        <button data-tabs-target="trigger" data-tab-id="a" data-action="tabs#open">A</button>
        <button data-tabs-target="trigger" data-tab-id="b" data-action="tabs#open">B</button>
        <div data-tabs-target="tab" data-tab-id="a">A</div>
        <div data-tabs-target="tab" data-tab-id="b">B</div>
      </div>`;
    await tick();
    const [a, b] = Array.from(document.querySelectorAll<HTMLElement>('button'));
    expect(a.hasAttribute('tabindex')).toBe(false);
    expect(key(a, 'ArrowRight').defaultPrevented).toBe(false);
    b.click();
    await tick();
    expect(b.getAttribute('aria-selected')).toBe('true');
    expect(b.hasAttribute('data-active')).toBe(true);
  });
  it('2.5.2: hides the legends of its own panels visually (no nowo-ui.css needed)', async () => {
    document.body.innerHTML = markup('g')
      .replace('<input required>', '<fieldset><legend id="lg-a" class="nowo-ui-locale-tabs__legend">Español <span>ES</span></legend><input required></fieldset>')
      .replace('<div id="p-g-c" data-tabs-target="tab" data-tab-id="g-c"></div>', `<div id="p-g-c" data-tabs-target="tab" data-tab-id="g-c"><fieldset><legend id="lg-c" data-nowo-ui-tabs-legend>C</legend></fieldset>${markup('h').replace('<input required>', '<legend id="lg-plain">Plain</legend>')}</div>`);
    await tick();
    for (const id of ['#lg-a', '#lg-c']) {
      const el = $(id);
      expect(el.classList.contains('visually-hidden')).toBe(true);
      expect(el.hasAttribute('data-nowo-ui-visually-hidden')).toBe(true);
      expect(el.hasAttribute('hidden')).toBe(false);
      expect(el.style.getPropertyValue('position')).toBe('absolute');
      expect(el.style.getPropertyPriority('clip')).toBe('important');
    }
    expect($('#lg-plain').hasAttribute('data-nowo-ui-visually-hidden')).toBe(false);
    const css = $('#lg-a').style.cssText;
    $('#t-g-b').click();
    await tick();
    expect($('#lg-a').style.cssText).toBe(css);
  });
});

describe('tabs peer 2.5 (delegated to nowo-ui-tabs.js)', () => {
  it('hands values to window.nowoUiActivateTab and leaves keys / invalid to the runtime', async () => {
    const activate = vi.fn();
    window.nowoUiActivateTab = activate;
    document.body.innerHTML = markup('d', 'data-nowo-ui-tabs');
    await tick();
    expect(activate).toHaveBeenCalledWith($('#d'), 'd-a');
    expect($('#d').hasAttribute('data-nowo-ui-tabs-ready')).toBe(false);
    $('#t-d-b').click();
    await tick();
    expect(activate).toHaveBeenLastCalledWith($('#d'), 'd-b');
    expect(key($('#t-d-b'), 'ArrowRight').defaultPrevented).toBe(false);
    $('#in-d-b').dispatchEvent(new Event('invalid'));
    expect(activate).toHaveBeenCalledTimes(2);
  });

  it('ignores keys, invalid and morphs once the runtime appears after connect', async () => {
    document.body.innerHTML = markup('l', 'data-nowo-ui-tabs');
    await tick();
    const activate = vi.fn();
    window.nowoUiActivateTab = activate;
    expect(key($('#t-l-a'), 'ArrowRight').defaultPrevented).toBe(false);
    $('#in-l-b').dispatchEvent(new Event('invalid'));
    $('#p-l-b').hidden = false;
    await tick();
    expect($('#p-l-b').hidden).toBe(false);
    expect(activate).not.toHaveBeenCalled();
  });

  it('open() ignores events without a current target', () => {
    const controller = new (TabsController as unknown as new (...args: unknown[]) => { open(e: Event): void })();
    expect(() => controller.open(new Event('click'))).not.toThrow();
  });
});
