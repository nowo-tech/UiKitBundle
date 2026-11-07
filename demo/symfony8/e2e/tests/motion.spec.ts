import { test, expect, type Locator, type Page } from '@playwright/test';

/**
 * Opening and closing through the motion tokens, in Chromium, Firefox and WebKit. The effect is checked through `getAnimations()`, so
 * not by timing. With durations at 0ms a close must be complete in the same task.
 */
test.describe.configure({ timeout: 45_000 });

async function noTooling(page: Page): Promise<void> {
  await page.route('**/_wdt/**', (route) => route.abort());
  await page.route('https://cdn.jsdelivr.net/**', (route) => route.abort());
}

async function openRemoteDialog(page: Page): Promise<Locator> {
  await noTooling(page);
  await page.goto('/kit/remote-form');
  await page.getByTestId('destination-add').click();
  const dialog = page.locator('#destination-dialog');
  await expect(dialog).toHaveAttribute('open', '');
  await expect(dialog.locator('form')).toBeVisible();
  return dialog;
}

test.describe('motion', () => {
  test('a dialog fades in and fades out, the state changes when it ends, and focus comes back', async ({ page, browserName }) => {
    await noTooling(page);
    await page.goto('/kit/remote-form');
    const opener = page.getByTestId('destination-add');
    const dialog = page.locator('#destination-dialog');
    await opener.click();
    await expect(dialog).toHaveAttribute('open', '');
    await expect(dialog.locator('form')).toBeVisible();
    await expect.poll(() => dialog.evaluate((el) => getComputedStyle(el).opacity)).toBe('1');
    const box = (await dialog.boundingBox())!;
    const at = await dialog.evaluate((el) => {
      (el.querySelector('[data-nowo-ui-dialog-close]') as HTMLElement).click();
      const r = el.getBoundingClientRect();
      return {
        closing: el.hasAttribute('data-nowo-ui-closing'),
        props: el.getAnimations().map((a) => (a as CSSTransition).transitionProperty),
        backdrop: (el.getAnimations({ subtree: true }) as CSSTransition[]).filter((a) => (a.effect as KeyframeEffect).pseudoElement === '::backdrop').length,
        hasBody: el.querySelector('form') !== null,
        rect: { y: r.y, width: r.width },
      };
    });
    expect(at.closing).toBe(true);
    expect(at.props).toContain('opacity');
    // WebKit does not list a ::backdrop transition in getAnimations().
    if (browserName !== 'webkit') {
      expect(at.backdrop).toBeGreaterThan(0);
    }
    expect(at.hasBody).toBe(true); // the content stays for the fade
    expect(Math.abs(at.rect.width - box.width)).toBeLessThan(box.width * 0.05); // no jump
    expect(Math.abs(at.rect.y - box.y)).toBeLessThan(box.height * 0.1);
    await expect(dialog).not.toHaveAttribute('open', '');
    await expect(dialog).toBeHidden();
    await expect(dialog).not.toHaveAttribute('data-nowo-ui-closing', '');
    await expect.poll(() => dialog.locator('form').count()).toBe(0); // then it goes
    await expect(opener).toBeFocused();
  });

  test('Escape closes a dialog through the same fade, and focus comes back', async ({ page }) => {
    const dialog = await openRemoteDialog(page);
    await page.keyboard.press('Escape');
    await expect(dialog).not.toHaveAttribute('open', '');
    await expect(page.getByTestId('destination-add')).toBeFocused();
    await expect(dialog).toBeHidden();
  });

  test('a reopen during the fade keeps the new content', async ({ page }) => {
    const dialog = await openRemoteDialog(page);
    await dialog.evaluate((el) => {
      (el.querySelector('[data-nowo-ui-dialog-close]') as HTMLElement).click();
      document.querySelector<HTMLElement>('[data-testid="destination-add"]')!.click();
    });
    await expect(dialog).toHaveAttribute('open', '');
    await page.waitForTimeout(400);
    await expect(dialog.locator('form')).toBeVisible();
  });

  test('the emoji picker panel fades both ways, and focus returns at once', async ({ page }) => {
    await noTooling(page);
    await page.goto('/kit/emoji-show-more');
    const toggle = page.locator('[data-nowo-ui-emoji-picker-toggle]');
    const panel = page.locator('[data-nowo-ui-emoji-picker-panel]');
    await toggle.click();
    await expect(panel).toBeVisible();
    await expect.poll(() => panel.evaluate((el) => getComputedStyle(el).opacity)).toBe('1');
    const closing = await page.evaluate(() => {
      const search = document.querySelector<HTMLInputElement>('[data-nowo-ui-emoji-picker-search]')!;
      search.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }));
      const open = document.querySelector<HTMLElement>('[data-nowo-ui-emoji-picker-panel]')!;
      return { closing: open.hasAttribute('data-nowo-ui-closing'), props: open.getAnimations().map((a) => (a as CSSTransition).transitionProperty), focus: document.activeElement?.hasAttribute('data-nowo-ui-emoji-picker-toggle') };
    });
    expect(closing.closing).toBe(true);
    expect(closing.focus).toBe(true);
    expect(closing.props).toContain('opacity');
    await expect(panel).toBeHidden();
    await expect.poll(() => panel.evaluate((el) => (el as HTMLElement).hidden)).toBe(true);
    await toggle.click();
    await expect(panel).toBeVisible();
  });

  test.describe('the multi-select panel', () => {
    async function openPanel(page: Page): Promise<{ panel: Locator; csp: string[] }> {
      const csp: string[] = [];
      page.on('console', (msg) => {
        // Tom Select writes the panel's styles: none of them may hit the page's style-src-attr 'none'.
        if (/inline style/i.test(msg.text())) csp.push(msg.text());
      });
      await noTooling(page);
      await page.goto('/kit/multi-select');
      await page.locator('.ts-control').click();
      const panel = page.locator('.nowo-ui-multi-select__dropdown');
      await expect(panel).toBeVisible();
      return { panel, csp };
    }

    test('fades both ways on the small scale, and focus stays in the control', async ({ page }) => {
      const { panel, csp } = await openPanel(page);
      await expect.poll(() => panel.evaluate((el) => getComputedStyle(el).opacity)).toBe('1');
      expect(await panel.evaluate((el) => getComputedStyle(el).transitionDuration)).toContain('0.15s');

      const closing = await page.evaluate(() => {
        const input = document.querySelector<HTMLInputElement>('.ts-control input')!;
        input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', keyCode: 27, bubbles: true, cancelable: true } as KeyboardEventInit));
        const el = document.querySelector<HTMLElement>('.nowo-ui-multi-select__dropdown')!;
        return {
          closing: el.hasAttribute('data-nowo-ui-closing'),
          display: getComputedStyle(el).display,
          duration: getComputedStyle(el).transitionDuration,
          props: el.getAnimations().map((a) => (a as CSSTransition).transitionProperty),
          expanded: input.getAttribute('aria-expanded'),
          focus: document.activeElement === input,
        };
      });
      expect(closing.closing).toBe(true);
      expect(closing.display).toBe('block');
      expect(closing.duration).toContain('0.11s');
      expect(closing.props).toContain('opacity');
      expect(closing.expanded).toBe('false');
      expect(closing.focus).toBe(true);
      await expect(panel).toBeHidden();
      await expect(panel).not.toHaveAttribute('data-nowo-ui-closing');
      expect(csp).toEqual([]);
    });

    test('a choice still lands in the select, and the panel opens again after a close', async ({ page }) => {
      const { panel } = await openPanel(page);
      await panel.locator('.option', { hasText: 'Pending' }).click();
      await expect(page.locator('.ts-control .item')).toHaveText(/Pending/);
      expect(await page.locator('#status').evaluate((el) => [...(el as HTMLSelectElement).selectedOptions].map((o) => o.value))).toEqual(['pending']);
      await page.locator('h1').click();
      await expect(panel).toBeHidden();
      await page.locator('.ts-control').click();
      await expect(panel).toBeVisible();
      await expect.poll(() => panel.evaluate((el) => getComputedStyle(el).opacity)).toBe('1');
    });

    test('opening again during the fade keeps it open', async ({ page }) => {
      const { panel } = await openPanel(page);
      await page.evaluate(() => {
        const ts = (document.querySelector('#status') as unknown as { tomselect: { close: () => void; open: () => void } }).tomselect;
        ts.close();
        ts.open();
      });
      await page.waitForTimeout(300);
      await expect(panel).toBeVisible();
      await expect(panel).not.toHaveAttribute('data-nowo-ui-closing');
    });

    test('reduced motion keeps a short fade without movement, and 0ms closes in the same task', async ({ page }) => {
      await page.emulateMedia({ reducedMotion: 'reduce' });
      const { panel } = await openPanel(page);
      await expect.poll(() => panel.evaluate((el) => getComputedStyle(el).opacity)).toBe('1');
      expect(await panel.evaluate((el) => getComputedStyle(el).transitionDuration)).toContain('0.08s');
      await page.addStyleTag({ content: ':root { --nowo-ui-motion-small: 0ms; --nowo-ui-motion-small-close: 0ms; }' });
      const display = await page.evaluate(() => {
        (document.querySelector('#status') as unknown as { tomselect: { close: () => void } }).tomselect.close();
        return getComputedStyle(document.querySelector('.nowo-ui-multi-select__dropdown')!).display;
      });
      expect(display).toBe('none');
    });
  });

  test('the more menu (popover) fades on Escape, and a confirm opened from it closes on the first Escape', async ({ page }) => {
    await noTooling(page);
    await page.goto('/page-contract/orders/1042');
    const toggle = page.locator('[data-nowo-ui-header-more] [data-nowo-ui-popover-trigger]');
    const panel = page.locator('#nowo-ui-header-more-panel');
    await toggle.click();
    await expect(panel).toBeVisible();
    await expect.poll(() => panel.evaluate((el) => getComputedStyle(el).opacity)).toBe('1');
    await page.keyboard.press('Escape');
    await expect(toggle).toBeFocused();
    await expect(panel).toBeHidden();
    await toggle.click();
    await panel.locator('.nowo-ui-menu__item').last().click();
    const dialog = page.locator('dialog[open]');
    await expect(dialog).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
  });

  test('a form dialog opened from the kit\'s confirm path closes through a cancel button', async ({ page }) => {
    await noTooling(page);
    await page.goto('/kit/dialogs');
    await page.getByTestId('open-medium').click();
    const dialog = page.locator('#medium-dialog');
    await expect(dialog).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await page.getByTestId('open-medium').click();
    await expect(dialog).toBeVisible();
  });

  test.describe('reduced motion', () => {
    test.use({ reducedMotion: 'reduce' });

    test('a short fade and no movement, from the tokens', async ({ page }) => {
      const dialog = await openRemoteDialog(page);
      await expect.poll(() => dialog.evaluate((el) => getComputedStyle(el).opacity)).toBe('1');
      const tokens = await page.evaluate(() => {
        const style = getComputedStyle(document.documentElement);
        return ['small', 'medium-close', 'lift', 'scale'].map((n) => style.getPropertyValue(`--nowo-ui-motion-${n}`).trim());
      });
      expect(tokens).toEqual(['80ms', '80ms', '0px', '1']);
      const transform = await dialog.evaluate((el) => {
        (el.querySelector('[data-nowo-ui-dialog-close]') as HTMLElement).click();
        return getComputedStyle(el).transform;
      });
      expect(['none', 'matrix(1, 0, 0, 1, 0, 0)']).toContain(transform);
      await expect(dialog).toBeHidden();
    });
  });

  test('a host setting overrides everything, and with 0ms a close is complete at once', async ({ page }) => {
    await noTooling(page);
    await page.goto('/kit/remote-form');
    await page.evaluate(() => {
      for (const name of ['small', 'small-close', 'medium', 'medium-close']) {
        document.documentElement.style.setProperty(`--nowo-ui-motion-${name}`, '0ms');
      }
    });
    await page.getByTestId('destination-add').click();
    const dialog = page.locator('#destination-dialog');
    await expect(dialog.locator('form')).toBeVisible();
    const state = await dialog.evaluate((el) => {
      (el.querySelector('[data-nowo-ui-dialog-close]') as HTMLElement).click();
      return { open: (el as HTMLDialogElement).open, display: getComputedStyle(el).display, closing: el.hasAttribute('data-nowo-ui-closing') };
    });
    expect(state).toEqual({ open: false, display: 'none', closing: false });
    await expect(dialog.locator('form')).toHaveCount(0);
  });

  test('html[data-motion="reduce"] goes the same way without the system setting', async ({ page }) => {
    await noTooling(page);
    await page.goto('/kit/remote-form');
    await page.evaluate(() => document.documentElement.setAttribute('data-motion', 'reduce'));
    const lift = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--nowo-ui-motion-lift').trim());
    expect(lift).toBe('0px');
  });

  test('no style attribute from the kit on a closed page', async ({ page }) => {
    const response = await page.goto('/kit/emoji-show-more');
    const html = await response!.text();
    expect(html.slice(html.indexOf('<main'), html.indexOf('</main>'))).not.toContain('style="');
  });

});
