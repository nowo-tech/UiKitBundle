import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import * as mod from './nowo-ui-context-menu';
import { openerCandidates, rememberOpener, resetFocusReturn } from './nowo-ui-focus-return';

const PANEL_W = 160;
const PANEL_H = 120;
let widthDescriptor: PropertyDescriptor | undefined;
let heightDescriptor: PropertyDescriptor | undefined;
const hadPopoverApi = typeof HTMLElement.prototype.showPopover === 'function';

beforeAll(() => {
  if (!hadPopoverApi) {
    // happy-dom has no Popover API: a minimal one (the open state as an attribute).
    HTMLElement.prototype.showPopover = function showPopover(this: HTMLElement) {
      this.setAttribute('data-test-popover-open', '');
    };
    HTMLElement.prototype.hidePopover = function hidePopover(this: HTMLElement) {
      this.removeAttribute('data-test-popover-open');
    };
  }
  widthDescriptor = Object.getOwnPropertyDescriptor(HTMLElement.prototype, 'offsetWidth');
  heightDescriptor = Object.getOwnPropertyDescriptor(HTMLElement.prototype, 'offsetHeight');
  Object.defineProperty(HTMLElement.prototype, 'offsetWidth', {
    configurable: true,
    get(this: HTMLElement) {
      return this.classList.contains('nowo-ui-context-menu') ? PANEL_W : 0;
    },
  });
  Object.defineProperty(HTMLElement.prototype, 'offsetHeight', {
    configurable: true,
    get(this: HTMLElement) {
      return this.classList.contains('nowo-ui-context-menu') ? PANEL_H : 0;
    },
  });
});

afterAll(() => {
  if (!hadPopoverApi) {
    // @ts-expect-error remove the test stub
    delete HTMLElement.prototype.showPopover;
    // @ts-expect-error remove the test stub
    delete HTMLElement.prototype.hidePopover;
  }
  if (widthDescriptor) {
    Object.defineProperty(HTMLElement.prototype, 'offsetWidth', widthDescriptor);
  }
  if (heightDescriptor) {
    Object.defineProperty(HTMLElement.prototype, 'offsetHeight', heightDescriptor);
  }
});

function rect(el: Element, box: { left: number; top: number; width: number; height: number }): void {
  (el as HTMLElement).getBoundingClientRect = () =>
    ({ ...box, right: box.left + box.width, bottom: box.top + box.height, x: box.left, y: box.top, toJSON: () => ({}) }) as DOMRect;
}

function contextmenu(target: Element, init: MouseEventInit = {}): MouseEvent {
  const event = new MouseEvent('contextmenu', { bubbles: true, cancelable: true, clientX: 50, clientY: 60, button: 2, ...init });
  target.dispatchEvent(event);
  return event;
}

function key(target: Element, k: string, init: KeyboardEventInit = {}): KeyboardEvent {
  const event = new KeyboardEvent('keydown', { key: k, bubbles: true, cancelable: true, ...init });
  target.dispatchEvent(event);
  return event;
}

function pointer(type: string, target: Element, init: PointerEventInit = {}): PointerEvent {
  const event = new PointerEvent(type, { bubbles: true, cancelable: true, pointerId: 7, pointerType: 'touch', isPrimary: true, clientX: 40, clientY: 30, ...init });
  target.dispatchEvent(event);
  return event;
}

function menu(): HTMLElement {
  return mod.openPanels()[0]!;
}

function items(panel: HTMLElement = menu()): HTMLElement[] {
  return [...panel.querySelectorAll<HTMLElement>('[role="menuitem"]')];
}

function labels(panel: HTMLElement = menu()): string[] {
  return items(panel).map((item) => item.textContent ?? '');
}

const ROWS = `
  <table>
    <tbody>
      <tr id="r1" tabindex="0">
        <td id="c1">Invoice #1042 <a id="row-link" href="/invoices/1042">open</a> <input id="field"></td>
        <td>
          <div class="nowo-ui-kebab" data-nowo-ui-kebab data-nowo-ui-popover-menu data-nowo-ui-context-menu-source data-nowo-ui-context-menu-label="Actions">
            <button type="button" id="k1-toggle" class="nowo-ui-kebab__summary" popovertarget="k1" aria-label="More actions" data-nowo-ui-popover-trigger>⋮</button>
            <div class="nowo-ui-kebab__menu" id="k1" role="menu" popover data-nowo-ui-popover-panel>
              <a id="edit" class="nowo-ui-kebab__item" href="#edit" role="menuitem"><svg id="ico" class="nowo-ui-icon"></svg>Edit</a>
              <a id="dup" class="nowo-ui-kebab__item" href="#dup" role="menuitem">Duplicate</a>
              <a id="arch" class="nowo-ui-kebab__item is-disabled" href="#arch" role="menuitem" aria-disabled="true">Archive</a>
              <a id="del" class="nowo-ui-kebab__item nowo-ui-kebab__item--danger" href="#del" role="menuitem">Delete</a>
            </div>
          </div>
        </td>
      </tr>
      <tr id="r2">
        <td id="c2">Invoice #1041</td>
        <td>
          <div class="nowo-ui-row-actions" data-nowo-ui-context-menu-source>
            <a id="view2" class="btn" href="#view2" aria-label="View"><i class="bi bi-eye"></i></a>
            <form method="post" action="#"><input type="hidden" name="_token" value="t"><button id="copy2" type="submit" class="btn">Copy</button></form>
            <button id="confirm2" type="button" class="btn btn-danger" data-nowo-confirm-toggle>Delete</button>
            <dialog id="d2"><button id="inner" type="submit">OK</button></dialog>
          </div>
        </td>
      </tr>
      <tr id="r3"><td id="c3">No menu here</td></tr>
    </tbody>
  </table>
`;

