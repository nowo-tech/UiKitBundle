/**
 * Every shipped IIFE entry announces itself ("script loaded" console line) once per page, under its own name.
 * The static check covers the whole vite.config.ts entry list (theme-boot is the one inline pre-paint script and
 * stays silent: it is inlined in the <head> of every page); a few entries are evaluated for real.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import viteConfig from '../../../../vite.config.ts?raw';

const sources = import.meta.glob<string>('./nowo-ui-*.ts', { query: '?raw', import: 'default', eager: true });
const entries = (viteConfig.match(/input: 'src\/Resources\/assets\/src\/nowo-ui-[a-z-]+\.ts'/g) ?? []).map((m) => m.replace(/^.*\/(nowo-ui-[a-z-]+)\.ts'$/, '$1'));

describe('shipped entries', () => {
  it('lists the entries of the build', () => {
    expect(entries.length).toBeGreaterThan(30);
  });

  it.each(entries.filter((entry) => entry !== 'nowo-ui-theme-boot'))('%s announces itself once, under its own name', (entry) => {
    const source = sources[`./${entry}.ts`] ?? '';
    const name = `ui-kit/${entry.replace(/^nowo-ui-/, '')}`;

    expect(source.split(`announceScript('${name}'`)).toHaveLength(2);
  });
});

describe.each(['./nowo-ui-open-modal', './nowo-ui-autosubmit', './nowo-ui-layout-state'])('%s', (entry) => {
  beforeEach(() => {
    delete window.__nowoScriptBanners;
    vi.resetModules();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    delete window.__nowoScriptBanners;
  });

  it('logs "script loaded" once with the injected build time, also when evaluated twice', async () => {
    vi.stubGlobal('__UI_KIT_BUILD_TIME__', '2026-11-23 14:05 UTC');
    const log = vi.spyOn(console, 'log').mockImplementation(() => undefined);

    await import(/* @vite-ignore */ entry);
    vi.resetModules();
    await import(/* @vite-ignore */ entry);

    const lines = log.mock.calls.filter((call) => String(call[0]).includes('script loaded'));
    expect(lines).toHaveLength(1);
    expect(String(lines[0]?.[0])).toContain(`[ui-kit/${entry.replace('./nowo-ui-', '')}] script loaded, build: `);
    expect(String(lines[0]?.[0])).toContain('2026-11-23 14:05 UTC');
  });
});
