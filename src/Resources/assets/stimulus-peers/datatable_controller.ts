/**
 * Optional Stimulus peer (1.15) — responsive DataTables layout for server-rendered tables.
 *
 * Requires the OPTIONAL host dependencies `datatables.net-dt` and `datatables.net-responsive-dt`
 * (peer dependencies of the kit assets; not installed unless the host adds them). The stylesheets are
 * not imported here — the host loads them (`datatables.net-dt/css/dataTables.dataTables.min.css`,
 * `datatables.net-responsive-dt/css/responsive.dataTables.min.css`) or styles the table itself.
 *
 * Sorting, searching and paging stay server-side (filter form + query string): DataTables only collapses
 * overflowing columns into child rows. Responsive priorities come from `<th data-priority>`, alignment
 * from the markup. Put the controller on a stable wrapper, not on the <table>: DataTables moves the table
 * node on init, which would otherwise disconnect / reconnect the controller.
 *
 * Identifier: `datatable`
 * Targets: `table`.
 * Values: `options` (JSON object merged over the defaults, e.g. `{"responsive":{"details":{"type":"column"}}}`).
 * Actions: `datatable#stop` — stop propagation (links inside a row must not toggle the child row).
 */

import { Controller } from '@hotwired/stimulus';
import DataTable from 'datatables.net-dt';
import 'datatables.net-responsive-dt';

export const DATATABLE_DEFAULT_OPTIONS: Record<string, unknown> = {
  autoWidth: false,
  deferRender: true,
  scrollX: false,
  paging: false,
  searching: false,
  ordering: false,
  info: false,
  lengthChange: false,
  layout: {
    topStart: null,
    topEnd: null,
    bottomStart: null,
    bottomEnd: null,
  },
  responsive: {
    details: {
      type: 'inline',
    },
  },
};

interface DataTableInstance {
  destroy(remove?: boolean): unknown;
}

export default class extends Controller<HTMLElement> {
  static targets = ['table'];

  static values = {
    options: { type: Object, default: {} },
  };

  declare readonly tableTarget: HTMLTableElement;
  declare readonly hasTableTarget: boolean;
  declare readonly optionsValue: Record<string, unknown>;

  private table: DataTableInstance | null = null;
  private mountFrame: number | null = null;

  connect(): void {
    this.mountFrame = window.requestAnimationFrame(() => {
      this.mountFrame = null;
      this.mount();
    });
  }

  disconnect(): void {
    if (this.mountFrame !== null) {
      window.cancelAnimationFrame(this.mountFrame);
      this.mountFrame = null;
    }

    if (this.table) {
      this.table.destroy(false);
      this.table = null;
    }
  }

  /** Keep Responsive from toggling the child row when a link / button inside the row is clicked. */
  stop(event: Event): void {
    event.stopPropagation();
  }

  private mount(): void {
    if (!this.hasTableTarget || this.table) {
      return;
    }

    const element = this.tableTarget;
    if (!(element instanceof HTMLTableElement) || DataTable.isDataTable(element)) {
      return;
    }

    this.table = new DataTable(element, { ...DATATABLE_DEFAULT_OPTIONS, ...this.optionsValue }) as unknown as DataTableInstance;
  }
}
