import { Application } from '@hotwired/stimulus';
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

import MultiSelectController, { type TomSelectConstructor } from './multi_select_controller';

/** Waits for the dynamic import and Stimulus' connect (a few macrotasks). */
const settle = async (): Promise<void> => {
  for (let i = 0; i < 5; i++) {
    await new Promise((r) => setTimeout(r, 0));
  }
};

class BrokenImportController extends MultiSelectController {
  protected override tomSelectFactory(): Promise<TomSelectConstructor> {
    return Promise.reject(new Error('no tom-select'));
  }
}

class ThrowingConstructorController extends MultiSelectController {
  protected override tomSelectFactory(): Promise<TomSelectConstructor> {
    return Promise.resolve(
      class {
        constructor() {
          throw new Error('bad settings');
        }
      } as unknown as TomSelectConstructor,
    );
  }
}

let app: Application;

// The peer imports Tom Select on demand: load it once so the first test does not wait for a cold import.
beforeAll(async () => {
  await import('tom-select');
});

const statusSelect = (attrs = '', options = ''): string => `
  <form>
    <label for="status">Status</label>
    <select id="status" name="status[]" multiple class="nowo-ui-input nowo-ui-multi-select" placeholder="Any"
            data-controller="multi-select" ${attrs}>
      <option value="active" selected>Active</option>
      <option value="cancelled">Cancelled</option>
      <option value="pending" selected>Pending</option>
      ${options}
    </select>
  </form>`;

const select = (): HTMLSelectElement => document.querySelector('select')!;
const chosen = (): string[] => [...select().selectedOptions].map((o) => o.value);
/** Chosen values as Tom Select keeps them (happy-dom has no `option:checked`, so the native sync is not observable). */
const items = (identifier = 'multi-select'): string[] =>
  (app.getControllerForElementAndIdentifier(select(), identifier) as MultiSelectController).tomSelect?.items ?? [];

beforeEach(() => {
  app = Application.start();
  app.register('multi-select', MultiSelectController);
  app.register('broken-multi-select', BrokenImportController);
  app.register('throwing-multi-select', ThrowingConstructorController);
});

afterEach(() => {
  app.stop();
  document.body.innerHTML = '';
  vi.restoreAllMocks();
});

