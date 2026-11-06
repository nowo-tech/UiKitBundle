import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * UiKit 2.28 on /kit/remote-form: the remote form dialog (IIFE driver) — open from a link with the dialog header,
 * 422 re-rendered with focus on the invalid field, 204 + X-Nowo-Redirect followed with the flash, Escape and focus
 * back (the saved label in the redirect target's query: the demo has no session), a fresh form on reopen, the screen width with a 16 px gutter on phones (2.29), the actions in the footer, the no-JavaScript page — and the overlay registry: the overlay
 * side panel makes the marked header inert, sets html[data-nowo-overlay] and dispatches nowo:overlay-* events.
 * The page forbids inline style attributes and handlers (CSP).
 */
async function watchCsp(page: Page): Promise<string[]> {
  const violations: string[] = [];
  page.on('console', (msg) => {
    if (/Content Security Policy/i.test(msg.text())) violations.push(msg.text());
  });
  return violations;
}

test.describe('remote form dialog (2.28)', () => {
  test.use({ reducedMotion: 'reduce' });

  test('opens the form, re-renders 422, follows 204 + X-Nowo-Redirect with the flash', async ({ page }) => {
    // The dev web debug toolbar lists every fetch with inline-styled rows (its own CSP noise): keep it out.
    await page.route('**/_wdt/**', (route) => route.abort());
    const csp = await watchCsp(page);
    await page.goto('/kit/remote-form');
    const requests: Record<string, string>[] = [];
    page.on('request', (request) => {
      if (request.url().includes('/kit/remote-form/new')) requests.push(request.headers());
    });
    const add = page.getByTestId('destination-add');
    await add.click();
    const dialog = page.getByTestId('destination-dialog');
    await expect(dialog).toBeVisible();
    await expect(page).toHaveURL(/\/kit\/remote-form$/);
    await expect(dialog.locator('h2')).toHaveText('New destination');
    await expect(page.getByTestId('destination-label')).toBeFocused();
    expect(requests[0]?.['x-nowo-dialog']).toBe('1');
    await expect(page.locator('html')).toHaveAttribute('data-nowo-overlay', '1');

    await page.getByTestId('destination-save').click();
    await expect(dialog.locator('[aria-invalid="true"]')).toBeFocused();
    await expect(dialog).toContainText('Enter a label.');

    await page.getByTestId('destination-label').fill('Ops channel');
    await page.getByTestId('destination-save').click();
    await expect(page.getByTestId('flash')).toHaveText('Destination "Ops channel" saved.');
    await expect(dialog).toBeHidden();
    expect(csp).toEqual([]);
  });

  test('a dot-segment X-Nowo-Redirect never navigates off-origin (REQ-SEC-004 ES-M1)', async ({ page }) => {
    const offOrigin: string[] = [];
    page.on('request', (request) => {
      if (new URL(request.url()).hostname === 'evil.example') offOrigin.push(request.url());
    });
    await page.route((url) => url.hostname === 'evil.example', (route) => route.abort());
    await page.goto('/kit/remote-form');
    const origin = new URL(page.url()).origin;
    for (const dotted of ['/.//evil.example/pwn', '/..//evil.example/pwn', '/%2e//evil.example/pwn', '/%2E%2E//evil.example/pwn', '/a/..//evil.example/pwn', '/.%2e//evil.example/pwn']) {
      await page.route('**/kit/remote-form/new', (route) =>
        route.request().method() === 'POST' ? route.fulfill({ status: 204, headers: { 'X-Nowo-Redirect': dotted } }) : route.fallback(),
      );
      await page.getByTestId('destination-add').click();
      await page.getByTestId('destination-label').fill('x');
      await Promise.all([page.waitForEvent('load'), page.getByTestId('destination-save').click()]);
      expect(new URL(page.url()).origin).toBe(origin);
      await page.unroute('**/kit/remote-form/new');
    }
    expect(offOrigin).toEqual([]);
  });

  test('Escape closes, focus returns, and a reopened dialog has a fresh form', async ({ page }) => {
    await page.goto('/kit/remote-form');
    const add = page.getByTestId('destination-add');
    await add.click();
    await page.getByTestId('destination-label').fill('draft');
    await page.keyboard.press('Escape');
    await expect(page.getByTestId('destination-dialog')).toBeHidden();
    await expect(add).toBeFocused();
    await expect(page.locator('html')).not.toHaveAttribute('data-nowo-overlay', /.*/);
    await add.click();
    await expect(page.getByTestId('destination-label')).toHaveValue('');
    // axe after the CSP checks of the other test: axe-core applies inline styles while it measures.
    const results = await new AxeBuilder({ page }).include('[data-testid="destination-dialog"]').analyze();
    expect(results.violations).toEqual([]);
    await page.getByTestId('destination-cancel').click();
    await expect(page.getByTestId('destination-dialog')).toBeHidden();
  });

  test('screen width with the 16 px gutter on phones, content height, without horizontal scroll (2.29, 2.31)', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/kit/remote-form');
    await page.getByTestId('destination-add').click();
    await expect(page.getByTestId('destination-label')).toBeVisible();
    const box = await page.getByTestId('destination-dialog').boundingBox();
    expect(Math.round(box?.x ?? 0)).toBe(16);
    expect(Math.round(box?.width ?? 0)).toBe(358);
    // 2.31 (dialog rule v2): the content's height (header + body + footer), capped at 100dvh - 2 × 16 px
    expect(Math.round(box?.height ?? 0)).toBeLessThanOrEqual(812);
    const parts = await page.getByTestId('destination-dialog').evaluate((node) => ['header', '.nowo-ui-dialog__body', 'footer']
      .map((selector) => (node.querySelector(`:scope > ${selector}`) as HTMLElement | null)?.getBoundingClientRect().height ?? 0)
      .reduce((a, b) => a + b, 0));
    expect(Math.abs((box?.height ?? 0) - parts)).toBeLessThanOrEqual(2);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  });

  test('the form actions sit in the dialog footer, bound to the form (2.29)', async ({ page }) => {
    await page.goto('/kit/remote-form');
    await page.getByTestId('destination-add').click();
    const save = page.locator('[data-testid="destination-dialog"] > footer [data-testid="destination-save"]');
    await expect(save).toBeVisible();
    const formId = await page.getByTestId('destination-form').getAttribute('id');
    expect(formId).toBeTruthy();
    await expect(save).toHaveAttribute('form', formId as string);
  });

  test('without JavaScript the link is the full form page', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto('/kit/remote-form');
    await page.getByTestId('destination-add').click();
    await expect(page).toHaveURL(/\/kit\/remote-form\/new$/);
    await expect(page.getByTestId('destination-page')).toBeVisible();
    await page.getByTestId('destination-label').fill('No JS');
    await page.getByTestId('destination-save').click();
    await expect(page.getByTestId('flash')).toHaveText('Destination "No JS" saved.');
    await context.close();
  });

  test('overlay side panel: marked header inert, root marker and events', async ({ page }) => {
    await page.goto('/kit/remote-form');
    await page.evaluate(() => {
      const log: string[] = [];
      (window as unknown as { overlayLog: string[] }).overlayLog = log;
      document.addEventListener('nowo:overlay-open', (e) => log.push(`open:${(e as CustomEvent).detail.depth}:${(e as CustomEvent).detail.kind}`));
      document.addEventListener('nowo:overlay-close', (e) => log.push(`close:${(e as CustomEvent).detail.depth}:${(e as CustomEvent).detail.kind}`));
    });
    await page.getByTestId('help-toggle').click();
    const panel = page.getByTestId('help-panel');
    await expect(panel).toBeVisible();
    await expect(page.getByTestId('remote-form-header')).toHaveAttribute('inert', '');
    await expect(page.locator('html')).toHaveAttribute('data-nowo-overlay', '1');
    await page.keyboard.press('Escape');
    await expect(panel).toBeHidden();
    await expect(page.getByTestId('remote-form-header')).not.toHaveAttribute('inert', /.*/);
    expect(await page.evaluate(() => (window as unknown as { overlayLog: string[] }).overlayLog)).toEqual(['open:1:side-panel', 'close:0:side-panel']);
  });

  test('a multi-step flow: 204 + X-Nowo-Dialog-Location loads the next step in the same dialog (2.33)', async ({ page }) => {
    await page.route('**/_wdt/**', (route) => route.abort());
    const csp = await watchCsp(page);
    const answers: { url: string; status: number; location: string | null }[] = [];
    page.on('response', (response) => {
      if (response.url().includes('/kit/remote-form/wizard')) {
        answers.push({ url: response.url(), status: response.status(), location: response.headers()['x-nowo-dialog-location'] ?? null });
      }
    });
    await page.goto('/kit/remote-form');
    const opener = page.getByTestId('wizard-add');
    await opener.click();
    const dialog = page.getByTestId('destination-dialog');
    await expect(dialog.locator('h2')).toHaveText('New destination (wizard)');
    await expect(page.getByTestId('wizard-step')).toHaveText('Step 1 of 2');

    await page.getByTestId('wizard-next').click();
    await expect(dialog.locator('[aria-invalid="true"]')).toBeFocused();

    await page.getByTestId('wizard-label').fill('Ops');
    await page.getByTestId('wizard-next').click();
    await expect(page.getByTestId('wizard-step')).toHaveText('Step 2 of 2: Ops');
    await expect(dialog).toBeVisible();
    await expect(page).toHaveURL(/\/kit\/remote-form$/);
    await expect(dialog.locator('h2')).toHaveText('New destination (wizard)');
    await expect(page.getByTestId('wizard-channel')).toBeFocused();
    expect(answers.find((a) => a.status === 204)?.location).toBe('/kit/remote-form/wizard/channel?label=Ops');

    await page.getByTestId('wizard-channel').selectOption('slack');
    await page.getByTestId('wizard-next').click();
    await expect(page.getByTestId('flash')).toHaveText('Destination "Ops (slack)" saved.');
    await expect(dialog).toBeHidden();
    expect(csp).toEqual([]);
  });

  test('a step location on another origin is never loaded (2.33)', async ({ page }) => {
    const offOrigin: string[] = [];
    page.on('request', (request) => {
      if (new URL(request.url()).hostname === 'evil.example') offOrigin.push(request.url());
    });
    await page.route((url) => url.hostname === 'evil.example', (route) => route.abort());
    await page.goto('/kit/remote-form');
    const origin = new URL(page.url()).origin;
    for (const bad of ['https://evil.example/step', '//evil.example/step', '/.//evil.example/step', '/%2e%2e//evil.example/step', '/\\evil.example/step']) {
      await page.route('**/kit/remote-form/wizard', (route) =>
        route.request().method() === 'POST' ? route.fulfill({ status: 204, headers: { 'X-Nowo-Dialog-Location': bad } }) : route.fallback(),
      );
      await page.getByTestId('wizard-add').click();
      await page.getByTestId('wizard-label').fill('x');
      // An invalid location is ignored: a 204 without a valid header reloads the page.
      await Promise.all([page.waitForEvent('load'), page.getByTestId('wizard-next').click()]);
      expect(new URL(page.url()).origin).toBe(origin);
      await page.unroute('**/kit/remote-form/wizard');
    }
    expect(offOrigin).toEqual([]);
  });

  test('without JavaScript the wizard steps are full pages (2.33)', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto('/kit/remote-form');
    await page.getByTestId('wizard-add').click();
    await expect(page.getByTestId('wizard-page')).toBeVisible();
    await page.getByTestId('wizard-label').fill('Plain');
    await page.getByTestId('wizard-next').click();
    await expect(page).toHaveURL(/\/kit\/remote-form\/wizard\/channel\?label=Plain$/);
    await page.getByTestId('wizard-channel').selectOption('email');
    await page.getByTestId('wizard-next').click();
    await expect(page.getByTestId('flash')).toHaveText('Destination "Plain (email)" saved.');
    await context.close();
  });
});
