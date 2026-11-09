/**
 * Image crop (`js/nowo-ui-image-crop.js`, opt-in: add `image-crop` to `script_names`): a crop step for the file inputs that carry
 * `data-nowo-ui-image-crop`. When an image is picked, a dialog (the kit's dialog anatomy, opened through js/nowo-ui-dialog.js when it
 * is loaded) shows it under a square or round frame. The image moves (drag, arrow keys) and zooms (slider, wheel, pinch, + and -).
 * "Apply" draws the crop on a canvas and puts it in the input in place of the picked file (DataTransfer), so the form uploads the crop
 * and the server validates it as any upload. "Cancel", the close button and Escape clear the input.
 *
 * Contract (attributes on the input; `ImageCrop::attributes()`, the FileType option `nowo_ui_image_crop` and the Twig function
 * `nowo_ui_image_crop_attrs()` print them): -shape square|circle, -aspect 1|free|16/9, -max (px), -min (px), -format png|webp|jpeg,
 * -quality, -bake-circle, -preview (id of an <img>), -labels (JSON). See nowo-ui-image-crop-core.ts and docs/USAGE.md.
 *
 * Events on the input (bubbling): `nowo-ui:image-crop:open` {file} (cancelable: the file then stays as picked), `:cropped` {file,
 * original, width, height}, `:cancel` {original}, `:error` {reason, original} (the file stays as picked). After the crop and after a
 * cancel, native `input` and `change` are dispatched again; the picked file's own `input` and `change` never reach other listeners
 * (stopped in the capture phase on window), so a host listener only ever sees the final file.
 *
 * Fallback: without DataTransfer, canvas toBlob or <dialog>, a `multiple` input, a file that is not a raster image (SVG included) or an
 * image the browser cannot decode, the upload goes on as picked: no crop, no error shown.
 *
 * CSP: no style attribute and no inline script. The canvas is sized through its width and height attributes, colours come from CSS
 * custom properties (`--nowo-ui-image-crop-mask`, `--nowo-ui-image-crop-frame`). The preview needs `img-src blob:`.
 * Importing touches no DOM.
 */
import {
  ATTR,
  clampView,
  DEFAULT_LABELS,
  frameAspect,
  frameCentre,
  frameRect,
  initialView,
  isCroppable,
  keyAction,
  MIME,
  outputName,
  outputSize,
  panBy,
  readOptions,
  refit,
  scaleBounds,
  sourceRect,
  wheelFactor,
  zoomPercent,
  zoomText,
  zoomTo,
  type Bounds,
  type CropOptions,
  type Labels,
  type Rect,
  type Size,
  type View,
} from './nowo-ui-image-crop-core';

export const DIALOG_ID = 'nowo-ui-image-crop';
const INPUT = `input[type="file"][${ATTR}]`;
/** Stage size used when the stage has no layout (hidden, or a test DOM). */
const FALLBACK_STAGE: Size = { width: 320, height: 320 };

export interface Decoded {
  source: CanvasImageSource;
  width: number;
  height: number;
  close(): void;
}

type DialogWindow = Window & {
  nowoUiOpenDialog?: (id: string | HTMLDialogElement | null, opener?: Element | null) => void;
  nowoUiCloseDialog?: (id: string | HTMLDialogElement | null) => void;
};

/** Browser seams (decode, encode, 2D context), replaced by the tests. */
export const env = {
  /** Decodes a file upright (EXIF orientation applied): createImageBitmap, else an <img> on an object URL. */
  async decode(file: File): Promise<Decoded> {
    if (typeof createImageBitmap === 'function') {
      try {
        const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
        return { source: bitmap, width: bitmap.width, height: bitmap.height, close: () => bitmap.close() };
      } catch {
        // Older engines refuse the option or the type: the <img> below (which applies EXIF orientation by default).
      }
    }
    const url = URL.createObjectURL(file);
    const image = new Image();
    image.src = url;
    try {
      await image.decode();
    } catch (error) {
      URL.revokeObjectURL(url);
      throw error;
    }
    return { source: image, width: image.naturalWidth, height: image.naturalHeight, close: () => URL.revokeObjectURL(url) };
  },
  encode(canvas: HTMLCanvasElement, type: string, quality: number): Promise<Blob | null> {
    return new Promise((resolve) => {
      canvas.toBlob(resolve, type, quality);
    });
  },
  context(canvas: HTMLCanvasElement): CanvasRenderingContext2D | null {
    return canvas.getContext('2d');
  },
  createObjectURL(blob: Blob): string {
    return URL.createObjectURL(blob);
  },
  revokeObjectURL(url: string): void {
    URL.revokeObjectURL(url);
  },
};

