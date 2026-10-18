import { test, expect, type Page } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * REQ-DEMO-013 / REQ-UI-003 — host-theme demo screenshots in full demo context: host chrome (sidebar / header)
 * → page content → Symfony WebProfiler toolbar, captured as a padded union clip on a viewport tall enough for the
 * whole page. README: host-theme list, host-theme dashboard, default-theme list with the delete confirm open.
 * docs/PAGE_CONTRACT.md links the remaining scaffolds.
 */
const outDir = process.env.SCREENSHOT_DIR ? resolve(process.env.SCREENSHOT_DIR) : resolve(__dirname, '../../../../docs/images/demo');

const WIDTH = 1280;
const PAD = 16;
const CHROME = 'aside, nav, header, main, .acme-guest, .nowo-ui-content';

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

test.use({ viewport: { width: WIDTH, height: 900 } });

/** Open a page, wait for the profiler toolbar and grow the viewport so the content ends above the toolbar. */
async function open(page: Page, path: string): Promise<void> {
  await page.setViewportSize({ width: WIDTH, height: 900 });
  await page.goto(path);
  const toolbar = page.locator('.sf-toolbar .sf-toolbarreset');
  await expect(toolbar).toBeVisible({ timeout: 15_000 });
  const toolbarHeight = Math.ceil((await toolbar.boundingBox())?.height ?? 36);
  const contentBottom = await page.evaluate((sel) => Math.max(...[...document.querySelectorAll(sel)]
    .filter((el) => !el.closest('.sf-toolbar, dialog'))
    .map((el) => {
      // the box of an element can be stretched by min-height: 100vh — measure its last visible child instead
      const kids = [...el.children].filter((c) => (c as HTMLElement).offsetParent !== null);
      const last = kids.length ? kids[kids.length - 1] : el;
      return last.getBoundingClientRect().bottom + window.scrollY;
    })), CHROME);
  await page.setViewportSize({ width: WIDTH, height: Math.max(720, Math.ceil(contentBottom) + 32 + toolbarHeight) });
  await page.evaluate(() => window.scrollTo(0, 0));
}

/** Padded union of the chrome, the main content and the profiler toolbar, clamped to the viewport. */
async function shoot(page: Page, file: string): Promise<void> {
  const box = await page.evaluate((sel) => {
    const els = [...document.querySelectorAll(`${sel}, .sf-toolbar .sf-toolbarreset`)]
      .map((el) => el.getBoundingClientRect())
      .filter((r) => r.width > 0 && r.height > 0);
    return {
      x: Math.min(...els.map((r) => r.left)),
      y: Math.min(...els.map((r) => r.top)),
      r: Math.max(...els.map((r) => r.right)),
      b: Math.max(...els.map((r) => r.bottom)),
    };
  }, CHROME);
  const vp = page.viewportSize()!;
  const x = Math.max(0, box.x - PAD);
  const y = Math.max(0, box.y - PAD);
  await page.screenshot({
    path: resolve(outDir, `${file}.png`),
    clip: { x, y, width: Math.min(vp.width, box.r + PAD) - x, height: Math.min(vp.height, box.b + PAD) - y },
  });
}

const gallery: Array<{ file: string; path: string; ready: string }> = [
  { file: 'page-contract-show', path: '/page-contract/orders/1042', ready: '[data-nowo-ui-page="show"]' },
  { file: 'page-contract-form', path: '/page-contract/orders/1042/edit', ready: '[data-nowo-ui-page="form"]' },
  { file: 'page-contract-settings', path: '/page-contract/settings', ready: '[data-nowo-ui-page="settings"]' },
  { file: 'page-contract-confirm', path: '/page-contract/orders/1041/delete', ready: '[data-nowo-ui-page="confirm"]' },
  { file: 'page-contract-wizard', path: '/page-contract/onboarding', ready: '[data-nowo-ui-page="wizard"]' },
  { file: 'page-contract-auth', path: '/page-contract/sign-in', ready: '[data-nowo-ui-page="auth"]' },
];

test.describe('page contract screenshots — README', () => {
  test('host-theme orders list (filters, badges, row actions, pagination)', async ({ page }) => {
    await open(page, '/page-contract/orders');
    await expect(page.locator('table.acme-table tbody tr')).toHaveCount(5);
    await shoot(page, 'page-contract-list');
  });

  test('host-theme dashboard (KPIs and panels)', async ({ page }) => {
    await open(page, '/page-contract/dashboard');
    await expect(page.locator('[data-nowo-ui-page="dashboard"]')).toBeVisible();
    await shoot(page, 'page-contract-dashboard');
  });

  test('default-theme orders list with the delete confirm dialog open', async ({ page }) => {
    await open(page, '/page-contract/default/orders');
    await page.locator('tbody tr').first().locator('.nowo-ui-action--delete').click();
    await expect(page.locator('dialog[open]')).toBeVisible();
    await page.waitForTimeout(300); // dialog open transition
    await shoot(page, 'page-contract-default-list');
  });
});

test.describe('page contract screenshots — PAGE_CONTRACT.md gallery', () => {
  test('overview — host shell with every scaffold', async ({ page }) => {
    await open(page, '/page-contract');
    await expect(page.locator('[data-nowo-ui-page="dashboard"]')).toBeVisible();
    await shoot(page, 'page-contract-overview');
  });

  for (const shot of gallery) {
    test(shot.file, async ({ page }) => {
      await open(page, shot.path);
      await expect(page.locator(shot.ready).first()).toBeVisible();
      await shoot(page, shot.file);
    });
  }
});
