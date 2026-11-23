import { test, expect, type Page } from '@playwright/test';

/**
 * UiKit 2.70 on /kit/stimulus-dialogs/{stimulus,iife,both}: the on-demand dialogs driven by the Stimulus peers.
 * - stimulus: the page loads NO js/nowo-ui-dialog.js / js/nowo-ui-confirm.js (a Beacon product page): the row kebabs
 *   share one confirm dialog (confirm-shared peer) and the "Edit" openers one remote form shell (remote-form-dialog
 *   peer, delegated mode); the same protocol as the IIFE runtime (X-Nowo-Dialog GET, 422 in place, 204 +
 *   X-Nowo-Redirect);
 * - iife: the same page on the IIFE runtimes;
 * - both: the Stimulus markup with both runtimes loaded — one request per open, one POST per submit.
 * The shared confirm submits the clicked row's own hidden form: its target and token (the server checks it).
 */
async function watchCsp(page: Page): Promise<string[]> {
  const violations: string[] = [];
  page.on('console', (msg) => {
    if (/Content Security Policy/i.test(msg.text())) violations.push(msg.text());
  });
  return violations;
}

/** Requests the page makes, by method and path. */
function track(page: Page): { method: string; path: string; headers: Record<string, string>; body: string | null }[] {
  const seen: { method: string; path: string; headers: Record<string, string>; body: string | null }[] = [];
  page.on('request', (request) => {
    const url = new URL(request.url());
    if (url.pathname.startsWith('/kit/stimulus-dialogs/') && url.pathname.includes('/members/')) {
      seen.push({ method: request.method(), path: url.pathname, headers: request.headers(), body: request.postData() });
    }
  });
  return seen;
}