/** Whether this browser can crop: DataTransfer that builds a file list, canvas toBlob and a modal <dialog>. */
export function supported(): boolean {
  try {
    if (
      typeof DataTransfer !== 'function' ||
      typeof HTMLCanvasElement === 'undefined' ||
      typeof HTMLCanvasElement.prototype.toBlob !== 'function' ||
      typeof HTMLDialogElement === 'undefined' ||
      typeof HTMLDialogElement.prototype.showModal !== 'function'
    ) {
      return false;
    }
    const transfer = new DataTransfer();
    transfer.items.add(new File([''], 'probe.png', { type: 'image/png' }));
    return transfer.files.length === 1;
  } catch {
    return false;
  }
}

/** Puts `file` in the input in place of its files; false when the browser does not take a DataTransfer's list. */
export function replaceFiles(input: HTMLInputElement, file: File): boolean {
  try {
    const transfer = new DataTransfer();
    transfer.items.add(file);
    input.files = transfer.files;
    const now = input.files?.[0];
    return input.files?.length === 1 && now !== undefined && now.name === file.name && now.size === file.size;
  } catch {
    return false;
  }
}

let replaying = false;

/** Dispatches `input` and `change` on the input again (the crop step's own, which it lets through). */
function replay(input: HTMLInputElement): void {
  replaying = true;
  try {
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  } finally {
    replaying = false;
  }
}

const emit = (input: HTMLInputElement, name: string, detail: Record<string, unknown>, cancelable = false): boolean =>
  input.dispatchEvent(new CustomEvent(`nowo-ui:image-crop:${name}`, { bubbles: true, cancelable, detail }));

interface Ui {
  dialog: HTMLDialogElement;
  title: HTMLElement;
  help: HTMLElement;
  stage: HTMLElement;
  canvas: HTMLCanvasElement;
  zoomLabel: HTMLElement;
  range: HTMLInputElement;
  status: HTMLElement;
  apply: HTMLButtonElement;
  cancel: HTMLButtonElement;
  close: HTMLButtonElement;
}

function element<K extends keyof HTMLElementTagNameMap>(tag: K, className: string, attributes: Record<string, string> = {}): HTMLElementTagNameMap[K] {
  const node = document.createElement(tag);
  if (className !== '') {
    node.className = className;
  }
  for (const [name, value] of Object.entries(attributes)) {
    node.setAttribute(name, value);
  }
  return node;
}

let ui: Ui | null = null;
let current: Session | null = null;
/** The input focus goes back to when the dialog closes. */
let returnFocus: HTMLInputElement | null = null;

/** The crop dialog, built once per page with the anatomy of the `dialog` partial (header, body, footer) and the kit's classes. */
export function cropDialog(): Ui {
  if (ui && ui.dialog.isConnected) {
    return ui;
  }
  const dialog = element('dialog', 'nowo-ui-dialog nowo-ui-image-crop', { id: DIALOG_ID, 'data-nowo-ui-dialog': '', 'data-nowo-ui-image-crop-dialog': '', 'aria-labelledby': `${DIALOG_ID}-title` });
  const header = element('header', 'nowo-ui-dialog__header');
  const title = element('h2', 'nowo-ui-dialog__title', { id: `${DIALOG_ID}-title` });
  const close = element('button', 'nowo-ui-dialog__close', { type: 'button', 'data-nowo-ui-image-crop-cancel': '' });
  const cross = element('span', '', { 'aria-hidden': 'true' });
  cross.textContent = '×';
  close.append(cross);
  header.append(title, close);

  const body = element('div', 'nowo-ui-dialog__body nowo-ui-image-crop__body');
  const help = element('p', 'nowo-ui-image-crop__help', { id: `${DIALOG_ID}-help` });
  const stage = element('div', 'nowo-ui-image-crop__stage', { 'data-nowo-ui-image-crop-stage': '', tabindex: '0', role: 'application', 'aria-describedby': `${DIALOG_ID}-help`, autofocus: '' });
  const canvas = element('canvas', 'nowo-ui-image-crop__canvas', { 'aria-hidden': 'true' });
  stage.append(canvas);
  const zoom = element('div', 'nowo-ui-image-crop__zoom');
  const zoomLabel = element('label', 'nowo-ui-image-crop__zoom-label', { for: `${DIALOG_ID}-zoom` });
  const range = element('input', 'nowo-ui-image-crop__range', { id: `${DIALOG_ID}-zoom`, type: 'range', min: '100', max: '800', step: '1', value: '100', 'data-nowo-ui-image-crop-zoom': '' });
  zoom.append(zoomLabel, range);
  const status = element('p', 'visually-hidden', { role: 'status', 'aria-live': 'polite', 'data-nowo-ui-image-crop-status': '' });
  body.append(help, stage, zoom, status);

  const footer = element('footer', 'nowo-ui-dialog__footer');
  const cancel = element('button', 'nowo-ui-btn nowo-ui-btn--secondary', { type: 'button', 'data-nowo-ui-image-crop-cancel': '' });
  const apply = element('button', 'nowo-ui-btn nowo-ui-btn--primary', { type: 'button', 'data-nowo-ui-image-crop-apply': '' });
  footer.append(cancel, apply);
  dialog.append(header, body, footer);
  document.body.append(dialog);

  ui = { dialog, title, help, stage, canvas, zoomLabel, range, status, apply, cancel, close };
  bindUi(ui);
  return ui;
}

