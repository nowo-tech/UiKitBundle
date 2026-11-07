import { test, expect, type Locator } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * UiKit 2.21 detail page (Beacon UI standardisation) on the order page, with the host theme (Acme page_header
 * override printing `status_badge` and the split `toolbar`) and with UiKit's default theme: status badge next to the
 * title, icon buttons, one primary button, the square "more" menu (popover) with the destructive action last and its
 * confirm dialog outside the panel, the key figures row and the compact details grid — plus axe on those regions.
 */
const views = [
  ['host theme', '/page-contract/orders/1042'],
  ['default view', '/page-contract/default/orders/1042'],
] as const;

async function box(locator: Locator): Promise<{ width: number; height: number }> {
  return locator.evaluate((el) => {
    const rect = el.getBoundingClientRect();
    return { width: rect.width, height: rect.height };
  });
}

for (const [view, path] of views) {
  test.describe(`detail page — ${view}`, () => {
    test('status badge next to the title, icon buttons, one primary button, square more toggle', async ({ page }) => {
      await page.goto(path);
      const header = page.locator('[data-nowo-ui-region="header"]');
      const status = header.locator('[data-nowo-ui-page-status="success"]');
      await expect(status).toHaveText('Paid');
      await expect(status).toHaveAttribute('title', 'Updated 2026-10-16 10:30');
      const actions = header.locator('[data-nowo-ui-header-actions]');
      await expect(actions).toHaveCount(1);
      const edit = actions.getByRole('link', { name: 'Edit', exact: true });
      await expect(edit).toHaveAttribute('title', 'Edit');
      await expect(actions.locator('[data-testid="order-invoice"]')).toHaveAttribute('aria-label', 'Download invoice');
      await expect(actions.locator('.nowo-ui-action--create, [data-testid="order-ship"]')).toHaveText('Mark as shipped');
      const toggle = actions.locator('[data-nowo-ui-header-more] [data-nowo-ui-popover-trigger]');
      await expect(toggle).toHaveAttribute('aria-label', 'More actions');
      await expect(toggle).toHaveAttribute('title', 'More actions');
      // the square toggle has the box of the icon buttons next to it
      const [icon, more] = [await box(edit), await box(toggle)];
      expect(Math.abs(more.width - icon.width)).toBeLessThanOrEqual(1);
      expect(Math.abs(more.height - icon.height)).toBeLessThanOrEqual(1);
      // the actions render once (split), never as a second toolbar
      await expect(page.locator('[data-testid="order-duplicate"]')).toHaveCount(1);
    });

    test('more menu: items in order, destructive last, confirm dialog outside the popover', async ({ page }) => {
      await page.goto(path);
      const toggle = page.locator('[data-nowo-ui-header-more] [data-nowo-ui-popover-trigger]');
      const panel = page.locator('#nowo-ui-header-more-panel');
      await expect(panel).toBeHidden();
      await toggle.click();
      await expect(panel).toBeVisible();
      await expect(toggle).toHaveAttribute('aria-expanded', 'true');
      const items = panel.locator('.nowo-ui-menu__item');
      await expect(items).toHaveText(['Duplicate order', 'Reopen', 'Delete']);
      await expect(items.last()).toHaveClass(/\bnowo-ui-menu__item--danger\b/);
      await expect(panel.locator('form[name="reopen_order"] button.nowo-ui-menu__item')).toHaveText('Reopen');
      await items.last().click();
      const dialog = page.locator('dialog[open]');
      await expect(dialog).toBeVisible();
      await expect(dialog).toContainText('Delete order #1042?');
      await expect(panel).toBeHidden();
      expect(await dialog.evaluate((el) => el.closest('[popover]') === null)).toBe(true);
      await page.keyboard.press('Escape');
      await expect(dialog).toBeHidden();
    });

    test('key figures row and the compact details grid', async ({ page }) => {
      await page.goto(path);
      const tiles = page.locator('[data-nowo-ui-region="stats"] .nowo-ui-stat');
      await expect(tiles).toHaveCount(4);
      await expect(tiles.first().locator('.nowo-ui-stat__label')).toHaveText('Items');
      await expect(tiles.nth(2)).toHaveClass(/\bnowo-ui-stat--success\b/);
      await expect(tiles.nth(2).locator('.nowo-ui-stat__hint')).toHaveText('vs 31 % average');
      const tops = await tiles.evaluateAll((els) => els.map((el) => Math.round(el.getBoundingClientRect().top)));
      expect(new Set(tops).size).toBe(1);
      const facts = page.locator('#shipping dl.nowo-ui-detail-list--facts');
      await expect(facts.locator('dt')).toHaveCount(6);
      const columns = await facts.evaluate((el) => getComputedStyle(el).gridTemplateColumns.split(' ').length);
      expect(columns).toBeGreaterThan(1);
      const terms = await facts.locator('.nowo-ui-detail-list__item').evaluateAll((els) => els.map((el) => getComputedStyle(el).display));
      expect(new Set(terms)).toEqual(new Set(['block']));
    });

    test('phone: the actions wrap inside the header', async ({ page }) => {
      await page.setViewportSize({ width: 390, height: 844 });
      await page.goto(path);
      const actions = page.locator('[data-nowo-ui-header-actions]');
      await expect(actions).toBeVisible();
      // every button stays inside the actions row (they wrap instead of overflowing it)
      const overflow = await actions.evaluate((el) => {
        const right = el.getBoundingClientRect().right;
        return Math.max(0, ...[...el.children].map((child) => child.getBoundingClientRect().right - right));
      });
      expect(overflow).toBeLessThanOrEqual(1);
      expect(await actions.evaluate((el) => el.scrollWidth - el.clientWidth)).toBeLessThanOrEqual(0);
    });

    test('axe: header, menu, key figures and details have no violations', async ({ page }) => {
      await page.goto(path);
      const closed = await new AxeBuilder({ page })
        .include('[data-nowo-ui-region="header"]')
        .include('[data-nowo-ui-region="stats"]')
        .include('#shipping')
        .analyze();
      expect(closed.violations).toEqual([]);
      await page.locator('[data-nowo-ui-header-more] [data-nowo-ui-popover-trigger]').click();
      await expect(page.locator('#nowo-ui-header-more-panel')).toBeVisible();
      // Contrast is measured on the final colours, not during the fade.
      await page.evaluate(() => Promise.all(document.getAnimations().filter((a) => a instanceof CSSTransition).map((a) => a.finished.catch(() => undefined))));
      const open = await new AxeBuilder({ page }).include('#nowo-ui-header-more-panel').analyze();
      expect(open.violations).toEqual([]);
    });
  });
}