for (const runtime of ['stimulus', 'iife', 'both'] as const) {
  test.describe(`stimulus dialogs (2.70), runtime ${runtime}`, () => {
    test.use({ reducedMotion: 'reduce' });

    test.beforeEach(async ({ page }) => {
      await page.route('**/_wdt/**', (route) => route.abort());
    });

    test('loads the expected runtimes', async ({ page }) => {
      await page.goto(`/kit/stimulus-dialogs/${runtime}`);
      const scripts = await page.locator('script[src]').evaluateAll((nodes) => nodes.map((n) => (n as HTMLScriptElement).src));
      const has = (name: string): boolean => scripts.some((src) => src.includes(name));
      expect(has('nowo-ui-dialog.js')).toBe(runtime !== 'stimulus');
      expect(has('nowo-ui-confirm.js')).toBe(runtime !== 'stimulus');
      expect(has('demo-stimulus-dialogs.js')).toBe(runtime !== 'iife');
      await expect(page.locator('dialog')).toHaveCount(2);
      if (runtime !== 'iife') {
        await expect(page.locator('#members-confirm')).toHaveAttribute('data-nowo-ui-runtime-owner', 'stimulus');
        await expect(page.locator('#member-edit')).toHaveAttribute('data-nowo-ui-runtime-owner', 'stimulus');
      } else {
        await expect(page.locator('#members-confirm')).not.toHaveAttribute('data-nowo-ui-runtime-owner', /.*/);
      }
    });

    test('the shared confirm submits the clicked row (its target and token), once', async ({ page }) => {
      const csp = await watchCsp(page);
      const requests = track(page);
      await page.goto(`/kit/stimulus-dialogs/${runtime}`);
      await page.getByTestId('member-2').getByText('Actions for Bob').click();
      await page.getByTestId('member-2').getByRole('button', { name: 'Delete' }).click();
      const dialog = page.locator('#members-confirm');
      await expect(dialog).toBeVisible();
      await expect(dialog.getByRole('heading')).toHaveText('Remove Bob?');
      await expect(dialog).toContainText('Bob loses access to the project.');
      // A destructive confirm opens on Cancel (2.61).
      await expect(dialog.getByRole('button', { name: 'Cancel' })).toBeFocused();
      await dialog.getByRole('button', { name: 'Cancel' }).click();
      await expect(dialog).toBeHidden();
      // Focus back on the row toggle, or (js/nowo-ui-dialog.js closed the kebab on the outside click) its menu toggle.
      const focused = await page.evaluate(() => (document.activeElement?.closest('[data-testid]')?.getAttribute('data-testid') ?? '') + '|' + (document.activeElement?.textContent ?? '').trim());
      expect(focused).toMatch(/^member-2\|(Delete|Actions for Bob)$/);
      expect(csp).toEqual([]);

      // Another row, then Confirm.
      await page.getByTestId('member-3').getByText('Actions for Cy').click();
      await page.getByTestId('member-3').getByRole('button', { name: 'Delete' }).click();
      await expect(dialog.getByRole('heading')).toHaveText('Remove Cy?');
      await dialog.getByRole('button', { name: 'Remove' }).click();
      await expect(page.getByTestId('status')).toHaveText('Removed Cy.');
      const posts = requests.filter((r) => r.method === 'POST');
      expect(posts).toHaveLength(1);
      expect(posts[0]?.path).toBe(`/kit/stimulus-dialogs/${runtime}/members/3/remove`);
      expect(posts[0]?.body ?? '').toMatch(/^_token=[0-9a-f]{32}$/);
    });

    test('the shared remote form shell: X-Nowo-Dialog GET, 422 in place, 204 + X-Nowo-Redirect, one request each', async ({ page }) => {
      const csp = await watchCsp(page);
      const requests = track(page);
      await page.goto(`/kit/stimulus-dialogs/${runtime}`);
      await page.getByTestId('edit-1').click();
      const dialog = page.getByTestId('member-edit-dialog');
      await expect(dialog).toBeVisible();
      await expect(page).toHaveURL(new RegExp(`/kit/stimulus-dialogs/${runtime}$`));
      await expect(dialog.locator('h2')).toHaveText('Edit member');
      await expect(page.getByTestId('destination-label')).toBeFocused();
      const gets = requests.filter((r) => r.method === 'GET');
      expect(gets).toHaveLength(1);
      expect(gets[0]?.path).toBe(`/kit/stimulus-dialogs/${runtime}/members/1/edit`);
      expect(gets[0]?.headers['x-nowo-dialog']).toBe('1');

      await page.getByTestId('destination-save').click();
      await expect(dialog.locator('[aria-invalid="true"]')).toBeFocused();
      await expect(dialog).toContainText('Enter a label.');
      expect(requests.filter((r) => r.method === 'POST')).toHaveLength(1);
      // Checked before the redirect: the dev toolbar of the next full page brings its own inline script.
      expect(csp).toEqual([]);

      await page.getByTestId('destination-label').fill('Ops');
      await page.getByTestId('destination-save').click();
      await expect(page.getByTestId('status')).toHaveText('Saved Ops (Ann).');
      expect(requests.filter((r) => r.method === 'POST')).toHaveLength(2);
    });

    test('the × closes the remote shell and focus goes back to the opener', async ({ page }) => {
      await page.goto(`/kit/stimulus-dialogs/${runtime}`);
      await page.getByTestId('edit-2').click();
      const dialog = page.getByTestId('member-edit-dialog');
      await expect(page.getByTestId('destination-label')).toBeFocused();
      await dialog.getByRole('button', { name: 'Close' }).click();
      await expect(dialog).toBeHidden();
      await expect(page.getByTestId('edit-2')).toBeFocused();
      // Escape (native cancel) too.
      await page.getByTestId('edit-2').click();
      await expect(page.getByTestId('destination-label')).toBeFocused();
      await page.keyboard.press('Escape');
      await expect(dialog).toBeHidden();
    });

    if (runtime === 'both') {
      test('a remote form opened before the peers start, then saved after they claim it: one POST (ZAF-M1)', async ({ page }) => {
        // Hold the peers' script: the IIFE runtime drives the dialog first.
        let release: () => void = () => undefined;
        const gate = new Promise<void>((resolve) => {
          release = resolve;
        });
        await page.route('**/js/demo-stimulus-dialogs.js', async (route) => {
          await gate;
          await route.continue();
        });
        const requests = track(page);
        await page.goto(`/kit/stimulus-dialogs/${runtime}`, { waitUntil: 'commit' });
        await page.waitForFunction(() => typeof window.nowoUiOpenRemoteForm === 'function');
        const dialog = page.getByTestId('member-edit-dialog');
        await page.getByTestId('edit-1').click();
        await expect(page.getByTestId('destination-label')).toBeFocused();
        await expect(dialog).not.toHaveAttribute('data-nowo-ui-runtime-owner', /.*/);
        await page.keyboard.press('Escape');
        await expect(dialog).toBeHidden();

        release();
        await expect(dialog).toHaveAttribute('data-nowo-ui-runtime-owner', 'stimulus');
        await page.getByTestId('edit-2').click();
        await expect(page.getByTestId('destination-label')).toBeFocused();
        await page.getByTestId('destination-label').fill('Once');
        await page.getByTestId('destination-save').click();
        await expect(page.getByTestId('status')).toHaveText('Saved Once (Bob).');
        expect(requests.filter((r) => r.method === 'POST')).toHaveLength(1);
        expect(requests.filter((r) => r.method === 'GET')).toHaveLength(2);
      });
    }

    if (runtime !== 'iife') {
      test('confirm-submit shows the title and the button labels', async ({ page }) => {
        await page.goto(`/kit/stimulus-dialogs/${runtime}`);
        await page.getByTestId('reset').click();
        const ask = page.locator('dialog.nowo-ui-confirm[open]').last();
        await expect(ask.getByRole('heading')).toHaveText('Reset filters?');
        await expect(ask).toContainText('Saved filters and column choices go back to the defaults.');
        await expect(ask.getByRole('button', { name: 'Keep them' })).toBeFocused();
        await ask.getByRole('button', { name: 'Reset' }).click();
        await expect(page.getByTestId('status')).toHaveText('Filters reset.');
      });
    }
  });
}
