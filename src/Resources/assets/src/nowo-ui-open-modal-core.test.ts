import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  clearQueryParam,
  isModalOpen,
  openModalOnLoad,
  readOpenModalConfig,
  tryOpenModal,
} from './nowo-ui-open-modal-core';

const island = (json: string): string =>
  `<script type="application/json" id="nowo-ui-open-modal-on-load">${json}</script>`;

const CONFIG = '{"modal":"m","trigger":"button.open","clearParam":"new"}';

describe('readOpenModalConfig', () => {
  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('returns null without the island', () => {
    expect(readOpenModalConfig(document)).toBeNull();
  });

  it('returns null on invalid JSON, non-objects and missing keys', () => {
    for (const json of ['{', 'null', '"x"', '{"modal":""}', '{"modal":1}', '{"trigger":"t"}']) {
      document.body.innerHTML = island(json);
      expect(readOpenModalConfig(document)).toBeNull();
    }
  });

  it('treats an empty island as invalid', () => {
    document.body.innerHTML = '<script type="application/json" id="nowo-ui-open-modal-on-load"></script>';
    expect(readOpenModalConfig(document)).toBeNull();
  });

  it('parses modal, trigger and optional clearParam', () => {
    document.body.innerHTML = island(CONFIG);
    expect(readOpenModalConfig(document)).toEqual({ modal: 'm', trigger: 'button.open', clearParam: 'new' });
    document.body.innerHTML = island('{"modal":"m","trigger":"t","clearParam":1}');
    expect(readOpenModalConfig(document)).toEqual({ modal: 'm', trigger: 't', clearParam: undefined });
    document.body.innerHTML = island('{"modal":"m","trigger":"","clearParam":""}');
    expect(readOpenModalConfig(document)).toEqual({ modal: 'm', trigger: undefined, clearParam: undefined });
  });

  it('accepts a custom island id', () => {
    document.body.innerHTML = '<script type="application/json" id="custom">{"modal":"x"}</script>';
    expect(readOpenModalConfig(document, 'custom')).toEqual({ modal: 'x', trigger: undefined, clearParam: undefined });
  });
});

