/**
 * Dropzone runtime (2.61, partial `dropzone`): enhances [data-nowo-ui-dropzone] boxes around a file input. The input
 * stays the control (stretched over the box, so a click, Enter or Space opens the picker); this script adds drop
 * (the dropped files go into the same input through DataTransfer, then a `change` event), the list of chosen files
 * with their size, the checks of data-nowo-ui-dropzone-max (bytes) and of the input's `accept` (setCustomValidity,
 * so the form does not submit a file the server would refuse), and a polite status line. Labels come from the
 * data-nowo-ui-dropzone-one / -many / -none / -too-large / -wrong-type attributes (%count%, %name%, %size%).
 */

import { announceScript } from './script-banner';

declare const __UI_KIT_BUILD_TIME__: string;

announceScript('ui-kit/dropzone', typeof __UI_KIT_BUILD_TIME__ !== 'undefined' ? __UI_KIT_BUILD_TIME__ : undefined);

const ROOT = '[data-nowo-ui-dropzone]';

export function formatBytes(bytes: number): string {
  if (bytes < 1024) {
    return `${bytes} B`;
  }
  const units = ['KB', 'MB', 'GB'];
  let value = bytes / 1024;
  let unit = 0;
  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024;
    unit += 1;
  }
  return `${value >= 10 ? Math.round(value) : Math.round(value * 10) / 10} ${units[unit]}`;
}

/** Whether a file matches an `accept` list (".pdf", "image/*", "application/pdf"); an empty list accepts all. */
export function accepts(accept: string, file: { name: string; type: string }): boolean {
  const tokens = accept
    .split(',')
    .map((t) => t.trim().toLowerCase())
    .filter((t) => t !== '');
  if (tokens.length === 0) {
    return true;
  }
  const name = file.name.toLowerCase();
  const type = (file.type || '').toLowerCase();
  return tokens.some((token) => {
    if (token.startsWith('.')) {
      return name.endsWith(token);
    }
    if (token.endsWith('/*')) {
      return type.startsWith(token.slice(0, -1));
    }
    return type === token;
  });
}

function text(root: Element, key: string, params: Record<string, string>): string {
  let value = root.getAttribute(`data-nowo-ui-dropzone-${key}`) ?? '';
  Object.keys(params).forEach((name) => {
    value = value.split(name).join(params[name]);
  });
  return value;
}

function inputOf(root: Element): HTMLInputElement | null {
  return root.querySelector<HTMLInputElement>('input[type="file"]');
}

/** Lists the input's files, flags the ones over the limit or of a refused type, updates validity and status. */
export function render(root: Element, announce = true): void {
  const input = inputOf(root);
  const list = root.querySelector<HTMLElement>('[data-nowo-ui-dropzone-files]');
  const status = root.querySelector<HTMLElement>('[data-nowo-ui-dropzone-status]');
  if (!input || !list) {
    return;
  }
  const max = Number.parseInt(root.getAttribute('data-nowo-ui-dropzone-max') ?? '', 10);
  const files = Array.from(input.files ?? []);
  const problems: string[] = [];
  list.textContent = '';
  files.forEach((file) => {
    const item = document.createElement('li');
    item.className = 'nowo-ui-dropzone__file';
    const name = document.createElement('span');
    name.className = 'nowo-ui-dropzone__file-name';
    name.textContent = file.name;
    const size = document.createElement('span');
    size.className = 'nowo-ui-dropzone__file-size';
    size.textContent = formatBytes(file.size);
    item.append(name, size);
    let problem = '';
    if (Number.isFinite(max) && max > 0 && file.size > max) {
      problem = text(root, 'too-large', { '%name%': file.name, '%size%': formatBytes(max) });
    } else if (!accepts(input.accept, file)) {
      problem = text(root, 'wrong-type', { '%name%': file.name });
    }
    if (problem !== '') {
      item.classList.add('nowo-ui-dropzone__file--invalid');
      item.setAttribute('title', problem);
      problems.push(problem);
    }
    list.appendChild(item);
  });
  input.setCustomValidity(problems.join(' '));
  if (status && announce) {
    const count = String(files.length);
    const summary = files.length === 0 ? text(root, 'none', {}) : text(root, files.length === 1 ? 'one' : 'many', { '%count%': count });
    status.textContent = [summary, ...problems].join(' ');
  }
}

/** Puts dropped files into the input (all of them with `multiple`, else the first); false when the browser refuses. */
export function assignFiles(input: HTMLInputElement, files: FileList | File[]): boolean {
  const chosen = Array.from(files).slice(0, input.multiple ? undefined : 1);
  try {
    const transfer = new DataTransfer();
    chosen.forEach((file) => transfer.items.add(file));
    input.files = transfer.files;
  } catch {
    return false;
  }
  input.dispatchEvent(new Event('change', { bubbles: true }));
  return true;
}

function rootFrom(target: EventTarget | null): Element | null {
  return target instanceof Element ? target.closest(ROOT) : null;
}

function onDrag(event: DragEvent): void {
  const root = rootFrom(event.target);
  if (!root) {
    return;
  }
  event.preventDefault();
  if (event.type === 'dragleave') {
    const next = event.relatedTarget;
    if (!(next instanceof Node && root.contains(next))) {
      root.classList.remove('is-dragover');
    }
    return;
  }
  root.classList.add('is-dragover');
}

function onDrop(event: DragEvent): void {
  const root = rootFrom(event.target);
  const input = root ? inputOf(root) : null;
  if (!root || !input) {
    return;
  }
  event.preventDefault();
  root.classList.remove('is-dragover');
  const files = event.dataTransfer?.files;
  if (files && files.length > 0 && !input.disabled) {
    assignFiles(input, files);
  }
}

function onChange(event: Event): void {
  const target = event.target;
  if (target instanceof HTMLInputElement && target.type === 'file') {
    const root = rootFrom(target);
    if (root) {
      render(root);
    }
  }
}

export function upgradeDropzones(scope: ParentNode = document): void {
  scope.querySelectorAll(ROOT).forEach((root) => {
    if (!root.hasAttribute('data-nowo-ui-dropzone-ready') && inputOf(root)) {
      root.setAttribute('data-nowo-ui-dropzone-ready', '');
      render(root, false);
    }
  });
}

let bound = false;

export function bindNowoUiDropzone(): void {
  upgradeDropzones();
  if (bound) {
    return;
  }
  bound = true;
  document.addEventListener('dragenter', onDrag);
  document.addEventListener('dragover', onDrag);
  document.addEventListener('dragleave', onDrag);
  document.addEventListener('drop', onDrop);
  document.addEventListener('change', onChange);
}

bindNowoUiDropzone();
