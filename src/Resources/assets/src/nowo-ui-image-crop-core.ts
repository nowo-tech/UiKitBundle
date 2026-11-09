/**
 * Image crop, the part without a DOM: options read from the input's attributes, the geometry of the frame and the image (move, zoom,
 * limits) and the size, type and name of the output. `nowo-ui-image-crop.ts` draws and listens; everything it decides comes from here.
 *
 * Geometry. The stage is the box the user sees (CSS px). The frame is the crop area inside it, centred, with a margin so the image
 * around it shows dimmed. A view is the image's scale (CSS px per image px) and the position of its top-left corner relative to the
 * frame's top-left corner. The image always covers the whole frame: the scale is at least the "cover" scale, and the position keeps
 * every edge of the frame on the image. The crop is the frame read back in image pixels.
 *
 * Importing touches no DOM.
 */

export type Shape = 'square' | 'circle';
export type Format = 'auto' | 'png' | 'webp' | 'jpeg';

export interface Labels {
  title: string;
  instructions: string;
  stage: string;
  zoom: string;
  zoomStatus: string;
  apply: string;
  cancel: string;
  close: string;
  /** The crop cannot be encoded within max-bytes; `%max%` is the limit (e.g. "300 KB"). */
  tooLarge: string;
  /** Shown (polite) while the crop is encoded again to fit max-bytes. */
  optimizing: string;
}

export interface CropOptions {
  shape: Shape;
  /** Width / height of the frame; null = free (the frame takes the image's own proportions, within 1:3 and 3:1). */
  aspect: number | null;
  /** Longest edge of the output in pixels; the crop is never enlarged. */
  max: number;
  /** Shortest edge the crop may have, in image pixels: zooming in stops there. */
  min: number;
  /** `auto`: WebP when the engine encodes it, else PNG with transparency and JPEG without. */
  format: Format;
  /** Largest output file in bytes (null: no limit); quality, then size, step down to fit. */
  maxBytes: number | null;
  /** Encoder quality for WebP and JPEG (0 to 1). */
  quality: number;
  /** Circle only: the pixels outside the circle become transparent (PNG or WebP). */
  bakeCircle: boolean;
  /** Id of an <img> that shows the crop once applied. */
  preview: string | null;
  labels: Partial<Labels>;
}

export interface Size {
  width: number;
  height: number;
}

export interface Rect extends Size {
  x: number;
  y: number;
}

export interface View {
  scale: number;
  x: number;
  y: number;
}

export interface Bounds {
  minScale: number;
  maxScale: number;
}

export const ATTR = 'data-nowo-ui-image-crop';

export const DEFAULT_LABELS: Labels = {
  title: 'Crop the image',
  instructions: 'Drag the image or use the arrow keys to move it. Zoom with the slider, the mouse wheel, a pinch, or the + and - keys. Enter applies, Escape cancels.',
  stage: 'Image to crop',
  zoom: 'Zoom',
  zoomStatus: 'Zoom %percent%%',
  apply: 'Apply',
  cancel: 'Cancel',
  close: 'Close',
  tooLarge: 'This crop cannot be made smaller than %max%. Crop a smaller part of the image, or cancel.',
  optimizing: 'Optimizing the image…',
};

export const DEFAULTS = { max: 1024, min: 1, quality: 0.92 } as const;
/** Limits of `max` (a canvas larger than 8192 px on a side fails in some browsers). */
export const MAX_RANGE = [16, 8192] as const;
/** How far the user can zoom in from the cover scale. */
export const ZOOM_LIMIT = 8;
/** Free aspect: the frame keeps the image's proportions within these. */
export const FREE_ASPECT_RANGE = [1 / 3, 3] as const;
/** Margin around the frame, as a share of the stage's shorter side. */
export const FRAME_MARGIN = 0.08;
/** Arrow keys move by this many CSS px; with Shift, by the larger step. */
export const KEY_STEP = 10;
export const KEY_STEP_LARGE = 50;
/** One zoom key press multiplies the scale by this. */
export const KEY_ZOOM = 1.1;

const clamp = (value: number, low: number, high: number): number => Math.min(high, Math.max(low, value));

/**
 * The aspect of an attribute: `free`, a number (`1`, `1.5`) or a ratio (`16/9`, `16:9`). Anything else, zero or negative gives 1.
 * Kept between 1:10 and 10:1.
 */
export function parseAspect(raw: string | null): number | null {
  const value = (raw ?? '').trim().toLowerCase();
  if (value === 'free') {
    return null;
  }
  const ratio = /^(\d+(?:\.\d+)?)\s*[/:]\s*(\d+(?:\.\d+)?)$/.exec(value);
  const number = ratio ? Number(ratio[1]) / Number(ratio[2]) : /^\d+(?:\.\d+)?$/.test(value) ? Number(value) : Number.NaN;

  return Number.isFinite(number) && number > 0 ? clamp(number, 0.1, 10) : 1;
}

