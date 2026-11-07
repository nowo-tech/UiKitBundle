import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/** The show more fold on /kit/emoji-show-more, in Chromium, Firefox and WebKit: CSS only, a real button, keyboard reachable. */
test.describe.configure({ timeout: 45_000 });

const PAGE = '/kit/emoji-show-more';

test.describe('show more', () => {
  test('folds to three lines, opens and folds by keyboard, and says so', async ({ page }) => {
    const response = await page.goto(PAGE);
    const html = await response!.text();
    expect(html.slice(html.indexOf('<main'), html.indexOf('</main>'))).not.toContain('style="');
    const root = page.getByTestId('show-long');
    const content = root.locator('[data-nowo-ui-show-more-content]');
    const button = root.getByRole('button', { name: 'Show more' });
    await expect(button).toBeVisible();
    await expect(button).toHaveAttribute('aria-expanded', 'false');
    await expect(button).toHaveAttribute('aria-controls', 'long-text');
    const folded = (await content.boundingBox())!.height;
    const fontSize = await content.evaluate((el) => Number.parseFloat(getComputedStyle(el.firstElementChild!).fontSize));
    expect(folded).toBeLessThan(fontSize * 7); // three lines and the paragraph's margin, not the whole text
    await expect(content.evaluate((el) => el.scrollHeight > el.clientHeight)).resolves.toBe(true);

    await button.focus();
    await page.keyboard.press('Enter');
    const less = root.getByRole('button', { name: 'Show less' });
    await expect(less).toBeFocused();
    await expect(less).toHaveAttribute('aria-expanded', 'true');
    expect((await content.boundingBox())!.height).toBeGreaterThan(folded * 1.5);

    await page.keyboard.press('Space');
    await expect(root.getByRole('button', { name: 'Show more' })).toBeFocused();
    expect((await content.boundingBox())!.height).toBe(folded);
  });

  test('text that fits has no button', async ({ page }) => {
    await page.goto(PAGE);
    await expect(page.getByTestId('show-short').locator('[data-nowo-ui-show-more-toggle]')).toBeHidden();
  });

  test('HR-L9: focus on a visible link leaves it folded; focus on a clipped one opens it, and it is seen', async ({ page }) => {
    await page.goto(PAGE);
    const root = page.getByTestId('show-long');
    await root.getByRole('button', { name: 'Show more' }).waitFor();
    await page.getByTestId('inner-link').focus();
    await expect(root.getByRole('button', { name: 'Show more' })).toBeVisible();
    const clipped = page.getByTestId('clipped-link');
    await clipped.focus();
    await expect(root.getByRole('button', { name: 'Show less' })).toBeVisible();
    await expect(clipped).toBeInViewport();
    const rects = await clipped.evaluate((el) => {
      const content = el.closest('[data-nowo-ui-show-more-content]')!.getBoundingClientRect();
      return { link: el.getBoundingClientRect().bottom, content: content.bottom };
    });
    expect(rects.link).toBeLessThanOrEqual(rects.content + 1);
  });

  test('a narrower window that makes the text overflow brings the button', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 720 });
    await page.goto(PAGE);
    await page.getByTestId('show-short').evaluate((root) => {
      root.querySelector('[data-nowo-ui-show-more-content]')!.insertAdjacentText('beforeend', ' grows '.repeat(200));
      (root.parentElement as HTMLElement).style.setProperty('max-width', '10rem');
    });
    await expect(page.getByTestId('show-short').locator('[data-nowo-ui-show-more-toggle]')).toBeVisible();
  });

  test.describe('without JavaScript', () => {
    test.use({ javaScriptEnabled: false });

    test('the whole text shows, and no button', async ({ page }) => {
      await page.goto(PAGE);
      const root = page.getByTestId('show-long');
      await expect(root.locator('[data-nowo-ui-show-more-toggle]')).toBeHidden();
      await expect(root.locator('[data-nowo-ui-show-more-content]')).toBeVisible();
      const box = (await root.locator('[data-nowo-ui-show-more-content]').boundingBox())!;
      expect(box.height).toBeGreaterThan(100);
    });
  });

  test('axe: no violations folded and open', async ({ page }) => {
    await page.goto(PAGE);
    const scope = '[data-testid="show-long"]';
    expect((await new AxeBuilder({ page }).include(scope).analyze()).violations).toEqual([]);
    await page.getByTestId('show-long').getByRole('button', { name: 'Show more' }).click();
    expect((await new AxeBuilder({ page }).include(scope).analyze()).violations).toEqual([]);
  });
});
