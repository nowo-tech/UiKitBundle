import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  advanceSidebarMode,
  applySidebarShellState,
  isMobileSidebar,
  isSidebarMode,
  nextSidebarMode,
  parseSidebarCookie,
  parseSidebarMode,
  persistSidebarMode,
  railInitial,
  readSidebarDirection,
  readStoredSidebarMode,
  resolveSidebarMode,
  SIDEBAR_DEFAULTS,
  sidebarCookieString,
  sidebarDirectionAfter,
  sidebarOptionsFrom,
  sidebarStoredValue,
  sidebarToggleLabelAttribute,
  sidebarToggleState,
  syncSidebarRailLabels,
  syncSidebarStorageFromCookie,
  syncSidebarToggle,
  writeSidebarCookie,
  writeSidebarDirection,
  type SidebarOptions,
} from './nowo-ui-sidebar-core';

const COOKIE_OPTS: SidebarOptions = { ...SIDEBAR_DEFAULTS, cookieName: 'app_sidebar' };
const LEGACY_OPTS: SidebarOptions = { ...COOKIE_OPTS, storageKey: 'app-sidebar', hiddenValue: 'collapsed' };

/** Spy on the `document.cookie` setter (the accessor lives on an internal prototype in happy-dom). */
function spyCookieSetter() {
  let proto: object | null = document;
  while (proto && !Object.getOwnPropertyDescriptor(proto, 'cookie')) {
    proto = Object.getPrototypeOf(proto);
  }
  return vi.spyOn(proto as Document, 'cookie', 'set');
}

/** Replace window.sessionStorage with a throwing stub for the duration of `fn`. */
function withBlockedSessionStorage(fn: () => void): void {
  const original = Object.getOwnPropertyDescriptor(window, 'sessionStorage');
  const blocked = {
    getItem: () => {
      throw new Error('blocked');
    },
    setItem: () => {
      throw new Error('blocked');
    },
  };
  Object.defineProperty(window, 'sessionStorage', { configurable: true, value: blocked });
  try {
    fn();
  } finally {
    if (original) {
      Object.defineProperty(window, 'sessionStorage', original);
    } else {
      delete (window as unknown as Record<string, unknown>).sessionStorage;
    }
  }
}

function clearCookie(name = 'app_sidebar'): void {
  document.cookie = `${name}=; Path=/; Max-Age=0`;
}

function mockMobile(matches: boolean): void {
  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    configurable: true,
    value: vi.fn().mockImplementation((query: string) => ({
      matches,
      media: query,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
    })),
  });
}

function navFixture(): HTMLElement {
  const shell = document.createElement('div');
  shell.innerHTML = `
    <nav>
      <a class="nowo-ui-aside__link" href="/a" title="Original"><svg></svg> Projects</a>
      <a class="nowo-ui-aside__link" href="/b">  émails </a>
      <a class="nowo-ui-aside__link" href="/c"><i class="bi bi-x"></i></a>
      <button class="nowo-ui-aside__group-toggle" type="button"><span>Admin</span></button>
    </nav>`;
  document.body.append(shell);

  return shell;
}

