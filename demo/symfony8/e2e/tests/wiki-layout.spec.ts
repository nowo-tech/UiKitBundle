import { test, expect } from '@playwright/test';

/**
 * UiKit 2.48 Wiki page layout on /kit/wiki-layout: the comments panel has `drawer_below: 'lg'` and is rendered open,
 * as a host store that remembers the inline column would. Audit LV-L2: at 1024 px (under the lg breakpoint) it starts
 * closed instead of a modal drawer (no inert content, no backdrop, no overlay), no event is dispatched, and the toggle
 * still opens it as a drawer. At 1280 px it stays an open inline column. The rail toggle collapses the tree in place.
 * The panel is closed before the first paint by js/nowo-ui-layout-state.js in <head>; nowo-ui-side-panel.js syncs the
 * toggle when it starts.
 */
test.describe('wiki layout (2.48)', () => {
  test.use({ reducedMotion: 'reduce' });

  test('1024 px: a server-open lg panel starts closed, not as a modal drawer (LV-L2)', async ({ page }) => {
    await page.setViewportSize({ width: 1024, height: 768 });
    await page.addInitScript(() => {
      const w = window as unknown as { panelEvents: string[] };
      w.panelEvents = [];
      document.addEventListener('nowo-ui:side-panel:open', () => w.panelEvents.push('open'));
      document.addEventListener('nowo-ui:side-panel:close', () => w.panelEvents.push('close'));
    });
    await page.goto('/kit/wiki-layout');
    const panel = page.getByTestId('comments-panel');
    const toggle = page.getByTestId('comments-toggle');
    await expect(page.getByTestId('comments-panel')).toHaveAttribute('data-nowo-ui-side-panel-ready', '');
    await expect(panel).toBeHidden();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(page.locator('[data-nowo-ui-side-panel-main]')).not.toHaveAttribute('inert', '');
    await expect(page.locator('[data-nowo-ui-side-panel-backdrop][data-for="wiki-comments"]')).toBeHidden();
    expect(await page.evaluate(() => document.documentElement.hasAttribute('data-nowo-overlay'))).toBe(false);
    expect(await page.evaluate(() => Object.keys(window.localStorage))).toEqual([]);
    expect(await page.evaluate(() => (window as unknown as { panelEvents: string[] }).panelEvents)).toEqual([]);
    // The content is usable at once.
    await page.getByTestId('content-button').click();

    // The toggle opens it as the lg drawer, as before.
    await toggle.click();
    await expect(panel).toBeVisible();
    await expect(panel).toHaveAttribute('data-drawer', '');
    await expect(page.locator('[data-nowo-ui-side-panel-main]')).toHaveAttribute('inert', '');
    await page.keyboard.press('Escape');
    await expect(panel).toBeHidden();
    await expect(page.locator('[data-nowo-ui-side-panel-main]')).not.toHaveAttribute('inert', '');
  });

  test('1280 px: the server-open panel stays an open inline column', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto('/kit/wiki-layout');
    const panel = page.getByTestId('comments-panel');
    await expect(panel).toHaveAttribute('data-nowo-ui-side-panel-ready', '');
    await expect(panel).toBeVisible();
    await expect(panel).not.toHaveAttribute('data-drawer', '');
    await expect(page.getByTestId('comments-toggle')).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('[data-nowo-ui-side-panel-main]')).not.toHaveAttribute('inert', '');
  });

  test('the rail toggle collapses the tree in place and keeps modified clicks for the browser', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto('/kit/wiki-layout');
    const rail = page.getByTestId('rail-toggle');
    await expect(rail).toHaveAttribute('data-nowo-ui-rail-ready', '');
    await expect(rail).toHaveAttribute('href', /tree=rail/);
    await rail.click();
    await expect(page).toHaveURL(/\/kit\/wiki-layout$/);
    await expect(page.getByTestId('tree')).toHaveAttribute('data-nowo-ui-rail', 'collapsed');
    await expect(rail).toHaveAttribute('aria-expanded', 'false');
    await expect(page.locator('#wiki-tree-body')).toBeHidden();
    await rail.click();
    await expect(page.getByTestId('tree')).toHaveAttribute('data-nowo-ui-rail', 'expanded');
    // LV-I6: a modified click is not prevented (the browser opens the link in a new tab).
    const prevented = await rail.evaluate((el) => {
      let seen = true;
      // Runs after the kit's document listener: records its decision, then cancels the navigation to keep this page.
      window.addEventListener('click', (e) => {
        seen = e.defaultPrevented;
        e.preventDefault();
      }, { once: true });
      el.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, ctrlKey: true }));
      return seen;
    });
    expect(prevented).toBe(false);
    await expect(page.getByTestId('tree')).toHaveAttribute('data-nowo-ui-rail', 'expanded');
  });
});