function setLabels(target: Ui, labels: Labels): void {
  target.title.textContent = labels.title;
  target.help.textContent = labels.instructions;
  target.stage.setAttribute('aria-label', labels.stage);
  target.zoomLabel.textContent = labels.zoom;
  target.apply.textContent = labels.apply;
  target.cancel.textContent = labels.cancel;
  target.close.setAttribute('aria-label', labels.close);
  target.close.setAttribute('title', labels.close);
}

const ratio = (): number => Math.min(3, Math.max(1, window.devicePixelRatio || 1));

function cssValue(node: Element, name: string, fallback: string): string {
  const value = window.getComputedStyle(node).getPropertyValue(name).trim();
  return value === '' ? fallback : value;
}

/** One crop: the picked file, its decoded image and the view the user moves. */
export class Session {
  stage: Size = FALLBACK_STAGE;
  frame: Rect = { x: 0, y: 0, ...FALLBACK_STAGE };
  bounds: Bounds = { minScale: 1, maxScale: 1 };
  view: View = { scale: 1, x: 0, y: 0 };
  private readonly pointers = new Map<number, { x: number; y: number }>();
  private pinch: { distance: number; scale: number } | null = null;
  private laidOut = false;
  finished = false;
  busy = false;

  constructor(
    readonly input: HTMLInputElement,
    readonly original: File,
    readonly image: Decoded,
    readonly options: CropOptions,
    readonly ui: Ui,
  ) {}

  get labels(): Labels {
    return { ...DEFAULT_LABELS, ...this.options.labels };
  }

  /** Measures the stage and fits the frame and the view to it (keeping the zoom and the centre after the first time). */
  layout(): void {
    const measured = { width: this.ui.stage.clientWidth, height: this.ui.stage.clientHeight };
    const stage = measured.width > 0 && measured.height > 0 ? measured : FALLBACK_STAGE;
    const frame = frameRect(stage, frameAspect(this.options.aspect, this.image));
    const bounds = scaleBounds(frame, this.image, this.options.min);
    this.view = this.laidOut ? refit(this.view, this.frame, this.bounds, frame, this.image, bounds) : initialView(frame, this.image, bounds);
    this.stage = stage;
    this.frame = frame;
    this.bounds = bounds;
    this.laidOut = true;
    const dpr = ratio();
    this.ui.canvas.width = Math.round(stage.width * dpr);
    this.ui.canvas.height = Math.round(stage.height * dpr);
    this.ui.stage.classList.toggle('is-circle', this.options.shape === 'circle');
    this.syncRange();
    this.draw();
  }

