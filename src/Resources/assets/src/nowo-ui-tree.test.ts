import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as mod from './nowo-ui-tree';

function key(el: Element, name: string, init: KeyboardEventInit = {}): KeyboardEvent {
  const event = new KeyboardEvent('keydown', { key: name, bubbles: true, cancelable: true, ...init });
  el.dispatchEvent(event);
  return event;
}
const item = (id: string) => document.querySelector<HTMLElement>(`[data-nowo-ui-tree-node="${id}"] > .nowo-ui-tree__row > [data-nowo-ui-tree-item]`)!;
const tick = () => new Promise((r) => setTimeout(r, 0));

const MARKUP = `
  <ul role="tree" aria-label="Pages" data-nowo-ui-tree data-nowo-ui-tree-movable>
    <li role="none" data-nowo-ui-tree-node="a"><div class="nowo-ui-tree__row"><span data-nowo-ui-tree-toggle></span>
      <a href="/a" role="treeitem" data-nowo-ui-tree-item tabindex="0" aria-level="1" aria-expanded="true" aria-owns="g-a">Alpha</a></div>
      <ul role="group" id="g-a">
        <li role="none" data-nowo-ui-tree-node="a1"><div class="nowo-ui-tree__row"><span></span><a href="/a1" role="treeitem" data-nowo-ui-tree-item tabindex="-1" aria-level="2">Apple</a></div></li>
        <li role="none" data-nowo-ui-tree-node="a2"><div class="nowo-ui-tree__row"><span data-nowo-ui-tree-toggle></span><a href="/a2" role="treeitem" data-nowo-ui-tree-item tabindex="-1" aria-level="2" aria-expanded="false" aria-owns="g-a2">Avocado</a></div>
          <ul role="group" id="g-a2" hidden data-nowo-ui-tree-children-url="/children?parent=a2"></ul></li>
      </ul></li>
    <li role="none" data-nowo-ui-tree-node="b"><div class="nowo-ui-tree__row"><span data-nowo-ui-tree-toggle></span>
      <a href="/b" role="treeitem" data-nowo-ui-tree-item tabindex="-1" aria-level="1" aria-expanded="false" aria-owns="g-b">Beta</a></div>
      <ul role="group" id="g-b" hidden><li role="none" data-nowo-ui-tree-node="b1"><div class="nowo-ui-tree__row"><span></span><a href="/b1" role="treeitem" data-nowo-ui-tree-item tabindex="-1" aria-level="2">Berry</a></div></li></ul></li>
    <li role="none" data-nowo-ui-tree-node="c"><div class="nowo-ui-tree__row"><span></span><a href="/c" role="treeitem" data-nowo-ui-tree-item tabindex="-1" aria-level="1">Cherry</a></div></li>
  </ul>`;

