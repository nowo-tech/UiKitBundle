import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * UiKit 2.54 shell focus mode on /kit/focus-mode (theme.focus_mode enabled with keep [breadcrumb_min, search,
 * timer_chip]): the page toggle and the focus bar, hidden regions (inert, out of the tab order) and kept ones, the
 * full-width work area, F / Escape / Ctrl+K, dialogs keeping Escape, the per-area memory (also without storage),
 * fullscreen and its browser-side exit, the live region, axe, and no CSP violation (the page forbids inline style
 * attributes, handlers and scripts).
 */
async function watchCsp(page: Page): Promise<string[]> {
  const violations: string[] = [];
  page.on('console', (msg) => {
    if (/Content Security Policy/i.test(msg.text())) violations.push(msg.text());
  });
  return violations;
}

const state = (page: Page): Promise<string | null> => page.getByTestId('shell').getAttribute('data-focus-mode');

test.describe('shell focus mode (2.54)', () => {
  test.use({ reducedMotion: 'reduce' });

  test.beforeEach(async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto('/kit/focus-mode');
    await page.evaluate(() => localStorage.clear());
    await page.reload();
  });

  test('the toggle hides the shell regions, keeps the kept ones and widens the work area; the bar leaves', async ({ page }) => {
    const csp = await watchCsp(page);
    const toggle = page.getByTestId('focus-toggle');
    const article = page.getByTestId('article');
    await expect(toggle).toHaveAttribute('aria-pressed', 'false');
    await expect(toggle).toHaveAccessibleName('Focus mode');
    await expect(page.locator('[data-nowo-ui-focus-bar]')).toBeHidden();
    const before = (await article.boundingBox())!;

    await toggle.click();
    expect(await state(page)).toBe('on');
    await expect(toggle).toHaveAttribute('aria-pressed', 'true');
    await expect(toggle).toBeFocused();
    for (const hidden of [page.locator('[data-nowo-ui-aside]'), page.locator('footer'), page.getByTestId('dock'), page.getByTestId('comments'), page.getByTestId('header-link')]) {
      await expect(hidden).toBeHidden();
    }
    await expect(page.locator('[data-nowo-ui-aside]')).toHaveAttribute('inert', '');
    // Kept: the search field and the timer chip of the header.
    await expect(page.getByTestId('search')).toBeVisible();
    await expect(page.getByTestId('timer-chip')).toBeVisible();
    // The bar: one-line breadcrumb + exit (+ fullscreen where the browser has it).
    const bar = page.locator('[data-nowo-ui-focus-bar]');
    await expect(bar).toBeVisible();
    await expect(bar.locator('[aria-current="page"]')).toHaveText('Deploying the API');
    await expect(page.getByRole('button', { name: 'Exit focus' })).toBeVisible();
    await expect(page.locator('[data-nowo-ui-focus-status]')).toHaveText('Focus mode on. Press Escape to leave it.');
    const after = (await article.boundingBox())!;
    expect(after.width).toBeGreaterThan(before.width + 200);

    // Hidden regions are out of the tab order: Tab from the search field never lands in the aside or the footer.
    await page.getByTestId('search').focus();
    for (let i = 0; i < 12; i += 1) {
      await page.keyboard.press('Tab');
      expect(await page.evaluate(() => document.activeElement?.closest('[data-nowo-ui-focus-hidden]') === null)).toBe(true);
    }

    await page.getByRole('button', { name: 'Exit focus' }).click();
    expect(await state(page)).toBeNull();
    await expect(page.locator('[data-nowo-ui-aside]')).toBeVisible();
    await expect(page.getByTestId('dock')).toBeVisible();
    await expect(bar).toBeHidden();
    await expect(toggle).toBeFocused();
    await expect(page.locator('[data-nowo-ui-focus-status]')).toHaveText('Focus mode off.');
    expect(csp).toEqual([]);
  });

  test('F toggles unless typing, Escape leaves, a dialog keeps its Escape, Ctrl+K still reaches the search', async ({ page }) => {
    await page.locator('body').click({ position: { x: 5, y: 5 } });
    await page.keyboard.press('f');
    expect(await state(page)).toBe('on');
    await page.keyboard.press('f');
    expect(await state(page)).toBeNull();

    await page.getByTestId('editor').click();
    await page.keyboard.press('End');
    await page.keyboard.type('ff');
    expect(await state(page)).toBeNull();
    await expect(page.getByTestId('editor')).toHaveValue(/ff$/);

    await page.getByTestId('focus-toggle').click();
    await page.getByTestId('open-dialog').click();
    await expect(page.locator('#kit-focus-dialog')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.locator('#kit-focus-dialog')).toBeHidden();
    expect(await state(page)).toBe('on');

    await page.keyboard.press('Control+k');
    await expect(page.getByTestId('search')).toBeFocused();
    await page.keyboard.press('Escape');
    expect(await state(page)).toBeNull();
    await expect(page.getByTestId('search')).toBeFocused();
  });

  test('remembered per area across reloads, and fine without storage', async ({ page, context }) => {
    await page.getByTestId('focus-toggle').click();
    expect(await page.evaluate(() => localStorage.getItem('nowo-ui-focus:wiki'))).toBe('on');
    await page.reload();
    expect(await state(page)).toBe('on');
    await expect(page.locator('[data-nowo-ui-aside]')).toBeHidden();
    await page.keyboard.press('Escape');
    await page.reload();
    expect(await state(page)).toBeNull();

    const blocked = await context.newPage();
    await blocked.addInitScript(() => {
      Object.defineProperty(window, 'localStorage', {
        configurable: true,
        get() {
          throw new DOMException('blocked', 'SecurityError');
        },
      });
    });
    const errors: string[] = [];
    blocked.on('pageerror', (error) => errors.push(error.message));
    await blocked.goto('/kit/focus-mode');
    await blocked.getByTestId('focus-toggle').click();
    expect(await blocked.getByTestId('shell').getAttribute('data-focus-mode')).toBe('on');
    await blocked.keyboard.press('Escape');
    expect(await blocked.getByTestId('shell').getAttribute('data-focus-mode')).toBeNull();
    expect(errors).toEqual([]);
  });

  test('fullscreen on the shell, and a browser-side exit goes back to level 1', async ({ page }) => {
    const supported = await page.evaluate(() => document.fullscreenEnabled);
    await page.getByTestId('fullscreen-toggle').click();
    if (!supported) {
      expect(await state(page)).toBe('on');
      await expect(page.locator('[data-nowo-ui-focus-status]')).toHaveText('Full screen is not available. Focus mode stays on.');
      return;
    }
    await expect.poll(() => state(page)).toBe('fullscreen');
    expect(await page.evaluate(() => document.fullscreenElement?.getAttribute('data-testid'))).toBe('shell');
    await expect(page.getByTestId('fullscreen-toggle')).toHaveAttribute('aria-pressed', 'true');

    // The browser leaves fullscreen (Escape / F11 / the API): focus mode stays on.
    await page.evaluate(() => document.exitFullscreen());
    await expect.poll(() => state(page)).toBe('on');
    await expect(page.locator('[data-nowo-ui-focus-status]')).toHaveText('Full screen off. Focus mode stays on.');
    await expect(page.locator('[data-nowo-ui-aside]')).toBeHidden();

    // From the bar: fullscreen again, then exit leaves both.
    await page.locator('[data-nowo-ui-focus-bar] [data-nowo-ui-focus-mode="fullscreen"]').click();
    await expect.poll(() => state(page)).toBe('fullscreen');
    await page.getByRole('button', { name: 'Exit focus' }).click();
    expect(await state(page)).toBeNull();
    await expect.poll(() => page.evaluate(() => document.fullscreenElement === null)).toBe(true);
  });

  test('axe: no violations in focus mode', async ({ page }) => {
    await page.getByTestId('focus-toggle').click();
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
    expect(results.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`)).toEqual([]);
  });
});
