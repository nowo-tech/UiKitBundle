import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * UiKit 2.58 on /kit/context-menu (desktop): a kebab row (quick reactions) and separators, in the kebab and in its context
 * menu; Left / Right inside the row, Up / Down leave it; no sheet on a wide screen. Chromium, Firefox and WebKit.
 */
test.describe.configure({ timeout: 45_000 });

const PAGE = '/kit/context-menu';
const menu = (page: Page) => page.locator('.nowo-ui-context-menu:not(.nowo-ui-context-menu--sub)');
const card = (page: Page) => page.getByTestId('card-message');

async function openByRightClick(page: Page): Promise<void> {
  await card(page).locator('h2').click({ button: 'right', position: { x: 8, y: 8 } });
  await expect(menu(page)).toBeVisible();
}

test.describe('context menu rows (desktop)', () => {
  test.beforeEach(async ({ page }) => {
    // The dev toolbar sits over the bottom of the page (and of a bottom sheet): keep it out.
    await page.route('**/_wdt/**', (route) => route.abort());
  });

  test('the row is one group of menu items, separators split the groups, and no sheet on a wide screen', async ({ page }) => {
    await page.goto(PAGE);
    await openByRightClick(page);
    const group = menu(page).getByRole('group', { name: 'Quick reactions' });
    await expect(group.getByRole('menuitem')).toHaveCount(4);
    await expect(menu(page).getByRole('separator')).toHaveCount(2);
    await expect(menu(page)).not.toHaveClass(/nowo-ui-context-menu--sheet/);
    await expect(page.locator('[data-nowo-ui-context-menu-backdrop]')).toHaveCount(0);
    // The row lies on one line: the items share their top edge.
    const tops = await group.getByRole('menuitem').evaluateAll((els) => els.map((el) => Math.round(el.getBoundingClientRect().top)));
    expect(new Set(tops).size).toBe(1);
    // Let the fade-in end: colours read during it are not the final ones.
    await page.evaluate(() => Promise.all(document.getAnimations().map((a) => a.finished.catch(() => undefined))));
    const results = await new AxeBuilder({ page }).include('.nowo-ui-context-menu').analyze();
    expect(results.violations).toEqual([]);
  });

  test('keyboard: Left / Right move inside the row, Down leaves it, Up comes back to its first item; Enter runs the item', async ({ page }) => {
    await page.goto(PAGE);
    await card(page).focus();
    await page.keyboard.press('Shift+F10');
    await expect(menu(page)).toBeVisible();
    const focused = () => page.evaluate(() => document.activeElement?.textContent?.trim() ?? '');
    expect(await focused()).toBe('👍');
    await page.keyboard.press('ArrowRight');
    expect(await focused()).toBe('❤️');
    await page.keyboard.press('ArrowLeft');
    await page.keyboard.press('ArrowLeft');
    expect(await focused()).toBe('+');
    await page.keyboard.press('ArrowDown');
    expect(await focused()).toBe('Reply');
    await page.keyboard.press('ArrowUp');
    expect(await focused()).toBe('👍');
    await page.keyboard.press('ArrowRight');
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/picked=react-heart/);
  });

  test('the kebab itself shows the row and the separators', async ({ page }) => {
    await page.goto(PAGE);
    await card(page).getByRole('button', { name: 'Message actions' }).or(card(page).locator('summary')).first().click();
    const kebabMenu = card(page).locator('.nowo-ui-kebab__menu');
    await expect(kebabMenu.locator('[data-nowo-ui-menu-row] [role="menuitem"]')).toHaveCount(4);
    await expect(kebabMenu.locator('hr[role="separator"]')).toHaveCount(2);
    await kebabMenu.getByRole('menuitem', { name: 'Copy text' }).click();
    await expect(page).toHaveURL(/picked=copy-text/);
  });
});