describe('isModalOpen / tryOpenModal', () => {
  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('reads the open state from <dialog> and from the kit class', () => {
    const dialog = document.createElement('dialog');
    expect(isModalOpen(dialog)).toBe(false);
    dialog.setAttribute('open', '');
    expect(isModalOpen(dialog)).toBe(true);
    const div = document.createElement('div');
    expect(isModalOpen(div)).toBe(false);
    div.classList.add('nowo-ui-modal-open');
    expect(isModalOpen(div)).toBe(true);
  });

  it('waits while trigger or dialog are missing', () => {
    expect(tryOpenModal(document, { modal: 'm', trigger: 'button.open' })).toBe(false);
    document.body.innerHTML = '<button class="open"></button>';
    expect(tryOpenModal(document, { modal: 'm', trigger: 'button.open' })).toBe(false);
  });

  it('stops when the kit trigger already opened the modal', () => {
    document.body.innerHTML = '<button class="open"></button><div id="m"></div>';
    const dialog = document.getElementById('m') as HTMLElement;
    const trigger = document.querySelector('button.open') as HTMLElement;
    trigger.addEventListener('click', () => dialog.classList.add('nowo-ui-modal-open'));
    expect(tryOpenModal(document, { modal: 'm', trigger: 'button.open' })).toBe(true);
    // Already open: no second click.
    const spy = vi.fn();
    trigger.addEventListener('click', spy);
    expect(tryOpenModal(document, { modal: 'm', trigger: 'button.open' })).toBe(true);
    expect(spy).not.toHaveBeenCalled();
  });

  it('force-opens a <dialog> and dispatches show.bs.modal with the trigger', () => {
    document.body.innerHTML = '<button class="open"></button><dialog id="m" hidden aria-hidden="true"></dialog>';
    const dialog = document.getElementById('m') as HTMLDialogElement;
    dialog.showModal = function showModal(this: HTMLDialogElement): void {
      this.setAttribute('open', '');
    };
    const seen: unknown[] = [];
    dialog.addEventListener('show.bs.modal', (ev) => seen.push((ev as Event & { relatedTarget?: unknown }).relatedTarget));
    expect(tryOpenModal(document, { modal: 'm', trigger: 'button.open' })).toBe(true);
    expect(dialog.hasAttribute('hidden')).toBe(false);
    expect(dialog.getAttribute('aria-hidden')).toBe('false');
    expect(seen).toEqual([document.querySelector('button.open')]);
  });

  it('falls back to the open attribute when showModal throws', () => {
    document.body.innerHTML = '<button class="open"></button><dialog id="m"></dialog>';
    const dialog = document.getElementById('m') as HTMLDialogElement;
    dialog.showModal = () => {
      throw new Error('not connected');
    };
    expect(tryOpenModal(document, { modal: 'm', trigger: 'button.open' })).toBe(true);
    expect(dialog.open).toBe(true);
  });

  it('force-opens a non-dialog modal via the kit class', () => {
    document.body.innerHTML = '<button class="open"></button><div id="m" hidden></div>';
    expect(tryOpenModal(document, { modal: 'm', trigger: 'button.open' })).toBe(true);
    expect(document.getElementById('m')?.classList.contains('nowo-ui-modal-open')).toBe(true);
    expect(document.body.classList.contains('nowo-modal-open')).toBe(true);
    document.body.classList.remove('nowo-modal-open');
  });

  it('opens the modal directly without a trigger selector', () => {
    document.body.innerHTML = '<dialog id="m"></dialog>';
    const dialog = document.getElementById('m') as HTMLDialogElement;
    dialog.showModal = function showModal(this: HTMLDialogElement): void {
      this.setAttribute('open', '');
    };
    const seen: unknown[] = [];
    dialog.addEventListener('show.bs.modal', (ev) => seen.push((ev as Event & { relatedTarget?: unknown }).relatedTarget));
    expect(tryOpenModal(document, { modal: 'm' })).toBe(true);
    expect(seen).toEqual([null]);
    expect(document.body.classList.contains('nowo-modal-open')).toBe(false);
  });

  it('does not call showModal on an already open dialog after a no-op trigger', () => {
    document.body.innerHTML = '<button class="open"></button><dialog id="m"></dialog>';
    const dialog = document.getElementById('m') as HTMLDialogElement;
    const showModal = vi.fn();
    dialog.showModal = showModal;
    let calls = 0;
    Object.defineProperty(dialog, 'open', { get: () => (calls += 1) > 2 });
    expect(tryOpenModal(document, { modal: 'm', trigger: 'button.open' })).toBe(true);
    expect(showModal).not.toHaveBeenCalled();
  });
});

describe('clearQueryParam', () => {
  beforeEach(() => {
    window.history.replaceState(null, '', '/admin/items?new=1&page=2#top');
  });

  it('is a no-op without a param name or when the param is absent', () => {
    const spy = vi.spyOn(window.history, 'replaceState');
    clearQueryParam(window, undefined);
    clearQueryParam(window, 'missing');
    expect(spy).not.toHaveBeenCalled();
    spy.mockRestore();
  });

  it('drops the param and keeps the rest of the URL', () => {
    clearQueryParam(window, 'new');
    expect(window.location.pathname + window.location.search + window.location.hash).toBe('/admin/items?page=2#top');
  });

  it('drops the query string entirely when nothing is left', () => {
    window.history.replaceState(null, '', '/admin/items?new=1');
    clearQueryParam(window, 'new');
    expect(window.location.search).toBe('');
  });
});

describe('openModalOnLoad', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    window.history.replaceState(null, '', '/admin/items?new=1');
  });

  afterEach(() => {
    vi.useRealTimers();
    document.body.innerHTML = '';
  });

  it('does nothing without the island', () => {
    const spy = vi.spyOn(window, 'setInterval');
    openModalOnLoad();
    expect(spy).not.toHaveBeenCalled();
    spy.mockRestore();
  });

  it('retries until the kit markup exists, opens and clears the param', () => {
    document.body.innerHTML = island(CONFIG);
    openModalOnLoad(document, window, 40, 50);
    vi.advanceTimersByTime(50);
    expect(window.location.search).toBe('?new=1');
    document.body.insertAdjacentHTML('beforeend', '<button class="open"></button><div id="m"></div>');
    vi.advanceTimersByTime(50);
    expect(document.getElementById('m')?.classList.contains('nowo-ui-modal-open')).toBe(true);
    expect(window.location.search).toBe('');
  });

  it('gives up after maxTries and still clears the param', () => {
    document.body.innerHTML = island(CONFIG);
    openModalOnLoad(document, window, 2, 10);
    vi.advanceTimersByTime(30);
    expect(window.location.search).toBe('');
  });
});
