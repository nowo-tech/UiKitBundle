import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * Card intro contrast (`.nowo-ui-card__intro`, 0.875rem muted text under a card title): WCAG AA 4.5:1 with the
 * default palette in light and dark mode, on the bootstrap5 stack with Bootstrap in the `base` layer (this demo) and
 * with Bootstrap loaded unlayered (the 1.x load order of most host demos), where Bootstrap's own
 * `.card { --bs-card-cap-bg: rgba(var(--bs-body-color-rgb), .03) }` paints the card header and plain muted text
 * measured 4.48:1. Page: the settings scaffold (one card with an intro per section).
 */

const SETTINGS = '/page-contract/default/settings/notifications';
const BOOTSTRAP_CSS = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';

/** Dark sets data-theme and data-bs-theme together, like the UiKit theme boot / toggle (unless data-nowo-ui-bs-theme="off"). */
type Mode = 'light' | 'dark';

/** Contrast of every card intro against the opaque colour behind it (alpha backgrounds composited up the tree). */
const ratiosOf = (page: Page, selector: string): Promise<number[]> =>
  page.locator(selector).evaluateAll((nodes) => {
    const probe = document.createElement('canvas').getContext('2d', { willReadFrequently: true }) as CanvasRenderingContext2D;
    // Any CSS colour (rgb(), color(srgb …) from color-mix) → [r, g, b, a] through a 1px canvas.
    const rgba = (css: string): number[] => {
      probe.clearRect(0, 0, 1, 1);
      probe.fillStyle = '#000';
      probe.fillStyle = css;
      probe.fillRect(0, 0, 1, 1);
      const [r, g, b, a] = probe.getImageData(0, 0, 1, 1).data;
      return [r, g, b, a / 255];
    };
    const over = (top: number[], below: number[]): number[] => [0, 1, 2].map((i) => top[i] * top[3] + below[i] * (1 - top[3])).concat(1);
    const background = (el: Element | null): number[] => {
      const layers: number[][] = [];
      for (let node = el; node; node = node.parentElement) {
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
    return nodes.map((el) => {
      const bg = background(el);
      const fg = over(rgba(getComputedStyle(el).color), bg);
      const [hi, lo] = [luminance(fg), luminance(bg)].sort((a, b) => b - a);
      return (hi + 0.05) / (lo + 0.05);
    });
  });

const open = async (page: Page, unlayeredBootstrap: boolean, mode: Mode): Promise<void> => {
  await page.goto(SETTINGS);
  if (unlayeredBootstrap) {
    await page.addStyleTag({ url: BOOTSTRAP_CSS });
  }
  await page.evaluate((m) => {
    document.documentElement.setAttribute('data-theme', m);
    document.documentElement.setAttribute('data-bs-theme', m);
  }, mode);
  await expect(page.locator('.nowo-ui-card__intro').first()).toBeVisible();
};

for (const unlayeredBootstrap of [false, true]) {
  for (const mode of ['light', 'dark'] as Mode[]) {
    test(`card intro keeps 4.5:1 (${unlayeredBootstrap ? 'unlayered' : 'layered'} Bootstrap, ${mode})`, async ({ page }) => {
      await open(page, unlayeredBootstrap, mode);
      const ratios = await ratiosOf(page, '.nowo-ui-card__intro');
      expect(ratios.length).toBeGreaterThan(0);
      for (const ratio of ratios) {
        expect(ratio).toBeGreaterThanOrEqual(4.5);
      }
      const axe = await new AxeBuilder({ page }).include('.nowo-ui-card__intro').withRules(['color-contrast']).analyze();
      expect(axe.violations).toEqual([]);
      expect(axe.passes.map((rule) => rule.id)).toContain('color-contrast');
    });
  }
}

/** 2.24: the tree badge on the tinted current row (and on a plain row) keeps 4.5:1 in light and dark. */
for (const mode of ['light', 'dark'] as Mode[]) {
  test(`tree badge keeps 4.5:1 on the current row (${mode})`, async ({ page }) => {
    await page.goto('/');
    await page.evaluate((m) => {
      document.documentElement.setAttribute('data-theme', m);
      document.documentElement.setAttribute('data-bs-theme', m);
    }, mode);
    await expect(page.locator('#demo-tree .is-current .nowo-ui-tree__badge')).toBeVisible();
    const ratios = await ratiosOf(page, '#demo-tree .nowo-ui-tree__badge');
    expect(ratios.length).toBe(2);
    for (const ratio of ratios) {
      expect(ratio).toBeGreaterThanOrEqual(4.5);
    }
    const axe = await new AxeBuilder({ page }).include('#demo-tree').withTags(['wcag2a', 'wcag2aa']).analyze();
    expect(axe.violations.map((v) => v.id)).toEqual([]);
  });
}
