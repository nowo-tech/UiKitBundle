import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
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

  it('ignores clicks whose target is not an element', () => {
    mod.openModal('m1');
    document.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect(document.getElementById('m1')?.classList.contains('nowo-ui-modal-open')).toBe(true);
  });

  describe('native <dialog> roots (2.33)', () => {
    const polyfill = (dialog: HTMLDialogElement): void => {
      dialog.showModal = function showModal() {
        this.setAttribute('open', '');
      };
      dialog.close = function close() {
        if (!this.hasAttribute('open')) return;
        this.removeAttribute('open');
        this.dispatchEvent(new Event('close'));
      };
      Object.defineProperty(dialog, 'open', { configurable: true, get() { return this.hasAttribute('open'); } });
    };

    beforeEach(() => {
      document.body.innerHTML = `
        <button type="button" id="t" data-nowo-modal-open="#d1">Open</button>
        <button type="button" id="t2" data-nowo-modal-open data-nowo-modal-target="missing">Missing</button>
        <dialog id="d1" class="nowo-ui-modal"><button type="button" data-nowo-modal-close>Close</button></dialog>`;
      polyfill(document.getElementById('d1') as HTMLDialogElement);
    });

    it('opens with showModal() from a data-nowo-modal-open="<id>" trigger and returns focus on close', () => {
      const trigger = document.getElementById('t') as HTMLButtonElement;
      const dialog = document.getElementById('d1') as HTMLDialogElement;
      trigger.focus();
      const click = new MouseEvent('click', { bubbles: true, cancelable: true });
      trigger.dispatchEvent(click);
      expect(click.defaultPrevented).toBe(true);
      expect(dialog.open).toBe(true);
      expect(dialog.classList.contains('nowo-ui-modal-open')).toBe(true);
      expect(document.body.classList.contains('nowo-modal-open')).toBe(true);
      (document.activeElement as HTMLElement | null)?.blur();
      dialog.querySelector<HTMLElement>('[data-nowo-modal-close]')!.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
      expect(dialog.open).toBe(false);
      expect(dialog.classList.contains('nowo-ui-modal-open')).toBe(false);
      expect(document.body.classList.contains('nowo-modal-open')).toBe(false);
      expect(document.activeElement).toBe(trigger);
    });

    it('a native close (Escape) removes the open class', () => {
      const dialog = document.getElementById('d1') as HTMLDialogElement;
      mod.openModal(dialog);
      document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
      expect(dialog.open).toBe(true);
      dialog.close();
      expect(dialog.classList.contains('nowo-ui-modal-open')).toBe(false);
      expect(document.body.classList.contains('nowo-modal-open')).toBe(false);
    });

    it('a trigger whose modal is missing keeps its default action', () => {
      const click = new MouseEvent('click', { bubbles: true, cancelable: true });
      document.getElementById('t2')!.dispatchEvent(click);
      expect(click.defaultPrevented).toBe(false);
      expect(mod.triggerTarget(document.getElementById('t2')!)).toBe('missing');
      expect(mod.triggerTarget(document.getElementById('t')!)).toBe('d1');
    });

    it('binds once per page, even when the script runs twice', async () => {
      const spy = vi.spyOn(document, 'addEventListener');
      vi.resetModules();
      const second = await import('./nowo-ui-modal');
      second.bindNowoUiModal();
      expect(spy).not.toHaveBeenCalled();
      spy.mockRestore();
    });
  });
});
