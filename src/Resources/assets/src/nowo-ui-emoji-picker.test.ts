import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { COLUMNS, bindNowoUiEmojiPicker, closePicker, firstGrapheme, matching, nextIndex, openPicker, revealToggles } from './nowo-ui-emoji-picker';
import { EMOJI } from './nowo-ui-emoji-table';

const MARKUP = `
<form id="form">
  <div id="root" data-nowo-ui-emoji-picker data-nowo-ui-emoji-picker-labels='{"results":"%count% found","none":"Nothing"}'>
    <input id="field" type="text" name="icon">
    <button id="toggle" type="button" data-nowo-ui-emoji-picker-toggle aria-expanded="false" hidden>o</button>
    <div id="panel" data-nowo-ui-emoji-picker-panel hidden>
      <input id="search" type="search" data-nowo-ui-emoji-picker-search aria-expanded="false">
      <div id="grid" data-nowo-ui-emoji-picker-grid></div>
      <p id="status" data-nowo-ui-emoji-picker-status></p>
    </div>
  </div>
</form>
<button id="outside" type="button">x</button>`;

const el = <T extends HTMLElement = HTMLElement>(id: string): T => document.getElementById(id) as T;
const key = (name: string): KeyboardEvent => {
  const event = new KeyboardEvent('keydown', { key: name, bubbles: true, cancelable: true });
  el('search').dispatchEvent(event);
  return event;
};
const type = (text: string): void => {
  el<HTMLInputElement>('search').value = text;
  el('search').dispatchEvent(new Event('input', { bubbles: true }));
};
const active = (): string | null => document.querySelector('.is-active')?.getAttribute('data-nowo-ui-emoji-picker-cell') ?? null;

