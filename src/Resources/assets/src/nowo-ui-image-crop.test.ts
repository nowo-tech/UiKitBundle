import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { bindNowoUiImageCrop, cropDialog, currentSession, DIALOG_ID, env, replaceFiles, resetForTests, startCrop, supported, wantsCrop, type Decoded, type Session } from './nowo-ui-image-crop';
import { DEFAULT_LABELS, KEY_STEP } from './nowo-ui-image-crop-core';

type Call = { name: string; args: unknown[] };

/** A 2D context that records its calls (happy-dom has none). */
function fakeContext(calls: Call[]): CanvasRenderingContext2D {
  return new Proxy({} as Record<string | symbol, unknown>, {
    get(target, prop) {
      if (prop in target) {
        return target[prop];
      }
      if (prop === 'getImageData') {
        return () => (pixels === 'throw' ? (() => { throw new Error('tainted'); })() : pixels === null ? undefined : { data: pixels });
      }
      return (...args: unknown[]) => {
        calls.push({ name: String(prop), args });
      };
    },
    set(target, prop, value) {
      target[prop] = value;
      calls.push({ name: `set:${String(prop)}`, args: [value] });
      return true;
    },
  }) as unknown as CanvasRenderingContext2D;
}

const original = { ...env };
let calls: Call[];
/** What getImageData answers: RGBA data, undefined (null) or a throw. */
let pixels: Uint8ClampedArray | null | 'throw';
let closed: number;
let encoded: { type: string; quality: number; width: number; height: number }[];

const image = (width: number, height: number): Decoded => ({
  source: { kind: 'bitmap' } as unknown as CanvasImageSource,
  width,
  height,
  close: () => {
    closed += 1;
  },
});

function pick(input: HTMLInputElement, file: File): void {
  const transfer = new DataTransfer();
  transfer.items.add(file);
  input.files = transfer.files;
  input.dispatchEvent(new Event('input', { bubbles: true }));
  input.dispatchEvent(new Event('change', { bubbles: true }));
}

const photo = (name = 'photo.jpg', type = 'image/jpeg'): File => new File([new Uint8Array(64)], name, { type });
const el = <T extends HTMLElement = HTMLElement>(id: string): T => document.getElementById(id) as T;
const session = async (): Promise<Session> => {
  await vi.waitFor(() => expect(currentSession()).not.toBeNull());
  return currentSession() as Session;
};
const dialog = (): HTMLDialogElement => el<HTMLDialogElement>(DIALOG_ID);
const pointer = (type: string, id: number, x: number, y: number, extra: PointerEventInit = {}): PointerEvent =>
  new PointerEvent(type, { pointerId: id, clientX: x, clientY: y, bubbles: true, cancelable: true, pointerType: 'touch', ...extra });

