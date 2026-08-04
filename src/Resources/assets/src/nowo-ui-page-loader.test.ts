import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import * as mod from './nowo-ui-page-loader';

describe('nowo-ui-page-loader', () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <div data-nowo-ui-page-loader hidden aria-busy="false"></div>
      <button type="button" data-nowo-ui-page-loader-show>Show</button>
      <button type="button" data-nowo-ui-page-loader-hide>Hide</button>
    `;
    mod.bindNowoUiPageLoader();
  });

  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('shows and hides via helpers', () => {
    const el = document.querySelector('[data-nowo-ui-page-loader]')!;
    mod.showPageLoader();
    expect(el.classList.contains('is-active')).toBe(true);
    expect(el.getAttribute('aria-busy')).toBe('true');
    expect(el.hasAttribute('hidden')).toBe(false);
    mod.hidePageLoader();
    expect(el.classList.contains('is-active')).toBe(false);
    expect(el.hasAttribute('hidden')).toBe(true);
  });

  it('toggles via data attributes', () => {
    document.querySelector('[data-nowo-ui-page-loader-show]')!
      .dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect(document.querySelector('[data-nowo-ui-page-loader]')?.classList.contains('is-active')).toBe(true);
    document.querySelector('[data-nowo-ui-page-loader-hide]')!
      .dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect(document.querySelector('[data-nowo-ui-page-loader]')?.classList.contains('is-active')).toBe(false);
  });

  it('no-ops without loader markup', () => {
    document.body.innerHTML = '';
    mod.showPageLoader();
    mod.hidePageLoader();
    expect(window.nowoUiShowPageLoader).toBe(mod.showPageLoader);
  });

  it('bindNowoUiPageLoader is idempotent', () => {
    mod.bindNowoUiPageLoader();
    expect(window.nowoUiHidePageLoader).toBe(mod.hidePageLoader);
  });

  it('ignores unrelated clicks', () => {
    document.body.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect(document.querySelector('[data-nowo-ui-page-loader]')?.classList.contains('is-active')).toBe(false);
  });
});

