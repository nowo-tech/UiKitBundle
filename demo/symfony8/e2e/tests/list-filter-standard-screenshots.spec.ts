import { test, expect, type Page } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * UiKit 2.72 filter standard screenshots (/page-contract/orders/filter-standard): the desktop bar with the "More
 * filters" fold open, and the phone sheet. Converted to WebP by `make demo-screenshots` (scripts/to-webp.mjs) —
 * REQ-DEMO-013.
 */
const outDir = process.env.SCREENSHOT_DIR ? resolve(process.env.SCREENSHOT_DIR) : resolve(__dirname, '../../../../docs/images/demo');

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

test.use({ reducedMotion: 'reduce' });

async function clean(page: Page): Promise<void> {
  await page.locator('.sf-toolbar').evaluateAll((els) => els.forEach((el) => el.remove()));
}

test('filter standard: desktop bar with the fold open', async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 800 });
  await page.goto('/page-contract/orders/filter-standard?filters%5Bchannel%5D=web');
  await expect(page.locator('details.nowo-ui-filters-more')).toHaveAttribute('open', '');
  await clean(page);
  await page.locator('form[data-nowo-ui-filters]').screenshot({ path: resolve(outDir, 'kit-list-filters-standard-desktop.png') });
});

test('filter standard: phone sheet', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/page-contract/orders/filter-standard?filters%5Bchannel%5D=web');
  await clean(page);
  await page.locator('.nowo-ui-filter-sheet--list [data-nowo-ui-filter-sheet-open]').click();
  await expect(page.getByRole('dialog', { name: 'Filters' })).toBeVisible();
  await page.waitForTimeout(300);
  await page.screenshot({ path: resolve(outDir, 'kit-list-filters-standard-phone.png') });
});
