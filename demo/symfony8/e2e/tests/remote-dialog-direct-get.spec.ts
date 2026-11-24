import { test, expect } from '@playwright/test';

/**
 * UiKit 2.73 on /kit/remote-form: remote dialog URLs opened directly (nowo_ui_kit.remote_dialog.direct_get). A
 * top-level navigation to a dialog step lands on the list page with the dialog open on that step and a clean URL; a
 * reopen query naming a route outside the dialog's safe list opens nothing; a link of the page followed without
 * JavaScript keeps the full page (the no-JS fallback).
 */
test.describe('remote dialog content opened directly (2.73)', () => {
  test.use({ reducedMotion: 'reduce' });

  test('the address bar lands on the owning page with the dialog open on that step', async ({ page }) => {
    await page.goto('/kit/remote-form/new');
    const dialog = page.getByTestId('destination-dialog');
    await expect(dialog).toBeVisible();
    await expect(page.getByTestId('destination-label')).toBeVisible();
    await expect(page).toHaveURL(/\/kit\/remote-form$/);

    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(page).toHaveURL(/\/kit\/remote-form$/);
  });

  test('a later step keeps its query and is saved from the reopened dialog', async ({ page }) => {
    await page.goto('/kit/remote-form/wizard/channel?label=Ops');
    await expect(page.getByTestId('destination-dialog')).toBeVisible();
    await expect(page.getByTestId('wizard-step')).toHaveText('Step 2 of 2: Ops');
    await expect(page).toHaveURL(/\/kit\/remote-form$/);

    await page.getByTestId('wizard-next').click();
    await expect(page.getByTestId('flash')).toHaveText(/Destination "Ops \(.+\)" saved\./);
  });

  test('a reopen query outside the safe list opens nothing', async ({ page }) => {
    for (const route of ['kit_dialogs', 'https://evil.example/', 'javascript:alert(1)']) {
      await page.goto(`/kit/remote-form?_dialog[route]=${encodeURIComponent(route)}`);
      await expect(page.getByTestId('remote-form-demo')).toBeVisible();
      await expect(page.getByTestId('destination-dialog')).toBeHidden();
      expect(new URL(page.url()).searchParams.get('_dialog[route]')).toBe(route);
    }
  });

  test('a link of the page followed without JavaScript keeps the full page', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto('/kit/remote-form');
    await page.getByTestId('destination-add').click();
    await expect(page).toHaveURL(/\/kit\/remote-form\/new$/);
    await expect(page.getByTestId('destination-label')).toBeVisible();
    await context.close();
  });
});
