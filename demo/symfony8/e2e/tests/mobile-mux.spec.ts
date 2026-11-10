import { test, expect, type Locator, type Page } from '@playwright/test';

/**
 * 2.41 phone mode (Beacon mobile UX audit 2026-11-09) at 390 × 844 in Chromium and WebKit (projects phone-chromium,
 * phone-webkit): edge tabs folded behind one launcher (MUX-01), kit selects WebKit no longer cuts (MUX-40), phone cards
 * and the overflow scroll of responsive tables (MUX-20 / MUX-22), touch targets and the font floor (MUX-04 / MUX-161),
 * the guest header (MUX-150). Screenshots go to test-results/mux-* for a visual check.
 */
async function open(page: Page, path = '/kit/mobile'): Promise<void> {
  await page.route('**/_wdt/**', (route) => route.abort());
  await page.goto(path);
  await expect(page.locator('html')).toHaveAttribute('data-nowo-ui-edge-tabs-ready', '');
}

/** 2.45: the tabs unfold / fold with an animation; wait for it before measuring. */
const settled = (l: Locator): Promise<unknown> => l.evaluate((el) => Promise.all(el.getAnimations().map((a) => a.finished)));

const visible = (l: Locator): Promise<boolean> => l.evaluate((el) => getComputedStyle(el).display !== 'none');

