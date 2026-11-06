import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * UiKit 2.32 full-height sidebar layout on /demo/sidebar-layout — the acceptance checks of Symfony Beacon's request
 * (BUNDLES-REQUESTS/2026-11-06-uikit-sidebar-layout.md, Beacon e2e/account/sidebar-layout.spec.ts) against the kit
 * demo: geometry at 1440 / 1280 / 1024 px (expanded and icons-only rail, end-of-page and fixed footer), the
 * hover-expanded rail over the header with its own burger, the hidden aside, phones (390 px), the default below-header
 * layout, the compact header, axe (wcag2a / 2aa / 21a / 21aa: serious + critical = 0) in light and dark.
 */
type Theme = 'light' | 'dark';
type Rect = { left: number; right: number; top: number; bottom: number; width: number; height: number };

const URL = '/demo/sidebar-layout';

async function setTheme(page: Page, theme: Theme): Promise<void> {
  await page.evaluate((t) => {
    document.documentElement.setAttribute('data-theme', t);
    document.documentElement.setAttribute('data-bs-theme', t);
  }, theme);
}

async function axe(page: Page): Promise<string[]> {
  const result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
  return result.violations
    .filter((v) => v.impact === 'serious' || v.impact === 'critical')
    .map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`);
}

async function open(page: Page, query: string, width = 1280, height = 900): Promise<void> {
  await page.setViewportSize({ width, height });
  await page.goto(`${URL}${query}`);
  await expect(page.locator('[data-nowo-ui-shell]')).toBeVisible();
  // the shell script has run (it writes the mode it resolved)
  await expect(page.locator('[data-nowo-ui-shell]')).toHaveAttribute('data-nowo-ui-sidebar-mode', /expanded|rail|hidden/);
}

/** Boxes of the shell parts; null for an element that is not rendered (display: none). */
async function boxes(page: Page): Promise<Record<string, Rect | null>> {
  return page.evaluate(() => {
    const rect = (sel: string): Rect | null => {
      const el = document.querySelector(sel);
      if (!el || el.getClientRects().length === 0) {
        return null;
      }
      const r = el.getBoundingClientRect();
      return { left: r.left, right: r.right, top: r.top, bottom: r.bottom, width: r.width, height: r.height };
    };
    return {
      aside: rect('.nowo-ui-aside'),
      header: rect('.nowo-ui-header'),
      footer: rect('.nowo-ui-footer'),
      main: rect('.nowo-ui-main'),
      headerStart: rect('.nowo-ui-header__start'),
      headerBurger: rect('.nowo-ui-header__start [data-nowo-ui-burger]'),
      asideBurger: rect('[data-nowo-ui-aside-head] [data-nowo-ui-burger]'),
      brandIcon: rect('[data-nowo-ui-aside-head] .nowo-ui-aside-brand__icon'),
      brandName: rect('[data-nowo-ui-aside-head] .nowo-ui-aside-brand__name'),
    };
  });
}

/** Whether a node is painted: rendered, visible, not clipped to a 1 px box. */
async function painted(page: Page, selector: string): Promise<boolean> {
  return page.locator(selector).first().evaluate((el) => {
    const r = el.getBoundingClientRect();
    const s = getComputedStyle(el);
    return r.width > 1 && r.height > 1 && s.visibility !== 'hidden' && Number(s.opacity) > 0 && s.clipPath !== 'inset(50%)';
  });
}

async function noHorizontalScroll(page: Page): Promise<void> {
  expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(0);
}

async function settle(page: Page): Promise<void> {
  // the aside slides / rails with a 0.36s transition
  await page.waitForTimeout(450);
}

test.describe('full-height sidebar layout (2.32)', () => {
  for (const width of [1440, 1280, 1024]) {
    for (const mode of ['expanded', 'rail'] as const) {
      for (const footer of ['end', 'fixed'] as const) {
        test(`${width}px, ${mode}, ${footer} footer: geometry`, async ({ page }) => {
          const query = `?layout=full-height${mode === 'rail' ? '&mode=rail' : ''}${footer === 'fixed' ? '&footer=fixed' : ''}`;
          await open(page, query, width);
          await settle(page);
          const b = await boxes(page);
          expect(b.aside!.top).toBe(0);
          expect(Math.abs(b.header!.left - b.aside!.right)).toBeLessThanOrEqual(1);
          expect(Math.abs(b.footer!.left - b.aside!.right)).toBeLessThanOrEqual(1);
          expect(b.main!.left).toBeGreaterThanOrEqual(b.aside!.right - 1);
          // the burger is painted inside the aside's top strip (the header's row)
          expect(b.headerStart).toBeNull();
          expect(b.asideBurger).not.toBeNull();
          expect(b.asideBurger!.left).toBeGreaterThanOrEqual(b.aside!.left);
          expect(b.asideBurger!.right).toBeLessThanOrEqual(b.aside!.right + 1);
          expect(b.asideBurger!.bottom).toBeLessThanOrEqual(b.header!.bottom);
          if (mode === 'expanded') {
            const iconMid = (b.brandIcon!.top + b.brandIcon!.bottom) / 2;
            const burgerMid = (b.asideBurger!.top + b.asideBurger!.bottom) / 2;
            expect(Math.abs(iconMid - burgerMid)).toBeLessThanOrEqual(4);
            expect(b.brandIcon!.left).toBeGreaterThanOrEqual(b.asideBurger!.right);
            expect(await painted(page, '[data-nowo-ui-aside-head] .nowo-ui-aside-brand__name')).toBe(true);
          } else {
            expect(b.brandIcon!.top).toBeGreaterThanOrEqual(b.asideBurger!.bottom);
            const iconCentre = (b.brandIcon!.left + b.brandIcon!.right) / 2;
            const burgerCentre = (b.asideBurger!.left + b.asideBurger!.right) / 2;
            expect(Math.abs(iconCentre - burgerCentre)).toBeLessThanOrEqual(1);
            expect(await painted(page, '[data-nowo-ui-aside-head] .nowo-ui-aside-brand__name')).toBe(false);
            // still the brand link's name
            await expect(page.locator('[data-nowo-ui-aside-brand]')).toHaveAccessibleName(/Acme Billing/);
          }
          await noHorizontalScroll(page);
          if (footer === 'fixed') {
            // the fixed footer still starts at the aside's edge after scrolling
            await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
            const after = await boxes(page);
            expect(Math.abs(after.footer!.left - after.aside!.right)).toBeLessThanOrEqual(1);
            expect(after.footer!.bottom).toBeLessThanOrEqual(page.viewportSize()!.height + 1);
            // the aside head sticks to the top
            expect(after.asideBurger!.top).toBeGreaterThanOrEqual(0);
            expect(after.asideBurger!.bottom).toBeLessThanOrEqual(after.header!.bottom);
          }
        });
      }
    }
  }

  for (const theme of ['light', 'dark'] as Theme[]) {
    for (const mode of ['expanded', 'rail'] as const) {
      test(`axe clean: ${mode}, ${theme}`, async ({ page }) => {
        await open(page, `?layout=full-height${mode === 'rail' ? '&mode=rail' : ''}`, 1440);
        await setTheme(page, theme);
        await settle(page);
        expect(await axe(page)).toEqual([]);
      });
    }
  }

  test('hover-expanded rail goes over the header with its own burger; one click leaves the rail', async ({ page }) => {
    await open(page, '?layout=full-height&mode=rail&hover=1', 1440);
    const shell = page.locator('[data-nowo-ui-shell]');
    await expect(shell).toHaveAttribute('data-nowo-ui-sidebar-hover-expand', '');
    const before = await boxes(page);
    await page.locator('.nowo-ui-aside__link').first().hover();
    await expect(shell).toHaveClass(/is-aside-peek/);
    await settle(page);
    const b = await boxes(page);
    expect(b.aside!.width).toBeGreaterThan(200);
    // the content did not move
    expect(b.main!.left).toBe(before.main!.left);
    expect(b.header!.left).toBe(before.header!.left);
    // the nav icon column and the brand icon stay where the rail had them
    expect(Math.abs(b.brandIcon!.left - before.brandIcon!.left)).toBeLessThanOrEqual(1);
    expect(await painted(page, '[data-nowo-ui-aside-head] .nowo-ui-aside-brand__name')).toBe(true);
    // over the header: a point of the header row inside the panel belongs to the aside
    const headerRowY = (b.header!.top + b.header!.bottom) / 2;
    const inAside = await page.evaluate(([x, y]) => !!document.elementFromPoint(x, y)?.closest('[data-nowo-ui-aside]'), [before.aside!.right + 60, headerRowY]);
    expect(inAside).toBe(true);
    // the burger's spot is the panel's own burger, labelled
    const burgerHit = await page.evaluate(([x, y]) => {
      const el = document.elementFromPoint(x, y)?.closest('[data-nowo-ui-burger]');
      return el && el.closest('[data-nowo-ui-aside-head]') ? el.getAttribute('aria-label') : null;
    }, [(b.asideBurger!.left + b.asideBurger!.right) / 2, (b.asideBurger!.top + b.asideBurger!.bottom) / 2]);
    expect(burgerHit).toBeTruthy();
    expect(await axe(page)).toEqual([]);
    await setTheme(page, 'dark');
    expect(await axe(page)).toEqual([]);

    await page.locator('[data-nowo-ui-aside-head] [data-nowo-ui-burger]').click();
    await expect(shell).not.toHaveAttribute('data-nowo-ui-sidebar-mode', 'rail');
    await expect(shell).not.toHaveClass(/is-aside-peek/);
  });

  test('keyboard focus on the rail peeks; Escape closes it', async ({ page }) => {
    await open(page, '?layout=full-height&mode=rail&hover=1', 1440);
    const shell = page.locator('[data-nowo-ui-shell]');
    await page.locator('.nowo-ui-aside__link').first().focus();
    await expect(shell).toHaveClass(/is-aside-peek/);
    await page.keyboard.press('Escape');
    await expect(shell).not.toHaveClass(/is-aside-peek/);
  });

  test('hidden aside: the header starts at 0 with its burger and brand; the footer too', async ({ page }) => {
    await open(page, '?layout=full-height&mode=hidden', 1440);
    await settle(page);
    const b = await boxes(page);
    expect(b.header!.left).toBe(0);
    expect(b.footer!.left).toBe(0);
    expect(b.headerBurger).not.toBeNull();
    await expect(page.locator('.nowo-ui-header .nowo-ui-header__brand')).toBeVisible();
    await expect(page.locator('[data-nowo-ui-aside-head]')).toBeHidden();
    expect(await axe(page)).toEqual([]);
    await setTheme(page, 'dark');
    expect(await axe(page)).toEqual([]);
  });

  test('burgers hand over: hiding from the aside head focuses the header burger, and back', async ({ page }) => {
    await open(page, '?layout=full-height', 1280);
    const shell = page.locator('[data-nowo-ui-shell]');
    // expanded → rail → hidden (three-mode cycle), from the aside head burger
    const asideBurger = page.locator('[data-nowo-ui-aside-head] [data-nowo-ui-burger]');
    await asideBurger.focus();
    await page.keyboard.press('Enter');
    await expect(shell).toHaveAttribute('data-nowo-ui-sidebar-mode', 'rail');
    await page.keyboard.press('Enter');
    await expect(shell).toHaveAttribute('data-nowo-ui-sidebar-mode', 'hidden');
    const headerBurger = page.locator('.nowo-ui-header__start [data-nowo-ui-burger]');
    await expect(headerBurger).toBeFocused();
    await expect(headerBurger).toHaveAttribute('aria-expanded', 'false');
    await page.keyboard.press('Enter');
    await expect(shell).not.toHaveAttribute('data-nowo-ui-sidebar-mode', 'hidden');
    await expect(asideBurger).toBeFocused();
  });

  test('390 px: the phone layout is unchanged', async ({ page }) => {
    for (const layout of ['full-height', 'below-header']) {
      await open(page, `?layout=${layout}`, 390, 844);
      const b = await boxes(page);
      expect(b.header!.left).toBe(0);
      expect(b.aside!.right).toBeLessThanOrEqual(0);
      expect(b.headerBurger).not.toBeNull();
      await expect(page.locator('.nowo-ui-header .nowo-ui-header__brand')).toBeVisible();
      await expect(page.locator('[data-nowo-ui-aside-head]')).toBeHidden();
      await noHorizontalScroll(page);
      expect(await axe(page)).toEqual([]);
      await setTheme(page, 'dark');
      expect(await axe(page)).toEqual([]);
      await setTheme(page, 'light');
      // the drawer opens under the header, without the aside head
      await page.locator('.nowo-ui-header__start [data-nowo-ui-burger]').click();
      await expect(page.locator('[data-nowo-ui-shell]')).toHaveClass(/is-aside-open-mobile/);
      await settle(page);
      const open_ = await boxes(page);
      expect(open_.aside!.left).toBe(0);
      expect(open_.aside!.top).toBeGreaterThanOrEqual(open_.header!.bottom - 1);
      await expect(page.locator('[data-nowo-ui-aside-head]')).toBeHidden();
    }
  });

  test('below-header (default): header at 0, the aside under it, no aside head', async ({ page }) => {
    for (const query of ['?layout=below-header', '?layout=below-header&mode=rail&hover=1']) {
      await open(page, query, 1280);
      await settle(page);
      const b = await boxes(page);
      expect(b.header!.left).toBe(0);
      expect(b.aside!.top).toBeGreaterThanOrEqual(b.header!.bottom - 1);
      expect(b.headerBurger).not.toBeNull();
      await expect(page.locator('[data-nowo-ui-aside-head]')).toHaveCount(0);
      await expect(page.locator('[data-nowo-ui-shell]')).not.toHaveAttribute('data-nowo-ui-sidebar-layout', /.+/);
      await noHorizontalScroll(page);
    }
    // the hover-expanded rail also works below the header (over the page, not the header)
    await page.locator('.nowo-ui-aside__link').first().hover();
    await expect(page.locator('[data-nowo-ui-shell]')).toHaveClass(/is-aside-peek/);
    expect(await axe(page)).toEqual([]);
  });

  test('compact header: the context slot sits in the header next to the aside', async ({ page }) => {
    await open(page, '?layout=full-height&header=compact', 1280);
    await settle(page);
    const b = await boxes(page);
    await expect(page.locator('[data-testid="header-context"]')).toBeVisible();
    expect(Math.abs(b.header!.left - b.aside!.right)).toBeLessThanOrEqual(1);
    const ctx = await page.locator('[data-testid="header-context"]').boundingBox();
    expect(ctx!.x).toBeGreaterThanOrEqual(b.aside!.right);
    await noHorizontalScroll(page);
    expect(await axe(page)).toEqual([]);
  });
});
