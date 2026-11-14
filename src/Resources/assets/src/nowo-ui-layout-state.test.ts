import { afterEach, describe, expect, it } from 'vitest';

describe('nowo-ui-layout-state (2.48)', () => {
  afterEach(() => {
    document.body.innerHTML = '';
    localStorage.clear();
  });

  it('applies the remembered states on load and exposes a re-run helper', async () => {
    localStorage.setItem('nowo-ui-panel:c', 'closed');
    document.body.innerHTML = '<aside id="p" data-nowo-ui-side-panel data-nowo-ui-side-panel-persist="c"></aside>';
    await import('./nowo-ui-layout-state');
    expect((document.getElementById('p') as HTMLElement).hidden).toBe(true);
    expect(window.nowoUiLayoutState).toBeTypeOf('function');
    expect(window.nowoUiLayoutState!()).toBeTypeOf('function');
  });
});
