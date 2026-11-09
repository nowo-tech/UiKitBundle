import { describe, expect, it } from 'vitest';
import {
  clampView,
  DEFAULT_LABELS,
  frameAspect,
  frameCentre,
  frameRect,
  initialView,
  isCroppable,
  keyAction,
  KEY_STEP,
  KEY_STEP_LARGE,
  KEY_ZOOM,
  outputName,
  outputSize,
  panBy,
  parseAspect,
  parseLabels,
  readOptions,
  refit,
  scaleBounds,
  sourceRect,
  wheelFactor,
  zoomFactor,
  zoomPercent,
  zoomText,
  zoomTo,
  ZOOM_LIMIT,
} from './nowo-ui-image-crop-core';

const reader =
  (attributes: Record<string, string>) =>
  (name: string): string | null =>
    attributes[name] ?? null;

describe('image crop options', () => {
  it('has safe defaults', () => {
    const options = readOptions(reader({}));
    expect(options).toEqual({ shape: 'square', aspect: 1, max: 1024, min: 1, format: 'png', quality: 0.92, bakeCircle: false, preview: null, labels: {} });
  });

  it('reads every attribute', () => {
    const options = readOptions(
      reader({
        'data-nowo-ui-image-crop-shape': 'square',
        'data-nowo-ui-image-crop-aspect': '16/9',
        'data-nowo-ui-image-crop-max': '640',
        'data-nowo-ui-image-crop-min': '200',
        'data-nowo-ui-image-crop-format': 'webp',
        'data-nowo-ui-image-crop-quality': '0.8',
        'data-nowo-ui-image-crop-preview': ' avatar-preview ',
        'data-nowo-ui-image-crop-labels': '{"apply":"Aplicar","zoom":3,"other":"x"}',
      }),
    );
    expect(options.aspect).toBeCloseTo(16 / 9);
    expect(options.max).toBe(640);
    expect(options.min).toBe(200);
    expect(options.format).toBe('webp');
    expect(options.quality).toBe(0.8);
    expect(options.preview).toBe('avatar-preview');
    expect(options.labels).toEqual({ apply: 'Aplicar' });
  });

  it('a circle is always 1:1 and bakes only with an alpha format', () => {
    const circle = readOptions(reader({ 'data-nowo-ui-image-crop-shape': 'circle', 'data-nowo-ui-image-crop-aspect': 'free', 'data-nowo-ui-image-crop-bake-circle': '' }));
    expect(circle.aspect).toBe(1);
    expect(circle.bakeCircle).toBe(true);
    expect(readOptions(reader({ 'data-nowo-ui-image-crop-shape': 'circle', 'data-nowo-ui-image-crop-bake-circle': 'false' })).bakeCircle).toBe(false);
    expect(readOptions(reader({ 'data-nowo-ui-image-crop-shape': 'circle', 'data-nowo-ui-image-crop-format': 'jpeg', 'data-nowo-ui-image-crop-bake-circle': 'true' })).bakeCircle).toBe(false);
    expect(readOptions(reader({ 'data-nowo-ui-image-crop-bake-circle': 'true' })).bakeCircle).toBe(false);
  });

  it('refuses bad values', () => {
    const options = readOptions(
      reader({
        'data-nowo-ui-image-crop-shape': 'star',
        'data-nowo-ui-image-crop-max': '99999',
        'data-nowo-ui-image-crop-min': '-3',
        'data-nowo-ui-image-crop-format': 'gif',
        'data-nowo-ui-image-crop-quality': '7',
        'data-nowo-ui-image-crop-preview': '   ',
      }),
    );
    expect(options.shape).toBe('square');
    expect(options.max).toBe(8192);
    expect(options.min).toBe(1);
    expect(options.format).toBe('png');
    expect(options.quality).toBe(1);
    expect(options.preview).toBeNull();
    expect(readOptions(reader({ 'data-nowo-ui-image-crop-max': '2' })).max).toBe(16);
  });

  it('parses aspects', () => {
    expect(parseAspect('free')).toBeNull();
    expect(parseAspect(' FREE ')).toBeNull();
    expect(parseAspect('1')).toBe(1);
    expect(parseAspect('1.5')).toBe(1.5);
    expect(parseAspect('4:3')).toBeCloseTo(4 / 3);
    expect(parseAspect('3 / 4')).toBe(0.75);
    expect(parseAspect('0')).toBe(1);
    expect(parseAspect('1/0')).toBe(1);
    expect(parseAspect('wide')).toBe(1);
    expect(parseAspect(null)).toBe(1);
    expect(parseAspect('100')).toBe(10);
  });

  it('parses labels', () => {
    expect(parseLabels(null)).toEqual({});
    expect(parseLabels('not json')).toEqual({});
    expect(parseLabels('["a"]')).toEqual({});
    expect(parseLabels('{"title":"","cancel":"Annuler"}')).toEqual({ cancel: 'Annuler' });
    expect(Object.keys(DEFAULT_LABELS)).toEqual(['title', 'instructions', 'stage', 'zoom', 'zoomStatus', 'apply', 'cancel', 'close']);
  });
});

