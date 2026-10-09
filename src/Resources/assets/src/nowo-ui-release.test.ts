import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as core from './nowo-ui-release-core';
import * as mod from './nowo-ui-release';

const MESSAGES = {
  update_available: 'Update: %version%',
  versions_behind: '%count% behind',
  upgrade_hint: 'Upgrade hint',
  up_to_date: 'Up to date',
  latest_label: 'Latest: %version%',
  check_skipped: 'Skipped',
  check_unavailable: 'Unavailable',
  checked_at: 'Checked %when%',
};

function markup(url = '/status', extra = ''): string {
  return `
    <dialog id="rel" data-nowo-ui-release data-nowo-ui-release-url="${url}"
            data-nowo-ui-release-messages='${JSON.stringify(MESSAGES)}' ${extra}>
      <p data-nowo-ui-release-part="loading" hidden></p>
      <div data-nowo-ui-release-part="content" hidden>
        <p data-nowo-ui-release-part="summary"></p>
        <p data-nowo-ui-release-part="detail" hidden></p>
        <p data-nowo-ui-release-part="meta" hidden></p>
      </div>
      <p data-nowo-ui-release-part="error" hidden></p>
      <a data-nowo-ui-release-part="compare" href="#" hidden></a>
      <a data-nowo-ui-release-part="release" href="#" hidden></a>
    </dialog>`;
}

function payload(over: Partial<core.ReleaseStatusPayload> = {}): core.ReleaseStatusPayload {
  return {
    status: 'ok',
    current: 'v1.0.0',
    latest: 'v1.2.0',
    updateAvailable: true,
    versionsBehind: 2,
    releaseUrl: 'https://github.com/acme/app/releases/tag/v1.2.0',
    compareUrl: 'https://github.com/acme/app/compare/v1.0.0...v1.2.0',
    checkedAt: '2026-10-09T08:30:00+00:00',
    ...over,
  };
}

function part(name: string): HTMLElement {
  return document.querySelector(`[data-nowo-ui-release-part="${name}"]`) as HTMLElement;
}

function mockFetch(impl: () => Promise<unknown>): ReturnType<typeof vi.fn> {
  const fn = vi.fn(impl);
  vi.stubGlobal('fetch', fn);
  return fn;
}

describe('nowo-ui-release-core', () => {
  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('interpolates placeholders and formats ATOM dates', () => {
    expect(core.interpolateReleaseMessage('%a% and %a% / %b%', { a: 'x', b: 'y' })).toBe('x and x / y');
    expect(core.interpolateReleaseMessage('plain')).toBe('plain');
    expect(core.formatReleaseCheckedAt('2026-10-09T08:30:00+00:00')).toBe('2026-10-09 08:30 UTC');
    expect(core.formatReleaseCheckedAt('nope')).toBe('nope');
  });

  it('parses messages defensively', () => {
    expect(core.parseReleaseMessages(null)).toEqual({});
    expect(core.parseReleaseMessages('{bad')).toEqual({});
    expect(core.parseReleaseMessages('[1]')).toEqual({});
    expect(core.parseReleaseMessages('null')).toEqual({});
    expect(core.parseReleaseMessages('{"a":"b","n":1}')).toEqual({ a: 'b' });
    expect(core.translateRelease({}, 'missing_key')).toBe('missing_key');
  });

  it('renders update available with versions behind and links', () => {
    document.body.innerHTML = markup();
    const parts = core.resolveReleaseParts(document);
    core.renderReleaseStatus(parts, payload(), MESSAGES);
    expect(part('content').hidden).toBe(false);
    expect(part('summary').textContent).toBe('Update: v1.2.0');
    expect(part('detail').textContent).toBe('2 behind');
    expect(part('meta').textContent).toBe('Checked 2026-10-09 08:30 UTC');
    expect((part('compare') as HTMLAnchorElement).href).toContain('/compare/');
    expect(part('compare').getAttribute('data-nowo-ui-release-update')).toBe('true');
    expect(part('release').hidden).toBe(false);
  });

  it('renders upgrade hint when versions behind is unknown', () => {
    document.body.innerHTML = markup();
    core.renderReleaseStatus(core.resolveReleaseParts(document), payload({ versionsBehind: null }), MESSAGES);
    expect(part('detail').textContent).toBe('Upgrade hint');
  });

  it('renders up to date, skipped and error states', () => {
    document.body.innerHTML = markup();
    const parts = core.resolveReleaseParts(document);
    core.renderReleaseStatus(parts, payload({ updateAvailable: false, versionsBehind: 0, latest: 'v1.0.0' }), MESSAGES);
    expect(part('summary').textContent).toBe('Up to date');
    expect(part('detail').textContent).toBe('Latest: v1.0.0');
    expect(part('release').hidden).toBe(true);
    expect(part('compare').getAttribute('data-nowo-ui-release-update')).toBe('false');

    core.renderReleaseStatus(parts, payload({ updateAvailable: false, latest: null, checkedAt: null }), MESSAGES);
    expect(part('detail').hidden).toBe(true);
    expect(part('meta').hidden).toBe(true);

    core.renderReleaseStatus(parts, payload({ status: 'skipped', updateAvailable: false, compareUrl: null }), MESSAGES);
    expect(part('summary').textContent).toBe('Skipped');
    expect(part('compare').hidden).toBe(true);

    core.renderReleaseStatus(parts, payload({ status: 'error', updateAvailable: false, latest: null }), MESSAGES);
    expect(part('summary').textContent).toBe('Unavailable');
  });

  it('never renders non-github links', () => {
    document.body.innerHTML = markup();
    core.renderReleaseStatus(
      core.resolveReleaseParts(document),
      payload({ releaseUrl: 'javascript:alert(1)', compareUrl: 'https://evil.test/x' }),
      MESSAGES,
    );
    expect(part('compare').hidden).toBe(true);
    expect(part('release').hidden).toBe(true);
  });

  it('tolerates missing parts and honours overrides', () => {
    document.body.innerHTML = '<div id="x"><p id="o"></p></div>';
    const override = document.getElementById('o') as HTMLElement;
    const parts = core.resolveReleaseParts(document.getElementById('x')!, { summary: override });
    expect(parts.summary).toBe(override);
    expect(parts.loading).toBeNull();
    core.showReleaseIdle(parts);
    core.showReleaseLoading(parts);
    core.showReleaseError(parts, MESSAGES);
    core.renderReleaseStatus(parts, payload(), MESSAGES);
    expect(override.textContent).toBe('Update: v1.2.0');
  });

  it('fetchReleaseStatus throws on HTTP errors', async () => {
    mockFetch(async () => ({ ok: false, status: 500 }));
    await expect(core.fetchReleaseStatus('/s')).rejects.toThrow('HTTP 500');
    vi.unstubAllGlobals();
  });
});

