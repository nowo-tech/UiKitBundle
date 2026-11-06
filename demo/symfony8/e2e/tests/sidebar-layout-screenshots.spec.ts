import { test, expect, type Page } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * UiKit 2.32 sidebar layout screenshots (/demo/sidebar-layout): both layouts at 1280 px (expanded, icons-only rail,
 * hover-expanded rail) and the phone layout at 390 px, light and dark. Viewport captures, converted to WebP by
 * `make demo-screenshots` (scripts/to-webp.mjs).
 */
const outDir = process.env.SCREENSHOT_DIR ? resolve(process.env.SCREENSHOT_DIR) : resolve(__dirname, '../../../../docs/images/demo');

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

test.use({ reducedMotion: 'reduce' });

async function shot(page: Page, query: string, file: string, theme: 'light' | 'dark', width: number, height: number, hover = false): Promise<void> {
  await page.setViewportSize({ width, height });
  await page.goto(`/demo/sidebar-layout${query}`);
  await page.evaluate((t) => {
    document.documentElement.setAttribute('data-theme', t);
    document.documentElement.setAttribute('data-bs-theme', t);
  }, theme);
  await expect(page.locator('[data-nowo-ui-shell]')).toHaveAttribute('data-nowo-ui-sidebar-mode', /expanded|rail|hidden/);
  await page.locator('.sf-toolbar').evaluateAll((els) => els.forEach((el) => el.remove()));
  if (hover) {
    await page.locator('.nowo-ui-aside__link').nth(1).hover();
    await expect(page.locator('[data-nowo-ui-shell]')).toHaveClass(/is-aside-peek/);
  }
  await page.waitForTimeout(450);
  await page.screenshot({ path: resolve(outDir, `${file}.png`) });
}

for (const theme of ['light', 'dark'] as const) {
  for (const layout of ['full-height', 'below-header'] as const) {
    test(`${layout} ${theme}: expanded, rail, hover at 1280 px`, async ({ page }) => {
      await shot(page, `?layout=${layout}`, `kit-sidebar-${layout}-expanded-${theme}`, theme, 1280, 800);
      await shot(page, `?layout=${layout}&mode=rail`, `kit-sidebar-${layout}-rail-${theme}`, theme, 1280, 800);
      await shot(page, `?layout=${layout}&mode=rail&hover=1`, `kit-sidebar-${layout}-hover-${theme}`, theme, 1280, 800, true);
    });
  }
  test(`phone ${theme} at 390 px`, async ({ page }) => {
    await shot(page, '?layout=full-height', `kit-sidebar-phone-${theme}`, theme, 390, 844);
    await page.locator('.nowo-ui-header__start [data-nowo-ui-burger]').click();
    await page.waitForTimeout(450);
    await page.screenshot({ path: resolve(outDir, `kit-sidebar-phone-drawer-${theme}.png`) });
  });
}
