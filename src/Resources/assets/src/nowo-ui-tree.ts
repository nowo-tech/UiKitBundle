/**
 * Tree view (2.24, partial `tree`): the WAI-ARIA APG navigation treeview over server-rendered markup.
 *
 * Markup (from `_tree.html.twig`): `ul[role=tree][data-nowo-ui-tree]`, `li[role=none][data-nowo-ui-tree-node]`, each with
 * an `a[role=treeitem][data-nowo-ui-tree-item]` (aria-level / setsize / posinset, aria-expanded on parents, aria-owns
 * of its `ul[role=group]`) and an optional decorative toggle `[data-nowo-ui-tree-toggle]`.
 *
 * - One tab stop (roving tabindex). Down / Up: next / previous visible item; Right: expand, then first child; Left:
 *   collapse, then parent; Home / End; `*` expands the siblings; printable characters jump to the next item starting
 *   with them (type-ahead); Enter follows the link (native).
 * - Lazy children: a group with `data-nowo-ui-tree-children-url` and no items is filled on first expand from a JSON
 *   array `[{id, label, href, hasChildren?, childrenUrl?, badge?}]` (DOM APIs only, never HTML from the server).
 * - Events (bubbling CustomEvents on the tree root): `nowo-ui:tree:toggle` {id, expanded} after a fold change (persist
 *   it), and with `data-nowo-ui-tree-movable` the cancellable `nowo-ui:tree:move` {id, direction: up|down|in|out}
 *   (Alt+arrows, the keyboard alternative to dragging) and `nowo-ui:tree:drop` {id, targetId, position:
 *   before|after|inside} after a pointer drag (drop line, auto-expand after 600 ms, Escape cancels natively). The host
 *   performs the move and re-renders; `announceTree(root, text)` says the result in the root's polite live region.
 * - Delegated listeners, no inline handlers or style attributes (strict CSP).
 */

export const TREE = '[data-nowo-ui-tree]';
export const ITEM = '[data-nowo-ui-tree-item]';
export const NODE = '[data-nowo-ui-tree-node]';

export type TreeMoveDirection = 'up' | 'down' | 'in' | 'out';
export type TreeDropPosition = 'before' | 'after' | 'inside';
export type TreeChild = { id: string; label: string; href: string; hasChildren?: boolean; childrenUrl?: string; badge?: string };

type Fetcher = (url: string) => Promise<unknown>;
let fetcher: Fetcher = async (url) => {
  const res = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
  if (!res.ok) throw new Error(String(res.status));
  return res.json();
};

/** Replaces the JSON loader (tests, hosts with their own transport). */
export function setTreeFetcher(next: Fetcher): void {
  fetcher = next;
}

const treeOf = (el: Element): HTMLElement | null => el.closest<HTMLElement>(TREE);
const nodeOf = (item: Element): HTMLElement | null => item.closest<HTMLElement>(NODE);
const groupOf = (item: HTMLElement): HTMLElement | null => {
  const id = item.getAttribute('aria-owns');
  return id ? item.ownerDocument.getElementById(id) : null;
};
const isParent = (item: HTMLElement): boolean => item.hasAttribute('aria-expanded');
const isExpanded = (item: HTMLElement): boolean => item.getAttribute('aria-expanded') === 'true';

/** Items whose ancestors are all expanded, in document order. */
export function visibleItems(tree: HTMLElement): HTMLElement[] {
  return [...tree.querySelectorAll<HTMLElement>(ITEM)].filter((item) => {
    for (let group = item.closest('[role="group"]'); group && tree.contains(group); group = group.parentElement?.closest('[role="group"]') ?? null) {
      if ((group as HTMLElement).hidden) return false;
    }
    return true;
  });
}

function focusItem(tree: HTMLElement, item: HTMLElement): void {
  tree.querySelectorAll<HTMLElement>(ITEM).forEach((el) => {
    el.tabIndex = el === item ? 0 : -1;
  });
  item.focus();
}

/** The parent treeitem of an item, or null at the top level. */
export function parentItem(item: HTMLElement): HTMLElement | null {
  const group = item.closest('[role="group"]');
  return group ? (item.ownerDocument.querySelector<HTMLElement>(`[aria-owns="${CSS.escape(group.id)}"]`) ?? null) : null;
}

function emit<T>(tree: HTMLElement, name: string, detail: T, cancelable = false): boolean {
  return tree.dispatchEvent(new CustomEvent<T>(name, { bubbles: true, cancelable, detail }));
}

/** Says `text` in the tree's polite live region (created on first use). */
export function announceTree(tree: HTMLElement, text: string): void {
  let live = tree.parentElement?.querySelector<HTMLElement>(':scope > [data-nowo-ui-tree-live]') ?? null;
  if (!live) {
    live = tree.ownerDocument.createElement('div');
    live.className = 'visually-hidden';
    live.setAttribute('data-nowo-ui-tree-live', '');
    live.setAttribute('role', 'status');
    tree.after(live);
  }
  live.textContent = text;
}