describe('nowo-ui-tree', () => {
  let tree: HTMLElement;
  let events: { type: string; detail: unknown }[];

  beforeEach(() => {
    document.body.innerHTML = MARKUP;
    tree = document.querySelector<HTMLElement>('[data-nowo-ui-tree]')!;
    events = [];
    for (const type of ['nowo-ui:tree:toggle', 'nowo-ui:tree:move', 'nowo-ui:tree:drop']) {
      tree.addEventListener(type, (e) => events.push({ type, detail: (e as CustomEvent).detail }));
    }
    mod.bindNowoUiTree();
    mod.setTreeFetcher(async () => [
      { id: 'x1', label: 'Lazy <b>one</b>', href: '/x1', badge: '3' },
      { id: 'x2', label: 'Lazy two', href: 'javascript:alert(1)', hasChildren: true, childrenUrl: '/children?parent=x2' },
      { bad: true },
    ]);
  });

  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('moves through the visible items with arrows, Home and End (roving tabindex)', () => {
    item('a').focus();
    key(item('a'), 'ArrowDown');
    expect(document.activeElement).toBe(item('a1'));
    expect(item('a1').tabIndex).toBe(0);
    expect(item('a').tabIndex).toBe(-1);
    key(item('a1'), 'ArrowDown');
    key(item('a2'), 'ArrowDown');
    expect(document.activeElement).toBe(item('b')); // b1 is in a collapsed group
    key(item('b'), 'End');
    expect(document.activeElement).toBe(item('c'));
    key(item('c'), 'ArrowDown');
    expect(document.activeElement).toBe(item('c'));
    key(item('c'), 'Home');
    expect(document.activeElement).toBe(item('a'));
    key(item('a'), 'ArrowUp');
    expect(document.activeElement).toBe(item('a'));
    expect(mod.visibleItems(tree)).toHaveLength(5);
  });

  it('Right expands then enters, Left collapses then goes to the parent; toggles emit events', async () => {
    item('b').focus();
    key(item('b'), 'ArrowRight');
    await tick();
    expect(item('b').getAttribute('aria-expanded')).toBe('true');
    expect(document.getElementById('g-b')!.hidden).toBe(false);
    expect(events).toContainEqual({ type: 'nowo-ui:tree:toggle', detail: { id: 'b', expanded: true } });
    key(item('b'), 'ArrowRight');
    expect(document.activeElement).toBe(item('b1'));
    key(item('b1'), 'ArrowLeft');
    expect(document.activeElement).toBe(item('b'));
    key(item('b'), 'ArrowLeft');
    await tick();
    expect(item('b').getAttribute('aria-expanded')).toBe('false');
    expect(mod.parentItem(item('b'))).toBeNull();
    key(item('c'), 'ArrowRight'); // a leaf: nothing
    key(item('c'), 'ArrowLeft'); // top level: nothing
    await mod.setExpanded(item('c'), true);
  });

  it('loads lazy children once, safely (text only, safe links, invalid rows dropped)', async () => {
    await mod.setExpanded(item('a2'), true);
    const group = document.getElementById('g-a2')!;
    expect(group.querySelectorAll('[data-nowo-ui-tree-item]')).toHaveLength(2);
    expect(group.querySelector('b')).toBeNull();
    expect(item('x1').textContent).toContain('Lazy <b>one</b>');
    expect(item('x1').getAttribute('aria-level')).toBe('3');
    expect(item('x1').getAttribute('aria-setsize')).toBe('2');
    expect(item('x2').getAttribute('href')).toBe('#');
    expect(item('x2').getAttribute('aria-expanded')).toBe('false');
    expect(group.hasAttribute('data-nowo-ui-tree-children-url')).toBe(false);
    expect(item('x1').querySelector('.nowo-ui-tree__badge')?.textContent).toBe('3');
    mod.setTreeFetcher(async () => {
      throw new Error('down');
    });
    await mod.setExpanded(item('x2'), true);
    expect(item('x2').getAttribute('aria-expanded')).toBe('true');
    mod.setTreeFetcher(async () => ({}));
    await mod.setExpanded(item('x2'), false);
    await mod.setExpanded(item('x2'), true);
  });

  it('`*` expands the siblings; type-ahead finds items; a click on a toggle folds', async () => {
    item('a').focus();
    key(item('a'), '*');
    await tick();
    expect(item('b').getAttribute('aria-expanded')).toBe('true');
    const now = vi.spyOn(Date, 'now').mockReturnValue(1000);
    key(item('a'), 'c');
    expect(document.activeElement).toBe(item('c'));
    now.mockReturnValue(5000);
    key(item('c'), 'b');
    expect(document.activeElement).toBe(item('b'));
    key(item('b'), 'e'); // "be": Beta itself
    expect(document.activeElement).toBe(item('b'));
    now.mockReturnValue(9000);
    key(item('b'), 'z');
    now.mockRestore();
    expect(key(item('b'), ' ').defaultPrevented).toBe(false);
    document.querySelector<HTMLElement>('[data-nowo-ui-tree-node="a"] [data-nowo-ui-tree-toggle]')!.click();
    await tick();
    expect(item('a').getAttribute('aria-expanded')).toBe('false');
    document.body.click();
  });

  it('Alt+arrows ask the host to move (movable trees only)', () => {
    key(item('a1'), 'ArrowDown', { altKey: true });
    key(item('a1'), 'ArrowRight', { altKey: true });
    key(item('a1'), 'x', { altKey: true });
    expect(events.filter((e) => e.type === 'nowo-ui:tree:move').map((e) => e.detail)).toEqual([
      { id: 'a1', direction: 'down' },
      { id: 'a1', direction: 'in' },
    ]);
    tree.removeAttribute('data-nowo-ui-tree-movable');
    key(item('a1'), 'ArrowUp', { altKey: true });
    expect(events.filter((e) => e.type === 'nowo-ui:tree:move')).toHaveLength(2);
    expect(key(item('a1'), 'ArrowDown', { ctrlKey: true }).defaultPrevented).toBe(false);
  });

  it('drag and drop: indicator, auto-expand, drop event; not onto itself or its descendants', () => {
    vi.useFakeTimers();
    const data = { setData: vi.fn(), effectAllowed: '' };
    const drag = (type: string, target: Element, y = 0) => {
      const event = new Event(type, { bubbles: true, cancelable: true }) as DragEvent;
      Object.defineProperty(event, 'dataTransfer', { value: data });
      Object.defineProperty(event, 'clientY', { value: y });
      target.dispatchEvent(event);
      return event;
    };
    const rowB = item('b').parentElement!;
    rowB.getBoundingClientRect = () => ({ top: 0, height: 40 }) as DOMRect;
    drag('dragstart', item('c'));
    expect(data.setData).toHaveBeenCalledWith('text/plain', 'c');
    drag('dragover', rowB, 20);
    expect(rowB.getAttribute('data-nowo-ui-tree-drop')).toBe('inside');
    vi.advanceTimersByTime(700);
    expect(item('b').getAttribute('aria-expanded')).toBe('true');
    drag('dragover', rowB, 2);
    expect(rowB.getAttribute('data-nowo-ui-tree-drop')).toBe('before');
    drag('dragover', rowB, 38);
    expect(rowB.getAttribute('data-nowo-ui-tree-drop')).toBe('after');
    drag('drop', rowB, 38);
    expect(events.find((e) => e.type === 'nowo-ui:tree:drop')?.detail).toEqual({ id: 'c', targetId: 'b', position: 'after' });
    expect(rowB.hasAttribute('data-nowo-ui-tree-drop')).toBe(false);
    // Onto its own child: refused.
    drag('dragstart', item('a'));
    const over = drag('dragover', item('a1').parentElement!, 5);
    expect(over.defaultPrevented).toBe(false);
    drag('dragend', item('a'));
    drag('drop', rowB, 5); // nothing dragged
    tree.removeAttribute('data-nowo-ui-tree-movable');
    drag('dragstart', item('c'));
    drag('dragover', rowB, 20);
    expect(rowB.hasAttribute('data-nowo-ui-tree-drop')).toBe(false);
    vi.useRealTimers();
  });

  it('marks the trees as driven by the script (pointer on toggles only then)', () => {
    tree.removeAttribute('data-nowo-ui-tree-ready');
    mod.markTreesReady();
    expect(tree.hasAttribute('data-nowo-ui-tree-ready')).toBe(true);
  });

  it('announces in a polite live region next to the tree', () => {
    mod.announceTree(tree, 'Moved');
    mod.announceTree(tree, 'Moved again');
    const live = document.querySelectorAll('[data-nowo-ui-tree-live]');
    expect(live).toHaveLength(1);
    expect(live[0]!.getAttribute('role')).toBe('status');
    expect(live[0]!.textContent).toBe('Moved again');
  });
});
