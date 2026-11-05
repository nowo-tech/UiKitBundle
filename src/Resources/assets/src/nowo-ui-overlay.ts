/**
 * Overlay registry (2.28, `js/nowo-ui-overlay.js`; also bundled into nowo-ui-dialog.js and nowo-ui-side-panel.js).
 *
 * One owner of "a modal overlay is open" for UiKit, host scripts and other nowo bundles (ChatKit's dock):
 * - `window.NowoUiOverlay.acquire(owner, { kind, inertShell, closeOnBackdrop })` returns a handle; `handle.release()`
 *   undoes it (idempotent). Depth = live handles.
 * - While depth > 0: `<html data-nowo-overlay="<depth>">`.
 * - `nowo:overlay-open` / `nowo:overlay-close` on `document` after each change, detail `{ depth, kind, owner }`
 *   (`depth` after the change; `close` with depth 0 = every overlay closed). A DOM contract: listeners need no UiKit.
 * - Shell inert: while a live handle wants it (`inertShell`, default true), every `SHELL` region that neither contains
 *   nor is inside a live owner gets `inert` + `data-nowo-ui-overlay-inert`. A region already `inert` is left alone,
 *   and an `inert` the host writes after ours is never lifted. Without `inert` support the same regions get
 *   `aria-hidden="true"` instead (marked the same way).
 * - `closeOnBackdrop` per kind: `dialog` / `host` false, `side-panel` / `drawer` true; owners ask the handle.
 * - An owner removed from the DOM while open (Turbo frame, Stimulus replacement, host code) is released at once: while
 *   depth > 0 a `childList` observer on the document prunes disconnected owners (no stuck shell, threat T9 / ES-L1).
 * - Every IIFE carries its own copy of this module, so the state lives on `window.NowoUiOverlay` (first copy wins).
 */

export type OverlayKind = 'dialog' | 'side-panel' | 'drawer' | 'host';

export interface OverlayOptions {
  kind?: OverlayKind;
  inertShell?: boolean;
  closeOnBackdrop?: boolean;
}

export interface OverlayHandle {
  readonly kind: OverlayKind;
  readonly owner: Element;
  readonly closeOnBackdrop: boolean;
  readonly inertShell: boolean;
  /** Whether the handle still counts (not released, owner not pruned). */
  active(): boolean;
  release(): void;
}

export interface OverlayDetail {
  depth: number;
  kind: OverlayKind;
  owner: Element;
}

export interface OverlayRegistry {
  readonly version: 1;
  readonly SHELL: string;
  acquire(owner: Element, options?: OverlayOptions): OverlayHandle;
  depth(): number;
  closeOnBackdrop(kind: string): boolean;
  /** Re-applies the shell state (e.g. after the host inserted a region); also drops handles of removed owners. */
  sync(): void;
  /** Releases every handle (page hide). */
  releaseAll(): void;
}

declare global {
  interface Window {
    NowoUiOverlay?: OverlayRegistry;
  }
}

export const SHELL = '[data-nowo-ui-shell] > header, [data-nowo-ui-aside], [data-nowo-ui-overlay-region]';
export const MARK = 'data-nowo-ui-overlay-inert';
export const ROOT_ATTR = 'data-nowo-overlay';
export const OPEN_EVENT = 'nowo:overlay-open';
export const CLOSE_EVENT = 'nowo:overlay-close';

const BACKDROP_DEFAULTS: Record<OverlayKind, boolean> = { dialog: false, 'side-panel': true, drawer: true, host: false };
const KINDS: OverlayKind[] = ['dialog', 'side-panel', 'drawer', 'host'];

export function closeOnBackdropDefault(kind: string): boolean {
  return (KINDS as string[]).includes(kind) ? BACKDROP_DEFAULTS[kind as OverlayKind] : false;
}

/** Whether the browser knows the `inert` attribute (else `aria-hidden` stands in for it). */
export function supportsInert(): boolean {
  return typeof HTMLElement !== 'undefined' && 'inert' in HTMLElement.prototype;
}

type MarkState = { attr: 'inert' | 'aria-hidden'; observer: MutationObserver | null; hostTouched: boolean };

