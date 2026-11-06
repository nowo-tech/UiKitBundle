import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ClipboardCopyController from './clipboard_copy_controller';

describe('clipboard-copy controller', () => {
  let application: Application;

  const start = async (inner: string): Promise<HTMLButtonElement> => {
    document.body.innerHTML = `<button type="button" data-controller="clipboard-copy" data-clipboard-copy-text-value="x"
      data-clipboard-copy-label-value="Copy" data-clipboard-copy-done-label-value="Copied" data-action="clipboard-copy#copy">${inner}</button>`;
    application = Application.start();
    application.register('clipboard-copy', ClipboardCopyController);
    await Promise.resolve();
    return document.querySelector('button') as HTMLButtonElement;
  };

  const click = async (button: HTMLButtonElement): Promise<void> => {
    button.click();
    await vi.advanceTimersByTimeAsync(0);
  };

  beforeEach(() => {
    vi.useFakeTimers();
    Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText: vi.fn().mockResolvedValue(undefined) } });
  });

  afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
    vi.useRealTimers();
  });

  it('keeps the icon and updates the label node', async () => {
    const button = await start('<i class="ti ti-copy"></i><span class="nowo-ui-action__label">Copy</span>');
    await click(button);
    expect(button.querySelector('i.ti-copy')).not.toBeNull();
    expect(button.querySelector('.nowo-ui-action__label')?.textContent).toBe('Copied');
    expect(button.getAttribute('aria-label')).toBe('Copied');
    vi.advanceTimersByTime(1600);
    expect(button.querySelector('i.ti-copy')).not.toBeNull();
    expect(button.querySelector('.nowo-ui-action__label')?.textContent).toBe('Copy');
    expect(button.getAttribute('aria-label')).toBe('Copy');
  });

  it('keeps the output of a text-only button', async () => {
    const button = await start('Copy');
    await click(button);
    expect(button.innerHTML).toBe('Copied');
    vi.advanceTimersByTime(1600);
    expect(button.innerHTML).toBe('Copy');
  });
});
