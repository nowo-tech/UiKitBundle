import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * UiKit 2.25 side panel / drawer on /kit/side-panel: toggle with aria-controls / aria-expanded and a count in its
 * name, focus in on open and back on close, Escape, the live region, the inline column (no trap) versus the phone
 * drawer (backdrop, inert content, Tab cycle), the shortcut, the no-JavaScript state, axe in light and dark, and no
 * CSP violation (the page forbids inline style attributes and handlers).
 */
type Mode = 'light' | 'dark';

async function watchCsp(page: Page): Promise<string[]> {
  const violations: string[] = [];
  page.on('console', (msg) => {
    if (/Content Security Policy/i.test(msg.text())) violations.push(msg.text());
  });
  return violations;
}

async function setMode(page: Page, mode: Mode): Promise<void> {
  await page.evaluate((m) => {
    document.documentElement.setAttribute('data-theme', m);
    document.documentElement.setAttribute('data-bs-theme', m);
  }, mode);
}

test.describe('side panel (2.25)', () => {
  // No slide-in animation: positions and colours are final at once.
  test.use({ reducedMotion: 'reduce' });

  test('desktop: inline column, focus in and back, Escape, announcements, events', async ({ page }) => {
    const csp = await watchCsp(page);
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto('/kit/side-panel');
    const toggle = page.getByTestId('comments-toggle');
    const panel = page.getByTestId('comments-panel');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(toggle).toHaveAttribute('aria-controls', 'demo-comments');
    await expect(toggle).toHaveAccessibleName(/Comments\s*3\s*open/);
    await expect(panel).toBeHidden();

    await page.evaluate(() => {
      (window as unknown as { panelEvents: string[] }).panelEvents = [];
      document.addEventListener('nowo-ui:side-panel:open', (e) => (window as unknown as { panelEvents: string[] }).panelEvents.push(`open:${(e as CustomEvent).detail.source}`));
      document.addEventListener('nowo-ui:side-panel:close', (e) => (window as unknown as { panelEvents: string[] }).panelEvents.push(`close:${(e as CustomEvent).detail.source}`));
    });
    await toggle.click();
    await expect(page).toHaveURL(/\/kit\/side-panel$/); // handled in place, no navigation
    await expect(panel).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('#demo-comments-title')).toBeFocused();
    await expect(page.locator('[data-nowo-ui-side-panel-status][data-for="demo-comments"]')).toHaveText('Comments opened');
    // Inline: the content stays usable, no backdrop, no trap.
    await expect(page.locator('[data-nowo-ui-side-panel-main]')).not.toHaveAttribute('inert', '');
    await expect(page.locator('[data-nowo-ui-side-panel-backdrop][data-for="demo-comments"]')).toBeHidden();
    const box = await panel.boundingBox();
    expect(box!.x).toBeGreaterThan(640); // a column on the right
    await page.getByTestId('content-button').click(); // the content stays usable

    await page.getByTestId('reply').focus();
    await page.keyboard.press('Escape');
    await expect(panel).toBeHidden();
    await expect(toggle).toBeFocused();
    await expect(page.locator('[data-nowo-ui-side-panel-status][data-for="demo-comments"]')).toHaveText('Comments closed');

    await page.keyboard.press('Alt+Shift+C');
    await expect(panel).toBeVisible();
    await page.getByRole('button', { name: 'Close Comments' }).click();
    await expect(panel).toBeHidden();
    expect(await page.evaluate(() => (window as unknown as { panelEvents: string[] }).panelEvents)).toEqual(['open:toggle', 'close:escape', 'open:shortcut', 'close:close']);
    // setCount(0): no badge, the name is just the label.
    await page.evaluate(() => (window as unknown as { NowoUiSidePanel: { setCount: (id: string, n: number) => void } }).NowoUiSidePanel.setCount('demo-comments', 0));
    await expect(toggle).toHaveAccessibleName('Comments');
    expect(csp).toEqual([]);
  });

  test('phone: full-height drawer with backdrop, inert content and a Tab cycle', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 740 });
    await page.goto('/kit/side-panel');
    await page.getByTestId('comments-toggle').click();
    const panel = page.getByTestId('comments-panel');
    const box = await panel.boundingBox();
    expect(box).toEqual({ x: 0, y: 0, width: 375, height: 740 });
    await expect(page.locator('[data-nowo-ui-side-panel-main]')).toHaveAttribute('inert', '');
    await page.getByTestId('reply').focus();
    await page.keyboard.press('Tab');
    await expect(page.getByRole('button', { name: 'Close Comments' })).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(panel).toBeHidden();
    await expect(page.locator('[data-nowo-ui-side-panel-main]')).not.toHaveAttribute('inert', '');
    await expect(page.getByTestId('comments-toggle')).toBeFocused();
  });

  test('phone: the drawer follows the dynamic viewport height and keeps its last control on screen (2.27.1)', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 740 });
    await page.goto('/kit/side-panel');
    await page.getByTestId('comments-toggle').click();
    const panel = page.getByTestId('comments-panel');
    await expect(panel).toBeVisible();
    const sizes = await panel.evaluate((el) => {
      const probe = document.createElement('div');
      probe.className = 'nowo-ui-dvh-probe';
      probe.setAttribute('aria-hidden', 'true');
      document.body.append(probe);
      // CSSOM only (the page forbids style attributes): a 100dvh probe for comparison
      probe.style.position = 'fixed';
      probe.style.height = '100dvh';
      const dvh = probe.getBoundingClientRect().height;
      probe.remove();
      const body = el.querySelector('.nowo-ui-side-panel__body') as HTMLElement;
      return {
        supported: CSS.supports('height', '100dvh'),
        dvh,
        height: el.getBoundingClientRect().height,
        bottom: el.getBoundingClientRect().bottom,
        inner: window.innerHeight,
        bodyPaddingBottom: getComputedStyle(body).paddingBottom,
      };
    });
    expect(sizes.supported).toBe(true);
    expect(sizes.height).toBe(sizes.dvh);
    expect(sizes.bottom).toBeLessThanOrEqual(sizes.inner);
    expect(sizes.bodyPaddingBottom).toBe('16px'); // 1rem + env(safe-area-inset-bottom), 0 in this browser

    // A shorter viewport (a browser toolbar showing): the drawer shrinks with it and the last control stays reachable.
    await page.setViewportSize({ width: 375, height: 560 });
    await expect.poll(async () => (await panel.boundingBox())!.height).toBe(560);
    const reply = page.getByTestId('reply');
    await reply.scrollIntoViewIfNeeded();
    const box = (await reply.boundingBox())!;
    expect(box.y + box.height).toBeLessThanOrEqual(560);
    await reply.click();
  });

  test('overlay panel at the start side closes from its backdrop', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto('/kit/side-panel');
    await page.getByTestId('outline-toggle').click();
    const outline = page.getByTestId('outline-panel');
    await expect(outline).toBeVisible();
    expect((await outline.boundingBox())!.x).toBe(0);
    await page.mouse.click(1200, 450);
    await expect(outline).toBeHidden();
    await expect(page.getByTestId('outline-toggle')).toHaveAttribute('aria-expanded', 'false');
  });

  test('without JavaScript the toggle and the close link switch the server-rendered state', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto('/kit/side-panel');
    await expect(page.getByTestId('comments-panel')).toBeHidden();
    await page.getByTestId('comments-toggle').click();
    await expect(page).toHaveURL(/panel=1/);
    await expect(page.getByTestId('comments-panel')).toBeVisible();
    await expect(page.getByTestId('comments-toggle')).toHaveAttribute('aria-expanded', 'true');
    await page.getByRole('button', { name: 'Close Comments' }).click();
    await expect(page).toHaveURL(/panel=0/);
    await expect(page.getByTestId('comments-panel')).toBeHidden();
    await context.close();
  });

  for (const mode of ['light', 'dark'] as Mode[]) {
    test(`axe: no violations with the panel open (${mode}, desktop and phone)`, async ({ page }) => {
      for (const width of [1280, 375]) {
        await page.setViewportSize({ width, height: 800 });
        await page.goto('/kit/side-panel?panel=1');
        await setMode(page, mode);
        await expect(page.getByTestId('comments-panel')).toBeVisible();
        const axe = await new AxeBuilder({ page }).include('[data-testid="comments-panel"]').include('[data-testid="comments-toggle"]').analyze();
        expect(axe.violations.map((v) => `${v.id}: ${v.nodes.map((n) => `${n.target.join(' ')} — ${n.failureSummary}`).join(', ')}`)).toEqual([]);
      }
    });
  }
});