  /** The image under a dimmed mask with the frame cut out (a circle or a rectangle) and outlined. */
  draw(): void {
    const ctx = env.context(this.ui.canvas);
    if (!ctx) {
      return;
    }
    const dpr = ratio();
    const { frame, view, stage } = this;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, stage.width, stage.height);
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(this.image.source, frame.x + view.x, frame.y + view.y, this.image.width * view.scale, this.image.height * view.scale);
    ctx.beginPath();
    ctx.rect(0, 0, stage.width, stage.height);
    this.framePath(ctx);
    ctx.fillStyle = cssValue(this.ui.stage, '--nowo-ui-image-crop-mask', 'rgb(0 0 0 / 55%)');
    ctx.fill('evenodd');
    ctx.beginPath();
    this.framePath(ctx);
    ctx.lineWidth = 2;
    ctx.strokeStyle = cssValue(this.ui.stage, '--nowo-ui-image-crop-frame', 'rgb(255 255 255)');
    ctx.stroke();
  }

  private framePath(ctx: CanvasRenderingContext2D): void {
    const { frame } = this;
    if (this.options.shape === 'circle') {
      ctx.moveTo(frame.x + frame.width, frame.y + frame.height / 2);
      ctx.arc(frame.x + frame.width / 2, frame.y + frame.height / 2, frame.width / 2, 0, Math.PI * 2);
    } else {
      ctx.rect(frame.x, frame.y, frame.width, frame.height);
    }
  }

  setView(view: View, announce = false): void {
    this.view = clampView(view, this.frame, this.image, this.bounds);
    this.syncRange();
    if (announce) {
      this.ui.status.textContent = zoomText(this.labels.zoomStatus, zoomPercent(this.view, this.bounds));
    }
    this.draw();
  }

  syncRange(): void {
    const { range } = this.ui;
    const max = Math.max(100, Math.floor((this.bounds.maxScale / this.bounds.minScale) * 100));
    const percent = zoomPercent(this.view, this.bounds);
    range.max = String(max);
    range.value = String(Math.min(max, percent));
    range.disabled = max <= 100;
    range.setAttribute('aria-valuetext', zoomText(this.labels.zoomStatus, percent));
  }

  /** Stage coordinates of a pointer event. */
  private point(event: { clientX: number; clientY: number }): { x: number; y: number } {
    const rect = this.ui.stage.getBoundingClientRect();
    return { x: event.clientX - rect.left, y: event.clientY - rect.top };
  }

  /** Frame coordinates of a stage point. */
  private toFrame(point: { x: number; y: number }): { x: number; y: number } {
    return { x: point.x - this.frame.x, y: point.y - this.frame.y };
  }

  private pinchState(): { distance: number; mid: { x: number; y: number } } {
    const [a, b] = [...this.pointers.values()];
    return { distance: Math.max(1, Math.hypot(b.x - a.x, b.y - a.y)), mid: { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 } };
  }

  pointerDown(event: PointerEvent): void {
    if (event.pointerType === 'mouse' && event.button !== 0) {
      return;
    }
    event.preventDefault();
    try {
      this.ui.stage.setPointerCapture(event.pointerId);
    } catch {
      // A synthetic pointer has nothing to capture.
    }
    this.pointers.set(event.pointerId, this.point(event));
    if (this.pointers.size === 2) {
      this.pinch = { distance: this.pinchState().distance, scale: this.view.scale };
    }
  }

  pointerMove(event: PointerEvent): void {
    const previous = this.pointers.get(event.pointerId);
    if (!previous) {
      return;
    }
    event.preventDefault();
    if (this.pointers.size === 1) {
      const next = this.point(event);
      this.pointers.set(event.pointerId, next);
      this.setView(panBy(this.view, next.x - previous.x, next.y - previous.y, this.frame, this.image, this.bounds));
      return;
    }
    const before = this.pinchState();
    this.pointers.set(event.pointerId, this.point(event));
    const after = this.pinchState();
    const pinch = this.pinch ?? { distance: before.distance, scale: this.view.scale };
    const zoomed = zoomTo(this.view, pinch.scale * (after.distance / pinch.distance), this.toFrame(after.mid), this.frame, this.image, this.bounds);
    this.setView(panBy(zoomed, after.mid.x - before.mid.x, after.mid.y - before.mid.y, this.frame, this.image, this.bounds));
  }

  pointerUp(event: PointerEvent): void {
    this.pointers.delete(event.pointerId);
    if (this.pointers.size < 2) {
      this.pinch = null;
    }
  }

  wheel(event: WheelEvent): void {
    event.preventDefault();
    const focal = this.toFrame(this.point(event));
    this.setView(zoomTo(this.view, this.view.scale * wheelFactor(event.deltaY, event.deltaMode), focal, this.frame, this.image, this.bounds));
  }

  key(event: KeyboardEvent): void {
    if (event.altKey || event.ctrlKey || event.metaKey) {
      return;
    }
    const action = keyAction(event.key, event.shiftKey);
    if (action === null) {
      return;
    }
    event.preventDefault();
    if ('pan' in action) {
      this.setView(panBy(this.view, action.pan[0], action.pan[1], this.frame, this.image, this.bounds));
    } else if ('zoom' in action) {
      this.setView(zoomTo(this.view, this.view.scale * action.zoom, frameCentre(this.frame), this.frame, this.image, this.bounds), true);
    } else if ('reset' in action) {
      this.setView(initialView(this.frame, this.image, this.bounds), true);
    } else {
      void this.apply();
    }
  }

  rangeInput(): void {
    const percent = Number(this.ui.range.value);
    this.setView(zoomTo(this.view, (this.bounds.minScale * percent) / 100, frameCentre(this.frame), this.frame, this.image, this.bounds));
  }

  /** The crop as a canvas of its output size (transparent outside the circle when baked, white under a JPEG). */
  render(): { canvas: HTMLCanvasElement; size: Size } | null {
    const source = sourceRect(this.view, this.frame);
    const size = outputSize(source, this.options.max, this.options.aspect === 1);
    const canvas = document.createElement('canvas');
    canvas.width = size.width;
    canvas.height = size.height;
    const ctx = env.context(canvas);
    if (!ctx) {
      return null;
    }
    ctx.imageSmoothingQuality = 'high';
    if (this.options.format === 'jpeg') {
      ctx.fillStyle = '#fff';
      ctx.fillRect(0, 0, size.width, size.height);
    }
    if (this.options.bakeCircle) {
      ctx.beginPath();
      ctx.arc(size.width / 2, size.height / 2, Math.min(size.width, size.height) / 2, 0, Math.PI * 2);
      ctx.clip();
    }
    ctx.drawImage(this.image.source, source.x, source.y, source.width, source.height, 0, 0, size.width, size.height);
    return { canvas, size };
  }

  /** Encodes the crop and puts it in the input; on any failure the picked file stays. */
  async apply(): Promise<void> {
    if (this.finished || this.busy) {
      return;
    }
    this.busy = true;
    this.ui.apply.disabled = true;
    this.ui.apply.setAttribute('aria-busy', 'true');
    let file: File | null = null;
    let size: Size | null = null;
    try {
      const rendered = this.render();
      if (rendered) {
        size = rendered.size;
        const blob = await env.encode(rendered.canvas, MIME[this.options.format], this.options.quality);
        if (blob && blob.size > 0) {
          // A browser that cannot encode WebP gives PNG: the name and type follow what it really encoded.
          const type = blob.type || MIME[this.options.format];
          file = new File([blob], outputName(this.original.name, type), { type, lastModified: Date.now() });
        }
      }
    } catch {
      file = null;
    }
    this.busy = false;
    this.ui.apply.disabled = false;
    this.ui.apply.removeAttribute('aria-busy');
    if (this.finished) {
      return;
    }
    this.finished = true;
    if (file && size && replaceFiles(this.input, file)) {
      this.showPreview(file);
      emit(this.input, 'cropped', { file, original: this.original, width: size.width, height: size.height });
    } else {
      emit(this.input, 'error', { reason: file ? 'replace' : 'encode', original: this.original });
    }
    this.end();
  }

  /** Clears the input (the picked file is not uploaded). */
  cancel(): void {
    if (this.finished) {
      return;
    }
    this.finished = true;
    this.input.value = '';
    emit(this.input, 'cancel', { original: this.original });
    this.end();
  }

  private showPreview(file: File): void {
    const id = this.options.preview;
    const target = id === null ? null : document.getElementById(id);
    if (!(target instanceof HTMLImageElement)) {
      return;
    }
    const previous = previews.get(target);
    if (previous) {
      env.revokeObjectURL(previous);
    }
    const url = env.createObjectURL(file);
    previews.set(target, url);
    target.src = url;
    target.hidden = false;
  }

  private end(): void {
    this.image.close();
    if (current === this) {
      current = null;
    }
    returnFocus = this.input;
    replay(this.input);
    closeCropDialog(this.ui.dialog);
  }
}

