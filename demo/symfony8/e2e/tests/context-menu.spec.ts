import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * UiKit 2.54 context menus on /kit/context-menu: right-click, Shift+F10 / the ContextMenu key, the WAI-ARIA menu keyboard,
 * submenus, the native menu kept where it belongs, one menu at a time. Chromium, Firefox and WebKit (make test-e2e-context-menu).
 */
test.describe.configure({ timeout: 45_000 });

async function watchCsp(page: Page): Promise<() => Promise<string[]>> {
  await page.route('**/_wdt/**', (route) => route.abort());
  await page.addInitScript(() => {
    const seen: string[] = [];
    (window as unknown as { cspSeen: string[] }).cspSeen = seen;
    document.addEventListener('securitypolicyviolation', (e) => seen.push(`${e.effectiveDirective} ${e.blockedURI}`));
  });
  // Inline style attributes and handlers: what the context menu could cause. The dev toolbar's own inline script is not ours.
  return () => page.evaluate(() => (window as unknown as { cspSeen: string[] }).cspSeen.filter((v) => /^[a-z-]+-attr /.test(v)));
}

const PAGE = '/kit/context-menu';
const menu = (page: Page) => page.locator('.nowo-ui-context-menu:not(.nowo-ui-context-menu--sub)');
const submenu = (page: Page) => page.locator('.nowo-ui-context-menu--sub');
const row = (page: Page, id: string) => page.getByTestId(`row-${id}`);

/** Lets running transitions end (the menu fades in): colours read during one are not the final ones. */
const settled = (page: Page): Promise<unknown> =>
  page.evaluate(() => Promise.all(document.getAnimations().filter((a) => a instanceof CSSTransition).map((a) => a.finished.catch(() => undefined))));

async function rightClick(page: Page, testId: string, options: { modifiers?: Array<'Shift' | 'Control'> } = {}): Promise<void> {
  // The first cell's text, away from its link.
  await page.getByTestId(testId).locator('td, h2').first().click({ button: 'right', position: { x: 8, y: 8 }, ...options });
}