describe('nowo-ui-release IIFE', () => {
  beforeEach(() => {
    mod.bindNowoUiRelease();
  });

  afterEach(() => {
    document.body.innerHTML = '';
    vi.unstubAllGlobals();
  });

  it('exposes window helper and is idempotent', () => {
    mod.bindNowoUiRelease();
    expect(window.nowoUiLoadReleaseStatus).toBe(mod.loadReleaseStatus);
  });

  it('loads once on toggle open and renders', async () => {
    const fetchFn = mockFetch(async () => ({ ok: true, json: async () => payload() }));
    document.body.innerHTML = markup();
    const dialog = document.getElementById('rel') as HTMLDialogElement;
    dialog.setAttribute('open', '');
    dialog.dispatchEvent(new Event('toggle'));
    await mod.loadReleaseStatus(dialog);
    expect(fetchFn).toHaveBeenCalledTimes(1);
    expect(fetchFn.mock.calls[0][0]).toBe('/status');
    expect(dialog.getAttribute('data-nowo-ui-release-state')).toBe('loaded');
    expect(part('summary').textContent).toBe('Update: v1.2.0');

    await mod.loadReleaseStatus(dialog);
    expect(fetchFn).toHaveBeenCalledTimes(1);
  });

  it('ignores closed dialogs, foreign dialogs and Stimulus-owned dialogs', () => {
    const fetchFn = mockFetch(async () => ({ ok: true, json: async () => payload() }));
    document.body.innerHTML = markup('/status', 'data-controller="release-status"') + '<dialog id="other" open></dialog>';
    const dialog = document.getElementById('rel') as HTMLDialogElement;
    dialog.dispatchEvent(new Event('toggle'));
    dialog.setAttribute('open', '');
    dialog.dispatchEvent(new Event('toggle'));
    document.getElementById('other')!.dispatchEvent(new Event('toggle'));
    document.body.dispatchEvent(new Event('toggle'));
    expect(fetchFn).not.toHaveBeenCalled();
  });

  it('shows error on fetch failure and when url is missing', async () => {
    mockFetch(async () => {
      throw new Error('offline');
    });
    document.body.innerHTML = markup();
    const dialog = document.getElementById('rel') as HTMLDialogElement;
    await mod.loadReleaseStatus(dialog);
    expect(dialog.getAttribute('data-nowo-ui-release-state')).toBe('error');
    expect(part('error').hidden).toBe(false);
    expect(part('error').textContent).toBe('Unavailable');

    document.body.innerHTML = markup('');
    await mod.loadReleaseStatus(document.getElementById('rel'));
    expect(part('error').textContent).toBe('Unavailable');
  });

  it('dedupes concurrent loads and ignores non-release elements', async () => {
    let resolve: (v: unknown) => void = () => {};
    const fetchFn = mockFetch(() => new Promise((r) => (resolve = r)));
    document.body.innerHTML = markup();
    const dialog = document.getElementById('rel') as HTMLDialogElement;
    const a = mod.loadReleaseStatus(dialog);
    const b = mod.loadReleaseStatus(dialog);
    expect(a).toBe(b);
    expect(part('loading').hidden).toBe(false);
    resolve({ ok: true, json: async () => payload() });
    await a;
    expect(fetchFn).toHaveBeenCalledTimes(1);
    await mod.loadReleaseStatus(null);
    await mod.loadReleaseStatus(document.createElement('div'));
  });
});
