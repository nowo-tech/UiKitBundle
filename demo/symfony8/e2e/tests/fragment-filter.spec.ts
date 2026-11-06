import { test, expect, type Page, type Route } from '@playwright/test';

/**
 * Audit Appendix GT: the fragment filter in a real browser, through the built js/nowo-ui-dialog.js, on both dialog paths
 * (remote form, plain data-nowo-ui-dialog-src). The page's CSP is taken off for these tests, so a payload that survives
 * the filter runs and is seen; the demo policy itself is checked last. Fragments are fulfilled by the test.
 */
const PAGE = '/kit/remote-form';
const FRAGMENT = '/kit/gt-fragment';

/** A payload that runs requests this URL, which the test counts. */
const run = "fetch('/gt-beacon')";

const PAYLOADS: Record<string, { html: string; click?: boolean; clone?: boolean }> = {
  'iframe javascript:': { html: `<iframe src="javascript:parent.${run}"></iframe>` },
  'iframe with a tab in the scheme': { html: `<iframe src=" java&#9;script:parent.${run}"></iframe>` },
  'iframe in mixed case': { html: `<IFRAME SRC="JaVaScRiPt:parent.${run}"></IFRAME>` },
  'iframe in foreignObject': { html: `<svg><foreignObject><iframe src="javascript:parent.${run}"></iframe></foreignObject></svg>` },
  'iframe srcdoc': { html: `<iframe srcdoc="<script>parent.${run}</script>"></iframe>` },
  'img onerror': { html: `<img src="/gt-missing.png" onerror="${run}">` },
  'details ontoggle': { html: `<details open ontoggle="${run}">x</details>` },
  'link javascript:': { html: `<a id="gt-go" href="javascript:${run}">go</a>`, click: true },
  'svg link javascript:': { html: `<svg width="50" height="50"><a id="gt-go" href="javascript:${run}"><rect width="50" height="50"></rect></a></svg>`, click: true },
  'svg href animation': { html: `<svg width="50" height="50"><a id="gt-go"><animate attributeName="href" to="javascript:${run}" fill="freeze" dur="0.01s"></animate><rect width="50" height="50"></rect></a></svg>`, click: true },
  'form action javascript:': { html: `<form method="post" action="javascript:${run}"><button id="gt-go">go</button></form>`, click: true },
  'button formaction javascript:': { html: `<form method="post" data-nowo-ui-remote-form-native><button id="gt-go" formaction="javascript:${run}">go</button></form>`, click: true },
  'nested template cloned by a script': { html: `<template id="gt-t"><img src="/gt-missing.png" onerror="${run}"></template>`, clone: true },
};

async function prepare(page: Page, fragment: (route: Route) => Promise<void>, keepCsp = false): Promise<{ beacons: () => number }> {
  let beacons = 0;
  await page.route('**/_wdt/**', (route) => route.abort());
  await page.route('**/gt-beacon', (route) => {
    beacons += 1;
    return route.fulfill({ status: 204 });
  });
  if (!keepCsp) {
    await page.route(`**${PAGE}`, async (route) => {
      const response = await route.fetch();
      const headers = { ...response.headers() };
      delete headers['content-security-policy'];
      await route.fulfill({ response, headers });
    });
  }
  await page.route(`**${FRAGMENT}`, fragment);
  await page.goto(PAGE);

  return { beacons: () => beacons };
}

/** Adds an opener for the fragment URL on the demo's dialog (the form path, or the plain path without the form marker). */
async function openFragment(page: Page, mode: 'form' | 'plain', link = true): Promise<void> {
  await page.evaluate(
    ([url, kind, asLink]) => {
      const opener = document.createElement(asLink ? 'a' : 'button');
      opener.id = 'gt-open';
      if (opener instanceof HTMLAnchorElement) {
        opener.href = url as string;
      }
      opener.textContent = 'open';
      opener.setAttribute('data-nowo-ui-dialog-open', 'destination-dialog');
      opener.setAttribute('data-nowo-ui-dialog-src', url as string);
      if (kind === 'form') {
        opener.setAttribute('data-nowo-ui-dialog-form', '');
      }
      document.querySelector('main')?.append(opener);
    },
    [FRAGMENT, mode, link] as const,
  );
  await page.locator('#gt-open').click();
}

const html = (body: string) => (route: Route) => route.fulfill({ status: 200, contentType: 'text/html; charset=UTF-8', body: `<p data-testid="gt-ok">fragment</p>${body}` });

