import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * UiKit 2.23 opt-in features on /kit/2.23/*: per-item attributes, pagination modes (pager | infinite | responsive) at
 * phone and desktop widths, and provider-dependent fields (visible-when peer: switch, save, hidden values kept).
 */

const PHONE = { width: 390, height: 844 };
const DESKTOP = { width: 1280, height: 900 };

const rows = (page: Page) => page.locator('tbody[data-nowo-ui-list-rows] > tr');
/** The list-pagination peer reflects the mode in force once connected. */
const peerReady = async (page: Page): Promise<void> => {
  await expect(page.locator('[data-controller~="list-pagination"]')).toHaveAttribute('data-nowo-ui-pagination-active', /pager|infinite/);
};

test.describe('2.23 per-item attributes', () => {
  test('stat tiles, value cells, the status badge and facts items carry the host attributes', async ({ page }) => {
    await page.goto('/kit/2.23/item-attributes');
    await expect(page.getByTestId('monitor-status')).toHaveAttribute('data-detail-status', 'up');
    await expect(page.locator('[data-stat="uptime-24h"] dd[data-uptime-24h="99.98"]')).toHaveText('99.98 %');
    await expect(page.locator('dd[data-response-ms="182"]')).toBeVisible();
    await expect(page.locator('[data-fact="interval"] > dd')).toHaveText('60 s');
    await expect(page.locator('dd[data-detail-status="up"] .nowo-ui-badge, dd[data-detail-status="up"] span')).toHaveText('Up');
    const axe = await new AxeBuilder({ page }).include('[data-testid="item-attributes"]').analyze();
    expect(axe.violations).toEqual([]);
  });
});

test.describe('2.23 pagination modes', () => {
  test('pager: links only, no peer contract, no rows marker', async ({ page }) => {
    await page.goto('/kit/2.23/pagination/pager');
    await expect(page.locator('[data-controller~="list-pagination"]')).toHaveCount(0);
    await expect(page.locator('[data-nowo-ui-list-rows]')).toHaveCount(0);
    await expect(page.locator('.nowo-ui-pagination__nav')).toBeVisible();
  });

  for (const viewport of [PHONE, DESKTOP]) {
    test(`infinite at ${viewport.width}px: loads on scroll, then "Load more", keeps the filters, URL and announcements`, async ({ page }) => {
      await page.setViewportSize(viewport);
      await page.goto('/kit/2.23/pagination/infinite?status=up');
      await peerReady(page);
      const root = page.locator('[data-controller~="list-pagination"]');
      await expect(root.locator('.nowo-ui-pagination__nav')).toBeHidden();
      const more = root.locator('[data-nowo-ui-pagination-more]');
      await expect(more).toBeVisible();
      // automatic loads on scroll (status=up keeps 14 rows: pages of 5 → 5 + 5 + 4)
      await expect
        .poll(
          async () => {
            await page.mouse.wheel(0, 4000);
            return rows(page).count();
          },
          { timeout: 20_000 },
        )
        .toBe(14);
      await expect(page.locator('[data-nowo-ui-pagination-status]')).toHaveText('4 more rows loaded');
      expect(new URL(page.url()).searchParams.get('page')).toBe('3');
      expect(new URL(page.url()).searchParams.get('status')).toBe('up');
      // every row matches the filter: the next pages kept ?status=up
      for (const text of await rows(page).locator('td[data-label="Status"]').allTextContents()) {
        expect(text.trim()).toBe('up');
      }
      await expect(root.locator('[data-nowo-ui-pagination-more-region]')).toBeHidden();

      // a delete on an appended row opens its own confirm dialog (merged from the next page)
      await rows(page).nth(12).locator('[data-nowo-confirm-open]').click();
      await expect(page.locator('dialog[open]')).toHaveCount(1);
      await page.keyboard.press('Escape');

      if (viewport.width < 640) {
        // phone: appended rows are labelled cards too
        const cell = rows(page).nth(12).locator('td[data-label="Name"]');
        expect(await cell.evaluate((el) => getComputedStyle(el, '::before').content)).toBe('"Name"');
        expect(await rows(page).nth(12).evaluate((el) => getComputedStyle(el).display)).toBe('block');
      }
      const axe = await new AxeBuilder({ page }).include('[data-nowo-ui-region="pagination"]').include('[data-nowo-ui-region="table"]').analyze();
      expect(axe.violations).toEqual([]);
    });
  }

  test('infinite: after the automatic loads only the keyboard-reachable button loads more, focus stays stable', async ({ page }) => {
    await page.setViewportSize(DESKTOP);
    await page.goto('/kit/2.23/pagination/infinite?per_page=5');
    await peerReady(page);
    await expect
      .poll(
        async () => {
          await page.mouse.wheel(0, 4000);
          return rows(page).count();
        },
        { timeout: 20_000 },
      )
      .toBe(20);
    // auto loads exhausted (3): scrolling does nothing more
    await page.mouse.wheel(0, 4000);
    await page.waitForTimeout(400);
    await expect(rows(page)).toHaveCount(20);
    const more = page.locator('[data-nowo-ui-pagination-more]');
    await more.focus();
    await page.keyboard.press('Enter');
    await expect(rows(page)).toHaveCount(23, { timeout: 15_000 });
    // last page: focus moved to the first appended row
    await expect(rows(page).nth(20)).toBeFocused();
  });

  test('responsive: pager on desktop, infinite on a phone, switching live keeps the appended rows', async ({ page }) => {
    await page.setViewportSize(DESKTOP);
    await page.goto('/kit/2.23/pagination/responsive');
    await peerReady(page);
    const root = page.locator('[data-controller~="list-pagination"]');
    await expect(root).toHaveAttribute('data-nowo-ui-pagination-query', '(max-width: 900px)');
    await expect(root.locator('.nowo-ui-pagination__nav')).toBeVisible();
    await expect(root.locator('[data-nowo-ui-pagination-more-region]')).toBeHidden();

    await expect(root).toHaveAttribute('data-nowo-ui-pagination-active', 'pager');
    await page.setViewportSize(PHONE);
    await expect(root).toHaveAttribute('data-nowo-ui-pagination-active', 'infinite');
    await expect(root.locator('.nowo-ui-pagination__nav')).toBeHidden();
    await expect
      .poll(
        async () => {
          await page.mouse.wheel(0, 4000);
          return rows(page).count();
        },
        { timeout: 15_000 },
      )
      .toBeGreaterThanOrEqual(10);
    await page.setViewportSize(DESKTOP);
    await expect(root).toHaveAttribute('data-nowo-ui-pagination-active', 'pager');
    await expect(root).not.toHaveAttribute('data-nowo-ui-pagination-loading', '');
    await page.waitForTimeout(500);
    const loaded = await rows(page).count();
    await expect(root.locator('.nowo-ui-pagination__nav')).toBeVisible();
    await expect(root.locator('[data-nowo-ui-pagination-more-region]')).toBeHidden();
    await page.mouse.wheel(0, 4000);
    await page.waitForTimeout(400);
    await expect(rows(page)).toHaveCount(loaded);
  });

  test('no JavaScript: infinite falls back to the pager links', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto('/kit/2.23/pagination/infinite');
    await expect(page.locator('.nowo-ui-pagination__nav')).toBeVisible();
    await expect(page.locator('[data-nowo-ui-pagination-more-region]')).toBeHidden();
    await page.locator('.nowo-ui-pagination__nav a', { hasText: '2' }).first().click();
    await expect(page).toHaveURL(/page=2/);
    await expect(rows(page)).toHaveCount(5);
    await context.close();
  });
});

