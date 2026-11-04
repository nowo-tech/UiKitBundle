import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * UiKit 2.27 compact header on /demo/compact-header (contract .uic-work/uikit-compact-header/CONTRACT.md §11): axe in
 * light / dark and both presentations with the "…" panel open and closed; one line and no horizontal scroll at 320,
 * 375 and 1280 px; the phone rule ("‹" + the current level only); the Tab order and the two menus; the no-JavaScript
 * trail; landmarks (one breadcrumb nav, the hidden h1, the skip link); zero CSP violations; RTL.
 */
type Mode = 'light' | 'dark';

const COMPACT = '/demo/compact-header';
const STANDARD = '/demo/compact-header?presentation=standard';
const CURRENT = 'Q-1042 · Annual maintenance renewal for ACME Corporation';

/** Records every CSP violation of the page (the `securitypolicyviolation` event, from the first script on). */
async function watchCsp(page: Page): Promise<void> {
  await page.addInitScript(() => {
    const log: string[] = [];
    (window as unknown as { cspViolations: string[] }).cspViolations = log;
    document.addEventListener('securitypolicyviolation', (e) => log.push(`${e.violatedDirective} ${e.blockedURI} ${e.sourceFile}:${e.lineNumber} ${e.sample}`), true);
  });
}

async function cspViolations(page: Page): Promise<string[]> {
  return page.evaluate(() => (window as unknown as { cspViolations?: string[] }).cspViolations ?? ['watcher missing']);
}

async function setMode(page: Page, mode: Mode): Promise<void> {
  await page.evaluate((m) => {
    document.documentElement.setAttribute('data-theme', m);
    document.documentElement.setAttribute('data-bs-theme', m);
  }, mode);
}

async function axe(page: Page): Promise<string[]> {
  const result = await new AxeBuilder({ page }).include('[data-testid="compact-demo"]').analyze();
  return result.violations.map((v) => `${v.id}: ${v.nodes.map((n) => `${n.target.join(' ')} — ${n.failureSummary}`).join(', ')}`);
}

async function noHorizontalScroll(page: Page): Promise<void> {
  const overflow = await page.evaluate(() => {
    const header = document.querySelector('.nowo-ui-header') as HTMLElement;
    return {
      doc: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      header: header.scrollWidth - header.clientWidth,
    };
  });
  expect(overflow.doc).toBeLessThanOrEqual(0);
  expect(overflow.header).toBeLessThanOrEqual(0);
}

async function headerHeight(page: Page): Promise<number> {
  return (await page.locator('.nowo-ui-header').boundingBox())!.height;
}

const trail = (page: Page) => page.locator('[data-testid="header-context"] nav[aria-label="Breadcrumb"]');
const more = (page: Page) => page.getByRole('button', { name: 'Show full path' });