/** Object URLs shown in preview images, revoked when replaced. */
const previews = new WeakMap<HTMLImageElement, string>();

function openCropDialog(dialog: HTMLDialogElement, opener: HTMLElement): void {
  const page = window as DialogWindow;
  if (typeof page.nowoUiOpenDialog === 'function') {
    page.nowoUiOpenDialog(dialog, opener);
  }
  if (!dialog.open) {
    dialog.showModal();
  }
}

function closeCropDialog(dialog: HTMLDialogElement): void {
  const page = window as DialogWindow;
  if (!dialog.open) {
    return;
  }
  if (typeof page.nowoUiCloseDialog === 'function') {
    page.nowoUiCloseDialog(dialog);
  } else {
    dialog.close();
  }
}

function bindUi(target: Ui): void {
  const { dialog, stage, range } = target;
  dialog.addEventListener('click', (event) => {
    const node = event.target instanceof Element ? event.target : null;
    if (!current || !node) {
      return;
    }
    if (node.closest('[data-nowo-ui-image-crop-apply]')) {
      event.preventDefault();
      void current.apply();
    } else if (node.closest('[data-nowo-ui-image-crop-cancel]')) {
      event.preventDefault();
      current.cancel();
    }
  });
  // Escape (the native `cancel`), the dialog runtime's backdrop or any other close: a session still open is cancelled.
  dialog.addEventListener('close', () => {
    const session = current;
    if (session && !session.finished) {
      session.cancel();
    }
    const input = returnFocus;
    returnFocus = null;
    if (input && input.isConnected) {
      input.focus();
    }
  });
  stage.addEventListener('pointerdown', (event) => current?.pointerDown(event));
  stage.addEventListener('pointermove', (event) => current?.pointerMove(event));
  stage.addEventListener('pointerup', (event) => current?.pointerUp(event));
  stage.addEventListener('pointercancel', (event) => current?.pointerUp(event));
  stage.addEventListener('lostpointercapture', (event) => current?.pointerUp(event));
  stage.addEventListener('wheel', (event) => current?.wheel(event), { passive: false });
  // touch-action: none stops scrolling; a cancelled touchmove also stops the fling that would swallow the next tap (Chromium).
  stage.addEventListener('touchmove', (event) => event.preventDefault(), { passive: false });
  stage.addEventListener('keydown', (event) => current?.key(event));
  range.addEventListener('input', () => current?.rangeInput());
  if (typeof ResizeObserver === 'function') {
    new ResizeObserver(() => current?.layout()).observe(stage);
  }
}

