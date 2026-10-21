import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

describe('nowo-ui-error-retry', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    vi.resetModules();
  });

  afterEach(() => {
    document.body.innerHTML = '';
    vi.useRealTimers();
    vi.restoreAllMocks();
  });

  it('binds every countdown once, renders m:ss and reloads its URL at zero', async () => {
    document.body.innerHTML = `
      <p id="a" data-nowo-ui-error-retry data-nowo-ui-error-retry-url="/a" data-nowo-ui-error-retry-seconds="2">Reloads in <span data-nowo-ui-error-retry-remaining>10:00</span></p>
      <p id="b" data-nowo-ui-error-retry data-nowo-ui-error-retry-seconds="x"></p>
    `;
    const assign = vi.fn();
    vi.spyOn(window, 'location', 'get').mockReturnValue({ ...window.location, assign, href: 'http://localhost/b' } as Location);
    const mod = await import('./nowo-ui-error-retry');
    // the module auto-binds on import (document already loaded)
    expect(document.querySelector('#a span')!.textContent).toBe('0:02');
    expect(document.getElementById('a')!.hasAttribute('data-nowo-ui-error-retry-bound')).toBe(true);
    expect(mod.bindNowoUiErrorRetry()).toEqual([]);
    vi.advanceTimersByTime(2000);
    expect(document.querySelector('#a span')!.textContent).toBe('0:00');
    expect(assign).toHaveBeenCalledWith('/a');
  });

  it('accepts a root and a navigate callback; elements without a remaining child still count down', async () => {
    const mod = await import('./nowo-ui-error-retry');
    const root = document.createElement('div');
    root.innerHTML = '<p data-nowo-ui-error-retry data-nowo-ui-error-retry-url="/c" data-nowo-ui-error-retry-seconds="1"></p>';
    const navigate = vi.fn();
    const started = mod.bindNowoUiErrorRetry(root, navigate);
    expect(started).toHaveLength(1);
    vi.advanceTimersByTime(1000);
    expect(navigate).toHaveBeenCalledWith('/c');
  });

  it('waits for DOMContentLoaded while the document is loading', async () => {
    vi.spyOn(document, 'readyState', 'get').mockReturnValue('loading');
    document.body.innerHTML = '<p id="d" data-nowo-ui-error-retry data-nowo-ui-error-retry-seconds="5"><span data-nowo-ui-error-retry-remaining></span></p>';
    await import('./nowo-ui-error-retry');
    expect(document.querySelector('#d span')!.textContent).toBe('');
    document.dispatchEvent(new Event('DOMContentLoaded'));
    expect(document.querySelector('#d span')!.textContent).toBe('0:05');
  });
});
