import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as mod from './nowo-ui-clipboard';

function mockClipboard(writeText: ReturnType<typeof vi.fn>): void {
  Object.defineProperty(navigator, 'clipboard', {
    configurable: true,
    value: { writeText },
  });
}

describe('nowo-ui-clipboard', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    mockClipboard(vi.fn().mockResolvedValue(undefined));
    document.body.innerHTML = `
      <button
        type="button"
        data-nowo-ui-clipboard
        data-nowo-ui-clipboard-text="hello"
        data-nowo-ui-clipboard-label="Copy"
        data-nowo-ui-clipboard-done-label="Copied"
      >Copy</button>
    `;
    mod.bindNowoUiClipboard();
  });

  afterEach(() => {
    vi.useRealTimers();
    document.body.innerHTML = '';
    vi.restoreAllMocks();
  });

  it('copies text on click and flashes done label', async () => {
    const btn = document.querySelector('button')!;
    btn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    await Promise.resolve();
    expect(navigator.clipboard.writeText).toHaveBeenCalledWith('hello');
    expect(btn.textContent).toBe('Copied');
    vi.advanceTimersByTime(1600);
    expect(btn.textContent).toBe('Copy');
  });

  it('copyText helper returns false for empty and exposes window API', async () => {
    expect(await mod.copyText('')).toBe(false);
    expect(window.nowoUiCopyText).toBe(mod.copyText);
    expect(await mod.copyText('x')).toBe(true);
  });

  it('bindNowoUiClipboard is idempotent', () => {
    mod.bindNowoUiClipboard();
    mod.bindNowoUiClipboard();
    expect(window.nowoUiCopyText).toBe(mod.copyText);
  });

  it('copies from URL when text is absent', async () => {
    document.body.innerHTML = `
      <button
        type="button"
        data-nowo-ui-clipboard
        data-nowo-ui-clipboard-url="/copy.md"
        data-nowo-ui-clipboard-label="Copy"
        data-nowo-ui-clipboard-done-label="Copied"
      >Copy</button>
    `;
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        text: async () => 'from-url',
      }),
    );
    mod.bindNowoUiClipboard();
    const btn = document.querySelector('button')!;
    btn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    await Promise.resolve();
    await Promise.resolve();
    expect(fetch).toHaveBeenCalled();
    expect(navigator.clipboard.writeText).toHaveBeenCalledWith('from-url');
  });

  it('falls back when clipboard API throws', async () => {
    mockClipboard(vi.fn().mockRejectedValue(new Error('denied')));
    Object.defineProperty(document, 'execCommand', {
      configurable: true,
      writable: true,
      value: vi.fn().mockReturnValue(true),
    });
    expect(await mod.copyText('fallback')).toBe(true);
    expect(document.execCommand).toHaveBeenCalledWith('copy');
  });

  it('ignores clicks outside clipboard hosts', () => {
    document.body.innerHTML = `<button type="button">Other</button>`;
    mod.bindNowoUiClipboard();
    document.querySelector('button')!.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect(navigator.clipboard.writeText).not.toHaveBeenCalled();
  });

  it('uses execCommand when clipboard API is missing', async () => {
    Object.defineProperty(navigator, 'clipboard', {
      configurable: true,
      value: undefined,
    });
    Object.defineProperty(document, 'execCommand', {
      configurable: true,
      writable: true,
      value: vi.fn().mockReturnValue(true),
    });
    expect(await mod.copyText('plain', document.querySelector('button'))).toBe(true);
    expect(document.execCommand).toHaveBeenCalledWith('copy');
  });

  it('returns false when clipboard and fallback both fail', async () => {
    mockClipboard(vi.fn().mockRejectedValue(new Error('denied')));
    Object.defineProperty(document, 'execCommand', {
      configurable: true,
      writable: true,
      value: vi.fn(() => {
        throw new Error('exec failed');
      }),
    });
    expect(await mod.copyText('x')).toBe(false);
  });

  it('skips failed or empty URL fetches', async () => {
    document.body.innerHTML = `
      <button type="button" data-nowo-ui-clipboard data-nowo-ui-clipboard-url="/x">Copy</button>
    `;
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, text: async () => '' }));
    mod.bindNowoUiClipboard();
    document.querySelector('button')!.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    await Promise.resolve();
    await Promise.resolve();
    expect(navigator.clipboard.writeText).not.toHaveBeenCalled();

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({ ok: true, text: async () => '   ' }),
    );
    document.querySelector('button')!.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    await Promise.resolve();
    await Promise.resolve();
    expect(navigator.clipboard.writeText).not.toHaveBeenCalled();

    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('net')));
    document.querySelector('button')!.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    await Promise.resolve();
    await Promise.resolve();
    expect(navigator.clipboard.writeText).not.toHaveBeenCalled();
  });

  it('flashes nested copy trigger and clears prior timer', async () => {
    document.body.innerHTML = `
      <div data-nowo-ui-clipboard data-nowo-ui-clipboard-text="nested">
        <button type="button" data-nowo-ui-clipboard-copy data-nowo-ui-clipboard-label="Copy" data-nowo-ui-clipboard-done-label="Copied">Copy</button>
      </div>
    `;
    const btn = document.querySelector('button')!;
    expect(await mod.copyText('nested', btn)).toBe(true);
    expect(btn.textContent).toBe('Copied');
    expect(await mod.copyText('nested', btn)).toBe(true);
    expect(btn.textContent).toBe('Copied');
    vi.advanceTimersByTime(1600);
    expect(btn.textContent).toBe('Copy');

    btn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    await vi.waitFor(() => {
      expect(navigator.clipboard.writeText).toHaveBeenCalledWith('nested');
    });
  });

  it('ignores empty text and empty url hosts', () => {
    document.body.innerHTML = `<button type="button" data-nowo-ui-clipboard>Copy</button>`;
    mod.bindNowoUiClipboard();
    document.querySelector('button')!.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect(navigator.clipboard.writeText).not.toHaveBeenCalled();
  });
});