describe('nowo-ui-emoji-picker', () => {
  beforeEach(() => {
    document.body.innerHTML = MARKUP;
    bindNowoUiEmojiPicker();
    revealToggles();
  });

  afterEach(() => {
    document.body.innerHTML = '';
    vi.restoreAllMocks();
  });

  it('shows the toggle only with the script, and not for a field that cannot change', () => {
    expect(el('toggle').hidden).toBe(false);
    el<HTMLInputElement>('field').disabled = true;
    revealToggles();
    expect(el('toggle').hidden).toBe(true);
    el('toggle').click();
    expect(el('panel').hidden).toBe(true);
    el<HTMLInputElement>('field').disabled = false;
    el<HTMLInputElement>('field').readOnly = true;
    revealToggles(el('form'));
    expect(el('toggle').hidden).toBe(true);
  });

  it('a field inserted later gets its toggle', async () => {
    const wrap = document.createElement('div');
    wrap.innerHTML = MARKUP.replace(/id="/g, 'id="late-');
    document.body.append(wrap);
    await vi.waitFor(() => expect(el('late-toggle').hidden).toBe(false));
  });

  it('opens with focus in the search box and every emoji in rows of the grid, and closes again', () => {
    el('toggle').click();
    expect(el('panel').hidden).toBe(false);
    expect(el('toggle').getAttribute('aria-expanded')).toBe('true');
    expect(el('search').getAttribute('aria-expanded')).toBe('true');
    expect(document.activeElement).toBe(el('search'));
    expect(document.querySelectorAll('[role="gridcell"]')).toHaveLength(EMOJI.length);
    expect(document.querySelector('[role="row"]')!.children).toHaveLength(COLUMNS);
    expect(el('status').textContent).toBe(`${EMOJI.length} found`);
    el('toggle').click();
    expect(el('panel').hidden).toBe(true);
    expect(el('toggle').getAttribute('aria-expanded')).toBe('false');
    expect(document.activeElement).toBe(el('toggle'));
  });

  it('search filters by name, and says when nothing matches', () => {
    openPicker(el('root'));
    type('ROCK');
    expect([...document.querySelectorAll('[role="gridcell"]')].map((c) => c.textContent)).toEqual(['🚀']);
    expect(el('status').textContent).toBe('1 found');
    type('nothing like this');
    expect(document.querySelectorAll('[role="gridcell"]')).toHaveLength(0);
    expect(el('status').textContent).toBe('Nothing');
    expect(key('Enter').defaultPrevented).toBe(true);
    expect(el('panel').hidden).toBe(false);
  });

  it('arrows move in the grid with aria-activedescendant, focus staying in the box', () => {
    openPicker(el('root'));
    expect(key('ArrowDown').defaultPrevented).toBe(true);
    expect(active()).toBe(EMOJI[0]![0]);
    expect(el('search').getAttribute('aria-activedescendant')).toBe(document.querySelector('.is-active')!.id);
    expect(document.querySelector('.is-active')!.getAttribute('aria-selected')).toBe('true');
    key('ArrowRight');
    expect(active()).toBe(EMOJI[1]![0]);
    key('ArrowDown');
    expect(active()).toBe(EMOJI[1 + COLUMNS]![0]);
    key('ArrowUp');
    key('ArrowLeft');
    expect(active()).toBe(EMOJI[0]![0]);
    key('End');
    expect(active()).toBe(EMOJI[COLUMNS - 1]![0]);
    key('Home');
    expect(active()).toBe(EMOJI[0]![0]);
    expect(document.activeElement).toBe(el('search'));
    expect(document.querySelectorAll('.is-active')).toHaveLength(1);
  });

  it('left, right, home and end belong to the text until the grid is entered', () => {
    openPicker(el('root'));
    for (const name of ['ArrowLeft', 'ArrowRight', 'Home', 'End']) {
      expect(key(name).defaultPrevented).toBe(false);
    }
    expect(active()).toBeNull();
  });

  it('typing again leaves the grid', () => {
    openPicker(el('root'));
    key('ArrowDown');
    type('r');
    expect(active()).toBeNull();
    expect(el('search').hasAttribute('aria-activedescendant')).toBe(false);
  });

  it('Enter picks the active cell: one value, input and change, closed, focus back, form not submitted', () => {
    const seen: string[] = [];
    el('field').addEventListener('input', () => seen.push('input'));
    el('field').addEventListener('change', () => seen.push('change'));
    const pickEvents: unknown[] = [];
    el('root').addEventListener('nowo-ui:emoji-picker:pick', (e) => pickEvents.push((e as CustomEvent).detail));
    openPicker(el('root'));
    key('ArrowDown');
    key('ArrowRight');
    const enter = key('Enter');
    expect(enter.defaultPrevented).toBe(true);
    expect(el<HTMLInputElement>('field').value).toBe(EMOJI[1]![1]);
    expect(seen).toEqual(['input', 'change']);
    expect(pickEvents).toEqual([{ emoji: EMOJI[1]![1], name: EMOJI[1]![0] }]);
    expect(el('panel').hidden).toBe(true);
    expect(document.activeElement).toBe(el('toggle'));
  });

  it('Enter with no active cell picks the first result', () => {
    openPicker(el('root'));
    type('rocket');
    key('Enter');
    expect(el<HTMLInputElement>('field').value).toBe('🚀');
  });

  it('a listener can refuse a pick', () => {
    el('root').addEventListener('nowo-ui:emoji-picker:pick', (e) => e.preventDefault());
    openPicker(el('root'));
    key('Enter');
    expect(el<HTMLInputElement>('field').value).toBe('');
    expect(el('panel').hidden).toBe(false);
  });

  it('a click on a cell picks it, and pressing it keeps focus in the box', () => {
    openPicker(el('root'));
    const cell = document.querySelector<HTMLElement>('[role="gridcell"]')!;
    const press = new Event('pointerdown', { bubbles: true, cancelable: true });
    cell.dispatchEvent(press);
    expect(press.defaultPrevented).toBe(true);
    cell.click();
    expect(el<HTMLInputElement>('field').value).toBe(EMOJI[0]![1]);
  });

  it('Escape closes the panel only: it does not reach the page, and focus goes back', () => {
    const outer = vi.fn();
    document.body.addEventListener('keydown', outer);
    openPicker(el('root'));
    expect(key('Escape').defaultPrevented).toBe(true);
    document.body.removeEventListener('keydown', outer);
    expect(outer).not.toHaveBeenCalled();
    expect(el('panel').hidden).toBe(true);
    expect(document.activeElement).toBe(el('toggle'));
  });

  it('Tab closes and leaves focus to the browser; a click outside closes', () => {
    openPicker(el('root'));
    expect(key('Tab').defaultPrevented).toBe(false);
    expect(el('panel').hidden).toBe(true);
    openPicker(el('root'));
    el('outside').click();
    expect(el('panel').hidden).toBe(true);
  });

  it('opening one closes another', () => {
    const wrap = document.createElement('div');
    wrap.innerHTML = MARKUP.replace(/id="/g, 'id="b-');
    document.body.append(wrap);
    openPicker(el('root'));
    openPicker(el('b-root'));
    expect(el('panel').hidden).toBe(true);
    expect(el('b-panel').hidden).toBe(false);
  });

  it('typed or pasted text is cut to one grapheme on change, in the field only', () => {
    const field = el<HTMLInputElement>('field');
    field.value = ' 👍🏽abc';
    field.dispatchEvent(new Event('change', { bubbles: true }));
    expect(field.value).toBe('👍🏽');
    field.value = '👨‍👩‍👧‍👦';
    field.dispatchEvent(new Event('change', { bubbles: true }));
    expect(field.value).toBe('👨‍👩‍👧‍👦');
    el<HTMLInputElement>('search').value = 'two words';
    el('search').dispatchEvent(new Event('change', { bubbles: true }));
    expect(el<HTMLInputElement>('search').value).toBe('two words');
  });

  it('marks the current value in the grid', () => {
    el<HTMLInputElement>('field').value = '🚀';
    openPicker(el('root'));
    expect(document.querySelector('.is-selected')?.textContent).toBe('🚀');
  });

  it('labels and table: a host table replaces the list, as text; broken ones leave the defaults', () => {
    el('root').setAttribute('data-nowo-ui-emoji-picker-table', JSON.stringify([['cat', '<b>x</b>'], ['bad', 5], 'junk']));
    openPicker(el('root'));
    const cells = document.querySelectorAll('[role="gridcell"]');
    expect(cells).toHaveLength(1);
    expect(cells[0]!.querySelector('b')).toBeNull();
    closePicker(el('root'), false);
    for (const raw of ['{broken', '[]', '{"a":1}']) {
      el('root').setAttribute('data-nowo-ui-emoji-picker-table', raw);
      openPicker(el('root'));
      expect(document.querySelectorAll('[role="gridcell"]')).toHaveLength(EMOJI.length);
      closePicker(el('root'), false);
    }
    el('root').setAttribute('data-nowo-ui-emoji-picker-labels', '{broken');
    openPicker(el('root'));
    expect(el('status').textContent).toBe(`${EMOJI.length} results`);
    closePicker(el('root'), false);
    el('root').setAttribute('data-nowo-ui-emoji-picker-labels', '{"results":5}');
    openPicker(el('root'));
    expect(el('status').textContent).toBe(`${EMOJI.length} results`);
  });

  it('a form with controls named like DOM members hides nothing', () => {
    el('form').insertAdjacentHTML('beforeend', '<input name="closest"><input name="querySelector">');
    el('toggle').click();
    expect(el('panel').hidden).toBe(false);
    el('form').dispatchEvent(new MouseEvent('click', { bubbles: true }));
  });

  it('a picker without its parts does nothing', () => {
    document.body.innerHTML = '<div id="root" data-nowo-ui-emoji-picker><input id="field"><button id="toggle" data-nowo-ui-emoji-picker-toggle></button></div>';
    el('toggle').click();
    closePicker(el('root'), true);
    expect(document.activeElement).not.toBe(el('field'));
  });

  describe('helpers', () => {
    it('firstGrapheme takes one user-perceived character', () => {
      expect(firstGrapheme('  ')).toBe('');
      expect(firstGrapheme('abc')).toBe('a');
      expect(firstGrapheme('🇪🇸x')).toBe('🇪🇸');
      expect(firstGrapheme('1️⃣2')).toBe('1️⃣');
      const intl = Intl as unknown as { Segmenter?: unknown };
      const segmenter = intl.Segmenter;
      intl.Segmenter = undefined;
      expect(firstGrapheme(' abc ')).toBe('abc');
      intl.Segmenter = segmenter;
    });

    it('matching looks at the name and the character', () => {
      expect(matching(EMOJI, '')).toBe(EMOJI);
      expect(matching(EMOJI, '🚀')).toEqual([['rocket', '🚀']]);
    });

    it('nextIndex stops at the edges, in a short last row too', () => {
      expect(nextIndex('ArrowDown', -1, 0)).toBe(-1);
      expect(nextIndex('ArrowUp', -1, 10)).toBe(9);
      expect(nextIndex('End', -1, 10)).toBe(9);
      expect(nextIndex('ArrowRight', 9, 10)).toBe(9);
      expect(nextIndex('ArrowLeft', 0, 10)).toBe(0);
      expect(nextIndex('ArrowDown', 8, 10)).toBe(8);
      expect(nextIndex('ArrowUp', 3, 10)).toBe(3);
      expect(nextIndex('End', 8, 10)).toBe(9);
      expect(nextIndex('x', 4, 10)).toBe(4);
    });

    it('the table has unique names and one grapheme each', () => {
      expect(new Set(EMOJI.map(([name]) => name)).size).toBe(EMOJI.length);
      EMOJI.forEach(([, emoji]) => expect(firstGrapheme(emoji)).toBe(emoji));
    });
  });
});