test.describe('phone mode', () => {
  test('MUX-01: the edge tabs fold behind one launcher that covers nothing', async ({ page }, info) => {
    await open(page);
    const launcher = page.getByTestId('edge-launcher');
    await expect(launcher).toBeVisible();
    await expect(launcher).toHaveAttribute('aria-expanded', 'false');
    await expect(launcher).toHaveAccessibleName('Page tools');
    for (const id of ['edge-manual', 'edge-notes', 'edge-prefs']) {
      expect(await visible(page.getByTestId(id).locator('a'))).toBe(false);
    }
    // The gutter keeps the launcher's width free: the end of the lede line is the lede, not a tab.
    const lede = page.getByTestId('lede');
    const box = (await lede.boundingBox())!;
    const launcherBox = (await launcher.boundingBox())!;
    expect(box.x + box.width).toBeLessThanOrEqual(launcherBox.x + 1);
    await page.screenshot({ path: `test-results/mux-edge-closed-${info.project.name}.png` });

    await launcher.click();
    await expect(launcher).toHaveAttribute('aria-expanded', 'true');
    const manual = page.getByTestId('edge-manual').locator('a');
    await expect(manual).toBeVisible();
    await settled(manual);
    expect((await manual.boundingBox())!.y).toBeGreaterThan(launcherBox.y + launcherBox.height - 1);
    await page.screenshot({ path: `test-results/mux-edge-open-${info.project.name}.png` });

    await page.keyboard.press('Escape');
    await expect(launcher).toHaveAttribute('aria-expanded', 'false');
    await expect(launcher).toBeFocused();
    await expect(manual).toBeHidden();

    // Using a tab folds them again.
    await launcher.click();
    await page.getByTestId('edge-notes').locator('a').click();
    await expect(page).toHaveURL(/#notes$/);
    await expect(launcher).toHaveAttribute('aria-expanded', 'false');
  });

  test('2.45: with the size tokens, the launcher and the tabs share one size, and the first tab keeps the gap', async ({ page }, info) => {
    await open(page);
    // Beacon's values (ChatKit's .nowo-chat-edge): 1.85rem × 2.3rem, icon 0.95rem, hover 2.15rem, step 2.6rem.
    await page.evaluate(() => {
      const s = document.documentElement.style;
      s.setProperty('--nowo-ui-edge-tab-width', '1.85rem');
      s.setProperty('--nowo-ui-edge-tab-height', '2.3rem');
      s.setProperty('--nowo-ui-edge-tab-icon', '0.95rem');
      s.setProperty('--nowo-ui-edge-tab-hover-width', '2.15rem');
      s.setProperty('--nowo-ui-edge-tab-step', '2.6rem');
    });
    const rem = await page.evaluate(() => parseFloat(getComputedStyle(document.documentElement).fontSize));
    const launcher = page.getByTestId('edge-launcher');
    await expect(launcher).toHaveClass(/nowo-ui-edge-tabs-launcher--chevron/);
    // Open from the keyboard: no pointer over the launcher (it widens on hover).
    await launcher.focus();
    await page.keyboard.press('Enter');
    await expect(launcher).toHaveAttribute('aria-expanded', 'true');
    const tabs = ['edge-manual', 'edge-notes', 'edge-tour'].map((id) => page.getByTestId(id).locator('.nowo-ui-edge-tab__link'));
    for (const tab of tabs) {
      await settled(tab);
    }
    const l = (await launcher.boundingBox())!;
    const boxes = await Promise.all(tabs.map(async (t) => (await t.boundingBox())!));
    expect(l.width).toBeCloseTo(1.85 * rem, 0);
    expect(l.height).toBeCloseTo(2.3 * rem, 0);
    for (const b of boxes) {
      expect(b.width).toBeCloseTo(l.width, 0);
      expect(b.height).toBeCloseTo(l.height, 0);
    }
    // Same 0.3rem gap from the launcher to the first tab as between the tabs.
    expect(boxes[0]!.y - (l.y + l.height)).toBeCloseTo(0.3 * rem, 0);
    expect(boxes[1]!.y - (boxes[0]!.y + boxes[0]!.height)).toBeCloseTo(0.3 * rem, 0);
    const iconSize = await launcher.locator('.nowo-ui-edge-tab__icon').evaluate((el) => el.getBoundingClientRect().width);
    expect(iconSize).toBeCloseTo(0.95 * rem, 0);
    // The chevron points down while open.
    await settled(launcher.locator('.nowo-ui-edge-tab__icon'));
    expect(await launcher.locator('.nowo-ui-edge-tab__icon').evaluate((el) => new DOMMatrix(getComputedStyle(el).transform).b)).toBeCloseTo(-1, 3);
    await page.screenshot({ path: `test-results/mux-edge-245-open-${info.project.name}.png`, clip: { x: l.x - 120, y: l.y - 20, width: 120 + l.width, height: 260 } });
    // The launcher widens on hover like the tabs.
    await launcher.hover();
    await expect.poll(async () => (await launcher.boundingBox())!.width).toBeCloseTo(2.15 * rem, 0);
  });

  test('2.45: the tabs unfold from the launcher and fold back, inert while folding', async ({ page }) => {
    await open(page);
    const launcher = page.getByTestId('edge-launcher');
    await launcher.click();
    const notes = page.getByTestId('edge-notes').locator('a');
    expect(await notes.evaluate((el) => el.getAnimations().map((a) => (a as CSSAnimation).animationName))).toEqual(['nowo-ui-edge-tab-unfold']);
    await settled(notes);
    await launcher.click();
    await expect(page.locator('html')).toHaveAttribute('data-nowo-ui-edge-tabs-closing', '');
    await expect(notes).toHaveAttribute('inert', '');
    await expect(notes).toHaveAttribute('aria-hidden', 'true');
    await expect(page.locator('html')).not.toHaveAttribute('data-nowo-ui-edge-tabs-closing', '');
    await expect(notes).toBeHidden();
    await expect(notes).not.toHaveAttribute('inert', '');
  });

  test('2.45: reduced motion opens and closes at once', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await open(page);
    const launcher = page.getByTestId('edge-launcher');
    await launcher.click();
    const notes = page.getByTestId('edge-notes').locator('a');
    expect(await notes.evaluate((el) => el.getAnimations().length)).toBe(0);
    await launcher.click();
    expect(await page.locator('html').getAttribute('data-nowo-ui-edge-tabs-closing')).toBeNull();
    expect(await visible(notes)).toBe(false);
  });

  test('JC-L1: closing a dialog opened from a folded tab gives the focus to the launcher', async ({ page }) => {
    await open(page);
    const launcher = page.getByTestId('edge-launcher');
    await launcher.click();
    await expect(launcher).toHaveAttribute('aria-controls', /\S/);
    await page.getByTestId('edge-customizer').locator('button').click();
    await expect(page.getByTestId('customizer-body')).toBeVisible();
    await expect(launcher).toHaveAttribute('aria-expanded', 'false');
    await page.keyboard.press('Escape');
    await expect(page.getByTestId('customizer-body')).toBeHidden();
    await expect(launcher).toBeFocused();

    // From the keyboard too: Enter on the launcher, arrows to the tab, Enter, Escape.
    await launcher.focus();
    await page.keyboard.press('Enter');
    const tab = page.getByTestId('edge-customizer').locator('button');
    for (let i = 0; i < 6 && !(await tab.evaluate((el) => el === document.activeElement)); i += 1) {
      await page.keyboard.press('ArrowDown');
    }
    await expect(tab).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.getByTestId('customizer-body')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(launcher).toBeFocused();
  });

  test('2.42 MUX-01: an expanded edge tab never folds and keeps its own top inside a fixed, transformed drawer', async ({ page }, info) => {
    await open(page);
    const launcher = page.getByTestId('edge-launcher');
    const drawer = page.getByTestId('drawer');
    const tab = page.getByTestId('edge-drawer').locator('button');
    const others = ['edge-manual', 'edge-notes', 'edge-tour', 'edge-prefs', 'edge-customizer'].map((id) => page.getByTestId(id).locator('a, button'));
    const rem = await page.evaluate(() => Number.parseFloat(getComputedStyle(document.documentElement).fontSize));
    // Own top (slot 5, --nowo-ui-edge-tab-top 0.75rem on the drawer), from the drawer's top: its containing block.
    const ownTop = 0.75 * rem + 5 * 3.25 * rem;
    const offset = async (): Promise<number> => (await tab.boundingBox())!.y - (await drawer.boundingBox())!.y;

    // Folded and collapsed: hidden like the others.
    await expect(tab).toBeHidden();

    // Used from the open launcher: the drawer opens, the launcher folds, the tab rides on the drawer's edge.
    await launcher.click();
    await tab.click();
    await expect(drawer).toHaveAttribute('data-open', '');
    await expect(tab).toHaveAttribute('aria-expanded', 'true');
    await expect(launcher).toHaveAttribute('aria-expanded', 'false');
    await expect(tab).toBeVisible();
    for (const other of others) {
      await expect(other).toBeHidden();
    }
    expect(Math.abs((await offset()) - ownTop)).toBeLessThan(1);
    expect(await tab.evaluate((el) => getComputedStyle(el).top)).toBe(`${ownTop}px`);
    const tabBox = (await tab.boundingBox())!;
    // right: 100% of the drawer's padding box: the tab ends on the drawer's 1px border.
    expect(Math.abs(tabBox.x + tabBox.width - (await drawer.boundingBox())!.x)).toBeLessThanOrEqual(1.5);
    // position: fixed resolves against the transformed drawer too: same place.
    await tab.evaluate((el) => { (el as HTMLElement).style.position = 'fixed'; });
    expect(Math.abs((await offset()) - ownTop)).toBeLessThan(1);
    // Without a transform (transform: none) a fixed tab is placed from the viewport; an absolute one still from the drawer.
    await drawer.evaluate((el) => { (el as HTMLElement).style.transform = 'none'; });
    expect(Math.abs((await tab.boundingBox())!.y - ownTop)).toBeLessThan(1);
    await tab.evaluate((el) => { (el as HTMLElement).style.position = ''; });
    expect(Math.abs((await offset()) - ownTop)).toBeLessThan(1);
    await drawer.evaluate((el) => { (el as HTMLElement).style.transform = ''; });

    // A click elsewhere leaves it; the open launcher stacks it under itself (slot + 1), folding puts it back.
    // (the open drawer covers the right of the page: click the start of the lede)
    const lede = page.getByTestId('lede');
    await lede.click({ position: { x: 4, y: 4 } });
    await expect(tab).toBeVisible();
    await launcher.focus();
    await page.keyboard.press('Enter');
    await expect(launcher).toHaveAttribute('aria-expanded', 'true');
    expect(Math.abs((await offset()) - (ownTop + 3.25 * rem))).toBeLessThan(1);
    await lede.click({ position: { x: 4, y: 4 } });
    await expect(launcher).toHaveAttribute('aria-expanded', 'false');
    await expect(drawer).toHaveAttribute('data-open', '');
    expect(Math.abs((await offset()) - ownTop)).toBeLessThan(1);

    // Its own click closes the drawer: collapsed, it folds again.
    await tab.click();
    await expect(tab).toHaveAttribute('aria-expanded', 'false');
    await expect(tab).toBeHidden();

    // From the keyboard: the focus never stays on the folded tab.
    await launcher.focus();
    await page.keyboard.press('Enter');
    for (let i = 0; i < 8 && !(await tab.evaluate((el) => el === document.activeElement)); i += 1) {
      await page.keyboard.press('ArrowDown');
    }
    await expect(tab).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(tab).toHaveAttribute('aria-expanded', 'true');
    await expect(tab).toBeVisible();
    await expect(tab).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(tab).toBeHidden();
    await expect(launcher).toBeFocused();

    // Last: a file written under the demo makes its dev hot reload morph the page.
    await tab.evaluate((el) => (el as HTMLElement).click());
    await expect(tab).toBeVisible();
    await page.screenshot({ path: `test-results/mux-edge-pinned-${info.project.name}.png` });
  });

  test('2.42 MUX-01: data-nowo-ui-edge-tab-pinned keeps a collapsed tab out of the fold', async ({ page }) => {
    await open(page);
    const manual = page.getByTestId('edge-manual').locator('a');
    await expect(manual).toBeHidden();
    await manual.evaluate((el) => el.setAttribute('data-nowo-ui-edge-tab-pinned', ''));
    await expect(manual).toBeVisible();
    await expect(page.getByTestId('edge-notes').locator('a')).toBeHidden();
  });

  test('JC-I12: a hidden launcher folds nothing', async ({ page }) => {
    await open(page);
    await page.getByTestId('edge-launcher').evaluate((el) => el.setAttribute('hidden', ''));
    await expect(page.getByTestId('edge-manual').locator('a')).toBeVisible();
  });

  test('MUX-01: desktop keeps the 2.40 tabs', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await open(page);
    // Wider than 64rem: no fold, even on a touch screen.
    await expect(page.getByTestId('edge-launcher')).toBeHidden();
    await expect(page.getByTestId('edge-manual').locator('a')).toBeVisible();
  });

  test('MUX-40: kit selects keep their text inside the box', async ({ page }, info) => {
    await open(page);
    for (const id of ['select-kit', 'select-opt-in', 'select-pager']) {
      const metrics = await page.getByTestId(id).evaluate((el) => {
        const s = getComputedStyle(el);
        const px = (v: string): number => Number.parseFloat(v) || 0;
        return {
          appearance: s.getPropertyValue('appearance') || s.getPropertyValue('-webkit-appearance'),
          content: el.clientHeight - px(s.paddingTop) - px(s.paddingBottom),
          line: px(s.lineHeight),
        };
      });
      expect(metrics.appearance, id).toBe('none');
      expect(metrics.content, id).toBeGreaterThanOrEqual(metrics.line - 0.5);
    }
    await page.getByTestId('selects').screenshot({ path: `test-results/mux-selects-${info.project.name}.png` });
  });

  test('MUX-20: phone cards, primary first, hidden column out, no sideways scroll', async ({ page }, info) => {
    await open(page);
    const root = page.getByTestId('rt-cards');
    await expect(root).toHaveAttribute('data-rt-cards', '');
    const row = root.locator('tbody tr').first();
    const name = row.locator('td[data-column="name"]');
    const role = row.locator('td[data-column="role"]');
    await expect(name).toBeVisible();
    await expect(row.locator('td[data-column="id"]')).toBeHidden();
    await expect(role).toHaveAttribute('data-rt-label', 'Role');
    await expect(row.locator('td[data-column="email"]')).toHaveAttribute('data-nowo-ui-phone', 'meta');
    expect((await name.boundingBox())!.y).toBeLessThan((await role.boundingBox())!.y);
    const scroller = root.locator('.nowo-ui-responsive-table__scroller');
    expect(await scroller.evaluate((el) => el.scrollWidth <= el.clientWidth + 1)).toBe(true);
    await root.screenshot({ path: `test-results/mux-cards-${info.project.name}.png` });
  });

  test('MUX-20: required columns that overflow scroll sideways, words stay whole', async ({ page }, info) => {
    await open(page);
    const root = page.getByTestId('rt-scroll');
    await expect(root).toHaveAttribute('data-table-mode', 'responsive');
    await expect(root).toHaveAttribute('data-rt-overflow', '');
    const scroller = root.locator('.nowo-ui-responsive-table__scroller');
    expect(await scroller.evaluate((el) => getComputedStyle(el).overflowX)).toBe('auto');
    expect(await scroller.evaluate((el) => el.scrollWidth > el.clientWidth)).toBe(true);
    expect(await page.getByTestId('rt-scroll-name').evaluate((el) => getComputedStyle(el).overflowWrap)).not.toBe('anywhere');
    await root.screenshot({ path: `test-results/mux-scroll-${info.project.name}.png` });
  });

  test('2.42 MUX-20 / MUX-22: ListingField::phone in phone cards, under a stored customization', async ({ page }, info) => {
    await open(page);
    const root = page.getByTestId('rt-listing');
    await expect(root).toHaveAttribute('data-rt-cards', '');
    await expect(root.locator('thead th[data-column="name"]')).toHaveAttribute('data-nowo-ui-phone', 'primary');
    await expect(root.locator('thead th[data-column="role"]')).not.toHaveAttribute('data-nowo-ui-phone', /./);
    const row = root.locator('tbody tr').first();
    // Stored order puts Role first and hides Status; the primary column is still the card title.
    await expect(row.locator('td[data-column]').first()).toHaveAttribute('data-column', 'role');
    await expect(row.locator('td[data-column="status"]')).toHaveCount(0);
    const name = row.locator('td[data-column="name"]');
    await expect(name).toHaveAttribute('data-nowo-ui-phone', 'primary');
    await expect(name).toHaveText('Demo Admin');
    expect((await name.boundingBox())!.y).toBeLessThan((await row.locator('td[data-column="role"]').boundingBox())!.y);
    await expect(row.locator('td[data-column="email"]')).toHaveAttribute('data-nowo-ui-phone', 'meta');
    await expect(row.locator('td[data-column="id"]')).toBeHidden();
    await expect(row.locator('td[data-column="role"]')).toHaveAttribute('data-rt-label', 'Role');
    const scroller = root.locator('.nowo-ui-responsive-table__scroller');
    expect(await scroller.evaluate((el) => el.scrollWidth <= el.clientWidth + 1)).toBe(true);
    await root.screenshot({ path: `test-results/mux-listing-cards-${info.project.name}.png` });
  });

  test('MUX-22: Column::phone in the table partial card mode', async ({ page }) => {
    await open(page);
    const row = page.getByTestId('column-phone').locator('tbody tr').first();
    const name = row.locator('td[data-nowo-ui-phone="primary"]');
    await expect(name).toHaveText('Demo Admin');
    await expect(row.locator('td[data-nowo-ui-phone="hide"]')).toBeHidden();
    expect((await name.boundingBox())!.y).toBeLessThan((await row.locator('td[data-label="Role"]').boundingBox())!.y);
    const before = await name.evaluate((el) => getComputedStyle(el, '::before').content);
    expect(['none', 'normal', '']).toContain(before);
  });

  test('MUX-04 / MUX-161: touch targets and the font floor', async ({ page }) => {
    await open(page);
    const coarse = await page.evaluate(() => matchMedia('(pointer: coarse)').matches);
    if (coarse) {
      const tab = (await page.getByTestId('tabs').locator('.nowo-ui-tabs__item').first().boundingBox())!;
      expect(tab.height).toBeGreaterThanOrEqual(43.5);
    }
    const size = await page.getByTestId('tiny-badge').evaluate((el) => Number.parseFloat(getComputedStyle(el).fontSize));
    expect(size).toBeGreaterThanOrEqual(12);
  });

  test('MUX-150: the guest header links home', async ({ page }, info) => {
    await page.route('**/_wdt/**', (route) => route.abort());
    await page.goto('/kit/mobile/guest');
    const header = page.getByTestId('guest-header');
    await expect(header.getByRole('link', { name: /UiKit demo/ })).toHaveAttribute('href', '/kit/mobile');
    await expect(header.getByRole('link', { name: 'Sign in' })).toBeVisible();
    await page.screenshot({ path: `test-results/mux-guest-${info.project.name}.png` });
  });
});
