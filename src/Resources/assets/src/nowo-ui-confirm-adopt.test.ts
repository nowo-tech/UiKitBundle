/**
 * 2.70 (audit ZAF-L1) — when js/nowo-ui-confirm.js binds, the menu group dialogs already on the page
 * (data-nowo-confirm-portal) go to the end of <body>, one per id.
 */
import { describe, expect, it } from 'vitest';

describe('confirm runtime bind (2.70)', () => {
  it('adopts the group dialogs printed inside the menus', async () => {
    document.body.innerHTML = `
      <div id="row1"><dialog id="grp" data-nowo-ui-confirm data-nowo-confirm-shared data-nowo-confirm-portal></dialog></div>
      <div id="row2"><dialog id="grp" data-nowo-ui-confirm data-nowo-confirm-shared data-nowo-confirm-portal></dialog></div>
      <div id="table"><dialog id="tbl" data-nowo-ui-confirm data-nowo-confirm-shared></dialog></div>`;
    await import('./nowo-ui-confirm');
    const groups = document.querySelectorAll('dialog#grp');
    expect(groups).toHaveLength(1);
    expect(groups[0]!.parentElement).toBe(document.body);
    expect(document.getElementById('tbl')!.parentElement!.id).toBe('table');
  });
});
