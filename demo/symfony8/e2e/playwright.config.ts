import { defineConfig, devices } from '@playwright/test';
import { readFileSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';

/** Resolve demo PORT from sibling .env / .env.example (demo/symfony8). */
function resolvePort(fallback = 8000): number {
  for (const file of ['.env', '.env.example']) {
    const path = resolve(__dirname, '..', file);
    if (!existsSync(path)) {
      continue;
    }
    const m = readFileSync(path, 'utf8').match(/^PORT=(\d+)/m);
    if (m) {
      return Number(m[1]);
    }
  }
  return fallback;
}

const port = Number(process.env.DEMO_PORT || resolvePort());
const baseURL = process.env.DEMO_BASE_URL || `http://127.0.0.1:${port}`;

export default defineConfig({
  testDir: './tests',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: [['list']],
  use: {
    baseURL,
    trace: 'on-first-retry',
    viewport: { width: 1280, height: 720 },
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
      testMatch: /.*\.spec\.ts/,
      testIgnore: /screenshots\.spec\.ts|sortable-touch\.spec\.ts|mobile-mux\.spec\.ts/,
    },
    // UiKit sortable: the other engines, and touch input in Chromium (make test-e2e-sortable).
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
      testMatch: /(sortable|emoji-picker|show-more|dialog-back|motion|table-cards)\.spec\.ts/,
    },
    {
      name: 'webkit',
      use: { ...devices['Desktop Safari'] },
      testMatch: /(sortable|emoji-picker|show-more|dialog-back|motion|table-cards|image-crop)\.spec\.ts/,
    },
    {
      name: 'chromium-touch',
      use: { ...devices['Pixel 7'] },
      testMatch: /sortable-touch\.spec\.ts/,
    },
    // 2.41 phone mode (Beacon mobile UX audit): 390 × 844, touch, Chromium and WebKit (make test-e2e-mobile).
    // 2.43: the image crop's touch tests too (make test-e2e-image-crop).
    {
      name: 'phone-chromium',
      use: { ...devices['Pixel 7'], viewport: { width: 390, height: 844 } },
      testMatch: /(mobile-mux|image-crop)\.spec\.ts/,
    },
    {
      name: 'phone-webkit',
      use: { ...devices['iPhone 14'], viewport: { width: 390, height: 844 } },
      testMatch: /(mobile-mux|image-crop)\.spec\.ts/,
    },
    // Fetched HTML under a CSP: WebKit let more through than Chromium, so it runs in both.
    {
      name: 'imported-style-webkit',
      use: { ...devices['Desktop Safari'] },
      testMatch: /imported-style\.spec\.ts/,
    },
    {
      name: 'screenshots',
      use: { ...devices['Desktop Chrome'] },
      testMatch: /screenshots\.spec\.ts/,
    },
  ],
});
