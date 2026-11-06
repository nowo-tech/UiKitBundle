import { test, expect, type Page, type CDPSession, type Locator } from '@playwright/test';

/**
 * UiKit 2.35 sortable with touch input, Chromium only (project `chromium-touch`, Pixel 7 emulation). CDP's
 * Input.dispatchTouchEvent goes through Blink's real input pipeline: touch-action, gesture detection, scrolling,
 * pointercancel and touchmove cancelling all run.
 *
 * What this does NOT prove: anything about iOS Safari, Firefox for Android or a real panel: finger slop and jitter,
 * palm rejection, long-press timing against the OS callout, haptics, the address bar collapsing, or performance. Those
 * need the manual checklist in docs/USAGE.md (Sortable, "Testing on a real device").
 */
test.describe.configure({ timeout: 45_000 });

type Point = { x: number; y: number };

async function touch(page: Page): Promise<(type: 'touchStart' | 'touchMove' | 'touchEnd', point?: Point) => Promise<void>> {
  const cdp: CDPSession = await page.context().newCDPSession(page);
  return (type, point) => cdp.send('Input.dispatchTouchEvent', { type, touchPoints: point ? [{ x: point.x, y: point.y, id: 1 }] : [] });
}

async function centre(locator: Locator): Promise<Point> {
  await locator.scrollIntoViewIfNeeded();
  const box = await locator.boundingBox();
  if (!box) throw new Error('no box');
  return { x: box.x + box.width / 2, y: box.y + box.height / 2 };
}

const cardsIn = (page: Page, column: string): Promise<(string | null)[]> =>
  page.getByTestId(`list-${column}`).locator('> [data-nowo-ui-sortable-item]').evaluateAll((els) => els.map((el) => el.getAttribute('data-nowo-ui-sortable-item')));

async function glide(send: Awaited<ReturnType<typeof touch>>, from: Point, to: Point, steps = 10): Promise<void> {
  for (let i = 1; i <= steps; i++) {
    await send('touchMove', { x: from.x + ((to.x - from.x) * i) / steps, y: from.y + ((to.y - from.y) * i) / steps });
  }
}

test.describe('sortable with touch input (2.35)', () => {
  test.beforeAll(async ({ browser }) => {
    const page = await browser.newPage();
    await page.goto('/kit/sortable');
    await page.waitForTimeout(1500);
    await page.close();
  });

  test('a swipe that starts on a card scrolls the list and starts no drag', async ({ page }) => {
    await page.goto('/kit/sortable');
    const send = await touch(page);
    const list = page.getByTestId('list-todo');
    expect(await list.evaluate((el) => el.scrollHeight > el.clientHeight)).toBe(true);
    const from = await centre(page.getByTestId('card-t3'));
    await send('touchStart', from);
    await glide(send, from, { x: from.x, y: from.y - 120 });
    await send('touchEnd');
    await expect.poll(() => list.evaluate((el) => el.scrollTop)).toBeGreaterThan(20);
    await expect(page.locator('.nowo-ui-sortable__ghost')).toHaveCount(0);
    await expect(page.getByTestId('log')).not.toContainText('start');
  });

  test('a long press drags a whole card, the board scrolls sideways at the screen edge, and it lands in another column', async ({ page }) => {
    await page.goto('/kit/sortable');
    const send = await touch(page);
    const board = page.getByTestId('board');
    const list = page.getByTestId('list-todo');
    const from = await centre(page.getByTestId('card-t1'));
    const width = page.viewportSize()!.width;
    const pageScroll = await page.evaluate(() => window.scrollY);
    await send('touchStart', from);
    await page.waitForTimeout(350);
    await expect(page.locator('.nowo-ui-sortable__ghost')).toHaveCount(1);
    // On a phone the next column is off-screen: hold at the edge until the board has brought it in.
    await glide(send, from, { x: width - 4, y: from.y });
    await expect(page.locator('.nowo-ui-sortable__placeholder')).toBeVisible();
    await expect.poll(() => board.evaluate((el) => el.scrollLeft), { timeout: 5_000 }).toBeGreaterThan(200);
    // The finger moved but the gesture never scrolled what the drag refuses to: the list and the page stayed.
    expect(await list.evaluate((el) => el.scrollTop)).toBe(0);
    expect(await page.evaluate(() => window.scrollY)).toBe(pageScroll);
    await send('touchEnd');
    await expect(page.locator('.nowo-ui-sortable__ghost')).toHaveCount(0);
    expect(await cardsIn(page, 'todo')).not.toContain('t1');
    await expect(page.getByTestId('log')).toContainText(/moved cards t1 (doing|done) todo/);
  });

  test('a handle drags at once, with no long press and no page scroll', async ({ page }) => {
    await page.goto('/kit/sortable');
    const send = await touch(page);
    const from = await centre(page.getByTestId('step-handle-draft'));
    const to = await centre(page.getByTestId('step-publish'));
    const before = await page.evaluate(() => window.scrollY);
    await send('touchStart', from);
    await glide(send, from, { x: to.x + 20, y: to.y });
    await expect(page.locator('.nowo-ui-sortable__ghost')).toHaveCount(1);
    await send('touchEnd');
    expect(await page.evaluate(() => window.scrollY)).toBe(before);
    const order = await page.getByTestId('steps').locator('> li').evaluateAll((els) => els.map((el) => el.getAttribute('data-nowo-ui-sortable-item')));
    expect(order).toEqual(['review', 'publish', 'draft']);
  });

  test('a tap on a link in a card follows it', async ({ page }) => {
    await page.goto('/kit/sortable');
    await page.getByTestId('link-t2').tap();
    await expect(page).toHaveURL(/#t2$/);
    await expect(page.getByTestId('log')).not.toContainText('start');
  });

  test('during a long-press drag, holding near the list edge auto-scrolls it', async ({ page }) => {
    await page.goto('/kit/sortable');
    const send = await touch(page);
    const list = page.getByTestId('list-todo');
    const box = (await list.boundingBox())!;
    const from = await centre(page.getByTestId('card-t1'));
    await send('touchStart', from);
    await page.waitForTimeout(350);
    await glide(send, from, { x: box.x + box.width / 2, y: box.y + box.height - 4 });
    await expect.poll(() => list.evaluate((el) => el.scrollTop), { timeout: 5_000 }).toBeGreaterThan(40);
    await send('touchEnd');
    await expect(page.locator('.nowo-ui-sortable__ghost')).toHaveCount(0);
  });
});