test.describe('2.23 provider-dependent fields', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/kit/2.23/provider-fields?reset=1');
  });

  test('switching the provider hides the other rows without disabling them; saving keeps the hidden values', async ({ page }) => {
    await page.goto('/kit/2.23/provider-fields');
    const form = page.getByTestId('provider-form');
    const hostRow = form.locator('[data-nowo-ui-visible-when-field]').filter({ has: page.locator('[name="settings[smtp_host]"]') });
    const keyRow = form.locator('[data-nowo-ui-visible-when-field]').filter({ has: page.locator('[name="settings[api_key]"]') });
    await expect(hostRow).toBeVisible();
    await expect(keyRow).toBeHidden();
    await expect(keyRow).toHaveAttribute('aria-hidden', 'true');
    await expect(keyRow).toHaveAttribute('inert', '');

    await page.locator('[name="settings[provider]"]').selectOption('ses');
    await expect(hostRow).toBeHidden();
    await expect(keyRow).toBeVisible();
    await expect(page.locator('[name="settings[smtp_host]"]')).toBeEnabled();
    await page.locator('[name="settings[api_key]"]').fill('demo-key-123');
    const axe = await new AxeBuilder({ page }).include('[data-testid="provider-form"]').analyze();
    expect(axe.violations).toEqual([]);
    await page.getByRole('button', { name: 'Save' }).click();

    await expect(page.getByTestId('provider-saved')).toBeVisible();
    const stored = page.getByTestId('stored');
    await expect(stored.locator('[data-key="provider"]')).toHaveText('ses');
    // the hidden SMTP values were submitted and survive
    await expect(stored.locator('[data-key="smtp_host"]')).toHaveText('mail.example.com');
    await expect(stored.locator('[data-key="smtp_port"]')).toHaveText('587');
    await expect(stored.locator('[data-key="api_key"]')).toHaveText('set (12 chars)');

    // back to SMTP, save with the key blank: the stored key is kept (blank = keep)
    await page.locator('[name="settings[provider]"]').selectOption('smtp');
    await expect(hostRow).toBeVisible();
    await expect(page.locator('[name="settings[smtp_host]"]')).toHaveValue('mail.example.com');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(stored.locator('[data-key="provider"]')).toHaveText('smtp');
    await expect(stored.locator('[data-key="api_key"]')).toHaveText('set (12 chars)');
  });

  test('without JavaScript every field is visible', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto('/kit/2.23/provider-fields');
    await expect(page.locator('[name="settings[smtp_host]"]')).toBeVisible();
    await expect(page.locator('[name="settings[api_key]"]')).toBeVisible();
    await expect(page.locator('[name="settings[region]"]')).toBeVisible();
    await context.close();
  });
});
