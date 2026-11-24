import { test, expect, type Page } from '@playwright/test';
import { ensureOutDir, hideDebugToolbar, shotPath } from './shot-helpers';

/**
 * UiKit 2.32 sidebar layout screenshots (/demo/sidebar-layout, Shell lab): the full-height layout at 1280 px,
 * expanded (light), icons-only rail (dark) and hover-expanded rail (light) — the three images docs/USAGE.md shows.
 * Viewport captures without the debug toolbar, converted to WebP by `make demo-screenshots` (scripts/to-webp.mjs).
 */
test.beforeAll(() => {
  ensureOutDir();
});

test.use({ reducedMotion: 'reduce' });

test.beforeEach(async ({ page }) => {
  await hideDebugToolbar(page);
});

async function shot(page: Page, query: string, file: string, theme: 'light' | 'dark', hover = false): Promise<void> {
  await page.setViewportSize({ width: 1280, height: 800 });
  await page.goto(`/demo/sidebar-layout${query}`);
  await page.evaluate((t) => {
    document.documentElement.setAttribute('data-theme', t);
    document.documentElement.setAttribute('data-bs-theme', t);
  }, theme);
  await expect(page.locator('[data-nowo-ui-shell]')).toHaveAttribute('data-nowo-ui-sidebar-mode', /expanded|rail|hidden/);
  if (hover) {
    await page.locator('.nowo-ui-aside__link').nth(1).hover();
    await expect(page.locator('[data-nowo-ui-shell]')).toHaveClass(/is-aside-peek/);
  }
  await page.waitForTimeout(450);
  await page.screenshot({ path: shotPath(file) });
}

test('full-height layout: expanded, rail and hover-expanded rail at 1280 px', async ({ page }) => {
  await shot(page, '?layout=full-height', 'kit-sidebar-full-height-expanded-light', 'light');
  await shot(page, '?layout=full-height&mode=rail', 'kit-sidebar-full-height-rail-dark', 'dark');
  await shot(page, '?layout=full-height&mode=rail&hover=1', 'kit-sidebar-full-height-hover-light', 'light', true);
});