/** Builds a fresh registry (exported for tests; pages use the shared `window.NowoUiOverlay`). */
export function createOverlayRegistry(): OverlayRegistry {
  const handles: OverlayHandle[] = [];
  /** Regions we marked, with the observer that tells whether the host wrote the attribute since. */
  const marked = new Map<HTMLElement, MarkState>();
  let savedFocus: Element | null = null;
  /** Watches removals while overlays are open, so a removed owner never leaves the shell inert. */
  let removals: MutationObserver | null = null;

  const live = (): OverlayHandle[] => handles.filter((handle) => handle.owner.isConnected);

  const mark = (region: HTMLElement): void => {
    const attr = supportsInert() ? 'inert' : 'aria-hidden';
    if (marked.has(region) || (attr === 'inert' ? region.hasAttribute('inert') : region.getAttribute('aria-hidden') === 'true')) {
      return;
    }
    if (attr === 'inert') {
      region.setAttribute('inert', '');
    } else {
      region.setAttribute('aria-hidden', 'true');
    }
    region.setAttribute(MARK, '');
    const state: MarkState = { attr, observer: null, hostTouched: false };
    if (typeof MutationObserver === 'function') {
      // Observed after our own write: any later write of the attribute is the host's.
      state.observer = new MutationObserver(() => {
        state.hostTouched = true;
      });
      state.observer.observe(region, { attributes: true, attributeFilter: [attr] });
    }
    marked.set(region, state);
  };

  const unmark = (region: HTMLElement): void => {
    const state = marked.get(region);
    if (!state) {
      return;
    }
    if (state.observer) {
      if (state.observer.takeRecords().length > 0) {
        state.hostTouched = true;
      }
      state.observer.disconnect();
    }
    region.removeAttribute(MARK);
    if (!state.hostTouched) {
      region.removeAttribute(state.attr);
    }
    marked.delete(region);
  };

  const syncShell = (): void => {
    const owners = live()
      .filter((handle) => handle.inertShell)
      .map((handle) => handle.owner);
    const wanted = new Set<HTMLElement>();
    if (owners.length > 0) {
      document.querySelectorAll<HTMLElement>(SHELL).forEach((region) => {
        if (owners.some((owner) => region.contains(owner) || owner.contains(region))) {
          return;
        }
        wanted.add(region);
      });
    }
    [...marked.keys()].forEach((region) => {
      if (!wanted.has(region) || !region.isConnected) {
        unmark(region);
      }
    });
    wanted.forEach(mark);
  };

  const syncRoot = (): void => {
    const depth = live().length;
    if (depth > 0) {
      document.documentElement.setAttribute(ROOT_ATTR, String(depth));
    } else {
      document.documentElement.removeAttribute(ROOT_ATTR);
    }
  };

  const emit = (name: string, handle: OverlayHandle): void => {
    document.dispatchEvent(new CustomEvent<OverlayDetail>(name, { detail: { depth: live().length, kind: handle.kind, owner: handle.owner } }));
  };

  /** Drops handles whose owner left the DOM (no stuck shell); emits their close. */
  const prune = (): void => {
    for (let i = handles.length - 1; i >= 0; i -= 1) {
      const handle = handles[i];
      if (handle && !handle.owner.isConnected) {
        handles.splice(i, 1);
        emit(CLOSE_EVENT, handle);
      }
    }
  };

  const watchRemovals = (): void => {
    if (handles.length > 0 && removals === null && typeof MutationObserver === 'function') {
      removals = new MutationObserver(() => {
        if (handles.some((handle) => !handle.owner.isConnected)) {
          prune();
          apply();
        }
      });
      removals.observe(document.documentElement, { childList: true, subtree: true });
    } else if (handles.length === 0 && removals !== null) {
      removals.disconnect();
      removals = null;
    }
  };

  const apply = (): void => {
    syncShell();
    syncRoot();
    watchRemovals();
  };

  const registry: OverlayRegistry = {
    version: 1,
    SHELL,
    acquire(owner: Element, options: OverlayOptions = {}): OverlayHandle {
      prune();
      const kind: OverlayKind = options.kind && KINDS.includes(options.kind) ? options.kind : 'host';
      if (handles.length === 0) {
        savedFocus = document.activeElement;
      }
      let released = false;
      const handle: OverlayHandle = {
        kind,
        owner,
        closeOnBackdrop: options.closeOnBackdrop ?? closeOnBackdropDefault(kind),
        inertShell: options.inertShell ?? true,
        active(): boolean {
          return !released && handles.includes(handle);
        },
        release(): void {
          if (released) {
            return;
          }
          released = true;
          const index = handles.indexOf(handle);
          if (index !== -1) {
            handles.splice(index, 1);
          }
          prune();
          apply();
          emit(CLOSE_EVENT, handle);
          if (handles.length === 0) {
            const focus = savedFocus;
            savedFocus = null;
            // Owners restore focus to their opener first; only fill in when focus fell to nothing.
            const active = document.activeElement;
            if ((active === null || active === document.body) && focus instanceof HTMLElement && focus.isConnected) {
              focus.focus();
            }
          }
        },
      };
      handles.push(handle);
      apply();
      emit(OPEN_EVENT, handle);
      return handle;
    },
    depth(): number {
      return live().length;
    },
    closeOnBackdrop: closeOnBackdropDefault,
    sync(): void {
      prune();
      apply();
    },
    releaseAll(): void {
      [...handles].forEach((handle) => handle.release());
    },
  };

  return registry;
}

/** The page's shared registry (created by the first UiKit script that needs it). */
export function overlayRegistry(): OverlayRegistry {
  if (!window.NowoUiOverlay || window.NowoUiOverlay.version !== 1) {
    const registry = createOverlayRegistry();
    window.NowoUiOverlay = registry;
    window.addEventListener('pagehide', () => registry.releaseAll());
  }
  return window.NowoUiOverlay;
}

overlayRegistry();
