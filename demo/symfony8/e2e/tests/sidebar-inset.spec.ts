import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * UiKit 2.52 inset sidebar style on /demo/sidebar-layout?style=inset — Symfony Beacon's request (GitLab-like shell,
 * adopted in Beacon 1.160): the header, the aside and the footer share one surface with no border between them, the
 * content is an inset rounded panel with its own surface and a gap from the aside, the header and the viewport edges.
 * Desktop expanded / icons-only rail / hidden in both sidebar layouts, toggling without layout shift, content width,
 * a sticky side panel column and an overlay drawer, phones unchanged, and axe (wcag2a / 2aa / 21a / 21aa, colour
 * contrast included: serious + critical = 0) in light and dark.
 */
type Theme = 'light' | 'dark';
type Rect = { left: number; right: number; top: number; bottom: number; width: number; height: number };

const URL = '/demo/sidebar-layout';
const GAP = 8; // --nowo-ui-shell-inset-gap: 0.5rem
const RADIUS = '12px'; // --nowo-ui-shell-inset-radius: 0.75rem
const TRANSPARENT = 'rgba(0, 0, 0, 0)';

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
  await expect(page.locator('[data-nowo-ui-shell]')).toHaveAttribute('data-nowo-ui-sidebar-mode', /expanded|rail|hidden/);
}

async function settle(page: Page): Promise<void> {
  await page.waitForTimeout(450);
}

async function rect(page: Page, selector: string): Promise<Rect | null> {
  return page.evaluate((sel) => {
    const el = document.querySelector(sel);
    if (!el || el.getClientRects().length === 0) {
      return null;
    }
    const r = el.getBoundingClientRect();
    return { left: r.left, right: r.right, top: r.top, bottom: r.bottom, width: r.width, height: r.height };
  }, selector);
}

/** Computed paint of the shell parts. */
async function paint(page: Page): Promise<Record<string, Record<string, string>>> {
  return page.evaluate(() => {
    const css = (sel: string): Record<string, string> => {
      const s = getComputedStyle(document.querySelector(sel)!);
      return {
        bg: s.backgroundColor,
        borderBottom: s.borderBottomColor,
        borderTop: s.borderTopColor,
        borderRight: s.borderRightColor,
        radius: s.borderTopLeftRadius,
        margin: s.margin,
      };
    };
    return {
      shell: css('[data-nowo-ui-shell]'),
      header: css('.nowo-ui-header'),
      aside: css('.nowo-ui-aside'),
      footer: css('.nowo-ui-footer'),
      main: css('.nowo-ui-main'),
    };
  });
}

async function noHorizontalScroll(page: Page): Promise<void> {
  expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(0);
}

async function viewportWidth(page: Page): Promise<number> {
  return page.evaluate(() => document.documentElement.clientWidth);
}

