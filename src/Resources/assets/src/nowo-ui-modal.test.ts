import { afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest';
import * as mod from './nowo-ui-modal';

describe('nowo-ui-modal', () => {
  beforeAll(() => {
    // Module side-effect binds listeners once for the suite.
    expect(mod.openModal).toBeTypeOf('function');
  });

  beforeEach(() => {
    document.body.innerHTML = `
      <button type="button" data-nowo-modal-open data-nowo-modal-target="m1">Open</button>
      <div id="m1" class="nowo-ui-modal">
        <button type="button" data-nowo-modal-close>Close</button>
      </div>
    `;
    document.body.className = '';
  });

  afterEach(() => {
    document.body.innerHTML = '';
    document.body.className = '';
  });

  it('opens and closes via helpers', () => {
    const modal = document.getElementById('m1');
    expect(modal).not.toBeNull();
    mod.openModal('m1');
    expect(modal?.classList.contains('nowo-ui-modal-open')).toBe(true);
    expect(document.body.classList.contains('nowo-modal-open')).toBe(true);
    mod.closeModal(modal);
    expect(modal?.classList.contains('nowo-ui-modal-open')).toBe(false);
  });

  it('openModal accepts HTMLElement and ignores missing ids', () => {
    const modal = document.getElementById('m1')!;
    mod.openModal(null);
    expect(modal.classList.contains('nowo-ui-modal-open')).toBe(false);
    mod.openModal('missing');
    expect(modal.classList.contains('nowo-ui-modal-open')).toBe(false);
    mod.openModal(modal);
    expect(modal.classList.contains('nowo-ui-modal-open')).toBe(true);
    mod.closeModal(null);
    expect(modal.classList.contains('nowo-ui-modal-open')).toBe(true);
  });

  it('opens via data-nowo-modal-open click', () => {
    const openBtn = document.querySelector('[data-nowo-modal-open]')!;
    openBtn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect(document.getElementById('m1')?.classList.contains('nowo-ui-modal-open')).toBe(true);
    expect(window.nowoOpenModal).toBeTypeOf('function');
  });

  it('closes via data-nowo-modal-close click', () => {
    mod.openModal('m1');
    const closeBtn = document.querySelector('[data-nowo-modal-close]')!;
    closeBtn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect(document.getElementById('m1')?.classList.contains('nowo-ui-modal-open')).toBe(false);
  });

  it('closes when clicking the open modal backdrop', () => {
    const modal = document.getElementById('m1')!;
    mod.openModal(modal);
    modal.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect(modal.classList.contains('nowo-ui-modal-open')).toBe(false);
  });

  it('closes on Escape', () => {
    mod.openModal('m1');
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    expect(document.getElementById('m1')?.classList.contains('nowo-ui-modal-open')).toBe(false);
  });

  it('ignores non-Escape keydown', () => {
    mod.openModal('m1');
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
    expect(document.getElementById('m1')?.classList.contains('nowo-ui-modal-open')).toBe(true);
  });

  it('bindNowoUiModal is idempotent', () => {
    mod.bindNowoUiModal();
    mod.bindNowoUiModal();
    expect(window.nowoOpenModal).toBe(mod.openModal);
  });
});
