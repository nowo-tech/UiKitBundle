/**
 * Image input (`js/nowo-ui-image-input.js`, 2.47, opt-in: add `image-input` to `script_names`): the box of the partial
 * `image_input` (FileType option `nowo_ui_image_input`). It shows the stored image, or the default with a "Default" badge, and
 * swaps to a local preview (an object URL) as soon as a file is picked or dropped. Replace opens the file picker, Remove (only
 * when something is stored) ticks the host's remove checkbox and shows what will remain, Undo puts the box and the field back
 * as the page rendered them. A polite live region announces the chosen file name, a removal and an undo.
 *
 * Without this script the box shows the current image above the plain file input and the remove checkbox, and the buttons stay
 * hidden: the form submits as any upload. With it (`.is-enhanced`) the input is visually hidden and out of the tab order; the
 * Replace button is the keyboard path, and the checkbox row is driven by Remove / Undo.
 *
 * Image crop (js/nowo-ui-image-crop.js): the crop step stops the picked file's `change` and dispatches it again after Apply with
 * the crop, so the box shows the cropped result; when the crop's `-preview` targets the box, its object URL is adopted, not
 * duplicated. Cancel leaves the box as it was. A dropped file goes through the same path (the input's `change`).
 *
 * Events on the root (bubbling): `nowo-ui:image-input:change` {state, file}. CSP: no style attribute, no inline script; the
 * preview needs `img-src blob:`. Delegated listeners plus one MutationObserver for boxes inserted later. Importing touches no DOM.
 */
export const ROOT = '[data-nowo-ui-image-input]';
const FILE = '[data-nowo-ui-image-input-file]';
const FRAME = '[data-nowo-ui-image-input-frame]';
const PREVIEW = '[data-nowo-ui-image-input-preview]';
const EMPTY = '[data-nowo-ui-image-input-empty]';
const BADGE = '[data-nowo-ui-image-input-badge]';
const REPLACE = '[data-nowo-ui-image-input-replace]';
const REMOVE = '[data-nowo-ui-image-input-remove]';
const UNDO = '[data-nowo-ui-image-input-undo]';
const REMOVE_FIELD = '[data-nowo-ui-image-input-remove-field]';
const STATUS = '[data-nowo-ui-image-input-status]';

export type State = 'stored' | 'default' | 'empty' | 'picked' | 'removed';

export interface Labels {
  replace: string;
  choose: string;
  remove: string;
  undo: string;
  default: string;
  empty: string;
  selected: string;
  removed: string;
  restored: string;
  drop: string;
  not_image: string;
}

export const DEFAULT_LABELS: Labels = {
  replace: 'Replace image',
  choose: 'Choose image',
  remove: 'Remove image',
  undo: 'Undo',
  default: 'Default',
  empty: 'No image',
  selected: 'Selected: %name%',
  removed: 'The image will be removed when you save.',
  restored: 'Change undone.',
  drop: 'Drop an image here',
  not_image: 'This file is not an image.',
};

interface Box {
  root: HTMLElement;
  input: HTMLInputElement;
  frame: HTMLElement | null;
  preview: HTMLImageElement | null;
  empty: HTMLElement | null;
  badge: HTMLElement | null;
  replace: HTMLButtonElement | null;
  remove: HTMLButtonElement | null;
  undo: HTMLButtonElement | null;
  removeField: HTMLElement | null;
  checkbox: HTMLInputElement | null;
  status: HTMLElement | null;
  labels: Labels;
}

/** Object URLs this script created, per preview image (revoked when replaced or undone). */
const ownUrls = new WeakMap<HTMLImageElement, string>();
/** The last `src` this script set or adopted, per preview image: a different `blob:` src was set by the crop step. */
const seenSrc = new WeakMap<HTMLImageElement, string>();

