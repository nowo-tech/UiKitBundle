import { test, expect } from '@playwright/test';

/**
 * UiKit 2.22 (REQ-UI-003): row_actions confirm_mode native without the confirm-submit Stimulus peer. The form has no
 * inline handler and js/nowo-ui-confirm.js warns once in the console. 2.23 (audit CK L1): it fails closed — the kit
 * runtime asks with window.confirm(), a dismissed prompt keeps the form unsent.
 */
test('confirm_mode native without the peer: no inline handler, console warning, window.confirm fallback', async ({ page }) => {
  const warnings: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'warning') {
      warnings.push(message.text());
    }
  });
  const messages: string[] = [];
  let accept = false;
  page.on('dialog', (dialog) => {
    messages.push(dialog.message());
    void (accept ? dialog.accept() : dialog.dismiss());
  });

  await page.goto('/kit/row-actions-confirm');
  const form = page.locator('form.nowo-ui-row-actions__form');
  await expect(form).toHaveAttribute('data-controller', 'confirm-submit');
  await expect(form).toHaveAttribute('data-confirm-submit-message-value', 'Delete invoice #1042?');
  expect(await page.locator('[onsubmit]').count()).toBe(0);

  await form.locator('button[type="submit"]').click();
  await expect.poll(() => messages.length).toBe(1);
  expect(messages[0]).toBe('Delete invoice #1042?');
  await expect(page.getByTestId('row-actions-submitted')).toHaveCount(0);
  expect(warnings.some((text) => text.includes('"confirm-submit" Stimulus peer'))).toBe(true);

  accept = true;
  await form.locator('button[type="submit"]').click();
  await expect(page.getByTestId('row-actions-submitted')).toBeVisible();
  expect(messages).toHaveLength(2);
});
