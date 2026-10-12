import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { bindNowoUiOpenModal, resetNowoUiOpenModalForTests } from './nowo-ui-open-modal';

const ISLAND = '<script type="application/json" id="nowo-ui-open-modal-on-load">{"modal":"m","clearParam":"new"}</script>';

describe('nowo-ui-open-modal IIFE', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    resetNowoUiOpenModalForTests();
    window.history.replaceState(null, '', '/items?new=1');
  });

  afterEach(() => {
    vi.useRealTimers();
    document.body.innerHTML = '';
    document.body.classList.remove('nowo-modal-open');
  });

  it('opens the modal right away when the document is interactive and exposes the helper', () => {
    document.body.innerHTML = `${ISLAND}<div id="m" class="nowo-ui-modal"></div>`;
    bindNowoUiOpenModal();
    vi.advanceTimersByTime(50);
    expect(document.getElementById('m')?.classList.contains('nowo-ui-modal-open')).toBe(true);
    expect(window.location.search).toBe('');
    expect(typeof window.nowoUiOpenModalOnLoad).toBe('function');
  });

  it('binds only once', () => {
    const win = {} as Window;
    bindNowoUiOpenModal(document, win);
    const first = win.nowoUiOpenModalOnLoad;
    bindNowoUiOpenModal(document, win);
    expect(win.nowoUiOpenModalOnLoad).toBe(first);
  });

  it('waits for DOMContentLoaded while the document is loading', () => {
    const doc = document.implementation.createHTMLDocument('x');
    Object.defineProperty(doc, 'readyState', { value: 'loading' });
    doc.body.innerHTML = `${ISLAND}<div id="m"></div>`;
    bindNowoUiOpenModal(doc, window);
    vi.advanceTimersByTime(100);
    expect(doc.getElementById('m')?.classList.contains('nowo-ui-modal-open')).toBe(false);
    doc.dispatchEvent(new Event('DOMContentLoaded'));
    vi.advanceTimersByTime(50);
    expect(doc.getElementById('m')?.classList.contains('nowo-ui-modal-open')).toBe(true);
  });
});
