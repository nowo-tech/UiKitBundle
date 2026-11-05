import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import CollapsePanelController, { COLLAPSE_PANEL_STORAGE_DEFAULT } from './collapse_panel_controller';

const tick = (): Promise<void> => new Promise((r) => setTimeout(r, 0));

describe('collapse-panel peer', () => {
  let application: Application;

  const mount = async (html: string): Promise<HTMLElement> => {
    application?.stop();
    document.body.innerHTML = html;
    application = Application.start();
    application.register('collapse-panel', CollapsePanelController);
    await tick();
    return document.querySelector('[data-controller="collapse-panel"]') as HTMLElement;
  };

  const controllerFor = (el: HTMLElement): CollapsePanelController =>
    application.getControllerForElementAndIdentifier(el, 'collapse-panel') as CollapsePanelController;

  beforeEach(() => {
    localStorage.clear();
  });

  afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
    vi.restoreAllMocks();
    localStorage.clear();
  });

  it('starts collapsed from the server default and does not persist on connect', async () => {
    const root = await mount(`
      <div data-controller="collapse-panel" data-collapse-panel-id-value="raw" data-collapse-panel-collapsed-value="true">
        <button type="button" data-collapse-panel-target="button" data-action="collapse-panel#toggle"></button>
        <span data-collapse-panel-target="icon"></span>
        <div data-collapse-panel-target="body">content</div>
      </div>`);
    expect(root.classList.contains('is-collapsed')).toBe(true);
    expect((root.querySelector('[data-collapse-panel-target="body"]') as HTMLElement).hidden).toBe(true);
    expect(root.querySelector('[data-collapse-panel-target="icon"]')!.textContent).toBe('▸');
    expect(root.querySelector('button')!.getAttribute('aria-expanded')).toBe('false');
    expect(localStorage.getItem(COLLAPSE_PANEL_STORAGE_DEFAULT)).toBeNull();
  });

  it('toggles, persists under the storage key and dispatches an event', async () => {
    const root = await mount(`
      <div data-controller="collapse-panel" data-collapse-panel-id-value="raw" data-collapse-panel-collapsed-value="true"
           data-collapse-panel-storage-key-value="app.panels" data-collapse-panel-open-icon-value="-" data-collapse-panel-closed-icon-value="+">
        <button type="button" data-collapse-panel-target="button" data-action="collapse-panel#toggle"></button>
        <span data-collapse-panel-target="icon"></span>
        <div data-collapse-panel-target="body">content</div>
      </div>`);
    const events: unknown[] = [];
    root.addEventListener('collapse-panel:toggled', (e) => events.push((e as CustomEvent).detail));
    root.querySelector('button')!.click();
    expect(root.classList.contains('is-collapsed')).toBe(false);
    expect(root.querySelector('[data-collapse-panel-target="icon"]')!.textContent).toBe('-');
    expect(JSON.parse(localStorage.getItem('app.panels') ?? '{}')).toEqual({ raw: true });
    expect(events).toEqual([{ open: true, id: 'raw' }]);
  });

  it('restores the saved state over the server default and ignores invalid storage', async () => {
    localStorage.setItem(COLLAPSE_PANEL_STORAGE_DEFAULT, JSON.stringify({ details: false }));
    let root = await mount(`
      <div data-controller="collapse-panel" data-collapse-panel-id-value="details">
        <div data-collapse-panel-target="body">content</div>
      </div>`);
    expect(root.classList.contains('is-collapsed')).toBe(true);

    localStorage.setItem(COLLAPSE_PANEL_STORAGE_DEFAULT, '[1,2]');
    root = await mount(`
      <div data-controller="collapse-panel" data-collapse-panel-id-value="other">
        <div data-collapse-panel-target="body">content</div>
      </div>`);
    expect(root.classList.contains('is-collapsed')).toBe(false);

    localStorage.setItem(COLLAPSE_PANEL_STORAGE_DEFAULT, '{bad');
    root = await mount(`
      <div data-controller="collapse-panel" data-collapse-panel-id-value="other">
        <div data-collapse-panel-target="body">content</div>
      </div>`);
    expect(root.classList.contains('is-collapsed')).toBe(false);
  });

  it('toggles without an id (no persistence) and accepts an event', async () => {
    const root = await mount(`
      <div data-controller="collapse-panel"><div data-collapse-panel-target="body">content</div></div>`);
    const event = new Event('click', { cancelable: true });
    controllerFor(root).toggle(event);
    expect(event.defaultPrevented).toBe(true);
    expect(root.classList.contains('is-collapsed')).toBe(true);
    expect(localStorage.length).toBe(0);
  });

  it('tolerates localStorage read/write failures', async () => {
    // A getter spy, not Storage.prototype: happy-dom binds Storage methods on first access, so a prototype spy that
    // runs first (--sequence.shuffle, e.g. seed 3) was captured for every later test of this file and broke them.
    const blocked = {
      getItem: () => {
        throw new Error('blocked');
      },
      setItem: () => {
        throw new Error('quota');
      },
    } as unknown as Storage;
    const getter = vi.spyOn(window, 'localStorage', 'get').mockReturnValue(blocked);
    const root = await mount(`
      <div data-controller="collapse-panel" data-collapse-panel-id-value="x">
        <div data-collapse-panel-target="body">content</div>
      </div>`);
    expect(() => controllerFor(root).toggle()).not.toThrow();
    expect(root.classList.contains('is-collapsed')).toBe(true);
    expect(getter).toHaveBeenCalled();
  });
});
