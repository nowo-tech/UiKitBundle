import { test, expect, type Page, type Route } from '@playwright/test';

/**
 * A `style` attribute in fetched HTML must go through the page's CSP. In Chromium and WebKit, `document.importNode()` skipped
 * it, in WebKit moving parsed content did too: the attribute applied under `style-src-attr 'none'`. The page keeps its policy
 * here and the fetched HTML is the test's. Projects `chromium` and `imported-style-webkit`.
 */
const POLICY = "style-src-attr 'none'";

async function noTooling(page: Page): Promise<void> {
  await page.route('**/_wdt/**', (route) => route.abort());
  await page.route('https://cdn.jsdelivr.net/**', (route) => route.abort());
}

async function withPolicy(page: Page, path: string, policy: string | null = POLICY): Promise<void> {
  await noTooling(page);
  await page.route(`**${path}`, async (route) => {
    const response = await route.fetch();
    const headers = { ...response.headers() };
    delete headers['content-security-policy'];
    if (policy) headers['content-security-policy'] = policy;
    // route.fetch() hands back a decoded body; the original encoding header would make a browser fail on it.
    delete headers['content-encoding'];
    delete headers['content-length'];
    await route.fulfill({ response, headers });
  });
}

async function changeHtml(route: Route, change: (html: string) => string): Promise<void> {
  const response = await route.fetch();
  const headers = { ...response.headers() };
  delete headers['content-encoding'];
  delete headers['content-length'];
  await route.fulfill({ response, headers, body: change(await response.text()) });
}

/**
 * Whether the style applies. It forces a new style pass first: WebKit applies a let-through attribute only then, and a read
 * without it reports `static`.
 */
async function expectBlocked(page: Page, selector: string): Promise<void> {
  await expect(page.locator(selector).first()).toBeAttached();
  const seen = await page.locator(selector).first().evaluate((el: HTMLElement) => {
    el.classList.toggle('kit-e2e-recalc');
    el.getBoundingClientRect();
    return { position: getComputedStyle(el).position, length: el.style.length };
  });
  expect(seen).toEqual({ position: expect.not.stringMatching(/^fixed$/), length: 0 });
}

const STYLED = 'style="position:fixed;color:rgb(255,0,0);background:url(/kit-e2e-imported.png)"';

async function openFragment(page: Page): Promise<void> {
  await page.evaluate(() => {
    const opener = document.createElement('button');
    opener.id = 'open-it';
    opener.textContent = 'open';
    opener.setAttribute('data-nowo-ui-dialog-open', 'destination-dialog');
    opener.setAttribute('data-nowo-ui-dialog-src', '/kit/imported-style-fragment');
    document.querySelector('main')?.append(opener);
  });
  await page.locator('#open-it').click();
}

test.describe('a style attribute in fetched HTML', () => {
  test('plain dialog fragment: blocked, nested too, and no request for its url()', async ({ page }) => {
    const leaked: string[] = [];
    await page.route('**/kit-e2e-imported.png', (route) => {
      leaked.push(route.request().url());
      return route.abort();
    });
    await withPolicy(page, '/kit/remote-form');
    await page.route('**/kit/imported-style-fragment', (route) =>
      route.fulfill({ status: 200, contentType: 'text/html; charset=UTF-8', body: `<p id="imported" ${STYLED}>fetched</p><div><b id="deep" style="position:fixed">d</b></div>` }),
    );
    await page.goto('/kit/remote-form');
    await openFragment(page);
    await expectBlocked(page, '#imported');
    await expectBlocked(page, '#deep');
    await page.waitForTimeout(150);
    expect(leaked).toEqual([]);
  });

  test('remote form dialog body: blocked', async ({ page }) => {
    await withPolicy(page, '/kit/remote-form');
    await page.route('**/kit/remote-form/new*', (route) => changeHtml(route, (html) => html.replace(/(<form)/, '<p id="in-form-body" style="position:fixed">f</p>$1')));
    await page.goto('/kit/remote-form');
    await page.locator('[href*="remote-form/new"], [data-nowo-ui-dialog-src*="remote-form/new"], [data-remote-form-dialog-url-param*="remote-form/new"]').first().click();
    await expectBlocked(page, '#in-form-body');
  });

  test('list pagination: rows, pager, info and row confirm dialogs: blocked', async ({ page }) => {
    await withPolicy(page, '/kit/2.23/pagination/infinite');
    const changed: Record<string, boolean> = {};
    // Every later page: the peer may load on past page 2, and each load replaces the pager and the info.
    await page.route(/\/kit\/2\.23\/pagination\/infinite.*page=[2-9]/, (route) =>
      changeHtml(route, (html) => {
        const step = (name: string, source: string, from: RegExp, to: string): string => {
          const next = source.replace(from, to);
          changed[name] = next !== source;
          return next;
        };
        let body = step('row', html, /(<tbody[^>]*>\s*)<tr/, '$1<tr data-imported style="position:fixed"');
        body = step('nav', body, /(class="[^"]*nowo-ui-pagination__nav[^"]*"[^>]*>\s*)</, '$1<span data-imported-nav style="position:fixed">n</span><');
        body = step('info', body, /(class="[^"]*nowo-ui-pagination__info[^"]*"[^>]*>)/, '$1<span data-imported-info style="position:fixed">i</span>');
        return step('confirm', body, /(data-nowo-ui-region="confirms"[^>]*>\s*<[a-z]+)/, '$1 data-imported-confirm style="position:fixed"');
      }),
    );
    await page.goto('/kit/2.23/pagination/infinite');
    await expect(page.locator('[data-controller~="list-pagination"]')).toHaveAttribute('data-nowo-ui-pagination-active', 'infinite');
    await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
    await expectBlocked(page, '[data-imported]');
    expect(changed).toEqual({ row: true, nav: true, info: true, confirm: true });
    await expectBlocked(page, '[data-imported-nav]');
    await expectBlocked(page, '[data-imported-info]');
    await expectBlocked(page, '[data-imported-confirm]');
  });

  test('with no such policy each style still applies, on its own element, past a growing custom element', async ({ page }) => {
    await withPolicy(page, '/kit/2.23/pagination/infinite', null);
    await page.route(/\/kit\/2\.23\/pagination\/infinite.*page=2/, (route) =>
      changeHtml(route, (html) =>
        html.replace(
          /(<tbody[^>]*>\s*<tr[^>]*>\s*<td[^>]*>)/,
          '$1<kit-e2e-grow id="grow" style="position:relative"></kit-e2e-grow><span id="after" style="position:sticky">a</span><span id="sib" style="position:absolute">s</span>',
        ),
      ),
    );
    await page.goto('/kit/2.23/pagination/infinite');
    await page.evaluate(() => {
      customElements.define(
        'kit-e2e-grow',
        class extends HTMLElement {
          constructor() {
            super();
            const added = document.createElement('i');
            added.className = 'added';
            this.prepend(added);
          }
        },
      );
    });
    await expect(page.locator('[data-controller~="list-pagination"]')).toHaveAttribute('data-nowo-ui-pagination-active', 'infinite');
    await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
    await expect(page.locator('#sib')).toBeAttached();
    const position = (selector: string): Promise<string> => page.locator(selector).first().evaluate((el) => getComputedStyle(el).position);
    expect(await position('#grow')).toBe('relative');
    expect(await position('#after')).toBe('sticky');
    expect(await position('#sib')).toBe('absolute');
    expect(await position('.added')).toBe('static');
  });
});
