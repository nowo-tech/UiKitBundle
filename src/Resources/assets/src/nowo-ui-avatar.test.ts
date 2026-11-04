import { afterEach, describe, expect, it } from 'vitest';
import * as mod from './nowo-ui-avatar';
import cases from '../../../../tests/Fixtures/avatar/url-policy-cases.json';

const avatar = (src: string, extra = ''): string =>
  `<span class="nowo-ui-avatar has-image" aria-hidden="true" data-nowo-ui-avatar ${extra}>AL<img class="nowo-ui-avatar__image" src="${src}" alt="" width="32" height="32" loading="lazy" decoding="async" referrerpolicy="same-origin"></span>`;

const flush = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));

const finished = (img: HTMLImageElement, naturalWidth: number): void => {
  Object.defineProperty(img, 'complete', { value: true, configurable: true });
  Object.defineProperty(img, 'naturalWidth', { value: naturalWidth, configurable: true });
};

describe('nowo-ui-avatar', () => {
  afterEach(() => {
    document.body.innerHTML = '';
    mod.resetFailedAvatars();
  });

  it('applies the same URL policy as PHP (shared case table)', () => {
    for (const [name, url] of Object.entries(cases.accepted)) {
      expect(mod.isSafeAvatarUrl(url), name).toBe(true);
    }
    for (const [name, url] of Object.entries(cases.rejected)) {
      expect(mod.isSafeAvatarUrl(url), name).toBe(false);
    }
    expect(mod.isSafeAvatarUrl(null)).toBe(false);
    expect(mod.isSafeAvatarUrl(undefined)).toBe(false);
  });

  it('marks the wrapper loaded on load (capture listener on the document)', () => {
    mod.bindNowoUiAvatar();
    document.body.innerHTML = avatar('/files/1/s64');
    const img = document.querySelector('img') as HTMLImageElement;
    img.dispatchEvent(new Event('load'));
    expect(img.parentElement?.className).toBe('nowo-ui-avatar has-image is-loaded');
  });

  it('falls back to the initials on error and never fetches a failed URL again', async () => {
    mod.bindNowoUiAvatar();
    mod.bindNowoUiAvatar();
    document.body.innerHTML = avatar('/files/broken/s64');
    const wrapper = document.querySelector('span') as HTMLElement;
    wrapper.classList.add('is-loaded');
    (wrapper.querySelector('img') as HTMLImageElement).dispatchEvent(new Event('error'));
    expect(wrapper.querySelector('img')).toBeNull();
    expect(wrapper.className).toBe('nowo-ui-avatar');
    expect(wrapper.textContent).toBe('AL');
    expect(mod.hasFailed('/files/broken/s64')).toBe(true);

    // A later fragment with the same URL loses its picture as soon as it is inserted.
    const host = document.createElement('div');
    host.innerHTML = avatar('/files/broken/s64') + avatar('/files/fine/s64');
    document.body.append(host);
    await flush();
    expect(host.querySelectorAll('img')).toHaveLength(1);
    expect(host.querySelector('img')?.getAttribute('src')).toBe('/files/fine/s64');

    // An inserted bare <img> with an unsafe URL goes too; other nodes are ignored.
    const bare = document.createElement('img');
    bare.setAttribute('data-nowo-avatar-image', '');
    bare.setAttribute('src', 'http://x.test/a.png');
    const span = document.createElement('span');
    span.append(bare);
    document.body.append(span, document.createTextNode('x'), document.createElement('img'));
    await flush();
    expect(span.querySelector('img')).toBeNull();
    expect(document.querySelectorAll('img')).toHaveLength(2);
  });

  it('ignores the events of other images', () => {
    mod.bindNowoUiAvatar();
    document.body.innerHTML = '<span class="x has-image"><img src="/other.png" alt="Other"></span>';
    const img = document.querySelector('img') as HTMLImageElement;
    img.dispatchEvent(new Event('load'));
    img.dispatchEvent(new Event('error'));
    expect(document.querySelector('img')).not.toBeNull();
    expect(document.querySelector('span')?.className).toBe('x has-image');
  });

  it('settles pictures that finished before the script ran, and the opt-in attribute of other bundles', () => {
    document.body.innerHTML = avatar('/ok.webp') + avatar('/broken.webp') + avatar('/pending.webp') + '<span class="audit has-image">AB<img data-nowo-avatar-image src="data:image/png;base64,AAAA" alt=""></span>';
    const [ok, broken, pending] = Array.from(document.querySelectorAll('img'));
    finished(ok, 64);
    finished(broken, 0);
    Object.defineProperty(pending, 'complete', { value: false, configurable: true });
    mod.scanAvatars();
    const wrappers = document.querySelectorAll('span');
    expect(wrappers[0].className).toBe('nowo-ui-avatar has-image is-loaded');
    expect(wrappers[1].className).toBe('nowo-ui-avatar');
    expect(wrappers[1].querySelector('img')).toBeNull();
    expect(wrappers[2].className).toBe('nowo-ui-avatar has-image');
    expect(wrappers[2].querySelector('img')).not.toBeNull();
    expect(wrappers[3].className).toBe('audit');
    expect(mod.hasFailed('/broken.webp')).toBe(true);
  });

  it('scans a root that is itself a picture, and a detached picture without src', () => {
    document.body.innerHTML = avatar('/ok.webp');
    const img = document.querySelector('img') as HTMLImageElement;
    finished(img, 10);
    mod.scanAvatars(img);
    expect(img.parentElement?.classList.contains('is-loaded')).toBe(true);

    const orphan = document.createElement('img');
    orphan.className = 'nowo-ui-avatar__image';
    mod.fallBack(orphan);
    mod.markLoaded(orphan);
    expect(mod.hasFailed('')).toBe(false);
  });

  it('scans again on DOMContentLoaded while the document is loading', () => {
    Object.defineProperty(document, 'readyState', { value: 'loading', configurable: true });
    mod.bindNowoUiAvatar();
    document.body.innerHTML = avatar('/late.webp');
    finished(document.querySelector('img') as HTMLImageElement, 0);
    document.dispatchEvent(new Event('DOMContentLoaded'));
    Object.defineProperty(document, 'readyState', { value: 'complete', configurable: true });
    expect(document.querySelector('img')).toBeNull();
  });
});
