import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as mod from './nowo-ui-header-crumbs';

const trail = (attrs = 'data-collapsible="true"'): string =>
  `<nav class="nowo-ui-header-crumbs" data-nowo-ui-header-crumbs ${attrs}><ol>` +
  '<li><a href="/sales">Sales</a></li>' +
  '<li data-nowo-ui-header-crumbs-more hidden><button type="button">…</button></li>' +
  '<li class="nowo-ui-header-crumbs__item--middle"><a href="/sales/customers">Customers</a></li>' +
  '<li><span aria-current="page">Q-1042</span></li></ol></nav>';

const flush = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));

const proto = HTMLElement.prototype as unknown as { togglePopover?: unknown };
const original = Object.getOwnPropertyDescriptor(HTMLElement.prototype, 'togglePopover');

function withPopoverApi(on: boolean): void {
  if (on) {
    Object.defineProperty(HTMLElement.prototype, 'togglePopover', { value: () => true, configurable: true, writable: true });
  } else {
    delete proto.togglePopover;
  }
}

describe('nowo-ui-header-crumbs', () => {
  beforeEach(() => {
    mod.unbindNowoUiHeaderCrumbs();
    withPopoverApi(true);
  });

  afterEach(() => {
    mod.unbindNowoUiHeaderCrumbs();
    document.body.innerHTML = '';
    if (original) {
      Object.defineProperty(HTMLElement.prototype, 'togglePopover', original);
    } else {
      delete proto.togglePopover;
    }
  });

  it('collapses the trails on start-up and reveals the "…" item', () => {
    document.body.innerHTML = trail() + trail();
    mod.bindNowoUiHeaderCrumbs();
    document.querySelectorAll('nav').forEach((nav) => {
      expect(nav.getAttribute('data-collapsed')).toBe('true');
      expect(nav.querySelector('[data-nowo-ui-header-crumbs-more]')?.hasAttribute('hidden')).toBe(false);
    });
  });

  it('collapses trails inserted later (MutationObserver on the body)', async () => {
    mod.bindNowoUiHeaderCrumbs();
    mod.bindNowoUiHeaderCrumbs(); // bound once
    const host = document.createElement('div');
    host.innerHTML = trail();
    document.body.append(host);
    await flush();
    expect(host.querySelector('nav')?.getAttribute('data-collapsed')).toBe('true');
    mod.unbindNowoUiHeaderCrumbs();
    const later = document.createElement('div');
    later.innerHTML = trail();
    document.body.append(later);
    await flush();
    expect(later.querySelector('nav')?.hasAttribute('data-collapsed')).toBe(false);
  });

  it('keeps working after a Turbo Drive style body swap', async () => {
    mod.bindNowoUiHeaderCrumbs();
    const oldBody = document.body;
    const newBody = document.createElement('body');
    newBody.innerHTML = trail();
    document.documentElement.replaceChild(newBody, oldBody);
    await flush();
    expect(newBody.querySelector('nav')?.getAttribute('data-collapsed')).toBe('true');
    const later = document.createElement('div');
    later.innerHTML = trail();
    newBody.append(later);
    await flush();
    expect(later.querySelector('nav')?.getAttribute('data-collapsed')).toBe('true');
    expect(later.querySelector('[data-nowo-ui-header-crumbs-more]')?.hasAttribute('hidden')).toBe(false);
  });

  it('is idempotent and leaves non-collapsible trails alone', () => {
    document.body.innerHTML = trail('data-collapsible="true" data-collapsed="true"') + trail('') + trail();
    const navs = document.querySelectorAll('nav');
    expect(mod.enhanceHeaderCrumbs()).toBe(1);
    expect(mod.enhanceHeaderCrumbs()).toBe(0);
    expect(navs[0]?.querySelector('[data-nowo-ui-header-crumbs-more]')?.hasAttribute('hidden')).toBe(true);
    expect(navs[1]?.hasAttribute('data-collapsed')).toBe(false);
    expect(navs[2]?.getAttribute('data-collapsed')).toBe('true');
  });

  it('enhances a trail passed as the root, and a trail without "…" item', () => {
    document.body.innerHTML = trail() + '<nav data-nowo-ui-header-crumbs data-collapsible="true"></nav>';
    const [first, bare] = Array.from(document.querySelectorAll<HTMLElement>('nav'));
    expect(mod.enhanceHeaderCrumbs(first as HTMLElement)).toBe(1);
    expect(first?.getAttribute('data-collapsed')).toBe('true');
    expect(mod.enhanceHeaderCrumbs(bare as HTMLElement)).toBe(1);
    expect(bare?.getAttribute('data-collapsed')).toBe('true');
  });

  it('leaves the trail un-collapsed without the Popover API', () => {
    withPopoverApi(false);
    expect(mod.supportsPopover()).toBe(false);
    document.body.innerHTML = trail();
    mod.bindNowoUiHeaderCrumbs();
    expect(mod.enhanceHeaderCrumbs()).toBe(0);
    expect(document.querySelector('nav')?.hasAttribute('data-collapsed')).toBe(false);
    expect(document.querySelector('[data-nowo-ui-header-crumbs-more]')?.hasAttribute('hidden')).toBe(true);
  });

  it('waits for DOMContentLoaded while the document is loading', () => {
    const state = vi.spyOn(document, 'readyState', 'get').mockReturnValue('loading');
    document.body.innerHTML = trail();
    mod.bindNowoUiHeaderCrumbs();
    expect(document.querySelector('nav')?.hasAttribute('data-collapsed')).toBe(false);
    state.mockRestore();
    document.dispatchEvent(new Event('DOMContentLoaded'));
    expect(document.querySelector('nav')?.getAttribute('data-collapsed')).toBe('true');
  });
});
