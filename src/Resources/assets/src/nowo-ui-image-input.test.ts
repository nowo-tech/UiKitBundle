import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { bindNowoUiImageInput, DEFAULT_LABELS, dropFile, enhance, enhanceAll, initialState, isImage, readLabels } from './nowo-ui-image-input';

const LABELS = JSON.stringify({ ...DEFAULT_LABELS, selected: 'Picked: %name%', removed: 'Will be removed', restored: 'Undone' });

const box = (id: string, { current = '/media/logo.png', fallback = '/img/default.svg', remove = true, disabled = false } = {}): string => `
<form id="${id}-form">
  <div id="${id}" data-nowo-ui-image-input data-nowo-ui-image-input-labels='${LABELS}'${current ? ` data-nowo-ui-image-input-current="${current}"` : ''}${fallback ? ` data-nowo-ui-image-input-default="${fallback}"` : ''}>
    <div id="${id}-frame" data-nowo-ui-image-input-frame>
      <img id="${id}-preview" data-nowo-ui-image-input-preview${current || fallback ? ` src="${current || fallback}"` : ' hidden'} alt="Logo">
      <span id="${id}-empty" data-nowo-ui-image-input-empty${current || fallback ? ' hidden' : ''}>No image</span>
      <span id="${id}-badge" data-nowo-ui-image-input-badge${current || !fallback ? ' hidden' : ''}>Default</span>
    </div>
    <div>
      <input id="${id}-file" type="file" name="logo" data-nowo-ui-image-input-file${disabled ? ' disabled' : ''}>
      <button id="${id}-replace" type="button" data-nowo-ui-image-input-replace hidden>Replace image</button>
      ${remove && current ? `<button id="${id}-remove" type="button" data-nowo-ui-image-input-remove hidden>Remove image</button>` : ''}
      <button id="${id}-undo" type="button" data-nowo-ui-image-input-undo hidden>Undo</button>
    </div>
    ${remove && current ? `<div id="${id}-remove-field" data-nowo-ui-image-input-remove-field><label><input id="${id}-check" type="checkbox" name="removeLogo" value="1"> Remove</label></div>` : ''}
    <p id="${id}-status" data-nowo-ui-image-input-status role="status"></p>
  </div>
</form>`;

const el = <T extends HTMLElement = HTMLElement>(id: string): T => document.getElementById(id) as T;
const img = (id: string): HTMLImageElement => el<HTMLImageElement>(`${id}-preview`);
const photo = (name = 'photo.png', type = 'image/png'): File => new File([new Uint8Array(16)], name, { type });

function pick(input: HTMLInputElement, file: File | null): void {
  const transfer = new DataTransfer();
  if (file) {
    transfer.items.add(file);
  }
  input.files = transfer.files;
  input.dispatchEvent(new Event('input', { bubbles: true }));
  input.dispatchEvent(new Event('change', { bubbles: true }));
}

let urls = 0;

