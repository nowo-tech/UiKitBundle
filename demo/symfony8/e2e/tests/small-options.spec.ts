import { expect, test } from '@playwright/test';

/**
 * UiKit 2.34 small options (/kit/small-options): attributes reach their elements, the back arrow sits before the title
 * with the × at the end, the remote form dialog opens as a full-screen side panel on a phone, the foot of a card table
 * stays inside the page, and window.nowoUiAskConfirm answers.
 */
test.describe('UiKit 2.34 small options', () => {
  test('row, foot, tree and meter attributes', async ({ page }) => {
    await page.goto('/kit/small-options');
    await expect(page.getByTestId('demo-row')).toHaveCount(2);
    await expect(page.getByTestId('demo-row').first()).toHaveAttribute('data-task-id', '1');
    await expect(page.getByTestId('demo-foot')).toContainText('Total');
    await expect(page.getByTestId('tree-link')).toHaveAttribute('data-title', 'JavaScript: plan');
    await expect(page.getByTestId('tree-leading').first()).toHaveText('[x]');
    await expect(page.getByTestId('demo-meter')).toHaveAttribute('max', '5');
    await expect(page.getByTestId('demo-meter')).toHaveAttribute('value', '3');
  });

  test('back arrow before the title, close button at the end', async ({ page }) => {
    await page.goto('/kit/small-options');
    await page.getByTestId('open-back').click();
    const header = page.getByTestId('demo-header');
    const back = header.locator('.nowo-ui-dialog__back');
    await expect(back).toHaveAttribute('aria-label', 'Back');
    const backBox = (await back.boundingBox())!;
    const titleBox = (await header.locator('h2').boundingBox())!;
    const closeBox = (await header.locator('.nowo-ui-dialog__close').boundingBox())!;
    const headerBox = (await header.boundingBox())!;
    expect(backBox.x + backBox.width).toBeLessThanOrEqual(titleBox.x);
    expect(titleBox.x + titleBox.width).toBeLessThanOrEqual(closeBox.x);
    expect(closeBox.x + closeBox.width).toBeGreaterThan(headerBox.x + headerBox.width - 40);
    await expect(page.getByTestId('demo-footer')).toHaveClass(/demo-actions/);
  });

  test('the remote form side panel takes the whole phone screen', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 800 });
    await page.goto('/kit/small-options');
    await page.getByTestId('open-panel').click();
    const dialog = page.locator('#demo-panel');
    await expect(dialog).toHaveClass(/nowo-ui-dialog--end/);
    await expect(dialog).toBeVisible();
    await expect.poll(async () => (await dialog.boundingBox())!.x).toBe(0);
    const box = (await dialog.boundingBox())!;
    expect(box.width).toBe(390);
    expect(box.height).toBe(800);
  });

  test('the foot of a card table stays inside a phone screen', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 700 });
    await page.goto('/kit/small-options');
    await expect(page.getByTestId('demo-foot')).toBeVisible();
    const foot = (await page.getByTestId('demo-foot').boundingBox())!;
    expect(foot.x + foot.width).toBeLessThanOrEqual(320);
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(320);
  });

  test('window.nowoUiAskConfirm answers', async ({ page }) => {
    await page.goto('/kit/small-options');
    const answer = page.evaluate(() => window.nowoUiAskConfirm!('Delete this link?', { danger: true, confirm: 'Delete', cancel: 'Keep' }));
    await page.locator('dialog.nowo-ui-confirm').getByRole('button', { name: 'Delete', exact: true }).click();
    expect(await answer).toBe(true);
  });

  test('window.nowoUiAskConfirm is not moved by an injected <img name="body"> (audit HG-I7)', async ({ page }) => {
    await page.goto('/kit/small-options');
    await page.evaluate(() => {
      for (const name of ['body', 'documentElement', 'createElement']) {
        const img = Document.prototype.createElement.call(document, 'img');
        img.setAttribute('name', name);
        Object.getOwnPropertyDescriptor(Document.prototype, 'body')!.get!.call(document).append(img);
      }
    });
    const answer = page.evaluate(() => window.nowoUiAskConfirm!('Delete this link?', { confirm: 'Delete', cancel: 'Keep' }));
    const dialog = page.locator('dialog.nowo-ui-confirm');
    await expect(dialog).toBeVisible();
    await dialog.getByRole('button', { name: 'Delete', exact: true }).click();
    expect(await answer).toBe(true);
  });
});
