import { test, expect, type Page, type CDPSession, type Locator } from '@playwright/test';

/**
 * UiKit 2.54 context menus with touch input, Chromium only (project `context-menu-touch`, Pixel 7 emulation: `pointer: coarse`).
 * CDP's Input.dispatchTouchEvent goes through Blink's real input pipeline (pointer events, gesture detection, scrolling).
 * What this does NOT prove: iOS Safari's own long-press callout and selection timing; check on a real device.
 */
test.describe.configure({ timeout: 45_000 });

type Point = { x: number; y: number };
type Send = (type: 'touchStart' | 'touchMove' | 'touchEnd', point?: Point) => Promise<void>;

async function touch(page: Page): Promise<Send> {
  const cdp: CDPSession = await page.context().newCDPSession(page);
  return (type, point) => cdp.send('Input.dispatchTouchEvent', { type, touchPoints: point ? [{ x: point.x, y: point.y, id: 1 }] : [] });
}

/** Near the start of an element, away from its link / field / kebab. */
async function grip(locator: Locator): Promise<Point> {
  await locator.scrollIntoViewIfNeeded();
  const box = (await locator.boundingBox())!;
  return { x: box.x + Math.min(12, box.width / 2), y: box.y + box.height / 2 };
}

const PAGE = '/kit/context-menu';
const menu = (page: Page) => page.locator('.nowo-ui-context-menu:not(.nowo-ui-context-menu--sub)');

test.describe('context menu (touch)', () => {
  test('a long press opens the kebab items at the finger; lifting it does not close the menu; a tap runs an item', async ({ page }) => {
    await page.goto(PAGE);
    expect(await page.evaluate(() => matchMedia('(pointer: coarse)').matches)).toBe(true);
    const send = await touch(page);
    const third = await grip(page.getByTestId('row-1041').locator('td').first());
    await send('touchStart', third);
    await page.waitForTimeout(800);
    await expect(menu(page)).toBeVisible();
    await send('touchEnd');
    await page.waitForTimeout(200);
    await expect(menu(page)).toBeVisible();
    await expect(menu(page).getByRole('menuitem')).toHaveText(['Edit', 'Delete']);
    const box = (await menu(page).boundingBox())!;
    expect(Math.abs(box.x - third.x)).toBeLessThan(4);
    const item = menu(page).getByRole('menuitem', { name: 'Edit' });
    expect((await item.boundingBox())!.height).toBeGreaterThanOrEqual(43);
    await item.tap();
    await expect(page).toHaveURL(/picked=edit-1041/);
  });

  test('moving the finger cancels the long press, and the swipe still scrolls the page', async ({ page }) => {
    await page.goto(PAGE);
    const send = await touch(page);
    const start = await grip(page.getByTestId('row-1041').locator('td').first());
    const before = await page.evaluate(() => window.scrollY);
    await send('touchStart', start);
    for (let i = 1; i <= 8; i++) {
      await send('touchMove', { x: start.x, y: start.y - i * 20 });
      await page.waitForTimeout(16);
    }
    await page.waitForTimeout(700);
    await send('touchEnd');
    await page.waitForTimeout(300);
    await expect(menu(page)).toHaveCount(0);
    expect(await page.evaluate(() => window.scrollY)).toBeGreaterThan(before);
  });

  test('a short tap is a tap, and a long press on a link keeps the browser behaviour', async ({ page }) => {
    await page.goto(PAGE);
    const send = await touch(page);
    const start = await grip(page.getByTestId('row-1041').locator('td').first());
    await send('touchStart', start);
    await page.waitForTimeout(80);
    await send('touchEnd');
    await page.waitForTimeout(700);
    await expect(menu(page)).toHaveCount(0);

    const link = await grip(page.getByTestId('row-link'));
    await send('touchStart', link);
    await page.waitForTimeout(800);
    await send('touchEnd');
    await page.waitForTimeout(200);
    await expect(menu(page)).toHaveCount(0);
  });
});
