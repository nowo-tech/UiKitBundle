import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * UiKit 2.60 demo platform shell (/kit/demo-platform): the layout every nowo-tech bundle demo extends. Landmarks and
 * skip link, the current nav item, the locale pills, the theme toggle, the phone drawer, no horizontal overflow, and
 * axe (wcag2a / 2aa / 21a / 21aa, colour contrast included: serious + critical = 0) in light and dark, desktop and phone.
 */
type Theme = 'light' | 'dark';

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

/** The demo layout + style rules (docs/USAGE.md "Demo platform shell"; same function as the rollout brief's spec): layout grammar,
 * no demo styles / host tokens / framework classes, closed drawer inert, 44 px targets on coarse pointers, input borders 3:1. */
/* eslint-disable */
// prettier-ignore
function demoPlatformRules(): string[] {
  const issues = [];
  const main = document.querySelector('#nowo-ui-content');
  const shell = document.querySelector('[data-testid="demo-platform"]');
  if (!main || !shell) return ['no-demo-platform-shell'];
  const visible = (el) => !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
  const carded = (el) => el.closest('.nowo-ui-card, [data-nowo-ui-region="table"], [data-nowo-ui-region="form"], .nowo-ui-form-panel, dialog');
  const skip = (el) => el.closest('dialog, [data-nowo-ui-region="filters"], .nowo-ui-filters, .nowo-ui-page-header');

  // Layout grammar.
  main.querySelectorAll('table').forEach((t) => { if (!carded(t)) issues.push('table-outside-card'); });
  main.querySelectorAll('form').forEach((f) => {
    if (f.querySelector('input:not([type="hidden"]), select, textarea') && !carded(f) && !skip(f) && !f.closest('table')) issues.push('form-outside-card');
  });
  const primaries = [...main.querySelectorAll('.nowo-ui-btn--primary')].filter((b) => visible(b) && !b.closest('table, dialog, [data-nowo-ui-region="filters"], .nowo-ui-filters, form'));
  if (primaries.length > 1) issues.push(`primary-buttons:${primaries.length}`);
  const header = main.querySelector('.nowo-ui-page-header');
  const flashes = main.querySelector('.nowo-ui-flashes');
  if (header && flashes && (flashes.compareDocumentPosition(header) & Node.DOCUMENT_POSITION_FOLLOWING)) issues.push('flashes-before-page-header');
  if (document.documentElement.scrollWidth > document.documentElement.clientWidth + 1) issues.push('page-scrolls-horizontally');

  // Styles: no demo styles, no host theme tokens, no CDN / foreign stylesheet, no framework classes inside the shell.
  document.querySelectorAll('style').forEach((s) => {
    if (s.matches('[data-nowo-ui-theme-tokens], [data-nowo-ui-appearance]')) { issues.push('host-theme-tokens'); return; }
    if (!/sf-toolbar|sfwdt|sf-minitoolbar|sf-display/.test(s.textContent || '') && !s.closest('[id^="sfwdt"], .sf-toolbar')) issues.push('style-element');
  });
  document.querySelectorAll('link[rel="stylesheet"]').forEach((l) => {
    const path = new URL(l.href, location.href).pathname;
    if (!path.startsWith('/bundles/') && !path.startsWith('/_wdt/') && !path.startsWith('/_profiler/')) issues.push(`stylesheet:${path}`);
  });
  const framework = /^(acme-.*|nav-tabs|nav-link|nav-item|alert|alert-.*|form-check.*|form-control|form-select|form-label|btn|btn-.*|card|card-.*|badge|row|col|col-.*|table|table-.*)$/;
  shell.querySelectorAll('[class]').forEach((el) => {
    for (const c of el.classList) { if (framework.test(c)) { issues.push(`framework-class:${c}`); break; } }
  });

  // Drawer: below 901 px a closed drawer is inert and the burger says so.
  const aside = shell.querySelector('[data-nowo-ui-aside]');
  if (aside && window.innerWidth < 901) {
    const open = shell.classList.contains('is-aside-open-mobile');
    if (!open && !aside.hasAttribute('inert')) issues.push('drawer-not-inert');
    shell.querySelectorAll('[data-nowo-ui-burger]').forEach((b) => {
      if (visible(b) && b.getAttribute('aria-expanded') !== (open ? 'true' : 'false')) issues.push('burger-state');
    });
  }

  // Touch: 44 px targets on coarse pointers (checkbox / radio 24 px, their label carries the 44 px row).
  if (window.matchMedia('(pointer: coarse)').matches) {
    const controls = [...shell.querySelectorAll('.nowo-ui-btn, button, summary, input:not([type="hidden"]), select, textarea, [role="tab"], .nowo-ui-aside__link, .nowo-ui-demo-platform__locale')]
      .filter((el) => visible(el) && !el.closest('[inert], table, .ts-control'));
    for (const el of controls) {
      const r = el.getBoundingClientRect();
      const small = el.matches('input[type="checkbox"], input[type="radio"]') ? r.height < 24 || r.width < 24 : r.height < 43.5;
      if (small) { issues.push(`target-under-44px:${(el.className && String(el.className).split(' ')[0]) || el.tagName.toLowerCase()}`); }
    }
  }

  // Inputs: boundary 3:1 or more against the surface behind them (WCAG 1.4.11).
  const rgb = (c) => { const m = c.match(/[\d.]+/g); return m ? m.slice(0, 4).map(Number) : null; };
  const lum = (c) => { const v = c.slice(0, 3).map((x) => { const s = x / 255; return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4; }); return 0.2126 * v[0] + 0.7152 * v[1] + 0.0722 * v[2]; };
  const bgOf = (el) => { for (let n = el.parentElement; n; n = n.parentElement) { const c = rgb(getComputedStyle(n).backgroundColor); if (c && (c.length < 4 || c[3] > 0)) return c; } return [255, 255, 255]; };
  shell.querySelectorAll('input:not([type="hidden"], [type="checkbox"], [type="radio"]), select, textarea').forEach((el) => {
    if (!visible(el) || el.closest('[inert]')) return;
    const border = rgb(getComputedStyle(el).borderTopColor);
    if (!border) return;
    const a = lum(border); const b = lum(bgOf(el));
    if ((Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05) < 3) issues.push(`input-border-under-3:1:${el.id || el.name || el.tagName.toLowerCase()}`);
  });

  return [...new Set(issues)];
}

