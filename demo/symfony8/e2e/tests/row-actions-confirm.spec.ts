import { test, expect } from '@playwright/test';

/**
 * UiKit 2.22 (REQ-UI-003): row_actions confirm_mode native without the confirm-submit Stimulus peer. The form has no
 * inline handler, submits unconfirmed, and js/nowo-ui-confirm.js warns once in the console.
 */
test('confirm_mode native without the peer: no inline handler, console warning, unconfirmed submit', async ({ page }) => {
  const warnings: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'warning') {
      warnings.push(message.text());
    }
  });
  let dialogs = 0;
  page.on('dialog', (dialog) => {
    dialogs += 1;
    void dialog.dismiss();
  });

  await page.goto('/kit/row-actions-confirm');
  const form = page.locator('form.nowo-ui-row-actions__form');
  await expect(form).toHaveAttribute('data-controller', 'confirm-submit');
  await expect(form).toHaveAttribute('data-confirm-submit-message-value', 'Delete invoice #1042?');
  expect(await page.locator('[onsubmit]').count()).toBe(0);

  await form.locator('button[type="submit"]').click();
  await expect(page.getByTestId('row-actions-submitted')).toBeVisible();
  expect(dialogs).toBe(0);
  expect(warnings.some((text) => text.includes('"confirm-submit" Stimulus peer'))).toBe(true);
});
