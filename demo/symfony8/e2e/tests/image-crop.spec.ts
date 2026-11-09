import { test, expect, type Locator, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { resolve } from 'node:path';

/**
 * UiKit 2.43 image crop on /kit/image-crop: a picked fixture is cropped in the dialog and the input then holds the crop. Desktop
 * Chromium and WebKit (mouse, wheel, keyboard), and phones (Pixel 7 and iPhone 14 emulation: touch, tap) in both engines.
 * make test-e2e-image-crop.
 */
test.describe.configure({ timeout: 60_000 });

const PAGE = '/kit/image-crop';
const PHOTO = resolve(__dirname, '../fixtures/photo.png'); // 640 × 480: red, green / blue, yellow quadrants
const ROTATED = resolve(__dirname, '../fixtures/rotated-exif.jpg'); // stored 800 × 600, red left / blue right, EXIF orientation 6
const LOGO = resolve(__dirname, '../fixtures/logo-alpha.png'); // 400 × 200, transparent with a blue box in the middle

type Violation = { directive: string };

async function open(page: Page): Promise<() => Promise<Violation[]>> {
  await page.route('**/_wdt/**', (route) => route.abort());
  await page.addInitScript(() => {
    const seen: { directive: string }[] = [];
    (window as unknown as { cspSeen: typeof seen }).cspSeen = seen;
    document.addEventListener('securitypolicyviolation', (e) => seen.push({ directive: e.effectiveDirective }));
  });
  await page.goto(PAGE);
  await page.waitForFunction(() => (window as unknown as Record<symbol, unknown>)[Symbol.for('nowo-ui.image-crop.bound')] === true);
  // Style and handler attributes: what this runtime must never need (script-src-elem reports come from the debug toolbar).
  return () => page.evaluate(() => (window as unknown as { cspSeen: { directive: string }[] }).cspSeen.filter((v) => v.directive.endsWith('-attr')));
}

const dialog = (page: Page): Locator => page.getByRole('dialog', { name: 'Crop the image' });
const stage = (page: Page): Locator => page.locator('[data-nowo-ui-image-crop-stage]');
const zoom = (page: Page): Locator => page.getByRole('slider', { name: 'Zoom' });
const input = (page: Page, name: string): Locator => page.locator(`input[type="file"][name="form[${name}]"]`);

type Picked = { count: number; name?: string; type?: string; bytes?: number; width?: number; height?: number; pixels?: number[][] };

/** What the input holds now: its file, decoded upright, and the RGBA of some points (fractions of the width and height). */
async function picked(page: Page, name: string, points: [number, number][] = []): Promise<Picked> {
  return input(page, name).evaluate(async (node, samples) => {
    const files = (node as HTMLInputElement).files;
    const file = files?.[0];
    if (!file) {
      return { count: files?.length ?? 0 };
    }
    const bitmap = await createImageBitmap(file);
    const canvas = document.createElement('canvas');
    canvas.width = bitmap.width;
    canvas.height = bitmap.height;
    const ctx = canvas.getContext('2d') as CanvasRenderingContext2D;
    ctx.drawImage(bitmap, 0, 0);
    const pixels = samples.map(([fx, fy]) => [...ctx.getImageData(Math.min(bitmap.width - 1, Math.floor(fx * bitmap.width)), Math.min(bitmap.height - 1, Math.floor(fy * bitmap.height)), 1, 1).data]);
    return { count: files.length, name: file.name, type: file.type, bytes: file.size, width: bitmap.width, height: bitmap.height, pixels };
  }, points);
}

/** A colour close to the expected one (JPEG and WebP are lossy). */
function near(actual: number[] | undefined, expected: [number, number, number], tolerance = 40): void {
  expect(actual).toBeDefined();
  const [r, g, b] = actual as number[];
  expect(Math.abs(r - expected[0]) + Math.abs(g - expected[1]) + Math.abs(b - expected[2]), `${actual} ≈ ${expected}`).toBeLessThan(tolerance * 3);
}

/** Picks a 2000 × 1500 "photo" drawn in the page into the `large` input: gradients and grain, which compress like a real one. */
async function pickLargePhoto(page: Page): Promise<void> {
  await input(page, 'large').evaluate(async (node) => {
    const canvas = document.createElement('canvas');
    canvas.width = 2000;
    canvas.height = 1500;
    const ctx = canvas.getContext('2d') as CanvasRenderingContext2D;
    const image = ctx.createImageData(2000, 1500);
    let seed = 7;
    const random = (): number => ((seed = (seed * 16807) % 2147483647) / 2147483647);
    for (let y = 0; y < 1500; y += 1) {
      for (let x = 0; x < 2000; x += 1) {
        const i = (y * 2000 + x) * 4;
        const grain = (random() - 0.5) * 70;
        image.data[i] = (x / 2000) * 255 + grain;
        image.data[i + 1] = (y / 1500) * 255 + grain;
        image.data[i + 2] = 128 + 100 * Math.sin(x / 40) * Math.cos(y / 55) + grain;
        image.data[i + 3] = 255;
      }
    }
    ctx.putImageData(image, 0, 0);
    const blob = await new Promise<Blob>((done) => canvas.toBlob((b) => done(b as Blob), 'image/png'));
    const transfer = new DataTransfer();
    transfer.items.add(new File([blob], 'large.png', { type: 'image/png' }));
    (node as HTMLInputElement).files = transfer.files;
    node.dispatchEvent(new Event('input', { bubbles: true }));
    node.dispatchEvent(new Event('change', { bubbles: true }));
  });
}

const RED: [number, number, number] = [220, 30, 30];
const GREEN: [number, number, number] = [30, 180, 60];
const BLUE: [number, number, number] = [30, 60, 220];

async function apply(page: Page, tap = false): Promise<void> {
  const button = dialog(page).getByRole('button', { name: 'Apply' });
  if (tap) {
    await button.tap();
  } else {
    await button.click();
  }
  await expect(dialog(page)).toBeHidden();
}

test.describe('image crop, desktop', () => {
  test.skip(({ isMobile }) => isMobile, 'desktop input');

  test('the avatar: a circle, 256 px, format auto (WebP, or JPEG where the engine cannot encode WebP), the preview and the server', async ({ page, browserName }) => {
    const csp = await open(page);
    await input(page, 'avatar').setInputFiles(PHOTO);
    await expect(dialog(page)).toBeVisible();
    await expect(stage(page)).toBeFocused();
    await expect(stage(page)).toHaveAttribute('aria-describedby', 'nowo-ui-image-crop-help');
    await expect(page.locator('#nowo-ui-image-crop-help')).toContainText('arrow keys');
    await expect(stage(page)).toHaveClass(/is-circle/);
    await apply(page);
    await expect(input(page, 'avatar')).toBeFocused();
    const file = await picked(page, 'avatar', [[0.5, 0.5]]);
    expect(file).toMatchObject({ count: 1, width: 256, height: 256 });
    expect(file.bytes).toBeLessThanOrEqual(200_000);
    const webp = file.type === 'image/webp';
    // An opaque crop: WebP, else JPEG (never PNG).
    expect(webp || (browserName === 'webkit' && file.type === 'image/jpeg')).toBe(true);
    expect(file.name).toBe(webp ? 'photo.webp' : 'photo.jpg');
    await expect(page.getByTestId('avatar-preview')).toBeVisible();
    await expect(page.getByTestId('avatar-preview')).toHaveAttribute('src', /^blob:/);
    await page.getByRole('button', { name: 'Upload' }).click();
    await expect(page.getByTestId('received-avatar')).toHaveText(webp ? 'photo.webp image/webp 256x256' : 'photo.jpg image/jpeg 256x256');
    expect(await csp()).toEqual([]);
  });

  test('keyboard: + zooms, arrows move, Enter applies a 16:9 crop that is never enlarged', async ({ page }) => {
    await open(page);
    await input(page, 'logo').setInputFiles(PHOTO);
    await expect(stage(page)).toBeFocused();
    await expect(zoom(page)).toHaveValue('100');
    // Eight zoom steps (1.1^8 = 214 %), then the image all the way left: the crop is inside the right half (green on top).
    for (let i = 0; i < 8; i += 1) {
      await page.keyboard.press('+');
    }
    await expect(zoom(page)).toHaveValue('214');
    await expect(page.locator('[data-nowo-ui-image-crop-status]')).toHaveText('Zoom 214%');
    for (let i = 0; i < 20; i += 1) {
      await page.keyboard.press('Shift+ArrowLeft');
    }
    await page.keyboard.press('Enter');
    await expect(dialog(page)).toBeHidden();
    const file = await picked(page, 'logo', [[0.02, 0.02], [0.98, 0.02]]);
    expect(file).toMatchObject({ count: 1, type: 'image/png', name: 'photo.png' });
    // 640 / 2.14 image pixels wide: smaller than max (800), so not enlarged.
    expect(file.width).toBe(299);
    expect(file.height).toBe(168);
    near(file.pixels?.[0], GREEN);
    near(file.pixels?.[1], GREEN);
  });

  test('without zoom the 16:9 crop is the whole width, at its own size', async ({ page }) => {
    await open(page);
    await input(page, 'logo').setInputFiles(PHOTO);
    await apply(page);
    expect(await picked(page, 'logo')).toMatchObject({ width: 640, height: 360 });
  });

  test('mouse: the slider zooms, a drag moves, the wheel zooms', async ({ page }) => {
    await open(page);
    await input(page, 'logo').setInputFiles(PHOTO);
    await zoom(page).fill('200');
    await expect(zoom(page)).toHaveValue('200');
    const box = (await stage(page).boundingBox())!;
    await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
    await page.mouse.down();
    await page.mouse.move(box.x + 10, box.y + box.height / 2, { steps: 8 });
    await page.mouse.move(box.x + 2, box.y + box.height / 2, { steps: 2 });
    await page.mouse.up();
    await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
    await page.mouse.wheel(0, -200);
    await expect.poll(async () => Number(await zoom(page).inputValue())).toBeGreaterThan(200);
    await apply(page);
    // Moved left: the crop's left edge is now past the image's middle (green, not red).
    const file = await picked(page, 'logo', [[0.02, 0.02]]);
    expect(file.width).toBeLessThanOrEqual(800);
    near(file.pixels?.[0], GREEN);
  });

  test('Escape and Cancel clear the input; the close button too', async ({ page }) => {
    await open(page);
    await input(page, 'logo').setInputFiles(PHOTO);
    await expect(dialog(page)).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(dialog(page)).toBeHidden();
    expect(await picked(page, 'logo')).toEqual({ count: 0 });
    await expect(input(page, 'logo')).toBeFocused();

    await input(page, 'logo').setInputFiles(PHOTO);
    await dialog(page).getByRole('button', { name: 'Cancel' }).click();
    await expect(dialog(page)).toBeHidden();
    expect(await picked(page, 'logo')).toEqual({ count: 0 });

    await input(page, 'logo').setInputFiles(PHOTO);
    await dialog(page).getByRole('button', { name: 'Close' }).click();
    await expect(dialog(page)).toBeHidden();
    expect(await picked(page, 'logo')).toEqual({ count: 0 });
  });

  test('EXIF orientation is applied: a free crop of the rotated photo is 600 × 800, red on top', async ({ page }) => {
    await open(page);
    await input(page, 'photo').setInputFiles(ROTATED);
    await apply(page);
    const file = await picked(page, 'photo', [[0.5, 0.1], [0.5, 0.9]]);
    expect(file).toMatchObject({ count: 1, type: 'image/jpeg', name: 'rotated-exif.jpg', width: 600, height: 800 });
    near(file.pixels?.[0], RED);
    near(file.pixels?.[1], BLUE);
    await page.getByRole('button', { name: 'Upload' }).click();
    await expect(page.getByTestId('received-photo')).toHaveText('rotated-exif.jpg image/jpeg 600x800');
  });

  test('a baked circle is transparent outside it: WebP, or PNG without a WebP encoder', async ({ page, browserName }) => {
    await open(page);
    await input(page, 'badge').setInputFiles(PHOTO);
    await apply(page);
    const file = await picked(page, 'badge', [[0.01, 0.01], [0.5, 0.5]]);
    expect(file).toMatchObject({ width: 128, height: 128 });
    expect(browserName === 'chromium' ? ['image/webp'] : ['image/webp', 'image/png']).toContain(file.type);
    expect(file.pixels?.[0]?.[3]).toBe(0);
    expect(file.pixels?.[1]?.[3]).toBe(255);
  });

  test('max-bytes: a large photo steps down to fit 300 000 bytes, and the server gets it', async ({ page, browserName }) => {
    await open(page);
    await page.evaluate(() => {
      document.addEventListener('nowo-ui:image-crop:cropped', (event) => {
        (window as unknown as { cropDetail: unknown }).cropDetail = { ...(event as CustomEvent).detail, file: undefined, original: undefined };
      });
    });
    await pickLargePhoto(page);
    await expect(dialog(page)).toBeVisible();
    await apply(page);
    const file = await picked(page, 'large');
    const detail = await page.evaluate(() => (window as unknown as { cropDetail: { width: number; height: number; quality: number; attempts: number } }).cropDetail);
    console.log(`[${browserName}] large photo: ${file.type} ${file.width}x${file.height} ${file.bytes} bytes, quality ${detail.quality}, ${detail.attempts} attempt(s)`);
    expect(file.type).toBe(browserName === 'chromium' ? 'image/webp' : file.type === 'image/webp' ? 'image/webp' : 'image/jpeg');
    expect(file.bytes).toBeLessThanOrEqual(300_000);
    expect(detail.attempts).toBeGreaterThan(1);
    expect([file.width, file.height]).toEqual([detail.width, detail.height]);
    // Free aspect keeps the photo's 4:3 within 1600 px, made smaller only as far as the size steps went.
    expect(Math.abs((file.width as number) / (file.height as number) - 4 / 3)).toBeLessThan(0.01);
    expect(file.width).toBeLessThanOrEqual(1600);
    await page.getByRole('button', { name: 'Upload' }).click();
    const ext = file.type === 'image/webp' ? 'webp' : 'jpg';
    await expect(page.getByTestId('received-large')).toHaveText(`large.${ext} ${file.type} ${file.width}x${file.height}`);
    expect(Number(await page.getByTestId('received-large').getAttribute('data-bytes'))).toBeLessThanOrEqual(300_000);
  });

  test('an engine without a WebP encoder (Safari): JPEG for a photo, PNG for a transparent logo', async ({ page }) => {
    // Canvas toBlob answers PNG for WebP, as an engine with no WebP encoder does.
    await page.addInitScript(() => {
      const toBlob = HTMLCanvasElement.prototype.toBlob;
      HTMLCanvasElement.prototype.toBlob = function (callback: BlobCallback, type?: string, quality?: number): void {
        toBlob.call(this, callback, type === 'image/webp' ? 'image/png' : type, quality);
      };
    });
    await open(page);
    await input(page, 'avatar').setInputFiles(PHOTO);
    await apply(page);
    expect(await picked(page, 'avatar')).toMatchObject({ name: 'photo.jpg', type: 'image/jpeg', width: 256, height: 256 });
    await input(page, 'brand').setInputFiles(LOGO);
    await apply(page);
    const logo = await picked(page, 'brand', [[0.02, 0.02]]);
    expect(logo).toMatchObject({ name: 'logo-alpha.png', type: 'image/png', width: 400, height: 200 });
    expect(logo.pixels?.[0]?.[3]).toBe(0);
    await pickLargePhoto(page);
    await apply(page);
    const large = await picked(page, 'large');
    expect(large).toMatchObject({ name: 'large.jpg', type: 'image/jpeg' });
    expect(large.bytes).toBeLessThanOrEqual(300_000);
    console.log(`large photo without WebP: ${large.type} ${large.width}x${large.height} ${large.bytes} bytes`);
    await page.getByRole('button', { name: 'Upload' }).click();
    await expect(page.getByTestId('received-avatar')).toHaveText('photo.jpg image/jpeg 256x256');
    await expect(page.getByTestId('received-brand')).toHaveText('logo-alpha.png image/png 400x200');
  });

  test('a logo with transparency keeps it: WebP, or PNG without a WebP encoder', async ({ page, browserName }) => {
    await open(page);
    await input(page, 'brand').setInputFiles(LOGO);
    await apply(page);
    const file = await picked(page, 'brand', [[0.02, 0.02], [0.5, 0.5]]);
    expect(file).toMatchObject({ width: 400, height: 200 });
    expect(browserName === 'chromium' ? ['image/webp'] : ['image/webp', 'image/png']).toContain(file.type);
    expect(file.name).toBe(file.type === 'image/webp' ? 'logo-alpha.webp' : 'logo-alpha.png');
    expect(file.pixels?.[0]?.[3]).toBe(0);
    expect(file.pixels?.[1]?.[3]).toBe(255);
  });

  test('an impossible limit: the size message, the dialog stays open, the input keeps the picked file', async ({ page }) => {
    await open(page);
    await input(page, 'tiny').setInputFiles(PHOTO);
    await dialog(page).getByRole('button', { name: 'Apply' }).click();
    const message = dialog(page).getByRole('alert');
    await expect(message).toHaveText('This crop cannot be made smaller than 100 B. Crop a smaller part of the image, or cancel.');
    await expect(dialog(page)).toBeVisible();
    expect(await picked(page, 'tiny')).toMatchObject({ count: 1, name: 'photo.png', width: 640, height: 480 });
    // A new crop hides the message; cancel clears the input.
    await stage(page).focus();
    await page.keyboard.press('+');
    await expect(message).toBeHidden();
    await dialog(page).getByRole('button', { name: 'Cancel' }).click();
    await expect(dialog(page)).toBeHidden();
    expect(await picked(page, 'tiny')).toEqual({ count: 0 });
  });

  test('an input that did not opt in is left alone', async ({ page }) => {
    await open(page);
    await input(page, 'document').setInputFiles(PHOTO);
    await expect(dialog(page)).toBeHidden();
    expect(await picked(page, 'document')).toMatchObject({ count: 1, name: 'photo.png', width: 640, height: 480 });
  });

  for (const mode of ['light', 'dark'] as const) {
    test(`the open dialog passes axe in ${mode} mode`, async ({ page }) => {
      await open(page);
      await page.evaluate((m) => {
        document.documentElement.setAttribute('data-theme', m);
        document.documentElement.setAttribute('data-bs-theme', m);
      }, mode);
      await input(page, 'avatar').setInputFiles(PHOTO);
      await expect(dialog(page)).toBeVisible();
      await page.evaluate(() => Promise.all(document.getAnimations().map((a) => a.finished.catch(() => undefined))));
      const axe = await new AxeBuilder({ page }).include('#nowo-ui-image-crop').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
      expect(axe.violations).toEqual([]);
    });
  }
});

test.describe('image crop, phone', () => {
  test.skip(({ isMobile }) => !isMobile, 'touch input');

  test('the dialog fits the screen, a finger drags, a tap applies', async ({ page, browserName }) => {
    const csp = await open(page);
    await input(page, 'logo').setInputFiles(PHOTO);
    await expect(dialog(page)).toBeVisible();
    const viewport = page.viewportSize()!;
    const box = (await dialog(page).boundingBox())!;
    expect(box.x).toBeGreaterThanOrEqual(0);
    expect(box.x + box.width).toBeLessThanOrEqual(viewport.width);
    expect(box.y + box.height).toBeLessThanOrEqual(viewport.height);
    await zoom(page).fill('200');
    const area = (await stage(page).boundingBox())!;
    const from = { x: area.x + area.width / 2, y: area.y + area.height / 2 };
    const to = { x: area.x + 4, y: from.y };
    if (browserName === 'chromium') {
      // Blink's real touch pipeline (touch-action, pointer events from touches).
      const cdp = await page.context().newCDPSession(page);
      await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ ...from, id: 1 }] });
      for (let i = 1; i <= 8; i += 1) {
        await cdp.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: from.x + ((to.x - from.x) * i) / 8, y: from.y, id: 1 }] });
      }
      // Lifted at speed: no fling may start (it would swallow the tap on Apply below).
      await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
    } else {
      // WebKit has no touch driver here: pointer events of type touch, as WebKit dispatches them.
      await stage(page).evaluate((node, [a, b]) => {
        const send = (type: string, x: number): boolean => node.dispatchEvent(new PointerEvent(type, { pointerId: 7, pointerType: 'touch', isPrimary: true, clientX: x, clientY: a.y, bubbles: true, cancelable: true }));
        send('pointerdown', a.x);
        for (let i = 1; i <= 8; i += 1) {
          send('pointermove', a.x + ((b.x - a.x) * i) / 8);
        }
        send('pointerup', b.x);
      }, [from, to]);
    }
    await apply(page, true);
    const file = await picked(page, 'logo', [[0.02, 0.02]]);
    expect(file).toMatchObject({ count: 1, type: 'image/png' });
    near(file.pixels?.[0], GREEN);
    expect(await csp()).toEqual([]);
  });

  test('two fingers zoom', async ({ page }) => {
    await open(page);
    await input(page, 'logo').setInputFiles(PHOTO);
    await expect(zoom(page)).toHaveValue('100');
    const area = (await stage(page).boundingBox())!;
    const y = area.y + area.height / 2;
    const cx = area.x + area.width / 2;
    await stage(page).evaluate((node, [centre, top]) => {
      const send = (type: string, id: number, x: number): boolean => node.dispatchEvent(new PointerEvent(type, { pointerId: id, pointerType: 'touch', isPrimary: id === 1, clientX: x, clientY: top, bubbles: true, cancelable: true }));
      send('pointerdown', 1, centre - 40);
      send('pointerdown', 2, centre + 40);
      for (let i = 1; i <= 8; i += 1) {
        send('pointermove', 1, centre - 40 - i * 5);
        send('pointermove', 2, centre + 40 + i * 5);
      }
      send('pointerup', 1, centre - 80);
      send('pointerup', 2, centre + 80);
    }, [cx, y]);
    await expect(zoom(page)).toHaveValue('200');
    await apply(page, true);
    const file = await picked(page, 'logo');
    expect(file).toMatchObject({ width: 320, height: 180 });
  });
});
