import { test, expect, type Locator, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * UiKit 2.52.2 on /kit/page-actions-dialogs (WCAG 2.4.3 Focus Order): dialogs opened from the page-actions menu, whose
 * items forward their click to hidden dialog triggers (symfony-beacon's `opens:` page actions). However the dialog
 * closes — Escape, Cancel, Confirm, ×, backdrop, a saved remote form — focus lands on the page-actions toggle (the
 * item is in the closed menu), never on the hidden trigger or the body. A visible trigger still gets focus back.
 * Confirm dialog (confirm-dialog peer), remote form dialog (remote-form-dialog peer), kit dialog (nowo-ui-dialog.js);
 * desktop and phone widths; no CSP violation; axe on the page and the open dialogs.
 */
/**
 * CSP violations of inline style attributes and handlers (style-src-attr / script-src-attr 'none'): what kit code
 * could cause. The dev server's own hot-reload inline <script> (no nonce) is demo noise, not counted.
 */
async function watchCsp(page: Page): Promise<string[]> {
  const violations: string[] = [];
  page.on('console', (msg) => {
    if (/Content Security Policy/i.test(msg.text()) && /src-attr/.test(msg.text())) violations.push(msg.text());
  });
  return violations;
}

/** axe without the dev web debug toolbar (outside the page's landmarks; not part of the demo). */
const axe = async (page: Page): Promise<AxeBuilder> => {
  // The pointer off the dialog buttons: a hover colour is not what the page shows at rest.
  await page.mouse.move(1, 1);
  await page.evaluate(() => Promise.all(document.getAnimations().map((animation) => animation.finished.catch(() => undefined))));
  return new AxeBuilder({ page }).exclude('.sf-toolbar');
};

const toggle = (page: Page): Locator => page.getByTestId('page-actions').locator('[data-nowo-ui-popover-trigger]');

async function openFromMenu(page: Page, item: string, dialog: string): Promise<Locator> {
  await toggle(page).click();
  await page.getByTestId(item).click();
  const box = page.getByTestId(dialog);
  await expect(box).toBeVisible();
  return box;
}

/** The focused element: never the body, never a hidden element. */
async function focusedTestId(page: Page): Promise<string | null> {
  return page.evaluate(() => {
    const el = document.activeElement;
    if (!(el instanceof HTMLElement) || el === document.body) return null;
    return el.dataset.testid ?? (el.matches('[data-nowo-ui-popover-trigger]') ? 'page-actions-toggle' : el.id || el.tagName);
  });
}

async function expectToggleFocused(page: Page): Promise<void> {
  await expect(toggle(page)).toBeFocused();
  expect(await focusedTestId(page)).toBe('page-actions-toggle');
}

/** A click on the backdrop: a corner of the viewport, outside the centred dialog box. */
async function clickBackdrop(page: Page): Promise<void> {
  await page.mouse.click(4, 4);
}

test.describe('focus return from page-actions dialogs (2.52.2)', () => {
  test.use({ reducedMotion: 'reduce' });

  test.beforeEach(async ({ page }) => {
    await page.route('**/_wdt/**', (route) => route.abort());
  });

  test('confirm dialog: Escape, Cancel, Confirm, × and backdrop return focus to the page-actions toggle', async ({ page }) => {
    const csp = await watchCsp(page);
    await page.goto('/kit/page-actions-dialogs');
    const closers: Array<[string, (dialog: Locator) => Promise<void>]> = [
      ['Escape', async () => page.keyboard.press('Escape')],
      ['Cancel', async () => page.getByTestId('pa-archive-cancel').click()],
      ['Confirm', async () => page.getByTestId('pa-archive-confirm').click()],
      ['×', async (dialog) => dialog.locator('.nowo-ui-dialog__close').click()],
      [
        'backdrop',
        async () => {
          // The peer ignores the backdrop for 500 ms after opening (the opening gesture).
          await page.waitForTimeout(600);
          await clickBackdrop(page);
        },
      ],
    ];
    for (const [how, close] of closers) {
      const dialog = await openFromMenu(page, 'pa-archive', 'pa-archive-dialog');
      await close(dialog);
      await expect(dialog, how).toBeHidden();
      await expectToggleFocused(page);
    }
    expect(csp).toEqual([]);
  });

  test('remote form dialog: Escape, ×, Cancel and a saved form return focus to the page-actions toggle', async ({ page }) => {
    const csp = await watchCsp(page);
    await page.goto('/kit/page-actions-dialogs');
    const closers: Array<[string, (dialog: Locator) => Promise<void>]> = [
      ['Escape', async () => page.keyboard.press('Escape')],
      ['×', async (dialog) => dialog.locator('header [data-nowo-ui-dialog-close]').click()],
      ['Cancel', async () => page.getByTestId('destination-cancel').click()],
      [
        'saved',
        async () => {
          await page.getByTestId('destination-label').fill('Ops channel');
          await page.getByTestId('destination-save').click();
          await expect(page.getByTestId('status')).toHaveText('Saved.');
        },
      ],
    ];
    for (const [how, close] of closers) {
      const dialog = await openFromMenu(page, 'pa-destination', 'pa-destination-dialog');
      await expect(page.getByTestId('destination-label')).toBeVisible();
      await close(dialog);
      await expect(dialog, how).toBeHidden();
      await expectToggleFocused(page);
    }
    await expect(page).toHaveURL(/\/kit\/page-actions-dialogs$/);
    expect(csp).toEqual([]);
  });

  test('kit dialog: Escape, Close and backdrop return focus to the page-actions toggle', async ({ page }) => {
    await page.goto('/kit/page-actions-dialogs');
    const closers: Array<[string, () => Promise<void>]> = [
      ['Escape', async () => page.keyboard.press('Escape')],
      ['Close', async () => page.getByTestId('pa-invoices-close').click()],
      ['backdrop', async () => clickBackdrop(page)],
    ];
    for (const [how, close] of closers) {
      const dialog = await openFromMenu(page, 'pa-invoices', 'pa-invoices-dialog');
      await close();
      await expect(dialog, how).toBeHidden();
      await expectToggleFocused(page);
    }
  });

  test('a toggle that never took focus (Safari / Firefox on macOS do not focus a clicked button): every dialog type', async ({ page }) => {
    await page.goto('/kit/page-actions-dialogs');
    const cases: Array<[string, string, () => Promise<void>]> = [
      ['pa-archive', 'pa-archive-dialog', async () => page.getByTestId('pa-archive-cancel').click()],
      ['pa-destination', 'pa-destination-dialog', async () => page.keyboard.press('Escape')],
      ['pa-invoices', 'pa-invoices-dialog', async () => page.getByTestId('pa-invoices-close').click()],
    ];
    for (const [item, id, close] of cases) {
      // The menu opens while the body has focus, so the browser's own popover / dialog focus restoration ends on the body.
      await page.evaluate(() => {
        (document.activeElement as HTMLElement | null)?.blur();
        document.querySelector<HTMLElement>('[data-testid="page-actions"] [popover]')?.showPopover();
      });
      await page.getByTestId(item).click();
      const dialog = page.getByTestId(id);
      await expect(dialog).toBeVisible();
      if (item === 'pa-destination') {
        await expect(page.getByTestId('destination-label')).toBeVisible();
      }
      await close();
      await expect(dialog, item).toBeHidden();
      await expectToggleFocused(page);
    }
  });

  test('keyboard only: toggle, item, Escape — focus back on the toggle', async ({ page }) => {
    await page.goto('/kit/page-actions-dialogs');
    await toggle(page).focus();
    await page.keyboard.press('Enter');
    const item = page.getByTestId('pa-archive');
    await expect(item).toBeVisible();
    await item.focus();
    await page.keyboard.press('Enter');
    const dialog = page.getByTestId('pa-archive-dialog');
    await expect(dialog).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expectToggleFocused(page);
  });

  test('a visible trigger still gets focus back (confirm and remote form)', async ({ page }) => {
    await page.goto('/kit/page-actions-dialogs');
    await page.getByTestId('archive-visible').click();
    await expect(page.getByTestId('pa-archive-dialog')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.getByTestId('pa-archive-dialog')).toBeHidden();
    await expect(page.getByTestId('archive-visible')).toBeFocused();

    await page.getByTestId('destination-visible').click();
    await expect(page.getByTestId('destination-label')).toBeVisible();
    await page.getByTestId('destination-cancel').click();
    await expect(page.getByTestId('pa-destination-dialog')).toBeHidden();
    await expect(page.getByTestId('destination-visible')).toBeFocused();
  });

  // Firefox (Playwright 1.65.1) crashes its content process when a dialog of this page opens after axe scanned the
  // document (the closed <dialog> elements included): every scan runs in a page of its own, dialogs opened first.
  test('axe: the page', async ({ page }) => {
    await page.goto('/kit/page-actions-dialogs');
    expect((await (await axe(page)).analyze()).violations).toEqual([]);
  });

  test('axe: the confirm dialog opened from the menu', async ({ page }) => {
    await page.goto('/kit/page-actions-dialogs');
    await openFromMenu(page, 'pa-archive', 'pa-archive-dialog');
    expect((await (await axe(page)).include('[data-testid="pa-archive-dialog"]').analyze()).violations).toEqual([]);
    await page.keyboard.press('Escape');
    await expectToggleFocused(page);
  });

  test('axe: the remote form dialog opened from the menu', async ({ page }) => {
    await page.goto('/kit/page-actions-dialogs');
    await openFromMenu(page, 'pa-destination', 'pa-destination-dialog');
    await expect(page.getByTestId('destination-label')).toBeVisible();
    expect((await (await axe(page)).include('[data-testid="pa-destination-dialog"]').analyze()).violations).toEqual([]);
    await page.keyboard.press('Escape');
    await expectToggleFocused(page);
  });
});

test.describe('focus return from page-actions dialogs on a phone (2.52.2)', () => {
  test.use({ reducedMotion: 'reduce', viewport: { width: 390, height: 844 }, hasTouch: true });

  test('confirm and remote form: closing returns focus to the page-actions toggle', async ({ page }) => {
    await page.route('**/_wdt/**', (route) => route.abort());
    await page.goto('/kit/page-actions-dialogs');
    const confirm = await openFromMenu(page, 'pa-archive', 'pa-archive-dialog');
    await page.getByTestId('pa-archive-cancel').click();
    await expect(confirm).toBeHidden();
    await expectToggleFocused(page);

    const remote = await openFromMenu(page, 'pa-destination', 'pa-destination-dialog');
    await expect(page.getByTestId('destination-label')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(remote).toBeHidden();
    await expectToggleFocused(page);
  });
});
