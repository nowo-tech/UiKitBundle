import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * UiKit 2.61 admin components in the catalogue (/catalogue/admin/{group}): every page is axe-clean (wcag2a / 2aa /
 * 21a / 21aa, serious + critical = 0) at 375 and 1440 px in light and dark, follows the demo layout rules (no table
 * or form outside a card, one primary, no horizontal page scroll, no <style> or foreign stylesheet), and the
 * interactive components behave: filter sheet, bulk selection, density and columns, sortable headers, client tabs,
 * toasts, tooltip, dropzone, code copy, destructive confirm focus, detail drawer, error summary focus.
 * SHOTS_DIR set: full-page screenshots of every page at both widths and themes (the profiler toolbar is off here).
 */
type Theme = 'light' | 'dark';

const GROUPS = ['data-tables', 'data-display', 'form-patterns', 'overlays', 'navigation-patterns'] as const;
const WIDTHS = [375, 1440] as const;

async function open(page: Page, group: string, width: number, theme: Theme): Promise<void> {
  await page.setViewportSize({ width, height: width < 600 ? 812 : 900 });
  await page.emulateMedia({ colorScheme: theme, reducedMotion: 'reduce' });
  await page.goto(`/catalogue/admin/${group}`);
  await page.evaluate((t) => {
    document.documentElement.setAttribute('data-theme', t);
    document.documentElement.setAttribute('data-bs-theme', t);
  }, theme);
}

