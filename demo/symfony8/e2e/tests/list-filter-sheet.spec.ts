import { test, expect } from '@playwright/test';

/**
 * UiKit 2.65 list-filter phone sheet (config/packages/nowo_ui_kit.yaml → theme.pages.list.filters.sheet) on the
 * page-contract orders list: below 48rem the card folds behind "Filters (n)" and opens in the filter_bar sheet, a
 * native modal <dialog> (Chrome Modern Web Guidance: showModal(), no role / aria-modal / focus trap / Escape handler /
 * root scroll lock; overscroll contained on the dialog and its backdrop). Runs in Chromium, Firefox and WebKit.
 */
test.describe('list filters phone sheet (2.65)', () => {
  test.beforeEach(async ({ context }) => {
    await context.clearCookies();
  });

  test('folds the card behind Filters (n) and opens the same form in a native modal dialog', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/page-contract/orders?filters%5Bq%5D=a');
    const root = page.locator('.nowo-ui-filter-sheet--list');
    await expect(root).toHaveAttribute('data-nowo-ui-filter-sheet-ready', '');
    const toggle = root.locator('[data-nowo-ui-filter-sheet-open]');
    const form = page.locator('form[data-nowo-ui-filters]');
    await expect(toggle).toBeVisible();
    await expect(toggle.locator('.nowo-ui-filter-sheet__count')).toContainText('1');
    await expect(form).toBeHidden();

    await toggle.click();
    const sheet = page.getByRole('dialog', { name: 'Filters' });
    await expect(sheet).toBeVisible();
    expect(await sheet.evaluate((d) => d.matches(':modal'))).toBe(true);
    await expect(sheet.locator('form[data-nowo-ui-filters]')).toBeVisible();
    await expect(sheet.locator('#filters_q')).toHaveValue('a');
    await expect(sheet.locator('#filters_q')).toBeFocused();
    // the card's fold toggle has no meaning inside the sheet
    await expect(sheet.getByTestId('filters-collapse-toggle')).toBeHidden();
    // no scroll lock on the page: the dialog contains its own overscroll
    expect(await sheet.evaluate((d) => getComputedStyle(d).overscrollBehaviorY)).toBe('contain');
    expect(await page.evaluate(() => [getComputedStyle(document.documentElement).overflowY, getComputedStyle(document.body).overflowY])).not.toContain('hidden');
    for (const attr of ['role', 'aria-modal']) {
      expect(await sheet.getAttribute(attr)).toBeNull();
    }

    // Escape is the native close request (no handler of ours). Pressed in a filled search field, Chromium and Firefox
    // first clear the field, so it is pressed from the sheet's Close button.
    await sheet.locator('[data-nowo-ui-filter-sheet-close]').focus();
    await page.keyboard.press('Escape');
    await expect(sheet).toBeHidden();
    await expect(toggle).toBeFocused();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(root.locator(':scope > [data-nowo-ui-filter-sheet-panel] > form[data-nowo-ui-filters]')).toHaveCount(1);

    // the GET submit is unchanged from inside the sheet
    await toggle.click();
    await sheet.locator('#filters_q').fill('Ada');
    await sheet.locator('button.nowo-ui-filters__submit').click();
    await expect(page).toHaveURL(/filters%5Bq%5D=Ada/);

    // wide screens: the card as before
    await page.setViewportSize({ width: 1280, height: 800 });
    await expect(page.locator('.nowo-ui-filter-sheet--list [data-nowo-ui-filter-sheet-open]')).toBeHidden();
    await expect(page.locator('form[data-nowo-ui-filters] #filters_q')).toBeVisible();
  });

  test('a folded card shows its fields inside the sheet', async ({ page, context, baseURL }) => {
    await context.addCookies([{ name: 'acme_filters_collapsed', value: 'page_contract_orders', url: baseURL ?? 'http://localhost' }]);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/page-contract/orders');
    await page.locator('.nowo-ui-filter-sheet--list [data-nowo-ui-filter-sheet-open]').click();
    const sheet = page.getByRole('dialog', { name: 'Filters' });
    await expect(sheet.locator('#filters_q')).toBeVisible();
    await expect(sheet.getByTestId('filters-collapse-summary')).toBeHidden();
    await page.keyboard.press('Escape');
    // back on a wide screen the card is still folded
    await page.setViewportSize({ width: 1280, height: 800 });
    await expect(page.getByTestId('filters-collapse-toggle')).toHaveAttribute('aria-expanded', 'false');
    await expect(page.locator('form[data-nowo-ui-filters] #filters_q')).toBeHidden();
  });
});
