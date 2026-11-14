// @vitest-environment node
import { describe, expect, it } from 'vitest';

// A host's server-side import, or a Node test environment, has no DOM: importing must not touch it.
describe('importing the image crop without a DOM', () => {
  it('imports, and the pure helpers work', async () => {
    expect(typeof document).toBe('undefined');
    const core = await import('./nowo-ui-image-crop-core');
    expect(core.outputSize({ width: 2000, height: 2000 }, 512, true)).toEqual({ width: 512, height: 512 });
    const crop = await import('./nowo-ui-image-crop');
    expect(crop.supported()).toBe(false);
  });
});