/** The labels of a box: its JSON over the English defaults; a malformed value keeps the defaults. */
export function readLabels(json: string | null): Labels {
  if (!json) {
    return { ...DEFAULT_LABELS };
  }
  try {
    const parsed: unknown = JSON.parse(json);
    const labels = { ...DEFAULT_LABELS };
    if (parsed && typeof parsed === 'object') {
      for (const key of Object.keys(DEFAULT_LABELS) as (keyof Labels)[]) {
        const value = (parsed as Record<string, unknown>)[key];
        if (typeof value === 'string' && value !== '') {
          labels[key] = value;
        }
      }
    }
    return labels;
  } catch {
    return { ...DEFAULT_LABELS };
  }
}

/** The state the page rendered: `stored` with a current URL, else `default` with a default URL, else `empty`. */
export function initialState(root: Element): 'stored' | 'default' | 'empty' {
  if (root.getAttribute('data-nowo-ui-image-input-current')) {
    return 'stored';
  }
  return root.getAttribute('data-nowo-ui-image-input-default') ? 'default' : 'empty';
}

/** Whether a file is an image the browser can show (by its type, as the picker's `accept` does). */
export const isImage = (file: File): boolean => file.type.startsWith('image/');

function boxOf(root: HTMLElement): Box | null {
  const input = root.querySelector<HTMLInputElement>(FILE);
  if (!input) {
    return null;
  }
  const removeField = root.querySelector<HTMLElement>(REMOVE_FIELD);
  return {
    root,
    input,
    frame: root.querySelector<HTMLElement>(FRAME),
    preview: root.querySelector<HTMLImageElement>(PREVIEW),
    empty: root.querySelector<HTMLElement>(EMPTY),
    badge: root.querySelector<HTMLElement>(BADGE),
    replace: root.querySelector<HTMLButtonElement>(REPLACE),
    remove: root.querySelector<HTMLButtonElement>(REMOVE),
    undo: root.querySelector<HTMLButtonElement>(UNDO),
    removeField,
    checkbox: removeField?.querySelector<HTMLInputElement>('input[type="checkbox"]') ?? null,
    status: root.querySelector<HTMLElement>(STATUS),
    labels: readLabels(root.getAttribute('data-nowo-ui-image-input-labels')),
  };
}

const usable = (box: Box): boolean => !box.input.disabled && !box.root.hasAttribute('data-nowo-ui-image-input-disabled');

function announce(box: Box, message: string): void {
  if (box.status) {
    box.status.textContent = message;
  }
}

function forgetOwnUrl(preview: HTMLImageElement | null): void {
  if (!preview) {
    return;
  }
  const own = ownUrls.get(preview);
  if (own) {
    URL.revokeObjectURL(own);
    ownUrls.delete(preview);
  }
}

function setSrc(preview: HTMLImageElement, src: string | null): void {
  if (src === null) {
    preview.removeAttribute('src');
    preview.hidden = true;
  } else {
    if (preview.getAttribute('src') !== src) {
      preview.setAttribute('src', src);
    }
    preview.hidden = false;
  }
  seenSrc.set(preview, preview.getAttribute('src') ?? '');
}

/** Paints a state: the image (or the empty text), the badge, which buttons show and the Replace / Choose wording. */
export function render(box: Box, state: State, src: string | null = null): void {
  const current = box.root.getAttribute('data-nowo-ui-image-input-current');
  const fallback = box.root.getAttribute('data-nowo-ui-image-input-default');
  const shown =
    state === 'picked' ? src : state === 'stored' ? current : state === 'removed' || state === 'default' ? fallback : null;
  if (box.preview) {
    setSrc(box.preview, shown || null);
  }
  if (box.empty) {
    box.empty.hidden = Boolean(shown);
  }
  if (box.badge) {
    box.badge.hidden = !((state === 'default' || state === 'removed') && fallback);
  }
  if (box.replace) {
    box.replace.textContent = state === 'stored' || state === 'picked' ? box.labels.replace : box.labels.choose;
  }
  if (box.remove) {
    box.remove.hidden = state !== 'stored';
  }
  if (box.undo) {
    box.undo.hidden = state !== 'picked' && state !== 'removed';
  }
  box.root.setAttribute('data-nowo-ui-image-input-state', state);
}

