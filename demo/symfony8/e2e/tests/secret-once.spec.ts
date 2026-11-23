import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * UiKit 2.69 one-time secret (partial `secret_once`, runtime `secret-once`) on /catalogue/admin/data-display: the
 * secret is in the document exactly once (document.documentElement.outerHTML) before and after reveal and copy, and
 * not at all once cleared (clear_on_hide, the enclosing dialog closed, pagehide / back-forward navigation). Without
 * JavaScript it is plain visible text. The demo values change on every request.
 */
const PATH = '/catalogue/admin/data-display';

async function secretOf(page: Page, testid: string): Promise<string> {
  const text = await page.locator(`[data-testid="${testid}"] [data-nowo-ui-secret-once-value]`).evaluate((el) => el.textContent ?? '');
  expect(text).toMatch(/^demo_[a-z]{3}_[0-9a-f]{16,24}$/);

  return text;
}

async function count(page: Page, secret: string): Promise<number> {
  return page.evaluate((s) => document.documentElement.outerHTML.split(s).length - 1, secret);
}

test.describe('one-time secret', () => {
  test('without JavaScript the secret is plain visible text and the controls stay hidden', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto(PATH);
    const root = page.getByTestId('secret-token');
    const value = root.locator('[data-nowo-ui-secret-once-value]');
    await expect(value).toBeVisible();
    const secret = (await value.textContent()) ?? '';
    expect(secret).toMatch(/^demo_tok_/);
    expect((await page.content()).split(secret).length - 1).toBe(1);
    await expect(root.locator('[data-nowo-ui-secret-once-controls]')).toBeHidden();
    await expect(root.locator('[data-nowo-ui-secret-once-mask]')).toBeHidden();
    await expect(value).toHaveCSS('user-select', 'all');
    await context.close();
  });

  test('masked, revealed with the keyboard and copied: the secret stays exactly once in the DOM', async ({ page, context }) => {
    await context.grantPermissions(['clipboard-read', 'clipboard-write']);
    await page.goto(PATH);
    const root = page.getByTestId('secret-token');
    await expect(root).toHaveAttribute('data-nowo-ui-secret-once-state', 'masked');
    const secret = await secretOf(page, 'secret-token');
    const value = root.locator('[data-nowo-ui-secret-once-value]');
    const toggle = root.getByRole('button', { name: 'Show' });
    expect(await count(page, secret)).toBe(1);
    await expect(value).toBeHidden();
    await expect(root.locator('[data-nowo-ui-secret-once-mask]')).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-pressed', 'false');

    await toggle.focus();
    await page.keyboard.press('Space');
    await expect(toggle).toHaveAttribute('aria-pressed', 'true');
    await expect(value).toBeVisible();
    await expect(value).toHaveText(secret);
    expect(await count(page, secret)).toBe(1);

    await root.getByRole('button', { name: 'Copy' }).click();
    await expect(root.locator('[role="status"]')).toHaveText('Copied');
    expect(await page.evaluate(() => navigator.clipboard.readText())).toBe(secret);
    expect(await count(page, secret)).toBe(1);

    // hidden again: copy still works from the same node
    await page.keyboard.press('Shift+Tab');
    await page.keyboard.press('Enter');
    await expect(toggle).toHaveAttribute('aria-pressed', 'false');
    await expect(value).toBeHidden();
    expect(await count(page, secret)).toBe(1);
    // no secret in any attribute
    expect(await page.evaluate((s) => [...document.querySelectorAll('*')].some((el) => [...el.attributes].some((a) => a.value.includes(s))), secret)).toBe(false);
  });

  test('clear_on_hide wipes the text in place and disables the controls', async ({ page }) => {
    await page.goto(PATH);
    const root = page.getByTestId('secret-recovery');
    await expect(root).toHaveAttribute('data-nowo-ui-secret-once-state', 'revealed');
    const secret = await secretOf(page, 'secret-recovery');
    expect(await count(page, secret)).toBe(1);
    const toggle = root.getByRole('button', { name: 'Show' });
    await toggle.click();
    await expect(root).toHaveAttribute('data-nowo-ui-secret-once-state', 'cleared');
    expect(await count(page, secret)).toBe(0);
    await expect(toggle).toHaveAttribute('aria-disabled', 'true');
    await expect(root.getByRole('button', { name: 'Copy' })).toHaveAttribute('aria-disabled', 'true');
    await expect(root.locator('[data-nowo-ui-secret-once-cleared]')).toBeVisible();
    await expect(root.locator('[role="status"]')).toHaveText('Cleared: the secret is no longer on this page');
    await expect(toggle).toBeFocused();
  });

  test('closing the enclosing dialog wipes the secret', async ({ page }) => {
    await page.goto(PATH);
    const secret = await secretOf(page, 'secret-dialog');
    expect(await count(page, secret)).toBe(1);
    await page.getByTestId('secret-dialog-open').click();
    const dialog = page.getByTestId('secret-dialog-box');
    await expect(dialog).toBeVisible();
    await dialog.getByRole('button', { name: 'Show' }).click();
    await expect(dialog.locator('[data-nowo-ui-secret-once-value]')).toBeVisible();
    expect(await count(page, secret)).toBe(1);
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    expect(await count(page, secret)).toBe(0);
    await expect(page.getByTestId('secret-dialog')).toHaveAttribute('data-nowo-ui-secret-once-state', 'cleared');
  });

  test('pagehide wipes every secret, so back / forward never brings one back', async ({ page }) => {
    await page.goto(PATH);
    const secrets = [await secretOf(page, 'secret-token'), await secretOf(page, 'secret-recovery'), await secretOf(page, 'secret-dialog')];
    // the bfcache event itself
    await page.evaluate(() => window.dispatchEvent(new PageTransitionEvent('pagehide', { persisted: true })));
    for (const secret of secrets) {
      expect(await count(page, secret)).toBe(0);
    }

    // a real navigation away and back (restored from the bfcache, wiped; or reloaded with new values)
    await page.goto(PATH);
    const token = await secretOf(page, 'secret-token');
    await page.goto('/catalogue/admin/overlays');
    await page.goBack();
    await expect(page.getByTestId('secret-token')).toBeVisible();
    expect(await count(page, token)).toBe(0);
    await page.goForward();
    await page.goBack();
    expect(await count(page, token)).toBe(0);
  });

  test('axe: the secret section has no serious or critical violation, light and dark', async ({ page }) => {
    for (const theme of ['light', 'dark'] as const) {
      await page.emulateMedia({ colorScheme: theme });
      await page.goto(PATH);
      await page.evaluate((t) => {
        document.documentElement.setAttribute('data-theme', t);
        document.documentElement.setAttribute('data-bs-theme', t);
      }, theme);
      await page.getByTestId('secret-token').getByRole('button', { name: 'Show' }).click();
      const result = await new AxeBuilder({ page }).include('[data-testid="catalogue-secret-once"]').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
      expect(result.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical').map((v) => `${theme} ${v.id}: ${v.nodes.map((n) => `${n.target.join(' ')} ${n.failureSummary ?? ''}`).join(' | ')}`)).toEqual([]);
    }
  });
});
