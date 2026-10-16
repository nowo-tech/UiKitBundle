import { test, expect } from '@playwright/test';

/**
 * REQ-UI-003 host-theme demo — every scaffold renders inside the Acme host layout with the host theme
 * (tokens, class map, page_header partial override), and the default-theme view renders the same pages with
 * UiKit's defaults.
 */
const scaffolds: Array<{ name: string; path: string }> = [
  { name: 'list', path: '/page-contract/orders' },
  { name: 'show', path: '/page-contract/orders/1042' },
  { name: 'form', path: '/page-contract/orders/1042/edit' },
  { name: 'settings', path: '/page-contract/settings' },
  { name: 'dashboard', path: '/page-contract/dashboard' },
  { name: 'confirm', path: '/page-contract/orders/1041/delete' },
  { name: 'wizard', path: '/page-contract/onboarding' },
  { name: 'auth', path: '/page-contract/sign-in' },
];

test.describe('page contract — host theme', () => {
  for (const { name, path } of scaffolds) {
    test(`${name} scaffold uses the host layout and theme`, async ({ page }) => {
      const response = await page.goto(path);
      expect(response?.ok()).toBeTruthy();
      await expect(page.locator(`[data-nowo-ui-page="${name}"]`)).toBeVisible();
      // host tokens: <html data-nowo-ui-host-tokens> + token stylesheet, and the token reaches UiKit's CSS
      await expect(page.locator('html')).toHaveAttribute('data-nowo-ui-host-tokens', '');
      await expect(page.locator('style[data-nowo-ui-theme-tokens]')).toHaveCount(1);
      const primary = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--nowo-ui-primary').trim());
      expect(primary).toBe('#0f766e');
      if (name === 'auth') {
        await expect(page.locator('.acme-guest')).toBeVisible();
        await expect(page.locator('.acme-sidebar')).toHaveCount(0);
        await expect(page.locator('.acme-auth')).toBeVisible();
      } else {
        await expect(page.locator('.acme-sidebar')).toBeVisible();
        // partial override: every page header comes from the host template
        await expect(page.locator('.acme-header .acme-title')).toBeVisible();
        await expect(page.locator('.nowo-ui-page-header')).toHaveCount(0);
      }
    });
  }

  test('list: class map, attributes, row actions and confirm dialog', async ({ page }) => {
    await page.goto('/page-contract/orders');
    const table = page.locator('table.acme-table');
    await expect(table).toHaveAttribute('data-acme-table', 'zebra');
    await expect(table.locator('tbody tr.acme-row')).toHaveCount(5);
    await expect(page.locator('.acme-badge--ok').first()).toHaveText('Paid');
    await expect(page.locator('.nowo-ui-pagination')).toContainText('of 42');
    await page.locator('tbody tr').first().locator('.nowo-ui-action--delete').click();
    const dialog = page.locator('dialog[open]');
    await expect(dialog).toBeVisible();
    await expect(dialog).toContainText('Order #1042 of Lucía Fernández will be deleted.');
  });

  test('list: filters submit with GET', async ({ page }) => {
    await page.goto('/page-contract/orders');
    await page.locator('#filters_q').fill('emma');
    await page.locator('#filters_q').press('Enter');
    await expect(page).toHaveURL(/filters%5Bq%5D=emma|filters\[q\]=emma/);
  });

  test('settings: tabs mark the current section', async ({ page }) => {
    await page.goto('/page-contract/settings/notifications');
    await expect(page.locator('a.acme-tab--active[aria-current="page"]')).toHaveText('Notifications');
    await expect(page.locator('#notifications_recipients')).toHaveValue('orders@acme.test');
  });

  test('wizard: current step is highlighted', async ({ page }) => {
    await page.goto('/page-contract/onboarding');
    await expect(page.locator('.acme-step--active')).toHaveText('Payments');
    await expect(page.locator('.nowo-ui-wizard-page__progress')).toContainText('3');
  });
});

test.describe('page contract — default theme view', () => {
  test('same list page with UiKit defaults (no host tokens, classes or partial)', async ({ page }) => {
    const response = await page.goto('/page-contract/default/orders');
    expect(response?.ok()).toBeTruthy();
    await expect(page.locator('[data-nowo-ui-page="list"]')).toBeVisible();
    await expect(page.locator('style[data-nowo-ui-theme-tokens]')).toHaveCount(0);
    await expect(page.locator('.nowo-ui-page-header')).toBeVisible();
    await expect(page.locator('.acme-header, .acme-table, .acme-badge')).toHaveCount(0);
    await expect(page.locator('table.nowo-ui-table')).not.toHaveAttribute('data-acme-table', /.*/);
    await expect(page.locator('main[data-nowo-ui-bundle="DemoDefaultTheme"]')).toBeVisible();
    const primary = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--nowo-ui-primary').trim());
    expect(primary).toBe('#2563eb');
  });
});
