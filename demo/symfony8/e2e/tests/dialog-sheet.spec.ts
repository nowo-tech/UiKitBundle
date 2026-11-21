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