describe('sidebar mode helpers', () => {
  it('validates and normalises modes', () => {
    expect(isSidebarMode('expanded')).toBe(true);
    expect(isSidebarMode('rail')).toBe(true);
    expect(isSidebarMode('hidden')).toBe(true);
    expect(isSidebarMode('collapsed')).toBe(false);
    expect(parseSidebarMode('collapsed')).toBe('hidden');
    expect(parseSidebarMode('closed', { ...SIDEBAR_DEFAULTS, hiddenValue: 'closed' })).toBe('hidden');
    expect(parseSidebarMode('open')).toBeNull();
    expect(parseSidebarMode(null)).toBeNull();
    expect(sidebarStoredValue('hidden', LEGACY_OPTS)).toBe('collapsed');
    expect(sidebarStoredValue('rail', LEGACY_OPTS)).toBe('rail');
  });

  it('ping-pongs through the rail on desktop', () => {
    expect(nextSidebarMode('hidden', false)).toBe('rail');
    expect(nextSidebarMode('expanded', false)).toBe('rail');
    expect(nextSidebarMode('rail', false)).toBe('expanded');
    expect(nextSidebarMode('rail', false, 'closing')).toBe('hidden');
  });

  it('flips the direction only at the ends of the cycle', () => {
    expect(sidebarDirectionAfter('hidden', 'closing')).toBe('opening');
    expect(sidebarDirectionAfter('expanded', 'opening')).toBe('closing');
    expect(sidebarDirectionAfter('rail', 'closing')).toBe('closing');
    expect(sidebarDirectionAfter('rail', 'opening')).toBe('opening');
  });

  it('toggles open ↔ hidden on phones', () => {
    expect(nextSidebarMode('hidden', true)).toBe('expanded');
    expect(nextSidebarMode('expanded', true)).toBe('hidden');
    expect(nextSidebarMode('rail', true)).toBe('hidden');
  });

  it('maps modes to burger states', () => {
    expect(sidebarToggleState('hidden', false)).toBe('closed');
    expect(sidebarToggleState('rail', false)).toBe('rail');
    expect(sidebarToggleState('rail', true)).toBe('open');
    expect(sidebarToggleState('expanded', false)).toBe('open');
  });

  it('resolves the initial mode', () => {
    expect(resolveSidebarMode(true, 'rail', 'rail')).toBe('hidden');
    expect(resolveSidebarMode(false, 'rail', 'hidden')).toBe('rail');
    expect(resolveSidebarMode(false, 'bogus', 'collapsed')).toBe('hidden');
    expect(resolveSidebarMode(false, null, undefined)).toBe('expanded');
  });

  it('maps the next mode to its label data attribute', () => {
    expect(sidebarToggleLabelAttribute('expanded')).toBe('labelExpand');
    expect(sidebarToggleLabelAttribute('rail')).toBe('labelRail');
    expect(sidebarToggleLabelAttribute('hidden')).toBe('labelHide');
  });

  it('derives the fallback initial', () => {
    expect(railInitial('  émails')).toBe('É');
    expect(railInitial('')).toBe('');
  });

  it('reads the mobile breakpoint (false without matchMedia)', () => {
    mockMobile(true);
    expect(isMobileSidebar()).toBe(true);
    expect(window.matchMedia).toHaveBeenCalledWith(SIDEBAR_DEFAULTS.mobileQuery);
    mockMobile(false);
    expect(isMobileSidebar()).toBe(false);
    Object.defineProperty(window, 'matchMedia', { writable: true, configurable: true, value: undefined });
    expect(isMobileSidebar()).toBe(false);
  });

  it('reads options from data attributes', () => {
    const el = document.createElement('div');
    expect(sidebarOptionsFrom(el)).toEqual({ ...SIDEBAR_DEFAULTS, directionKey: 'nowo-ui-sidebar-direction' });
    el.setAttribute('data-nowo-ui-sidebar-storage', 'app-sidebar');
    el.setAttribute('data-nowo-ui-sidebar-cookie', 'app_sidebar');
    el.setAttribute('data-nowo-ui-sidebar-cookie-max-age', '60');
    el.setAttribute('data-nowo-ui-sidebar-hidden-value', 'collapsed');
    el.setAttribute('data-nowo-ui-sidebar-mobile-query', '(max-width: 600px)');
    expect(sidebarOptionsFrom(el)).toEqual({
      storageKey: 'app-sidebar',
      cookieName: 'app_sidebar',
      cookieMaxAge: 60,
      directionKey: 'app-sidebar-direction',
      mobileQuery: '(max-width: 600px)',
      hiddenValue: 'collapsed',
    });
    el.setAttribute('data-nowo-ui-sidebar-direction-storage', 'dir');
    el.setAttribute('data-nowo-ui-sidebar-cookie-max-age', 'x');
    expect(sidebarOptionsFrom(el).directionKey).toBe('dir');
    expect(sidebarOptionsFrom(el).cookieMaxAge).toBe(31536000);
    expect(sidebarOptionsFrom(null).storageKey).toBe('nowo-ui-sidebar');
  });
});

