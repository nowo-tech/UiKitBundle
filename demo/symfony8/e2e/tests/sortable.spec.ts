import { test, expect, type Page, type Locator } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * UiKit 2.35 sortable on /kit/sortable, in Chromium, Firefox and WebKit with a real mouse and keyboard: moves within
 * and between lists, placeholder, auto-scroll, Escape, refusal, revert, focus, live region, no CSP violation, axe.
 *
 * The last block sends synthetic PointerEvents with pointerType "touch" in all three engines. That checks the script's
 * logic only: no engine gesture recognizer runs. sortable-touch.spec.ts drives real touch input in Chromium.
 */
test.describe.configure({ timeout: 45_000 });

function watchCsp(page: Page): string[] {
  const violations: string[] = [];
  page.on('console', (msg) => {
    if (/Content Security Policy/i.test(msg.text())) violations.push(msg.text());
  });
  page.on('pageerror', (err) => violations.push(`pageerror: ${err.message}`));
  return violations;
}

const cardsIn = (page: Page, column: string): Promise<(string | null)[]> =>
  page.getByTestId(`list-${column}`).locator('> [data-nowo-ui-sortable-item]').evaluateAll((els) => els.map((el) => el.getAttribute('data-nowo-ui-sortable-item')));
const log = (page: Page): Locator => page.getByTestId('log');
const live = (page: Page): Locator => page.locator('[data-nowo-ui-sortable-live]').first();

async function centre(locator: Locator): Promise<{ x: number; y: number }> {
  await locator.scrollIntoViewIfNeeded();
  const box = await locator.boundingBox();
  if (!box) throw new Error('no box');
  return { x: box.x + box.width / 2, y: box.y + box.height / 2 };
}

/** Presses on `from` and moves in small steps over `to`, at a fraction of its height. The button stays down. */
async function dragOver(page: Page, from: Locator, to: Locator, fraction = 0.5): Promise<void> {
  const start = await centre(from);
  await page.mouse.move(start.x, start.y);
  await page.mouse.down();
  await page.mouse.move(start.x + 3, start.y + 8, { steps: 3 });
  const box = await to.boundingBox();
  if (!box) throw new Error('no box');
  await page.mouse.move(box.x + box.width / 2, box.y + box.height * fraction, { steps: 8 });
}