function parseInteger(raw: string | null, fallback: number, low: number, high: number): number {
  const value = (raw ?? '').trim();
  return /^\d+$/.test(value) ? clamp(Number(value), low, high) : fallback;
}

/** The labels of an attribute (JSON object of strings; unknown keys and other values are left out). */
export function parseLabels(raw: string | null): Partial<Labels> {
  const labels: Partial<Labels> = {};
  if (!raw) {
    return labels;
  }
  try {
    const parsed: unknown = JSON.parse(raw);
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
      for (const key of Object.keys(DEFAULT_LABELS) as (keyof Labels)[]) {
        const value = Object.prototype.hasOwnProperty.call(parsed, key) ? (parsed as Record<string, unknown>)[key] : undefined;
        if (typeof value === 'string' && value !== '') {
          labels[key] = value;
        }
      }
    }
  } catch {
    // A broken attribute leaves the default wording.
  }
  return labels;
}

/**
 * The options of an input, read through `get` (an attribute reader); every value has a safe default. A max-bytes that is not a
 * positive integer (digits only) is ignored, as the PHP helper refuses it, with a message through `warn`.
 */
export function readOptions(get: (name: string) => string | null, warn: (message: string) => void = () => undefined): CropOptions {
  const shape: Shape = get(`${ATTR}-shape`) === 'circle' ? 'circle' : 'square';
  const rawFormat = get(`${ATTR}-format`);
  const format: Format = rawFormat === 'png' || rawFormat === 'webp' || rawFormat === 'jpeg' ? rawFormat : 'auto';
  const rawBytes = (get(`${ATTR}-max-bytes`) ?? '').trim();
  const maxBytes = /^\d+$/.test(rawBytes) && Number(rawBytes) > 0 ? Number(rawBytes) : null;
  if (maxBytes === null && rawBytes !== '') {
    warn(`UiKit image crop: ignored an invalid ${ATTR}-max-bytes value "${rawBytes}" (a positive number of bytes, digits only).`);
  }
  const rawQuality = Number.parseFloat(get(`${ATTR}-quality`) ?? '');
  const bake = get(`${ATTR}-bake-circle`);
  const preview = (get(`${ATTR}-preview`) ?? '').trim();

  return {
    shape,
    aspect: shape === 'circle' ? 1 : parseAspect(get(`${ATTR}-aspect`)),
    max: parseInteger(get(`${ATTR}-max`), DEFAULTS.max, MAX_RANGE[0], MAX_RANGE[1]),
    min: parseInteger(get(`${ATTR}-min`), DEFAULTS.min, 1, MAX_RANGE[1]),
    format,
    maxBytes,
    quality: Number.isFinite(rawQuality) ? clamp(rawQuality, 0, 1) : DEFAULTS.quality,
    // JPEG has no transparency: a baked circle needs PNG or WebP.
    bakeCircle: shape === 'circle' && format !== 'jpeg' && bake !== null && bake !== 'false',
    preview: preview === '' ? null : preview,
    labels: parseLabels(get(`${ATTR}-labels`)),
  };
}

/** The frame's aspect for an image: the option's, or with `free` the image's own (within 1:3 and 3:1). */
export function frameAspect(aspect: number | null, image: Size): number {
  if (aspect !== null) {
    return aspect;
  }
  return clamp(image.width / image.height, FREE_ASPECT_RANGE[0], FREE_ASPECT_RANGE[1]);
}

/** The frame inside a stage: the largest rectangle of that aspect inside the stage less its margin, centred. */
export function frameRect(stage: Size, aspect: number): Rect {
  const margin = Math.min(stage.width, stage.height) * FRAME_MARGIN;
  const room = { width: Math.max(1, stage.width - 2 * margin), height: Math.max(1, stage.height - 2 * margin) };
  const width = Math.min(room.width, room.height * aspect);
  const height = width / aspect;

  return { x: (stage.width - width) / 2, y: (stage.height - height) / 2, width, height };
}

/**
 * The scales a view may take: from the cover scale (the image fills the frame) to `ZOOM_LIMIT` times it, and no further than the
 * scale at which the crop's shorter edge is `min` image pixels. An image too small for `min` cannot zoom at all.
 */
export function scaleBounds(frame: Size, image: Size, min = 1): Bounds {
  const minScale = Math.max(frame.width / image.width, frame.height / image.height);
  const byMin = Math.min(frame.width, frame.height) / Math.max(1, min);

  return { minScale, maxScale: Math.max(minScale, Math.min(minScale * ZOOM_LIMIT, byMin)) };
}