describe('nowo-ui-context-menu', () => {
  let opens: CustomEvent[];
  let closes: CustomEvent[];
  const onOpen = (event: Event): void => {
    opens.push(event as CustomEvent);
  };
  const onClose = (event: Event): void => {
    closes.push(event as CustomEvent);
  };

  beforeEach(() => {
    document.body.innerHTML = ROWS;
    vi.stubGlobal('innerWidth', 800);
    vi.stubGlobal('innerHeight', 600);
    opens = [];
    closes = [];
    document.addEventListener('nowo-ui:context-menu:open', onOpen);
    document.addEventListener('nowo-ui:context-menu:close', onClose);
    document.querySelectorAll('a[href]').forEach((a) => a.addEventListener('click', (event) => event.preventDefault()));
    document.querySelectorAll('form').forEach((form) => form.addEventListener('submit', (event) => event.preventDefault()));
    mod.bindNowoUiContextMenu();
  });

  afterEach(() => {
    mod.unbindNowoUiContextMenu();
    document.removeEventListener('nowo-ui:context-menu:open', onOpen);
    document.removeEventListener('nowo-ui:context-menu:close', onClose);
    vi.useRealTimers();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    resetFocusReturn();
    document.body.innerHTML = '';
  });

  describe('host resolution', () => {
    it('finds the scope of a marked source: the row, not the cell or a row without a source', () => {
      const resolved = mod.resolveHost(document.getElementById('c1'));
      expect(resolved?.host.id).toBe('r1');
      expect(resolved?.source.matches('[data-nowo-ui-kebab]')).toBe(true);
      expect(mod.resolveHost(document.getElementById('c2'))?.source.classList.contains('nowo-ui-row-actions')).toBe(true);
      expect(mod.resolveHost(document.getElementById('c3'))).toBeNull();
      expect(mod.resolveHost(null)).toBeNull();
    });

    it('takes the innermost scope: a row inside a card keeps its own kebab, the card header its own', () => {
      document.body.innerHTML = `
        <section data-nowo-ui-card id="card">
          <header id="head"><div data-nowo-ui-menu data-nowo-ui-context-menu-source id="card-menu"><ul class="nowo-ui-menu__panel"><li><a href="#c">Card item</a></li></ul></div></header>
          <table><tr id="row"><td id="cell">x</td><td><div data-nowo-ui-kebab data-nowo-ui-context-menu-source id="row-menu"><div class="nowo-ui-kebab__menu"><a href="#r">Row item</a></div></div></td></tr></table>
        </section>`;
      expect(mod.resolveHost(document.getElementById('cell'))?.source.id).toBe('row-menu');
      expect(mod.resolveHost(document.getElementById('head'))?.source.id).toBe('card-menu');
      expect(mod.scopeOf(document.getElementById('row-menu')!)?.id).toBe('row');
    });

    it('explicit attribute: empty = first source inside, an id (or #id, or a panel id), off, or a missing id', () => {
      document.body.innerHTML = `
        <article id="a1" data-nowo-ui-context-menu><p id="p1">x</p><div data-nowo-ui-kebab id="k"><div class="nowo-ui-kebab__menu" id="kp"><a href="#e">E</a></div></div></article>
        <article id="a2" data-nowo-ui-context-menu="#k"><p id="p2">x</p></article>
        <article id="a3" data-nowo-ui-context-menu="kp"><p id="p3">x</p></article>
        <article id="a4" data-nowo-ui-context-menu="off"><p id="p4">x</p></article>
        <article id="a5" data-nowo-ui-context-menu="missing"><p id="p5">x</p></article>
        <article id="a6" data-nowo-ui-context-menu="wrap"><p id="p6">x</p></article>
        <div id="wrap"><div data-nowo-ui-menu id="m6"><ul class="nowo-ui-menu__panel"><li><a href="#m">M</a></li></ul></div></div>
        <article id="a7" data-nowo-ui-context-menu="plain"><p id="p7">x</p></article>
        <ul id="plain"><li><a href="#pl">Plain</a></li></ul>
        <article id="a8" data-nowo-ui-context-menu><p id="p8">nothing inside</p></article>`;
      expect(mod.resolveHost(document.getElementById('p1'))?.source.id).toBe('k');
      expect(mod.resolveHost(document.getElementById('p2'))?.source.id).toBe('k');
      expect(mod.resolveHost(document.getElementById('p3'))?.source.id).toBe('k');
      expect(mod.resolveHost(document.getElementById('p4'))).toBeNull();
      expect(mod.resolveHost(document.getElementById('p5'))).toBeNull();
      // Only a kebab / menu / row-actions root or a panel inside one (audit SK-L2): not a wrapper, not any element.
      expect(mod.resolveHost(document.getElementById('p6'))).toBeNull();
      expect(mod.resolveHost(document.getElementById('p7'))).toBeNull();
      expect(mod.resolveHost(document.getElementById('p8'))).toBeNull();
    });

    it('never resolves inside an open context menu', () => {
      contextmenu(document.getElementById('c1')!);
      expect(mod.resolveHost(items()[0]!)).toBeNull();
    });
  });

  describe('items', () => {
    it('mirrors the kebab: labels, danger, disabled, icon; never the toggle', () => {
      const list = mod.collectEntries(document.querySelector<HTMLElement>('[data-nowo-ui-kebab]')!);
      expect(list.map((entry) => (entry.kind === 'item' ? entry.label : entry.kind))).toEqual(['Edit', 'Duplicate', 'Archive', 'Delete']);
      const [edit, , archive, del] = list as Extract<mod.Entry, { kind: 'item' }>[];
      expect(edit!.icon?.id).toBe('ico');
      expect(archive!.disabled).toBe(true);
      expect(del!.danger).toBe(true);
      expect(edit!.danger).toBe(false);
    });

    it('mirrors a row-actions cluster: aria-label, form submit, confirm toggle; never dialog content', () => {
      const list = mod.collectEntries(document.querySelector<HTMLElement>('.nowo-ui-row-actions')!);
      expect(list.map((entry) => (entry.kind === 'item' ? entry.original.id : entry.kind))).toEqual(['view2', 'copy2', 'confirm2']);
      expect((list[0] as { label: string }).label).toBe('View');
      expect((list[2] as { danger: boolean }).danger).toBe(true);
    });

    it('nested lists and nested menus become submenus; separators collapse; empty labels and hidden entries are skipped', () => {
      document.body.innerHTML = `
        <div id="src" data-nowo-ui-menu>
          <ul class="nowo-ui-menu__panel">
            <li role="separator"></li>
            <li><a href="#a">Alpha</a></li>
            <li><hr></li><li><hr></li>
            <li><span>Move to</span><ul><li><a href="#todo">To do</a></li><li><button type="button" disabled>Done</button></li></ul></li>
            <li><span>Empty</span><ul><li hidden><a href="#h">Hidden</a></li></ul></li>
            <li><details data-nowo-ui-menu aria-label="Share"><summary>Share…</summary><ul class="nowo-ui-menu__panel"><li><a href="#mail">Mail</a></li></ul></details></li>
            <li><details data-nowo-ui-menu><summary>Toggle only</summary></details></li>
            <li><div data-nowo-ui-popover-menu><button type="button" data-nowo-ui-popover-trigger>Export</button><ul data-nowo-ui-popover-panel popover><li><a href="#csv">CSV</a></li></ul></div></li>
            <li><a href="#blank"> </a></li>
            <li><input type="submit" value="Save"></li>
            <li><hr></li>
          </ul>
        </div>`;
      const list = mod.collectEntries(document.getElementById('src')!);
      expect(list.map((entry) => entry.kind)).toEqual(['item', 'separator', 'submenu', 'submenu', 'submenu', 'item']);
      const move = list[2] as Extract<mod.Entry, { kind: 'submenu' }>;
      expect(move.label).toBe('Move to');
      expect(move.children.map((entry) => (entry as { label: string }).label)).toEqual(['To do', 'Done']);
      expect((move.children[1] as { disabled: boolean }).disabled).toBe(true);
      expect((list[3] as { label: string }).label).toBe('Share');
      expect((list[4] as { label: string }).label).toBe('Export');
      expect((list[5] as { label: string }).label).toBe('Save');
      expect(mod.labelOf(document.createElement('span'))).toBe('');
      const titled = document.createElement('button');
      titled.title = 'By title';
      expect(mod.labelOf(titled)).toBe('By title');
    });

    it('never mirrors inert, aria-hidden or unrendered entries; :disabled and fieldset[disabled] are disabled (audit SK-L1)', () => {
      document.body.innerHTML = `
        <div id="src" data-nowo-ui-popover-menu>
          <ul class="nowo-ui-menu__panel" data-nowo-ui-popover-panel popover style="display: none">
            <li><a href="#ok">Shown</a></li>
            <li inert><a href="#inert">Inert</a></li>
            <li><a href="#inert2" inert>Inert item</a></li>
            <li aria-hidden="true"><a href="#ah">Aria hidden</a></li>
            <li style="display: none"><a href="#dn">Display none wrapper</a></li>
            <li><a href="#dn2" style="display: none">Display none item</a></li>
            <li><a href="#vh" style="visibility: hidden">Visibility hidden</a></li>
            <li><a href="#vc" style="visibility: collapse">Visibility collapse</a></li>
            <li><form><fieldset disabled><button type="submit" id="fs">In disabled fieldset</button></fieldset></form></li>
            <li><select disabled id="sel"></select><button type="button" id="dis" disabled>Disabled</button></li>
            <li><details data-nowo-ui-menu><summary>More</summary><ul class="nowo-ui-menu__panel" style="display: none"><li><a href="#n">Nested</a></li></ul></details></li>
          </ul>
        </div>`;
      const list = mod.collectEntries(document.getElementById('src')!);
      const labelsOf = (entries: mod.Entry[]): string[] => entries.map((entry) => (entry.kind === 'separator' ? '-' : entry.label));
      expect(labelsOf(list)).toEqual(['Shown', 'In disabled fieldset', 'Disabled', 'More']);
      expect((list[1] as { disabled: boolean }).disabled).toBe(true);
      expect((list[2] as { disabled: boolean }).disabled).toBe(true);
      expect((list[0] as { disabled: boolean }).disabled).toBe(false);
      expect(labelsOf((list[3] as { children: mod.Entry[] }).children)).toEqual(['Nested']);
    });

    it('a menu panel itself can be the source', () => {
      document.body.innerHTML = '<ul id="p" role="menu"><li><a href="#x">X</a></li></ul>';
      expect(mod.panelOf(document.getElementById('p')!).id).toBe('p');
    });
  });

  describe('pointer', () => {
    it('right-click opens a top-layer menu with the kebab items, ARIA menu roles, focus on the first item', () => {
      const event = contextmenu(document.getElementById('c1')!);
      expect(event.defaultPrevented).toBe(true);
      expect(mod.isContextMenuOpen()).toBe(true);
      const panel = menu();
      expect(panel.getAttribute('role')).toBe('menu');
      expect(panel.getAttribute('aria-label')).toBe('Actions');
      expect(panel.getAttribute('popover')).toBe('manual');
      expect(panel.parentElement).toBe(document.body);
      expect(labels()).toEqual(['Edit', 'Duplicate', 'Archive', 'Delete']);
      items().forEach((item) => {
        expect(item.getAttribute('tabindex')).toBe('-1');
        expect(item.parentElement?.getAttribute('role')).toBe('none');
        expect(item.getAttribute('type')).toBe('button');
      });
      expect(items()[2]!.getAttribute('aria-disabled')).toBe('true');
      expect(items()[3]!.classList.contains('nowo-ui-menu__item--danger')).toBe(true);
      expect(items()[0]!.querySelector('svg')?.getAttribute('aria-hidden')).toBe('true');
      expect(items()[0]!.querySelector('svg')?.hasAttribute('id')).toBe(false);
      expect(document.activeElement).toBe(items()[0]);
      expect(panel.style.left).toBe('50px');
      expect(panel.style.top).toBe('60px');
      expect(document.getElementById('r1')!.hasAttribute('data-nowo-ui-context-menu-open')).toBe(true);
      expect(opens).toHaveLength(1);
      expect(opens[0]!.detail.trigger).toBe('pointer');
      expect(opens[0]!.target).toBe(document.getElementById('r1'));
    });

    it('flips at the viewport edges and stays inside it', () => {
      contextmenu(document.getElementById('c1')!, { clientX: 790, clientY: 590 });
      expect(menu().style.left).toBe(`${790 - PANEL_W}px`);
      expect(menu().style.top).toBe(`${590 - PANEL_H}px`);
      mod.closeContextMenu(false);
      contextmenu(document.getElementById('c1')!, { clientX: 100, clientY: 100 });
      vi.stubGlobal('innerWidth', 120);
      mod.placeAtPoint(menu(), 100, 100);
      expect(menu().style.left).toBe('8px');
    });

    it('keeps the native menu: rows without a kebab, links, fields, Shift / Ctrl, selected text', () => {
      expect(contextmenu(document.getElementById('c3')!).defaultPrevented).toBe(false);
      expect(contextmenu(document.getElementById('row-link')!).defaultPrevented).toBe(false);
      expect(contextmenu(document.getElementById('field')!).defaultPrevented).toBe(false);
      expect(contextmenu(document.getElementById('c1')!, { shiftKey: true }).defaultPrevented).toBe(false);
      expect(contextmenu(document.getElementById('c1')!, { ctrlKey: true }).defaultPrevented).toBe(false);
      const selection = { isCollapsed: false, toString: () => 'Invoice' } as unknown as Selection;
      vi.spyOn(window, 'getSelection').mockReturnValue(selection);
      expect(contextmenu(document.getElementById('c1')!).defaultPrevented).toBe(false);
      expect(mod.isContextMenuOpen()).toBe(false);
      expect(mod.keepsNativeMenu(document.getElementById('c1'), {})).toBe(true);
      vi.spyOn(window, 'getSelection').mockReturnValue({ isCollapsed: false, toString: () => '  ' } as unknown as Selection);
      expect(mod.keepsNativeMenu(document.getElementById('c1'))).toBe(false);
      const editable = document.createElement('div');
      editable.setAttribute('contenteditable', '');
      expect(mod.keepsNativeMenu(editable)).toBe(true);
      editable.setAttribute('contenteditable', 'false');
      expect(mod.keepsNativeMenu(editable)).toBe(false);
    });

    it('a right-click a host handler already prevented is left to it', () => {
      const cell = document.getElementById('c1')!;
      cell.addEventListener('contextmenu', (event) => event.preventDefault(), { once: true });
      contextmenu(cell);
      expect(mod.isContextMenuOpen()).toBe(false);
    });

    it('a source without items, or a cancelled open event, keeps the native menu', () => {
      document.querySelectorAll('#k1 a').forEach((a) => a.remove());
      expect(contextmenu(document.getElementById('c1')!).defaultPrevented).toBe(false);
      const cancel = (event: Event): void => event.preventDefault();
      document.addEventListener('nowo-ui:context-menu:open', cancel);
      expect(contextmenu(document.getElementById('c2')!).defaultPrevented).toBe(false);
      document.removeEventListener('nowo-ui:context-menu:open', cancel);
      expect(mod.isContextMenuOpen()).toBe(false);
    });

    it('one menu at a time: a second right-click replaces it; kit popovers and <details> menus close', () => {
      const kebabPanel = document.getElementById('k1')!;
      const shown = vi.spyOn(kebabPanel, 'matches').mockImplementation((selector: string) => selector === ':popover-open');
      const hidden = vi.spyOn(kebabPanel, 'hidePopover').mockImplementation(() => {
        throw new Error('InvalidStateError');
      });
      const other = document.createElement('div');
      other.setAttribute('data-nowo-ui-popover-panel', '');
      vi.spyOn(other, 'matches').mockImplementation(() => {
        throw new Error('SyntaxError');
      });
      document.body.appendChild(other);
      document.body.insertAdjacentHTML('beforeend', '<details id="dm" data-nowo-ui-menu open><summary>m</summary></details>');
      contextmenu(document.getElementById('c1')!);
      expect(hidden).toHaveBeenCalledTimes(1);
      shown.mockRestore();
      expect(document.getElementById('dm')!.hasAttribute('open')).toBe(false);
      const first = menu();
      contextmenu(document.getElementById('c2')!);
      expect(first.isConnected).toBe(false);
      expect(document.querySelectorAll('.nowo-ui-context-menu')).toHaveLength(1);
      expect(labels()).toEqual(['View', 'Copy', 'Delete']);
      expect(closes).toHaveLength(1);
      expect(closes[0]!.target).toBe(document.getElementById('r1'));
    });

    it('a right-click inside the open menu shows no menu at all', () => {
      contextmenu(document.getElementById('c1')!);
      expect(contextmenu(items()[1]!).defaultPrevented).toBe(true);
      expect(mod.isContextMenuOpen()).toBe(true);
    });

    it('closes on an outside pointerdown, scroll, resize, window blur, Turbo cache, and another popover opening', () => {
      const reopen = (): void => {
        contextmenu(document.getElementById('c1')!);
        expect(mod.isContextMenuOpen()).toBe(true);
      };
      reopen();
      pointer('pointerdown', items()[0]!, { pointerType: 'mouse' });
      expect(mod.isContextMenuOpen()).toBe(true);
      pointer('pointerdown', document.getElementById('c3')!, { pointerType: 'mouse' });
      expect(mod.isContextMenuOpen()).toBe(false);
      reopen();
      menu().dispatchEvent(new Event('scroll'));
      expect(mod.isContextMenuOpen()).toBe(true);
      document.dispatchEvent(new Event('scroll'));
      expect(mod.isContextMenuOpen()).toBe(true);
      document.getElementById('c3')!.dispatchEvent(new Event('scroll'));
      expect(mod.isContextMenuOpen()).toBe(true);
      document.querySelector('table')!.dispatchEvent(new Event('scroll'));
      expect(mod.isContextMenuOpen()).toBe(false);
      reopen();
      vi.stubGlobal('scrollY', 40);
      document.dispatchEvent(new Event('scroll'));
      expect(mod.isContextMenuOpen()).toBe(false);
      vi.stubGlobal('scrollY', 0);
      reopen();
      window.dispatchEvent(new Event('resize'));
      expect(mod.isContextMenuOpen()).toBe(false);
      reopen();
      window.dispatchEvent(new Event('blur'));
      expect(mod.isContextMenuOpen()).toBe(false);
      reopen();
      document.dispatchEvent(new Event('turbo:before-cache'));
      expect(mod.isContextMenuOpen()).toBe(false);
      reopen();
      const own = new Event('toggle');
      Object.defineProperty(own, 'newState', { value: 'open' });
      menu().dispatchEvent(own);
      expect(mod.isContextMenuOpen()).toBe(true);
      const closed = new Event('toggle');
      Object.defineProperty(closed, 'newState', { value: 'closed' });
      document.getElementById('k1')!.dispatchEvent(closed);
      expect(mod.isContextMenuOpen()).toBe(true);
      const other = new Event('toggle');
      Object.defineProperty(other, 'newState', { value: 'open' });
      document.getElementById('k1')!.dispatchEvent(other);
      expect(mod.isContextMenuOpen()).toBe(false);
      expect(document.getElementById('r1')!.hasAttribute('data-nowo-ui-context-menu-open')).toBe(false);
    });

    it('clicking an item forwards the click to the kebab item and returns focus to the row', () => {
      const edit = document.getElementById('edit')!;
      const spy = vi.fn();
      edit.addEventListener('click', spy);
      contextmenu(document.getElementById('c1')!);
      items()[0]!.click();
      expect(spy).toHaveBeenCalledTimes(1);
      expect(mod.isContextMenuOpen()).toBe(false);
      expect(document.activeElement).toBe(document.getElementById('r1'));
    });

    it('a disabled item does nothing', () => {
      const spy = vi.fn();
      document.getElementById('arch')!.addEventListener('click', spy);
      contextmenu(document.getElementById('c1')!);
      items()[2]!.click();
      expect(spy).not.toHaveBeenCalled();
      expect(mod.isContextMenuOpen()).toBe(true);
      menu().click();
      expect(mod.isContextMenuOpen()).toBe(true);
    });

    it('forwards to a POST form submit button and a confirm toggle of a cluster', () => {
      const submit = vi.fn((event: Event) => event.preventDefault());
      document.querySelector('form')!.addEventListener('submit', submit);
      const confirm = vi.fn();
      document.getElementById('confirm2')!.addEventListener('click', confirm);
      contextmenu(document.getElementById('c2')!);
      items()[1]!.click();
      expect(submit).toHaveBeenCalledTimes(1);
      contextmenu(document.getElementById('c2')!);
      key(items()[0]!, 'End');
      key(document.activeElement!, 'Enter');
      expect(confirm).toHaveBeenCalledTimes(1);
    });

    it('a dialog opened by the forwarded item returns focus to the row (focus-return chain)', () => {
      const dialog = document.createElement('dialog');
      document.body.appendChild(dialog);
      document.getElementById('dup')!.addEventListener('click', () => rememberOpener(dialog));
      contextmenu(document.getElementById('c1')!);
      items()[1]!.click();
      expect(openerCandidates(dialog)[0]).toBe(document.getElementById('r1'));
    });

    it('a row that cannot take focus returns it to the kebab toggle', () => {
      document.getElementById('r1')!.removeAttribute('tabindex');
      contextmenu(document.getElementById('c1')!);
      key(items()[0]!, 'Escape');
      expect(document.activeElement).toBe(document.getElementById('k1-toggle'));
    });

    it('opens inside an open modal dialog, so the menu is not inert', () => {
      document.body.innerHTML = `<dialog id="dlg" open><div data-nowo-ui-context-menu id="h"><p id="t">x</p><div data-nowo-ui-kebab><div class="nowo-ui-kebab__menu"><a href="#a">A</a></div></div></div></dialog>`;
      contextmenu(document.getElementById('t')!);
      expect(menu().parentElement?.id).toBe('dlg');
    });

    it('without the Popover API the panel is a fixed, visible element', () => {
      const show = HTMLElement.prototype.showPopover;
      // @ts-expect-error simulate an engine without the Popover API
      delete HTMLElement.prototype.showPopover;
      try {
        contextmenu(document.getElementById('c1')!);
        expect(menu().hasAttribute('popover')).toBe(false);
        expect(menu().classList.contains('nowo-ui-context-menu--fallback')).toBe(true);
        expect(menu().hidden).toBe(false);
        const panel = menu();
        mod.closeContextMenu();
        expect(panel.isConnected).toBe(false);
      } finally {
        HTMLElement.prototype.showPopover = show;
      }
    });

    it('a failing showPopover falls back too', () => {
      vi.spyOn(HTMLElement.prototype, 'showPopover').mockImplementation(() => {
        throw new Error('NotSupportedError');
      });
      contextmenu(document.getElementById('c1')!);
      expect(menu().classList.contains('nowo-ui-context-menu--fallback')).toBe(true);
    });

    it('a panel the browser already hid is still removed', () => {
      contextmenu(document.getElementById('c1')!);
      const panel = menu();
      vi.spyOn(panel, 'hidePopover').mockImplementation(() => {
        throw new Error('InvalidStateError');
      });
      mod.closeContextMenu(false);
      expect(panel.isConnected).toBe(false);
    });

    it('closeContextMenu without an open menu is a no-op; binding twice is a no-op', () => {
      mod.closeContextMenu();
      mod.bindNowoUiContextMenu();
      contextmenu(document.getElementById('c1')!);
      expect(opens).toHaveLength(1);
      expect(mod.openPanels()).toHaveLength(1);
    });
  });

  describe('keyboard', () => {
    it('the ContextMenu key opens the menu under the focused element and swallows the browser contextmenu that follows', () => {
      const row = document.getElementById('r1')!;
      rect(row, { left: 20, top: 100, width: 600, height: 30 });
      row.focus();
      const event = key(row, 'ContextMenu');
      expect(event.defaultPrevented).toBe(true);
      expect(mod.isContextMenuOpen()).toBe(true);
      expect(opens[0]!.detail.trigger).toBe('keyboard');
      expect(menu().style.left).toBe('20px');
      expect(menu().style.top).toBe(`${130 + 4}px`);
      expect(contextmenu(row, { clientX: 0, clientY: 0, button: 0 }).defaultPrevented).toBe(true);
      expect(opens).toHaveLength(1);
      key(items()[0]!, 'Escape');
      expect(mod.isContextMenuOpen()).toBe(false);
      expect(document.activeElement).toBe(row);
    });

    it('Shift+F10 opens it too; plain F10, other keys, prevented keys, links and fields do not', () => {
      const row = document.getElementById('r1')!;
      row.focus();
      key(row, 'F10');
      key(row, 'a');
      expect(mod.isContextMenuOpen()).toBe(false);
      const prevented = new KeyboardEvent('keydown', { key: 'ContextMenu', bubbles: true, cancelable: true });
      prevented.preventDefault();
      row.dispatchEvent(prevented);
      expect(mod.isContextMenuOpen()).toBe(false);
      const link = document.getElementById('row-link')!;
      link.focus();
      expect(key(link, 'ContextMenu').defaultPrevented).toBe(false);
      document.getElementById('c3')!.setAttribute('tabindex', '0');
      document.getElementById('c3')!.focus();
      expect(key(document.getElementById('c3')!, 'ContextMenu').defaultPrevented).toBe(false);
      (document.activeElement as HTMLElement).blur();
      expect(key(document.body, 'ContextMenu').defaultPrevented).toBe(false);
      row.focus();
      key(row, 'F10', { shiftKey: true });
      expect(mod.isContextMenuOpen()).toBe(true);
      expect(key(items()[0]!, 'ContextMenu').defaultPrevented).toBe(false);
    });

    it('opens above the element without room below', () => {
      const row = document.getElementById('r1')!;
      rect(row, { left: 20, top: 560, width: 100, height: 30 });
      row.focus();
      key(row, 'ContextMenu');
      expect(menu().style.top).toBe(`${560 - 4 - PANEL_H}px`);
    });

    it('a keyboard contextmenu event (no pointer position) anchors to its target', () => {
      const cell = document.getElementById('c1')!;
      rect(cell, { left: 30, top: 50, width: 100, height: 20 });
      contextmenu(cell, { clientX: 0, clientY: 0, button: 0 });
      expect(opens[0]!.detail.trigger).toBe('keyboard');
      expect(menu().style.left).toBe('30px');
    });

    it('arrow keys, Home and End move a wrapping roving focus', () => {
      contextmenu(document.getElementById('c1')!);
      const list = items();
      key(list[0]!, 'ArrowDown');
      expect(document.activeElement).toBe(list[1]);
      key(list[1]!, 'End');
      expect(document.activeElement).toBe(list[3]);
      key(list[3]!, 'ArrowDown');
      expect(document.activeElement).toBe(list[0]);
      key(list[0]!, 'ArrowUp');
      expect(document.activeElement).toBe(list[3]);
      key(list[3]!, 'Home');
      expect(document.activeElement).toBe(list[0]);
      menu().focus();
      key(menu(), 'ArrowDown');
      expect(document.activeElement).toBe(list[0]);
      menu().focus();
      key(menu(), 'ArrowUp');
      expect(document.activeElement).toBe(list[3]);
      key(list[3]!, 'ArrowRight');
      key(list[3]!, 'ArrowLeft');
      expect(mod.isContextMenuOpen()).toBe(true);
    });

    it('typeahead: a letter jumps to the next item starting with it, repeated letters cycle, words match', () => {
      vi.useFakeTimers();
      document.querySelector('#k1')!.insertAdjacentHTML('beforeend', '<a href="#d2" role="menuitem">Download</a>');
      contextmenu(document.getElementById('c1')!);
      const list = items();
      key(list[0]!, 'd');
      expect(document.activeElement).toBe(list[1]);
      key(list[1]!, 'd');
      expect(document.activeElement).toBe(list[3]);
      vi.advanceTimersByTime(mod.TYPEAHEAD_MS + 1);
      key(list[3]!, 'D');
      key(list[3]!, 'o');
      expect(document.activeElement).toBe(list[4]);
      vi.advanceTimersByTime(mod.TYPEAHEAD_MS + 1);
      key(list[4]!, 'z');
      expect(document.activeElement).toBe(list[4]);
      key(list[4]!, 'x', { ctrlKey: true });
      menu().focus();
      vi.advanceTimersByTime(mod.TYPEAHEAD_MS + 1);
      key(menu(), 'e');
      expect(document.activeElement).toBe(list[0]);
    });

    it('Enter and Space activate; Tab closes and returns focus', () => {
      const spy = vi.fn();
      document.getElementById('dup')!.addEventListener('click', spy);
      contextmenu(document.getElementById('c1')!);
      key(items()[0]!, 'ArrowDown');
      key(document.activeElement!, 'Enter');
      expect(spy).toHaveBeenCalledTimes(1);
      contextmenu(document.getElementById('c1')!);
      key(items()[0]!, 'ArrowDown');
      key(document.activeElement!, ' ');
      expect(spy).toHaveBeenCalledTimes(2);
      contextmenu(document.getElementById('c1')!);
      menu().focus();
      key(menu(), 'Enter');
      expect(mod.isContextMenuOpen()).toBe(true);
      const tab = key(menu(), 'Tab');
      expect(tab.defaultPrevented).toBe(true);
      expect(mod.isContextMenuOpen()).toBe(false);
      expect(document.activeElement).toBe(document.getElementById('r1'));
    });

    it('Escape is kept from a surrounding dialog', () => {
      contextmenu(document.getElementById('c1')!);
      const outer = vi.fn();
      document.addEventListener('keydown', outer);
      const event = key(items()[0]!, 'Escape');
      document.removeEventListener('keydown', outer);
      expect(event.defaultPrevented).toBe(true);
      expect(outer).not.toHaveBeenCalled();
      expect(closes).toHaveLength(1);
    });

    it('keys reaching a stale panel do nothing', () => {
      contextmenu(document.getElementById('c1')!);
      const panel = menu();
      mod.closeContextMenu(false);
      expect(key(panel, 'ArrowDown').defaultPrevented).toBe(false);
      contextmenu(document.getElementById('c1')!);
      expect(key(panel, 'ArrowDown').defaultPrevented).toBe(false);
    });
  });

  describe('submenus', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <div id="card" data-nowo-ui-card>
          <p id="body">Card</p>
          <div data-nowo-ui-menu data-nowo-ui-context-menu-source>
            <ul class="nowo-ui-menu__panel">
              <li><a id="open" href="#open">Open</a></li>
              <li><span>Move to</span><ul><li><a id="todo" href="#todo">To do</a></li><li><a id="done" href="#done">Done</a></li></ul></li>
              <li><span>Label</span><ul><li><a id="red" href="#red">Red</a></li></ul></li>
            </ul>
          </div>
        </div>`;
      document.querySelectorAll('a[href]').forEach((a) => a.addEventListener('click', (event) => event.preventDefault()));
    });

    it('ArrowRight / Enter open a submenu beside its item; ArrowLeft and Escape close only the submenu', () => {
      contextmenu(document.getElementById('body')!);
      const [, move] = items();
      expect(move!.getAttribute('aria-haspopup')).toBe('menu');
      expect(move!.getAttribute('aria-expanded')).toBe('false');
      rect(move!, { left: 100, top: 200, width: 150, height: 30 });
      key(items()[0]!, 'ArrowDown');
      key(move!, 'ArrowRight');
      expect(mod.openPanels()).toHaveLength(2);
      const sub = mod.openPanels()[1]!;
      expect(sub.classList.contains('nowo-ui-context-menu--sub')).toBe(true);
      expect(move!.getAttribute('aria-expanded')).toBe('true');
      expect(move!.getAttribute('aria-controls')).toBe(sub.id);
      expect(sub.getAttribute('aria-label')).toBe('Move to');
      expect(sub.style.left).toBe('250px');
      expect(sub.style.top).toBe('200px');
      expect(document.activeElement).toBe(items(sub)[0]);
      key(items(sub)[0]!, 'ArrowDown');
      expect(document.activeElement).toBe(items(sub)[1]);
      key(document.activeElement!, 'ArrowLeft');
      expect(mod.openPanels()).toHaveLength(1);
      expect(document.activeElement).toBe(move);
      expect(move!.getAttribute('aria-expanded')).toBe('false');
      key(move!, 'Enter');
      expect(mod.openPanels()).toHaveLength(2);
      key(document.activeElement!, 'Escape');
      expect(mod.openPanels()).toHaveLength(1);
      expect(mod.isContextMenuOpen()).toBe(true);
      key(move!, ' ');
      key(document.activeElement!, 'ArrowRight');
      expect(mod.openPanels()).toHaveLength(2);
    });

    it('choosing a submenu item forwards the click and closes every level', () => {
      const spy = vi.fn();
      document.getElementById('done')!.addEventListener('click', spy);
      contextmenu(document.getElementById('body')!);
      items()[1]!.click();
      const sub = mod.openPanels()[1]!;
      items(sub)[1]!.click();
      expect(spy).toHaveBeenCalledTimes(1);
      expect(mod.isContextMenuOpen()).toBe(false);
      expect(sub.isConnected).toBe(false);
    });

    it('hovering opens a submenu without moving focus into it, hovering another item closes it', () => {
      contextmenu(document.getElementById('body')!);
      const [open, move, label] = items();
      move!.dispatchEvent(new PointerEvent('pointerover', { bubbles: true }));
      expect(mod.openPanels()).toHaveLength(2);
      expect(document.activeElement).toBe(move);
      move!.dispatchEvent(new PointerEvent('pointerover', { bubbles: true }));
      expect(mod.openPanels()).toHaveLength(2);
      label!.dispatchEvent(new PointerEvent('pointerover', { bubbles: true }));
      expect(mod.openPanels()[1]!.getAttribute('aria-label')).toBe('Label');
      open!.dispatchEvent(new PointerEvent('pointerover', { bubbles: true }));
      expect(mod.openPanels()).toHaveLength(1);
      expect(document.activeElement).toBe(open);
      menu().dispatchEvent(new PointerEvent('pointerover', { bubbles: true }));
      expect(mod.openPanels()).toHaveLength(1);
    });

    it('a submenu without room after its item opens before it; RTL mirrors it', () => {
      contextmenu(document.getElementById('body')!);
      const move = items()[1]!;
      rect(move, { left: 700, top: 50, width: 90, height: 30 });
      key(move, 'ArrowRight');
      move.focus();
      key(move, 'ArrowRight');
      expect(mod.openPanels()[1]!.style.left).toBe(`${700 - PANEL_W}px`);
      const style = window.getComputedStyle;
      vi.spyOn(window, 'getComputedStyle').mockImplementation((el: Element) => {
        const real = style.call(window, el);
        return { ...real, direction: 'rtl' } as CSSStyleDeclaration;
      });
      const panel = document.createElement('div');
      panel.className = 'nowo-ui-context-menu';
      rect(move, { left: 300, top: 50, width: 90, height: 30 });
      mod.placeBesideItem(panel, move);
      expect(panel.style.left).toBe(`${300 - PANEL_W}px`);
      mod.placeAtElement(panel, move);
      expect(panel.style.left).toBe(`${390 - PANEL_W}px`);
      // RTL keys: ArrowLeft opens, ArrowRight closes.
      mod.closeContextMenu(false);
      contextmenu(document.getElementById('body')!);
      const rtlMove = items()[1]!;
      rtlMove.focus();
      key(rtlMove, 'ArrowLeft');
      expect(mod.openPanels()).toHaveLength(2);
      key(document.activeElement!, 'ArrowRight');
      expect(mod.openPanels()).toHaveLength(1);
    });
  });

  describe('long press (touch)', () => {
    beforeEach(() => {
      vi.useFakeTimers();
    });

    it('opens after LONG_PRESS_MS at the finger, then swallows the click that ends the press (not the menu items)', () => {
      const cell = document.getElementById('c1')!;
      const rowClick = vi.fn();
      cell.addEventListener('click', rowClick);
      pointer('pointerdown', cell);
      vi.advanceTimersByTime(mod.LONG_PRESS_MS - 1);
      expect(mod.isContextMenuOpen()).toBe(false);
      vi.advanceTimersByTime(1);
      expect(mod.isContextMenuOpen()).toBe(true);
      expect(opens[0]!.detail.trigger).toBe('touch');
      expect(menu().style.left).toBe('40px');
      expect(menu().style.top).toBe('30px');
      pointer('pointerup', cell);
      expect(contextmenu(cell).defaultPrevented).toBe(true);
      const ending = new MouseEvent('click', { bubbles: true, cancelable: true });
      cell.dispatchEvent(ending);
      expect(ending.defaultPrevented).toBe(true);
      expect(rowClick).not.toHaveBeenCalled();
      cell.click();
      expect(rowClick).toHaveBeenCalledTimes(1);
    });

    it('a click on a menu item right after the press is not swallowed', () => {
      const spy = vi.fn();
      document.getElementById('edit')!.addEventListener('click', spy);
      pointer('pointerdown', document.getElementById('c1')!);
      vi.advanceTimersByTime(mod.LONG_PRESS_MS);
      items()[0]!.click();
      expect(spy).toHaveBeenCalledTimes(1);
    });

    it("the suppression follows the press's own pointerup (not a fixed delay), and a new pointer ends it", () => {
      const cell = document.getElementById('c1')!;
      const later = vi.fn();
      document.getElementById('c3')!.addEventListener('click', later);
      pointer('pointerdown', cell);
      vi.advanceTimersByTime(mod.LONG_PRESS_MS);
      // A slow click, long after opening, while the finger is still down: still the press's own.
      vi.advanceTimersByTime(mod.SUPPRESS_MS * 3);
      pointer('pointerup', cell, { pointerId: 99 });
      vi.advanceTimersByTime(mod.CLICK_GRACE_MS);
      const slow = new MouseEvent('click', { bubbles: true, cancelable: true });
      document.getElementById('c3')!.dispatchEvent(slow);
      expect(slow.defaultPrevented).toBe(true);
      // Its pointerup: the click right after it is swallowed, a click later is not.
      pointer('pointerdown', cell);
      vi.advanceTimersByTime(mod.LONG_PRESS_MS);
      pointer('pointerup', cell);
      pointer('pointerup', cell);
      vi.advanceTimersByTime(mod.CLICK_GRACE_MS);
      document.getElementById('c3')!.click();
      expect(later).toHaveBeenCalledTimes(1);
      // No click ever came, but a new touch starts: the old suppression is gone.
      pointer('pointerdown', cell);
      vi.advanceTimersByTime(mod.LONG_PRESS_MS);
      pointer('pointerdown', document.getElementById('c3')!, { pointerId: 8, pointerType: 'mouse' });
      document.getElementById('c3')!.click();
      expect(later).toHaveBeenCalledTimes(2);
      // pointercancel ends it like pointerup.
      pointer('pointerdown', cell);
      vi.advanceTimersByTime(mod.LONG_PRESS_MS);
      pointer('pointercancel', cell);
      vi.advanceTimersByTime(mod.CLICK_GRACE_MS);
      document.getElementById('c3')!.click();
      expect(later).toHaveBeenCalledTimes(3);
    });

    it('a second copy of the script (Turbo re-runs body scripts) binds nothing: one long press, one menu', async () => {
      // The module under test bound once at import (window flag set); a fresh copy must see the flag.
      vi.resetModules();
      const second = await import('./nowo-ui-context-menu');
      expect(second).not.toBe(mod);
      expect((window as unknown as Record<symbol, unknown>)[Symbol.for('nowo-ui.context-menu.bound')]).toBe(true);
      pointer('pointerdown', document.getElementById('c1')!);
      vi.advanceTimersByTime(mod.LONG_PRESS_MS);
      expect(document.querySelectorAll('.nowo-ui-context-menu')).toHaveLength(1);
      expect(second.isContextMenuOpen()).toBe(false);
      expect(mod.isContextMenuOpen()).toBe(true);
    });

    it('moving the finger, lifting it, cancelling or scrolling cancels the press', () => {
      const cell = document.getElementById('c1')!;
      pointer('pointerdown', cell);
      pointer('pointermove', cell, { clientX: 45, clientY: 33 });
      pointer('pointermove', cell, { clientX: 40, clientY: 30, pointerId: 99 });
      pointer('pointermove', cell, { clientX: 60, clientY: 30 });
      vi.advanceTimersByTime(mod.LONG_PRESS_MS);
      expect(mod.isContextMenuOpen()).toBe(false);
      pointer('pointerdown', cell);
      pointer('pointerup', cell, { pointerId: 99 });
      pointer('pointerup', cell);
      vi.advanceTimersByTime(mod.LONG_PRESS_MS);
      expect(mod.isContextMenuOpen()).toBe(false);
      pointer('pointerdown', cell);
      pointer('pointercancel', cell);
      vi.advanceTimersByTime(mod.LONG_PRESS_MS);
      expect(mod.isContextMenuOpen()).toBe(false);
      pointer('pointerdown', cell);
      window.dispatchEvent(new Event('scroll'));
      vi.advanceTimersByTime(mod.LONG_PRESS_MS);
      expect(mod.isContextMenuOpen()).toBe(false);
      pointer('pointermove', cell, { clientX: 400 });
    });

    it('mouse, secondary pointers, links, fields and rows without a menu never start a press', () => {
      pointer('pointerdown', document.getElementById('c1')!, { pointerType: 'mouse' });
      pointer('pointerdown', document.getElementById('c1')!, { isPrimary: false });
      pointer('pointerdown', document.getElementById('row-link')!);
      vi.advanceTimersByTime(mod.LONG_PRESS_MS);
      pointer('pointerdown', document.getElementById('c3')!);
      vi.advanceTimersByTime(mod.LONG_PRESS_MS);
      expect(mod.isContextMenuOpen()).toBe(false);
      pointer('pointerdown', document.getElementById('c1')!, { pointerType: 'pen' });
      vi.advanceTimersByTime(mod.LONG_PRESS_MS);
      expect(mod.isContextMenuOpen()).toBe(true);
      pointer('pointerdown', items()[0]!);
      vi.advanceTimersByTime(mod.LONG_PRESS_MS);
      expect(opens).toHaveLength(1);
    });

    it('text selected when the timer fires keeps the native behaviour', () => {
      pointer('pointerdown', document.getElementById('c1')!);
      vi.spyOn(window, 'getSelection').mockReturnValue({ isCollapsed: false, toString: () => 'Invoice' } as unknown as Selection);
      vi.advanceTimersByTime(mod.LONG_PRESS_MS);
      expect(mod.isContextMenuOpen()).toBe(false);
    });

    it("the browser's own long-press contextmenu (Android) opens the menu at once, only once", () => {
      const cell = document.getElementById('c1')!;
      pointer('pointerdown', cell);
      vi.advanceTimersByTime(300);
      const event = contextmenu(cell, { clientX: 41, clientY: 31 });
      expect(event.defaultPrevented).toBe(true);
      expect(mod.isContextMenuOpen()).toBe(true);
      expect(menu().style.left).toBe('40px');
      vi.advanceTimersByTime(mod.LONG_PRESS_MS);
      expect(opens).toHaveLength(1);
      mod.closeContextMenu(false);
      vi.advanceTimersByTime(mod.SUPPRESS_MS);
      pointer('pointerdown', cell);
      document.querySelectorAll('#k1 a').forEach((a) => a.remove());
      expect(contextmenu(cell).defaultPrevented).toBe(false);
    });
  });

  describe('rows and sheets (2.58)', () => {
    const ROW_KEBAB = `
      <article id="msg" tabindex="0" data-nowo-ui-context-menu-scope>
        <p id="body">Hello</p>
        <div class="nowo-ui-kebab" id="mk" data-nowo-ui-kebab data-nowo-ui-context-menu-source SHEET>
          <details class="nowo-ui-kebab__details"><summary class="nowo-ui-kebab__summary" aria-label="Message actions">⋮</summary>
            <div class="nowo-ui-kebab__menu" role="menu">
              <div class="nowo-ui-kebab__row" role="group" aria-label="Quick reactions" data-nowo-ui-menu-row>
                <a id="re1" class="nowo-ui-kebab__item is-current" href="#r1" role="menuitem" aria-label="React 1">1</a>
                <a id="re2" class="nowo-ui-kebab__item" href="#r2" role="menuitem" aria-label="React 2">2</a>
                <hr role="separator">
                <a id="re3" class="nowo-ui-kebab__item" href="#r3" role="menuitem" aria-label="More">+</a>
                <a class="nowo-ui-kebab__item" href="#x" role="menuitem" hidden>hidden</a>
              </div>
              <hr class="nowo-ui-kebab__separator" role="separator">
              <a id="reply" class="nowo-ui-kebab__item" href="#reply" role="menuitem">Reply</a>
              <a id="copy" class="nowo-ui-kebab__item" href="#copy" role="menuitem">Copy</a>
              <div class="nowo-ui-kebab__row" role="group" data-nowo-ui-menu-row><a class="nowo-ui-kebab__item" href="#h" role="menuitem" hidden>none</a></div>
              <div class="nowo-ui-kebab__row" role="group" data-nowo-ui-menu-row>
                <a id="tail1" class="nowo-ui-kebab__item" href="#t1" role="menuitem">T1</a>
                <a id="tail2" class="nowo-ui-kebab__item" href="#t2" role="menuitem">T2</a>
              </div>
            </div>
          </details>
        </div>
      </article>`;

    function mount(sheet: boolean): void {
      document.body.innerHTML = ROW_KEBAB.replace('SHEET', sheet ? 'data-nowo-ui-sheet' : '');
      document.querySelectorAll('a[href]').forEach((a) => a.addEventListener('click', (event) => event.preventDefault()));
    }

    function narrow(matches: boolean): void {
      vi.stubGlobal('matchMedia', (query: string) => ({ matches: matches && query === mod.SHEET_QUERY, media: query }));
    }

    it('a row of the kebab becomes one group of items; hidden and non-item entries inside it are left out; empty rows vanish', () => {
      mount(false);
      const entries = mod.collectEntries(document.getElementById('mk')!);
      expect(entries.map((entry) => entry.kind)).toEqual(['row', 'separator', 'item', 'item', 'row']);
      const row = entries[0] as Extract<mod.Entry, { kind: 'row' }>;
      expect(row.label).toBe('Quick reactions');
      expect(row.children.map((child) => child.label)).toEqual(['React 1', 'React 2', 'More']);
      expect((entries[4] as Extract<mod.Entry, { kind: 'row' }>).label).toBe('');
    });

    it('renders the row as role=group with menuitems; Left / Right move inside it (wrapping), Up / Down leave it', () => {
      mount(false);
      contextmenu(document.getElementById('body')!);
      const group = menu().querySelector('[role="group"]')!;
      expect(group.getAttribute('aria-label')).toBe('Quick reactions');
      expect(group.querySelectorAll('[role="menuitem"]')).toHaveLength(3);
      expect(menu().querySelectorAll('[role="group"]')[1]!.hasAttribute('aria-label')).toBe(false);
      const [r1, r2, r3, reply, copy, t1, t2] = items();
      expect(r1!.classList.contains('nowo-ui-context-menu__item--row')).toBe(true);
      // A row item shows the kebab item's text and keeps its accessible name; same text: no extra aria-label.
      expect(r1!.textContent).toBe('1');
      expect(r1!.getAttribute('aria-label')).toBe('React 1');
      expect(t1!.hasAttribute('aria-label')).toBe(false);
      expect(r1!.classList.contains('is-current')).toBe(true);
      expect(document.activeElement).toBe(r1);
      key(r1!, 'ArrowRight');
      expect(document.activeElement).toBe(r2);
      key(r2!, 'ArrowRight');
      key(r3!, 'ArrowRight');
      expect(document.activeElement).toBe(r1);
      key(r1!, 'ArrowLeft');
      expect(document.activeElement).toBe(r3);
      key(r3!, 'ArrowDown');
      expect(document.activeElement).toBe(reply);
      key(reply!, 'ArrowUp');
      expect(document.activeElement).toBe(r1);
      key(r1!, 'ArrowUp');
      expect(document.activeElement).toBe(t1);
      key(t1!, 'ArrowRight');
      expect(document.activeElement).toBe(t2);
      key(t2!, 'ArrowDown');
      expect(document.activeElement).toBe(r1);
      key(r1!, 'ArrowDown');
      key(reply!, 'ArrowDown');
      expect(document.activeElement).toBe(copy);
      key(copy!, 'ArrowDown');
      expect(document.activeElement).toBe(t1);
      // A plain item keeps its Left / Right meaning (no submenu here: nothing happens).
      key(copy!, 'ArrowRight');
      expect(mod.isContextMenuOpen()).toBe(true);
      r2!.click();
      expect(mod.isContextMenuOpen()).toBe(false);
    });

    it('a menu made of one row only: Up / Down stay on it', () => {
      document.body.innerHTML = `<article id="solo" data-nowo-ui-context-menu-scope><p id="sp">x</p><div data-nowo-ui-kebab data-nowo-ui-context-menu-source><div class="nowo-ui-kebab__menu"><div data-nowo-ui-menu-row><a href="#a" role="menuitem">A</a><a href="#b" role="menuitem">B</a></div></div></div></article>`;
      contextmenu(document.getElementById('sp')!);
      const [a, b] = items();
      key(a!, 'ArrowRight');
      key(b!, 'ArrowDown');
      expect(document.activeElement).toBe(b);
      key(b!, 'ArrowUp');
      expect(document.activeElement).toBe(b);
    });

    it('without the sheet attribute, or on a wide screen, or without matchMedia, the menu is placed at the pointer', () => {
      mount(false);
      narrow(true);
      contextmenu(document.getElementById('body')!);
      expect(menu().classList.contains('nowo-ui-context-menu--sheet')).toBe(false);
      expect(document.querySelector('[data-nowo-ui-context-menu-backdrop]')).toBeNull();
      mod.closeContextMenu(false);

      mount(true);
      narrow(false);
      contextmenu(document.getElementById('body')!);
      expect(menu().classList.contains('nowo-ui-context-menu--sheet')).toBe(false);
      expect(menu().style.left).toBe('50px');
      mod.closeContextMenu(false);

      vi.stubGlobal('matchMedia', undefined);
      expect(mod.opensAsSheet(document.getElementById('msg')!, document.getElementById('mk')!)).toBe(false);
    });

    it('opens as a bottom sheet above a backdrop on a narrow screen (source or host attribute); no coordinates', () => {
      mount(true);
      narrow(true);
      contextmenu(document.getElementById('body')!);
      expect(menu().classList.contains('nowo-ui-context-menu--sheet')).toBe(true);
      expect(menu().style.left).toBe('');
      const backdrop = document.querySelector<HTMLElement>('[data-nowo-ui-context-menu-backdrop]')!;
      expect(backdrop.getAttribute('aria-hidden')).toBe('true');
      expect(backdrop.compareDocumentPosition(menu()) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
      // The backdrop's own toggle event (the browser fires one for every popover shown) keeps the sheet open.
      const toggle = new Event('toggle', { bubbles: false });
      Object.assign(toggle, { newState: 'open' });
      backdrop.dispatchEvent(toggle);
      expect(mod.isContextMenuOpen()).toBe(true);
      mod.closeContextMenu(false);
      expect(document.querySelector('[data-nowo-ui-context-menu-backdrop]')).toBeNull();

      mount(false);
      document.getElementById('msg')!.setAttribute('data-nowo-ui-sheet', '');
      expect(mod.opensAsSheet(document.getElementById('msg')!, document.getElementById('mk')!)).toBe(true);
    });

    it('a tap on the backdrop closes the sheet and its click never reaches the page; an outside tap without a sheet does not swallow', () => {
      mount(true);
      narrow(true);
      const pageClick = vi.fn();
      document.getElementById('body')!.addEventListener('click', pageClick);
      contextmenu(document.getElementById('body')!);
      const backdrop = document.querySelector<HTMLElement>('[data-nowo-ui-context-menu-backdrop]')!;
      pointer('pointerdown', backdrop, { pointerId: 9 });
      expect(mod.isContextMenuOpen()).toBe(false);
      pointer('pointerup', document.getElementById('body')!, { pointerId: 9 });
      const click = new MouseEvent('click', { bubbles: true, cancelable: true });
      document.getElementById('body')!.dispatchEvent(click);
      expect(click.defaultPrevented).toBe(true);
      expect(pageClick).not.toHaveBeenCalled();

      mount(false);
      contextmenu(document.getElementById('body')!);
      pointer('pointerdown', document.body, { pointerType: 'mouse' });
      expect(mod.isContextMenuOpen()).toBe(false);
      const later = new MouseEvent('click', { bubbles: true, cancelable: true });
      document.body.dispatchEvent(later);
      expect(later.defaultPrevented).toBe(false);
    });

    it('2.58.1: the click ending the long press that opened a sheet is swallowed on a sheet item too', () => {
      vi.useFakeTimers();
      mount(true);
      narrow(true);
      const reply = document.getElementById('reply') as HTMLElement;
      const ran = vi.fn();
      reply.addEventListener('click', ran);
      const body = document.getElementById('body')!;
      pointer('pointerdown', body, { pointerId: 3 });
      vi.advanceTimersByTime(mod.LONG_PRESS_MS);
      expect(menu().classList.contains('nowo-ui-context-menu--sheet')).toBe(true);
      const item = items().find((el) => el.textContent === 'Reply')!;
      pointer('pointerup', item, { pointerId: 3 });
      const ending = new MouseEvent('click', { bubbles: true, cancelable: true });
      item.dispatchEvent(ending);
      expect(ending.defaultPrevented).toBe(true);
      expect(mod.isContextMenuOpen()).toBe(true);
      expect(ran).not.toHaveBeenCalled();
      // The next tap runs the item.
      item.click();
      expect(ran).toHaveBeenCalledTimes(1);
      vi.useRealTimers();
    });

    it('2.58.1: after a backdrop tap closed a sheet, a right-click opens a menu again at once', () => {
      mount(true);
      narrow(true);
      contextmenu(document.getElementById('body')!);
      pointer('pointerdown', document.querySelector<HTMLElement>('[data-nowo-ui-context-menu-backdrop]')!, { pointerId: 5 });
      expect(mod.isContextMenuOpen()).toBe(false);
      expect(contextmenu(document.getElementById('body')!).defaultPrevented).toBe(true);
      expect(mod.isContextMenuOpen()).toBe(true);
    });

    it('a submenu of a sheet is a sheet too, not placed beside its item', () => {
      document.body.innerHTML = `<article id="sm" data-nowo-ui-context-menu-scope><p id="smp">x</p><div data-nowo-ui-kebab data-nowo-ui-context-menu-source data-nowo-ui-sheet><div class="nowo-ui-kebab__menu"><ul><li><span>Move</span><ul><li><a href="#todo">To do</a></li></ul></li></ul></div></div></article>`;
      narrow(true);
      contextmenu(document.getElementById('smp')!);
      key(items()[0]!, 'ArrowRight');
      const panels = mod.openPanels();
      expect(panels).toHaveLength(2);
      expect(panels[1]!.classList.contains('nowo-ui-context-menu--sheet')).toBe(true);
      expect(panels[1]!.style.left).toBe('');
    });
  });
});
