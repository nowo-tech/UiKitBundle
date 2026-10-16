import { afterEach, describe, expect, it } from 'vitest';

import { syncIconState } from '../stimulus-peers/icon_state';
import {
  asMorphIcon,
  installMorphiconPeers,
  morphiconsIconStateHandler,
  resolveReducedMotion,
  syncContentWidthMorphIcon,
  syncDisclosureMorphIcon,
  syncFiltersCollapseMorphIcon,
  syncListingCustomizerMorphIcon,
  syncMorphIcon,
  syncPasswordMorphIcon,
  syncSearchMorphIcon,
  syncSidebarMorphIcon,
  syncSupportMorphIcon,
  syncTableModeMorphIcon,
  syncThemeMorphIcon,
  tableModeIcons,
} from './index';

describe('morphicons helpers', () => {
  afterEach(() => {
    document.body.innerHTML = '';
    document.documentElement.dataset.motion = '';
  });

  it('hydrates the theme morph-icon without animation and morphs on later sync', () => {
    document.body.innerHTML = '<button type="button"><morph-icon data-theme-morph size="14"></morph-icon></button>';
    const button = document.querySelector('button') as HTMLElement;

    syncThemeMorphIcon(button, 'light', false);
    expect(button.classList.contains('is-morph-ready')).toBe(true);
    const el = button.querySelector('morph-icon') as HTMLElement & { reducedMotion: string };
    expect(el.querySelector('svg')).not.toBeNull();
    expect(el.style.getPropertyValue('display')).toBe('inline-flex');
    expect(el.style.width).toBe('14px');
    expect(el.reducedMotion).toBe('never');

    syncThemeMorphIcon(button, 'dark', true);
    expect(button.classList.contains('is-morph-ready')).toBe(true);
  });

  it('honours data-motion="reduce" and custom selectors / sizes', () => {
    document.documentElement.dataset.motion = 'reduce';
    expect(resolveReducedMotion()).toBe('always');
    document.body.innerHTML = '<button type="button"><morph-icon class="mine"></morph-icon></button>';
    const button = document.querySelector('button') as HTMLElement;
    syncContentWidthMorphIcon(button, 'content', false, { selector: '.mine', size: 20 });
    const el = button.querySelector('morph-icon') as HTMLElement & { reducedMotion: string };
    expect(el.reducedMotion).toBe('always');
    expect(el.style.width).toBe('20px');
    syncContentWidthMorphIcon(button, 'full', true, { selector: '.mine' });
    expect(button.classList.contains('is-morph-ready')).toBe(true);
  });

  it('creates the password morph-icon when missing', () => {
    document.body.innerHTML = '<button type="button" id="a"><span class="icon"></span></button><button type="button" id="b"></button>';
    const a = document.getElementById('a') as HTMLElement;
    syncPasswordMorphIcon(a, false, false);
    expect(a.classList.contains('is-morph-ready')).toBe(true);
    const created = a.querySelector('[data-password-morph]') as HTMLElement;
    expect(created.className).toBe('nowo-ui-password-toggle__morph');
    expect(created.getAttribute('stroke-width')).toBe('1.75');
    expect(a.querySelector('.icon')).toBeNull();
    syncPasswordMorphIcon(a, true, true);
    expect(a.querySelectorAll('morph-icon')).toHaveLength(1);

    const b = document.getElementById('b') as HTMLElement;
    syncPasswordMorphIcon(b, true, false, { className: 'password-toggle__morph', strokeWidth: '2', size: 16 });
    expect(b.querySelector('morph-icon')?.className).toBe('password-toggle__morph');
    expect(b.querySelector('morph-icon')?.getAttribute('size')).toBe('16');
  });

  it('hydrates the sidebar burger menu → rail → X and the listing customizer', () => {
    document.body.innerHTML = `
      <button type="button" id="s"><morph-icon data-sidebar-morph></morph-icon></button>
      <button type="button" id="l"><morph-icon data-listing-morph></morph-icon></button>`;
    const sidebar = document.getElementById('s') as HTMLElement;
    syncSidebarMorphIcon(sidebar, 'closed', false);
    syncSidebarMorphIcon(sidebar, 'rail', true);
    syncSidebarMorphIcon(sidebar, 'open', true);
    expect(sidebar.querySelector('morph-icon svg')).not.toBeNull();
    const listing = document.getElementById('l') as HTMLElement;
    syncListingCustomizerMorphIcon(listing, false, false);
    syncListingCustomizerMorphIcon(listing, true, true);
    expect(listing.classList.contains('is-morph-ready')).toBe(true);
  });

  it('paints and morphs the table mode, filter fold, disclosure and support toggles', () => {
    for (const [attr, sync] of [
      ['data-table-mode-morph', (host: HTMLElement, on: boolean, animate: boolean) => syncTableModeMorphIcon(host, on ? 'responsive' : 'scroll', animate)],
      ['data-filters-collapse-morph', syncFiltersCollapseMorphIcon],
      ['data-disclosure-morph', syncDisclosureMorphIcon],
      ['data-support-morph', syncSupportMorphIcon],
    ] as const) {
      const host = document.createElement('button');
      host.innerHTML = `<morph-icon ${attr}></morph-icon>`;
      document.body.append(host);
      sync(host, true, false);
      expect(host.classList.contains('is-morph-ready')).toBe(true);
      expect(host.querySelector('morph-icon svg')).not.toBeNull();
      sync(host, false, true);
      expect(host.querySelector('morph-icon svg')).not.toBeNull();
    }
  });

  it('morphs the search toggle magnifier → X → loader and flags loading', () => {
    const host = document.createElement('button');
    host.innerHTML = '<morph-icon data-search-morph></morph-icon>';
    document.body.append(host);
    syncSearchMorphIcon(host, 'idle', false);
    expect(host.classList.contains('is-morph-ready')).toBe(true);
    syncSearchMorphIcon(host, 'loading', true);
    expect(host.classList.contains('is-loading')).toBe(true);
    syncSearchMorphIcon(host, 'open', true);
    expect(host.classList.contains('is-loading')).toBe(false);
  });

  it('no-ops when morph targets are missing', () => {
    const button = document.createElement('button');
    button.innerHTML = '<span data-theme-morph></span>';
    expect(asMorphIcon(button.querySelector('span'))).toBeNull();
    expect(asMorphIcon(null)).toBeNull();
    expect(syncMorphIcon(button, '[data-theme-morph]', tableModeIcons.scroll, false)).toBe(false);
    syncThemeMorphIcon(button, 'dark', true);
    syncContentWidthMorphIcon(button, 'full', true);
    syncSidebarMorphIcon(button, 'open', true);
    syncListingCustomizerMorphIcon(button, true, true);
    syncTableModeMorphIcon(button, 'responsive', true);
    syncFiltersCollapseMorphIcon(button, true, true);
    syncDisclosureMorphIcon(button, true, true);
    syncSearchMorphIcon(button, 'loading', true);
    syncSupportMorphIcon(button, true, true);
    expect(button.classList.contains('is-morph-ready')).toBe(false);
    expect(button.classList.contains('is-loading')).toBe(false);
  });

  it('animates the kit peers once installed', () => {
    document.body.innerHTML = `
      <button type="button" id="t"><morph-icon data-table-mode-morph></morph-icon></button>
      <button type="button" id="f"><morph-icon data-filters-collapse-morph></morph-icon></button>
      <button type="button" id="d"><morph-icon data-disclosure-morph></morph-icon></button>`;
    installMorphiconPeers();
    for (const [id, kind, states] of [
      ['t', 'table-mode', ['scroll', 'responsive']],
      ['f', 'filters-collapse', ['expanded', 'collapsed']],
      ['d', 'disclosure', ['closed', 'open']],
    ] as const) {
      const host = document.getElementById(id) as HTMLElement;
      syncIconState(kind, host, states[0], false);
      syncIconState(kind, host, states[1], true);
      expect(host.classList.contains('is-morph-ready')).toBe(true);
    }
    const host = document.getElementById('t') as HTMLElement;
    host.classList.remove('is-morph-ready');
    morphiconsIconStateHandler('table-mode', host, 'responsive', false);
    expect(host.classList.contains('is-morph-ready')).toBe(true);
  });
});