function buildChild(doc: Document, child: TreeChild, level: number, index: number, size: number): HTMLLIElement {
  const li = doc.createElement('li');
  li.setAttribute('role', 'none');
  li.className = 'nowo-ui-tree__item';
  li.dataset.nowoUiTreeNode = child.id;
  const row = doc.createElement('div');
  row.className = 'nowo-ui-tree__row';
  const toggle = doc.createElement('span');
  toggle.className = child.hasChildren ? 'nowo-ui-tree__toggle' : 'nowo-ui-tree__spacer';
  toggle.setAttribute('aria-hidden', 'true');
  if (child.hasChildren) toggle.setAttribute('data-nowo-ui-tree-toggle', '');
  const a = doc.createElement('a');
  a.className = 'nowo-ui-tree__link';
  a.href = /^(https?:)?\//i.test(child.href) && !child.href.startsWith('//') ? child.href : '#';
  a.tabIndex = -1;
  a.setAttribute('role', 'treeitem');
  a.setAttribute('data-nowo-ui-tree-item', '');
  a.setAttribute('aria-level', String(level));
  a.setAttribute('aria-setsize', String(size));
  a.setAttribute('aria-posinset', String(index + 1));
  const label = doc.createElement('span');
  label.className = 'nowo-ui-tree__label';
  label.textContent = child.label;
  a.append(label);
  if (child.badge) {
    const badge = doc.createElement('span');
    badge.className = 'nowo-ui-tree__badge';
    badge.textContent = child.badge;
    a.append(badge);
  }
  row.append(toggle, a);
  li.append(row);
  if (child.hasChildren) {
    const group = doc.createElement('ul');
    group.setAttribute('role', 'group');
    group.className = 'nowo-ui-tree__group';
    group.id = `nowo-ui-tree-${child.id.replace(/[^\w-]/g, '_')}-group`;
    group.hidden = true;
    if (child.childrenUrl) group.dataset.nowoUiTreeChildrenUrl = child.childrenUrl;
    a.setAttribute('aria-expanded', 'false');
    a.setAttribute('aria-owns', group.id);
    li.append(group);
  }
  return li;
}

async function loadChildren(item: HTMLElement, group: HTMLElement): Promise<void> {
  const url = group.dataset.nowoUiTreeChildrenUrl;
  if (!url || group.querySelector(ITEM)) return;
  group.setAttribute('aria-busy', 'true');
  try {
    const data = await fetcher(url);
    const rows = Array.isArray(data) ? (data as TreeChild[]).filter((c) => c && typeof c.id === 'string' && typeof c.label === 'string' && typeof c.href === 'string') : [];
    const level = Number(item.getAttribute('aria-level') ?? '1') + 1;
    rows.forEach((child, i) => group.append(buildChild(item.ownerDocument, child, level, i, rows.length)));
    delete group.dataset.nowoUiTreeChildrenUrl;
  } catch {
    // Children stay unloaded; the next expand tries again.
  } finally {
    group.removeAttribute('aria-busy');
  }
}

/** Expands or collapses a parent item (loading lazy children), and emits `nowo-ui:tree:toggle`. */
export async function setExpanded(item: HTMLElement, expanded: boolean): Promise<void> {
  const tree = treeOf(item);
  const group = groupOf(item);
  if (!tree || !group || !isParent(item) || isExpanded(item) === expanded) return;
  item.setAttribute('aria-expanded', String(expanded));
  group.hidden = !expanded;
  if (expanded) await loadChildren(item, group);
  emit(tree, 'nowo-ui:tree:toggle', { id: nodeOf(item)?.dataset.nowoUiTreeNode ?? '', expanded });
}

let typed = '';
let typedAt = 0;

function onKeydown(event: KeyboardEvent): void {
  const item = (event.target as Element | null)?.closest?.<HTMLElement>(ITEM);
  const tree = item ? treeOf(item) : null;
  if (!item || !tree || event.ctrlKey || event.metaKey) return;
  const items = visibleItems(tree);
  const index = items.indexOf(item);
  if (event.altKey) {
    const direction = ({ ArrowUp: 'up', ArrowDown: 'down', ArrowRight: 'in', ArrowLeft: 'out' } as Record<string, TreeMoveDirection>)[event.key];
    if (!direction || !tree.hasAttribute('data-nowo-ui-tree-movable')) return;
    event.preventDefault();
    emit(tree, 'nowo-ui:tree:move', { id: nodeOf(item)?.dataset.nowoUiTreeNode ?? '', direction }, true);
    return;
  }
  let handled = true;
  switch (event.key) {
    case 'ArrowDown':
      if (items[index + 1]) focusItem(tree, items[index + 1]!);
      break;
    case 'ArrowUp':
      if (index > 0) focusItem(tree, items[index - 1]!);
      break;
    case 'Home':
      focusItem(tree, items[0]!);
      break;
    case 'End':
      focusItem(tree, items[items.length - 1]!);
      break;
    case 'ArrowRight':
      if (isParent(item) && !isExpanded(item)) void setExpanded(item, true);
      else if (isParent(item)) {
        const first = groupOf(item)?.querySelector<HTMLElement>(ITEM);
        if (first) focusItem(tree, first);
      }
      break;
    case 'ArrowLeft': {
      if (isParent(item) && isExpanded(item)) void setExpanded(item, false);
      else {
        const parent = parentItem(item);
        if (parent) focusItem(tree, parent);
      }
      break;
    }
    case '*': {
      const siblings = [...(item.closest('[role="group"], [role="tree"]')?.querySelectorAll<HTMLElement>(':scope > li > .nowo-ui-tree__row > [data-nowo-ui-tree-item]') ?? [])];
      siblings.filter((s) => isParent(s)).forEach((s) => void setExpanded(s, true));
      break;
    }
    default:
      handled = event.key.length === 1 && event.key !== ' ' && typeahead(tree, items, index, event.key);
  }
  if (handled) event.preventDefault();
}

