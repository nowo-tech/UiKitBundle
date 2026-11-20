import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as mod from './nowo-ui-clipboard';

/** 2.61: the `code_block` partial copies the text of its <code> (data-nowo-ui-clipboard-target) and reveals its button. */
describe('nowo-ui-clipboard target + reveal', () => {
  let writeText: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    vi.useFakeTimers();
    writeText = vi.fn().mockResolvedValue(undefined);
    Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText } });
    document.body.innerHTML = `
      <div class="nowo-ui-code-panel">
        <button type="button" id="copy" data-nowo-ui-clipboard data-nowo-ui-clipboard-target="code-1" data-nowo-ui-clipboard-label="Copy code" data-nowo-ui-clipboard-done-label="Copied" aria-label="Copy code" data-nowo-ui-clipboard-reveal hidden>Copy code</button>
        <pre><code id="code-1">nowo_ui_kit:
  css_framework: custom</code></pre>
      </div>
      <button type="button" id="ghost" data-nowo-ui-clipboard data-nowo-ui-clipboard-target="missing">x</button>`;
    mod.bindNowoUiClipboard();
  });

  afterEach(() => {
    vi.useRealTimers();
    document.body.innerHTML = '';
  });

  it('reveals the copy controls', () => {
    expect((document.getElementById('copy') as HTMLButtonElement).hidden).toBe(false);
  });

  it('copies the target element text and flashes the done label', async () => {
    const button = document.getElementById('copy')!;
    button.click();
    await Promise.resolve();
    expect(writeText).toHaveBeenCalledWith('nowo_ui_kit:\n  css_framework: custom');
    expect(button.getAttribute('aria-label')).toBe('Copied');
    vi.advanceTimersByTime(1600);
    expect(button.getAttribute('aria-label')).toBe('Copy code');
  });

  it('copies nothing when the target is missing', async () => {
    document.getElementById('ghost')!.click();
    await Promise.resolve();
    expect(writeText).not.toHaveBeenCalled();
  });

  it('XJ-4: keeps the leading / trailing whitespace of the target text', async () => {
    document.getElementById('code-1')!.textContent = '  indented:\n    yes\n';
    document.getElementById('copy')!.click();
    await Promise.resolve();
    expect(writeText).toHaveBeenCalledWith('  indented:\n    yes\n');
  });

  it('XJ-8: a refused execCommand copy counts as a failure', async () => {
    Object.defineProperty(navigator, 'clipboard', { configurable: true, value: undefined });
    document.execCommand = () => false;
    expect(await mod.copyText('x', null)).toBe(false);
    document.execCommand = () => true;
    expect(await mod.copyText('x', null)).toBe(true);
  });
});