describe('nowo-ui-image-crop', () => {
  let changes: string[];
  const onChange = (event: Event): void => {
    const input = event.target as HTMLInputElement;
    changes.push(`${event.type}:${input.files?.[0]?.name ?? ''}`);
  };

  beforeEach(() => {
    calls = [];
    pixels = new Uint8ClampedArray([1, 2, 3, 255, 4, 5, 6, 255]);
    closed = 0;
    encoded = [];
    changes = [];
    env.decode = vi.fn(async () => image(1600, 1200));
    env.context = () => fakeContext(calls);
    env.encode = vi.fn(async (canvas: HTMLCanvasElement, type: string, quality: number) => {
      encoded.push({ type, quality, width: canvas.width, height: canvas.height });
      return new Blob([new Uint8Array(10)], { type });
    });
    env.createObjectURL = vi.fn(() => `blob:crop-${encoded.length}`);
    env.revokeObjectURL = vi.fn();
    document.body.innerHTML = `
      <form id="form">
        <input id="avatar" type="file" name="avatar" accept="image/*" data-nowo-ui-image-crop data-nowo-ui-image-crop-max="512" data-nowo-ui-image-crop-format="png">
        <input id="auto" type="file" name="auto" data-nowo-ui-image-crop data-nowo-ui-image-crop-max="400">
        <input id="plain" type="file" name="plain">
        <input id="many" type="file" multiple data-nowo-ui-image-crop>
        <img id="preview" alt="" hidden>
      </form>`;
    document.addEventListener('change', onChange);
    document.addEventListener('input', onChange);
    bindNowoUiImageCrop();
  });

  afterEach(() => {
    document.removeEventListener('change', onChange);
    document.removeEventListener('input', onChange);
    resetForTests();
    Object.assign(env, original);
    document.body.innerHTML = '';
    vi.restoreAllMocks();
  });

  it('supports this browser, and not one without DataTransfer', () => {
    expect(supported()).toBe(true);
    const saved = globalThis.DataTransfer;
    // @ts-expect-error: a browser without DataTransfer
    globalThis.DataTransfer = undefined;
    try {
      expect(supported()).toBe(false);
      expect(replaceFiles(el<HTMLInputElement>('avatar'), photo())).toBe(false);
    } finally {
      globalThis.DataTransfer = saved;
    }
    const toBlob = HTMLCanvasElement.prototype.toBlob;
    // @ts-expect-error: a canvas without toBlob
    HTMLCanvasElement.prototype.toBlob = undefined;
    try {
      expect(supported()).toBe(false);
    } finally {
      HTMLCanvasElement.prototype.toBlob = toBlob;
    }
  });

  it('without support the upload goes on as picked: no dialog, the events pass', () => {
    const saved = globalThis.DataTransfer;
    const input = el<HTMLInputElement>('avatar');
    const transfer = new DataTransfer();
    transfer.items.add(photo());
    input.files = transfer.files;
    // @ts-expect-error: the browser loses DataTransfer before the change
    globalThis.DataTransfer = undefined;
    try {
      input.dispatchEvent(new Event('change', { bubbles: true }));
    } finally {
      globalThis.DataTransfer = saved;
    }
    expect(changes).toEqual(['change:photo.jpg']);
    expect(document.getElementById(DIALOG_ID)).toBeNull();
    expect(env.decode).not.toHaveBeenCalled();
  });

  it('leaves inputs that did not opt in, multiple inputs and files that are not raster images', () => {
    pick(el<HTMLInputElement>('plain'), photo());
    pick(el<HTMLInputElement>('many'), photo());
    pick(el<HTMLInputElement>('avatar'), photo('logo.svg', 'image/svg+xml'));
    pick(el<HTMLInputElement>('avatar'), photo('cv.pdf', 'application/pdf'));
    expect(changes).toEqual(['input:photo.jpg', 'change:photo.jpg', 'input:photo.jpg', 'change:photo.jpg', 'input:logo.svg', 'change:logo.svg', 'input:cv.pdf', 'change:cv.pdf']);
    expect(env.decode).not.toHaveBeenCalled();
    const disabled = el<HTMLInputElement>('avatar');
    disabled.disabled = true;
    expect(wantsCrop(disabled)).toBe(false);
    expect(wantsCrop(null)).toBe(false);
    expect(wantsCrop(document.body)).toBe(false);
  });

  it('opens the dialog with the kit anatomy, labels and focus on the stage', async () => {
    pick(el<HTMLInputElement>('avatar'), photo());
    const crop = await session();
    // The picked file's own events never reach the page's listeners.
    expect(changes).toEqual([]);
    const box = dialog();
    expect(box.open).toBe(true);
    expect(box.matches('dialog.nowo-ui-dialog.nowo-ui-image-crop[data-nowo-ui-dialog]')).toBe(true);
    expect(box.getAttribute('aria-labelledby')).toBe(`${DIALOG_ID}-title`);
    expect(el(`${DIALOG_ID}-title`).textContent).toBe(DEFAULT_LABELS.title);
    expect(box.querySelector('.nowo-ui-dialog__header .nowo-ui-dialog__close')?.getAttribute('aria-label')).toBe(DEFAULT_LABELS.close);
    expect(box.querySelector('.nowo-ui-dialog__footer [data-nowo-ui-image-crop-apply]')?.textContent).toBe(DEFAULT_LABELS.apply);
    const stage = box.querySelector('[data-nowo-ui-image-crop-stage]') as HTMLElement;
    expect(stage.getAttribute('aria-label')).toBe(DEFAULT_LABELS.stage);
    expect(el(stage.getAttribute('aria-describedby') as string).textContent).toBe(DEFAULT_LABELS.instructions);
    expect(document.activeElement).toBe(stage);
    const range = el<HTMLInputElement>(`${DIALOG_ID}-zoom`);
    expect(box.querySelector(`label[for="${DIALOG_ID}-zoom"]`)?.textContent).toBe(DEFAULT_LABELS.zoom);
    expect(range.value).toBe('100');
    expect(range.max).toBe('800');
    expect(range.getAttribute('aria-valuetext')).toBe('Zoom 100%');
    expect(box.querySelector('[style]')).toBeNull();
    expect(crop.frame.x).toBeCloseTo(25.6);
    expect(crop.frame.width).toBeCloseTo(268.8);
    expect(crop.frame.height).toBeCloseTo(268.8);
    // The image was drawn, then the mask with the frame cut out.
    expect(calls.some((call) => call.name === 'drawImage')).toBe(true);
    expect(calls.some((call) => call.name === 'fill' && call.args[0] === 'evenodd')).toBe(true);
  });

  it('Apply puts a square PNG of at most max pixels in the input, then dispatches input and change', async () => {
    const input = el<HTMLInputElement>('avatar');
    const cropped = vi.fn();
    input.addEventListener('nowo-ui:image-crop:cropped', cropped);
    pick(input, photo());
    await session();
    (dialog().querySelector('[data-nowo-ui-image-crop-apply]') as HTMLButtonElement).click();
    await vi.waitFor(() => expect(cropped).toHaveBeenCalled());
    expect(encoded).toEqual([{ type: 'image/png', quality: 0.92, width: 512, height: 512 }]);
    const file = input.files?.[0] as File;
    expect(file.name).toBe('photo.png');
    expect(file.type).toBe('image/png');
    expect((cropped.mock.calls[0][0] as CustomEvent).detail).toMatchObject({ width: 512, height: 512, file });
    expect(changes).toEqual(['input:photo.png', 'change:photo.png']);
    expect(dialog().open).toBe(false);
    expect(document.activeElement).toBe(input);
    expect(closed).toBe(1);
    expect(currentSession()).toBeNull();
    // The crop was the centre square of the 1600 × 1200 image, scaled down.
    const draw = calls.filter((call) => call.name === 'drawImage').pop() as Call;
    (draw.args.slice(1) as number[]).forEach((value, index) => expect(value).toBeCloseTo([200, 0, 1200, 1200, 0, 0, 512, 512][index]));
  });

  it('a small crop is not enlarged', async () => {
    env.decode = vi.fn(async () => image(300, 200));
    const input = el<HTMLInputElement>('avatar');
    pick(input, photo());
    await session();
    (dialog().querySelector('[data-nowo-ui-image-crop-apply]') as HTMLButtonElement).click();
    await vi.waitFor(() => expect(encoded).toHaveLength(1));
    expect(encoded[0]).toMatchObject({ width: 200, height: 200 });
  });

  it('WebP with the quality, and the name follows what the browser really encoded', async () => {
    env.encode = vi.fn(async (_canvas: HTMLCanvasElement, type: string, quality: number) => {
      encoded.push({ type, quality, width: 0, height: 0 });
      return new Blob([new Uint8Array(4)], { type: 'image/png' });
    });
    const input = el<HTMLInputElement>('avatar');
    input.setAttribute('data-nowo-ui-image-crop-format', 'webp');
    input.setAttribute('data-nowo-ui-image-crop-quality', '0.7');
    pick(input, photo('me.jpeg'));
    await session();
    (dialog().querySelector('[data-nowo-ui-image-crop-apply]') as HTMLButtonElement).click();
    await vi.waitFor(() => expect(input.files?.[0]?.name).toBe('me.png'));
    expect(encoded[0]).toMatchObject({ type: 'image/webp', quality: 0.7 });
    expect(input.files?.[0]?.type).toBe('image/png');
  });

  it('a 16:9 frame gives a 16:9 output, a JPEG has a white ground', async () => {
    const input = el<HTMLInputElement>('avatar');
    input.setAttribute('data-nowo-ui-image-crop-aspect', '16/9');
    input.setAttribute('data-nowo-ui-image-crop-max', '800');
    input.setAttribute('data-nowo-ui-image-crop-format', 'jpeg');
    pick(input, photo());
    await session();
    (dialog().querySelector('[data-nowo-ui-image-crop-apply]') as HTMLButtonElement).click();
    await vi.waitFor(() => expect(encoded).toHaveLength(1));
    expect(encoded[0]).toMatchObject({ type: 'image/jpeg', width: 800, height: 450 });
    expect(calls.some((call) => call.name === 'fillRect')).toBe(true);
    expect(input.files?.[0]?.name).toBe('photo.jpg');
  });

  it('a circle masks with an arc; baked, the output is clipped to it', async () => {
    const input = el<HTMLInputElement>('avatar');
    input.setAttribute('data-nowo-ui-image-crop-shape', 'circle');
    pick(input, photo());
    const crop = await session();
    expect(calls.some((call) => call.name === 'arc')).toBe(true);
    expect(dialog().querySelector('.nowo-ui-image-crop__stage')?.classList.contains('is-circle')).toBe(true);
    calls.length = 0;
    await crop.apply();
    expect(calls.some((call) => call.name === 'clip')).toBe(false);

    input.setAttribute('data-nowo-ui-image-crop-bake-circle', '');
    pick(input, photo());
    const baked = await session();
    calls.length = 0;
    await baked.apply();
    expect(calls.some((call) => call.name === 'clip')).toBe(true);
  });

  it('drag, keys, wheel, slider and pinch move and zoom the image', async () => {
    pick(el<HTMLInputElement>('avatar'), photo());
    const crop = await session();
    const stage = dialog().querySelector('[data-nowo-ui-image-crop-stage]') as HTMLElement;
    const start = { ...crop.view };

    stage.dispatchEvent(pointer('pointerdown', 1, 100, 100));
    stage.dispatchEvent(pointer('pointermove', 1, 130, 100));
    stage.dispatchEvent(pointer('pointerup', 1, 130, 100));
    expect(crop.view.x - start.x).toBeCloseTo(30);
    // A move with no press does nothing; neither does a right button.
    stage.dispatchEvent(pointer('pointermove', 1, 300, 100));
    stage.dispatchEvent(pointer('pointerdown', 2, 100, 100, { pointerType: 'mouse', button: 2 }));
    stage.dispatchEvent(pointer('pointermove', 2, 200, 100, { pointerType: 'mouse', button: 2 }));
    expect(crop.view.x - start.x).toBeCloseTo(30);

    stage.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft', bubbles: true, cancelable: true }));
    expect(crop.view.x - start.x).toBeCloseTo(30 - KEY_STEP);
    const ignored = new KeyboardEvent('keydown', { key: 'ArrowLeft', ctrlKey: true, bubbles: true, cancelable: true });
    stage.dispatchEvent(ignored);
    expect(ignored.defaultPrevented).toBe(false);
    stage.dispatchEvent(new KeyboardEvent('keydown', { key: 'x', bubbles: true, cancelable: true }));

    stage.dispatchEvent(new KeyboardEvent('keydown', { key: '+', bubbles: true, cancelable: true }));
    expect(crop.view.scale / crop.bounds.minScale).toBeCloseTo(1.1);
    expect(dialog().querySelector('[data-nowo-ui-image-crop-status]')?.textContent).toBe('Zoom 110%');
    expect(el<HTMLInputElement>(`${DIALOG_ID}-zoom`).value).toBe('110');

    stage.dispatchEvent(new KeyboardEvent('keydown', { key: 'Home', bubbles: true, cancelable: true }));
    expect(crop.view.scale).toBeCloseTo(crop.bounds.minScale);

    const wheel = new WheelEvent('wheel', { deltaY: -100, clientX: 160, clientY: 160, bubbles: true, cancelable: true });
    stage.dispatchEvent(wheel);
    expect(wheel.defaultPrevented).toBe(true);
    expect(crop.view.scale).toBeGreaterThan(crop.bounds.minScale);

    // A cancelled touchmove: no scroll and no fling (which would swallow the next tap).
    const touch = new Event('touchmove', { bubbles: true, cancelable: true });
    stage.dispatchEvent(touch);
    expect(touch.defaultPrevented).toBe(true);

    const range = el<HTMLInputElement>(`${DIALOG_ID}-zoom`);
    range.value = '300';
    range.dispatchEvent(new Event('input', { bubbles: true }));
    expect(crop.view.scale / crop.bounds.minScale).toBeCloseTo(3);

    // Two fingers spreading to twice their distance double the zoom.
    stage.dispatchEvent(new KeyboardEvent('keydown', { key: 'Home', bubbles: true, cancelable: true }));
    stage.dispatchEvent(pointer('pointerdown', 5, 100, 160));
    stage.dispatchEvent(pointer('pointerdown', 6, 200, 160));
    stage.dispatchEvent(pointer('pointermove', 5, 50, 160));
    stage.dispatchEvent(pointer('pointermove', 6, 250, 160));
    expect(crop.view.scale / crop.bounds.minScale).toBeCloseTo(2);
    stage.dispatchEvent(pointer('pointercancel', 5, 50, 160));
    stage.dispatchEvent(pointer('pointerup', 6, 250, 160));
    const after = crop.view.scale;
    stage.dispatchEvent(pointer('pointermove', 6, 10, 160));
    expect(crop.view.scale).toBe(after);
  });

  it('Enter applies', async () => {
    const input = el<HTMLInputElement>('avatar');
    pick(input, photo());
    await session();
    dialog().querySelector('[data-nowo-ui-image-crop-stage]')?.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));
    await vi.waitFor(() => expect(input.files?.[0]?.name).toBe('photo.png'));
  });

  it('Cancel clears the input and tells the page', async () => {
    const input = el<HTMLInputElement>('avatar');
    const cancelled = vi.fn();
    input.addEventListener('nowo-ui:image-crop:cancel', cancelled);
    pick(input, photo());
    await session();
    (dialog().querySelector('.nowo-ui-dialog__footer [data-nowo-ui-image-crop-cancel]') as HTMLButtonElement).click();
    expect(input.files?.length ?? 0).toBe(0);
    expect(input.value).toBe('');
    expect(cancelled).toHaveBeenCalledOnce();
    expect(changes).toEqual(['input:', 'change:']);
    expect(dialog().open).toBe(false);
    expect(closed).toBe(1);
    expect(document.activeElement).toBe(input);
  });

  it('closing the dialog another way (Escape) is a cancel', async () => {
    const input = el<HTMLInputElement>('avatar');
    pick(input, photo());
    await session();
    dialog().close();
    expect(input.files?.length ?? 0).toBe(0);
    expect(currentSession()).toBeNull();
    // A click in the dialog with no session does nothing.
    (dialog().querySelector('[data-nowo-ui-image-crop-apply]') as HTMLButtonElement).click();
    expect(encoded).toHaveLength(0);
  });

  it('uses the dialog runtime when it is loaded', async () => {
    const page = window as unknown as Record<string, unknown>;
    const opened = vi.fn((box: HTMLDialogElement) => box.showModal());
    const shut = vi.fn((box: HTMLDialogElement) => box.close());
    page.nowoUiOpenDialog = opened;
    page.nowoUiCloseDialog = shut;
    try {
      const input = el<HTMLInputElement>('avatar');
      pick(input, photo());
      await session();
      expect(opened).toHaveBeenCalledWith(dialog(), input);
      (dialog().querySelector('.nowo-ui-dialog__close') as HTMLButtonElement).click();
      expect(shut).toHaveBeenCalledWith(dialog());
    } finally {
      delete page.nowoUiOpenDialog;
      delete page.nowoUiCloseDialog;
    }
  });

  it('labels from the attribute', async () => {
    const input = el<HTMLInputElement>('avatar');
    input.setAttribute('data-nowo-ui-image-crop-labels', JSON.stringify({ title: 'Recortar', apply: 'Aplicar', zoomStatus: 'Zum %percent% %' }));
    pick(input, photo());
    await session();
    expect(el(`${DIALOG_ID}-title`).textContent).toBe('Recortar');
    expect(dialog().querySelector('[data-nowo-ui-image-crop-apply]')?.textContent).toBe('Aplicar');
    expect(dialog().querySelector('.nowo-ui-dialog__footer [data-nowo-ui-image-crop-cancel]')?.textContent).toBe('Cancel');
    expect(el(`${DIALOG_ID}-zoom`).getAttribute('aria-valuetext')).toBe('Zum 100 %');
  });

  it('shows the crop in the preview image, revoking the previous URL', async () => {
    const input = el<HTMLInputElement>('avatar');
    input.setAttribute('data-nowo-ui-image-crop-preview', 'preview');
    pick(input, photo());
    await (await session()).apply();
    expect(el<HTMLImageElement>('preview').getAttribute('src')).toBe('blob:crop-1');
    expect(el('preview').hidden).toBe(false);
    pick(input, photo());
    await (await session()).apply();
    expect(env.revokeObjectURL).toHaveBeenCalledWith('blob:crop-1');
    expect(el<HTMLImageElement>('preview').getAttribute('src')).toBe('blob:crop-2');
    // An id that is not an image is ignored.
    input.setAttribute('data-nowo-ui-image-crop-preview', 'form');
    pick(input, photo());
    await (await session()).apply();
    expect(el('form').hasAttribute('src')).toBe(false);
  });

  it('an image the browser cannot decode is uploaded as picked', async () => {
    env.decode = vi.fn(async () => {
      throw new Error('HEIC');
    });
    const input = el<HTMLInputElement>('avatar');
    const failed = vi.fn();
    input.addEventListener('nowo-ui:image-crop:error', failed);
    pick(input, photo('a.heic', 'image/heic'));
    await vi.waitFor(() => expect(failed).toHaveBeenCalled());
    expect((failed.mock.calls[0][0] as CustomEvent).detail.reason).toBe('decode');
    expect(changes).toEqual(['input:a.heic', 'change:a.heic']);
    expect(document.getElementById(DIALOG_ID)).toBeNull();

    env.decode = vi.fn(async () => image(0, 0));
    changes.length = 0;
    pick(input, photo());
    await vi.waitFor(() => expect(failed).toHaveBeenCalledTimes(2));
    expect(closed).toBe(1);
    expect(changes).toEqual(['input:photo.jpg', 'change:photo.jpg']);
  });

  it('a host can refuse the crop: the file stays as picked', async () => {
    const input = el<HTMLInputElement>('avatar');
    input.addEventListener('nowo-ui:image-crop:open', (event) => event.preventDefault());
    pick(input, photo());
    await vi.waitFor(() => expect(changes).toEqual(['input:photo.jpg', 'change:photo.jpg']));
    expect(env.decode).not.toHaveBeenCalled();
  });

  it('an encoder that fails keeps the picked file', async () => {
    env.encode = vi.fn(async () => null);
    const input = el<HTMLInputElement>('avatar');
    const failed = vi.fn();
    input.addEventListener('nowo-ui:image-crop:error', failed);
    pick(input, photo());
    const crop = await session();
    await crop.apply();
    expect((failed.mock.calls[0][0] as CustomEvent).detail.reason).toBe('encode');
    expect(input.files?.[0]?.name).toBe('photo.jpg');
    expect(changes).toEqual(['input:photo.jpg', 'change:photo.jpg']);
    // A second apply after the end does nothing.
    await crop.apply();
    expect(env.encode).toHaveBeenCalledOnce();

    env.encode = vi.fn(async () => {
      throw new Error('tainted');
    });
    pick(input, photo());
    await (await session()).apply();
    expect(failed).toHaveBeenCalledTimes(2);

    env.context = () => null;
    pick(input, photo());
    await (await session()).apply();
    expect(failed).toHaveBeenCalledTimes(3);
  });

  it('a browser that does not take the new file list keeps the picked file', async () => {
    const input = el<HTMLInputElement>('avatar');
    const failed = vi.fn();
    input.addEventListener('nowo-ui:image-crop:error', failed);
    pick(input, photo());
    const crop = await session();
    const descriptor = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'files') as PropertyDescriptor;
    Object.defineProperty(input, 'files', { configurable: true, get: () => descriptor.get?.call(input), set: () => undefined });
    await crop.apply();
    expect((failed.mock.calls[0][0] as CustomEvent).detail.reason).toBe('replace');
    expect(input.files?.[0]?.name).toBe('photo.jpg');
  });

  it('one crop at a time; startCrop without a file does nothing', async () => {
    const input = el<HTMLInputElement>('avatar');
    expect(await startCrop(input)).toBeNull();
    pick(input, photo());
    const crop = await session();
    expect(await startCrop(input)).toBeNull();
    crop.cancel();
    crop.cancel();
    expect(currentSession()).toBeNull();
  });

  it('measures the stage again when it is resized', async () => {
    pick(el<HTMLInputElement>('avatar'), photo());
    const crop = await session();
    const stage = dialog().querySelector('[data-nowo-ui-image-crop-stage]') as HTMLElement;
    vi.spyOn(stage, 'clientWidth', 'get').mockReturnValue(200);
    vi.spyOn(stage, 'clientHeight', 'get').mockReturnValue(200);
    stage.dispatchEvent(new KeyboardEvent('keydown', { key: '+', bubbles: true, cancelable: true }));
    crop.layout();
    expect(crop.stage).toEqual({ width: 200, height: 200 });
    expect(crop.view.scale / crop.bounds.minScale).toBeCloseTo(1.1);
    expect(dialog().querySelector('canvas')?.width).toBe(200);
  });

  it('the dialog is built once, and again when it was removed', async () => {
    const first = cropDialog();
    expect(cropDialog()).toBe(first);
    first.dialog.remove();
    expect(cropDialog()).not.toBe(first);
    expect(document.querySelectorAll(`#${DIALOG_ID}`)).toHaveLength(1);
  });

  it('the browser seams: an upright bitmap, an <img> fallback, toBlob', async () => {
    const bitmap = { width: 30, height: 20, close: vi.fn() };
    const create = vi.fn(async () => bitmap);
    vi.stubGlobal('createImageBitmap', create);
    try {
      const decoded = await original.decode(photo());
      expect(create).toHaveBeenCalledWith(expect.any(File), { imageOrientation: 'from-image' });
      expect(decoded).toMatchObject({ width: 30, height: 20 });
      decoded.close();
      expect(bitmap.close).toHaveBeenCalled();

      create.mockRejectedValueOnce(new TypeError('imageOrientation'));
      const decode = vi.spyOn(HTMLImageElement.prototype, 'decode').mockResolvedValueOnce(undefined);
      vi.spyOn(HTMLImageElement.prototype, 'naturalWidth', 'get').mockReturnValue(40);
      vi.spyOn(HTMLImageElement.prototype, 'naturalHeight', 'get').mockReturnValue(10);
      const revoke = vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => undefined);
      vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:x');
      const fallback = await original.decode(photo());
      expect(fallback).toMatchObject({ width: 40, height: 10 });
      fallback.close();
      expect(revoke).toHaveBeenCalledWith('blob:x');

      create.mockRejectedValueOnce(new TypeError('type'));
      decode.mockRejectedValueOnce(new Error('broken'));
      await expect(original.decode(photo())).rejects.toThrow('broken');
      expect(revoke).toHaveBeenCalledTimes(2);

      original.revokeObjectURL('blob:y');
      expect(original.createObjectURL(new Blob())).toBe('blob:x');
    } finally {
      vi.unstubAllGlobals();
    }
    const canvas = document.createElement('canvas');
    const blob = new Blob(['x'], { type: 'image/png' });
    vi.spyOn(canvas, 'toBlob').mockImplementation((done: BlobCallback, type?: string, quality?: number) => {
      expect([type, quality]).toEqual(['image/webp', 0.5]);
      done(blob);
    });
    await expect(original.encode(canvas, 'image/webp', 0.5)).resolves.toBe(blob);
    expect(original.context(canvas)).toBeNull();
  });

  describe('format auto and max-bytes', () => {
    /** An encoder whose answers are scripted: the type it really encodes and the size, per call. */
    const scripted = (answer: (type: string, quality: number, width: number) => { type: string; size: number } | null): void => {
      env.encode = vi.fn(async (canvas: HTMLCanvasElement, type: string, quality: number) => {
        encoded.push({ type, quality, width: canvas.width, height: canvas.height });
        const out = answer(type, quality, canvas.width);
        return out ? new Blob([new Uint8Array(out.size)], { type: out.type }) : null;
      });
    };
    const run = async (): Promise<{ input: HTMLInputElement; crop: Session }> => {
      const input = el<HTMLInputElement>('auto');
      pick(input, photo());
      const crop = await session();
      await crop.apply();
      return { input, crop };
    };

    it('an opaque crop: WebP where the engine encodes it', async () => {
      scripted((type) => ({ type, size: 100 }));
      const { input } = await run();
      expect(encoded.map((e) => e.type)).toEqual(['image/webp']);
      expect(input.files?.[0]).toMatchObject({ name: 'photo.webp', type: 'image/webp' });
    });

    it('an opaque crop without a WebP encoder: JPEG', async () => {
      scripted((type) => ({ type: type === 'image/webp' ? 'image/png' : type, size: 100 }));
      const { input } = await run();
      expect(encoded.map((e) => e.type)).toEqual(['image/webp', 'image/jpeg']);
      expect(input.files?.[0]).toMatchObject({ name: 'photo.jpg', type: 'image/jpeg' });
    });

    it('a crop with transparency without a WebP encoder: PNG; unreadable pixels count as transparent', async () => {
      scripted((type) => ({ type: type === 'image/webp' ? 'image/png' : type, size: 100 }));
      for (const data of [new Uint8ClampedArray([1, 2, 3, 255, 4, 5, 6, 0]), null, 'throw'] as const) {
        pixels = data;
        encoded.length = 0;
        const { input } = await run();
        expect(encoded.map((e) => e.type)).toEqual(['image/webp', 'image/png']);
        expect(input.files?.[0]?.type).toBe('image/png');
      }
    });

    it('a baked circle is transparent without reading the pixels', async () => {
      scripted((type) => ({ type, size: 100 }));
      const input = el<HTMLInputElement>('auto');
      input.setAttribute('data-nowo-ui-image-crop-shape', 'circle');
      input.setAttribute('data-nowo-ui-image-crop-bake-circle', '');
      pixels = 'throw';
      const { input: same } = await run();
      expect(same.files?.[0]?.type).toBe('image/webp');
      expect(input).toBe(same);
    });

    it('an encoder that gives nothing for WebP falls to the next type', async () => {
      scripted((type) => (type === 'image/webp' ? null : { type, size: 100 }));
      const { input } = await run();
      expect(input.files?.[0]?.type).toBe('image/jpeg');
    });

    it('over max-bytes: lower qualities first, then smaller sizes, and the event says how', async () => {
      // 400 px; the encoder gives 1000 bytes at 0.92 and 0.85, 900 at 0.78…, and fits only once the width is 340 (one size step).
      scripted((type, quality, width) => ({ type, size: width <= 340 ? 400 : quality > 0.8 ? 1000 : 900 }));
      const input = el<HTMLInputElement>('auto');
      input.setAttribute('data-nowo-ui-image-crop-max-bytes', '500');
      const cropped = vi.fn();
      input.addEventListener('nowo-ui:image-crop:cropped', cropped);
      await run();
      expect(encoded.map((e) => [e.quality, e.width])).toEqual([
        [0.92, 400],
        [0.85, 400],
        [0.78, 400],
        [0.7, 400],
        [0.6, 400],
        [0.5, 400],
        [0.5, 340],
      ]);
      expect((cropped.mock.calls[0][0] as CustomEvent).detail).toMatchObject({ width: 340, height: 340, quality: 0.5, attempts: 7 });
      expect(input.files?.[0]?.size).toBe(400);
    });

    it('PNG over max-bytes only steps the size down', async () => {
      scripted((type, _quality, width) => ({ type, size: width * 10 }));
      const input = el<HTMLInputElement>('avatar');
      input.setAttribute('data-nowo-ui-image-crop-max-bytes', '4000');
      pick(input, photo());
      await (await session()).apply();
      expect(encoded.map((e) => e.width)).toEqual([512, 435, 370]);
      expect(input.files?.[0]?.size).toBe(3700);
    });

    it('nothing fits: the size error, the dialog stays open with a message, the picked file stays; a new crop hides it', async () => {
      scripted((type) => ({ type, size: 5000 }));
      const input = el<HTMLInputElement>('auto');
      input.setAttribute('data-nowo-ui-image-crop-max-bytes', '307200');
      input.setAttribute('data-nowo-ui-image-crop-labels', JSON.stringify({ tooLarge: 'Too big: %max%' }));
      scripted((type) => ({ type, size: 400000 }));
      const failed = vi.fn();
      input.addEventListener('nowo-ui:image-crop:error', failed);
      const { crop } = await run();
      expect(encoded).toHaveLength(8);
      expect((failed.mock.calls[0][0] as CustomEvent).detail).toMatchObject({ reason: 'size', bytes: 400000, maxBytes: 307200 });
      expect(dialog().open).toBe(true);
      const message = dialog().querySelector('[data-nowo-ui-image-crop-error]') as HTMLElement;
      expect(message.hidden).toBe(false);
      expect(message.getAttribute('role')).toBe('alert');
      expect(message.textContent).toBe('Too big: 300 KB');
      expect(input.files?.[0]?.name).toBe('photo.jpg');
      expect(changes).toEqual([]);
      dialog().querySelector('[data-nowo-ui-image-crop-stage]')?.dispatchEvent(new KeyboardEvent('keydown', { key: '+', bubbles: true, cancelable: true }));
      expect(message.hidden).toBe(true);
      crop.cancel();
      expect(input.files?.length ?? 0).toBe(0);
    });

    it('an image smaller than min cannot step down: the size error after the qualities', async () => {
      scripted((type) => ({ type, size: 9999 }));
      const input = el<HTMLInputElement>('auto');
      input.setAttribute('data-nowo-ui-image-crop-max-bytes', '10');
      input.setAttribute('data-nowo-ui-image-crop-min', '2000');
      const failed = vi.fn();
      input.addEventListener('nowo-ui:image-crop:error', failed);
      await run();
      expect(encoded).toHaveLength(6);
      expect((failed.mock.calls[0][0] as CustomEvent).detail.reason).toBe('size');
    });
  });
});
