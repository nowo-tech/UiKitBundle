import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/** The async grouped combobox (2.53) on /kit/combobox, real keyboard and mouse in Chromium, Firefox and WebKit. */
test.describe.configure({ timeout: 45_000 });

async function watchCsp(page: Page): Promise<() => Promise<string[]>> {
  await page.route('**/_wdt/**', (route) => route.abort());
  await page.route('https://cdn.jsdelivr.net/**', (route) => route.abort());
  await page.addInitScript(() => {
    const seen: string[] = [];
    (window as unknown as { cspSeen: string[] }).cspSeen = seen;
    document.addEventListener('securitypolicyviolation', (e) => seen.push(`${e.effectiveDirective} ${e.blockedURI}`));
  });
  // Inline style attributes and handlers: what the combobox could cause (as in emoji-picker.spec.ts). The dev toolbar's own inline
  // script is not the combobox's.
  return () => page.evaluate(() => (window as unknown as { cspSeen: string[] }).cspSeen.filter((v) => /^[a-z-]+-attr /.test(v)));
}

const PAGE = '/kit/combobox';
const field = (page: Page) => page.getByRole('combobox', { name: 'Wiki page' });
const listbox = (page: Page) => page.locator('#form_page_combobox-listbox');
const status = (page: Page) => page.locator('[data-nowo-ui-combobox] [role="status"]').first();
const options = (page: Page) => listbox(page).getByRole('option');

/** Lets running transitions end: a box read during one is not the final one. */
const settled = (page: Page): Promise<unknown> =>
  page.evaluate(() => Promise.all(document.getAnimations().filter((a) => a instanceof CSSTransition).map((a) => a.finished.catch(() => undefined))));

