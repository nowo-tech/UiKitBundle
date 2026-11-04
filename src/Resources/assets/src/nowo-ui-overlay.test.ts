import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  CLOSE_EVENT,
  closeOnBackdropDefault,
  createOverlayRegistry,
  MARK,
  OPEN_EVENT,
  overlayRegistry,
  ROOT_ATTR,
  SHELL,
  supportsInert,
  type OverlayDetail,
  type OverlayRegistry,
} from './nowo-ui-overlay';

const flush = (): Promise<void> => new Promise((r) => setTimeout(r, 0));

const shell = (): void => {
  document.body.innerHTML = `
    <div data-nowo-ui-shell>
      <header id="hdr">H</header>
      <aside id="aside" data-nowo-ui-aside>A</aside>
      <div class="nowo-ui-main" id="main">
        <aside id="panel">P</aside>
      </div>
    </div>
    <footer id="footer" data-nowo-ui-overlay-region>F</footer>
    <div id="toasts" aria-live="polite"></div>`;
};

const el = (id: string): HTMLElement => document.getElementById(id) as HTMLElement;

describe('overlay registry', () => {
  let registry: OverlayRegistry;
  let events: Array<{ type: string; detail: OverlayDetail }>;
  const record = (event: Event): void => {
    events.push({ type: event.type, detail: (event as CustomEvent<OverlayDetail>).detail });
  };

  beforeEach(() => {
    shell();
    registry = createOverlayRegistry();
    events = [];
    document.addEventListener(OPEN_EVENT, record);
    document.addEventListener(CLOSE_EVENT, record);
  });

  afterEach(() => {
    registry.releaseAll();
    document.removeEventListener(OPEN_EVENT, record);
    document.removeEventListener(CLOSE_EVENT, record);
    document.documentElement.removeAttribute(ROOT_ATTR);
    document.body.innerHTML = '';
    vi.restoreAllMocks();
  });

  it('counts depth, marks the root and emits open / close with the depth after the change', () => {
    const a = registry.acquire(el('panel'), { kind: 'side-panel' });
    const b = registry.acquire(el('main'), { kind: 'dialog', inertShell: false });
    expect(registry.depth()).toBe(2);
    expect(document.documentElement.getAttribute(ROOT_ATTR)).toBe('2');
    b.release();
    b.release();
    a.release();
    expect(registry.depth()).toBe(0);
    expect(document.documentElement.hasAttribute(ROOT_ATTR)).toBe(false);
    expect(events.map((e) => `${e.type}:${e.detail.depth}:${e.detail.kind}`)).toEqual([
      'nowo:overlay-open:1:side-panel',
      'nowo:overlay-open:2:dialog',
      'nowo:overlay-close:1:dialog',
      'nowo:overlay-close:0:side-panel',
    ]);
    expect(events[0]?.detail.owner).toBe(el('panel'));
  });

  it('makes the shell regions outside the owner inert, never live regions, and restores only its own marks', () => {
    el('aside').setAttribute('inert', '');
    const handle = registry.acquire(el('panel'), { kind: 'side-panel' });
    expect(el('hdr').hasAttribute('inert')).toBe(true);
    expect(el('hdr').hasAttribute(MARK)).toBe(true);
    expect(el('footer').hasAttribute('inert')).toBe(true);
    expect(el('aside').hasAttribute(MARK)).toBe(false);
    expect(el('toasts').hasAttribute('inert')).toBe(false);
    handle.release();
    expect(el('hdr').hasAttribute('inert')).toBe(false);
    expect(el('hdr').hasAttribute(MARK)).toBe(false);
    expect(el('aside').hasAttribute('inert')).toBe(true);
  });

  it('keeps an inert the host wrote after ours', async () => {
    const handle = registry.acquire(el('panel'), { kind: 'drawer' });
    el('hdr').setAttribute('inert', '');
    await flush();
    handle.release();
    expect(el('hdr').hasAttribute('inert')).toBe(true);
    expect(el('hdr').hasAttribute(MARK)).toBe(false);
  });

  it('never inerts a region that contains or is inside an owner', () => {
    el('hdr').innerHTML = '<div id="in-header"></div>';
    const handle = registry.acquire(el('in-header'), { kind: 'drawer' });
    expect(el('hdr').hasAttribute('inert')).toBe(false);
    expect(el('aside').hasAttribute('inert')).toBe(true);
    handle.release();
  });

  it('does not inert the shell for owners that ask so (top-layer dialogs)', () => {
    const handle = registry.acquire(el('panel'), { kind: 'dialog', inertShell: false });
    expect(el('hdr').hasAttribute('inert')).toBe(false);
    expect(handle.closeOnBackdrop).toBe(false);
    handle.release();
  });

  it('resolves closeOnBackdrop per kind, with an override', () => {
    expect(closeOnBackdropDefault('dialog')).toBe(false);
    expect(closeOnBackdropDefault('side-panel')).toBe(true);
    expect(closeOnBackdropDefault('drawer')).toBe(true);
    expect(closeOnBackdropDefault('host')).toBe(false);
    expect(closeOnBackdropDefault('bogus')).toBe(false);
    expect(registry.closeOnBackdrop('drawer')).toBe(true);
    const handle = registry.acquire(el('panel'), { kind: 'drawer', closeOnBackdrop: false });
    expect(handle.closeOnBackdrop).toBe(false);
    expect(registry.acquire(el('main')).kind).toBe('host');
    expect(registry.acquire(el('main'), { kind: 'weird' as never }).kind).toBe('host');
  });

  it('drops the handles of owners removed from the DOM', () => {
    registry.acquire(el('panel'), { kind: 'drawer' });
    el('panel').remove();
    expect(registry.depth()).toBe(0);
    registry.sync();
    expect(el('hdr').hasAttribute('inert')).toBe(false);
    expect(document.documentElement.hasAttribute(ROOT_ATTR)).toBe(false);
    expect(events[events.length - 1]?.type).toBe(CLOSE_EVENT);
  });

  it('gives the focus back when the last overlay closes and focus fell to the body', () => {
    const button = document.createElement('button');
    document.body.append(button);
    button.focus();
    const handle = registry.acquire(el('panel'), { kind: 'drawer' });
    (document.activeElement as HTMLElement | null)?.blur();
    handle.release();
    expect(document.activeElement).toBe(button);
  });

  it('falls back to aria-hidden without inert support', () => {
    const descriptor = Object.getOwnPropertyDescriptor(HTMLElement.prototype, 'inert');
    // @ts-expect-error test-only removal of the inert property
    delete HTMLElement.prototype.inert;
    try {
      expect(supportsInert()).toBe(false);
      const handle = registry.acquire(el('panel'), { kind: 'drawer' });
      expect(el('hdr').getAttribute('aria-hidden')).toBe('true');
      expect(el('hdr').hasAttribute('inert')).toBe(false);
      handle.release();
      expect(el('hdr').hasAttribute('aria-hidden')).toBe(false);
    } finally {
      if (descriptor) {
        Object.defineProperty(HTMLElement.prototype, 'inert', descriptor);
      }
    }
  });

  it('works without MutationObserver', () => {
    vi.stubGlobal('MutationObserver', undefined);
    try {
      const handle = registry.acquire(el('panel'), { kind: 'drawer' });
      expect(el('hdr').hasAttribute('inert')).toBe(true);
      handle.release();
      expect(el('hdr').hasAttribute('inert')).toBe(false);
    } finally {
      vi.unstubAllGlobals();
    }
  });

  it('exposes the shell selector', () => {
    expect(registry.SHELL).toBe(SHELL);
    expect(registry.version).toBe(1);
  });
});

describe('shared registry', () => {
  it('is created once on window and shared by every copy of the module', () => {
    const first = overlayRegistry();
    expect(window.NowoUiOverlay).toBe(first);
    expect(overlayRegistry()).toBe(first);
  });

  it('is replaced when another version sits on window, and released on pagehide', () => {
    window.NowoUiOverlay = { version: 0 } as unknown as OverlayRegistry;
    const registry = overlayRegistry();
    expect(registry.version).toBe(1);
    document.body.innerHTML = '<div id="o"></div>';
    registry.acquire(document.getElementById('o') as HTMLElement);
    window.dispatchEvent(new Event('pagehide'));
    expect(registry.depth()).toBe(0);
  });
});
