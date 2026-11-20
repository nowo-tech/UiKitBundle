import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * UiKit 2.60 demo platform shell (/kit/demo-platform): the layout every nowo-tech bundle demo extends. Landmarks and
 * skip link, the current nav item, the locale pills, the theme toggle, the phone drawer, no horizontal overflow, and
 * axe (wcag2a / 2aa / 21a / 21aa, colour contrast included: serious + critical = 0) in light and dark, desktop and phone.
 */
type Theme = 'light' | 'dark';

async function setTheme(page: Page, theme: Theme): Promise<void> {
  await page.evaluate((t) => {
    document.documentElement.setAttribute('data-theme', t);
    document.documentElement.setAttribute('data-bs-theme', t);
  }, theme);
}

async function axe(page: Page): Promise<string[]> {
  const result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
  return result.violations
    .filter((v) => v.impact === 'serious' || v.impact === 'critical')
    .map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`);
}

test.describe('Demo platform shell', () => {
  test('regions, current item and skip link', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/kit/demo-platform/messages');
    const shell = page.getByTestId('demo-platform');
    await expect(shell).toBeVisible();
    await expect(page.getByTestId('demo-platform-brand')).toContainText('nowo · bundles');
    await expect(page.getByTestId('demo-platform-bundle')).toHaveText('Acme Inbox');
    await expect(page.getByRole('banner')).toBeVisible();
    await expect(page.getByRole('complementary', { name: 'Demo navigation' })).toBeVisible();
    await expect(page.getByRole('main')).toBeVisible();
    await expect(page.getByRole('contentinfo')).toContainText('Source code');
    await expect(page.locator('[aria-current="page"]')).toHaveCount(1);
    await expect(page.locator('[aria-current="page"]')).toHaveText('Messages');
    await expect(page.getByRole('navigation', { name: 'Inbox' })).toBeVisible();
    await expect(page.getByTestId('demo-platform-locales').locator('[aria-current="true"]')).toHaveAttribute('lang', 'en');

    await page.keyboard.press('Tab');
    const skip = page.locator('.nowo-ui-skip-link');
    await expect(skip).toBeFocused();
    await expect(skip).toBeInViewport();
    await skip.press('Enter');
    await expect(page.locator('#nowo-ui-content')).toBeFocused();

    const font = await page.evaluate(() => getComputedStyle(document.body).fontFamily);
    expect(font).not.toMatch(/^(Times|serif)/i);
  });

  test('theme toggle switches to dark', async ({ page }) => {
    await page.goto('/kit/demo-platform');
    await page.evaluate(() => localStorage.removeItem('nowo-ui-theme'));
    const before = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
    await page.locator('[data-nowo-ui-theme-toggle]').click();
    await expect(page.locator('html')).not.toHaveAttribute('data-theme', before ?? '');
  });

  test('phone: the aside is a drawer behind the burger, no horizontal overflow', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/kit/demo-platform');
    const aside = page.locator('#nowo-ui-demo-aside');
    await expect(aside).not.toBeInViewport();
    await page.locator('[data-nowo-ui-burger]').first().click();
    await expect(page.getByTestId('demo-platform')).toHaveClass(/is-aside-open-mobile/);
    await expect(aside).toBeInViewport();
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(0);
  });

  for (const theme of ['light', 'dark'] as Theme[]) {
    for (const [name, width] of [['desktop', 1440], ['phone', 390]] as const) {
      test(`axe clean: ${name}, ${theme}`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/kit/demo-platform/settings');
        await setTheme(page, theme);
        expect(await axe(page)).toEqual([]);
      });
    }
  }
});
