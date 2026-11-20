/**
 * Table view runtime (2.61, partial `table_view`): [data-nowo-ui-table-view] controls for the table whose id is
 * data-nowo-ui-table-view-table. Shown by this script (the markup is `hidden` without it).
 * - Density: buttons [data-nowo-ui-density="comfortable|compact"] (aria-pressed) toggle `nowo-ui-data-table--compact`.
 * - Columns: buttons [data-nowo-ui-column="<key>"] (aria-pressed) hide / show the header cell with the same
 *   data-nowo-ui-column and the cell at the same position in every row (`hidden`); the last visible column cannot be
 *   hidden. Columns are positional: no colspan in the rows of such a table.
 * - data-nowo-ui-table-view-persist="<key>": the choice is kept in localStorage `nowo-ui-table-view:<key>` (browser
 *   only; every access in try / catch) and applied on load.
 */

const ROOT = '[data-nowo-ui-table-view]';
export const STORAGE_PREFIX = 'nowo-ui-table-view:';
const COMPACT = 'nowo-ui-data-table--compact';

interface ViewState {
  density: string;
  hidden: string[];
}

function tableOf(root: Element): HTMLTableElement | null {
  const el = document.getElementById(root.getAttribute('data-nowo-ui-table-view-table') ?? '');
  return el instanceof HTMLTableElement ? el : null;
}

function headerIndex(table: HTMLTableElement, key: string): number {
  const header = Array.from(table.querySelectorAll<HTMLTableCellElement>('th[data-nowo-ui-column]')).find(
    (th) => th.getAttribute('data-nowo-ui-column') === key,
  );
  return header ? header.cellIndex : -1;
}

/** Hides or shows one column (its header and the cell at the same index in every row). */
export function setColumn(table: HTMLTableElement, key: string, visible: boolean): void {
  const index = headerIndex(table, key);
  if (index < 0) {
    return;
  }
  Array.from(table.rows).forEach((row) => {
    const cell = row.cells[index];
    if (cell) {
      cell.hidden = !visible;
    }
  });
}

export function setDensity(root: Element, table: HTMLTableElement, density: string): void {
  const compact = density === 'compact';
  table.classList.toggle(COMPACT, compact);
  root.querySelectorAll('[data-nowo-ui-density]').forEach((button) => {
    button.setAttribute('aria-pressed', button.getAttribute('data-nowo-ui-density') === (compact ? 'compact' : 'comfortable') ? 'true' : 'false');
  });
}

function columnButtons(root: Element): HTMLButtonElement[] {
  return Array.from(root.querySelectorAll<HTMLButtonElement>('button[data-nowo-ui-column]'));
}

function storageKey(root: Element): string | null {
  const key = root.getAttribute('data-nowo-ui-table-view-persist');
  return key ? STORAGE_PREFIX + key : null;
}

function save(root: Element, table: HTMLTableElement): void {
  const key = storageKey(root);
  if (!key) {
    return;
  }
  const state: ViewState = {
    density: table.classList.contains(COMPACT) ? 'compact' : 'comfortable',
    hidden: columnButtons(root)
      .filter((b) => b.getAttribute('aria-pressed') === 'false')
      .map((b) => b.getAttribute('data-nowo-ui-column') ?? ''),
  };
  try {
    window.localStorage.setItem(key, JSON.stringify(state));
  } catch {
    // Storage blocked (private mode, quota): the choice lasts for this page only.
  }
}

export function load(root: Element): ViewState | null {
  const key = storageKey(root);
  if (!key) {
    return null;
  }
  try {
    const raw = window.localStorage.getItem(key);
    const parsed: unknown = raw ? JSON.parse(raw) : null;
    if (parsed && typeof parsed === 'object' && Array.isArray((parsed as ViewState).hidden)) {
      return { density: String((parsed as ViewState).density), hidden: (parsed as ViewState).hidden.map(String) };
    }
  } catch {
    // Unreadable or blocked storage: the server-rendered view stays.
  }
  return null;
}

function applyColumns(root: Element, table: HTMLTableElement): void {
  columnButtons(root).forEach((button) => {
    setColumn(table, button.getAttribute('data-nowo-ui-column') ?? '', button.getAttribute('aria-pressed') !== 'false');
  });
}

export function upgradeTableViews(scope: ParentNode = document): void {
  scope.querySelectorAll<HTMLElement>(ROOT).forEach((root) => {
    const table = tableOf(root);
    if (!table || root.hasAttribute('data-nowo-ui-table-view-ready')) {
      return;
    }
    const stored = load(root);
    if (stored) {
      columnButtons(root).forEach((button) => {
        if (!button.disabled) {
          button.setAttribute('aria-pressed', stored.hidden.includes(button.getAttribute('data-nowo-ui-column') ?? '') ? 'false' : 'true');
        }
      });
      setDensity(root, table, stored.density);
    } else {
      const pressed = root.querySelector('[data-nowo-ui-density][aria-pressed="true"]');
      setDensity(root, table, pressed?.getAttribute('data-nowo-ui-density') ?? 'comfortable');
    }
    applyColumns(root, table);
    root.hidden = false;
    root.setAttribute('data-nowo-ui-table-view-ready', '');
  });
}

function onClick(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) {
    return;
  }
  const root = target.closest(ROOT);
  const table = root ? tableOf(root) : null;
  if (!root || !table) {
    return;
  }
  const density = target.closest('[data-nowo-ui-density]');
  if (density) {
    setDensity(root, table, density.getAttribute('data-nowo-ui-density') ?? 'comfortable');
    save(root, table);
    return;
  }
  const column = target.closest('button[data-nowo-ui-column]');
  if (column instanceof HTMLButtonElement && !column.disabled) {
    const show = column.getAttribute('aria-pressed') === 'false';
    const visible = columnButtons(root).filter((b) => b.getAttribute('aria-pressed') !== 'false').length;
    if (!show && visible <= 1) {
      return;
    }
    column.setAttribute('aria-pressed', show ? 'true' : 'false');
    setColumn(table, column.getAttribute('data-nowo-ui-column') ?? '', show);
    save(root, table);
  }
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key !== 'Escape' || !(event.target instanceof Element)) {
    return;
  }
  const menu = event.target.closest<HTMLDetailsElement>('[data-nowo-ui-table-view-columns][open]');
  if (menu) {
    menu.open = false;
    menu.querySelector<HTMLElement>('summary')?.focus();
  }
}

let bound = false;

export function bindNowoUiTableView(): void {
  upgradeTableViews();
  if (bound) {
    return;
  }
  bound = true;
  document.addEventListener('click', onClick);
  document.addEventListener('keydown', onKeydown);
}

bindNowoUiTableView();
