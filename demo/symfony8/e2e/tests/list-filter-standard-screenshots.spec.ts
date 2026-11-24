import { test, expect } from '@playwright/test';
import { ensureOutDir, hideDebugToolbar, shotPath } from './shot-helpers';

/**
 * UiKit 2.72 filter standard screenshots (/page-contract/orders/filter-standard): the desktop bar with the "More
 * filters" fold open, and the phone sheet. Converted to WebP by `make demo-screenshots` (scripts/to-webp.mjs) —
 * REQ-DEMO-013.
 */
test.beforeAll(() => {
  ensureOutDir();
});

test.use({ reducedMotion: 'reduce' });

test.beforeEach(async ({ page }) => {
  await hideDebugToolbar(page);
});

test('filter standard: desktop bar with the fold open', async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 800 });
  await page.goto('/page-contract/orders/filter-standard?filters%5Bchannel%5D=web');
  await expect(page.locator('details.nowo-ui-filters-more')).toHaveAttribute('open', '');
  await page.locator('form[data-nowo-ui-filters]').screenshot({ path: shotPath('kit-list-filters-standard-desktop') });
});

test('filter standard: phone sheet', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/page-contract/orders/filter-standard?filters%5Bchannel%5D=web');
  await page.locator('.nowo-ui-filter-sheet--list [data-nowo-ui-filter-sheet-open]').click();
  await expect(page.getByRole('dialog', { name: 'Filters' })).toBeVisible();
  await page.waitForTimeout(300);
  await page.screenshot({ path: shotPath('kit-list-filters-standard-phone') });
});