/** A view kept within its bounds: scale within the bounds, and no part of the frame off the image. */
export function clampView(view: View, frame: Size, image: Size, bounds: Bounds): View {
  const scale = clamp(view.scale, bounds.minScale, bounds.maxScale);
  const x = clamp(view.x, frame.width - image.width * scale, 0);
  const y = clamp(view.y, frame.height - image.height * scale, 0);

  return { scale, x, y };
}

/** The first view: the cover scale, the image centred on the frame. */
export function initialView(frame: Size, image: Size, bounds: Bounds): View {
  const scale = bounds.minScale;
  return clampView({ scale, x: (frame.width - image.width * scale) / 2, y: (frame.height - image.height * scale) / 2 }, frame, image, bounds);
}

/** The view moved by (dx, dy) CSS px: the image follows the pointer, or the arrow keys. */
export function panBy(view: View, dx: number, dy: number, frame: Size, image: Size, bounds: Bounds): View {
  return clampView({ scale: view.scale, x: view.x + dx, y: view.y + dy }, frame, image, bounds);
}

/** The view at another scale, the image point under `focal` (frame coordinates) staying where it is. */
export function zoomTo(view: View, scale: number, focal: { x: number; y: number }, frame: Size, image: Size, bounds: Bounds): View {
  const next = clamp(scale, bounds.minScale, bounds.maxScale);
  const pointX = (focal.x - view.x) / view.scale;
  const pointY = (focal.y - view.y) / view.scale;

  return clampView({ scale: next, x: focal.x - pointX * next, y: focal.y - pointY * next }, frame, image, bounds);
}

/** The centre of a frame, in frame coordinates (where keys and the slider zoom). */
export const frameCentre = (frame: Size): { x: number; y: number } => ({ x: frame.width / 2, y: frame.height / 2 });

/** The zoom of a view as a factor of the cover scale (1 = the whole frame filled, nothing more). */
export const zoomFactor = (view: View, bounds: Bounds): number => view.scale / bounds.minScale;

/** The zoom of a view in percent of the cover scale, rounded. */
export const zoomPercent = (view: View, bounds: Bounds): number => Math.round(zoomFactor(view, bounds) * 100);

/**
 * The view for a new frame (the stage was resized, a phone was turned): the same zoom factor and the same image point at the
 * frame's centre.
 */
export function refit(view: View, oldFrame: Size, oldBounds: Bounds, newFrame: Size, image: Size, newBounds: Bounds): View {
  const factor = zoomFactor(view, oldBounds);
  const centreX = (oldFrame.width / 2 - view.x) / view.scale;
  const centreY = (oldFrame.height / 2 - view.y) / view.scale;
  const scale = clamp(factor * newBounds.minScale, newBounds.minScale, newBounds.maxScale);

  return clampView({ scale, x: newFrame.width / 2 - centreX * scale, y: newFrame.height / 2 - centreY * scale }, newFrame, image, newBounds);
}

/** The crop in image pixels: the frame read back through the view. */
export function sourceRect(view: View, frame: Size): Rect {
  return { x: -view.x / view.scale, y: -view.y / view.scale, width: frame.width / view.scale, height: frame.height / view.scale };
}

/**
 * The output's size for a crop: the crop's own size, made smaller (never larger) so its longest edge is at most `max`. A square
 * frame gives a square output; any other keeps the crop's proportions, at least 1 × 1.
 */
export function outputSize(source: Size, max: number, square: boolean): Size {
  const factor = Math.min(1, max / Math.max(source.width, source.height));
  const width = Math.max(1, Math.round(source.width * factor));
  const height = square ? width : Math.max(1, Math.round(source.height * factor));

  return { width: Math.min(width, max), height: Math.min(height, max) };
}

export const MIME: Record<Exclude<Format, 'auto'>, string> = { png: 'image/png', webp: 'image/webp', jpeg: 'image/jpeg' };

/**
 * The types to try, in order. `auto`: WebP first (kept only if the engine really encoded WebP), then PNG for a crop with transparent
 * pixels or JPEG for an opaque one. An explicit format is tried alone: the engine may still answer with PNG (no WebP encoder).
 */
export function candidateTypes(format: Format, transparent: boolean): string[] {
  if (format === 'auto') {
    return ['image/webp', transparent ? 'image/png' : 'image/jpeg'];
  }
  return [MIME[format]];
}

/** Whether RGBA pixel data has any pixel that is not fully opaque. */
export function hasTransparency(data: ArrayLike<number>): boolean {
  for (let i = 3; i < data.length; i += 4) {
    if (data[i] < 255) {
      return true;
    }
  }
  return false;
}

