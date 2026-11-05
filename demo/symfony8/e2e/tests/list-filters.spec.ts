import { test, expect } from '@playwright/test';

/**
 * UiKit 2.30 list-filter host options (config/packages/nowo_ui_kit.yaml → theme.pages.list.filters) on the
 * page-contract orders list: no `list_filters` partial override, yet the card has the fold toggle (cookie
 * acme_filters_collapsed, peer filters-collapse), a track-list grid (token) and icon + text Filter / Clear.
 */
test.describe('list filters host options (2.30)', () => {
  test('fold toggle folds the card, remembers it in the cookie and unfolds', async ({ page, context }) => {
    await context.clearCookies();
    await page.goto('/page-contract/orders');
    const form = page.locator('form[data-nowo-ui-filters]');
    const toggle = page.getByTestId('filters-collapse-toggle');
    const field = form.locator('input[type="search"]');

    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(toggle).toHaveAttribute('aria-controls', (await form.getAttribute('id')) ?? 'missing');
    await expect(field).toBeVisible();
    // the host track list arrives as a token (no inline style)
    const tracks = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--nowo-ui-filters-grid-columns').trim());
    expect(tracks).toBe('repeat(auto-fill, minmax(min(100%, 13rem), 1fr))');
    expect(await form.getAttribute('style')).toBeNull();

    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(field).toBeHidden();
    await expect(page.getByTestId('filters-collapse-summary')).toBeVisible();
    const cookie = (await context.cookies()).find((c) => c.name === 'acme_filters_collapsed');
    expect(cookie?.value).toBe('page_contract_orders');
    expect(cookie?.sameSite).toBe('Lax');

    // server-rendered folded state after a reload
    await page.reload();
    await expect(page.getByTestId('filters-collapse-toggle')).toHaveAttribute('aria-expanded', 'false');
    await expect(form.locator('input[type="search"]')).toBeHidden();

    await page.getByTestId('filters-collapse-toggle').click();
    await expect(form.locator('input[type="search"]')).toBeVisible();
  });

  test('apply / clear show the icon and the translated label', async ({ page }) => {
    await page.goto('/page-contract/orders');
    const submit = page.locator('button.nowo-ui-filters__submit');
    await expect(submit.locator('.nowo-ui-action__label')).toHaveText('Filter');
    await expect(submit.locator('i, svg')).toHaveCount(1);
  });
});