/** Whether a change on this input starts a crop: an opted-in single file input holding one raster image, in a browser that can. */
export function wantsCrop(target: EventTarget | null): target is HTMLInputElement {
  if (!(target instanceof HTMLInputElement) || !target.matches(INPUT) || target.multiple || target.disabled) {
    return false;
  }
  const file = target.files?.[0];
  return target.files?.length === 1 && file !== undefined && isCroppable(file.type) && supported();
}

/** Starts the crop of the input's picked file. Resolves with the session, or null when the upload goes on as picked. */
export async function startCrop(input: HTMLInputElement): Promise<Session | null> {
  const original = input.files?.[0];
  if (!original || current) {
    return null;
  }
  if (!emit(input, 'open', { file: original }, true)) {
    replay(input);
    return null;
  }
  let image: Decoded;
  try {
    image = await env.decode(original);
    if (!(image.width > 0 && image.height > 0)) {
      image.close();
      throw new Error('empty image');
    }
  } catch {
    emit(input, 'error', { reason: 'decode', original });
    replay(input);
    return null;
  }
  const options = readOptions((name) => input.getAttribute(name));
  const target = cropDialog();
  const session = new Session(input, original, image, options, target);
  current = session;
  setLabels(target, session.labels);
  target.status.textContent = '';
  openCropDialog(target.dialog, input);
  session.layout();
  target.stage.focus();
  // The dialog's box exists only once it is shown: measure again on the next frame.
  window.requestAnimationFrame(() => {
    if (current === session) {
      session.layout();
    }
  });
  return session;
}

/** The session on screen (tests and hosts that drive the dialog). */
export const currentSession = (): Session | null => current;

function onPick(event: Event): void {
  if (replaying || !wantsCrop(event.target)) {
    return;
  }
  // The picked file's own events stop here: listeners get the final file through replay().
  event.stopImmediatePropagation();
  if (event.type === 'change') {
    void startCrop(event.target);
  }
}

let bound = false;

export function bindNowoUiImageCrop(): void {
  if (bound) {
    return;
  }
  bound = true;
  window.addEventListener('input', onPick, true);
  window.addEventListener('change', onPick, true);
}

/** Test helper: forgets the dialog and the session. */
export function resetForTests(): void {
  ui?.dialog.remove();
  ui = null;
  current = null;
  returnFocus = null;
}

// A second copy of the script must not bind again. A symbol: an element with some id cannot turn this off.
const BOUND_KEY = Symbol.for('nowo-ui.image-crop.bound');
if (typeof window !== 'undefined' && typeof document !== 'undefined' && !(window as unknown as Record<symbol, unknown>)[BOUND_KEY]) {
  (window as unknown as Record<symbol, unknown>)[BOUND_KEY] = true;
  bindNowoUiImageCrop();
}
