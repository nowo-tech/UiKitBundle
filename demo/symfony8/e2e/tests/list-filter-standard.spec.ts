import { test, expect } from '@playwright/test';

/**
 * UiKit 2.72 Beacon filter standard on /page-contract/orders/filter-standard: icon-only Filter / Clear filters squares
 * at field height, "N per page" compact right before them, the rarer filters under "More filters" (open with a count
 * while one is set), and on phones the sheet with an icon close button, light dismiss and sticky actions.
 */
const URL = '/page-contract/orders/filter-standard';

test.describe('list filters standard (2.72)', () => {
  test('desktop bar: icon-only actions at field height, compact pager, closed fold', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto(URL);
    const form = page.locator('form[data-nowo-ui-filters]');
    const submit = form.getByRole('button', { name: 'Filter', exact: true });
    const clear = form.getByRole('link', { name: 'Clear filters' });
    await expect(submit).toHaveClass(/nowo-ui-filter-bar__action--icon-only/);
    await expect(clear).toHaveClass(/nowo-ui-filter-bar__action--icon-only/);
    const field = await form.locator('#filters_q').boundingBox();
    const box = await submit.boundingBox();
    expect(field && box).toBeTruthy();
    expect(Math.abs(box!.height - field!.height)).toBeLessThanOrEqual(1);
    expect(Math.abs(box!.width - box!.height)).toBeLessThanOrEqual(1);

    // the pager is the first item of the actions group, at its natural width
    const actions = form.locator('.nowo-ui-filters__actions');
    await expect(actions.locator(':scope > .nowo-ui-filters__pager.demo-filters__pager')).toHaveCount(1);
    const pager = await actions.locator('#filters_per_page').boundingBox();
    expect(pager!.width).toBeLessThan(200);
    expect(pager!.x).toBeLessThan(box!.x);
    await expect(actions.locator('#filters_per_page')).toHaveAccessibleName('Results per page');

    const more = form.locator('details.nowo-ui-filters-more');
    await expect(more).not.toHaveAttribute('open', /.*/);
    await expect(form.locator('#filters_channel')).toBeHidden();
    await more.locator('summary').click();
    await expect(form.locator('#filters_channel')).toBeVisible();
  });

  test('a hidden filter set keeps the fold open with its count', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto(`${URL}?filters%5Bchannel%5D=web&filters%5Bgift%5D=1`);
    const more = page.locator('details.nowo-ui-filters-more');
    await expect(more).toHaveAttribute('open', '');
    await expect(more.locator('[data-nowo-ui-filters-more-count]')).toContainText('2');
    await expect(page.locator('#filters_channel')).toHaveValue('web');
  });

  test('phone sheet: icon close, backdrop light dismiss, sticky actions, no nested card', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 700 });
    await page.goto(URL);
    const toggle = page.locator('.nowo-ui-filter-sheet--list [data-nowo-ui-filter-sheet-open]');
    await toggle.click();
    const sheet = page.getByRole('dialog', { name: 'Filters' });
    await expect(sheet).toBeVisible();
    const close = sheet.getByRole('button', { name: 'Close' });
    await expect(close).toBeVisible();
    await expect(close).toHaveClass(/nowo-ui-filter-sheet__close/);
    expect((await close.textContent())?.trim()).toBe('Close'); // visually hidden text only, the X is the icon
    const form = sheet.locator('form[data-nowo-ui-filters]');
    expect(await form.evaluate((f) => [getComputedStyle(f).borderTopWidth, getComputedStyle(f).boxShadow])).toEqual(['0px', 'none']);
    const actions = sheet.locator('.nowo-ui-filters__actions');
    expect(await actions.evaluate((a) => getComputedStyle(a).position)).toBe('sticky');
    await expect(actions.locator('#filters_per_page')).toBeVisible();
    // the actions stay in view at the bottom of the sheet
    const dialogBox = (await sheet.boundingBox())!;
    const actionsBox = (await actions.boundingBox())!;
    expect(actionsBox.y + actionsBox.height).toBeLessThanOrEqual(dialogBox.y + dialogBox.height + 1);

    // a click on the dimmed backdrop (above the sheet) closes it
    await page.mouse.click(195, 10);
    await expect(sheet).toBeHidden();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');

    await toggle.click();
    await close.click();
    await expect(sheet).toBeHidden();
  });
});