test.describe('inset sidebar style (2.52)', () => {
  for (const layout of ['below-header', 'full-height'] as const) {
    for (const mode of ['expanded', 'rail', 'hidden'] as const) {
      test(`${layout}, ${mode}: one shell surface, inset rounded panel`, async ({ page }) => {
        await open(page, `?style=inset&layout=${layout}${mode === 'expanded' ? '' : `&mode=${mode}`}`);
        await settle(page);
        await expect(page.locator('[data-nowo-ui-shell]')).toHaveAttribute('data-nowo-ui-sidebar-style', 'inset');
        await expect(page.locator('[data-nowo-ui-shell]')).toHaveClass(/is-sidebar-inset/);
        await expect(page.getByTestId('style')).toHaveText('inset');

        const p = await paint(page);
        // one surface for the chrome, no visible separator
        expect(p.header.bg).toBe(p.shell.bg);
        expect(p.aside.bg).toBe(p.shell.bg);
        expect(p.footer.bg).toBe(p.shell.bg);
        expect(p.header.borderBottom).toBe(TRANSPARENT);
        expect(p.aside.borderRight).toBe(TRANSPARENT);
        expect(p.footer.borderTop).toBe(TRANSPARENT);
        // the panel: its own surface, rounded corners
        expect(p.main.bg).not.toBe(p.shell.bg);
        expect(p.main.radius).toBe(RADIUS);

        const main = (await rect(page, '.nowo-ui-main'))!;
        const header = (await rect(page, '.nowo-ui-header'))!;
        const aside = await rect(page, '.nowo-ui-aside');
        const footer = (await rect(page, '.nowo-ui-footer'))!;
        const start = mode === 'hidden' || !aside ? 0 : aside.right;
        expect(Math.abs(main.left - start - GAP)).toBeLessThanOrEqual(1);
        expect(Math.abs(main.top - header.bottom - GAP)).toBeLessThanOrEqual(1);
        expect(Math.abs((await viewportWidth(page)) - main.right - GAP)).toBeLessThanOrEqual(1);
        expect(Math.abs(footer.top - main.bottom - GAP)).toBeLessThanOrEqual(1);
        await noHorizontalScroll(page);
      });
    }
  }

  for (const layout of ['below-header', 'full-height'] as const) {
    test(`${layout}: toggling the aside moves only the panel's start edge (no layout shift)`, async ({ page }) => {
      await open(page, `?style=inset&layout=${layout}`);
      await settle(page);
      const before = (await rect(page, '.nowo-ui-main'))!;
      const headerBefore = (await rect(page, '.nowo-ui-header'))!;

      // sample the panel every frame while the aside goes to the rail
      const samples = page.evaluate(
        () =>
          new Promise<Array<{ top: number; right: number; height: number }>>((resolve) => {
            const out: Array<{ top: number; right: number; height: number }> = [];
            const t0 = performance.now();
            const tick = (): void => {
              const r = document.querySelector('.nowo-ui-main')!.getBoundingClientRect();
              out.push({ top: r.top, right: r.right, height: document.querySelector('.nowo-ui-header')!.getBoundingClientRect().height });
              if (performance.now() - t0 < 700) {
                requestAnimationFrame(tick);
              } else {
                resolve(out);
              }
            };
            requestAnimationFrame(tick);
          }),
      );
      await page.locator('[data-nowo-ui-burger]:visible').first().click();
      const frames = await samples;
      expect(frames.length).toBeGreaterThan(5);
      for (const f of frames) {
        expect(Math.abs(f.top - before.top)).toBeLessThanOrEqual(0.5);
        expect(Math.abs(f.right - before.right)).toBeLessThanOrEqual(0.5);
        expect(Math.abs(f.height - headerBefore.height)).toBeLessThanOrEqual(0.5);
      }
      await settle(page);
      await expect(page.locator('[data-nowo-ui-shell]')).toHaveAttribute('data-nowo-ui-sidebar-mode', 'rail');
      const after = (await rect(page, '.nowo-ui-main'))!;
      const aside = (await rect(page, '.nowo-ui-aside'))!;
      expect(Math.abs(after.left - aside.right - GAP)).toBeLessThanOrEqual(1);
      expect(after.left).toBeLessThan(before.left);
      expect(Math.abs(after.top - before.top)).toBeLessThanOrEqual(0.5);
      expect(Math.abs(after.right - before.right)).toBeLessThanOrEqual(0.5);
    });
  }

  test('content width: the panel stays full, the content is centred at the content width', async ({ page }) => {
    await open(page, '?style=inset&layout=below-header&width=content', 1600);
    await settle(page);
    const panel = (await rect(page, '.nowo-ui-main'))!;
    const content = (await rect(page, '#main-content'))!;
    const max = await page.evaluate(() => {
      const probe = document.createElement('div');
      probe.style.width = 'var(--nowo-ui-content-max-width)';
      document.body.append(probe);
      const w = probe.getBoundingClientRect().width;
      probe.remove();
      return w;
    });
    expect(Math.abs((await viewportWidth(page)) - panel.right - GAP)).toBeLessThanOrEqual(1);
    // classic content width: max width minus the 1.25rem padding on both sides
    expect(Math.abs(content.width - (max - 40))).toBeLessThanOrEqual(1);
    const leftSpace = content.left - panel.left;
    const rightSpace = panel.right - content.right;
    expect(Math.abs(leftSpace - rightSpace)).toBeLessThanOrEqual(1);
    await noHorizontalScroll(page);

    // full width (default): the content fills the panel minus its padding
    await open(page, '?style=inset&layout=below-header', 1600);
    await settle(page);
    const full = (await rect(page, '#main-content'))!;
    const fullPanel = (await rect(page, '.nowo-ui-main'))!;
    expect(Math.abs(full.width - (fullPanel.width - 40))).toBeLessThanOrEqual(1);
  });

  test('sticky side panel column and overlay drawer', async ({ page }) => {
    await open(page, '?style=inset&layout=full-height&panel=column', 1440, 400);
    await settle(page);
    const panel = page.getByTestId('side-panel');
    await expect(panel).toBeVisible();
    const before = (await panel.boundingBox())!;
    const main = (await rect(page, '.nowo-ui-main'))!;
    expect(before.x + before.width).toBeLessThanOrEqual(main.right);
    expect(before.y).toBeGreaterThan(main.top);
    const scrollY = await page.evaluate(() => {
      window.scrollTo(0, document.documentElement.scrollHeight);
      return window.scrollY;
    });
    await page.waitForTimeout(100);
    // the page scrolled past the panel's start, and the panel stuck to the top (position: sticky still works inside
    // the inset panel, which never clips)
    expect(scrollY).toBeGreaterThan(before.y + 20);
    const scrolled = (await panel.boundingBox())!;
    expect(Math.abs(scrolled.y)).toBeLessThanOrEqual(1);
    expect(await page.evaluate(() => getComputedStyle(document.querySelector('.nowo-ui-main')!).overflow)).toBe('visible');
    await noHorizontalScroll(page);

    await open(page, '?style=inset&layout=full-height&panel=drawer', 1440, 800);
    await settle(page);
    const drawer = (await page.getByTestId('side-panel').boundingBox())!;
    expect(Math.abs(drawer.x + drawer.width - (await viewportWidth(page)))).toBeLessThanOrEqual(1);
    expect(drawer.y).toBeLessThanOrEqual(1);
    await expect(page.getByTestId('side-panel')).toBeVisible();
  });

  test('390 px: the phone shell is unchanged, no inset', async ({ page }) => {
    for (const layout of ['below-header', 'full-height'] as const) {
      await open(page, `?style=inset&layout=${layout}`, 390, 844);
      await expect(page.locator('[data-nowo-ui-shell]')).toHaveAttribute('data-nowo-ui-sidebar-style', 'inset');
      const p = await paint(page);
      expect(p.main.margin).toBe('0px');
      expect(p.main.radius).toBe('0px');
      expect(p.main.bg).toBe(TRANSPARENT);
      expect(p.header.borderBottom).not.toBe(TRANSPARENT);
      const main = (await rect(page, '.nowo-ui-main'))!;
      const header = (await rect(page, '.nowo-ui-header'))!;
      expect(main.left).toBe(0);
      expect(Math.abs(main.top - header.bottom)).toBeLessThanOrEqual(1);
      expect(Math.abs(main.right - (await viewportWidth(page)))).toBeLessThanOrEqual(1);
      // same geometry as the classic style
      await open(page, `?layout=${layout}`, 390, 844);
      const classic = (await rect(page, '.nowo-ui-main'))!;
      expect(classic).toEqual(main);
      await noHorizontalScroll(page);
    }
  });

  test('classic (default) keeps the bordered chrome', async ({ page }) => {
    await open(page, '?layout=below-header', 1280);
    await expect(page.locator('[data-nowo-ui-shell]')).not.toHaveAttribute('data-nowo-ui-sidebar-style', /.+/);
    const p = await paint(page);
    expect(p.header.borderBottom).not.toBe(TRANSPARENT);
    expect(p.main.margin).toBe('0px');
    expect(p.main.radius).toBe('0px');
    // the per-user value switches it: the demo link sets ?style=inset as a preference would
    await page.getByTestId('switch-style').click();
    await expect(page.locator('[data-nowo-ui-shell]')).toHaveAttribute('data-nowo-ui-sidebar-style', 'inset');
  });

  for (const theme of ['light', 'dark'] as Theme[]) {
    for (const mode of ['expanded', 'rail'] as const) {
      test(`axe clean (contrast included): ${mode}, ${theme}`, async ({ page }) => {
        await open(page, `?style=inset&layout=full-height${mode === 'rail' ? '&mode=rail' : ''}`, 1440);
        await setTheme(page, theme);
        await settle(page);
        expect(await axe(page)).toEqual([]);
        // landmarks unchanged: one banner, one navigation aside, one main, one contentinfo
        await expect(page.getByRole('banner')).toHaveCount(1);
        await expect(page.getByRole('main')).toHaveCount(1);
        await expect(page.getByRole('contentinfo')).toHaveCount(1);
        await expect(page.locator('aside#nowo-ui-aside')).toHaveAttribute('aria-label', 'Billing');
      });
    }
    test(`axe clean: phone, ${theme}`, async ({ page }) => {
      await open(page, '?style=inset&layout=full-height', 390, 844);
      await setTheme(page, theme);
      await settle(page);
      expect(await axe(page)).toEqual([]);
    });
  }

  test('focus order is the same in both styles', async ({ page }) => {
    const order = async (query: string): Promise<string[]> => {
      await open(page, query, 1280);
      const seen: string[] = [];
      for (let i = 0; i < 12; i++) {
        await page.keyboard.press('Tab');
        seen.push(await page.evaluate(() => {
          const el = document.activeElement as HTMLElement | null;
          return el ? `${el.tagName}:${(el.getAttribute('aria-label') || el.textContent || '').trim().slice(0, 24)}` : '';
        }));
      }
      return seen;
    };
    const classic = await order('?layout=full-height');
    const inset = await order('?style=inset&layout=full-height');
    // the demo's style switch link names the other style; everything else is the same sequence
    const norm = (list: string[]): string[] => list.map((s) => s.replace(/^A:Switch to the .*$/, 'A:style'));
    expect(norm(inset)).toEqual(norm(classic));
  });
});
