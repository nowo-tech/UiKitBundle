import { test, expect, type Page } from '@playwright/test';
import { ensureOutDir, hideDebugToolbar, shotPath } from './shot-helpers';

/**
 * REQ-DEMO-013 — README and docs screenshots, never with the Symfony debug toolbar.
 *
 * README: the component catalogue on the shared nowo-tech demo platform shell (top bar, sidebar navigation, page
 * header, section cards), the same frame as every other nowo-tech bundle demo — data tables, form patterns and a
 * confirm dialog, light theme, full page at 1280 px.
 *
 * docs/PAGE_CONTRACT.md: the host-theme preview (/page-contract, Acme tokens and stylesheet: the one page whose purpose
 * is a non-default theme) and its default-theme twin. docs/USAGE.md: the kit templates of the Shell lab (app shell,
 * error pages, confirm dialog). Those are captured as a padded union clip of the page chrome and content.
 */
const WIDTH = 1280;
const PAD = 16;
const CHROME = 'aside, nav, header, main, .acme-guest, .nowo-ui-content';

test.beforeAll(() => {
  ensureOutDir();
});

test.use({ viewport: { width: WIDTH, height: 800 }, colorScheme: 'light', reducedMotion: 'reduce' });

test.beforeEach(async ({ page }) => {
  await hideDebugToolbar(page);
});

/** Demo platform shell page: wait for the shell and the fonts, then a full-page capture. */
async function shell(page: Page, path: string, file: string, ready: string): Promise<void> {
  await page.goto(path);
  await expect(page.getByTestId('demo-platform')).toBeVisible();
  await expect(page.locator(ready).first()).toBeVisible();
  await page.evaluate(() => document.fonts.ready);
  await page.screenshot({ path: shotPath(file), fullPage: true });
}

/** Open a Shell lab / host-theme page and grow the viewport so the whole content fits. */
async function open(page: Page, path: string): Promise<void> {
  await page.setViewportSize({ width: WIDTH, height: 900 });
  await page.goto(path);
  await page.evaluate(() => document.fonts.ready);
  const contentBottom = await page.evaluate((sel) => Math.max(...[...document.querySelectorAll(sel)]
    .filter((el) => !el.closest('.sf-toolbar, dialog'))
    .map((el) => {
      // the box of an element can be stretched by min-height: 100vh — measure its last visible child instead
      const kids = [...el.children].filter((c) => (c as HTMLElement).offsetParent !== null);
      const last = kids.length ? kids[kids.length - 1] : el;
      return last.getBoundingClientRect().bottom + window.scrollY;
    })), CHROME);
  await page.setViewportSize({ width: WIDTH, height: Math.max(720, Math.ceil(contentBottom) + 32) });
  await page.evaluate(() => window.scrollTo(0, 0));
}

/** Padded union of the chrome and the main content, clamped to the viewport. */
async function shoot(page: Page, file: string): Promise<void> {
  const box = await page.evaluate((sel) => {
    const els = [...document.querySelectorAll(sel)]
      .filter((el) => !el.closest('.sf-toolbar'))
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
    path: shotPath(file),
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
  { file: 'page-contract-auth-errors', path: '/page-contract/default/sign-in?failed=1', ready: '[data-nowo-ui-region="form-errors"]' },
  // 2.6 kit templates
  { file: 'kit-error-404', path: '/kit/errors/404', ready: '[data-nowo-ui-region="error"]' },
  { file: 'kit-error-500', path: '/kit/errors/500', ready: '[data-testid="error-auto-retry"]' },
  { file: 'kit-app-shell', path: '/kit/shell', ready: '[data-nowo-ui-pagination="compact"]' },
];

test.describe('demo platform screenshots — README', () => {
  test('catalogue data tables (filters, bulk bar, sortable headers, row actions, card footer)', async ({ page }) => {
    await shell(page, '/catalogue/admin/data-tables', 'catalogue-data-tables', '.nowo-ui-data-table tbody tr');
  });

  test('catalogue form patterns (error summary, fieldsets, option cards, file drop)', async ({ page }) => {
    await shell(page, '/catalogue/admin/form-patterns', 'catalogue-form-patterns', 'form');
  });

  test('catalogue confirm dialog open', async ({ page }) => {
    await page.goto('/catalogue/dialogs');
    await expect(page.getByTestId('demo-platform')).toBeVisible();
    await page.getByTestId('catalogue-dialog-open').click();
    await expect(page.locator('dialog[open]')).toBeVisible();
    await page.waitForTimeout(300); // dialog open transition
    await page.screenshot({ path: shotPath('catalogue-dialog') });
  });
});

test.describe('page contract screenshots — PAGE_CONTRACT.md gallery', () => {
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

  test('overview — host shell with every scaffold', async ({ page }) => {
    await open(page, '/page-contract');
    await expect(page.locator('[data-nowo-ui-page="dashboard"]')).toBeVisible();
    await shoot(page, 'page-contract-overview');
  });

  test('page-contract-menu — export menu open on the host-theme dashboard (2.3)', async ({ page }) => {
    await open(page, '/page-contract/dashboard');
    await page.getByTestId('export-menu').locator('summary').click();
    await expect(page.getByTestId('export-menu').locator('.nowo-ui-menu__panel')).toBeVisible();
    await shoot(page, 'page-contract-menu');
  });

  test('kit-confirm-dialog — confirm dialog wrapper open in the app shell (2.6)', async ({ page }) => {
    await open(page, '/kit/shell');
    await page.getByTestId('void-trigger').click();
    await expect(page.locator('dialog#void-1042[open]')).toBeVisible();
    await page.waitForTimeout(300);
    await shoot(page, 'kit-confirm-dialog');
  });

  for (const shot of gallery) {
    test(shot.file, async ({ page }) => {
      await open(page, shot.path);
      await expect(page.locator(shot.ready).first()).toBeVisible();
      await shoot(page, shot.file);
    });
  }
});