test.describe('sortable (2.35)', () => {
  // The first dev request warms the cache and the debug toolbar, which shifts the layout and logs its own CSP reports.
  test.beforeAll(async ({ browser }) => {
    const page = await browser.newPage();
    await page.goto('/kit/sortable');
    await page.waitForTimeout(1500);
    await page.close();
  });

  test('mouse: reorders in a list with a placeholder and a copy, and fires the events in order', async ({ page }) => {
    const csp = watchCsp(page);
    const response = await page.goto('/kit/sortable');
    // The served demo markup, not the page: the dev toolbar has its own inline styles.
    const html = await response!.text();
    expect(html.slice(html.indexOf('<main'), html.indexOf('</main>'))).not.toContain('style="');

    await dragOver(page, page.getByTestId('card-t1'), page.getByTestId('card-t3'), 0.75);
    const placeholder = page.locator('.nowo-ui-sortable__placeholder');
    await expect(placeholder).toHaveCount(1);
    await expect(placeholder).toBeVisible();
    await expect(page.locator('.nowo-ui-sortable__ghost')).toHaveCount(1);
    await expect(page.locator('.nowo-ui-sortable__ghost')).toHaveAttribute('aria-hidden', 'true');
    await expect(page.getByTestId('list-todo')).toHaveClass(/is-drop-target/);
    // The copy keeps a colour the host set through the CSSOM (cloning its style attribute raises no CSP report).
    await expect(page.locator('.nowo-ui-sortable__ghost')).toHaveCSS('border-inline-start-color', 'rgb(37, 99, 235)');
    // Nothing moved yet.
    expect((await cardsIn(page, 'todo')).slice(0, 3)).toEqual(['t1', 't2', 't3']);
    await page.mouse.up();

    await expect(placeholder).toHaveCount(0);
    await expect(page.locator('.nowo-ui-sortable__ghost')).toHaveCount(0);
    expect((await cardsIn(page, 'todo')).slice(0, 3)).toEqual(['t2', 't3', 't1']);
    await expect(log(page)).toContainText('start cards t1 todo 0 pointer');
    await expect(log(page)).toContainText('move cards t1 todo todo 2 0 pointer');
    await expect(log(page)).toContainText('moved cards t1 todo todo 2 0 pointer');
    expect(csp).toEqual([]);
  });

  test('mouse: moves between lists, also to an empty list by its column', async ({ page }) => {
    const csp = watchCsp(page);
    await page.goto('/kit/sortable');
    await dragOver(page, page.getByTestId('card-t1'), page.getByTestId('card-d2'), 0.1);
    await page.mouse.up();
    expect(await cardsIn(page, 'doing')).toEqual(['d1', 't1', 'd2']);
    await expect(log(page)).toContainText('moved cards t1 doing todo 1 0 pointer');

    // "Done" is empty: its header is part of the drop zone.
    await dragOver(page, page.getByTestId('card-t2'), page.getByTestId('column-done').locator('h2'));
    await page.mouse.up();
    expect(await cardsIn(page, 'done')).toEqual(['t2']);
    expect(csp).toEqual([]);
  });

  test('mouse: columns move by their handle, and cards go with them', async ({ page }) => {
    await page.goto('/kit/sortable');
    await dragOver(page, page.getByTestId('handle-done'), page.getByTestId('column-todo'), 0.5);
    const box = await page.getByTestId('column-todo').boundingBox();
    await page.mouse.move(box!.x + 5, box!.y + 20, { steps: 4 });
    await page.mouse.up();
    const order = await page.getByTestId('board').locator('> [data-nowo-ui-sortable-item]').evaluateAll((els) => els.map((el) => el.getAttribute('data-nowo-ui-sortable-item')));
    expect(order).toEqual(['done', 'todo', 'doing']);
    await expect(log(page)).toContainText('moved columns done board board 0 2 pointer');
    expect((await cardsIn(page, 'todo')).length).toBe(12);
  });

  test('mouse: a press on a card link with no movement still follows it; a drag from it does not', async ({ page }) => {
    await page.goto('/kit/sortable');
    await dragOver(page, page.getByTestId('link-t1'), page.getByTestId('card-t2'), 0.9);
    await page.mouse.up();
    await expect(page).not.toHaveURL(/#t1$/);
    expect((await cardsIn(page, 'todo')).slice(0, 2)).toEqual(['t2', 't1']);
    await page.getByTestId('link-t3').click();
    await expect(page).toHaveURL(/#t3$/);
  });

  test('mouse: dragging near the edge of a scrolling list scrolls it', async ({ page }) => {
    await page.goto('/kit/sortable');
    const list = page.getByTestId('list-todo');
    const overflow = await list.evaluate((el) => el.scrollHeight > el.clientHeight);
    expect(overflow).toBe(true);
    const box = (await list.boundingBox())!;
    const start = await centre(page.getByTestId('card-t1'));
    await page.mouse.move(start.x, start.y);
    await page.mouse.down();
    await page.mouse.move(start.x, start.y + 20, { steps: 3 });
    await page.mouse.move(box.x + box.width / 2, box.y + box.height - 4, { steps: 6 });
    await expect.poll(() => list.evaluate((el) => el.scrollTop), { timeout: 5_000 }).toBeGreaterThan(40);
    await page.mouse.up();
    const order = await cardsIn(page, 'todo');
    // The drop lands where the scrolled list was under the pointer, not at its old place.
    expect(order.indexOf('t1')).toBeGreaterThan(3);
  });

  test('Escape cancels a drag and restores the DOM', async ({ page }) => {
    await page.goto('/kit/sortable');
    const before = await cardsIn(page, 'todo');
    await dragOver(page, page.getByTestId('card-t1'), page.getByTestId('card-d2'));
    await expect(page.locator('.nowo-ui-sortable__placeholder')).toHaveCount(1);
    await page.keyboard.press('Escape');
    await expect(page.locator('.nowo-ui-sortable__placeholder, .nowo-ui-sortable__ghost')).toHaveCount(0);
    await page.mouse.up();
    expect(await cardsIn(page, 'todo')).toEqual(before);
    expect(await cardsIn(page, 'doing')).toEqual(['d1', 'd2']);
    await expect(log(page)).toContainText('cancel cards t1 todo 0 pointer escape');
    await expect(live(page)).toContainText('cancelled');
    await expect(page.getByTestId('card-t1')).toBeVisible();
  });

  test('a host refusal restores the DOM, by mouse and by keyboard', async ({ page }) => {
    await page.goto('/kit/sortable');
    await dragOver(page, page.getByTestId('card-d1'), page.getByTestId('column-done').locator('h2'));
    await page.mouse.up();
    expect(await cardsIn(page, 'doing')).toEqual(['d1', 'd2']);
    expect(await cardsIn(page, 'done')).toEqual([]);
    await expect(log(page)).toContainText('cancel cards d1 doing 0 pointer refused');

    const card = page.getByTestId('card-d1');
    await card.focus();
    await page.keyboard.press('Alt+ArrowDown');
    expect(await cardsIn(page, 'doing')).toEqual(['d1', 'd2']);
    await expect(card).toBeFocused();
    await expect(live(page)).toContainText('cannot be moved there');
  });

  test('a failed write reverts through detail.revert()', async ({ page }) => {
    await page.goto('/kit/sortable');
    await dragOver(page, page.getByTestId('card-d2'), page.getByTestId('column-done').locator('h2'));
    await page.mouse.up();
    await expect.poll(() => cardsIn(page, 'doing')).toEqual(['d1', 'd2']);
    expect(await cardsIn(page, 'done')).toEqual([]);
    await expect(log(page)).toContainText('reverted cards d2 done doing');
    await expect(live(page)).toContainText('undone');
  });

  test('keyboard: Alt+arrows move, keep focus, and speak', async ({ page }) => {
    const csp = watchCsp(page);
    await page.goto('/kit/sortable');
    const card = page.getByTestId('card-t1');
    await card.focus();
    await page.keyboard.press('Alt+ArrowDown');
    expect((await cardsIn(page, 'todo')).slice(0, 2)).toEqual(['t2', 't1']);
    await expect(card).toBeFocused();
    await expect(live(page)).toHaveAttribute('role', 'status');

    await page.keyboard.press('Alt+ArrowRight');
    expect(await cardsIn(page, 'doing')).toEqual(['d1', 't1', 'd2']);
    await expect(card).toBeFocused();
    await expect(page).toHaveURL(/\/kit\/sortable$/); // Alt+Right is not "forward" here
    await expect(log(page)).toContainText('moved cards t1 doing todo 1 1 keyboard');

    await page.keyboard.press('Alt+ArrowLeft');
    await expect(card).toBeFocused();
    expect((await cardsIn(page, 'todo')).indexOf('t1')).toBe(1);
    await expect(page).toHaveURL(/\/kit\/sortable$/); // nor "back"

    const handle = page.getByTestId('step-handle-draft');
    await handle.focus();
    await page.keyboard.press('Alt+ArrowRight');
    await expect(handle).toBeFocused();
    const steps = await page.getByTestId('steps').locator('> li').evaluateAll((els) => els.map((el) => el.getAttribute('data-nowo-ui-sortable-item')));
    expect(steps).toEqual(['review', 'draft', 'publish']);
    expect(csp).toEqual([]);
  });

  test('axe: no violations at rest and during a drag', async ({ page }) => {
    await page.goto('/kit/sortable');
    await page.getByTestId('card-t1').focus();
    const rest = await new AxeBuilder({ page }).include('[data-testid="sortable-demo"]').analyze();
    expect(rest.violations).toEqual([]);
    await dragOver(page, page.getByTestId('card-t1'), page.getByTestId('card-t3'));
    const during = await new AxeBuilder({ page }).include('[data-testid="sortable-demo"]').analyze();
    expect(during.violations).toEqual([]);
    await page.keyboard.press('Escape');
    await page.mouse.up();
  });

  test('synthetic touch events (logic only, proves nothing about the engine gesture): long press, then move and drop', async ({ page }) => {
    await page.goto('/kit/sortable');
    const a = await centre(page.getByTestId('card-t1'));
    const b = await centre(page.getByTestId('card-d2'));
    await page.evaluate(
      async ({ a, b }) => {
        const fire = (type: string, x: number, y: number): void => {
          const target = document.querySelector('[data-testid="card-t1"]')!;
          target.dispatchEvent(new PointerEvent(type, { bubbles: true, cancelable: true, pointerId: 7, pointerType: 'touch', isPrimary: true, button: type === 'pointermove' ? -1 : 0, clientX: x, clientY: y }));
        };
        fire('pointerdown', a.x, a.y);
        await new Promise((resolve) => setTimeout(resolve, 320));
        fire('pointermove', b.x, b.y);
        fire('pointerup', b.x, b.y);
      },
      { a, b },
    );
    expect((await cardsIn(page, 'doing'))).toContain('t1');
  });
});
