import { expect, test, type Page } from '@playwright/test';

/**
 * UiKit 2.29 dialog sizes (/kit/dialogs): medium min(40rem, 100vw - 2rem) × min(36rem, 100dvh - 2rem), wide
 * min(56rem, …) × min(52rem, …); a plain yes / no keeps its natural height; the body scrolls, the footer stays in view;
 * at 390 px the screen width with the 16 px gutter and no horizontal scroll. Writes screenshots when
 * DIALOG_SHOTS_DIR is set.
 */
const shots = process.env.DIALOG_SHOTS_DIR;

async function open(page: Page, trigger: string, dialog: string): Promise<DOMRect> {
  await page.goto('/kit/dialogs');
  await page.getByTestId(trigger).click();
  const el = page.locator(`dialog#${dialog}`);
  await expect(el).toBeVisible();
  return el.evaluate((node) => node.getBoundingClientRect().toJSON() as DOMRect);
}

async function shot(page: Page, name: string): Promise<void> {
  if (shots) {
    await page.screenshot({ path: `${shots}/${name}.png` });
  }
}

for (const [width, height] of [[1280, 900], [390, 844]] as const) {
  test.describe(`dialog sizes at ${width} px`, () => {
    test.use({ viewport: { width, height } });

    const md = { w: Math.min(640, width - 32), h: Math.min(576, height - 32) };
    const wide = { w: Math.min(896, width - 32), h: Math.min(832, height - 32) };

    test('medium form', async ({ page }) => {
      const box = await open(page, 'open-medium', 'medium-dialog');
      expect(Math.round(box.width)).toBe(md.w);
      expect(Math.round(box.height)).toBe(md.h);
      await shot(page, `medium-${width}`);
    });

    test('wide table', async ({ page }) => {
      const box = await open(page, 'open-wide', 'wide-dialog');
      expect(Math.round(box.width)).toBe(wide.w);
      expect(Math.round(box.height)).toBe(wide.h);
      await expect(page.locator('#wide-dialog .nowo-ui-page-status')).toHaveText('Synced');
      await shot(page, `wide-${width}`);
    });

    test('plain yes / no keeps its natural height', async ({ page }) => {
      const box = await open(page, 'open-confirm', 'confirm-dialog');
      expect(Math.round(box.width)).toBe(md.w);
      expect(box.height).toBeLessThan(md.h);
      await shot(page, `confirm-${width}`);
      await page.keyboard.press('Escape');
      await expect(page.locator('dialog#confirm-dialog')).toBeHidden();
      await expect(page.getByTestId('open-confirm')).toBeFocused();
    });

    test('long form: the body scrolls, the footer stays', async ({ page }) => {
      const box = await open(page, 'open-long', 'long-dialog');
      expect(Math.round(box.width)).toBe(md.w);
      expect(Math.round(box.height)).toBe(md.h);
      const body = page.locator('#long-dialog > .nowo-ui-dialog__body');
      expect(await body.evaluate((node) => node.scrollHeight > node.clientHeight)).toBe(true);
      await expect(page.getByTestId('long-save')).toBeInViewport();
      await body.evaluate((node) => node.scrollTo(0, node.scrollHeight));
      await expect(page.getByTestId('long-save')).toBeInViewport();
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
      await shot(page, `long-form-${width}`);
      // 2.29.1: a full-width field (the Notes textarea) stays inside the body's content box (border-box).
      const notes = await page.locator('#long-dialog textarea').evaluate((node) => {
        const host = node.closest('.nowo-ui-dialog__body') as HTMLElement;
        const box = host.getBoundingClientRect();
        return { right: node.getBoundingClientRect().right, limit: box.right - parseFloat(getComputedStyle(host).paddingRight) + 0.5 };
      });
      expect(notes.right).toBeLessThanOrEqual(notes.limit);
    });

    test('saving the long form posts, redirects and shows the status (2.29.1)', async ({ page }) => {
      await open(page, 'open-long', 'long-dialog');
      await page.getByTestId('long-save').click();
      await expect(page).toHaveURL(/\/kit\/dialogs\?submitted=1$/);
      await expect(page.getByRole('status')).toHaveText('Submitted.');
    });
  });
}

test('remote form dialog shots', async ({ page }) => {
  test.skip(!shots, 'screenshots only');
  for (const [width, height] of [[1280, 900], [390, 844]] as const) {
    await page.setViewportSize({ width, height });
    await page.goto('/kit/remote-form');
    await page.getByTestId('destination-add').click();
    await expect(page.getByTestId('destination-label')).toBeVisible();
    await shot(page, `remote-form-${width}`);
  }
});
