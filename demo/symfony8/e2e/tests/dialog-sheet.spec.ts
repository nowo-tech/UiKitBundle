import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

/**
 * UiKit 2.63 (/kit/dialogs, "Import (phone sheet, steps)"): `phone: 'sheet'` fills the screen at 40rem and below
 * (header and footer stay, the body scrolls, focus trap, Escape, focus return), `header_steps` renders the steps in the
 * header, and the `.nowo-ui-section` blocks of a dialog body get a divider. axe on desktop and phone. Writes
 * screenshots when DIALOG_SHOTS_DIR is set.
 */
const shots = process.env.DIALOG_SHOTS_DIR;

async function shot(page: Page, name: string): Promise<void> {
  if (shots) {
    await page.screenshot({ path: `${shots}/${name}.png` });
  }
}

async function openSheet(page: Page): Promise<void> {
  await page.goto('/kit/dialogs');
  await page.getByTestId('open-sheet').click();
  await expect(page.locator('dialog#sheet-dialog')).toBeVisible();
}

for (const [name, width, height, phone] of [['desktop', 1280, 900, false], ['phone', 390, 520, true]] as const) {
  test.describe(`dialog sheet, steps and sections on ${name}`, () => {
    test.use({ viewport: { width, height }, reducedMotion: 'reduce' });

    test('size: the whole screen on phones, the wide dialog above', async ({ page }) => {
      await openSheet(page);
      const box = await page.locator('dialog#sheet-dialog').evaluate((node) => node.getBoundingClientRect().toJSON() as DOMRect);
      if (phone) {
        expect(Math.round(box.x)).toBe(0);
        expect(Math.round(box.y)).toBe(0);
        expect(Math.round(box.width)).toBe(width);
        expect(Math.round(box.height)).toBe(height);
        const radius = await page.locator('dialog#sheet-dialog').evaluate((node) => getComputedStyle(node).borderTopLeftRadius);
        expect(radius).toBe('0px');
      } else {
        expect(Math.round(box.width)).toBe(896);
        expect(box.height).toBeLessThan(height - 31);
      }
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
      await shot(page, `sheet-${name}`);
    });

    test('the steps are in the header and stay while the body scrolls; header and footer stay in view', async ({ page }) => {
      await openSheet(page);
      const dialog = page.locator('dialog#sheet-dialog');
      const steps = dialog.locator('> header .nowo-ui-dialog__steps ol.nowo-ui-steps');
      await expect(steps).toHaveAttribute('aria-label', 'Import steps');
      await expect(steps.locator('[aria-current="step"]')).toContainText('Columns');
      const body = dialog.locator('> .nowo-ui-dialog__body');
      if (phone) {
        // Compact: only the current step shows its label.
        await expect(steps.locator('.nowo-ui-steps__item--upcoming .nowo-ui-steps__label').first()).not.toBeInViewport();
        await expect(steps.locator('[aria-current="step"] .nowo-ui-steps__label')).toBeInViewport();
        expect(await body.evaluate((n) => n.scrollHeight > n.clientHeight)).toBe(true);
      }
      await body.evaluate((n) => { n.scrollTop = n.scrollHeight; });
      await expect(dialog.locator('> header')).toBeInViewport();
      await expect(steps).toBeInViewport();
      await expect(dialog.locator('> footer')).toBeInViewport();
      const footer = await dialog.locator('> footer').boundingBox();
      const frame = await dialog.boundingBox();
      expect(Math.round((footer?.y ?? 0) + (footer?.height ?? 0))).toBe(Math.round((frame?.y ?? 0) + (frame?.height ?? 0)) - (phone ? 0 : 1));
      await shot(page, `sheet-${name}-scrolled`);
    });

    test('sections: a divider between the body sections, none above the first', async ({ page }) => {
      await openSheet(page);
      const borders = await page.locator('dialog#sheet-dialog [data-testid="sheet-section"]').evaluateAll((nodes) => nodes.map((n) => getComputedStyle(n).borderTopWidth));
      expect(borders).toEqual(['0px', '1px', '1px']);
    });

    test('sections inside a form of the dialog body: no doubled gap; a page form outside a dialog keeps its margins', async ({ page }) => {
      await openSheet(page);
      const result = await page.evaluate(() => {
        const make = (): HTMLElement => {
          const section = document.createElement('section');
          section.className = 'nowo-ui-section';
          return section;
        };
        const build = (parent: Element): HTMLElement[] => {
          const form = document.createElement('form');
          form.className = 'nowo-ui-form';
          const sections = [make(), make()];
          sections.forEach((s) => form.append(s));
          parent.append(form);
          return sections;
        };
        const inDialog = build(document.querySelector('dialog#sheet-dialog > .nowo-ui-dialog__body') as Element);
        const outside = build(document.body);
        outside[1].style.marginTop = '7px'; // the host's own margin (CSSOM, no inline attribute in the markup)
        return {
          dialogGap: getComputedStyle(inDialog[1]).marginTop,
          dialogBorder: getComputedStyle(inDialog[1]).borderTopWidth,
          outsideMargin: getComputedStyle(outside[1]).marginTop,
        };
      });
      expect(result).toEqual({ dialogGap: '0px', dialogBorder: '1px', outsideMargin: '7px' });
    });

    test('keyboard: focus stays in the dialog, Escape closes it and gives focus back', async ({ page }) => {
      await openSheet(page);
      // A native modal dialog keeps Tab inside it; at the ends the browser's own chrome may take it (the page behind never does).
      const outside = (): Promise<boolean> => page.evaluate(() => {
        const active = document.activeElement;
        return active !== null && active !== document.body && active.closest('dialog#sheet-dialog') === null;
      });
      for (let i = 0; i < 8; i++) {
        await page.keyboard.press('Tab');
        expect(await outside()).toBe(false);
      }
      await page.keyboard.press('Shift+Tab');
      expect(await outside()).toBe(false);
      await page.locator('dialog#sheet-dialog').getByRole('button', { name: 'Cancel' }).focus();
      await page.keyboard.press('Escape');
      await expect(page.locator('dialog#sheet-dialog')).toBeHidden();
      await expect(page.getByTestId('open-sheet')).toBeFocused();
    });

    test('no inline style or script in the dialog, and axe finds no violation', async ({ page }) => {
      await openSheet(page);
      expect(await page.locator('dialog#sheet-dialog [style], dialog#sheet-dialog script').count()).toBe(0);
      const results = await new AxeBuilder({ page }).include('dialog#sheet-dialog').analyze();
      expect(results.violations).toEqual([]);
    });
  });
}

