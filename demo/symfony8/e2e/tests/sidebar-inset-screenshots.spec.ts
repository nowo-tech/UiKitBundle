import { test, expect, type Page } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * UiKit 2.52 inset sidebar style screenshots (/demo/sidebar-layout?style=inset): both sidebar layouts at 1280 px,
 * expanded and icons-only rail (collapsed), and the phone layout at 390 px, light and dark. Viewport captures,
 * converted to WebP by `make demo-screenshots` (scripts/to-webp.mjs) — REQ-DEMO-013.
 */
const outDir = process.env.SCREENSHOT_DIR ? resolve(process.env.SCREENSHOT_DIR) : resolve(__dirname, '../../../../docs/images/demo');

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

test.use({ reducedMotion: 'reduce' });

async function shot(page: Page, query: string, file: string, theme: 'light' | 'dark', width: number, height: number): Promise<void> {
  await page.setViewportSize({ width, height });
  await page.goto(`/demo/sidebar-layout${query}`);
  await page.evaluate((t) => {
    document.documentElement.setAttribute('data-theme', t);
    document.documentElement.setAttribute('data-bs-theme', t);
  }, theme);
  await expect(page.locator('[data-nowo-ui-shell]')).toHaveAttribute('data-nowo-ui-sidebar-mode', /expanded|rail|hidden/);
  await expect(page.locator('[data-nowo-ui-shell]')).toHaveAttribute('data-nowo-ui-sidebar-style', 'inset');
  await page.locator('.sf-toolbar').evaluateAll((els) => els.forEach((el) => el.remove()));
  await page.waitForTimeout(450);
  await page.screenshot({ path: resolve(outDir, `${file}.png`) });
}

for (const theme of ['light', 'dark'] as const) {
  for (const layout of ['below-header', 'full-height'] as const) {
    test(`inset ${layout} ${theme}: expanded and rail at 1280 px`, async ({ page }) => {
      await shot(page, `?style=inset&layout=${layout}`, `kit-sidebar-inset-${layout}-expanded-${theme}`, theme, 1280, 800);
      await shot(page, `?style=inset&layout=${layout}&mode=rail`, `kit-sidebar-inset-${layout}-rail-${theme}`, theme, 1280, 800);
    });
  }
  test(`inset phone ${theme} at 390 px`, async ({ page }) => {
    await shot(page, '?style=inset&layout=full-height', `kit-sidebar-inset-phone-${theme}`, theme, 390, 844);
  });
}
