import { test, expect, type Locator, type Page } from '@playwright/test';

/**
 * 2.41 phone mode (Beacon mobile UX audit 2026-11-09) at 390 × 844 in Chromium and WebKit (projects phone-chromium,
 * phone-webkit): edge tabs folded behind one launcher (MUX-01), kit selects WebKit no longer cuts (MUX-40), phone cards
 * and the overflow scroll of responsive tables (MUX-20 / MUX-22), touch targets and the font floor (MUX-04 / MUX-161),
 * the guest header (MUX-150). Screenshots go to test-results/mux-* for a visual check.
 */
async function open(page: Page, path = '/kit/mobile'): Promise<void> {
  await page.route('**/_wdt/**', (route) => route.abort());
  await page.goto(path);
  await expect(page.locator('html')).toHaveAttribute('data-nowo-ui-edge-tabs-ready', '');
}

const visible = (l: Locator): Promise<boolean> => l.evaluate((el) => getComputedStyle(el).display !== 'none');

test.describe('phone mode', () => {
  test('MUX-01: the edge tabs fold behind one launcher that covers nothing', async ({ page }, info) => {
    await open(page);
    const launcher = page.getByTestId('edge-launcher');
    await expect(launcher).toBeVisible();
    await expect(launcher).toHaveAttribute('aria-expanded', 'false');
    await expect(launcher).toHaveAccessibleName('Page tools');
    for (const id of ['edge-manual', 'edge-notes', 'edge-prefs']) {
      expect(await visible(page.getByTestId(id).locator('a'))).toBe(false);
    }
    // The gutter keeps the launcher's width free: the end of the lede line is the lede, not a tab.
    const lede = page.getByTestId('lede');
    const box = (await lede.boundingBox())!;
    const launcherBox = (await launcher.boundingBox())!;
    expect(box.x + box.width).toBeLessThanOrEqual(launcherBox.x + 1);
    await page.screenshot({ path: `test-results/mux-edge-closed-${info.project.name}.png` });

    await launcher.click();
    await expect(launcher).toHaveAttribute('aria-expanded', 'true');
    const manual = page.getByTestId('edge-manual').locator('a');
    await expect(manual).toBeVisible();
    expect((await manual.boundingBox())!.y).toBeGreaterThan(launcherBox.y + launcherBox.height - 1);
    await page.screenshot({ path: `test-results/mux-edge-open-${info.project.name}.png` });

    await page.keyboard.press('Escape');
    await expect(launcher).toHaveAttribute('aria-expanded', 'false');
    await expect(launcher).toBeFocused();
    await expect(manual).toBeHidden();

    // Using a tab folds them again.
    await launcher.click();
    await page.getByTestId('edge-notes').locator('a').click();
    await expect(page).toHaveURL(/#notes$/);
    await expect(launcher).toHaveAttribute('aria-expanded', 'false');
  });

  test('MUX-01: desktop keeps the 2.40 tabs', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await open(page);
    // Wider than 64rem: no fold, even on a touch screen.
    await expect(page.getByTestId('edge-launcher')).toBeHidden();
    await expect(page.getByTestId('edge-manual').locator('a')).toBeVisible();
  });

  test('MUX-40: kit selects keep their text inside the box', async ({ page }, info) => {
    await open(page);
    for (const id of ['select-kit', 'select-opt-in', 'select-pager']) {
      const metrics = await page.getByTestId(id).evaluate((el) => {
        const s = getComputedStyle(el);
        const px = (v: string): number => Number.parseFloat(v) || 0;
        return {
          appearance: s.getPropertyValue('appearance') || s.getPropertyValue('-webkit-appearance'),
          content: el.clientHeight - px(s.paddingTop) - px(s.paddingBottom),
          line: px(s.lineHeight),
        };
      });
      expect(metrics.appearance, id).toBe('none');
      expect(metrics.content, id).toBeGreaterThanOrEqual(metrics.line - 0.5);
    }
    await page.getByTestId('selects').screenshot({ path: `test-results/mux-selects-${info.project.name}.png` });
  });

  test('MUX-20: phone cards, primary first, hidden column out, no sideways scroll', async ({ page }, info) => {
    await open(page);
    const root = page.getByTestId('rt-cards');
    await expect(root).toHaveAttribute('data-rt-cards', '');
    const row = root.locator('tbody tr').first();
    const name = row.locator('td[data-column="name"]');
    const role = row.locator('td[data-column="role"]');
    await expect(name).toBeVisible();
    await expect(row.locator('td[data-column="id"]')).toBeHidden();
    await expect(role).toHaveAttribute('data-rt-label', 'Role');
    await expect(row.locator('td[data-column="email"]')).toHaveAttribute('data-nowo-ui-phone', 'meta');
    expect((await name.boundingBox())!.y).toBeLessThan((await role.boundingBox())!.y);
    const scroller = root.locator('.nowo-ui-responsive-table__scroller');
    expect(await scroller.evaluate((el) => el.scrollWidth <= el.clientWidth + 1)).toBe(true);
    await root.screenshot({ path: `test-results/mux-cards-${info.project.name}.png` });
  });

  test('MUX-20: required columns that overflow scroll sideways, words stay whole', async ({ page }, info) => {
    await open(page);
    const root = page.getByTestId('rt-scroll');
    await expect(root).toHaveAttribute('data-table-mode', 'responsive');
    await expect(root).toHaveAttribute('data-rt-overflow', '');
    const scroller = root.locator('.nowo-ui-responsive-table__scroller');
    expect(await scroller.evaluate((el) => getComputedStyle(el).overflowX)).toBe('auto');
    expect(await scroller.evaluate((el) => el.scrollWidth > el.clientWidth)).toBe(true);
    expect(await page.getByTestId('rt-scroll-name').evaluate((el) => getComputedStyle(el).overflowWrap)).not.toBe('anywhere');
    await root.screenshot({ path: `test-results/mux-scroll-${info.project.name}.png` });
  });

  test('MUX-22: Column::phone in the table partial card mode', async ({ page }) => {
    await open(page);
    const row = page.getByTestId('column-phone').locator('tbody tr').first();
    const name = row.locator('td[data-nowo-ui-phone="primary"]');
    await expect(name).toHaveText('Demo Admin');
    await expect(row.locator('td[data-nowo-ui-phone="hide"]')).toBeHidden();
    expect((await name.boundingBox())!.y).toBeLessThan((await row.locator('td[data-label="Role"]').boundingBox())!.y);
    const before = await name.evaluate((el) => getComputedStyle(el, '::before').content);
    expect(['none', 'normal', '']).toContain(before);
  });

  test('MUX-04 / MUX-161: touch targets and the font floor', async ({ page }) => {
    await open(page);
    const coarse = await page.evaluate(() => matchMedia('(pointer: coarse)').matches);
    if (coarse) {
      const tab = (await page.getByTestId('tabs').locator('.nowo-ui-tabs__item').first().boundingBox())!;
      expect(tab.height).toBeGreaterThanOrEqual(43.5);
    }
    const size = await page.getByTestId('tiny-badge').evaluate((el) => Number.parseFloat(getComputedStyle(el).fontSize));
    expect(size).toBeGreaterThanOrEqual(12);
  });

  test('MUX-150: the guest header links home', async ({ page }, info) => {
    await page.route('**/_wdt/**', (route) => route.abort());
    await page.goto('/kit/mobile/guest');
    const header = page.getByTestId('guest-header');
    await expect(header.getByRole('link', { name: /UiKit demo/ })).toHaveAttribute('href', '/kit/mobile');
    await expect(header.getByRole('link', { name: 'Sign in' })).toBeVisible();
    await page.screenshot({ path: `test-results/mux-guest-${info.project.name}.png` });
  });
});