describe('nowo-ui-image-input', () => {
  beforeEach(() => {
    urls = 0;
    vi.spyOn(URL, 'createObjectURL').mockImplementation(() => `blob:local/${++urls}`);
    vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => undefined);
    document.body.innerHTML = box('a') + box('b', { current: '', remove: false }) + box('c', { current: '', fallback: '' }) + box('d', { disabled: true });
    bindNowoUiImageInput();
    enhanceAll();
  });

  afterEach(() => {
    document.body.innerHTML = '';
    vi.restoreAllMocks();
  });

  it('enhances: visible buttons, hidden input and checkbox row; a disabled field stays plain', () => {
    expect(el('a').classList.contains('is-enhanced')).toBe(true);
    expect(el('a-replace').hidden).toBe(false);
    expect(el('a-replace').getAttribute('aria-controls')).toBe('a-file');
    expect(el('a-remove').hidden).toBe(false);
    expect(el('a-undo').hidden).toBe(true);
    expect(el('a-remove-field').hidden).toBe(true);
    expect(el<HTMLInputElement>('a-file').tabIndex).toBe(-1);
    expect(el('a').getAttribute('data-nowo-ui-image-input-state')).toBe('stored');
    expect(el('b').getAttribute('data-nowo-ui-image-input-state')).toBe('default');
    expect(el('b-replace').textContent).toBe('Choose image');
    expect(el('b-badge').hidden).toBe(false);
    expect(el('c').getAttribute('data-nowo-ui-image-input-state')).toBe('empty');
    expect(img('c').hidden).toBe(true);
    expect(el('c-empty').hidden).toBe(false);
    expect(el('d').classList.contains('is-enhanced')).toBe(false);
    expect(el('d-replace').hidden).toBe(true);
    enhance(el('a'));
    expect(el('a').classList.contains('is-enhanced')).toBe(true);
  });

  it('a pick shows the local preview at once, announces the name and Undo puts the stored image back', () => {
    const changes: string[] = [];
    el('a').addEventListener('nowo-ui:image-input:change', (event) => changes.push((event as CustomEvent).detail.state));
    pick(el<HTMLInputElement>('a-file'), photo('me.png'));
    expect(img('a').getAttribute('src')).toBe('blob:local/1');
    expect(el('a-status').textContent).toBe('Picked: me.png');
    expect(el('a-undo').hidden).toBe(false);
    expect(el('a-remove').hidden).toBe(true);
    expect(el('a-replace').textContent).toBe('Replace image');

    pick(el<HTMLInputElement>('a-file'), photo('other.png'));
    expect(img('a').getAttribute('src')).toBe('blob:local/2');
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:local/1');

    el('a-undo').click();
    expect(el<HTMLInputElement>('a-file').value).toBe('');
    expect(img('a').getAttribute('src')).toBe('/media/logo.png');
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:local/2');
    expect(el('a-status').textContent).toBe('Undone');
    expect(document.activeElement).toBe(el('a-replace'));
    expect(changes).toEqual(['picked', 'picked', 'stored']);
  });

  it('a default box: the pick hides the badge, Undo shows the default again', () => {
    pick(el<HTMLInputElement>('b-file'), photo());
    expect(el('b-badge').hidden).toBe(true);
    expect(el('b-replace').textContent).toBe('Replace image');
    el('b-undo').click();
    expect(img('b').getAttribute('src')).toBe('/img/default.svg');
    expect(el('b-badge').hidden).toBe(false);
  });

  it('Remove ticks the checkbox and shows the default; a pick unticks it; an emptied input keeps the removal', () => {
    const check = el<HTMLInputElement>('a-check');
    el('a-remove').click();
    expect(check.checked).toBe(true);
    expect(el('a').getAttribute('data-nowo-ui-image-input-state')).toBe('removed');
    expect(img('a').getAttribute('src')).toBe('/img/default.svg');
    expect(el('a-badge').hidden).toBe(false);
    expect(el('a-status').textContent).toBe('Will be removed');
    expect(document.activeElement).toBe(el('a-undo'));

    pick(el<HTMLInputElement>('a-file'), photo());
    expect(check.checked).toBe(false);
    expect(el('a').getAttribute('data-nowo-ui-image-input-state')).toBe('picked');

    el('a-remove').click();
    expect(check.checked).toBe(false);
    el('a-undo').click();
    el('a-remove').click();
    expect(check.checked).toBe(true);
    pick(el<HTMLInputElement>('a-file'), null);
    expect(el('a').getAttribute('data-nowo-ui-image-input-state'), 'an emptied input keeps the removal').toBe('removed');
    expect(check.checked).toBe(true);
  });

  it('without a default, Remove leaves the empty box', () => {
    document.body.innerHTML = box('e', { fallback: '' });
    enhanceAll();
    el('e-remove').click();
    expect(img('e').hidden).toBe(true);
    expect(img('e').hasAttribute('src')).toBe(false);
    expect(el('e-empty').hidden).toBe(false);
    expect(el('e-badge').hidden).toBe(true);
    el('e-undo').click();
    expect(img('e').getAttribute('src')).toBe('/media/logo.png');
  });

  it('clearing the input of a picked box without checkbox goes back to the rendered state', () => {
    pick(el<HTMLInputElement>('c-file'), photo());
    expect(img('c').hidden).toBe(false);
    pick(el<HTMLInputElement>('c-file'), null);
    expect(img('c').hidden).toBe(true);
    expect(el('c').getAttribute('data-nowo-ui-image-input-state')).toBe('empty');
  });

  it('adopts the crop step preview (a blob it did not set) instead of making another URL', () => {
    img('a').setAttribute('src', 'blob:crop/9');
    pick(el<HTMLInputElement>('a-file'), photo('crop.webp', 'image/webp'));
    expect(img('a').getAttribute('src')).toBe('blob:crop/9');
    expect(URL.createObjectURL).not.toHaveBeenCalled();
    // A later pick that the crop step did not preview gets its own URL.
    pick(el<HTMLInputElement>('a-file'), photo('raw.svg', 'image/svg+xml'));
    expect(img('a').getAttribute('src')).toBe('blob:local/1');
  });

  it('a file that is not an image: no preview, the status says so', () => {
    pick(el<HTMLInputElement>('a-file'), photo('doc.pdf', 'application/pdf'));
    expect(img('a').hidden).toBe(true);
    expect(el('a-empty').hidden).toBe(false);
    expect(el('a-status').textContent).toBe('Picked: doc.pdf This file is not an image.');
  });

  it('Replace opens the picker; clicks elsewhere and on a plain box do nothing', () => {
    const click = vi.spyOn(el<HTMLInputElement>('a-file'), 'click');
    el('a-replace').click();
    expect(click).toHaveBeenCalledTimes(1);
    el('a-status').click();
    el('d-replace').click();
    document.body.dispatchEvent(new Event('click', { bubbles: true }));
    expect(click).toHaveBeenCalledTimes(1);
  });

  it('drag and drop: highlight while dragging files, drop goes through change', () => {
    const frame = el('a-frame');
    const drag = (type: string, files: File[] = [], types = ['Files']): DragEvent => {
      const event = new Event(type, { bubbles: true, cancelable: true }) as DragEvent;
      const transfer = { types, files, dropEffect: 'none' };
      Object.defineProperty(event, 'dataTransfer', { value: transfer });
      frame.dispatchEvent(event);
      return event;
    };
    expect(drag('dragenter').defaultPrevented).toBe(true);
    expect(frame.classList.contains('is-dragover')).toBe(true);
    expect(drag('dragover').defaultPrevented).toBe(true);
    drag('dragleave');
    expect(frame.classList.contains('is-dragover')).toBe(false);
    expect(drag('dragover', [], ['text/plain']).defaultPrevented).toBe(false);

    const drops: string[] = [];
    el<HTMLInputElement>('a-file').addEventListener('drop', () => drops.push('drop'));
    drag('drop', [photo('dropped.png')]);
    expect(drops).toEqual(['drop']);
    expect(el<HTMLInputElement>('a-file').files?.[0]?.name).toBe('dropped.png');
    expect(el('a-status').textContent).toBe('Picked: dropped.png');

    drag('drop', [photo('notes.txt', 'text/plain')]);
    expect(el('a-status').textContent).toBe('This file is not an image.');
    drag('drop', []);
    expect(el('a-status').textContent).toBe('This file is not an image.');
  });

  it('dropFile: false without DataTransfer or when the input refuses the list', () => {
    const root = el('a');
    const saved = globalThis.DataTransfer;
    // @ts-expect-error: a browser without DataTransfer
    globalThis.DataTransfer = undefined;
    try {
      expect(dropFile(boxFor(root), photo())).toBe(false);
    } finally {
      globalThis.DataTransfer = saved;
    }
    const input = el<HTMLInputElement>('a-file');
    Object.defineProperty(input, 'files', { configurable: true, get: () => null, set: () => undefined });
    expect(dropFile(boxFor(root), photo())).toBe(false);
  });

  it('a form reset paints the rendered state again', () => {
    vi.useFakeTimers();
    try {
      pick(el<HTMLInputElement>('a-file'), photo());
      el<HTMLFormElement>('a-form').reset();
      vi.runAllTimers();
      expect(el('a').getAttribute('data-nowo-ui-image-input-state')).toBe('stored');
      expect(img('a').getAttribute('src')).toBe('/media/logo.png');
      document.body.dispatchEvent(new Event('reset', { bubbles: true }));
      vi.runAllTimers();
    } finally {
      vi.useRealTimers();
    }
  });

  it('a box inserted later is enhanced; one rendered with the checkbox ticked starts removed', async () => {
    const wrap = document.createElement('div');
    wrap.innerHTML = box('late').replace('type="checkbox" name="removeLogo" value="1"', 'type="checkbox" name="removeLogo" value="1" checked');
    document.body.append(wrap);
    await vi.waitFor(() => expect(el('late').classList.contains('is-enhanced')).toBe(true));
    expect(el('late').getAttribute('data-nowo-ui-image-input-state')).toBe('removed');
    document.body.append(document.createTextNode('text'));
  });

  it('a box whose input already holds a file (back navigation) shows it', () => {
    document.body.innerHTML = box('f');
    const transfer = new DataTransfer();
    transfer.items.add(photo('kept.png'));
    el<HTMLInputElement>('f-file').files = transfer.files;
    enhance(el('f'));
    expect(el('f').getAttribute('data-nowo-ui-image-input-state')).toBe('picked');
    expect(el('f-status').textContent).toBe('Picked: kept.png');
  });

  it('a root without its input is left alone; change events of other inputs are ignored', () => {
    document.body.innerHTML = '<div id="g" data-nowo-ui-image-input></div><input id="other" type="file"><input id="loose" type="file" data-nowo-ui-image-input-file>';
    enhanceAll(el('g'));
    expect(el('g').classList.contains('is-enhanced')).toBe(false);
    el('other').dispatchEvent(new Event('change', { bubbles: true }));
    el('loose').dispatchEvent(new Event('change', { bubbles: true }));
    document.dispatchEvent(new Event('change'));
  });

  it('readLabels, initialState and isImage', () => {
    expect(readLabels(null)).toEqual(DEFAULT_LABELS);
    expect(readLabels('{')).toEqual(DEFAULT_LABELS);
    expect(readLabels('"x"')).toEqual(DEFAULT_LABELS);
    expect(readLabels('{"undo":"Back","remove":3,"empty":""}').undo).toBe('Back');
    expect(readLabels('{"undo":"Back","remove":3,"empty":""}').remove).toBe('Remove image');
    const root = document.createElement('div');
    expect(initialState(root)).toBe('empty');
    root.setAttribute('data-nowo-ui-image-input-default', '/d.png');
    expect(initialState(root)).toBe('default');
    root.setAttribute('data-nowo-ui-image-input-current', '/c.png');
    expect(initialState(root)).toBe('stored');
    expect(isImage(photo())).toBe(true);
    expect(isImage(photo('a.pdf', 'application/pdf'))).toBe(false);
  });

  it('binding twice is a no-op', () => {
    bindNowoUiImageInput();
    const click = vi.spyOn(el<HTMLInputElement>('a-file'), 'click');
    el('a-replace').click();
    expect(click).toHaveBeenCalledTimes(1);
  });
});

/** The box shape dropFile needs (the module builds the same from the DOM). */
function boxFor(root: HTMLElement): Parameters<typeof dropFile>[0] {
  return {
    root,
    input: root.querySelector('[data-nowo-ui-image-input-file]') as HTMLInputElement,
    frame: null,
    preview: null,
    empty: null,
    badge: null,
    replace: null,
    remove: null,
    undo: null,
    removeField: null,
    checkbox: null,
    status: root.querySelector('[data-nowo-ui-image-input-status]'),
    labels: DEFAULT_LABELS,
  };
}
