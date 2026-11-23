/**
 * announceScript(): one "script loaded" line per script name and page, even when the script is evaluated twice.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { announceScript } from './script-banner';

describe('announceScript', () => {
  let log: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    delete window.__nowoScriptBanners;
    log = vi.spyOn(console, 'log').mockImplementation(() => undefined);
  });

  afterEach(() => {
    vi.restoreAllMocks();
    delete window.__nowoScriptBanners;
  });

  it('logs the script name and the build id', () => {
    announceScript('ui-kit/dialog', 'abc123def456');

    expect(log).toHaveBeenCalledTimes(1);
    expect(String(log.mock.calls[0]?.[0])).toContain('[ui-kit/dialog] script loaded, build: ');
    expect(log.mock.calls[0]).toContain('abc123def456');
  });

  it('logs without a build id when none is injected', () => {
    announceScript('ui-kit/dialog');

    expect(String(log.mock.calls[0]?.[0])).toContain('[ui-kit/dialog] script loaded');
    expect(String(log.mock.calls[0]?.[0])).not.toContain('build: ');
  });

  it('logs a script loaded twice only once, and each script name on its own', () => {
    announceScript('ui-kit/dialog');
    announceScript('ui-kit/dialog');
    announceScript('ui-kit/other');

    expect(log).toHaveBeenCalledTimes(2);
    expect(String(log.mock.calls[1]?.[0])).toContain('[ui-kit/other] script loaded');
  });

  it('does nothing without a window', () => {
    vi.stubGlobal('window', undefined);
    try {
      announceScript('ui-kit/dialog');
    } finally {
      vi.unstubAllGlobals();
    }

    expect(log).not.toHaveBeenCalled();
  });
});
