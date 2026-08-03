import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

describe('nowo-ui-modal', () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <button type="button" data-nowo-modal-open data-nowo-modal-target="m1">Open</button>
      <div id="m1" class="nowo-ui-modal">
        <button type="button" data-nowo-modal-close>Close</button>
      </div>
    `;
    vi.resetModules();
  });

  afterEach(() => {
    document.body.innerHTML = '';
    document.body.className = '';
  });

  it('opens and closes via helpers', async () => {
    const mod = await import('./nowo-ui-modal');
    const modal = document.getElementById('m1');
    expect(modal).not.toBeNull();
    mod.openModal('m1');
    expect(modal?.classList.contains('nowo-ui-modal-open')).toBe(true);
    expect(document.body.classList.contains('nowo-modal-open')).toBe(true);
    mod.closeModal(modal);
    expect(modal?.classList.contains('nowo-ui-modal-open')).toBe(false);
  });
});
