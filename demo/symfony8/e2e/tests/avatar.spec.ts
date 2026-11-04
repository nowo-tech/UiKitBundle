import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * UiKit 2.26 avatars (/kit/avatars): the picture goes over the initials and hides them once loaded; a broken URL falls
 * back to the initials (js/nowo-ui-avatar.js); URLs the policy rejects never reach <img src>; no inline handler or
 * style; axe finds no image-alt / role-img-alt / aria-allowed-role / color-contrast issue, with and without pictures.
 */
test.describe('avatars (2.26)', () => {
  test('pictures load over the initials, a broken URL falls back, rejected URLs render initials only', async ({ page }) => {
    const requested: string[] = [];
    page.on('request', (request) => requested.push(request.url()));
    await page.goto('/kit/avatars');

    const picture = page.getByTestId('avatar-picture');
    await expect(picture).toHaveClass(/\bhas-image\b/);
    await expect(picture).toHaveClass(/\bis-loaded\b/);
    await expect(picture.locator('img.nowo-ui-avatar__image')).toHaveAttribute('alt', '');
    await expect(picture.locator('img')).toHaveAttribute('referrerpolicy', 'same-origin');
    await expect(picture).toHaveAttribute('aria-hidden', 'true');

    for (const id of ['avatar-broken', 'avatar-broken-labelled']) {
      const broken = page.getByTestId(id);
      await expect(broken.locator('img')).toHaveCount(0);
      await expect(broken).not.toHaveClass(/\bhas-image\b/);
      await expect(broken).toHaveText('GH');
    }
    await expect(page.getByTestId('avatar-broken-labelled')).toHaveAttribute('role', 'img');
    await expect(page.getByTestId('avatar-broken-labelled')).toHaveAttribute('aria-label', 'Grace Hopper');

    for (const [id, text] of [['avatar-http', 'AT'], ['avatar-data', 'KJ'], ['avatar-relative', 'ED'], ['avatar-none', 'BL']]) {
      const avatar = page.getByTestId(id);
      await expect(avatar.locator('img')).toHaveCount(0);
      await expect(avatar).toHaveText(text);
    }
    expect(requested.filter((r) => r.includes('example.com'))).toEqual([]);

    // Sizes: width / height attributes match the size modifier; labelled avatars carry role="img" + aria-label.
    for (const [size, px] of [['xs', '20'], ['sm', '24'], ['default', '32'], ['lg', '44']]) {
      const avatar = page.getByTestId(`avatar-size-${size}`);
      await expect(avatar).toHaveAttribute('role', 'img');
      await expect(avatar).toHaveAttribute('aria-label', 'Ada Lovelace');
      await expect(avatar.locator('img')).toHaveAttribute('width', px);
      expect(await avatar.evaluate((el) => getComputedStyle(el).width)).toBe(`${px}px`);
    }

    // The user menu shows the same picture.
    await expect(page.locator('.nowo-ui-user-menu__summary .nowo-ui-avatar')).toHaveClass(/\bis-loaded\b/);

    expect(await page.locator('[onerror], [onload], [onclick], .nowo-ui-avatar[style], .nowo-ui-avatar img[style]').count()).toBe(0);
  });

  test('without JavaScript a broken picture leaves the initials visible', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto('/kit/avatars');
    const broken = page.getByTestId('avatar-broken');
    // Without the script the broken <img alt=""> paints nothing and the initials stay visible (not transparent).
    await expect(broken).not.toHaveClass(/\bis-loaded\b/);
    expect(await broken.evaluate((el) => getComputedStyle(el).color)).not.toBe('rgba(0, 0, 0, 0)');
    await context.close();
  });

  test('axe: no avatar violation with pictures, a broken URL and initials', async ({ page }) => {
    await page.goto('/kit/avatars');
    await expect(page.getByTestId('avatar-picture')).toHaveClass(/\bis-loaded\b/);
    await expect(page.getByTestId('avatar-broken').locator('img')).toHaveCount(0);
    await page.locator('.nowo-ui-user-menu__summary').click();
    const results = await new AxeBuilder({ page })
      .exclude('.sf-toolbar')
      .exclude('.sf-minitoolbar')
      .withRules(['image-alt', 'role-img-alt', 'aria-allowed-role', 'aria-allowed-attr', 'aria-hidden-focus', 'color-contrast', 'nested-interactive'])
      .analyze();
    expect(results.violations).toEqual([]);
    const full = await new AxeBuilder({ page }).include('[data-testid="kit-avatars-main"]').analyze();
    expect(full.violations).toEqual([]);
  });
});