describe('multi-select peer', () => {
  it('enhances a native multi-select with chips, keeping the select classes off the wrapper', async () => {
    const ready = vi.fn();
    document.addEventListener('multi-select:ready', ready);
    document.body.innerHTML = statusSelect(`data-multi-select-labels-value='{"remove":"Quitar","noResults":"Sin <resultados>"}'`);
    await settle();

    const wrapper = document.querySelector('.ts-wrapper')!;
    expect(wrapper).not.toBeNull();
    expect(wrapper.classList.contains('nowo-ui-multi-select')).toBe(true);
    expect(wrapper.classList.contains('multi')).toBe(true);
    expect(wrapper.classList.contains('nowo-ui-input')).toBe(false);
    expect(select().classList.contains('nowo-ui-input')).toBe(true);
    expect(select().classList.contains('tomselected')).toBe(true);
    expect(wrapper.querySelector('.ts-control')!.classList.contains('nowo-ui-multi-select__control')).toBe(true);

    const items = [...wrapper.querySelectorAll('.item')];
    expect(items.map((i) => i.getAttribute('data-value'))).toEqual(['active', 'pending']);
    const remove = items[0]!.querySelector<HTMLElement>('.remove')!;
    expect(remove.title).toBe('Quitar');

    // The dropdown lives on <body> (a card with overflow: hidden does not clip it).
    const dropdown = document.querySelector('.ts-dropdown')!;
    expect(dropdown.classList.contains('nowo-ui-multi-select__dropdown')).toBe(true);
    expect(dropdown.parentElement).toBe(document.body);

    expect(ready).toHaveBeenCalledTimes(1);
    const controller = app.getControllerForElementAndIdentifier(select(), 'multi-select') as MultiSelectController;
    expect(controller.tomSelect).not.toBeNull();
    document.removeEventListener('multi-select:ready', ready);

    // The placeholder of the select and the no-results label (escaped).
    const settings = controller.settings(select());
    const render = settings.render as { no_results: () => string };
    expect(render.no_results()).toBe('<div class="no-results">Sin &lt;resultados&gt;</div>');
    expect((wrapper.querySelector('input') as HTMLInputElement).getAttribute('placeholder')).toBe('Any');
  });

  it('removing a chip removes the value', async () => {
    document.body.innerHTML = statusSelect();
    await settle();
    expect(items()).toEqual(['active', 'pending']);

    const remove = document.querySelector<HTMLElement>('.item[data-value="active"] .remove')!;
    remove.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect(items()).toEqual(['pending']);
    expect(document.querySelector('.item[data-value="active"]')).toBeNull();
  });

  it('uses English labels by default and merges the options value over the defaults', async () => {
    document.body.innerHTML = statusSelect(`data-multi-select-options-value='{"maxItems":2,"hideSelected":false}'`);
    await settle();
    const controller = app.getControllerForElementAndIdentifier(select(), 'multi-select') as MultiSelectController;
    const settings = controller.settings(select());
    expect(settings.maxItems).toBe(2);
    expect(settings.hideSelected).toBe(false);
    expect(settings.closeAfterSelect).toBe(false);
    expect(settings.plugins).toEqual({ remove_button: { title: 'Remove', label: '×' } });
    expect((settings.render as { no_results: () => string }).no_results()).toContain('No results');
  });

  it('enhances a single select too (one item, closes after select)', async () => {
    document.body.innerHTML = `<select name="owner" data-controller="multi-select"><option value="a">A</option><option value="b">B</option></select>`;
    await settle();
    const controller = app.getControllerForElementAndIdentifier(select(), 'multi-select') as MultiSelectController;
    const settings = controller.settings(select());
    expect(settings.maxItems).toBe(1);
    expect(settings.closeAfterSelect).toBe(true);
    expect(document.querySelector('.ts-wrapper.single')).not.toBeNull();
  });

  it('keeps the dropdown inline inside a dialog, and honours an explicit dropdown parent', async () => {
    document.body.innerHTML = `<dialog open>${statusSelect()}</dialog>`;
    await settle();
    expect(document.querySelector('.ts-dropdown')!.parentElement!.classList.contains('ts-wrapper')).toBe(true);

    app.stop();
    document.body.innerHTML = statusSelect('data-multi-select-dropdown-parent-value="inline"');
    app = Application.start();
    app.register('multi-select', MultiSelectController);
    await settle();
    expect(document.querySelector('.ts-dropdown')!.parentElement!.classList.contains('ts-wrapper')).toBe(true);

    app.stop();
    document.body.innerHTML = `<dialog open>${statusSelect('data-multi-select-dropdown-parent-value="body"')}</dialog>`;
    app = Application.start();
    app.register('multi-select', MultiSelectController);
    await settle();
    expect(document.querySelector('.ts-dropdown')!.parentElement).toBe(document.body);
  });

  it('marks the wrapper invalid when the select is', async () => {
    document.body.innerHTML = statusSelect('aria-invalid="true"');
    await settle();
    expect(document.querySelector('.ts-wrapper')!.classList.contains('nowo-ui-multi-select--invalid')).toBe(true);
  });

  it('restores the native select on disconnect and keeps the chosen values', async () => {
    document.body.innerHTML = statusSelect();
    await settle();
    document.querySelector<HTMLElement>('.item[data-value="active"] .remove')!.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect(items()).toEqual(['pending']);

    // happy-dom reports Tom Select's DOM insertions as a second element match, so the lifecycle is driven directly.
    const controller = app.getControllerForElementAndIdentifier(select(), 'multi-select') as MultiSelectController;
    controller.disconnect();
    expect(document.querySelector('.ts-wrapper')).toBeNull();
    expect(controller.tomSelect).toBeNull();
    expect(select().classList.contains('tomselected')).toBe(false);
    expect(select().classList.contains('nowo-ui-input')).toBe(true);
    expect(chosen()).toEqual(['pending']);
    expect('tomselect' in select()).toBe(false);
    controller.disconnect();

    // Connecting again enhances it again.
    controller.connect();
    await settle();
    expect(document.querySelectorAll('.ts-wrapper')).toHaveLength(1);
    expect(controller.tomSelect!.items).toEqual(['pending']);
  });

  it('does nothing on an element that is not a select', async () => {
    document.body.innerHTML = `<div data-controller="multi-select"></div>`;
    await settle();
    const controller = app.getControllerForElementAndIdentifier(document.querySelector('div')!, 'multi-select') as MultiSelectController;
    expect(controller.tomSelect).toBeNull();
    expect(document.querySelector('.ts-wrapper')).toBeNull();
    // disconnect without an instance is a no-op
    document.body.innerHTML = '';
    await settle();
  });

  it('skips a select removed before Tom Select loaded, and one already enhanced', async () => {
    document.body.innerHTML = statusSelect();
    document.body.innerHTML = '';
    await settle();
    expect(document.querySelector('.ts-wrapper')).toBeNull();

    // Another script enhanced it first: the peer leaves it alone.
    document.body.innerHTML = statusSelect().replace('data-controller="multi-select"', '');
    Object.defineProperty(select(), 'tomselect', { value: {}, configurable: true });
    select().setAttribute('data-controller', 'multi-select');
    await settle();
    expect(document.querySelector('.ts-wrapper')).toBeNull();
  });

  it('keeps the native multi-select when Tom Select cannot be loaded', async () => {
    const unavailable = vi.fn();
    document.addEventListener('broken-multi-select:unavailable', unavailable);
    document.body.innerHTML = statusSelect().replace('data-controller="multi-select"', 'data-controller="broken-multi-select"');
    await settle();
    expect(unavailable).toHaveBeenCalledTimes(1);
    expect((unavailable.mock.calls[0]![0] as CustomEvent<{ error: Error }>).detail.error.message).toBe('no tom-select');
    expect(document.querySelector('.ts-wrapper')).toBeNull();
    expect(chosen()).toEqual(['active', 'pending']);
    document.removeEventListener('broken-multi-select:unavailable', unavailable);
  });

  it('keeps the select classes when Tom Select throws', async () => {
    const unavailable = vi.fn();
    document.addEventListener('throwing-multi-select:unavailable', unavailable);
    document.body.innerHTML = statusSelect().replace('data-controller="multi-select"', 'data-controller="throwing-multi-select"');
    await settle();
    expect(unavailable).toHaveBeenCalledTimes(1);
    expect(select().className).toBe('nowo-ui-input nowo-ui-multi-select');
    document.removeEventListener('throwing-multi-select:unavailable', unavailable);
  });
});