test.describe('Demo platform shell', () => {
  test('regions, current item and skip link', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/kit/demo-platform/messages');
    const shell = page.getByTestId('demo-platform');
    await expect(shell).toBeVisible();
    await expect(page.getByTestId('demo-platform-brand')).toContainText('nowo · bundles');
    await expect(page.getByTestId('demo-platform-bundle')).toHaveText('Acme Inbox');
    await expect(page.getByRole('banner')).toBeVisible();
    await expect(page.getByRole('complementary', { name: 'Demo navigation' })).toBeVisible();
    await expect(page.getByRole('main')).toBeVisible();
    await expect(page.getByRole('contentinfo')).toContainText('Source code');
    await expect(page.locator('[aria-current="page"]')).toHaveCount(1);
    await expect(page.locator('[aria-current="page"]')).toHaveText('Messages');
    await expect(page.getByRole('navigation', { name: 'Inbox' })).toBeVisible();
    await expect(page.getByTestId('demo-platform-locales').locator('[aria-current="true"]')).toHaveAttribute('lang', 'en');

    await page.keyboard.press('Tab');
    const skip = page.locator('.nowo-ui-skip-link');
    await expect(skip).toBeFocused();
    await expect(skip).toBeInViewport();
    await skip.press('Enter');
    await expect(page.locator('#nowo-ui-content')).toBeFocused();

    const font = await page.evaluate(() => getComputedStyle(document.body).fontFamily);
    expect(font).not.toMatch(/^(Times|serif)/i);
  });

  test('theme toggle switches to dark', async ({ page }) => {
    await page.goto('/kit/demo-platform');
    await page.evaluate(() => localStorage.removeItem('nowo-ui-theme'));
    const before = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
    await page.locator('[data-nowo-ui-theme-toggle]').click();
    await expect(page.locator('html')).not.toHaveAttribute('data-theme', before ?? '');
  });

  test('phone: the aside is a drawer behind the burger, no horizontal overflow', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/kit/demo-platform');
    const aside = page.locator('#nowo-ui-demo-aside');
    await expect(aside).not.toBeInViewport();
    await expect(aside).toHaveAttribute('inert', '');
    const burger = page.locator('[data-nowo-ui-burger]').first();
    await expect(burger).toHaveAttribute('aria-expanded', 'false');
    await burger.focus();
    await page.keyboard.press('Enter');
    await expect(page.getByTestId('demo-platform')).toHaveClass(/is-aside-open-mobile/);
    await expect(aside).toBeInViewport();
    await expect(aside).not.toHaveAttribute('inert', '');
    await expect(burger).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('#nowo-ui-demo-aside a:focus')).toHaveCount(1);
    await expect(page.locator('html')).toHaveClass(/nowo-ui-drawer-open/);
    await expect(page.locator('.nowo-ui-main')).toHaveAttribute('inert', '');
    await page.keyboard.press('Escape');
    await expect(burger).toBeFocused();
    await expect(aside).toHaveAttribute('inert', '');
    await expect(page.locator('html')).not.toHaveClass(/nowo-ui-drawer-open/);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(0);
  });

  test('layout grammar: sections, states, one primary, no page scroll on phones', async ({ page }) => {
    for (const width of [1440, 390]) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto('/kit/demo-platform/messages');
      expect(await page.evaluate(demoPlatformRules)).toEqual([]);
      for (const group of ['layout', 'cards-tables', 'forms', 'feedback', 'navigation', 'dialogs', 'badges-tokens']) {
        await page.goto(`/catalogue/${group}`);
        expect(await page.evaluate(demoPlatformRules), group).toEqual([]);
      }
    }
    await page.goto('/kit/demo-platform/messages');
    await expect(page.getByTestId('kit-demo-platform-table').getByRole('region', { name: 'Latest messages' })).toBeVisible();
    await expect(page.getByTestId('kit-demo-platform-empty')).toContainText('No archived messages yet.');
    await expect(page.locator('.nowo-ui-demo-section__body:has(> [data-nowo-ui-demo-state="loading"])')).toHaveAttribute('aria-busy', 'true');
    await expect(page.locator('[data-nowo-ui-demo-state="error"]')).toHaveAttribute('role', 'alert');
  });

  test('primary button hover keeps AA contrast (2.60 token fix)', async ({ page }) => {
    for (const theme of ['light', 'dark'] as Theme[]) {
      await page.goto('/kit/demo-platform');
      await setTheme(page, theme);
      const primary = page.locator('.nowo-ui-page-header .nowo-ui-btn--primary').first();
      await primary.hover();
      const result = await new AxeBuilder({ page }).include('.nowo-ui-page-header').withRules(['color-contrast']).analyze();
      expect(result.violations.map((v) => v.id)).toEqual([]);
    }
  });

  for (const theme of ['light', 'dark'] as Theme[]) {
    for (const [name, width] of [['desktop', 1440], ['phone', 390]] as const) {
      test(`axe clean: ${name}, ${theme}`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/kit/demo-platform/settings');
        await setTheme(page, theme);
        expect(await axe(page)).toEqual([]);
      });
    }
  }
});
