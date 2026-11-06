import { test, expect, type Page } from '@playwright/test';

/**
 * A `style` attribute in fetched HTML must go through the page's CSP. In Chromium and WebKit, `document.importNode()` skipped
 * it: the attribute applied under `style-src-attr 'none'`. The page keeps its policy here; the fetched HTML is the test's.
 * The dialog path is not a clone and was not affected; it is here so it stays that way.
 */
const POLICY = "style-src-attr 'none'";

async function withPolicy(page: Page, path: string): Promise<void> {
  await page.route('**/_wdt/**', (route) => route.abort());
  await page.route(`**${path}`, async (route) => {
    const response = await route.fetch();
    const headers = { ...response.headers(), 'content-security-policy': POLICY };
    // route.fetch() hands back a decoded body; the original encoding header would make a browser fail on it.
    delete headers['content-encoding'];
    delete headers['content-length'];
    await route.fulfill({ response, headers });
  });
}

const read = (page: Page, selector: string): Promise<{ position: string; attr: string | null }> =>
  page.locator(selector).first().evaluate((el: HTMLElement) => ({ position: getComputedStyle(el).position, attr: el.getAttribute('style') }));

test.describe('a style attribute in fetched HTML', () => {
  test('dialog fragment: blocked under the policy', async ({ page }) => {
    await withPolicy(page, '/kit/remote-form');
    await page.route('**/kit/imported-style-fragment', (route) =>
      route.fulfill({ status: 200, contentType: 'text/html; charset=UTF-8', body: '<p id="imported" style="position:fixed;color:rgb(255,0,0)">fetched</p>' }),
    );
    await page.goto('/kit/remote-form');
    await page.evaluate(() => {
      const opener = document.createElement('button');
      opener.id = 'open-it';
      opener.textContent = 'open';
      opener.setAttribute('data-nowo-ui-dialog-open', 'destination-dialog');
      opener.setAttribute('data-nowo-ui-dialog-src', '/kit/imported-style-fragment');
      document.querySelector('main')?.append(opener);
    });
    await page.locator('#open-it').click();
    await expect(page.locator('#imported')).toBeAttached();
    expect((await read(page, '#imported')).position).toBe('static');
  });

  test('list pagination, appended row: blocked under the policy', async ({ page }) => {
    await withPolicy(page, '/kit/2.23/pagination/infinite*');
    await page.route(/\/kit\/2\.23\/pagination\/infinite.*page=2/, async (route) => {
      const response = await route.fetch();
      const body = (await response.text()).replace(/(<tbody[^>]*>\s*)<tr/, '$1<tr data-imported style="position:fixed"');
      const headers = { ...response.headers() };
      delete headers['content-encoding'];
      delete headers['content-length'];
      await route.fulfill({ response, body, headers });
    });
    await page.goto('/kit/2.23/pagination/infinite');
    await expect(page.locator('[data-controller~="list-pagination"]')).toHaveAttribute('data-nowo-ui-pagination-active', 'infinite');
    await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
    await expect(page.locator('[data-imported]').first()).toBeAttached();
    expect((await read(page, '[data-imported]')).position).toBe('static');
  });
});
