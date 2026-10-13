import { Application } from '@hotwired/stimulus';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { DataTable, destroyMock, isDataTable, dtCalls } = vi.hoisted(() => {
  const destroyMock = vi.fn();
  const isDataTable = vi.fn(() => false);
  const dtCalls: unknown[][] = [];
  class DataTable {
    static isDataTable = isDataTable;
    destroy = destroyMock;
    constructor(...args: unknown[]) {
      dtCalls.push(args);
    }
  }
  return { DataTable, destroyMock, isDataTable, dtCalls };
});

vi.mock('datatables.net-dt', () => ({ default: DataTable }));
vi.mock('datatables.net-responsive-dt', () => ({}));

import DatatableController, { DATATABLE_DEFAULT_OPTIONS } from './datatable_controller';

const tick = (): Promise<void> => new Promise((r) => setTimeout(r, 0));

describe('datatable peer', () => {
  let application: Application;

  const mount = async (html: string): Promise<HTMLElement> => {
    application?.stop();
    document.body.innerHTML = html;
    application = Application.start();
    application.register('datatable', DatatableController);
    await tick();
    return document.querySelector('[data-controller="datatable"]') as HTMLElement;
  };

  const controllerFor = (el: HTMLElement): DatatableController =>
    application.getControllerForElementAndIdentifier(el, 'datatable') as DatatableController;

  const TABLE = `
    <div data-controller="datatable" data-datatable-options-value='{"responsive":{"details":{"type":"column"}}}'>
      <table data-datatable-target="table">
        <thead><tr><th>A</th></tr></thead>
        <tbody><tr><td><a href="/i/1" data-action="datatable#stop">Item</a></td></tr></tbody>
      </table>
    </div>`;

  beforeEach(() => {
    dtCalls.length = 0;
    destroyMock.mockClear();
    isDataTable.mockReturnValue(false);
    vi.stubGlobal('requestAnimationFrame', (cb: FrameRequestCallback) => {
      cb(0);
      return 1;
    });
    vi.stubGlobal('cancelAnimationFrame', vi.fn());
  });

  afterEach(() => {
    application.stop();
    document.body.innerHTML = '';
    vi.unstubAllGlobals();
  });

  it('mounts DataTables after rAF with server-side defaults merged with options, destroys on disconnect', async () => {
    const el = await mount(TABLE);
    expect(dtCalls).toHaveLength(1);
    const options = dtCalls[0][1] as Record<string, unknown>;
    expect(options.paging).toBe(false);
    expect(options.ordering).toBe(false);
    expect(options.searching).toBe(false);
    expect(options.responsive).toEqual({ details: { type: 'column' } });
    expect(DATATABLE_DEFAULT_OPTIONS.responsive).toEqual({ details: { type: 'inline' } });

    controllerFor(el).disconnect();
    expect(destroyMock).toHaveBeenCalledWith(false);
  });

  it('stops propagation on the stop action', async () => {
    const el = await mount(TABLE);
    const event = new Event('click', { bubbles: true, cancelable: true });
    const stop = vi.spyOn(event, 'stopPropagation');
    controllerFor(el).stop(event);
    expect(stop).toHaveBeenCalled();
  });

  it('skips the mount when the table is already a DataTable or not a table', async () => {
    isDataTable.mockReturnValue(true);
    await mount(TABLE);
    expect(dtCalls).toHaveLength(0);

    isDataTable.mockReturnValue(false);
    await mount(`<div data-controller="datatable"><div data-datatable-target="table"></div></div>`);
    expect(dtCalls).toHaveLength(0);
  });

  it('cancels a pending mount frame on early disconnect and skips without a table', async () => {
    vi.stubGlobal('requestAnimationFrame', () => 42);
    const cancel = vi.fn();
    vi.stubGlobal('cancelAnimationFrame', cancel);
    const el = await mount(`<div data-controller="datatable"></div>`);
    controllerFor(el).disconnect();
    expect(cancel).toHaveBeenCalledWith(42);

    vi.stubGlobal('requestAnimationFrame', (cb: FrameRequestCallback) => {
      cb(0);
      return 1;
    });
    await mount(`<div data-controller="datatable"></div>`);
    expect(dtCalls).toHaveLength(0);
  });
});
