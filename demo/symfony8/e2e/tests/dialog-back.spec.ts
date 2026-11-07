import { test, expect, type Page } from '@playwright/test';

/**
 * A fragment's `[data-nowo-ui-dialog-back]` lifted into the header of the remote form dialog, in Chromium, Firefox and WebKit. The
 * fragments are the test's: a first one with a back button, a 422 with another, then one with none.
 */
test.describe.configure({ timeout: 45_000 });

const form = (extra: string): string => `${extra}<form method="post" action="/kit/remote-form/new"><label for="f-x">Label</label><input id="f-x" name="x"><div data-nowo-ui-dialog-actions><button type="submit" data-testid="send">Send</button></div></form>`;

async function open(page: Page, answers: { status: number; body: string }[]): Promise<void> {
  await page.route('**/_wdt/**', (route) => route.abort());
  await page.route('**/kit/remote-form/new', (route) => {
    const answer = answers.shift() ?? { status: 200, body: form('') };
    return route.fulfill({ status: answer.status, contentType: 'text/html; charset=UTF-8', body: answer.body });
  });
  await page.goto('/kit/remote-form');
  await page.getByTestId('destination-add').click();
}

test.describe('dialog back button', () => {
  test('sits in the header before the title, works by keyboard, and is replaced after a 422', async ({ page }) => {
    await open(page, [
      { status: 200, body: form('<button type="button" data-nowo-ui-dialog-back data-testid="back" aria-label="Back to the list">←</button>') },
      { status: 422, body: form('<button type="button" data-nowo-ui-dialog-back data-testid="back-2" aria-label="Back again">←</button><p data-testid="invalid">Wrong</p>') },
      { status: 422, body: form('<p data-testid="invalid-2">Still wrong</p>') },
    ]);
    const header = page.locator('#destination-dialog > header');
    const back = header.getByTestId('back');
    await expect(back).toBeVisible();
    await expect(page.locator('#destination-dialog [data-nowo-ui-dialog-body] [data-testid="back"]')).toHaveCount(0);
    const order = await header.evaluate((el) => {
      const items = [...el.children];
      return { back: items.findIndex((c) => c.getAttribute('data-testid') === 'back'), title: items.findIndex((c) => c.classList.contains('nowo-ui-dialog__title')) };
    });
    expect(order.back).toBeLessThan(order.title);

    await page.evaluate(() => {
      const w = window as unknown as { backs: string[] };
      w.backs = [];
      document.addEventListener('click', (e) => {
        const button = (e.target as Element).closest('[data-nowo-ui-dialog-back]');
        if (button) w.backs.push(button.getAttribute('data-testid') ?? '');
      });
    });
    await back.focus();
    await expect(back).toBeFocused();
    await expect(back).toHaveAccessibleName('Back to the list');
    await page.keyboard.press('Enter');
    await page.keyboard.press('Space');
    expect(await page.evaluate(() => (window as unknown as { backs: string[] }).backs)).toEqual(['back', 'back']);
    await expect(page.locator('#destination-dialog')).toHaveAttribute('open', '');

    // Tab reaches it, from the close button's side of the header.
    await page.locator('#f-x').focus();
    await page.keyboard.press('Shift+Tab');
    await page.keyboard.press('Shift+Tab');
    await expect(back).toBeFocused();

    await page.getByTestId('send').click();
    await expect(page.getByTestId('invalid')).toBeVisible();
    await expect(header.getByTestId('back')).toHaveCount(0);
    await expect(header.getByTestId('back-2')).toBeVisible();

    await page.getByTestId('send').click();
    await expect(page.getByTestId('invalid-2')).toBeVisible();
    await expect(header.locator('[data-nowo-ui-dialog-back]')).toHaveCount(0);
  });

  test('closing takes it away, and a fragment without one has none', async ({ page }) => {
    await open(page, [
      { status: 200, body: form('<button type="button" data-nowo-ui-dialog-back data-testid="back" aria-label="Back">←</button>') },
      { status: 200, body: form('') },
    ]);
    const dialog = page.locator('#destination-dialog');
    await expect(dialog.getByTestId('back')).toBeVisible();
    await dialog.locator('[data-nowo-ui-dialog-close]').click();
    await expect(dialog.locator('[data-nowo-ui-dialog-back]')).toHaveCount(0);
    await page.getByTestId('destination-add').click();
    await expect(page.locator('#f-x')).toBeVisible();
    await expect(dialog.locator('[data-nowo-ui-dialog-back]')).toHaveCount(0);
  });

  test('a submit button or a link is left where it is', async ({ page }) => {
    await open(page, [{ status: 200, body: form('<button data-nowo-ui-dialog-back data-testid="not-lifted" name="y">Back</button>') }]);
    await expect(page.locator('#destination-dialog [data-nowo-ui-dialog-body]').getByTestId('not-lifted')).toBeVisible();
    await expect(page.locator('#destination-dialog > header [data-nowo-ui-dialog-back]')).toHaveCount(0);
  });
});