test('the other dialogs keep their size on phones (the sheet is opt-in)', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 700 });
  await page.goto('/kit/dialogs');
  await page.getByTestId('open-long').click();
  const box = await page.locator('dialog#long-dialog').evaluate((node) => node.getBoundingClientRect().toJSON() as DOMRect);
  expect(Math.round(box.y)).toBeGreaterThan(0);
  expect(Math.round(box.height)).toBeLessThan(700);
});

/**
 * 2.63.1: the upcoming step labels (and hints) in the tinted dialog header measured 4.39:1 (#64748b on #f5f6f6) in
 * light mode, below WCAG AA. Every steps state keeps 4.5:1 in the header (desktop, light and dark) and in a page body.
 */
type Mode = 'light' | 'dark';

const setMode = (page: Page, mode: Mode): Promise<void> =>
  page.evaluate((m) => {
    document.documentElement.setAttribute('data-theme', m);
    document.documentElement.setAttribute('data-bs-theme', m);
  }, mode);

/** Contrast of each element's text colour against the opaque colour behind it (alpha backgrounds composited). */
const ratiosOf = (page: Page, selector: string): Promise<number[]> =>
  page.locator(selector).evaluateAll((nodes) => {
    const probe = document.createElement('canvas').getContext('2d', { willReadFrequently: true }) as CanvasRenderingContext2D;
    const rgba = (css: string): number[] => {
      probe.clearRect(0, 0, 1, 1);
      probe.fillStyle = '#000';
      probe.fillStyle = css;
      probe.fillRect(0, 0, 1, 1);
      const [r, g, b, a] = probe.getImageData(0, 0, 1, 1).data;
      return [r, g, b, a / 255];
    };
    const over = (top: number[], below: number[]): number[] => [0, 1, 2].map((i) => top[i] * top[3] + below[i] * (1 - top[3])).concat(1);
    const background = (el: Element | null): number[] => {
      const layers: number[][] = [];
      for (let node = el; node; node = node.parentElement) {
        const color = rgba(getComputedStyle(node).backgroundColor);
        if (color[3] > 0) {
          layers.push(color);
          if (color[3] >= 1) {
            break;
          }
        }
      }
      return layers.reduceRight((below, top) => over(top, below), [255, 255, 255, 1]);
    };
    const luminance = (c: number[]): number => {
      const [r, g, b] = c.slice(0, 3).map((v) => {
        const s = v / 255;
        return s <= 0.04045 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
      });
      return 0.2126 * r + 0.7152 * g + 0.0722 * b;
    };
    return nodes.map((el) => {
      const bg = background(el);
      const fg = over(rgba(getComputedStyle(el).color), bg);
      const [hi, lo] = [luminance(fg), luminance(bg)].sort((a, b) => b - a);
      return (hi + 0.05) / (lo + 0.05);
    });
  });

