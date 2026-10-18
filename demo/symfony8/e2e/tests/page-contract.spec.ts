import { test, expect } from '@playwright/test';

/**
 * REQ-UI-003 host-theme demo — every scaffold renders inside the Acme host layout with the host theme
 * (tokens, class map, page_header partial override), and the default-theme view renders the same pages with
 * UiKit's defaults.
 */
const scaffolds: Array<{ name: string; path: string }> = [
  { name: 'list', path: '/page-contract/orders' },
  { name: 'show', path: '/page-contract/orders/1042' },
  { name: 'form', path: '/page-contract/orders/1042/edit' },
  { name: 'settings', path: '/page-contract/settings' },
  { name: 'dashboard', path: '/page-contract/dashboard' },
  { name: 'confirm', path: '/page-contract/orders/1041/delete' },
  { name: 'wizard', path: '/page-contract/onboarding' },
  { name: 'auth', path: '/page-contract/sign-in' },
];

test.describe('page contract — host theme', () => {
  for (const { name, path } of scaffolds) {
    test(`${name} scaffold uses the host layout and theme`, async ({ page }) => {
      const response = await page.goto(path);
      expect(response?.ok()).toBeTruthy();
      await expect(page.locator(`[data-nowo-ui-page="${name}"]`)).toBeVisible();
      // host tokens: <html data-nowo-ui-host-tokens> + token stylesheet, and the token reaches UiKit's CSS
      await expect(page.locator('html')).toHaveAttribute('data-nowo-ui-host-tokens', '');
      await expect(page.locator('style[data-nowo-ui-theme-tokens]')).toHaveCount(1);
      const primary = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--nowo-ui-primary').trim());
      expect(primary).toBe('#0f766e');
      if (name === 'auth') {
        await expect(page.locator('.acme-guest')).toBeVisible();
        await expect(page.locator('.acme-sidebar')).toHaveCount(0);
        await expect(page.locator('.acme-auth')).toBeVisible();
      } else {
        await expect(page.locator('.acme-sidebar')).toBeVisible();
        // partial override: every page header comes from the host template
        await expect(page.locator('.acme-header .acme-title')).toBeVisible();
        await expect(page.locator('.nowo-ui-page-header')).toHaveCount(0);
      }
    });
  }

  test('list: class map, attributes, row actions and confirm dialog', async ({ page }) => {
    await page.goto('/page-contract/orders');
    const table = page.locator('table.acme-table');
    await expect(table).toHaveAttribute('data-acme-table', 'zebra');
    await expect(table.locator('tbody tr.acme-row')).toHaveCount(5);
    const paid = page.locator('.acme-badge--ok').first();
    await expect(paid).toHaveText('Paid');
    // framework_defaults: false on the badge slots — the host paints them alone (no Bootstrap badge classes)
    await expect(paid).toHaveClass('nowo-ui-badge nowo-ui-badge--success acme-badge acme-badge--ok');
    await expect(page.locator('.nowo-ui-pagination')).toContainText('of 42');
    await page.locator('tbody tr').first().locator('.nowo-ui-action--delete').click();
    const dialog = page.locator('dialog[open]');
    await expect(dialog).toBeVisible();
    await expect(dialog).toContainText('Delete order #1042?');
    await expect(dialog).toContainText('The order of Lucía Fernández (249.90 €) will be deleted.');
  });

  test('list: filters submit with GET', async ({ page }) => {
    await page.goto('/page-contract/orders');
    await page.locator('#filters_q').fill('emma');
    await page.locator('#filters_q').press('Enter');
    await expect(page).toHaveURL(/filters%5Bq%5D=emma|filters\[q\]=emma/);
  });

  test('settings: tabs mark the current section', async ({ page }) => {
    await page.goto('/page-contract/settings/notifications');
    await expect(page.locator('a.acme-tab--active[aria-current="page"]')).toHaveText('Notifications');
    await expect(page.locator('#notifications_recipients')).toHaveValue('orders@acme.test');
  });

  test('wizard: current step is highlighted', async ({ page }) => {
    await page.goto('/page-contract/onboarding');
    await expect(page.locator('.acme-step--active')).toHaveText('Payments');
    await expect(page.locator('.nowo-ui-wizard-page__progress')).toContainText('3');
  });
});

