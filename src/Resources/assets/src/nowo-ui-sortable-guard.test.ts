import { describe, expect, it, vi } from 'vitest';

// HH-L4: an element with this id makes `window.nowoUiAnnounceSortable` truthy. It must not turn the component off.
describe('the double-load guard', () => {
  it('binds despite an element named like the global, and only once', async () => {
    (window as unknown as Record<string, unknown>).nowoUiAnnounceSortable = document.createElement('a');
    const add = vi.spyOn(EventTarget.prototype, 'addEventListener');
    await import('./nowo-ui-sortable');
    const bound = add.mock.calls.filter(([type]) => type === 'pointerdown').length;
    expect(bound).toBe(1);
    expect(typeof window.nowoUiAnnounceSortable).toBe('function');

    vi.resetModules();
    await import('./nowo-ui-sortable');
    expect(add.mock.calls.filter(([type]) => type === 'pointerdown').length).toBe(1);
    add.mockRestore();
  });
});