describe('sidebar cookie + storage', () => {
  beforeEach(() => {
    localStorage.clear();
    clearCookie();
    mockMobile(false);
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('builds the cookie string with and without Secure', () => {
    expect(sidebarCookieString('rail', false, COOKIE_OPTS)).toBe('app_sidebar=rail; Path=/; Max-Age=31536000; SameSite=Lax');
    expect(sidebarCookieString('hidden', true, LEGACY_OPTS)).toBe('app_sidebar=collapsed; Path=/; Max-Age=31536000; SameSite=Lax; Secure');
  });

  it('parses the cookie value', () => {
    expect(parseSidebarCookie('a=1; app_sidebar=collapsed; b=2', COOKIE_OPTS)).toBe('hidden');
    expect(parseSidebarCookie('app_sidebar=bogus', COOKIE_OPTS)).toBeNull();
    expect(parseSidebarCookie('', COOKIE_OPTS)).toBeNull();
    expect(parseSidebarCookie('app_sidebar=rail')).toBeNull();
  });

  it('writes the cookie only when a name is configured', () => {
    const setter = spyCookieSetter();
    writeSidebarCookie('hidden');
    expect(setter).not.toHaveBeenCalled();
    writeSidebarCookie('hidden', COOKIE_OPTS);
    expect(setter).toHaveBeenLastCalledWith(sidebarCookieString('hidden', false, COOKIE_OPTS));
  });

  it('persists to storage and cookie, tolerating storage errors', () => {
    persistSidebarMode('rail', LEGACY_OPTS);
    expect(localStorage.getItem('app-sidebar')).toBe('rail');
    expect(parseSidebarCookie(document.cookie, LEGACY_OPTS)).toBe('rail');

    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('quota');
    });
    persistSidebarMode('hidden', LEGACY_OPTS);
    expect(document.cookie).toContain('app_sidebar=collapsed');
  });

  it('reads the stored mode, null on invalid or blocked storage', () => {
    expect(readStoredSidebarMode()).toBeNull();
    localStorage.setItem('nowo-ui-sidebar', 'hidden');
    expect(readStoredSidebarMode()).toBe('hidden');
    localStorage.setItem('nowo-ui-sidebar', 'bogus');
    expect(readStoredSidebarMode()).toBeNull();
    const original = Object.getOwnPropertyDescriptor(window, 'localStorage');
    Object.defineProperty(window, 'localStorage', {
      configurable: true,
      get: () => {
        throw new Error('blocked');
      },
    });
    try {
      expect(readStoredSidebarMode()).toBeNull();
      expect(() => syncSidebarStorageFromCookie('rail', COOKIE_OPTS)).not.toThrow();
      expect(() => persistSidebarMode('rail', COOKIE_OPTS)).not.toThrow();
    } finally {
      if (original) {
        Object.defineProperty(window, 'localStorage', original);
      }
    }
  });

  it('pre-paint sync: cookie wins, default seeds, storage mirrors to cookie', () => {
    document.cookie = 'app_sidebar=rail; Path=/';
    localStorage.setItem('app-sidebar', 'expanded');
    syncSidebarStorageFromCookie('collapsed', LEGACY_OPTS);
    expect(localStorage.getItem('app-sidebar')).toBe('rail');

    clearCookie();
    localStorage.clear();
    syncSidebarStorageFromCookie('collapsed', LEGACY_OPTS);
    expect(localStorage.getItem('app-sidebar')).toBe('collapsed');
    expect(parseSidebarCookie(document.cookie, LEGACY_OPTS)).toBe('hidden');

    clearCookie();
    localStorage.setItem('app-sidebar', 'rail');
    syncSidebarStorageFromCookie('expanded', LEGACY_OPTS);
    expect(localStorage.getItem('app-sidebar')).toBe('rail');
    expect(parseSidebarCookie(document.cookie, LEGACY_OPTS)).toBe('rail');

    clearCookie();
    localStorage.clear();
    syncSidebarStorageFromCookie('', LEGACY_OPTS);
    expect(localStorage.getItem('app-sidebar')).toBeNull();
    expect(parseSidebarCookie(document.cookie, LEGACY_OPTS)).toBeNull();
  });

  it('pre-paint sync leaves an in-sync cookie alone and survives blocked storage', () => {
    document.cookie = 'app_sidebar=expanded; Path=/';
    localStorage.setItem('nowo-ui-sidebar', 'expanded');
    const setter = spyCookieSetter();
    syncSidebarStorageFromCookie('rail', COOKIE_OPTS);
    expect(setter).not.toHaveBeenCalled();
    setter.mockRestore();

    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('blocked');
    });
    expect(() => syncSidebarStorageFromCookie('rail', COOKIE_OPTS)).not.toThrow();
  });
});