function typeahead(tree: HTMLElement, items: HTMLElement[], index: number, char: string): boolean {
  const now = Date.now();
  typed = now - typedAt < 700 ? typed + char.toLowerCase() : char.toLowerCase();
  typedAt = now;
  for (let step = typed.length > 1 ? 0 : 1; step <= items.length; step++) {
    const candidate = items[(index + step) % items.length]!;
    if ((candidate.textContent ?? '').trim().toLowerCase().startsWith(typed)) {
      focusItem(tree, candidate);
      return true;
    }
  }
  return true;
}

function onClick(event: MouseEvent): void {
  const toggle = (event.target as Element | null)?.closest?.<HTMLElement>('[data-nowo-ui-tree-toggle]');
  const item = toggle?.parentElement?.querySelector<HTMLElement>(ITEM);
  if (!toggle || !item) return;
  event.preventDefault();
  void setExpanded(item, !isExpanded(item));
}

let dragged: HTMLElement | null = null;
let hoverTimer: number | undefined;

function dropPosition(row: HTMLElement, y: number): TreeDropPosition {
  const box = row.getBoundingClientRect();
  const offset = y - box.top;
  if (offset < box.height / 4) return 'before';
  if (offset > (box.height * 3) / 4) return 'after';
  return 'inside';
}

function clearIndicator(): void {
  document.querySelectorAll('[data-nowo-ui-tree-drop]').forEach((el) => el.removeAttribute('data-nowo-ui-tree-drop'));
  window.clearTimeout(hoverTimer);
}

function onDragStart(event: DragEvent): void {
  const item = (event.target as Element | null)?.closest?.<HTMLElement>(ITEM);
  const tree = item ? treeOf(item) : null;
  if (!item || !tree?.hasAttribute('data-nowo-ui-tree-movable')) return;
  dragged = item;
  event.dataTransfer?.setData('text/plain', nodeOf(item)?.dataset.nowoUiTreeNode ?? '');
  if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
}

function onDragOver(event: DragEvent): void {
  const row = (event.target as Element | null)?.closest?.<HTMLElement>('.nowo-ui-tree__row');
  const item = row?.querySelector<HTMLElement>(ITEM);
  if (!dragged || !row || !item || item === dragged || nodeOf(dragged)?.contains(row)) return;
  event.preventDefault();
  const position = dropPosition(row, event.clientY);
  if (row.getAttribute('data-nowo-ui-tree-drop') !== position) {
    clearIndicator();
    row.setAttribute('data-nowo-ui-tree-drop', position);
    if (position === 'inside' && isParent(item) && !isExpanded(item)) hoverTimer = window.setTimeout(() => void setExpanded(item, true), 600);
  }
}

function onDrop(event: DragEvent): void {
  const row = (event.target as Element | null)?.closest?.<HTMLElement>('.nowo-ui-tree__row');
  const position = row?.getAttribute('data-nowo-ui-tree-drop') as TreeDropPosition | null;
  const target = row?.querySelector<HTMLElement>(ITEM);
  const source = dragged;
  clearIndicator();
  dragged = null;
  const tree = source ? treeOf(source) : null;
  if (!source || !target || !position || !tree) return;
  event.preventDefault();
  emit(tree, 'nowo-ui:tree:drop', { id: nodeOf(source)?.dataset.nowoUiTreeNode ?? '', targetId: nodeOf(target)?.dataset.nowoUiTreeNode ?? '', position });
}

function onDragEnd(): void {
  clearIndicator();
  dragged = null;
}

let bound = false;

export function bindNowoUiTree(): void {
  if (bound) return;
  bound = true;
  document.addEventListener('keydown', onKeydown);
  document.addEventListener('click', onClick);
  document.addEventListener('dragstart', onDragStart);
  document.addEventListener('dragover', onDragOver);
  document.addEventListener('drop', onDrop);
  document.addEventListener('dragend', onDragEnd);
}

bindNowoUiTree();
