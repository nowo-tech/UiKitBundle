/**
 * 2.69 — a second copy of js/nowo-ui-dialog.js / js/nowo-ui-confirm.js (another bundle's build) binds nothing: the
 * flag lives on window under Symbol.for('nowo-ui.bound.<name>'), set here as the first copy would have.
 */
import { describe, expect, it } from 'vitest';

describe('second copy of the runtimes (2.69)', () => {
  it('binds no listener and installs no global when a copy already bound', async () => {
    const registry = window as unknown as Record<symbol, unknown>;
    registry[Symbol.for('nowo-ui.bound.dialog')] = true;
    registry[Symbol.for('nowo-ui.bound.confirm')] = true;
    await import('./nowo-ui-dialog');
    await import('./nowo-ui-confirm');
    expect(window.nowoUiOpenRemoteForm).toBeUndefined();
    expect(window.nowoUiOpenConfirm).toBeUndefined();
  });
});
