import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as mod from './nowo-ui-dropzone';

function file(name: string, size: number, type: string): File {
  const f = new File(['x'], name, { type });
  Object.defineProperty(f, 'size', { value: size });
  return f;
}

function setFiles(input: HTMLInputElement, files: File[]): void {
  Object.defineProperty(input, 'files', { configurable: true, writable: true, value: files });
}

describe('nowo-ui-dropzone', () => {
  let root: HTMLElement;
  let input: HTMLInputElement;

  beforeEach(() => {
    document.body.innerHTML = `
      <form><div class="nowo-ui-dropzone" data-nowo-ui-dropzone data-nowo-ui-dropzone-max="1000"
        data-nowo-ui-dropzone-one="1 file selected" data-nowo-ui-dropzone-many="%count% files selected"
        data-nowo-ui-dropzone-none="No file selected" data-nowo-ui-dropzone-too-large="%name% is larger than %size%"
        data-nowo-ui-dropzone-wrong-type="%name% is not an accepted type">
        <input type="file" id="f" name="f" accept=".pdf,image/*" multiple>
        <ul data-nowo-ui-dropzone-files></ul>
        <p role="status" data-nowo-ui-dropzone-status></p>
      </div></form>`;
    root = document.querySelector('[data-nowo-ui-dropzone]')!;
    input = document.getElementById('f') as HTMLInputElement;
    mod.bindNowoUiDropzone();
  });

  afterEach(() => {
    document.body.innerHTML = '';
    vi.unstubAllGlobals();
  });

  it('formats sizes and matches accept lists', () => {
    expect(mod.formatBytes(512)).toBe('512 B');
    expect(mod.formatBytes(1536)).toBe('1.5 KB');
    expect(mod.formatBytes(20 * 1024 * 1024)).toBe('20 MB');
    expect(mod.formatBytes(5 * 1024 ** 4)).toBe('5120 GB');
    expect(mod.accepts('', { name: 'a.exe', type: '' })).toBe(true);
    expect(mod.accepts('.PDF', { name: 'a.pdf', type: '' })).toBe(true);
    expect(mod.accepts('image/*', { name: 'a', type: 'image/png' })).toBe(true);
    expect(mod.accepts('application/pdf', { name: 'a', type: 'application/pdf' })).toBe(true);
    expect(mod.accepts('application/pdf, .txt', { name: 'a.doc', type: 'application/msword' })).toBe(false);
  });

  it('is marked ready on load without announcing anything', () => {
    expect(root.hasAttribute('data-nowo-ui-dropzone-ready')).toBe(true);
    expect(root.querySelector('[data-nowo-ui-dropzone-status]')!.textContent).toBe('');
  });

  it('lists chosen files, flags the invalid ones and announces the selection', () => {
    setFiles(input, [file('a.pdf', 500, 'application/pdf'), file('big.png', 5000, 'image/png'), file('c.exe', 10, 'application/x-msdownload')]);
    input.dispatchEvent(new Event('change', { bubbles: true }));
    const items = root.querySelectorAll('.nowo-ui-dropzone__file');
    expect(items).toHaveLength(3);
    expect(items[0].textContent).toContain('a.pdf');
    expect(items[0].textContent).toContain('500 B');
    expect(items[1].classList.contains('nowo-ui-dropzone__file--invalid')).toBe(true);
    expect(items[2].getAttribute('title')).toBe('c.exe is not an accepted type');
    expect(input.validationMessage).toContain('big.png is larger than 1000 B');
    expect(root.querySelector('[data-nowo-ui-dropzone-status]')!.textContent).toBe(
      '3 files selected big.png is larger than 1000 B c.exe is not an accepted type',
    );
    setFiles(input, [file('a.pdf', 1, 'application/pdf')]);
    mod.render(root);
    expect(root.querySelector('[data-nowo-ui-dropzone-status]')!.textContent).toBe('1 file selected');
    expect(input.validationMessage).toBe('');
    setFiles(input, []);
    mod.render(root);
    expect(root.querySelector('[data-nowo-ui-dropzone-status]')!.textContent).toBe('No file selected');
  });

  it('highlights while dragging over and clears on leave', () => {
    const over = new Event('dragover', { bubbles: true, cancelable: true });
    input.dispatchEvent(over);
    expect(over.defaultPrevented).toBe(true);
    expect(root.classList.contains('is-dragover')).toBe(true);
    const inside = new Event('dragleave', { bubbles: true, cancelable: true });
    Object.defineProperty(inside, 'relatedTarget', { value: input });
    root.dispatchEvent(inside);
    expect(root.classList.contains('is-dragover')).toBe(true);
    root.dispatchEvent(new Event('dragleave', { bubbles: true, cancelable: true }));
    expect(root.classList.contains('is-dragover')).toBe(false);
    const elsewhere = new Event('dragover', { bubbles: true, cancelable: true });
    document.body.dispatchEvent(elsewhere);
    expect(elsewhere.defaultPrevented).toBe(false);
  });

  it('puts dropped files into the input through DataTransfer', () => {
    const added: File[] = [];
    class FakeTransfer {
      items = { add: (f: File) => added.push(f) };
      get files(): File[] {
        return added;
      }
    }
    vi.stubGlobal('DataTransfer', FakeTransfer);
    const changes = vi.fn();
    input.addEventListener('change', changes);
    const drop = new Event('drop', { bubbles: true, cancelable: true });
    Object.defineProperty(drop, 'dataTransfer', { value: { files: [file('a.pdf', 1, 'application/pdf'), file('b.pdf', 1, 'application/pdf')] } });
    root.classList.add('is-dragover');
    input.dispatchEvent(drop);
    expect(drop.defaultPrevented).toBe(true);
    expect(root.classList.contains('is-dragover')).toBe(false);
    expect(added).toHaveLength(2);
    expect(changes).toHaveBeenCalledTimes(1);
    expect(root.querySelectorAll('.nowo-ui-dropzone__file')).toHaveLength(2);
  });

  it('keeps one file without multiple and reports a refused DataTransfer', () => {
    input.multiple = false;
    const added: File[] = [];
    vi.stubGlobal(
      'DataTransfer',
      class {
        items = { add: (f: File) => added.push(f) };
        files = added;
      },
    );
    expect(mod.assignFiles(input, [file('a', 1, ''), file('b', 1, '')])).toBe(true);
    expect(added).toHaveLength(1);
    vi.stubGlobal(
      'DataTransfer',
      class {
        constructor() {
          throw new Error('no');
        }
      },
    );
    expect(mod.assignFiles(input, [file('a', 1, '')])).toBe(false);
  });

  it('ignores drops without files, outside a dropzone, and on a disabled input', () => {
    const empty = new Event('drop', { bubbles: true, cancelable: true });
    Object.defineProperty(empty, 'dataTransfer', { value: { files: [] } });
    input.dispatchEvent(empty);
    expect(root.querySelectorAll('.nowo-ui-dropzone__file')).toHaveLength(0);
    const outside = new Event('drop', { bubbles: true, cancelable: true });
    document.body.dispatchEvent(outside);
    expect(outside.defaultPrevented).toBe(false);
    input.disabled = true;
    const disabled = new Event('drop', { bubbles: true, cancelable: true });
    Object.defineProperty(disabled, 'dataTransfer', { value: { files: [file('a', 1, '')] } });
    input.dispatchEvent(disabled);
    expect(root.querySelectorAll('.nowo-ui-dropzone__file')).toHaveLength(0);
    const other = document.createElement('input');
    other.type = 'text';
    document.body.appendChild(other);
    other.dispatchEvent(new Event('change', { bubbles: true }));
    const stray = document.createElement('input');
    stray.type = 'file';
    document.body.appendChild(stray);
    stray.dispatchEvent(new Event('change', { bubbles: true }));
    const bare = document.createElement('div');
    bare.setAttribute('data-nowo-ui-dropzone', '');
    expect(() => mod.render(bare)).not.toThrow();
  });
});
