import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/** The emoji picker on /kit/emoji-show-more, real mouse and keyboard in Chromium, Firefox and WebKit. */
test.describe.configure({ timeout: 45_000 });

type Violation = { directive: string };

async function watchCsp(page: Page): Promise<() => Promise<Violation[]>> {
  await page.route('**/_wdt/**', (route) => route.abort());
  await page.route('https://cdn.jsdelivr.net/**', (route) => route.abort());
  await page.addInitScript(() => {
    const seen: { directive: string }[] = [];
    (window as unknown as { cspSeen: typeof seen }).cspSeen = seen;
    document.addEventListener('securitypolicyviolation', (e) => seen.push({ directive: e.effectiveDirective }));
  });
  return () => page.evaluate(() => (window as unknown as { cspSeen: { directive: string }[] }).cspSeen.filter((v) => v.directive.endsWith('-attr')));
}

const PAGE = '/kit/emoji-show-more';
const field = (page: Page) => page.getByRole('textbox', { name: 'Board icon' });
const toggle = (page: Page) => page.locator('[data-nowo-ui-emoji-picker-toggle]');
const search = (page: Page) => page.locator('[data-nowo-ui-emoji-picker-search]');
const panel = (page: Page) => page.locator('[data-nowo-ui-emoji-picker-panel]');

test.describe('emoji picker', () => {
  test('keyboard only: open, search, arrows, Enter, focus back, and the server takes it', async ({ page }) => {
    const csp = await watchCsp(page);
    await page.goto(PAGE);
    await expect(toggle(page)).toBeVisible();
    await field(page).focus();
    await page.keyboard.press('Tab');
    await expect(toggle(page)).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(panel(page)).toBeVisible();
    await expect(search(page)).toBeFocused();
    await expect(toggle(page)).toHaveAttribute('aria-expanded', 'true');
    await page.keyboard.type('thumbs');
    await expect(page.locator('[role="gridcell"]')).toHaveCount(2);
    await expect(page.locator('[data-nowo-ui-emoji-picker-status]')).toHaveText('2 results');
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('ArrowRight');
    const active = page.locator('[role="gridcell"].is-active');
    await expect(active).toHaveAttribute('aria-label', 'thumbs-down');
    await expect(search(page)).toHaveAttribute('aria-activedescendant', (await active.getAttribute('id'))!);
    await expect(search(page)).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(panel(page)).toBeHidden();
    await expect(toggle(page)).toBeFocused();
    await expect(field(page)).toHaveValue('👎');
    await expect(page).toHaveURL(new RegExp(`${PAGE}$`)); // Enter did not submit the form
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByTestId('saved')).toHaveText('Saved: 👎');
    expect(await csp()).toEqual([]);
  });

  test('mouse: a click on a cell picks it, a click outside closes', async ({ page }) => {
    await page.goto(PAGE);
    await toggle(page).click();
    await page.locator('[role="gridcell"][aria-label="tada"]').click();
    await expect(field(page)).toHaveValue('🎉');
    await expect(panel(page)).toBeHidden();
    await toggle(page).click();
    await expect(panel(page)).toBeVisible();
    await page.locator('h1').click();
    await expect(panel(page)).toBeHidden();
  });

  test('Escape closes the panel, not a modal dialog around it', async ({ page }) => {
    await page.goto(PAGE);
    await page.evaluate(() => {
      const dialog = document.createElement('dialog');
      dialog.id = 'around';
      dialog.append(document.querySelector('[data-nowo-ui-emoji-picker]')!);
      document.querySelector('main')!.append(dialog);
      dialog.showModal();
    });
    await toggle(page).click();
    await expect(panel(page)).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(panel(page)).toBeHidden();
    await expect(page.locator('#around')).toHaveAttribute('open', '');
    await expect(toggle(page)).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(page.locator('#around')).not.toHaveAttribute('open', '');
  });

  test('typing more than one is cut in the browser, and refused by the server without it', async ({ page }) => {
    await page.goto(PAGE);
    await field(page).fill('👍🏽abc');
    await field(page).blur();
    await expect(field(page)).toHaveValue('👍🏽');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByTestId('saved')).toHaveText('Saved: 👍🏽');
  });

  test.describe('without JavaScript', () => {
    test.use({ javaScriptEnabled: false });

    test('a plain input: no toggle, no panel, and the server checks the value', async ({ page }) => {
      await page.goto(PAGE);
      await expect(field(page)).toBeVisible();
      await expect(toggle(page)).toBeHidden();
      await expect(panel(page)).toBeHidden();
      await field(page).fill('🚀🚀');
      await page.getByRole('button', { name: 'Save' }).click();
      await expect(page.getByTestId('saved')).toHaveCount(0);
      await expect(page.locator('body')).toContainText('Enter one emoji or character.');
      await field(page).fill('🇪🇸');
      await page.getByRole('button', { name: 'Save' }).click();
      await expect(page.getByTestId('saved')).toHaveText('Saved: 🇪🇸');
    });
  });

  test('axe: no violations with the panel closed and open', async ({ page }) => {
    await page.goto(PAGE);
    const scope = '[data-testid="emoji-form"]';
    expect((await new AxeBuilder({ page }).include(scope).analyze()).violations).toEqual([]);
    await toggle(page).click();
    await page.keyboard.press('ArrowDown');
    expect((await new AxeBuilder({ page }).include(scope).analyze()).violations).toEqual([]);
  });
});