function emit(box: Box, state: State, file: File | null): void {
  box.root.dispatchEvent(new CustomEvent('nowo-ui:image-input:change', { bubbles: true, detail: { state, file } }));
}

/** Shows the input's file (after a pick, a drop or the crop's Apply); no file puts the rendered state back. */
export function showPick(box: Box): void {
  const file = box.input.files?.[0] ?? null;
  if (!file) {
    forgetOwnUrl(box.preview);
    const state = box.checkbox?.checked ? 'removed' : initialState(box.root);
    render(box, state);
    emit(box, state, null);
    return;
  }
  if (box.checkbox?.checked) {
    box.checkbox.checked = false;
    box.checkbox.dispatchEvent(new Event('change', { bubbles: true }));
  }
  let src: string | null = null;
  if (isImage(file) && box.preview) {
    const now = box.preview.getAttribute('src') ?? '';
    if (now.startsWith('blob:') && now !== seenSrc.get(box.preview) && now !== ownUrls.get(box.preview)) {
      // The crop step just showed its result here: adopt it.
      forgetOwnUrl(box.preview);
      src = now;
    } else {
      forgetOwnUrl(box.preview);
      src = URL.createObjectURL(file);
      ownUrls.set(box.preview, src);
    }
  }
  render(box, 'picked', src);
  const name = box.labels.selected.replace('%name%', file.name);
  announce(box, isImage(file) ? name : `${name} ${box.labels.not_image}`);
  emit(box, 'picked', file);
}

/** Undo: no picked file, the remove checkbox unticked, the box as the page rendered it. */
export function undo(box: Box): void {
  box.input.value = '';
  forgetOwnUrl(box.preview);
  if (box.checkbox?.checked) {
    box.checkbox.checked = false;
    box.checkbox.dispatchEvent(new Event('change', { bubbles: true }));
  }
  const state = initialState(box.root);
  render(box, state);
  announce(box, box.labels.restored);
  emit(box, state, null);
  box.replace?.focus();
}

/** Remove: no picked file, the remove checkbox ticked, the box shows what remains (the default, or nothing). */
export function remove(box: Box): void {
  if (!box.checkbox) {
    return;
  }
  box.input.value = '';
  forgetOwnUrl(box.preview);
  box.checkbox.checked = true;
  box.checkbox.dispatchEvent(new Event('change', { bubbles: true }));
  render(box, 'removed');
  announce(box, box.labels.removed);
  emit(box, 'removed', null);
  box.undo?.focus();
}

/** Puts a dropped file in the input and lets it go through `change` (and the crop step, when the input has one). */
export function dropFile(box: Box, file: File): boolean {
  if (!isImage(file)) {
    announce(box, box.labels.not_image);
    return false;
  }
  try {
    const transfer = new DataTransfer();
    transfer.items.add(file);
    // The crop step records the list before a pick on `drop` at the input.
    box.input.dispatchEvent(new Event('drop', { bubbles: true }));
    box.input.files = transfer.files;
  } catch {
    return false;
  }
  if (box.input.files?.[0] !== file) {
    return false;
  }
  box.input.dispatchEvent(new Event('input', { bubbles: true }));
  box.input.dispatchEvent(new Event('change', { bubbles: true }));
  return true;
}

/** Turns a rendered box into the enhanced one (once): hidden input, visible buttons, checkbox row driven by Remove / Undo. */
export function enhance(root: HTMLElement): void {
  if (root.classList.contains('is-enhanced')) {
    return;
  }
  const box = boxOf(root);
  if (!box || !usable(box)) {
    return;
  }
  root.classList.add('is-enhanced');
  box.input.tabIndex = -1;
  box.input.setAttribute('aria-hidden', 'true');
  if (box.replace) {
    box.replace.hidden = false;
    box.replace.setAttribute('aria-controls', box.input.id);
  }
  if (box.removeField) {
    box.removeField.hidden = true;
  }
  if (box.preview) {
    seenSrc.set(box.preview, box.preview.getAttribute('src') ?? '');
  }
  if (box.input.files?.length) {
    showPick(box);
  } else if (box.checkbox?.checked) {
    render(box, 'removed');
  } else {
    render(box, initialState(root));
  }
}