for (const mode of ['form', 'plain'] as const) {
  test.describe(`fragment filter, ${mode} path`, () => {
    for (const [name, payload] of Object.entries(PAYLOADS)) {
      test(`does not run: ${name}`, async ({ page }) => {
        const { beacons } = await prepare(page, html(payload.html));
        await openFragment(page, mode);
        await expect(page.getByTestId('gt-ok')).toBeVisible();
        if (payload.click) {
          await page.locator('#gt-go').click({ force: true, timeout: 3000 });
        }
        if (payload.clone) {
          await page.evaluate(() => document.body.append((document.getElementById('gt-t') as HTMLTemplateElement).content.cloneNode(true)));
        }
        await page.waitForTimeout(300);
        expect(beacons()).toBe(0);
      });
    }

    for (const name of ['attributes', 'removeAttribute', 'getAttributeNames', 'getAttribute', 'remove', 'matches', 'localName', 'tagName', 'children', 'parentNode', 'querySelectorAll', 'content']) {
      test(`GT-L3: a control named ${name} does not hide a form`, async ({ page }) => {
        const form = (id: string) => `<form id="${id}" onfocusin="${run}" onsubmit="${run}" action="javascript:${run}"><input name="${name}" autofocus><button id="${id}-go">go</button></form>`;
        const { beacons } = await prepare(page, html(`${form('gt-c')}<template id="gt-t">${form('gt-n')}<template id="gt-i">${form('gt-d')}</template></template>`));
        await openFragment(page, mode);
        // No locators from here on: Playwright's own engine reads form.matches, getAttribute and parentNode.
        await page.waitForFunction(() => document.querySelector('[data-testid="gt-ok"]') !== null);
        // Through the prototype: the test must not be fooled by the same control.
        const seen = await page.evaluate(() => {
          const names = (element: Element | null | undefined) => Element.prototype.getAttributeNames.call(element as Element);
          const outer = (document.getElementById('gt-t') as HTMLTemplateElement).content;
          const inner = (outer.querySelector('#gt-i') as HTMLTemplateElement).content;
          return [names(document.getElementById('gt-c')), names(outer.querySelector('#gt-n')), names(inner.querySelector('#gt-d'))];
        });
        expect(seen).toEqual([['id'], ['id'], ['id']]);
        await page.evaluate(() => {
          HTMLElement.prototype.focus.call(document.querySelector('#gt-c input') as HTMLElement);
          HTMLElement.prototype.click.call(document.getElementById('gt-c-go') as HTMLElement);
        });
        await page.waitForTimeout(300);
        expect(beacons()).toBe(0);
      });
    }

    test('a legitimate form fragment works: fields, other-origin link, http(s) frame', async ({ page }) => {
      await prepare(page, html('<form method="post" action="/kit/remote-form/new"><label for="gt-n">Name</label><input id="gt-n" name="n"><a id="gt-a" href="mailto:a@example.com">mail</a><iframe id="gt-f" src="/kit/remote-form/new"></iframe><button type="submit">Save</button></form>'));
      await openFragment(page, mode);
      await expect(page.locator('#gt-n')).toBeVisible();
      await expect(page.locator('#gt-a')).toHaveAttribute('href', 'mailto:a@example.com');
      await expect(page.locator('#gt-f')).toHaveAttribute('src', '/kit/remote-form/new');
    });
  });
}

test.describe('plain path answers', () => {
  test('meta refresh does not leave the page', async ({ page }) => {
    await prepare(page, html('<meta http-equiv="refresh" content="0;url=/kit/shell">'));
    await openFragment(page, 'plain');
    await expect(page.getByTestId('gt-ok')).toBeVisible();
    await page.waitForTimeout(500);
    await expect(page).toHaveURL(new RegExp(`${PAGE}$`));
  });

  test('text/plain is not parsed as HTML: the link is followed', async ({ page }) => {
    let served = 0;
    const { beacons } = await prepare(page, (route) => {
      served += 1;
      return route.fulfill({ status: 200, contentType: 'text/plain', headers: { 'x-content-type-options': 'nosniff' }, body: `<img src="/gt-missing.png" onerror="${run}">` });
    });
    await openFragment(page, 'plain');
    await expect(page).toHaveURL(new RegExp(`${FRAGMENT}$`));
    expect(served).toBeGreaterThan(0);
    expect(beacons()).toBe(0);
  });

  test('a redirect to another origin is not inserted', async ({ page }) => {
    await prepare(page, (route) => route.fulfill({ status: 302, headers: { location: 'http://other.invalid/evil', 'access-control-allow-origin': '*' } }));
    await page.route('http://other.invalid/**', (route) => route.fulfill({ status: 200, contentType: 'text/html', body: '<p data-testid="gt-third-party">third party</p>' }));
    // A button: no link to fall back to, so the page stays and the dialog can be read.
    await openFragment(page, 'plain', false);
    await page.waitForTimeout(500);
    await expect(page).toHaveURL(new RegExp(`${PAGE}$`));
    await expect(page.getByTestId('gt-third-party')).toHaveCount(0);
  });
});

/**
 * In dev the profiler adds a nonce to the header and can drop it for the rest of a worker's life, so the policy is only
 * asserted when it is there. A run with APP_ENV=prod gets the literal header.
 */
test('the demo policy has script-src self and the page runs with no violation', async ({ page }) => {
  const violations: string[] = [];
  page.on('console', (msg) => {
    if (/Content Security Policy/i.test(msg.text())) violations.push(msg.text());
  });
  await page.route('**/_wdt/**', (route) => route.abort());
  const response = await page.goto(PAGE);
  const policy = response?.headers()['content-security-policy'];
  if (policy !== undefined) {
    expect(policy).toContain("script-src 'self'");
  }
  await page.getByTestId('destination-add').click();
  await expect(page.getByTestId('destination-label')).toBeFocused();
  expect(violations).toEqual([]);
});
