// @vitest-environment node
import { describe, expect, it } from 'vitest';

// GT-I8: a host's server-side import, or a Node test environment, has no Element: importing must not touch the DOM.
describe('importing without a DOM', () => {
  it('has no DOM here', () => {
    expect(typeof Element).toBe('undefined');
  });

  it.each([
    ['fragment filter', () => import('./nowo-ui-fragment-filter')],
    ['remote form core', () => import('./nowo-ui-remote-form-core')],
    ['auto-refresh peer', () => import('../stimulus-peers/auto_refresh_controller')],
    ['list-pagination peer', () => import('../stimulus-peers/list_pagination_controller')],
    ['button label', () => import('./nowo-ui-button-label')],
    ['temporary-reveal peer', () => import('../stimulus-peers/temporary_reveal_controller')],
    ['clipboard-copy peer', () => import('../stimulus-peers/clipboard_copy_controller')],
  ])('%s imports', async (_name, load) => {
    await expect(load()).resolves.toBeDefined();
  });
});
