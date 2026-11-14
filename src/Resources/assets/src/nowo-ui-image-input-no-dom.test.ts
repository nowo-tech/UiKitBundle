// @vitest-environment node
import { describe, expect, it } from 'vitest';

// A host's server-side import, or a Node test environment, has no DOM: importing must not touch it.
describe('importing the image input without a DOM', () => {
  it('imports, and the pure helpers work', async () => {
    expect(typeof document).toBe('undefined');
    const input = await import('./nowo-ui-image-input');
    expect(input.readLabels('{"undo":"Back"}').undo).toBe('Back');
    expect(input.isImage(new File([], 'a.png', { type: 'image/png' }))).toBe(true);
  });
});