test.describe('page contract — validation follow-ups (REQ-UI-003)', () => {
  test('cascade layers: a host utility (@layer utilities) in a slot beats the kit (@layer nowo-ui)', async ({ page }) => {
    await page.goto('/page-contract/dashboard');
    const value = page.locator('.nowo-ui-kpi__value.acme-kpi-value').first();
    await expect(value).toBeVisible();
    // nowo-ui.css sets 1.5rem (24px) on .nowo-ui-kpi__value; the host utility sets 1.9rem
    expect(await value.evaluate((el) => getComputedStyle(el).fontSize)).toBe('30.4px');
    // the kit still beats the base layer: the confirm dialog keeps its centred, padded panel
    const layers = await page.evaluate(() => [...document.styleSheets].some((sheet) => {
      try {
        return [...sheet.cssRules].some((rule) => rule.constructor.name === 'CSSLayerStatementRule' && rule.cssText.includes('nowo-ui'));
      } catch {
        return false;
      }
    }));
    expect(layers).toBe(true);
  });

  test('list: regions, page attributes, page-type attribute, danger row action, filters options', async ({ page }) => {
    await page.goto('/page-contract/orders');
    const list = page.locator('[data-nowo-ui-page="list"][data-testid="orders-list"]');
    await expect(list).toBeVisible();
    // the page header renders in the layout's nowo_ui_page_header block (host partial override keeps the region)
    await expect(page.locator('.acme-header[data-nowo-ui-region="header"]')).toHaveCount(1);
    for (const region of ['filters', 'table', 'confirms', 'pagination']) {
      await expect(list.locator(`[data-nowo-ui-region="${region}"]`).first()).toBeAttached();
    }
    await expect(page.locator('table[data-acme-page="list"]')).toHaveCount(1);
    const del = page.locator('tbody tr').first().locator('.nowo-ui-action--delete');
    await expect(del).toHaveClass(/\bacme-action--danger\b/);
    expect(await del.evaluate((el) => getComputedStyle(el).color)).toBe('rgb(180, 35, 24)');
    await expect(page.locator('#filters_q')).toHaveAttribute('placeholder', 'Search customers…');
    await expect(page.locator('.nowo-ui-filters__field--md #filters_q')).toHaveCount(1);
    await expect(page.locator('.nowo-ui-filters__submit')).toContainText('Filter');
  });

  test('list: the table turns into labelled cards on a phone', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/page-contract/orders');
    const cell = page.locator('tbody tr').first().locator('td[data-label="Customer"]');
    await expect(cell).toBeVisible();
    expect(await cell.evaluate((el) => getComputedStyle(el).display)).toBe('grid');
    expect(await cell.evaluate((el) => getComputedStyle(el, '::before').content)).toBe('"Customer"');
    expect(await page.locator('table.nowo-ui-table--cards > thead').evaluate((el) => getComputedStyle(el).position)).toBe('absolute');
    // every column shows again in card mode, including the priority 3 "Placed" column
    await expect(page.locator('tbody tr').first().locator('td[data-label="Placed"]')).toBeVisible();
  });

  test('list: priority columns hide on mid widths, the table layout stays', async ({ page }) => {
    // 1100px viewport: the Acme main column leaves the table ~790px wide (between 48rem and 60rem)
    await page.setViewportSize({ width: 1100, height: 860 });
    await page.goto('/page-contract/orders');
    const width = await page.locator('[data-nowo-ui-region="table"]').evaluate((el) => el.getBoundingClientRect().width);
    expect(width).toBeGreaterThan(768);
    expect(width).toBeLessThan(960);
    await expect(page.locator('tbody tr').first().locator('td[data-label="Placed"]')).toBeHidden();
    await expect(page.locator('tbody tr').first().locator('td[data-label="Status"]')).toBeVisible();
    expect(await page.locator('table.nowo-ui-table--cards').evaluate((el) => getComputedStyle(el).display)).toBe('table');
    await page.setViewportSize({ width: 1400, height: 860 });
    await expect(page.locator('tbody tr').first().locator('td[data-label="Placed"]')).toBeVisible();
  });

  test('confirm: type-to-confirm keeps the delete button disabled until the order number is typed', async ({ page }) => {
    await page.goto('/page-contract/orders/1041/delete');
    const button = page.locator('.nowo-ui-confirm__submit');
    await expect(button).toBeDisabled();
    await page.locator('#nowo-ui-confirm-page-expected').fill('104');
    await expect(button).toBeDisabled();
    await page.locator('#nowo-ui-confirm-page-expected').fill('1041');
    await expect(button).toBeEnabled();
  });

  test('form: secondary actions in the page-header toolbar; dashboard: KPI tile as a link', async ({ page }) => {
    await page.goto('/page-contract/orders/1042/edit');
    await expect(page.locator('.acme-header .nowo-ui-action--copy')).toHaveText('Duplicate');
    await expect(page.locator('[data-nowo-ui-region="secondary-actions"]')).toHaveCount(0);
    await expect(page.locator('.nowo-ui-form-panel form[data-nowo-ui-region="form"]')).toHaveCount(1);
    await page.goto('/page-contract/dashboard');
    await expect(page.locator('a.acme-kpi.nowo-ui-kpi--link[href*="/orders"]')).toContainText('1,284');
    // the dashboard table is not a list page: no page-type attribute
    await expect(page.locator('table[data-acme-page]')).toHaveCount(0);
  });
});