describe('image crop geometry', () => {
  const stage = { width: 400, height: 400 };
  const image = { width: 1600, height: 1200 };

  it('fits the frame in the stage with a margin', () => {
    expect(frameRect(stage, 1)).toEqual({ x: 32, y: 32, width: 336, height: 336 });
    const wide = frameRect(stage, 2);
    expect(wide.width).toBe(336);
    expect(wide.height).toBe(168);
    expect(wide.y).toBe(116);
    const tall = frameRect({ width: 400, height: 200 }, 1);
    expect(tall).toEqual({ x: 116, y: 16, width: 168, height: 168 });
  });

  it('a free aspect takes the image proportions within 1:3 and 3:1', () => {
    expect(frameAspect(null, image)).toBeCloseTo(4 / 3);
    expect(frameAspect(null, { width: 1000, height: 10 })).toBe(3);
    expect(frameAspect(null, { width: 10, height: 1000 })).toBeCloseTo(1 / 3);
    expect(frameAspect(2, image)).toBe(2);
  });

  it('the image always covers the frame, and zooms up to the limit or the minimum crop', () => {
    const frame = frameRect(stage, 1);
    const bounds = scaleBounds(frame, image);
    expect(bounds.minScale).toBeCloseTo(336 / 1200);
    expect(bounds.maxScale).toBeCloseTo((336 / 1200) * ZOOM_LIMIT);
    const limited = scaleBounds(frame, image, 600);
    expect(limited.maxScale).toBeCloseTo(336 / 600);
    // Too small for the minimum: no zoom at all.
    expect(scaleBounds(frame, { width: 100, height: 100 }, 400)).toEqual({ minScale: 3.36, maxScale: 3.36 });
  });

  it('starts centred at the cover scale', () => {
    const frame = frameRect(stage, 1);
    const bounds = scaleBounds(frame, image);
    const view = initialView(frame, image, bounds);
    expect(view.y).toBeCloseTo(0);
    expect(view.x).toBeCloseTo((336 - 1600 * bounds.minScale) / 2);
    const crop = sourceRect(view, frame);
    expect(crop.x).toBeCloseTo(200);
    expect(crop.y).toBeCloseTo(0);
    expect(crop.width).toBeCloseTo(1200);
    expect(crop.height).toBeCloseTo(1200);
  });

  it('moving keeps every edge of the frame on the image', () => {
    const frame = frameRect(stage, 1);
    const bounds = scaleBounds(frame, image);
    const view = initialView(frame, image, bounds);
    const right = panBy(view, -1000, 0, frame, image, bounds);
    expect(right.x).toBeCloseTo(336 - 1600 * bounds.minScale);
    expect(sourceRect(right, frame).x).toBeCloseTo(400);
    const left = panBy(view, 1000, 50, frame, image, bounds);
    expect(left.x).toBe(0);
    expect(left.y).toBe(0);
    const moved = panBy(view, 10, 0, frame, image, bounds);
    expect(moved.x - view.x).toBeCloseTo(10);
  });

  it('zooms around a point that stays in place', () => {
    const frame = frameRect(stage, 1);
    const bounds = scaleBounds(frame, image);
    const view = initialView(frame, image, bounds);
    const focal = frameCentre(frame);
    const before = { x: (focal.x - view.x) / view.scale, y: (focal.y - view.y) / view.scale };
    const zoomed = zoomTo(view, view.scale * 2, focal, frame, image, bounds);
    expect(zoomFactor(zoomed, bounds)).toBeCloseTo(2);
    expect((focal.x - zoomed.x) / zoomed.scale).toBeCloseTo(before.x);
    expect((focal.y - zoomed.y) / zoomed.scale).toBeCloseTo(before.y);
    expect(sourceRect(zoomed, frame).width).toBeCloseTo(600);
    expect(zoomPercent(zoomed, bounds)).toBe(200);
    // Beyond the bounds: clamped, both ways.
    expect(zoomTo(view, 1000, focal, frame, image, bounds).scale).toBeCloseTo(bounds.maxScale);
    expect(zoomTo(zoomed, 0.0001, focal, frame, image, bounds).scale).toBeCloseTo(bounds.minScale);
    // Zooming out at a corner pulls the image back over the frame.
    const corner = zoomTo(zoomed, bounds.minScale, { x: 0, y: 0 }, frame, image, bounds);
    expect(clampView(corner, frame, image, bounds)).toEqual(corner);
  });

  it('keeps the zoom and the centre when the stage changes', () => {
    const frame = frameRect(stage, 1);
    const bounds = scaleBounds(frame, image);
    const view = panBy(zoomTo(initialView(frame, image, bounds), bounds.minScale * 3, frameCentre(frame), frame, image, bounds), -40, 20, frame, image, bounds);
    const centre = { x: (frame.width / 2 - view.x) / view.scale, y: (frame.height / 2 - view.y) / view.scale };
    const smaller = frameRect({ width: 300, height: 300 }, 1);
    const smallerBounds = scaleBounds(smaller, image);
    const next = refit(view, frame, bounds, smaller, image, smallerBounds);
    expect(zoomFactor(next, smallerBounds)).toBeCloseTo(3);
    expect((smaller.width / 2 - next.x) / next.scale).toBeCloseTo(centre.x);
    expect((smaller.height / 2 - next.y) / next.scale).toBeCloseTo(centre.y);
  });
});