for (const mode of ['light', 'dark'] as Mode[]) {
  test.describe(`header steps contrast on desktop, ${mode}`, () => {
    test.use({ viewport: { width: 1280, height: 900 }, reducedMotion: 'reduce' });

    test('upcoming, current and done step labels keep 4.5:1 in the header; axe color-contrast finds nothing', async ({ page }) => {
      await page.goto('/kit/dialogs');
      await setMode(page, mode);
      await page.getByTestId('open-sheet').click();
      const dialog = page.locator('dialog#sheet-dialog');
      await expect(dialog).toBeVisible();
      const steps = dialog.locator('> header .nowo-ui-dialog__steps');
      await expect(steps.locator('.nowo-ui-steps__item--upcoming .nowo-ui-steps__label').first()).toBeVisible();
      await expect(steps.locator('.nowo-ui-steps__item--done')).toHaveCount(1);
      await expect(steps.locator('.nowo-ui-steps__item--current')).toHaveCount(1);
      const ratios = await ratiosOf(page, 'dialog#sheet-dialog > header .nowo-ui-steps__label, dialog#sheet-dialog > header .nowo-ui-steps__hint');
      expect(ratios.length).toBe(4);
      for (const ratio of ratios) {
        expect(ratio).toBeGreaterThanOrEqual(4.5);
      }
      const axe = await new AxeBuilder({ page }).include('dialog#sheet-dialog > header').withRules(['color-contrast']).analyze();
      expect(axe.violations).toEqual([]);
      expect(axe.passes.map((rule) => rule.id)).toContain('color-contrast');
    });
  });

  test(`steps in a page body keep 4.5:1 for every state (${mode})`, async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto('/catalogue/admin/navigation-patterns');
    await setMode(page, mode);
    const steps = page.getByTestId('catalogue-steps');
    await expect(steps.locator('.nowo-ui-steps__item--upcoming')).toHaveCount(1);
    const ratios = await ratiosOf(page, '[data-testid="catalogue-steps"] .nowo-ui-steps__label, [data-testid="catalogue-steps"] .nowo-ui-steps__hint');
    expect(ratios.length).toBe(7);
    for (const ratio of ratios) {
      expect(ratio).toBeGreaterThanOrEqual(4.5);
    }
  });
}