test.describe('page contract — validation round 3 (REQ-UI-003)', () => {
  test('list: primary actions in the filter row, icon-only toolbar buttons keep an accessible name', async ({ page }) => {
    await page.goto('/page-contract/orders');
    const inFilters = page.locator('[data-nowo-ui-region="filters"] [data-nowo-ui-region="header-actions"]');
    await expect(inFilters.locator('.nowo-ui-action--create')).toHaveText('New order');
    await expect(page.locator('.acme-header .nowo-ui-action--create')).toHaveCount(0);
    const exportCsv = inFilters.locator('.nowo-ui-action--export');
    await expect(exportCsv).toHaveClass(/\bnowo-ui-btn--icon\b/);
    await expect(exportCsv).toHaveAttribute('aria-label', 'Export CSV');
    await expect(exportCsv.locator('.visually-hidden')).toHaveText('Export CSV');
    await expect(exportCsv.locator('.nowo-ui-action__label')).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Clear filters' })).toHaveClass(/\bnowo-ui-btn--icon\b/);
    // the header actions sit right after the filter actions (same group, no gap pushing them to the row end)
    const clear = await page.getByRole('link', { name: 'Clear filters' }).boundingBox();
    const actions = await inFilters.boundingBox();
    expect(clear && actions).toBeTruthy();
    if (clear && actions && Math.abs(actions.y - clear.y) < clear.height) {
      expect(actions.x - (clear.x + clear.width)).toBeLessThan(40);
    }
  });

  test('list: every row opens its own confirm dialog (unique ids)', async ({ page }) => {
    await page.goto('/page-contract/orders');
    const ids = await page.locator('dialog[data-nowo-ui-confirm]').evaluateAll((els) => els.map((el) => el.id));
    expect(ids.length).toBe(5);
    expect(new Set(ids).size).toBe(5);
    await page.locator('tbody tr').nth(1).locator('.nowo-ui-action--delete').click();
    const dialog = page.locator('dialog[open]');
    await expect(dialog).toContainText('The order of Marco Rossi (89.00 €) will be deleted.');
  });

  test('list: pagination links keep the active filters; actions column aligned to the end', async ({ page }) => {
    await page.goto('/page-contract/orders?filters%5Bq%5D=e');
    await expect(page.locator('.nowo-ui-pagination a[href*="page=2"]').first()).toHaveAttribute('href', /filters%5Bq%5D=e/);
    const cell = page.locator('tbody tr').first().locator('td.nowo-ui-table__actions');
    await expect(cell).toHaveClass(/\bnowo-ui-cell--end\b/);
    expect(await cell.locator('.nowo-ui-row-actions').evaluate((el) => getComputedStyle(el).justifyContent)).toBe('flex-end');
  });

  test('list (phone): badges keep their width in card mode', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/page-contract/orders');
    const cell = page.locator('tbody tr').first().locator('td[data-label="Status"]');
    const badge = cell.locator('.nowo-ui-badge');
    const [badgeWidth, cellWidth] = [await badge.evaluate((el) => el.getBoundingClientRect().width), await cell.evaluate((el) => el.getBoundingClientRect().width)];
    expect(badgeWidth).toBeLessThan(cellWidth / 2);
  });

  test('show: stacked detail list; dashboard: host colour on the KPI link tile; confirm: title slot', async ({ page }) => {
    await page.goto('/page-contract/orders/1042');
    const item = page.locator('[data-nowo-ui-region="section"] .acme-details .nowo-ui-detail-list__item').first();
    expect(await item.evaluate((el) => getComputedStyle(el).display)).toBe('grid');
    await page.goto('/page-contract/dashboard');
    const tile = page.locator('a.nowo-ui-kpi--link.acme-kpi-link');
    expect(await tile.evaluate((el) => getComputedStyle(el).color)).toBe('rgb(15, 118, 110)');
    await page.goto('/page-contract/orders/1041/delete');
    await expect(page.locator('.nowo-ui-confirm-page__header .nowo-ui-confirm__title.acme-confirm-title')).toBeVisible();
  });

  test('final validation: dialog CSS never hides the full-page confirm; arbitrary variants in class lists', async ({ page }) => {
    await page.goto('/page-contract/orders/1041/delete');
    const section = page.locator('section[data-nowo-ui-page="confirm"]');
    await expect(section).toBeVisible();
    await expect(section).toHaveClass(/\bacme-confirm-page\b/);
    await expect(section).not.toHaveClass(/\b(acme-dialog|nowo-ui-confirm)(\s|$)/);
    await page.goto('/page-contract/orders');
    const dialog = page.locator('dialog.acme-dialog').first();
    await expect(dialog).toBeHidden();
    await page.locator('tbody tr').first().locator('[data-nowo-confirm-open]').first().click();
    await expect(dialog).toBeVisible();
    await page.keyboard.press('Escape');
    const sort = page.locator('th a.nowo-ui-sort').first();
    expect((await sort.getAttribute('class'))?.split(/\s+/)).toContain('[&>span]:underline');
  });

  for (const [view, path] of [['host theme', '/page-contract/orders/1042'], ['default view', '/page-contract/default/orders/1042']]) {
    test(`show (${view}): a FormView-backed header action keeps its CSRF token once`, async ({ page }) => {
      await page.goto(path);
      const form = page.locator('form[name="reopen_order"]');
      await expect(form).toHaveCount(1);
      await expect(form.locator('input[type="hidden"][name="reopen_order[_token]"]')).toHaveCount(1);
      await expect(form.locator('input[name="reopen_order[_token]"]')).toHaveValue('demo-token');
    });
  }

  test('show: code slots — inline code and a monospace, scrollable JSON block', async ({ page }) => {
    await page.goto('/page-contract/orders/1042');
    const payload = page.locator('[data-demo-payload]');
    await expect(payload.locator('code.nowo-ui-code')).toHaveText('order.paid');
    const block = payload.locator('pre.nowo-ui-code-block');
    await expect(block).toContainText('"event": "order.paid"');
    const style = await block.evaluate((el) => {
      const s = getComputedStyle(el);
      return { font: s.fontFamily, overflow: s.overflowX, whiteSpace: s.whiteSpace };
    });
    expect(style.font).toMatch(/mono/i);
    expect(style.overflow).toBe('auto');
    expect(style.whiteSpace).toBe('pre');
  });

  for (const [view, path] of [['host theme', '/page-contract/dashboard'], ['default view', '/page-contract/default/dashboard']]) {
    test(`dashboard (${view}): area navigation under the page title`, async ({ page }) => {
      await page.goto(path);
      const nav = page.locator('nav[data-nowo-ui-region="area-nav"]');
      await expect(nav).toHaveAttribute('aria-label', 'Reports');
      await expect(nav).toHaveAttribute('data-turbo', 'false');
      await expect(nav.locator('a')).toHaveText(['Sales', 'Stock', 'Customers']);
      await expect(nav.locator('a[aria-current="page"]')).toHaveText('Sales');
      // Recent orders through the table partial: header row, end-aligned amounts
      const recent = page.locator('table', { has: page.locator('caption', { hasText: 'Last four orders' }) });
      await expect(recent.locator('thead th')).toHaveText(['#', 'Customer', 'Total'], { ignoreCase: true });
      expect(await recent.locator('thead th').last().evaluate((el) => getComputedStyle(el).textAlign)).toBe('end');
    });
  }

  test('UiKit 2.2 default theme: masthead, confirm close button, solid delete, aligned headers', async ({ page }) => {
    await page.goto('/page-contract/default/orders');
    await expect(page.locator('.demo-masthead a[aria-current="page"]')).toHaveText('Orders');
    const total = page.locator('thead th', { hasText: 'Total' });
    expect(await total.evaluate((el) => getComputedStyle(el).textAlign)).toBe('end');
    await expect(page.locator('thead th.nowo-ui-table__actions')).toHaveText('Actions');
    await page.locator('td.nowo-ui-table__actions button[data-nowo-confirm-open]').first().click();
    const dialog = page.locator('dialog[data-nowo-ui-confirm][open]');
    await expect(dialog.locator('h2')).toHaveText('Delete order #1042?');
    await expect(dialog.locator('.nowo-ui-confirm__submit')).toHaveClass(/btn-danger/);
    const box = await dialog.boundingBox();
    expect(Math.round(box!.width)).toBeLessThanOrEqual(448);
    await dialog.getByRole('button', { name: 'Close' }).click();
    await expect(dialog).toHaveCount(0);
  });

  test('UiKit 2.2: settings section actions sit next to the submit button', async ({ page }) => {
    await page.goto('/page-contract/settings');
    const row = page.locator('.nowo-ui-form__actions').first();
    await expect(row.getByRole('button', { name: 'Save notifications' })).toBeVisible();
    await expect(row.getByRole('link', { name: 'Send a test email' })).toBeVisible();
    const [save, extra] = [await row.getByRole('button', { name: 'Save notifications' }).boundingBox(), await row.getByRole('link', { name: 'Send a test email' }).boundingBox()];
    expect(Math.abs(save!.y - extra!.y)).toBeLessThan(4);
  });

  test('dashboard: generic native dialog opens, closes and returns focus', async ({ page }) => {
    await page.goto('/page-contract/dashboard');
    const opener = page.getByRole('button', { name: 'Restock details' });
    const dialog = page.locator('dialog#restock-dialog');
    await expect(dialog).toBeHidden();
    await opener.click();
    await expect(dialog).toBeVisible();
    await expect(dialog.locator('.nowo-ui-dialog__title')).toHaveText('Restock schedule');
    await dialog.getByRole('button', { name: 'Got it' }).click();
    await expect(dialog).toBeHidden();
    await expect(opener).toBeFocused();
    await opener.click();
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await opener.click();
    await dialog.getByRole('button', { name: 'Close' }).click();
    await expect(dialog).toBeHidden();
    await opener.click();
    await page.mouse.click(5, 5);
    await expect(dialog).toBeHidden();
  });

  test('default view: header actions stay in the page header', async ({ page }) => {
    await page.goto('/page-contract/default/orders');
    await expect(page.locator('.nowo-ui-page-header .nowo-ui-action--create')).toBeVisible();
    await expect(page.locator('[data-nowo-ui-region="header-actions"]')).toHaveCount(0);
  });
});

