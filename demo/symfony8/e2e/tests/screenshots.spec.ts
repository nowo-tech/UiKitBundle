import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * REQ-DEMO-013 / REQ-UI-003 — host-theme demo screenshots: one per scaffold (the page content inside the Acme
 * host layout), an overview (whole host shell) and the default-theme list for comparison.
 */
const outDir = process.env.SCREENSHOT_DIR ? resolve(process.env.SCREENSHOT_DIR) : resolve(__dirname, '../../../../docs/images/demo');

const shots: Array<{ file: string; path: string; target: string }> = [
  { file: 'page-contract-list', path: '/page-contract/orders', target: 'main' },
  { file: 'page-contract-show', path: '/page-contract/orders/1042', target: 'main' },
  { file: 'page-contract-form', path: '/page-contract/orders/1042/edit', target: 'main' },
  { file: 'page-contract-settings', path: '/page-contract/settings', target: 'main' },
  { file: 'page-contract-dashboard', path: '/page-contract/dashboard', target: 'main' },
  { file: 'page-contract-confirm', path: '/page-contract/orders/1041/delete', target: 'main' },
  { file: 'page-contract-wizard', path: '/page-contract/onboarding', target: 'main' },
  { file: 'page-contract-auth', path: '/page-contract/sign-in', target: '.acme-guest' },
  { file: 'page-contract-default-list', path: '/page-contract/default/orders', target: '.nowo-ui-page' },
];

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

test.use({ viewport: { width: 1280, height: 860 } });

/** Hide the Symfony web debug toolbar (dev demo) from the captures. */
async function open(page: import('@playwright/test').Page, path: string): Promise<void> {
  await page.goto(path);
  await page.addStyleTag({ content: '.sf-toolbar, [id^="sfwdt"], .sf-minitoolbar { display: none !important; }' });
}

test.describe('page contract screenshots', () => {
  test('overview — host shell with every scaffold', async ({ page }) => {
    await open(page, '/page-contract');
    await expect(page.locator('[data-nowo-ui-page="dashboard"]')).toBeVisible();
    await page.screenshot({ path: resolve(outDir, 'page-contract-overview.png'), fullPage: true });
  });

  for (const shot of shots) {
    test(shot.file, async ({ page }) => {
      await open(page, shot.path);
      const target = page.locator(shot.target).first();
      await expect(target).toBeVisible();
      await target.screenshot({ path: resolve(outDir, `${shot.file}.png`) });
    });
  }
});
