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

  it('focuses the first field that takes the focus (a disabled control is skipped)', () => {
    const form = document.querySelector('#panel form')!;
    form.insertAdjacentHTML('afterbegin', '<button type="button" id="fold" disabled>Fold</button>');
    document.getElementById('open')!.click();
    expect(document.activeElement?.id).toBe('q');
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

  it('never opens through the open attribute: without showModal the sheet stays off and the fields inline', async () => {
    document.body.innerHTML = MARKUP.replace('id="fb"', 'id="fb2"');
    const d = dialog();
    Object.defineProperty(d, 'showModal', { value: undefined });
    mod.upgradeFilterSheets();
    const root = document.getElementById('fb2')!;
    expect(root.hasAttribute('data-nowo-ui-filter-sheet-ready')).toBe(false);
    mod.openSheet(root);
    expect(d.hasAttribute('open')).toBe(false);
    expect(document.getElementById('panel')!.parentElement?.id).toBe('fb2');
    // an open dialog without close() is left alone (no attribute removal either)
    d.setAttribute('open', '');
    Object.defineProperty(d, 'close', { value: undefined });
    mod.closeSheet(root);
    expect(d.hasAttribute('open')).toBe(true);
  });

  it('leaves the focus where the browser returned it when it is outside the sheet', () => {
    const toggle = document.getElementById('open') as HTMLButtonElement;
    const other = document.createElement('button');
    document.body.appendChild(other);
    toggle.click();
    // the browser restored the focus to the invoker (or elsewhere) before the close event
    dialog().close();
    other.focus();
    dialog().dispatchEvent(new Event('close'));
    expect(document.getElementById('panel')!.parentElement?.id).toBe('fb');
    expect(document.activeElement).toBe(other);
    expect(toggle.getAttribute('aria-expanded')).toBe('false');
  });

  it('focuses the button itself when the panel moved back and the focus was left on the body', () => {
    const toggle = document.getElementById('open') as HTMLButtonElement;
    toggle.click();
    // the invoker never had the focus (e.g. Safari does not focus a tapped button): nothing to return to
    (document.activeElement as HTMLElement | null)?.blur();
    expect(document.activeElement).toBe(document.body);
    dialog().close();
    dialog().dispatchEvent(new Event('close'));
    expect(document.getElementById('panel')!.parentElement?.id).toBe('fb');
    expect(document.activeElement).toBe(toggle);
  });

  it('ignores a close event once the sheet lost its parts', () => {
    document.getElementById('slot')!.remove();
    expect(() => dialog().dispatchEvent(new Event('close'))).not.toThrow();
    expect(document.getElementById('open')!.getAttribute('aria-expanded')).toBe('false');
  });

  it('does not touch the focus when the panel was not moved', () => {
    const toggle = document.getElementById('open') as HTMLButtonElement;
    const other = document.createElement('button');
    document.body.appendChild(other);
    other.focus();
    mod.closeSheet(document.getElementById('fb')!);
    expect(document.activeElement).toBe(other);
    expect(toggle.getAttribute('aria-expanded')).toBe('false');
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
