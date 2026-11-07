import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/** The emoji picker on /kit/emoji-show-more, real mouse and keyboard in Chromium, Firefox and WebKit. */
test.describe.configure({ timeout: 45_000 });

type Violation = { directive: string };

async function watchCsp(page: Page): Promise<() => Promise<Violation[]>> {
  await page.route('**/_wdt/**', (route) => route.abort());
  await page.route('https://cdn.jsdelivr.net/**', (route) => route.abort());
  await page.addInitScript(() => {
    const seen: { directive: string }[] = [];
    (window as unknown as { cspSeen: typeof seen }).cspSeen = seen;
    document.addEventListener('securitypolicyviolation', (e) => seen.push({ directive: e.effectiveDirective }));
  });
  return () => page.evaluate(() => (window as unknown as { cspSeen: { directive: string }[] }).cspSeen.filter((v) => v.directive.endsWith('-attr')));
}

const PAGE = '/kit/emoji-show-more';
const field = (page: Page) => page.getByRole('textbox', { name: 'Board icon' });
const toggle = (page: Page) => page.locator('[data-nowo-ui-emoji-picker-toggle]');
const search = (page: Page) => page.locator('[data-nowo-ui-emoji-picker-search]');
const panel = (page: Page) => page.locator('[data-nowo-ui-emoji-picker-panel]');

/** Lets running transitions end: a box or a colour read during one is not the final one. */
const settled = (page: Page): Promise<unknown> => page.evaluate(() => Promise.all(document.getAnimations().filter((a) => a instanceof CSSTransition).map((a) => a.finished.catch(() => undefined))));

