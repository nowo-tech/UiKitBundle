import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const MARKUP = `
  <div id="fb" class="nowo-ui-filter-sheet" data-nowo-ui-filter-sheet>
    <div class="nowo-ui-filter-sheet__bar"><button type="button" id="open" aria-controls="fb-dialog" data-nowo-ui-filter-sheet-open>Filters</button></div>
    <div class="nowo-ui-filter-sheet__panel" id="panel" data-nowo-ui-filter-sheet-panel>
      <form method="get"><input type="hidden" name="page" value="1"><input type="search" id="q" name="q" value="ada"><button type="submit">Apply</button></form>
    </div>
    <dialog id="fb-dialog" data-nowo-ui-filter-sheet-dialog>
      <button type="button" id="close" data-nowo-ui-filter-sheet-close>Close</button>
      <div id="slot" data-nowo-ui-filter-sheet-slot></div>
    </dialog>
  </div>`;

describe('nowo-ui-filter-sheet', () => {
  let listeners: Array<() => void>;
  let matches: boolean;
  let mod: typeof import('./nowo-ui-filter-sheet');

  beforeEach(async () => {
    listeners = [];
    matches = true;
    vi.stubGlobal('matchMedia', (query: string) => ({
      get matches() {
        return matches;
      },
      media: query,
      addEventListener: (_: string, cb: () => void) => listeners.push(cb),
    }));
    document.body.innerHTML = MARKUP;
    vi.resetModules();
    mod = await import('./nowo-ui-filter-sheet');
  });

  afterEach(() => {
    document.body.innerHTML = '';
    vi.unstubAllGlobals();
  });

  const dialog = () => document.getElementById('fb-dialog') as HTMLDialogElement;

  it('marks the root ready and the toggle collapsed', () => {
    expect(document.getElementById('fb')!.hasAttribute('data-nowo-ui-filter-sheet-ready')).toBe(true);
    expect(document.getElementById('open')!.getAttribute('aria-expanded')).toBe('false');
  });

  it('opens the sheet with the same panel node and focuses the first field', () => {
    const panel = document.getElementById('panel');
    document.getElementById('open')!.click();
    expect(dialog().open).toBe(true);
    expect(document.getElementById('slot')!.firstElementChild).toBe(panel);
    expect((document.getElementById('q') as HTMLInputElement).value).toBe('ada');
    expect(document.activeElement?.id).toBe('q');
    expect(document.getElementById('open')!.getAttribute('aria-expanded')).toBe('true');
    // A second open is a no-op.
    mod.openSheet(document.getElementById('fb')!);
    expect(document.querySelectorAll('#panel')).toHaveLength(1);
  });

  it('closes from the button: panel back in place, focus on the toggle', () => {
    document.getElementById('open')!.click();
    document.getElementById('close')!.click();
    expect(dialog().open).toBe(false);
    expect(document.getElementById('panel')!.parentElement?.id).toBe('fb');
    expect(document.getElementById('panel')!.nextElementSibling).toBe(dialog());
    expect(document.activeElement?.id).toBe('open');
  });

  it('restores the panel after a native close (Escape) and when the screen grows', () => {
    document.getElementById('open')!.click();
    dialog().close();
    dialog().dispatchEvent(new Event('close'));
    expect(document.getElementById('panel')!.parentElement?.id).toBe('fb');
    document.getElementById('open')!.click();
    matches = false;
    listeners.forEach((cb) => cb());
    expect(dialog().open).toBe(false);
    expect(document.getElementById('panel')!.parentElement?.id).toBe('fb');
  });

  it('falls back to the open attribute without showModal / close', () => {
    const d = dialog();
    Object.defineProperty(d, 'showModal', { value: undefined });
    Object.defineProperty(d, 'close', { value: undefined });
    mod.openSheet(document.getElementById('fb')!);
    expect(d.hasAttribute('open')).toBe(true);
    mod.closeSheet(document.getElementById('fb')!);
    expect(d.hasAttribute('open')).toBe(false);
  });

  it('ignores clicks outside a sheet and roots without the parts', () => {
    document.body.insertAdjacentHTML('beforeend', '<div data-nowo-ui-filter-sheet id="bare"><button data-nowo-ui-filter-sheet-open id="bare-open">x</button></div>');
    mod.upgradeFilterSheets();
    document.getElementById('bare-open')!.click();
    mod.closeSheet(document.getElementById('bare')!);
    document.body.click();
    document.dispatchEvent(new MouseEvent('click'));
    document.getElementById('fb')!.click();
    expect(dialog().open).toBe(false);
  });
});