test.describe('page contract — default theme view', () => {
  test('same list page with UiKit defaults (no host tokens, classes or partial)', async ({ page }) => {
    const response = await page.goto('/page-contract/default/orders');
    expect(response?.ok()).toBeTruthy();
    await expect(page.locator('[data-nowo-ui-page="list"]')).toBeVisible();
    await expect(page.locator('style[data-nowo-ui-theme-tokens]')).toHaveCount(0);
    await expect(page.locator('.nowo-ui-page-header')).toBeVisible();
    await expect(page.locator('.acme-header, .acme-table, .acme-badge, .acme-action--danger, [data-acme-page]')).toHaveCount(0);
    await expect(page.locator('table.nowo-ui-table')).not.toHaveAttribute('data-acme-table', /.*/);
    await expect(page.locator('main[data-nowo-ui-bundle="DemoDefaultTheme"]')).toBeVisible();
    const primary = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--nowo-ui-primary').trim());
    expect(primary).toBe('#2563eb');
  });

  test('badges: one subtle set, readable text / background pair (UiKit 2.2, Bootstrap *-subtle / *-emphasis)', async ({ page }) => {
    await page.goto('/page-contract/default/orders');
    const paid = page.locator('.nowo-ui-badge--success').first();
    await expect(paid).toHaveClass(/\bbg-success-subtle\b/);
    await expect(paid).toHaveClass(/\btext-success-emphasis\b/);
    await expect(paid).not.toHaveClass(/\bms-2\b/);
    const [color, background] = await paid.evaluate((el) => [getComputedStyle(el).color, getComputedStyle(el).backgroundColor]);
    expect(color).not.toBe('rgb(255, 255, 255)');
    expect(background).not.toBe(color);
  });
});