async function axe(page: Page): Promise<string[]> {
  const result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).exclude('.sf-toolbar').analyze();
  return result.violations
    .filter((v) => v.impact === 'serious' || v.impact === 'critical')
    .map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`);
}

/** Same rules as the rollout brief (docs/USAGE.md "Demo platform shell"). */
function demoPlatformRules(): string[] {
  const issues: string[] = [];
  const main = document.querySelector('#nowo-ui-content');
  if (!main || !document.querySelector('[data-testid="demo-platform"]')) return ['no-demo-platform-shell'];
  const carded = (el: Element) => el.closest('.nowo-ui-card, [data-nowo-ui-region="table"], [data-nowo-ui-region="form"], .nowo-ui-form-panel, dialog');
  const skip = (el: Element) => el.closest('dialog, [data-nowo-ui-region="filters"], .nowo-ui-filters, .nowo-ui-page-header');
  main.querySelectorAll('table').forEach((t) => { if (!carded(t)) issues.push('table-outside-card'); });
  main.querySelectorAll('form').forEach((f) => {
    if (f.querySelector('input:not([type="hidden"]), select, textarea') && !carded(f) && !skip(f) && !f.closest('table')) issues.push('form-outside-card');
  });
  const visible = (el: HTMLElement) => !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
  const primaries = [...main.querySelectorAll<HTMLElement>('.nowo-ui-btn--primary')].filter((b) => visible(b) && !b.closest('table, dialog, [data-nowo-ui-region="filters"], .nowo-ui-filters, form'));
  if (primaries.length > 1) issues.push(`primary-buttons:${primaries.length}`);
  if (document.documentElement.scrollWidth > document.documentElement.clientWidth + 1) issues.push('page-scrolls-horizontally');
  document.querySelectorAll('style').forEach((s) => {
    if (!/sf-toolbar|sfwdt|sf-minitoolbar|sf-display/.test(s.textContent || '') && !s.closest('[id^="sfwdt"], .sf-toolbar') && !s.matches('[data-nowo-ui-theme-tokens], [data-nowo-ui-appearance]')) issues.push('style-element');
  });
  document.querySelectorAll<HTMLLinkElement>('link[rel="stylesheet"]').forEach((l) => {
    const path = new URL(l.href, location.href).pathname;
    if (!path.startsWith('/bundles/') && !path.startsWith('/_wdt/') && !path.startsWith('/_profiler/')) issues.push(`stylesheet:${path}`);
  });
  if (document.querySelector('.sf-toolbar, .sf-minitoolbar, [id^="sfwdt"]')) issues.push('debug-toolbar');
  return [...new Set(issues)];
}

test.describe('Admin components catalogue', () => {
  for (const group of GROUPS) {
    for (const theme of ['light', 'dark'] as Theme[]) {
      for (const width of WIDTHS) {
        test(`${group}: axe + layout rules at ${width} px, ${theme}`, async ({ page }) => {
          await open(page, group, width, theme);
          expect(await page.evaluate(demoPlatformRules)).toEqual([]);
          expect(await axe(page)).toEqual([]);
          if (process.env.SHOTS_DIR) {
            await page.screenshot({ path: `${process.env.SHOTS_DIR}/${group}-${width}-${theme}.png`, fullPage: true, animations: 'disabled' });
          }
        });
      }
    }
  }

  test('data tables: phone filter sheet keeps the same fields and returns focus', async ({ page }) => {
    await open(page, 'data-tables', 375, 'light');
    const toggle = page.locator('[data-nowo-ui-filter-sheet-open]');
    await expect(toggle).toBeVisible();
    await expect(page.locator('#catalogue-filters > [data-nowo-ui-filter-sheet-panel]')).toBeHidden();
    await toggle.click();
    const sheet = page.getByRole('dialog', { name: 'Filters' });
    await expect(sheet).toBeVisible();
    await expect(sheet.getByLabel('Search')).toBeFocused();
    if (process.env.SHOTS_DIR) {
      await page.screenshot({ path: `${process.env.SHOTS_DIR}/data-tables-375-light-filter-sheet.png`, animations: 'disabled' });
    }
    await page.keyboard.press('Escape');
    await expect(sheet).toBeHidden();
    await expect(toggle).toBeFocused();
    await expect(page.locator('#catalogue-filters > [data-nowo-ui-filter-sheet-panel]')).toBeHidden();

    await page.setViewportSize({ width: 1440, height: 900 });
    await expect(toggle).toBeHidden();
    await expect(page.getByLabel('Search')).toBeVisible();
  });

  test('data tables: filters, sort and per page are plain GET links / forms', async ({ page }) => {
    await open(page, 'data-tables', 1440, 'light');
    await page.getByLabel('Status').selectOption('overdue');
    await page.getByRole('button', { name: 'Apply' }).click();
    await expect(page).toHaveURL(/status=overdue/);
    await expect(page.locator('#catalogue-invoices tbody .nowo-ui-badge')).not.toHaveCount(0);
    for (const badge of await page.locator('#catalogue-invoices tbody .nowo-ui-badge').allTextContents()) expect(badge).toBe('Overdue');
    await expect(page.locator('[data-nowo-ui-filter-sheet-open] .nowo-ui-filter-sheet__count')).toContainText('1');

    await page.locator('th[data-nowo-ui-column="amount"] a').click();
    await expect(page.locator('th[data-nowo-ui-column="amount"]')).toHaveAttribute('aria-sort', 'ascending');
    await expect(page).toHaveURL(/status=overdue/);
    await page.locator('th[data-nowo-ui-column="amount"] a').click();
    await expect(page.locator('th[data-nowo-ui-column="amount"]')).toHaveAttribute('aria-sort', 'descending');
  });

  test('data tables: bulk selection counts, mixes, and offers every page', async ({ page }) => {
    await open(page, 'data-tables', 1440, 'light');
    const status = page.locator('[data-nowo-ui-bulk-status]');
    const archive = page.getByRole('button', { name: 'Archive', exact: true });
    await expect(status).toHaveText('No rows selected');
    await expect(archive).toHaveAttribute('aria-disabled', 'true');
    await page.getByLabel('Select INV-1001').check();
    await expect(status).toHaveText('1 row selected');
    await expect(page.getByLabel('Select every row on this page')).toHaveJSProperty('indeterminate', true);
    await expect(archive).toHaveAttribute('aria-disabled', 'false');
    await page.getByLabel('Select every row on this page').check();
    await expect(status).toHaveText('10 rows selected');
    await page.getByRole('button', { name: 'Select all 42 rows' }).click();
    await expect(status).toHaveText('All 42 rows selected');
    await archive.click();
    await expect(page.locator('[data-nowo-ui-toast]')).toContainText('42 invoices archived');
  });

  test('data tables: density and columns are remembered in the browser', async ({ page }) => {
    await open(page, 'data-tables', 1440, 'light');
    await page.evaluate(() => localStorage.clear());
    await page.reload();
    await page.getByRole('button', { name: 'Compact' }).click();
    await expect(page.locator('#catalogue-invoices')).toHaveClass(/nowo-ui-data-table--compact/);
    await page.getByText('Columns', { exact: true }).click();
    await page.getByRole('button', { name: 'Customer' }).click();
    await expect(page.locator('th[data-nowo-ui-column="customer"]')).toBeHidden();
    await page.keyboard.press('Escape');
    await page.reload();
    await expect(page.locator('#catalogue-invoices')).toHaveClass(/nowo-ui-data-table--compact/);
    await expect(page.locator('th[data-nowo-ui-column="customer"]')).toBeHidden();
    await page.getByText('Columns', { exact: true }).click();
    await expect(page.getByRole('button', { name: 'Number' })).toBeDisabled();
  });

  test('data tables: row menu is an APG menu button', async ({ page }) => {
    await open(page, 'data-tables', 1440, 'light');
    const toggle = page.getByRole('button', { name: 'Actions for INV-1001' });
    await toggle.click();
    const menu = page.getByRole('menu');
    await expect(menu).toBeVisible();
    await expect(menu.getByRole('menuitem', { name: 'View' })).toBeFocused();
    await page.keyboard.press('ArrowDown');
    await expect(menu.getByRole('menuitem', { name: 'Edit' })).toBeFocused();
    if (process.env.SHOTS_DIR) await page.screenshot({ path: `${process.env.SHOTS_DIR}/data-tables-1440-light-row-menu.png`, animations: 'disabled' });
    await page.keyboard.press('Escape');
    await expect(menu).toBeHidden();
    await expect(toggle).toBeFocused();
  });

  test('data display: copy button, stat trend words, timeline times', async ({ page, context }) => {
    await context.grantPermissions(['clipboard-read', 'clipboard-write']);
    await open(page, 'data-display', 1440, 'light');
    const copy = page.getByRole('button', { name: 'Copy code' });
    await expect(copy).toBeVisible();
    await copy.click();
    await expect(page.locator('[data-nowo-ui-clipboard-target]')).toHaveAttribute('aria-label', 'Copied');
    expect(await page.evaluate(() => navigator.clipboard.readText())).toContain('css_framework: custom');
    await expect(page.getByTestId('catalogue-stat-tiles').locator('.nowo-ui-stat__delta').first()).toContainText('Up');
    await expect(page.getByTestId('catalogue-timeline').locator('time').first()).toHaveAttribute('datetime', '2026-11-04T09:12');
    await expect(page.getByTestId('catalogue-skeleton').locator('[aria-busy="true"]')).toHaveCount(1);
  });

  test('form patterns: error summary takes focus and links to the fields; dropzone lists files', async ({ page }) => {
    await open(page, 'form-patterns', 1440, 'light');
    const summary = page.getByTestId('catalogue-invalid-form').locator('[data-nowo-ui-error-summary]');
    await expect(summary).toBeFocused();
    await summary.getByRole('link', { name: /Plan/ }).click();
    await expect(page.locator('#invalid_customer_plan_0')).toBeFocused();
    await expect(page.getByRole('group', { name: 'Plan' }).first()).toBeVisible();
    await expect(page.getByRole('switch', { name: 'Send payment reminders' }).first()).toBeVisible();

    const input = page.locator('#customer_contract');
    await input.setInputFiles([{ name: 'contract.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4') }, { name: 'virus.exe', mimeType: 'application/x-msdownload', buffer: Buffer.from('MZ') }]);
    const zone = page.getByTestId('catalogue-form-patterns').locator('[data-nowo-ui-dropzone]');
    await expect(zone.locator('.nowo-ui-dropzone__file')).toHaveCount(2);
    await expect(zone.locator('.nowo-ui-dropzone__file--invalid')).toContainText('virus.exe');
    await expect(zone.locator('[data-nowo-ui-dropzone-status]')).toContainText('2 files selected');
    expect(await input.evaluate((el: HTMLInputElement) => el.validationMessage)).toContain('virus.exe');
  });

  test('form patterns: submit shows the busy button, then the saved toast', async ({ page }) => {
    await open(page, 'form-patterns', 1440, 'light');
    const form = page.getByTestId('catalogue-form-patterns');
    await form.getByLabel('Company name').fill('Lovelace & Co');
    await form.getByLabel('Billing email').fill('billing@lovelace.example');
    await form.getByRole('radio', { name: 'Business' }).check();
    await Promise.all([page.waitForURL(/form-patterns/), form.getByRole('button', { name: 'Save customer' }).click()]);
    await expect(page.locator('[data-nowo-ui-toast]')).toContainText('Customer saved');
  });

  test('overlays: toasts are polite, dismissible, and stay with timeout 0', async ({ page }) => {
    await open(page, 'overlays', 1440, 'light');
    await expect(page.locator('[data-nowo-ui-toast-stack]')).toHaveAttribute('aria-live', 'polite');
    await page.getByRole('button', { name: 'Error toast (stays)' }).click();
    const toast = page.locator('[data-nowo-ui-toast]').last();
    await expect(toast).toContainText('The bank feed could not be refreshed.');
    await page.getByTestId('catalogue-tooltip-trigger').focus();
    if (process.env.SHOTS_DIR) await page.screenshot({ path: `${process.env.SHOTS_DIR}/overlays-1440-light-toast-tooltip.png`, animations: 'disabled' });
    await page.waitForTimeout(500);
    await expect(toast).toBeVisible();
    await toast.getByRole('button', { name: 'Dismiss notification' }).click();
    await expect(page.locator('[data-nowo-ui-toast]')).toHaveCount(0);
  });

  test('overlays: tooltip on focus and hover, Escape hides it', async ({ page }) => {
    await open(page, 'overlays', 1440, 'light');
    const trigger = page.getByTestId('catalogue-tooltip-trigger');
    const tip = page.locator('#catalogue-tip-resend');
    await expect(trigger).toHaveAccessibleDescription('Sends the invoice again to the billing email.');
    await trigger.focus();
    await expect(tip).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(tip).toBeHidden();
    await expect(trigger).toBeFocused();
    await page.mouse.move(0, 0);
    await trigger.hover();
    await expect(tip).toBeVisible();
  });

  test('overlays: destructive confirm opens on Cancel; detail drawer moves focus in and out', async ({ page }) => {
    await open(page, 'overlays', 1440, 'light');
    await page.getByTestId('catalogue-confirm').getByRole('button', { name: 'Delete invoice' }).click();
    const dialog = page.getByRole('dialog', { name: 'Delete invoice INV-1044?' });
    await expect(dialog).toBeVisible();
    await expect(dialog.getByRole('button', { name: 'Cancel' })).toBeFocused();
    if (process.env.SHOTS_DIR) await page.screenshot({ path: `${process.env.SHOTS_DIR}/overlays-1440-light-confirm.png`, animations: 'disabled' });
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();

    const opener = page.getByRole('button', { name: 'Show invoice details' });
    await opener.click();
    const panel = page.locator('#catalogue-invoice-panel');
    await expect(panel).toBeVisible();
    await expect(opener).toHaveAttribute('aria-expanded', 'true');
    if (process.env.SHOTS_DIR) await page.screenshot({ path: `${process.env.SHOTS_DIR}/overlays-1440-light-drawer.png`, animations: 'disabled' });
    await page.keyboard.press('Escape');
    await expect(opener).toBeFocused();
    await expect(opener).toHaveAttribute('aria-expanded', 'false');
  });

  test('navigation: breadcrumbs, tabs with arrow keys, steps', async ({ page }) => {
    await open(page, 'navigation-patterns', 1440, 'light');
    await expect(page.locator('.nowo-ui-page-header').getByRole('link', { name: 'Invoices' })).toBeVisible();
    const details = page.getByRole('tab', { name: 'Details' });
    await expect(page.getByRole('tablist', { name: 'Invoice INV-1042' })).toBeVisible();
    await details.focus();
    await page.keyboard.press('ArrowRight');
    await expect(page.getByRole('tab', { name: 'Lines' })).toBeFocused();
    await expect(page.getByRole('tab', { name: 'Lines' })).toHaveAttribute('aria-selected', 'true');
    await expect(page.getByRole('tabpanel', { name: 'Lines' })).toBeVisible();
    await expect(page.getByRole('tabpanel', { name: 'Details' })).toBeHidden();
    await page.keyboard.press('End');
    await expect(page.getByRole('tab', { name: 'History' })).toHaveAttribute('aria-selected', 'true');
    await expect(page.locator('.nowo-ui-steps [aria-current="step"]')).toContainText('Invoice numbering');
  });

  test('worker mode: two requests in a row keep their own state', async ({ request }) => {
    const a = await (await request.get('/catalogue/admin/data-tables?status=paid')).text();
    const b = await (await request.get('/catalogue/admin/data-tables')).text();
    expect(a).toContain('aria-hidden="true">1</span>');
    expect(b).not.toContain('nowo-ui-filter-sheet__count');
    const navCurrent = (html: string) => (html.match(/<a\s[^>]*data-testid="catalogue-nav-[^"]*"[^>]*>/g) ?? []).filter((a) => a.includes('aria-current="page"'));
    expect(navCurrent(b)).toHaveLength(1);
    expect(navCurrent(b)[0]).toContain('catalogue-nav-data-tables');
    const c = await (await request.get('/catalogue/admin/overlays')).text();
    expect(navCurrent(c)[0]).toContain('catalogue-nav-overlays');
  });
});