describe('sidebar burger direction', () => {
  afterEach(() => {
    sessionStorage.clear();
    vi.restoreAllMocks();
  });

  it('defaults to opening and remembers the direction per tab', () => {
    expect(readSidebarDirection()).toBe('opening');
    writeSidebarDirection('closing');
    expect(sessionStorage.getItem('nowo-ui-sidebar-direction')).toBe('closing');
    expect(readSidebarDirection()).toBe('closing');
  });

  it('walks hidden → rail → expanded → rail → hidden', () => {
    let mode = advanceSidebarMode('hidden', false);
    expect(mode).toBe('rail');
    mode = advanceSidebarMode(mode, false);
    expect(mode).toBe('expanded');
    mode = advanceSidebarMode(mode, false);
    expect(mode).toBe('rail');
    mode = advanceSidebarMode(mode, false);
    expect(mode).toBe('hidden');
  });

  it('toggles on phones without touching the direction', () => {
    expect(advanceSidebarMode('hidden', true)).toBe('expanded');
    expect(sessionStorage.getItem('nowo-ui-sidebar-direction')).toBeNull();
  });

  it('falls back to opening when sessionStorage is blocked', () => {
    withBlockedSessionStorage(() => {
      expect(readSidebarDirection()).toBe('opening');
      expect(() => writeSidebarDirection('closing')).not.toThrow();
      expect(advanceSidebarMode('rail', false)).toBe('expanded');
    });
  });
});

