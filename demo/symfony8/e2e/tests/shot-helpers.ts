import type { Page } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * REQ-DEMO-013 screenshot helpers shared by the `*-screenshots.spec.ts` / `screenshots.spec.ts` files (project
 * `screenshots`, `make demo-screenshots`). The Symfony debug toolbar never appears in a committed screenshot (owner
 * rule, demo platform rollout): it is hidden before the first paint of every page the test opens.
 */
export const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/images/demo');

const HIDE_TOOLBAR = '.sf-toolbar,.sf-minitoolbar,[id^="sfwdt"],[id^="sfToolbar"],.sf-toolbar-clearer{display:none!important}';

export function ensureOutDir(): void {
  mkdirSync(outDir, { recursive: true });
}

/** Hide the Symfony debug toolbar on every page this Page opens from now on. */
export async function hideDebugToolbar(page: Page): Promise<void> {
  await page.addInitScript((css) => {
    const add = (): void => {
      const style = document.createElement('style');
      style.setAttribute('data-e2e-hide-toolbar', '');
      style.textContent = css;
      (document.head || document.documentElement).appendChild(style);
    };
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', add);
    } else {
      add();
    }
  }, HIDE_TOOLBAR);
}

export function shotPath(file: string): string {
  return resolve(outDir, `${file}.png`);
}
