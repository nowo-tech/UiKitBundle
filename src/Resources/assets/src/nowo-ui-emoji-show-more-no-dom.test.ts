// @vitest-environment node
import { describe, expect, it } from 'vitest';

// GT-I8: a host's server-side import, or a Node test environment, has no DOM: importing must not touch it.
describe('importing without a DOM', () => {
  it('has no DOM here', () => {
    expect(typeof document).toBe('undefined');
  });

  it('the emoji picker and show more import, and the pure helpers work', async () => {
    const emoji = await import('./nowo-ui-emoji-picker');
    expect(emoji.firstGrapheme('🚀x')).toBe('🚀');
    await expect(import('./nowo-ui-show-more')).resolves.toBeDefined();
  });
});
