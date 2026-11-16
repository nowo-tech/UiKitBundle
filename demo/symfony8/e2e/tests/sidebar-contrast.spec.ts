import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * UiKit 2.52.1 (audit RK-M1 / RK-I5 / RK-L3 / RK-L1) on /demo/sidebar-layout, classic and inset, light and dark:
 * - the current aside item's text is 4.5:1 or more on its tint (WCAG 1.4.3; the classic primary was 4.37:1 in light);
 * - the :focus-visible outline of an aside link is 3:1 or more against the aside behind it (WCAG 1.4.11; was ~1.65:1);
 * - axe (wcag2a / 2aa / 21a / 21aa, colour contrast included: serious + critical = 0);
 * with the UiKit default palette (the demo's host `tokens` dropped: they apply in light mode only) and with the demo's
 * host palette. Plus the inset content width with an edge-tabs gutter: the gutter adds to the centring padding.
 */
type Theme = 'light' | 'dark';
type Palette = 'kit defaults' | 'demo host';

const URL = '/demo/sidebar-layout';

async function open(page: Page, query: string, theme: Theme, palette: Palette, width = 1440): Promise<void> {
  await page.setViewportSize({ width, height: 900 });
  await page.goto(`${URL}${query}`);
  await expect(page.locator('[data-nowo-ui-shell]')).toBeVisible();
  await page.evaluate(
    ([t, p]) => {
      const root = document.documentElement;
      if (p === 'kit defaults') {
        // the host theme tokens are one inline <style> printed by nowo_ui_tokens() plus the root attribute
        document.querySelectorAll('head style').forEach((el) => {
          if (/--nowo-ui-primary\s*:/.test(el.textContent ?? '')) {
            el.remove();
          }
        });
        root.removeAttribute('data-nowo-ui-host-tokens');
      }
      root.setAttribute('data-theme', t);
      root.setAttribute('data-bs-theme', t);
    },
    [theme, palette] as const,
  );
  await page.waitForTimeout(450);
}

/** Contrast of the element's text (or outline) against the opaque colour behind it (alpha backgrounds composited). */
async function ratio(page: Page, selector: string, what: 'color' | 'outline'): Promise<number> {
  return page.locator(selector).first().evaluate((el, prop) => {
    const probe = document.createElement('canvas').getContext('2d', { willReadFrequently: true }) as CanvasRenderingContext2D;
    const rgba = (css: string): number[] => {
      probe.clearRect(0, 0, 1, 1);
      probe.fillStyle = '#000';
      probe.fillStyle = css;
      probe.fillRect(0, 0, 1, 1);
      const [r, g, b, a] = probe.getImageData(0, 0, 1, 1).data;
      return [r, g, b, a / 255];
    };
    const over = (top: number[], below: number[]): number[] => [0, 1, 2].map((i) => top[i] * top[3] + below[i] * (1 - top[3])).concat(1);
    const background = (start: Element | null): number[] => {
      const layers: number[][] = [];
      for (let node = start; node; node = node.parentElement) {
        const color = rgba(getComputedStyle(node).backgroundColor);
        if (color[3] > 0) {
          layers.push(color);
          if (color[3] >= 1) {
            break;
          }
        }
      }
      return layers.reduceRight((below, top) => over(top, below), [255, 255, 255, 1]);
    };
    const luminance = (c: number[]): number => {
      const [r, g, b] = c.slice(0, 3).map((v) => {
        const s = v / 255;
        return s <= 0.04045 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
      });
      return 0.2126 * r + 0.7152 * g + 0.0722 * b;
    };
    // text sits on the element's own background; the outline (offset outside the box) on the parent's
    const bg = background(prop === 'color' ? el : el.parentElement);
    const style = getComputedStyle(el);
    const fg = over(rgba(prop === 'color' ? style.color : style.outlineColor), bg);
    const [hi, lo] = [luminance(fg), luminance(bg)].sort((a, b) => b - a);
    return Math.round(((hi + 0.05) / (lo + 0.05)) * 100) / 100;
  }, what);
}

async function focusAsideLink(page: Page): Promise<void> {
  for (let i = 0; i < 40; i++) {
    await page.keyboard.press('Tab');
    const done = await page.evaluate(() => {
      const el = document.activeElement;
      return !!el && el.matches('.nowo-ui-aside__link:not(.is-current)') && el.matches(':focus-visible');
    });
    if (done) {
      return;
    }
  }
  throw new Error('no aside link took keyboard focus');
}

async function axe(page: Page): Promise<string[]> {
  const result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
  return result.violations
    .filter((v) => v.impact === 'serious' || v.impact === 'critical')
    .map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`);
}

test.describe('sidebar contrast (2.52.1)', () => {
  for (const palette of ['kit defaults', 'demo host'] as Palette[]) {
    for (const style of ['classic', 'inset'] as const) {
      for (const theme of ['light', 'dark'] as Theme[]) {
        test(`${style}, ${theme}, ${palette}: current item 4.5:1, focus outline 3:1, axe clean`, async ({ page }) => {
          await open(page, `?layout=full-height${style === 'inset' ? '&style=inset' : ''}`, theme, palette);
          const current = await ratio(page, '.nowo-ui-aside__link.is-current', 'color');
          await focusAsideLink(page);
          const ring = await ratio(page, '.nowo-ui-aside__link:focus-visible', 'outline');
          test.info().annotations.push({ type: 'contrast', description: `${style} ${theme} ${palette}: current ${current}:1, focus ${ring}:1` });
          console.log(`[contrast] ${style} ${theme} ${palette}: current ${current}:1, focus outline ${ring}:1`);
          expect(current).toBeGreaterThanOrEqual(4.5);
          expect(ring).toBeGreaterThanOrEqual(3);
          await page.keyboard.press('Escape');
          expect(await axe(page)).toEqual([]);
        });
      }
    }
  }

  test('inset content width with an edge-tabs gutter: the gutter adds to the centring padding', async ({ page }) => {
    await open(page, '?layout=full-height&style=inset&width=content', 'light', 'demo host', 1600);
    const before = await page.evaluate(() => {
      const s = getComputedStyle(document.querySelector('.nowo-ui-main')!);
      return { start: parseFloat(s.paddingInlineStart), end: parseFloat(s.paddingInlineEnd) };
    });
    expect(Math.abs(before.start - before.end)).toBeLessThanOrEqual(1);
    expect(before.start).toBeGreaterThan(40); // centred: far more than the 1.25rem base padding at 1600 px

    // what the edge-tabs controller sets up for `when: always` with a gutter
    await page.evaluate(() => {
      const launcher = document.createElement('div');
      launcher.className = 'nowo-ui-edge-tabs-launcher';
      launcher.setAttribute('data-nowo-ui-edge-tabs-when', 'always');
      launcher.setAttribute('data-nowo-ui-edge-tabs-gutter', '');
      document.body.append(launcher);
      document.documentElement.setAttribute('data-nowo-ui-edge-tabs-ready', '');
    });
    await page.waitForTimeout(450);
    const after = await page.evaluate(() => {
      const s = getComputedStyle(document.querySelector('.nowo-ui-main')!);
      return { start: parseFloat(s.paddingInlineStart), end: parseFloat(s.paddingInlineEnd) };
    });
    expect(Math.abs(after.start - before.start)).toBeLessThanOrEqual(1);
    expect(Math.abs(after.end - before.end - 36)).toBeLessThanOrEqual(1); // + --nowo-ui-edge-tab-width (2.25rem)
  });
});
