// @vitest-environment node
import { describe, expect, it } from 'vitest';

// GT-I8: a host's server-side import, or a Node test environment, has no DOM: importing must not touch it.
describe('importing nowo-ui-sortable without a DOM', () => {
  it('has no DOM here', () => {
    expect(typeof document).toBe('undefined');
    expect(typeof Element).toBe('undefined');
  });

  it('imports, and the pure helpers work', async () => {
    const mod = await import('./nowo-ui-sortable');
    expect(mod.formatSortable('%a%', { a: 1 })).toBe('1');
  });
});
