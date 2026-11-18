import { test, expect, type Page, type CDPSession } from '@playwright/test';

/**
 * UiKit 2.58 on /kit/context-menu at phone size (390 × 844; projects phone-chromium and phone-webkit): a `sheet: true` kebab
 * and its context menu open as a bottom sheet above a backdrop; a tap on the backdrop closes it without reaching the page.
 * Long press with real touch input in Chromium (CDP).
 */
test.describe.configure({ timeout: 45_000 });

const PAGE = '/kit/context-menu';
const sheet = (page: Page) => page.locator('.nowo-ui-context-menu--sheet');
const card = (page: Page) => page.getByTestId('card-message');

async function expectBottomSheet(page: Page, panel: ReturnType<Page['locator']>): Promise<void> {
  const box = (await panel.boundingBox())!;
  const viewport = page.viewportSize()!;
  expect(Math.round(box.x)).toBe(0);
  expect(Math.round(box.width)).toBe(viewport.width);
  expect(Math.abs(box.y + box.height - viewport.height)).toBeLessThan(2);
}

test.describe('context menu sheet (phone)', () => {
  test.beforeEach(async ({ page }) => {
    // The dev toolbar sits over the bottom of the page (and of a bottom sheet): keep it out.
    await page.route('**/_wdt/**', (route) => route.abort());
  });

  test('the context menu of a sheet kebab is a bottom sheet with the row at the top; the backdrop tap closes it', async ({ page }) => {
    await page.goto(PAGE);
    await card(page).locator('h2').click({ button: 'right', position: { x: 8, y: 8 } });
    await expect(sheet(page)).toBeVisible();
    await page.evaluate(() => Promise.all(document.getAnimations().map((a) => a.finished.catch(() => undefined))));
    await expectBottomSheet(page, sheet(page));
    const first = sheet(page).getByRole('menuitem').first();
    await expect(first).toHaveText('👍');
    expect((await first.boundingBox())!.height).toBeGreaterThanOrEqual(43);
    await expect(page.locator('[data-nowo-ui-context-menu-backdrop]')).toBeVisible();
    // A tap near the top of the screen lands on the backdrop, not on the page below.
    await page.mouse.click(200, 20);
    await expect(sheet(page)).toHaveCount(0);
    await expect(page.locator('[data-nowo-ui-context-menu-backdrop]')).toHaveCount(0);
    await expect(page).not.toHaveURL(/picked=/);
  });

  test('a long press opens the sheet; a tap on an item runs it', async ({ page, browserName }) => {
    test.skip(browserName !== 'chromium', 'CDP touch input: Chromium only');
    await page.goto(PAGE);
    const cdp: CDPSession = await page.context().newCDPSession(page);
    await card(page).scrollIntoViewIfNeeded();
    const box = (await card(page).locator('h2').boundingBox())!;
    const point = { x: box.x + 10, y: box.y + box.height / 2, id: 1 };
    await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [point] });
    await page.waitForTimeout(800);
    await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
    await expect(sheet(page)).toBeVisible();
    await sheet(page).getByRole('menuitem', { name: 'Reply' }).tap();
    await expect(page).toHaveURL(/picked=reply/);
  });

  test('the kebab itself opens as a bottom sheet on a phone', async ({ page }) => {
    await page.goto(PAGE);
    await card(page).locator('summary').click();
    const kebabMenu = card(page).locator('.nowo-ui-kebab__menu');
    await expect(kebabMenu).toBeVisible();
    await expectBottomSheet(page, kebabMenu);
    await kebabMenu.getByRole('menuitem', { name: 'Delete' }).click();
    await expect(page).toHaveURL(/picked=delete-message/);
  });
});
