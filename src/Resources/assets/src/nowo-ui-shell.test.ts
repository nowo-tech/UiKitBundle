import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

describe('nowo-ui-shell', () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <div data-nowo-ui-shell class="nowo-ui-shell">
        <button type="button" data-nowo-ui-burger aria-expanded="true">Menu</button>
        <aside data-nowo-ui-aside></aside>
        <div data-nowo-ui-aside-backdrop></div>
      </div>
    `;
    Object.defineProperty(window, 'matchMedia', {
      writable: true,
      value: vi.fn().mockImplementation((query: string) => ({
        matches: query.includes('min-width: 901px'),
        media: query,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
      })),
    });
    vi.resetModules();
  });

  afterEach(() => {
    document.body.innerHTML = '';
    document.body.className = '';
  });

  it('collapses aside on desktop toggle', async () => {
    const mod = await import('./nowo-ui-shell');
    const shell = document.querySelector('[data-nowo-ui-shell]');
    expect(shell).toBeInstanceOf(HTMLElement);
    mod.toggleAside(shell as HTMLElement);
    expect(shell?.classList.contains('is-aside-collapsed')).toBe(true);
  });
});