test.describe('emoji picker', () => {
  test('keyboard only: open, search, arrows, Enter, focus back, and the server takes it', async ({ page }) => {
    const csp = await watchCsp(page);
    await page.goto(PAGE);
    await expect(toggle(page)).toBeVisible();
    await field(page).focus();
    await page.keyboard.press('Tab');
    await expect(toggle(page)).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(panel(page)).toBeVisible();
    await expect(search(page)).toBeFocused();
    await expect(toggle(page)).toHaveAttribute('aria-expanded', 'true');
    await page.keyboard.type('thumbs');
    await expect(page.locator('[role="gridcell"]')).toHaveCount(2);
    await expect(page.locator('[data-nowo-ui-emoji-picker-status]')).toHaveText('2 results');
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('ArrowRight');
    const active = page.locator('[role="gridcell"].is-active');
    await expect(active).toHaveAttribute('aria-label', 'thumbs-down');
    await expect(search(page)).toHaveAttribute('aria-activedescendant', (await active.getAttribute('id'))!);
    await expect(search(page)).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(panel(page)).toBeHidden();
    await expect(toggle(page)).toBeFocused();
    await expect(field(page)).toHaveValue('👎');
    await expect(page).toHaveURL(new RegExp(`${PAGE}$`)); // Enter did not submit the form
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByTestId('saved')).toHaveText('Saved: 👎');
    expect(await csp()).toEqual([]);
  });

  test('mouse: a click on a cell picks it, a click outside closes', async ({ page }) => {
    await page.goto(PAGE);
    await toggle(page).click();
    await page.locator('[role="gridcell"][aria-label="tada"]').click();
    await expect(field(page)).toHaveValue('🎉');
    await expect(panel(page)).toBeHidden();
    await toggle(page).click();
    await expect(panel(page)).toBeVisible();
    await page.locator('h1').click();
    await expect(panel(page)).toBeHidden();
  });

  test('Escape closes the panel, not a modal dialog around it', async ({ page }) => {
    await page.goto(PAGE);
    await page.evaluate(() => {
      const dialog = document.createElement('dialog');
      dialog.id = 'around';
      dialog.append(document.querySelector('[data-nowo-ui-emoji-picker]')!);
      document.querySelector('main')!.append(dialog);
      dialog.showModal();
    });
    await toggle(page).click();
    await expect(panel(page)).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(panel(page)).toBeHidden();
    await expect(page.locator('#around')).toHaveAttribute('open', '');
    await expect(toggle(page)).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(page.locator('#around')).not.toHaveAttribute('open', '');
  });

  test('typing more than one is cut in the browser, and refused by the server without it', async ({ page }) => {
    await page.goto(PAGE);
    await field(page).fill('👍🏽abc');
    await field(page).blur();
    await expect(field(page)).toHaveValue('👍🏽');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByTestId('saved')).toHaveText('Saved: 👍🏽');
  });

  test.describe('without JavaScript', () => {
    test.use({ javaScriptEnabled: false });

    test('a plain input: no toggle, no panel, and the server checks the value', async ({ page }) => {
      await page.goto(PAGE);
      await expect(field(page)).toBeVisible();
      await expect(toggle(page)).toBeHidden();
      await expect(panel(page)).toBeHidden();
      await field(page).fill('🚀🚀');
      await page.getByRole('button', { name: 'Save' }).click();
      await expect(page.getByTestId('saved')).toHaveCount(0);
      await expect(page.locator('body')).toContainText('Enter one emoji or character.');
      await field(page).fill('🇪🇸');
      await page.getByRole('button', { name: 'Save' }).click();
      await expect(page.getByTestId('saved')).toHaveText('Saved: 🇪🇸');
    });
  });

  test.describe('HR-M1: the panel is always reachable', () => {
    // The debug toolbar would sit on a field at the bottom of the window.
    test.beforeEach(async ({ page }) => {
      await page.route('**/_wdt/**', (route) => route.abort());
    });

    async function expectReachable(page: Page): Promise<void> {
      await expect(panel(page)).toBeVisible();
      await settled(page);
      const view = page.viewportSize()!;
      const box = (await panel(page).boundingBox())!;
      expect(box.x).toBeGreaterThanOrEqual(0);
      expect(box.y).toBeGreaterThanOrEqual(0);
      expect(box.x + box.width).toBeLessThanOrEqual(view.width);
      expect(box.y + box.height).toBeLessThanOrEqual(view.height);
      // Really on top where it is: what the pointer would hit.
      const hit = await page.evaluate(() => {
        const at = (el: Element): boolean => {
          const r = el.getBoundingClientRect();
          const found = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
          return !!found && (found === el || el.contains(found));
        };
        return { search: at(document.querySelector('[data-nowo-ui-emoji-picker-search]')!), cell: at(document.querySelector('[role="gridcell"]')!) };
      });
      expect(hit).toEqual({ search: true, cell: true });
      await page.locator('[role="gridcell"][aria-label="grin"]').click();
      await expect(panel(page)).toBeHidden();
    }

    /** The picker's root as a 120 px wide box, fixed at one spot on the screen. */
    const fixField = (page: Page, where: 'top-left' | 'bottom' | 'bottom-right') =>
      page.evaluate((spot) => {
        const root = document.querySelector<HTMLElement>('[data-nowo-ui-emoji-picker]')!;
        const { style } = root;
        style.setProperty('position', 'fixed');
        style.setProperty('width', '120px');
        style.setProperty('z-index', '5');
        style.setProperty(spot === 'top-left' ? 'top' : 'bottom', '0');
        style.setProperty(spot === 'bottom-right' ? 'right' : 'left', '0');
      }, where);

    test('a 120 px field at the left', async ({ page }) => {
      await page.goto(PAGE);
      await fixField(page, 'top-left');
      await toggle(page).click();
      await expectReachable(page);
      await expect(field(page)).toHaveValue('😁');
    });

    test('a field at the bottom of the viewport opens above it', async ({ page }) => {
      await page.goto(PAGE);
      await fixField(page, 'bottom');
      await toggle(page).click();
      await settled(page);
      const fieldBox = (await field(page).boundingBox())!;
      const box = (await panel(page).boundingBox())!;
      expect(box.y + box.height).toBeLessThanOrEqual(fieldBox.y);
      await expectReachable(page);
    });

    test('a phone-sized window, with little height too', async ({ page }) => {
      await page.setViewportSize({ width: 320, height: 300 });
      await page.goto(PAGE);
      await fixField(page, 'bottom-right');
      await toggle(page).click();
      await expectReachable(page);
    });

    test('the kit\'s remote form dialog, whose body scrolls', async ({ page }) => {
      await page.setViewportSize({ width: 1000, height: 420 }); // a short body, as on a laptop with a long form
      await page.goto('/kit/remote-form');
      await page.evaluate(() => {
        const opener = document.createElement('a');
        opener.id = 'open-it';
        opener.href = '/kit/emoji-show-more/fragment';
        opener.textContent = 'open';
        opener.setAttribute('data-nowo-ui-dialog-open', 'destination-dialog');
        opener.setAttribute('data-nowo-ui-dialog-src', '/kit/emoji-show-more/fragment');
        opener.setAttribute('data-nowo-ui-dialog-form', '');
        document.querySelector('main')?.append(opener);
      });
      await page.locator('#open-it').click();
      const inDialog = page.locator('#destination-dialog [data-nowo-ui-emoji-picker-toggle]');
      await expect(inDialog).toBeVisible();
      const body = page.locator('#destination-dialog [data-nowo-ui-dialog-body]');
      // The size the audit measured for this body, fixed so that a panel inside it could not fit, whatever the window.
      await body.evaluate((el: HTMLElement) => el.style.setProperty('max-height', '140px'));
      await inDialog.scrollIntoViewIfNeeded();
      const before = await body.evaluate((el) => ({ scrollHeight: el.scrollHeight, scrollTop: el.scrollTop, overflow: getComputedStyle(el).overflowY }));
      expect(before.overflow).toMatch(/auto|scroll/);
      await inDialog.click();
      await expect(panel(page)).toBeVisible();
      await expect(search(page)).toBeFocused();
      expect((await body.evaluate((el) => el.scrollHeight))).toBe(before.scrollHeight); // the panel does not grow the body
      await settled(page);
      expect(await body.evaluate((el) => el.scrollTop)).toBe(before.scrollTop); // and does not make it scroll
      // No part of it is cut off by the body: each corner of the panel is the panel, before anything scrolls.
      const corners = await page.evaluate(() => {
        const open = document.querySelector('[data-nowo-ui-emoji-picker-panel]') as HTMLElement;
        const r = open.getBoundingClientRect();
        // Inside the rounded corners.
        return [[r.left + 10, r.top + 10], [r.right - 10, r.top + 10], [r.left + 10, r.bottom - 10], [r.right - 10, r.bottom - 10]].map(([x, y]) => {
          const found = document.elementFromPoint(x!, y!);
          return !!found && open.contains(found);
        });
      });
      expect(corners).toEqual([true, true, true, true]);
      await page.keyboard.press('ArrowDown');
      await expectReachable(page);
      await expect(page.locator('#destination-dialog')).toHaveAttribute('open', '');
      await expect(page.locator('#destination-dialog input[type="text"]').first()).toHaveValue('😁');
      // And Escape belongs to the panel there.
      await inDialog.click();
      await page.keyboard.press('Escape');
      await expect(panel(page)).toBeHidden();
      await expect(page.locator('#destination-dialog')).toHaveAttribute('open', '');
    });
  });

  test('HR-L3: in RTL, ArrowRight moves to the right on screen', async ({ page }) => {
    await page.goto(PAGE);
    await page.evaluate(() => document.documentElement.setAttribute('dir', 'rtl'));
    await toggle(page).click();
    await page.keyboard.press('ArrowDown');
    const x = (): Promise<number> => page.locator('[role="gridcell"].is-active').evaluate((el) => el.getBoundingClientRect().left);
    await page.keyboard.press('ArrowLeft');
    const first = await x();
    await page.keyboard.press('ArrowRight');
    expect(await x()).toBeGreaterThan(first);
  });

  test('HR-L4: a click on the panel keeps focus in the box, Escape then closes it; Tab goes to the toggle in every engine', async ({ page }) => {
    await page.goto(PAGE);
    await toggle(page).click();
    await page.locator('[data-nowo-ui-emoji-picker-status]').click();
    await expect(search(page)).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(panel(page)).toBeHidden();
    await expect(toggle(page)).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(search(page)).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(panel(page)).toBeHidden();
    await expect(toggle(page)).toBeFocused();
  });

  test('HR-L5: a morph that puts hidden back on the toggle does not keep it hidden', async ({ page }) => {
    await page.goto(PAGE);
    await expect(toggle(page)).toBeVisible();
    await toggle(page).evaluate((el) => el.setAttribute('hidden', ''));
    await expect(toggle(page)).toBeVisible();
  });

  test('HR-L8: the current value is selected for a screen reader, and visible in forced colours', async ({ page, browserName }) => {
    await page.goto(PAGE);
    await field(page).fill('🚀');
    await toggle(page).click();
    const current = page.locator('[role="gridcell"][aria-selected="true"]');
    await expect(current).toHaveCount(1);
    await expect(current).toHaveText('🚀');
    test.skip(browserName !== 'chromium', 'Playwright emulates forced colours in Chromium');
    await page.emulateMedia({ forcedColors: 'active' });
    expect(await current.evaluate((el) => getComputedStyle(el).borderTopWidth)).toBe('2px');
  });

  test('axe: no violations with the panel closed and open', async ({ page }) => {
    await page.goto(PAGE);
    const scope = '[data-testid="emoji-form"]';
    expect((await new AxeBuilder({ page }).include(scope).analyze()).violations).toEqual([]);
    await toggle(page).click();
    await page.keyboard.press('ArrowDown');
    await settled(page); // contrast is measured on the final colours, not during the fade
    expect((await new AxeBuilder({ page }).include(scope).analyze()).violations).toEqual([]);
  });
});