/** Quality steps for a lossy type over max-bytes, and the lowest quality used. */
export const QUALITY_STEPS = [0.92, 0.85, 0.78, 0.7, 0.6, 0.55, 0.5] as const;
export const QUALITY_FLOOR = 0.5;
/** Each size step multiplies the output's edges by this. */
export const SCALE_STEP = 0.85;
/** Encodes tried for one crop, at most (each is an async toBlob). */
export const MAX_ATTEMPTS = 10;

export interface Attempt {
  quality: number;
  scale: number;
}

/** The output size at a scale: both edges scaled, at least 1 px, a square kept square. */
export function scaledSize(size: Size, scale: number, square: boolean): Size {
  const width = Math.max(1, Math.round(size.width * scale));
  return { width, height: square ? width : Math.max(1, Math.round(size.height * scale)) };
}

/**
 * The next encode for a file over max-bytes, null when nothing smaller is left. A lossy type first lowers the quality along
 * QUALITY_STEPS (never below QUALITY_FLOOR); then, or for PNG, the size steps down by SCALE_STEP, never below `min` on the
 * shorter edge.
 */
export function nextAttempt(previous: Attempt, lossy: boolean, size: Size, min: number): Attempt | null {
  if (lossy) {
    const quality = QUALITY_STEPS.find((step) => step < previous.quality - 1e-9);
    if (quality !== undefined && quality >= QUALITY_FLOOR) {
      return { quality, scale: previous.scale };
    }
  }
  const shorter = Math.min(size.width, size.height);
  const scale = Math.max(previous.scale * SCALE_STEP, Math.min(1, min / shorter));
  if (scale >= previous.scale - 1e-9 || Math.round(shorter * scale) >= Math.round(shorter * previous.scale)) {
    return null;
  }
  return { quality: previous.quality, scale };
}

/** A byte count for people: "100 B", "300 KB", "1.5 MB" (1024-based). */
export function formatBytes(bytes: number): string {
  if (bytes >= 1024 * 1024) {
    return `${Number((bytes / (1024 * 1024)).toFixed(1))} MB`;
  }
  return bytes >= 1024 ? `${Math.round(bytes / 1024)} KB` : `${bytes} B`;
}
const EXTENSION: Record<string, string> = { 'image/png': 'png', 'image/webp': 'webp', 'image/jpeg': 'jpg' };

/** The output's file name: the original's base name with the extension of the type the browser really encoded. */
export function outputName(original: string, mime: string): string {
  const base = original.replace(/\.[^./\\]*$/, '').replace(/[\\/]/g, '') || 'image';
  return `${base}.${EXTENSION[mime] ?? 'png'}`;
}

/**
 * Whether a picked file is cropped: a raster image the browser can decode on a canvas. SVG (a vector, and script-capable) and
 * anything that is not an image pass through untouched.
 */
export function isCroppable(type: string): boolean {
  const value = type.toLowerCase();
  return value.startsWith('image/') && value !== 'image/svg+xml';
}

export type KeyAction = { pan: [number, number] } | { zoom: number } | { reset: true } | { apply: true } | null;

/** What a key does on the stage. Arrows move the image (Shift: further), + and - (or Page Up / Down) zoom, Home resets, Enter applies. */
export function keyAction(key: string, shift: boolean): KeyAction {
  const step = shift ? KEY_STEP_LARGE : KEY_STEP;
  switch (key) {
    case 'ArrowLeft':
      return { pan: [-step, 0] };
    case 'ArrowRight':
      return { pan: [step, 0] };
    case 'ArrowUp':
      return { pan: [0, -step] };
    case 'ArrowDown':
      return { pan: [0, step] };
    case '+':
    case '=':
    case 'PageUp':
      return { zoom: KEY_ZOOM };
    case '-':
    case '_':
    case 'PageDown':
      return { zoom: 1 / KEY_ZOOM };
    case 'Home':
      return { reset: true };
    case 'Enter':
      return { apply: true };
    default:
      return null;
  }
}

/** The zoom factor of one wheel event (a trackpad pinch arrives as a wheel with ctrlKey and small deltas). */
export function wheelFactor(deltaY: number, deltaMode = 0): number {
  const pixels = deltaMode === 1 ? deltaY * 16 : deltaMode === 2 ? deltaY * 400 : deltaY;
  return Math.exp(-clamp(pixels, -200, 200) * 0.002);
}

/** The text of the zoom status, `%percent%` replaced. */
export const zoomText = (template: string, percent: number): string => template.replace('%percent%', String(percent));
