import { test, expect, type Locator, type Page } from '@playwright/test';

/**
 * Card mode of a table at phone width: a cell stays inside its row, and the label, drawn over the row's start, takes no taps. A wide
 * link and a wide button are added to a cell by script, because the page forbids style attributes.
 */
const PHONE = { width: 390, height: 844 };

async function wideControls(page: Page): Promise<{ cell: Locator; link: Locator; button: Locator }> {
  await page.route('**/_wdt/**', (route) => route.abort());
  await page.setViewportSize(PHONE);
  await page.goto('/page-contract/orders');
  // The label over the whole cell, the worst case of a cell whose content runs under it. This page's own layout does not overlap.
  await page.addStyleTag({
    content: '.e2e-wide { display: block; width: 100%; } td[data-label] { position: relative; } td[data-label]::before { inset: 0 !important; width: auto !important; z-index: 1; }',
  });
  const cell = page.locator('tbody tr').first().locator('td[data-label="Customer"]');
  const other = page.locator('tbody tr').first().locator('td[data-label="Status"]');
  // First in its cell, on the label's line.
  await cell.evaluate((el) => {
    const link = document.createElement('a');
    link.className = 'e2e-wide';
    link.href = '#tapped';
    link.textContent = 'Customer link';
    el.prepend(link);
  });
  await other.evaluate((el) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'e2e-wide';
    button.textContent = 'Status button';
    el.prepend(button);
  });
  return { cell, link: cell.locator('a.e2e-wide'), button: other.locator('button.e2e-wide') };
}

/** What a tap would hit at the start, the middle and the end of an element. */
function hits(control: Locator): Promise<boolean[]> {
  return control.evaluate((el) => {
    const box = el.getBoundingClientRect();
    const y = box.top + box.height / 2;
    return [box.left + 1, box.left + box.width / 2, box.right - 1].map((x) => document.elementFromPoint(x, y) === el);
  });
}

test.describe('table cards at phone width', () => {
  test('a link and a button take taps across their whole width', async ({ page }) => {
    const { link, button } = await wideControls(page);
    await button.scrollIntoViewIfNeeded();
    expect(await hits(button)).toEqual([true, true, true]);
    await link.scrollIntoViewIfNeeded();
    expect(await hits(link)).toEqual([true, true, true]);
    const box = (await link.boundingBox())!;
    await page.mouse.click(box.x + 1, box.y + box.height / 2);
    await expect(page).toHaveURL(/#tapped$/);
  });

  test('a cell stays inside its row and the table does not overflow', async ({ page }) => {
    const { cell } = await wideControls(page);
    const sizes = await cell.evaluate((el) => ({
      cell: el.getBoundingClientRect().right,
      row: el.closest('tr')!.getBoundingClientRect().right,
      sizing: getComputedStyle(el).boxSizing,
      events: getComputedStyle(el, '::before').pointerEvents,
    }));
    expect(sizes.sizing).toBe('border-box');
    expect(sizes.events).toBe('none');
    expect(sizes.cell).toBeLessThanOrEqual(sizes.row + 0.5);
  });

  test('on a desktop the table keeps its own cells', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto('/page-contract/orders');
    const cell = page.locator('tbody tr').first().locator('td[data-label="Customer"]');
    expect(await cell.evaluate((el) => getComputedStyle(el).display)).toBe('table-cell');
    expect(await cell.evaluate((el) => getComputedStyle(el, '::before').content)).toBe('none');
  });
});
