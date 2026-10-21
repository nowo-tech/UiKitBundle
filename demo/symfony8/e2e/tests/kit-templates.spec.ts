import { test, expect } from '@playwright/test';

/**
 * UiKit 2.6 kit templates: error pages (@NowoUiKit/error/status.html.twig), the signed-in app shell
 * (@NowoUiKit/shell/*), the confirm dialog wrapper and the compact pagination with its auto-submitting per-page select.
 */
test.describe('kit templates (2.6)', () => {
  test('404 preview: illustration (WebP source + PNG), texts, home link, no inline handlers', async ({ page }) => {
    const response = await page.goto('/kit/errors/404');
    expect(response?.ok()).toBeTruthy();
    await expect(page.locator('h1')).toHaveText('Page not found');
    await expect(page.locator('picture source[type="image/webp"]')).toHaveAttribute('srcset', /error-404\.webp$/);
    await expect(page.locator('picture img')).toHaveAttribute('src', /error-404\.png$/);
    await expect(page.getByTestId('error-home')).toHaveAttribute('href', '/');
    await expect(page.locator('a.nowo-ui-skip-link')).toHaveAttribute('href', '#main-content');
    expect(await page.locator('[onclick], [onload], [onerror]').count()).toBe(0);
  });

  test('500 preview counts down and offers a retry; 429 shows the Retry-After wait', async ({ page }) => {
    await page.goto('/kit/errors/500');
    await expect(page.getByTestId('error-retry')).toBeVisible();
    const remaining = page.locator('[data-nowo-ui-error-retry-remaining]');
    await expect(remaining).not.toHaveText('10:00', { timeout: 3_000 });
    await expect(remaining).toHaveText(/^9:5\d$/);

    await page.goto('/kit/errors/429');
    await expect(page.getByTestId('error-retry-after')).toHaveText('Try again in 30 seconds.');
  });

  test('app shell: burger toggles the aside, the confirm dialog opens without Stimulus, per-page select submits', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto('/kit/shell');
    const burger = page.locator('[data-nowo-ui-burger]');
    await expect(burger).toHaveAttribute('aria-expanded', 'true');
    await burger.click();
    await expect(page.locator('[data-nowo-ui-shell]')).toHaveClass(/is-aside-collapsed/);
    await burger.click();

    await page.getByTestId('void-trigger').click();
    await expect(page.locator('dialog#void-1042')).toHaveAttribute('open', '');
    await page.locator('dialog#void-1042 [data-nowo-confirm-close]').click();
    await expect(page.locator('dialog#void-1042')).not.toHaveAttribute('open', '');

    await expect(page.locator('.nowo-ui-pagination__page')).toHaveText('Page 1 of 3');
    await page.locator('select[data-nowo-ui-autosubmit]').selectOption('10');
    await page.waitForURL(/per_page=10/);
    await expect(page.locator('.nowo-ui-pagination__page')).toHaveText('Page 1 of 2');
    await expect(page.getByTestId('kit-rows').locator('li')).toHaveCount(10);
  });
});