test.describe('context menu', () => {
  test('right-click opens the kebab items at the pointer, as an ARIA menu; choosing one runs the kebab item', async ({ page }) => {
    const csp = await watchCsp(page);
    await page.goto(PAGE);
    const cell = row(page, '1042').locator('td').first();
    const box = (await cell.boundingBox())!;
    await cell.click({ button: 'right', position: { x: 8, y: 8 } });
    await expect(menu(page)).toBeVisible();
    await expect(menu(page)).toHaveAttribute('role', 'menu');
    await expect(page.getByRole('menu', { name: 'Actions' })).toBeVisible();
    const items = menu(page).getByRole('menuitem');
    await expect(items).toHaveText(['Edit', 'Duplicate', 'Download PDF', 'Delete']);
    await expect(items.first()).toBeFocused();
    const panel = (await menu(page).boundingBox())!;
    expect(Math.abs(panel.x - (box.x + 8))).toBeLessThan(3);
    expect(Math.abs(panel.y - (box.y + 8))).toBeLessThan(3);
    expect(await menu(page).evaluate((el) => el.matches(':popover-open'))).toBe(true);
    await settled(page);
    const axe = await new AxeBuilder({ page }).include('.nowo-ui-context-menu').analyze();
    expect(axe.violations).toEqual([]);
    await items.nth(1).click();
    await expect(page).toHaveURL(/picked=duplicate-1042/);
    await expect(page.getByTestId('picked')).toHaveText('Picked: duplicate-1042');
    expect(await csp()).toEqual([]);
  });

  test('Shift+F10 on a focused row: anchored to it, arrows / Home / End / typeahead, Escape returns focus, Enter activates', async ({ page }) => {
    await page.goto(PAGE);
    const target = row(page, '1042');
    await target.focus();
    await page.keyboard.press('Shift+F10');
    await expect(menu(page)).toBeVisible();
    const items = menu(page).getByRole('menuitem');
    const rowBox = (await target.boundingBox())!;
    const panel = (await menu(page).boundingBox())!;
    expect(panel.y).toBeGreaterThanOrEqual(rowBox.y + rowBox.height - 1);
    await expect(items.nth(0)).toBeFocused();
    await page.keyboard.press('ArrowDown');
    await expect(items.nth(1)).toBeFocused();
    await page.keyboard.press('End');
    await expect(items.nth(3)).toBeFocused();
    await page.keyboard.press('ArrowDown');
    await expect(items.nth(0)).toBeFocused();
    await page.keyboard.press('ArrowUp');
    await expect(items.nth(3)).toBeFocused();
    await page.keyboard.press('Home');
    await expect(items.nth(0)).toBeFocused();
    await page.keyboard.press('d');
    await expect(items.nth(1)).toBeFocused();
    await page.keyboard.press('d');
    await expect(items.nth(2)).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(menu(page)).toHaveCount(0);
    await expect(target).toBeFocused();
    await page.keyboard.press('Shift+F10');
    await expect(items.nth(0)).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/picked=edit-1042/);
  });

  test('the ContextMenu key opens it too', async ({ page, browserName }) => {
    test.skip(browserName !== 'chromium', 'Only Chromium maps the ContextMenu key name in Playwright');
    await page.goto(PAGE);
    await row(page, '1041').focus();
    await page.keyboard.press('ContextMenu');
    await expect(menu(page).getByRole('menuitem')).toHaveText(['Edit', 'Delete']);
    await page.keyboard.press('Tab');
    await expect(menu(page)).toHaveCount(0);
    await expect(row(page, '1041')).toBeFocused();
  });

  test('the native menu stays: links, fields, Shift / Ctrl held, selected text, rows without a kebab', async ({ page }) => {
    await page.goto(PAGE);
    await page.getByTestId('row-link').click({ button: 'right' });
    await expect(menu(page)).toHaveCount(0);
    await page.getByTestId('row-input').click({ button: 'right' });
    await expect(menu(page)).toHaveCount(0);
    await rightClick(page, 'row-1042', { modifiers: ['Shift'] });
    await expect(menu(page)).toHaveCount(0);
    await rightClick(page, 'row-plain');
    await expect(menu(page)).toHaveCount(0);
    await row(page, '1041').locator('td').first().selectText();
    await rightClick(page, 'row-1041');
    await expect(menu(page)).toHaveCount(0);
    await page.evaluate(() => window.getSelection()?.removeAllRanges());
    await rightClick(page, 'row-1041');
    await expect(menu(page)).toHaveCount(1);
  });

  test('submenus: Right opens, Left closes, hover opens, a submenu item runs', async ({ page }) => {
    await page.goto(PAGE);
    await rightClick(page, 'card-board');
    const items = menu(page).getByRole('menuitem');
    await expect(items).toHaveText(['Open', 'Move to', 'Archive']);
    await expect(menu(page).getByRole('separator')).toHaveCount(1);
    const move = items.nth(1);
    await expect(move).toHaveAttribute('aria-haspopup', 'menu');
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('ArrowRight');
    await expect(submenu(page)).toBeVisible();
    await expect(move).toHaveAttribute('aria-expanded', 'true');
    await expect(submenu(page).getByRole('menuitem')).toHaveText(['To do', 'Doing', 'Done']);
    await expect(submenu(page).getByRole('menuitem').first()).toBeFocused();
    await page.keyboard.press('ArrowLeft');
    await expect(submenu(page)).toHaveCount(0);
    await expect(move).toBeFocused();
    await move.hover();
    await expect(submenu(page)).toBeVisible();
    await submenu(page).getByRole('menuitem', { name: 'Done' }).click();
    await expect(page).toHaveURL(/picked=move-done/);
  });

  test('one menu at a time; an outside click and scrolling close it; a kebab popover closes when it opens', async ({ page }) => {
    await page.goto(PAGE);
    await rightClick(page, 'row-1042');
    await rightClick(page, 'row-1041');
    await expect(menu(page)).toHaveCount(1);
    await expect(menu(page).getByRole('menuitem')).toHaveText(['Edit', 'Delete']);
    await page.locator('h1').click();
    await expect(menu(page)).toHaveCount(0);

    await rightClick(page, 'card-project');
    await expect(menu(page).getByRole('menuitem')).toHaveText(['Rename', 'Share']);
    await page.mouse.wheel(0, 200);
    await expect(menu(page)).toHaveCount(0);

    await page.evaluate(() => window.scrollTo(0, 0));
    await page.locator('#kebab-1042').evaluate((panel) => (panel as HTMLElement).showPopover());
    await expect(page.locator('#kebab-1042')).toBeVisible();
    await rightClick(page, 'row-1041');
    await expect(page.locator('#kebab-1042')).toBeHidden();
    await expect(menu(page)).toHaveCount(1);
  });

  test('a POST row action runs from the context menu (the form of the row actions submits)', async ({ page }) => {
    await page.goto(PAGE);
    await rightClick(page, 'row-1040');
    await expect(menu(page).getByRole('menuitem')).toHaveText(['View', 'Copy']);
    await menu(page).getByRole('menuitem', { name: 'Copy' }).click();
    await expect(page.getByTestId('submitted')).toBeVisible();
  });

  test('kept inside a narrow viewport', async ({ page }) => {
    await page.setViewportSize({ width: 420, height: 500 });
    await page.goto(PAGE);
    const box = (await row(page, '1041').boundingBox())!;
    // The row's visible right end: the demo platform keeps a 16 px gutter (and the card's own padding) on phones.
    await page.mouse.click(Math.min(box.x + box.width, 420 - 32) - 10, box.y + box.height / 2, { button: 'right' });
    await expect(menu(page)).toBeVisible();
    const panel = (await menu(page).boundingBox())!;
    expect(panel.x).toBeGreaterThanOrEqual(0);
    expect(panel.x + panel.width).toBeLessThanOrEqual(420);
  });
});