describe('image crop output', () => {
  it('is never enlarged and at most max on its longest edge', () => {
    expect(outputSize({ width: 1200, height: 1200 }, 512, true)).toEqual({ width: 512, height: 512 });
    expect(outputSize({ width: 300.4, height: 300.4 }, 512, true)).toEqual({ width: 300, height: 300 });
    expect(outputSize({ width: 1600, height: 900 }, 800, false)).toEqual({ width: 800, height: 450 });
    expect(outputSize({ width: 900, height: 1600 }, 800, false)).toEqual({ width: 450, height: 800 });
    expect(outputSize({ width: 0.2, height: 0.2 }, 800, true)).toEqual({ width: 1, height: 1 });
    // A square crop rounded to an odd size stays square.
    const square = outputSize({ width: 999.5, height: 999.4999 }, 4096, true);
    expect(square.width).toBe(square.height);
  });

  it('names the file after the original with the encoded type', () => {
    expect(outputName('holiday.photo.JPG', 'image/png')).toBe('holiday.photo.png');
    expect(outputName('me', 'image/webp')).toBe('me.webp');
    expect(outputName('a.heic', 'image/jpeg')).toBe('a.jpg');
    expect(outputName('', 'image/png')).toBe('image.png');
    expect(outputName('x.png', 'image/unknown')).toBe('x.png');
    expect(outputName('..\\evil/.png', 'image/png')).toBe('..evil.png');
  });

  it('crops raster images only', () => {
    expect(isCroppable('image/jpeg')).toBe(true);
    expect(isCroppable('IMAGE/PNG')).toBe(true);
    expect(isCroppable('image/svg+xml')).toBe(false);
    expect(isCroppable('application/pdf')).toBe(false);
    expect(isCroppable('')).toBe(false);
  });
});

describe('image crop input', () => {
  it('maps keys', () => {
    expect(keyAction('ArrowLeft', false)).toEqual({ pan: [-KEY_STEP, 0] });
    expect(keyAction('ArrowRight', true)).toEqual({ pan: [KEY_STEP_LARGE, 0] });
    expect(keyAction('ArrowUp', false)).toEqual({ pan: [0, -KEY_STEP] });
    expect(keyAction('ArrowDown', false)).toEqual({ pan: [0, KEY_STEP] });
    for (const key of ['+', '=', 'PageUp']) {
      expect(keyAction(key, false)).toEqual({ zoom: KEY_ZOOM });
    }
    for (const key of ['-', '_', 'PageDown']) {
      expect(keyAction(key, false)).toEqual({ zoom: 1 / KEY_ZOOM });
    }
    expect(keyAction('Home', false)).toEqual({ reset: true });
    expect(keyAction('Enter', false)).toEqual({ apply: true });
    expect(keyAction('a', false)).toBeNull();
  });

  it('turns wheel deltas into a zoom factor', () => {
    expect(wheelFactor(-100)).toBeGreaterThan(1);
    expect(wheelFactor(100)).toBeLessThan(1);
    expect(wheelFactor(100) * wheelFactor(-100)).toBeCloseTo(1);
    expect(wheelFactor(3, 1)).toBeCloseTo(wheelFactor(48));
    expect(wheelFactor(1, 2)).toBeCloseTo(wheelFactor(200));
    expect(wheelFactor(100000)).toBeCloseTo(wheelFactor(200));
  });

  it('writes the zoom status', () => {
    expect(zoomText('Zoom %percent%%', 150)).toBe('Zoom 150%');
  });
});