describe('sidebar DOM glue', () => {
  afterEach(() => {
    document.body.innerHTML = '';
    sessionStorage.clear();
  });

  it('labels the toggle with the next action and sets aria-expanded', () => {
    const button = document.createElement('button');
    button.dataset.labelExpand = 'Expand navigation';
    button.dataset.labelRail = 'Show icons only';
    button.dataset.labelHide = 'Hide navigation';

    expect(syncSidebarToggle(button, 'expanded', false)).toBe('open');
    expect(button.getAttribute('aria-expanded')).toBe('true');
    expect(button.getAttribute('aria-label')).toBe('Show icons only');
    expect(button.getAttribute('title')).toBe('Show icons only');
    expect(button.getAttribute('data-nowo-ui-sidebar-state')).toBe('open');

    writeSidebarDirection('closing');
    expect(syncSidebarToggle(button, 'rail', false)).toBe('rail');
    expect(button.getAttribute('aria-label')).toBe('Hide navigation');

    expect(syncSidebarToggle(button, 'hidden', false)).toBe('closed');
    expect(button.getAttribute('aria-expanded')).toBe('false');
    expect(button.getAttribute('aria-label')).toBe('Show icons only');

    writeSidebarDirection('opening');
    syncSidebarToggle(button, 'rail', false);
    expect(button.getAttribute('aria-label')).toBe('Expand navigation');

    expect(syncSidebarToggle(button, 'expanded', true)).toBe('open');
    expect(button.getAttribute('aria-label')).toBe('Hide navigation');
  });

  it('accepts data-label-collapse and keeps the label when none is configured', () => {
    const legacy = document.createElement('button');
    legacy.dataset.labelCollapse = 'Collapse';
    syncSidebarToggle(legacy, 'expanded', true);
    expect(legacy.getAttribute('aria-label')).toBe('Collapse');

    const button = document.createElement('button');
    button.setAttribute('aria-label', 'Toggle navigation');
    syncSidebarToggle(button, 'rail', false);
    expect(button.getAttribute('aria-label')).toBe('Toggle navigation');
    expect(button.hasAttribute('title')).toBe(false);
  });

  it('adds and removes rail labels, titles and fallback initials', () => {
    const shell = navFixture();
    const [withIcon, noIcon, empty] = shell.querySelectorAll<HTMLElement>('.nowo-ui-aside__link');
    const toggle = shell.querySelector<HTMLElement>('.nowo-ui-aside__group-toggle')!;

    syncSidebarRailLabels(shell, true);
    expect(withIcon.getAttribute('data-nowo-ui-rail-label')).toBe('Projects');
    expect(withIcon.hasAttribute('title')).toBe(false);
    expect(withIcon.getAttribute('data-nowo-ui-rail-title')).toBe('Original');
    expect(withIcon.hasAttribute('data-nowo-ui-rail-initial')).toBe(false);
    expect(noIcon.getAttribute('data-nowo-ui-rail-initial')).toBe('É');
    expect(empty.hasAttribute('data-nowo-ui-rail-label')).toBe(false);
    expect(toggle.getAttribute('data-nowo-ui-rail-label')).toBe('Admin');

    syncSidebarRailLabels(shell, true);
    expect(withIcon.getAttribute('data-nowo-ui-rail-title')).toBe('Original');

    syncSidebarRailLabels(shell, false);
    expect(withIcon.getAttribute('title')).toBe('Original');
    expect(withIcon.hasAttribute('data-nowo-ui-rail-label')).toBe(false);
    expect(noIcon.hasAttribute('title')).toBe(false);
    expect(noIcon.hasAttribute('data-nowo-ui-rail-initial')).toBe(false);
    expect(empty.hasAttribute('title')).toBe(false);
  });

  it('supports host markup: classes, attribute prefix, label lookup', () => {
    const shell = document.createElement('div');
    shell.innerHTML = `<a class="nav-link">Home</a><span class="section"><b>Admin</b><button class="toggle">x</button></span>`;
    const dom = {
      classes: { hidden: 'is-sidebar-collapsed', rail: 'is-sidebar-rail', mobileOpen: 'is-sidebar-open' },
      railLabelSelector: '.nav-link, .toggle',
      railAttributePrefix: 'data-rail',
      modeAttribute: 'data-sidebar-mode',
      labelFor: (el: HTMLElement) =>
        el.classList.contains('toggle') ? (el.parentElement?.querySelector('b')?.textContent ?? '') : (el.textContent ?? ''),
    };
    applySidebarShellState(shell, 'rail', false, dom);
    expect(shell.classList.contains('is-sidebar-rail')).toBe(true);
    expect(shell.dataset.sidebarMode).toBe('rail');
    expect(shell.querySelector<HTMLElement>('.toggle')!.dataset.railLabel).toBe('Admin');
    expect(shell.querySelector<HTMLElement>('.nav-link')!.dataset.railInitial).toBe('H');
  });

  it('applies kit shell classes per mode and viewport', () => {
    const shell = navFixture();

    applySidebarShellState(shell, 'rail', false);
    expect(shell.classList.contains('is-aside-rail')).toBe(true);
    expect(shell.classList.contains('is-aside-collapsed')).toBe(false);
    expect(shell.getAttribute('data-nowo-ui-sidebar-mode')).toBe('rail');
    expect(shell.querySelector('.nowo-ui-aside__link')!.getAttribute('data-nowo-ui-rail-label')).toBe('Projects');

    applySidebarShellState(shell, 'hidden', false);
    expect(shell.classList.contains('is-aside-collapsed')).toBe(true);
    expect(shell.classList.contains('is-aside-rail')).toBe(false);
    expect(shell.querySelector('.nowo-ui-aside__link')!.hasAttribute('data-nowo-ui-rail-label')).toBe(false);

    applySidebarShellState(shell, 'rail', true);
    expect(shell.classList.contains('is-aside-rail')).toBe(false);
    expect(shell.classList.contains('is-aside-open-mobile')).toBe(true);

    applySidebarShellState(shell, 'hidden', true);
    expect(shell.classList.contains('is-aside-open-mobile')).toBe(false);
    expect(shell.classList.contains('is-aside-collapsed')).toBe(false);
  });
});