test.describe('page contract — UiKit 2.3', () => {
  for (const view of ['', '/default']) {
    test(`dashboard${view}: export menu (native disclosure) opens and closes on outside click / Escape`, async ({ page }) => {
      await page.goto(`/page-contract${view}/dashboard`);
      const menu = page.getByTestId('export-menu');
      const toggle = menu.locator('summary');
      const panel = menu.locator('.nowo-ui-menu__panel');
      await expect(panel).toBeHidden();
      await toggle.click();
      await expect(panel).toBeVisible();
      await expect(panel.getByRole('link', { name: 'JSON' })).toBeVisible();
      // the panel floats (does not push the card content) and stays inside the viewport
      expect(await panel.evaluate((el) => getComputedStyle(el).position)).toBe('absolute');
      const box = await panel.boundingBox();
      expect(box!.x + box!.width).toBeLessThanOrEqual(page.viewportSize()!.width);
      await page.mouse.click(5, 5);
      await expect(panel).toBeHidden();
      await toggle.click();
      await expect(panel).toBeVisible();
      await panel.getByRole('link', { name: 'CSV' }).focus();
      await page.keyboard.press('Escape');
      await expect(panel).toBeHidden();
      await expect(toggle).toBeFocused();
    });
  }

  test('sign-in: root form errors above the fields, document title, form attributes, full-width fields', async ({ page }) => {
    await page.goto('/page-contract/default/sign-in?failed=1');
    const errors = page.locator('[data-nowo-ui-region="form-errors"]');
    await expect(errors).toHaveAttribute('role', 'alert');
    await expect(errors).toContainText('The email or password is incorrect.');
    await expect(page).toHaveTitle(/^Sign in/);
    const form = page.getByTestId('login-form');
    await expect(form).toHaveAttribute('data-turbo', 'false');
    // errors first, then the fields
    expect(await form.evaluate((el) => el.firstElementChild?.getAttribute('data-nowo-ui-region'))).toBe('form-errors');
    const panel = await page.locator('.nowo-ui-auth-panel').boundingBox();
    const email = await page.locator('#login_email').boundingBox();
    expect(panel!.width).toBeLessThanOrEqual(28 * 16 + 1);
    expect(email!.width).toBeGreaterThan(panel!.width - 64);
    // the footer link list has no reboot padding (unlayered Bootstrap ul { padding-left: 2rem })
    expect(await page.locator('.nowo-ui-auth-panel__links').evaluate((el) => getComputedStyle(el).paddingLeft)).toBe('0px');
    await page.goto('/page-contract/default/sign-in');
    await expect(page.locator('[data-nowo-ui-region="form-errors"]')).toHaveCount(0);
  });

  test('list: filter placeholder printed once (no double translation); 1.x title classes; no band above the navbar', async ({ page }) => {
    await page.goto('/page-contract/default/orders');
    await expect(page.locator('#filters_q')).toHaveAttribute('placeholder', 'Search customers…');
    await expect(page.locator('#filters_status option').first()).toHaveText('Any status');
    // the content region carries the vertical padding, not <body>
    expect(await page.evaluate(() => getComputedStyle(document.body).paddingTop)).toBe('0px');
  });
});