/** Enhances every box in a scope. */
export function enhanceAll(scope: ParentNode = document): void {
  if (scope instanceof HTMLElement && scope.matches(ROOT)) {
    enhance(scope);
  }
  scope.querySelectorAll<HTMLElement>(ROOT).forEach(enhance);
}

function enhancedBox(target: EventTarget | null): Box | null {
  if (!(target instanceof Element)) {
    return null;
  }
  const root = target.closest<HTMLElement>(ROOT);
  if (!root || !root.classList.contains('is-enhanced')) {
    return null;
  }
  const box = boxOf(root);
  return box && usable(box) ? box : null;
}

function onChange(event: Event): void {
  const target = event.target;
  if (!(target instanceof HTMLInputElement) || !target.matches(FILE)) {
    return;
  }
  const box = enhancedBox(target);
  if (box) {
    showPick(box);
  }
}

function onClick(event: Event): void {
  const target = event.target;
  if (!(target instanceof Element)) {
    return;
  }
  const button = target.closest<HTMLElement>(`${REPLACE}, ${REMOVE}, ${UNDO}`);
  const box = button ? enhancedBox(button) : null;
  if (!button || !box || button.hidden) {
    return;
  }
  event.preventDefault();
  if (button.matches(REPLACE)) {
    box.input.click();
  } else if (button.matches(REMOVE)) {
    remove(box);
  } else {
    undo(box);
  }
}

const hasFiles = (event: DragEvent): boolean => [...(event.dataTransfer?.types ?? [])].includes('Files');

function onDrag(event: Event): void {
  const drag = event as DragEvent;
  const frame = drag.target instanceof Element ? drag.target.closest<HTMLElement>(FRAME) : null;
  const box = frame ? enhancedBox(frame) : null;
  if (!frame || !box || !hasFiles(drag)) {
    return;
  }
  drag.preventDefault();
  if (drag.type === 'dragenter' || drag.type === 'dragover') {
    frame.classList.add('is-dragover');
    if (drag.dataTransfer) {
      drag.dataTransfer.dropEffect = 'copy';
    }
    return;
  }
  frame.classList.remove('is-dragover');
  const file = drag.type === 'drop' ? drag.dataTransfer?.files?.[0] : undefined;
  if (file) {
    dropFile(box, file);
  }
}

function onReset(event: Event): void {
  if (!(event.target instanceof HTMLFormElement)) {
    return;
  }
  const form = event.target;
  // The form's own reset runs after this event: paint the boxes once it has.
  window.setTimeout(() => {
    form.querySelectorAll<HTMLElement>(`${ROOT}.is-enhanced`).forEach((root) => {
      const box = boxOf(root);
      if (box) {
        forgetOwnUrl(box.preview);
        render(box, box.checkbox?.checked ? 'removed' : initialState(root));
      }
    });
  }, 0);
}

let bound = false;

export function bindNowoUiImageInput(): void {
  if (bound) {
    return;
  }
  bound = true;
  document.addEventListener('change', onChange);
  document.addEventListener('click', onClick);
  for (const type of ['dragenter', 'dragover', 'dragleave', 'drop']) {
    document.addEventListener(type, onDrag);
  }
  document.addEventListener('reset', onReset, true);
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => enhanceAll());
  } else {
    enhanceAll();
  }
  // Boxes that arrive later (a remote form dialog, a Turbo frame or a morph).
  new MutationObserver((records) => {
    for (const record of records) {
      record.addedNodes.forEach((node) => {
        if (node instanceof Element) {
          enhanceAll(node);
        }
      });
    }
  }).observe(document, { childList: true, subtree: true });
}

// A second copy of the script must not bind again. A symbol: an element with some id cannot turn this off.
const BOUND_KEY = Symbol.for('nowo-ui.image-input.bound');
if (typeof document !== 'undefined' && !(window as unknown as Record<symbol, unknown>)[BOUND_KEY]) {
  (window as unknown as Record<symbol, unknown>)[BOUND_KEY] = true;
  bindNowoUiImageInput();
}
