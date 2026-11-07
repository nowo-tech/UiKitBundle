import { expect, test, type Page } from '@playwright/test';

/**
 * UiKit dialog sizes (/kit/dialogs): widths medium min(40rem, 100vw - 2rem) and wide min(56rem, …) (2.29). 2.31
 * (dialog rule v2): the height is the content's, capped at min(36rem | 52rem, 100dvh - 2rem); only the body scrolls,
 * the footer stays in view; `?height=size` (= theme.dialog.height: size, --nowo-ui-dialog-fill: 1) gives the 2.29 /
 * 2.30 size's height back. At 390 px the screen width with the 16 px gutter and no horizontal scroll. The JS confirm
 * (askConfirm) has a close button (×). Writes screenshots when DIALOG_SHOTS_DIR is set.
 */
const shots = process.env.DIALOG_SHOTS_DIR;

async function open(page: Page, trigger: string, dialog: string, query = ''): Promise<DOMRect> {
  await page.goto(`/kit/dialogs${query}`);
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
    // Sizes are read while a dialog could still be scaling in: no scale under reduced motion.
    test.use({ viewport: { width, height }, reducedMotion: 'reduce' });

    const md = { w: Math.min(640, width - 32), h: Math.min(576, height - 32) };
    const wide = { w: Math.min(896, width - 32), h: Math.min(832, height - 32) };

    /** The body's content fits (no scroll) and the dialog is header + body + footer, no empty area (2.31). */
    async function fitsContent(page: Page, dialog: string): Promise<void> {
      const parts = await page.locator(`#${dialog}`).evaluate((node) => {
        const height = (selector: string): number => (node.querySelector(`:scope > ${selector}`) as HTMLElement | null)?.getBoundingClientRect().height ?? 0;
        const body = node.querySelector(':scope > .nowo-ui-dialog__body') as HTMLElement;
        return {
          dialog: node.getBoundingClientRect().height,
          sum: height('.nowo-ui-dialog__header') + height('.nowo-ui-dialog__body') + height('.nowo-ui-dialog__footer'),
          scrolls: body.scrollHeight > body.clientHeight + 1,
        };
      });
      expect(parts.scrolls).toBe(false);
      expect(Math.abs(parts.dialog - parts.sum)).toBeLessThanOrEqual(2);
    }

    test('medium form: the content height (2.31)', async ({ page }) => {
      const box = await open(page, 'open-medium', 'medium-dialog');
      expect(Math.round(box.width)).toBe(md.w);
      expect(box.height).toBeLessThan(md.h);
      await fitsContent(page, 'medium-dialog');
      await shot(page, `medium-${width}`);
    });

    test('medium form: theme.dialog.height size keeps the size height (2.29 / 2.30)', async ({ page }) => {
      const box = await open(page, 'open-medium', 'medium-dialog', '?height=size');
      expect(Math.round(box.width)).toBe(md.w);
      expect(Math.round(box.height)).toBe(md.h);
      await shot(page, `medium-size-mode-${width}`);
      const confirm = await open(page, 'open-confirm', 'confirm-dialog', '?height=size');
      expect(confirm.height).toBeLessThan(md.h);
    });

    test('wide table', async ({ page }) => {
      const box = await open(page, 'open-wide', 'wide-dialog');
      expect(Math.round(box.width)).toBe(wide.w);
      expect(Math.round(box.height)).toBeLessThanOrEqual(wide.h);
      if (Math.round(box.height) < wide.h) {
        await fitsContent(page, 'wide-dialog');
      }
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

    test('long form: capped at the size height, the body scrolls, the footer stays', async ({ page }) => {
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

    test('View\\Confirm on the dialog anatomy: tinted header and footer, content height (2.31)', async ({ page }) => {
      await page.goto('/kit/dialogs');
      await page.getByTestId('open-view-confirm').click();
      const dialog = page.locator('dialog#archive-monitor');
      await expect(dialog).toBeVisible();
      const parts = await dialog.evaluate((node) => {
        const panel = node.querySelector('.nowo-ui-confirm__panel') as HTMLElement;
        const pick = (selector: string) => {
          const el = panel.querySelector(`:scope > ${selector}`) as HTMLElement;
          const r = el.getBoundingClientRect();
          return { top: r.top, bottom: r.bottom, height: r.height, bg: getComputedStyle(el).backgroundColor, border: getComputedStyle(el).borderBottomWidth };
        };
        return {
          dialog: node.getBoundingClientRect().toJSON() as DOMRect,
          panelBg: getComputedStyle(panel).backgroundColor,
          header: pick('.nowo-ui-confirm__header'),
          body: pick('.nowo-ui-confirm__body'),
          footer: pick('.nowo-ui-confirm__footer'),
        };
      });
      expect(Math.round(parts.dialog.width)).toBe(md.w);
      expect(parts.dialog.height).toBeLessThan(md.h);
      expect(parts.header.bg).not.toBe(parts.panelBg);
      expect(parts.footer.bg).toBe(parts.header.bg);
      expect(parts.header.border).toBe('1px');
      expect(parts.header.bottom).toBeLessThanOrEqual(parts.body.top + 1);
      expect(parts.body.bottom).toBeLessThanOrEqual(parts.footer.top + 1);
      await expect(dialog.locator('.nowo-ui-confirm__header').getByRole('button', { name: 'Close' })).toBeVisible();
      const buttons = dialog.locator('.nowo-ui-confirm__footer button');
      await expect(buttons).toHaveText(['Cancel', 'Archive']);
      await shot(page, `view-confirm-${width}`);
      await dialog.locator('.nowo-ui-confirm__header').getByRole('button', { name: 'Close' }).click();
      await expect(dialog).toBeHidden();

      await page.getByTestId('open-view-confirm-expect').click();
      const danger = page.locator('dialog#delete-monitor');
      await expect(danger).toBeVisible();
      const submit = danger.locator('.nowo-ui-confirm__submit');
      await expect(submit).toHaveClass(/nowo-ui-btn--solid/);
      await expect(submit).toBeDisabled();
      await danger.locator('#delete-monitor-expected').fill('api-health');
      await expect(submit).toBeEnabled();
      const box = await danger.evaluate((node) => node.getBoundingClientRect().toJSON() as DOMRect);
      expect(Math.round(box.width)).toBe(md.w);
      expect(box.height).toBeLessThan(md.h);
      await expect(submit).toBeInViewport();
      await shot(page, `view-confirm-expect-${width}`);
      await page.keyboard.press('Escape');
      await expect(danger).toBeHidden();
    });

    test('JS confirm (askConfirm): natural height, close button (×) cancels (2.31)', async ({ page }) => {
      await page.goto('/kit/dialogs');
      await page.getByTestId('open-ask-confirm').click();
      const dialog = page.locator('dialog.nowo-ui-confirm[open]');
      await expect(dialog).toBeVisible();
      await expect(dialog.locator('h2')).toHaveText('Reset the demo filters?');
      const box = await dialog.evaluate((node) => node.getBoundingClientRect().toJSON() as DOMRect);
      expect(Math.round(box.width)).toBe(md.w);
      expect(box.height).toBeLessThan(md.h);
      const close = dialog.getByRole('button', { name: 'Close' });
      await expect(close).toBeVisible();
      await expect(dialog.getByRole('button', { name: 'Cancel' })).toBeFocused();
      // The × sits on the title row, at the right edge of the panel.
      const xBox = (await close.boundingBox())!;
      expect(xBox.x + xBox.width).toBeGreaterThan(box.x + box.width - 48);
      expect(xBox.y).toBeLessThan(box.y + 48);
      await shot(page, `ask-confirm-${width}`);
      await close.click();
      await expect(page.locator('dialog.nowo-ui-confirm:not([id])')).toHaveCount(0); // askConfirm's dialog is removed (the View\Confirm ones stay)
      await expect(page).toHaveURL(/\/kit\/dialogs$/);
    });
  });
}

test('remote form dialog shots', async ({ page }) => {
  test.skip(!shots, 'screenshots only');
  for (const [width, height] of [[1280, 900], [390, 844]] as const) {
    await page.setViewportSize({ width, height });
    await page.goto('/kit/remote-form');
    await expect(async () => {
      await page.getByTestId('destination-add').click();
      await expect(page.getByTestId('destination-label')).toBeVisible({ timeout: 2000 });
    }).toPass();
    await shot(page, `remote-form-${width}`);
  }
});