test.describe('compact header (2.27)', () => {
  test.use({ reducedMotion: 'reduce' });

  for (const mode of ['light', 'dark'] as Mode[]) {
    test(`axe: no violations in both presentations, "…" open and closed (${mode})`, async ({ page }) => {
      await page.setViewportSize({ width: 1280, height: 800 });
      await page.goto(COMPACT);
      await setMode(page, mode);
      await expect(trail(page)).toHaveAttribute('data-collapsed', 'true');
      expect(await axe(page)).toEqual([]);
      await more(page).click();
      await expect(page.locator('#nowo-ui-header-crumbs-panel')).toBeVisible();
      expect(await axe(page)).toEqual([]);
      await page.keyboard.press('Escape');

      await page.goto(STANDARD);
      await setMode(page, mode);
      expect(await axe(page)).toEqual([]);
    });
  }

  test('desktop: one line, the middle levels behind "…", ancestors truncated, CSP clean', async ({ page }) => {
    await watchCsp(page);
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto(COMPACT);
    const nav = trail(page);
    await expect(nav).toHaveAttribute('data-collapsed', 'true');
    await expect(more(page)).toBeVisible();
    await expect(nav.getByRole('link', { name: 'Sales' })).toBeVisible();
    await expect(nav.getByRole('link', { name: 'Quotes' })).toBeVisible();
    await expect(nav.locator('.nowo-ui-header-crumbs__item--middle').first()).toBeHidden();
    await expect(nav.locator('[aria-current="page"]')).toHaveText(CURRENT);
    await expect(page.locator('[data-nowo-ui-header-crumbs-up]')).toBeHidden();
    expect(await headerHeight(page)).toBeLessThan(60);
    await noHorizontalScroll(page);

    // Every ancestor is capped (--nowo-ui-header-crumbs-item-max) and ends with an ellipsis: show the middle levels.
    await page.evaluate(() => document.querySelector('[data-nowo-ui-header-crumbs]')!.removeAttribute('data-collapsed'));
    const long = nav.getByRole('link', { name: 'Customers of the northern region and partners' });
    await expect(long).toBeVisible();
    expect(await long.evaluate((el) => el.scrollWidth > el.clientWidth && getComputedStyle(el).textOverflow === 'ellipsis')).toBe(true);
    await expect(long).toHaveAttribute('title', 'Customers of the northern region and partners');
    expect(await headerHeight(page)).toBeLessThan(60);
    await noHorizontalScroll(page);
    await page.waitForLoadState('networkidle');
    await expect(page.locator('.sf-toolbar, [id^="sfwdt"]')).toHaveCount(0); // no dev tool injects inline code here
    expect(await cspViolations(page)).toEqual([]);
    // The watcher works: parsed inline style attributes are reported.
    await expect
      .poll(async () => {
        await page.evaluate(() => document.body.insertAdjacentHTML('beforeend', `<div data-csp-probe style="color: red">${Date.now()}</div>`));
        return (await cspViolations(page)).length;
      })
      .toBeGreaterThan(0);
  });

  for (const width of [375, 320]) {
    test(`phone ${width} px: "‹" and the current level only, truncated, no horizontal scroll`, async ({ page }) => {
      await watchCsp(page);
      await page.setViewportSize({ width, height: 740 });
      await page.goto(COMPACT);
      const nav = trail(page);
      const up = page.locator('[data-nowo-ui-header-crumbs-up]');
      await expect(up).toBeVisible();
      await expect(up).toHaveAttribute('href', /at=quotes/);
      await expect(up).toHaveAccessibleName('Up to Quotes');
      await expect(nav.getByRole('link', { name: 'Sales' })).toBeHidden();
      await expect(more(page)).toBeHidden();
      const current = nav.locator('[aria-current="page"]');
      await expect(current).toBeVisible();
      expect(await nav.locator('.nowo-ui-header-crumbs__item--current').evaluate((el) => getComputedStyle(el, '::before').content)).toBe('none');
      // Truncated on screen, complete for assistive technology (accessibility tree) and in the tooltip.
      await expect(current).toHaveAttribute('title', CURRENT);
      await expect(nav).toMatchAriaSnapshot(`
        - navigation "Breadcrumb":
          - link "Up to Quotes"
          - list:
            - listitem: ${CURRENT}
      `);
      expect(await current.evaluate((el) => el.scrollWidth > el.clientWidth && getComputedStyle(el).textOverflow === 'ellipsis')).toBe(true);
      await expect(page.getByTestId('page-actions').getByRole('button', { name: 'Page actions' })).toBeVisible();
      await expect(page.getByRole('link', { name: 'Home', exact: true })).toBeVisible();
      for (const id of ['control-search', 'control-help']) {
        await expect(page.getByTestId(id)).toBeVisible();
      }
      expect(await headerHeight(page)).toBeLessThan(60);
      await noHorizontalScroll(page);
      expect(await cspViolations(page)).toEqual([]);
    });
  }

  test('keyboard: Tab order, the "…" disclosure and the actions menu', async ({ page }) => {
    await watchCsp(page);
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto(COMPACT);
    await expect(trail(page)).toHaveAttribute('data-collapsed', 'true');
    await page.getByTestId('skip-link').focus();
    const expected: Array<[string, (p: Page) => ReturnType<Page['locator']>]> = [
      ['burger', (p) => p.locator('[data-nowo-ui-burger]')],
      ['brand', (p) => p.getByTestId('brand')],
      ['trigger', (p) => p.getByTestId('page-actions').getByRole('button', { name: 'Page actions' })],
      ['home', (p) => p.getByRole('link', { name: 'Home', exact: true })],
      ['first level', (p) => trail(p).getByRole('link', { name: 'Sales' })],
      ['…', (p) => more(p)],
      ['parent', (p) => trail(p).getByRole('link', { name: 'Quotes' })],
      ['search', (p) => p.getByTestId('control-search')],
      ['help', (p) => p.getByTestId('control-help')],
      ['theme', (p) => p.locator('[data-nowo-ui-theme-toggle]')],
    ];
    for (const [name, locate] of expected) {
      await page.keyboard.press('Tab');
      await expect(locate(page), name).toBeFocused();
    }

    // "…": opens the panel, Escape closes it and returns focus, a link inside navigates.
    await more(page).focus();
    await page.keyboard.press('Enter');
    const panel = page.locator('#nowo-ui-header-crumbs-panel');
    await expect(panel).toBeVisible();
    await expect(more(page)).toHaveAttribute('aria-expanded', 'true');
    await expect(panel.getByRole('link')).toHaveText(['Customers of the northern region and partners', 'ACME Corporation']);
    await page.keyboard.press('Escape');
    await expect(panel).toBeHidden();
    await expect(more(page)).toBeFocused();
    await expect(more(page)).toHaveAttribute('aria-expanded', 'false');
    await page.keyboard.press('Enter');
    // Placed under "…" (start edges aligned) by nowo-ui-popover.js, through the CSSOM: no CSP violation.
    await expect.poll(async () => (await panel.boundingBox())!.y).toBeGreaterThan((await more(page).boundingBox())!.y);
    expect(Math.abs((await panel.boundingBox())!.x - (await more(page).boundingBox())!.x)).toBeLessThan(2);
    expect(await cspViolations(page)).toEqual([]);
    await panel.getByRole('link', { name: 'ACME Corporation' }).click();
    await expect(page).toHaveURL(/at=acme/);

    // The page-actions menu: opens, lists the actions, Escape returns focus to the trigger.
    const trigger = page.getByTestId('page-actions').getByRole('button', { name: 'Page actions' });
    await trigger.focus();
    await page.keyboard.press('Enter');
    const actions = page.locator('#nowo-ui-page-actions-panel');
    await expect(actions).toBeVisible();
    await expect.poll(async () => (await actions.boundingBox())!.y).toBeGreaterThan((await trigger.boundingBox())!.y);
    expect(Math.abs((await actions.boundingBox())!.x - (await trigger.boundingBox())!.x)).toBeLessThan(2);
    await expect(actions.getByRole('button', { name: 'Delete' })).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(actions).toBeHidden();
    await expect(trigger).toBeFocused();
    // The confirm action opens its dialog, rendered outside the popover.
    await trigger.click();
    await actions.getByRole('button', { name: 'Delete' }).click();
    await expect(page.getByRole('dialog', { name: 'Delete Q-1042?' })).toBeVisible();
    expect(await cspViolations(page)).toEqual([]);
  });

  test('without JavaScript every level shows on one line and "…" is not exposed', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 1280, height: 800 } });
    const page = await context.newPage();
    await page.goto(COMPACT);
    const nav = trail(page);
    await expect(nav).not.toHaveAttribute('data-collapsed', 'true');
    for (const name of ['Sales', 'Customers of the northern region and partners', 'ACME Corporation', 'Quotes']) {
      await expect(nav.getByRole('link', { name })).toBeVisible();
    }
    await expect(more(page)).toHaveCount(0); // hidden: not in the accessibility tree
    expect(await headerHeight(page)).toBeLessThan(60);
    await noHorizontalScroll(page);

    await page.setViewportSize({ width: 375, height: 740 });
    await expect(page.locator('[data-nowo-ui-header-crumbs-up]')).toBeVisible();
    await expect(nav.getByRole('link', { name: 'Sales' })).toBeHidden();
    await expect(nav.locator('[aria-current="page"]')).toBeVisible();
    await noHorizontalScroll(page);
    await context.close();
  });

  test('landmarks: one breadcrumb nav, the h1 visually hidden, the skip link to main', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto(COMPACT);
    await expect(page.locator('nav[aria-label="Breadcrumb"]')).toHaveCount(1);
    const h1 = page.locator('main h1');
    await expect(h1).toHaveCount(1);
    await expect(h1).toHaveText(CURRENT);
    await expect(h1).toHaveClass(/visually-hidden/);
    expect(await h1.evaluate((el) => el.getBoundingClientRect().width)).toBeLessThanOrEqual(1);
    await expect(page.getByRole('heading', { level: 1, name: CURRENT })).toHaveCount(1);
    await expect(page.locator('[data-nowo-ui-page-header-empty]')).toHaveCount(0); // the status badge stays
    await expect(page.locator('main').getByText('Sent', { exact: true })).toBeVisible();
    await page.getByTestId('skip-link').focus();
    await page.keyboard.press('Enter');
    await expect(page.locator('main#main-content')).toBeFocused();

    await page.goto(STANDARD);
    await expect(page.locator('nav[aria-label="Breadcrumb"]')).toHaveCount(1);
    await expect(page.locator('[data-nowo-ui-header-context]')).toHaveCount(0);
    await expect(page.locator('main h1')).not.toHaveClass(/visually-hidden/);
    await expect(page.locator('main').getByText('Quote sent on 2 November', { exact: false })).toBeVisible();
    await expect(page.locator('[data-nowo-ui-header-more]')).toHaveCount(1); // the split header's "more" menu
  });

  test('RTL: the trail is mirrored and the up glyph flipped', async ({ page }) => {
    await watchCsp(page);
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto(`${COMPACT}?dir=rtl`);
    const nav = trail(page);
    const first = (await nav.getByRole('link', { name: 'Sales' }).boundingBox())!;
    const parent = (await nav.getByRole('link', { name: 'Quotes' }).boundingBox())!;
    expect(first.x).toBeGreaterThan(parent.x);
    await noHorizontalScroll(page);

    await page.setViewportSize({ width: 375, height: 740 });
    const glyph = page.locator('.nowo-ui-header-crumbs__up-glyph');
    await expect(glyph).toBeVisible();
    expect(await nav.locator('.nowo-ui-header-crumbs__item--current').evaluate((el) => getComputedStyle(el, '::before').content)).toBe('none');
    expect(await glyph.evaluate((el) => getComputedStyle(el).transform)).toBe('matrix(-1, 0, 0, 1, 0, 0)');
    await noHorizontalScroll(page);
    expect(await cspViolations(page)).toEqual([]);
  });
});