test.describe('combobox', () => {
  test('keyboard only: type, grouped results announced, arrows, Enter picks without submitting, the server takes it', async ({ page }) => {
    const csp = await watchCsp(page);
    await page.goto(PAGE);
    await expect(field(page)).toHaveAttribute('aria-expanded', 'false');
    await field(page).focus();
    await page.keyboard.type('s');
    await expect(status(page)).toHaveText('Type at least 2 characters');
    await page.keyboard.type('t');
    await expect(listbox(page)).toBeVisible();
    await expect(field(page)).toHaveAttribute('aria-expanded', 'true');
    await expect(listbox(page).getByRole('group', { name: 'This wiki' })).toBeVisible();
    await expect(listbox(page).getByRole('group', { name: 'Other wikis' })).toBeVisible();
    await expect(options(page)).toHaveCount(4);
    await expect(status(page)).toHaveText('4 results');
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('ArrowDown');
    const active = listbox(page).locator('[role="option"][aria-selected="true"]');
    await expect(active).toContainText('Install the kit');
    await expect(field(page)).toHaveAttribute('aria-activedescendant', (await active.getAttribute('id'))!);
    await expect(field(page)).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(listbox(page)).toBeHidden();
    await expect(field(page)).toHaveValue('2');
    await expect(field(page)).toBeFocused();
    await expect(page).toHaveURL(new RegExp(`${PAGE}$`)); // Enter did not submit the form
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByTestId('saved')).toContainText('page=2');
    expect(await csp()).toEqual([]);
  });

  test('mouse: a click on an option picks it; the value input takes the id and the field the title; typing clears the id', async ({ page }) => {
    await page.goto(PAGE);
    const link = page.getByRole('combobox', { name: 'Link to page' });
    await link.fill('rel');
    await page.getByRole('option', { name: /Release notes/ }).click();
    await expect(link).toHaveValue('Release notes');
    await expect(page.locator('#form_link_id')).toHaveValue('3');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByTestId('saved')).toContainText('link=Release notes link_id=3');
    const again = page.getByRole('combobox', { name: 'Link to page' });
    await again.fill('Release note');
    await expect(page.locator('#form_link_id')).toHaveValue('');
  });

  test('a stale request is aborted and its late answer never shows', async ({ page }) => {
    await page.goto(PAGE);
    let first = true;
    await page.route('**/kit/combobox/search?*', async (route) => {
      if (first) {
        first = false;
        await new Promise((r) => setTimeout(r, 1500));
        await route.fulfill({ json: { groups: [{ label: 'Late', options: [{ label: 'Stale answer' }] }] } }).catch(() => undefined);
        return;
      }
      await route.continue();
    });
    await field(page).fill('st');
    await page.waitForTimeout(400); // the first request is in flight
    await field(page).fill('stat');
    await expect(options(page)).toHaveCount(1); // Status page
    await page.waitForTimeout(1600);
    await expect(listbox(page)).not.toContainText('Stale answer');
  });

  test('titles stay text: markup in an answer is never parsed', async ({ page }) => {
    await page.goto(PAGE);
    await field(page).fill('img');
    await expect(options(page)).toHaveCount(1);
    await expect(options(page).first()).toContainText('<img src=x onerror=');
    await expect(listbox(page).locator('img')).toHaveCount(0);
    await page.waitForTimeout(200);
    expect(await page.title()).not.toBe('pwned');
  });

  test('Escape closes the list, not a modal dialog around it', async ({ page }) => {
    await page.goto(PAGE);
    await page.evaluate(() => {
      const dialog = document.createElement('dialog');
      dialog.id = 'around';
      dialog.append(document.querySelector('[data-nowo-ui-combobox]')!);
      document.querySelector('main')!.append(dialog);
      dialog.showModal();
    });
    await field(page).fill('st');
    await expect(listbox(page)).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(listbox(page)).toBeHidden();
    await expect(page.locator('#around')).toHaveAttribute('open', '');
    await page.keyboard.press('Escape');
    await expect(page.locator('#around')).not.toHaveAttribute('open', '');
  });

  test('no results and a failing endpoint are announced', async ({ page }) => {
    await page.goto(PAGE);
    await field(page).fill('zzz');
    await expect(status(page)).toHaveText('No results');
    await expect(listbox(page)).toBeHidden();
    await page.route('**/kit/combobox/search?*', (route) => route.fulfill({ status: 500, body: 'oops' }));
    await field(page).fill('zzzz');
    await expect(status(page)).toHaveText('The search failed. Try again.');
  });

  for (const scheme of ['light', 'dark'] as const) {
    test(`open list passes axe in ${scheme} mode`, async ({ page }) => {
      await page.emulateMedia({ colorScheme: scheme });
      await page.goto(PAGE);
      await field(page).fill('st');
      await expect(options(page)).toHaveCount(4);
      await page.keyboard.press('ArrowDown');
      await settled(page);
      const results = await new AxeBuilder({ page }).include('[data-testid="combobox-form"]').include('#form_page_combobox-listbox').analyze();
      expect(results.violations.map((v) => `${v.id}: ${v.nodes.map((n) => `${n.target.join(' ')} ${n.failureSummary ?? ''}`).join(', ')}`)).toEqual([]);
    });
  }

  test('right-to-left: the list lines up with the field\'s right edge', async ({ page }) => {
    await page.goto(PAGE);
    await page.evaluate(() => document.documentElement.setAttribute('dir', 'rtl'));
    await field(page).fill('st');
    await expect(listbox(page)).toBeVisible();
    await settled(page);
    const f = (await field(page).boundingBox())!;
    const l = (await listbox(page).boundingBox())!;
    expect(Math.abs(l.x + l.width - (f.x + f.width))).toBeLessThanOrEqual(2);
    expect(l.width).toBeGreaterThanOrEqual(f.width - 1);
  });

  test('a phone-sized window: the list stays inside the screen and an option is reachable', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 640 });
    await page.goto(PAGE);
    await field(page).fill('st');
    await expect(listbox(page)).toBeVisible();
    await settled(page);
    const box = (await listbox(page).boundingBox())!;
    expect(box.x).toBeGreaterThanOrEqual(0);
    expect(box.x + box.width).toBeLessThanOrEqual(360);
    expect(box.y + box.height).toBeLessThanOrEqual(640);
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(360);
    await options(page).nth(3).click();
    await expect(field(page)).toHaveValue('5');
  });

  test.describe('without JavaScript', () => {
    test.use({ javaScriptEnabled: false });

    test('a plain text input that submits what was typed', async ({ page }) => {
      await page.goto(PAGE);
      const plain = page.getByRole('textbox', { name: 'Wiki page' });
      await expect(plain).toBeVisible();
      await expect(plain).not.toHaveAttribute('role', 'combobox');
      await expect(listbox(page)).toBeHidden();
      await plain.fill('Getting started');
      await page.getByRole('button', { name: 'Save' }).click();
      await expect(page.getByTestId('saved')).toContainText('page=Getting started');
    });
  });
});
