import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  MAX_GROUPS,
  MAX_OPTIONS,
  MAX_TEXT,
  bindNowoUiCombobox,
  closeList,
  debounceOf,
  enhance,
  enhanceAll,
  fieldOf,
  minLengthOf,
  nextIndex,
  normalise,
  pick,
  render,
  schedule,
  search,
  searchUrl,
} from './nowo-ui-combobox';

const MARKUP = `
<form id="form">
  <div id="root" data-nowo-ui-combobox data-nowo-ui-combobox-url="/search?scope=all" data-nowo-ui-combobox-min-length="2" data-nowo-ui-combobox-debounce="200"
       data-nowo-ui-combobox-labels='{"results":"%count% found","none":"Nothing","min_length":"At least %count%","error":"Broken"}'>
    <input id="field" type="text" name="page">
    <div id="list" data-nowo-ui-combobox-listbox role="listbox" popover="manual" hidden></div>
    <p id="status" data-nowo-ui-combobox-status role="status"></p>
  </div>
  <input type="hidden" id="page-id" name="page_id">
</form>
<button id="outside" type="button">x</button>`;

const ANSWER = {
  groups: [
    { label: 'This wiki', options: [{ value: '1', label: 'Home', description: '/home' }, { value: '2', label: 'Install' }] },
    { label: 'Other wikis', options: [{ value: '3', label: '<img src=x onerror=alert(1)>' }] },
  ],
};

const el = <T extends HTMLElement = HTMLElement>(id: string): T => document.getElementById(id) as T;
const root = (): HTMLElement => el('root');
const field = (): HTMLInputElement => el<HTMLInputElement>('field');
const key = (name: string, init: KeyboardEventInit = {}): KeyboardEvent => {
  const event = new KeyboardEvent('keydown', { key: name, bubbles: true, cancelable: true, ...init });
  field().dispatchEvent(event);
  return event;
};
const type = (text: string): void => {
  field().value = text;
  field().dispatchEvent(new Event('input', { bubbles: true }));
};
const json = (body: unknown, type = 'application/json'): Response => new Response(JSON.stringify(body), { status: 200, headers: { 'Content-Type': type } });
const active = (): string | null => document.querySelector('[role="option"].is-active')?.textContent ?? null;
const flush = async (): Promise<void> => {
  for (let i = 0; i < 5; i++) {
    await Promise.resolve();
  }
};

describe('nowo-ui-combobox', () => {
  let fetchMock: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    document.body.innerHTML = MARKUP;
    fetchMock = vi.fn().mockImplementation(() => Promise.resolve(json(ANSWER)));
    vi.stubGlobal('fetch', fetchMock);
    bindNowoUiCombobox();
    enhanceAll();
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    document.body.innerHTML = '';
  });

  describe('normalise', () => {
    it('keeps groups, own string properties and the label as default value', () => {
      expect(normalise(ANSWER)).toEqual([
        { label: 'This wiki', options: [{ value: '1', label: 'Home', description: '/home' }, { value: '2', label: 'Install', description: '' }] },
        { label: 'Other wikis', options: [{ value: '3', label: '<img src=x onerror=alert(1)>', description: '' }] },
      ]);
    });

    it('takes a bare list or {options} as one unnamed group, and numbers as strings', () => {
      expect(normalise([{ label: 'A', value: 7 }])).toEqual([{ label: '', options: [{ value: '7', label: 'A', description: '' }] }]);
      expect(normalise({ options: [{ label: 'B' }] })).toEqual([{ label: '', options: [{ value: 'B', label: 'B', description: '' }] }]);
    });

    it('drops what is not an option, and anything that is not an answer', () => {
      expect(normalise(null)).toEqual([]);
      expect(normalise('x')).toEqual([]);
      expect(normalise({ groups: 'x' })).toEqual([]);
      expect(normalise({ groups: [null, [], { label: 'G' }, { label: 'G', options: [null, 1, [], { label: '' }, { label: '  ' }, { label: {} }, { label: 'ok', value: Infinity, description: 3 }] }] })).toEqual([
        { label: 'G', options: [{ value: 'ok', label: 'ok', description: '3' }] },
      ]);
      const inherited = Object.create({ label: 'from the prototype' }) as object;
      expect(normalise([inherited])).toEqual([]);
    });

    it('caps groups, options and text', () => {
      const many = Array.from({ length: MAX_GROUPS + 5 }, (_, g) => ({ label: `g${g}`, options: [{ label: 'x' }] }));
      expect(normalise({ groups: many })).toHaveLength(MAX_GROUPS);
      const options = Array.from({ length: MAX_OPTIONS + 5 }, (_, i) => ({ label: `o${i}` }));
      expect(normalise({ groups: [{ label: 'a', options: options.slice(0, 60) }, { label: 'b', options: options.slice(60) }] }).reduce((n, g) => n + g.options.length, 0)).toBe(MAX_OPTIONS);
      expect(normalise([{ label: 'é'.repeat(MAX_TEXT + 10) }])[0]!.options[0]!.label).toHaveLength(MAX_TEXT);
    });
  });

  describe('searchUrl', () => {
    it('adds the term on this origin, replacing the parameter', () => {
      expect(searchUrl('/search?q=old&x=1', 'ab c', 'q')!.toString()).toBe(`${window.location.origin}/search?q=ab+c&x=1`);
      expect(searchUrl(`${window.location.origin}/s`, 'z', 'term')!.searchParams.get('term')).toBe('z');
      expect(searchUrl('/s', 'z', 'bad param')!.searchParams.get('q')).toBe('z');
    });

    it('refuses another origin, other schemes and nothing', () => {
      expect(searchUrl('https://evil.example/s', 'a')).toBeNull();
      expect(searchUrl('//evil.example/s', 'a')).toBeNull();
      expect(searchUrl('javascript:alert(1)', 'a')).toBeNull();
      expect(searchUrl('data:text/plain,x', 'a')).toBeNull();
      expect(searchUrl(null, 'a')).toBeNull();
      expect(searchUrl('http://[', 'a')).toBeNull();
    });
  });

  it('reads and bounds its settings', () => {
    expect(minLengthOf(root())).toBe(2);
    expect(debounceOf(root())).toBe(200);
    root().setAttribute('data-nowo-ui-combobox-min-length', '0');
    root().setAttribute('data-nowo-ui-combobox-debounce', '99999');
    expect(minLengthOf(root())).toBe(1);
    expect(debounceOf(root())).toBe(2000);
    root().setAttribute('data-nowo-ui-combobox-min-length', 'x');
    root().removeAttribute('data-nowo-ui-combobox-debounce');
    expect(minLengthOf(root())).toBe(2);
    expect(debounceOf(root())).toBe(250);
  });

  it('gives the field its combobox roles only with the script, and once', () => {
    expect(field().getAttribute('role')).toBe('combobox');
    expect(field().getAttribute('aria-autocomplete')).toBe('list');
    expect(field().getAttribute('aria-expanded')).toBe('false');
    expect(field().getAttribute('aria-controls')).toBe('list');
    expect(field().getAttribute('autocomplete')).toBe('off');
    expect(root().hasAttribute('data-nowo-ui-combobox-ready')).toBe(true);
    field().setAttribute('aria-expanded', 'true');
    enhance(root());
    expect(field().getAttribute('aria-expanded')).toBe('true');
  });

  it('a listbox without an id gets one, a root without a field or listbox is left alone, and focus enhances again', () => {
    el('list').removeAttribute('id');
    field().removeAttribute('role');
    field().dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
    expect(field().getAttribute('role')).toBe('combobox');
    expect(field().getAttribute('aria-controls')).toMatch(/^nowo-ui-combobox-\d+$/);
    const bare = document.createElement('div');
    bare.setAttribute('data-nowo-ui-combobox', '');
    document.body.append(bare);
    enhance(bare);
    expect(bare.hasAttribute('data-nowo-ui-combobox-ready')).toBe(false);
    expect(fieldOf(bare)).toBeNull();
    schedule(bare);
    closeList(bare);
    expect(render(bare, [])).toBe(0);
  });

  it('a combobox inserted later is enhanced', async () => {
    const wrap = document.createElement('section');
    wrap.innerHTML = MARKUP.replace(/id="/g, 'id="late-');
    document.body.append(wrap);
    await vi.waitFor(() => expect(el('late-field').getAttribute('role')).toBe('combobox'));
  });

  it('waits for the minimum length and the debounce, then renders grouped text options and announces the count', async () => {
    vi.useFakeTimers();
    type('h');
    expect(el('status').textContent).toBe('At least 2');
    vi.advanceTimersByTime(1000);
    expect(fetchMock).not.toHaveBeenCalled();
    type('ho');
    vi.advanceTimersByTime(199);
    expect(fetchMock).not.toHaveBeenCalled();
    vi.advanceTimersByTime(1);
    expect(fetchMock).toHaveBeenCalledTimes(1);
    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe(`${window.location.origin}/search?scope=all&q=ho`);
    expect(init).toMatchObject({ mode: 'same-origin', credentials: 'same-origin', headers: { Accept: 'application/json' } });
    expect(root().getAttribute('aria-busy')).toBe('true');
    vi.useRealTimers();
    await vi.waitFor(() => expect(el('list').hidden).toBe(false));
    expect(root().hasAttribute('aria-busy')).toBe(false);
    expect(field().getAttribute('aria-expanded')).toBe('true');
    expect(el('status').textContent).toBe('3 found');
    const groups = document.querySelectorAll('[role="group"]');
    expect(groups).toHaveLength(2);
    expect(document.getElementById(groups[0]!.getAttribute('aria-labelledby')!)!.textContent).toBe('This wiki');
    expect(groups[0]!.querySelectorAll('[role="option"]')).toHaveLength(2);
    expect(document.querySelector('.nowo-ui-combobox__option-description')!.textContent).toBe('/home');
    const evil = groups[1]!.querySelector('[role="option"]')!;
    expect(evil.textContent).toBe('<img src=x onerror=alert(1)>');
    expect(document.querySelector('img')).toBeNull();
    expect(document.querySelectorAll('[role="option"][aria-selected="false"]')).toHaveLength(3);
  });

  it('renders an unnamed group without a group wrapper', () => {
    fieldOf(root())!.value = 'ab';
    expect(render(root(), [{ label: '', options: [{ value: 'a', label: 'A', description: '' }] }])).toBe(1);
    expect(document.querySelector('[role="group"]')).toBeNull();
    expect(el('list').firstElementChild!.getAttribute('role')).toBe('option');
  });

  it('says so when nothing matches, and closes', async () => {
    fetchMock.mockImplementation(() => Promise.resolve(json({ groups: [] })));
    field().value = 'zz';
    await search(root(), 'zz');
    expect(el('list').hidden).toBe(true);
    expect(el('status').textContent).toBe('Nothing');
  });

  it('a newer term aborts the request in flight, and an older answer is dropped', async () => {
    const signals: AbortSignal[] = [];
    let release: (r: Response) => void = () => undefined;
    fetchMock.mockImplementationOnce((_url: string, init: RequestInit) => {
      signals.push(init.signal!);
      return new Promise<Response>((resolve) => {
        release = resolve;
      });
    });
    field().value = 'ab';
    const first = search(root(), 'ab');
    field().value = 'abc';
    fetchMock.mockImplementationOnce(() => Promise.resolve(json({ options: [{ label: 'Fresh' }] })));
    await search(root(), 'abc');
    expect(signals[0]!.aborted).toBe(true);
    release(json({ options: [{ label: 'Stale' }] }));
    await first;
    expect([...document.querySelectorAll('[role="option"]')].map((o) => o.textContent)).toEqual(['Fresh']);
  });

  it('drops an answer when the text changed meanwhile', async () => {
    field().value = 'ab';
    const pending = search(root(), 'ab');
    field().value = 'abz';
    await pending;
    expect(document.querySelectorAll('[role="option"]')).toHaveLength(0);
  });

  it('works without AbortController, by order alone', async () => {
    vi.stubGlobal('AbortController', undefined);
    field().value = 'ab';
    await search(root(), 'ab');
    expect(fetchMock.mock.calls[0]![1]).not.toHaveProperty('signal');
    expect(document.querySelectorAll('[role="option"]')).toHaveLength(3);
  });

  it('typing below the minimum aborts and closes', async () => {
    field().value = 'ab';
    await search(root(), 'ab');
    expect(el('list').hidden).toBe(false);
    type('');
    expect(field().getAttribute('aria-expanded')).toBe('false');
    expect(el('status').textContent).toBe('');
  });

  it.each([
    ['an error status', () => Promise.resolve(new Response('{}', { status: 500, headers: { 'Content-Type': 'application/json' } }))],
    ['HTML', () => Promise.resolve(new Response('<p>x</p>', { status: 200, headers: { 'Content-Type': 'text/html' } }))],
    ['a network failure', () => Promise.reject(new TypeError('offline'))],
    ['broken JSON', () => Promise.resolve(new Response('{', { status: 200, headers: { 'Content-Type': 'application/json' } }))],
  ])('reports a failure on %s', async (_name, answer) => {
    fetchMock.mockImplementation(answer);
    const errors: unknown[] = [];
    root().addEventListener('nowo-ui:combobox:error', (e) => errors.push((e as CustomEvent).detail));
    field().value = 'ab';
    await search(root(), 'ab');
    expect(el('status').textContent).toBe('Broken');
    expect(el('list').hidden).toBe(true);
    expect(errors).toEqual([{ term: 'ab' }]);
  });

  it('accepts a +json type, and reports the count', async () => {
    fetchMock.mockImplementation(() => Promise.resolve(json(ANSWER, 'application/vnd.api+json; charset=utf-8')));
    const seen: unknown[] = [];
    root().addEventListener('nowo-ui:combobox:results', (e) => seen.push((e as CustomEvent).detail));
    field().value = 'ab';
    await search(root(), 'ab');
    expect(seen).toEqual([{ term: 'ab', count: 3 }]);
  });

  it('sends no request to another origin', async () => {
    root().setAttribute('data-nowo-ui-combobox-url', 'https://evil.example/search');
    field().value = 'ab';
    await search(root(), 'ab');
    expect(fetchMock).not.toHaveBeenCalled();
    expect(el('status').textContent).toBe('Broken');
  });

  describe('keyboard', () => {
    beforeEach(async () => {
      field().focus();
      field().value = 'ab';
      await search(root(), 'ab');
    });

    it('Down and Up move through every group and wrap, with aria-activedescendant on the field', () => {
      key('ArrowDown');
      expect(active()).toBe('Home/home');
      expect(field().getAttribute('aria-activedescendant')).toBe(document.querySelector('.is-active')!.id);
      expect(document.querySelector('.is-active')!.getAttribute('aria-selected')).toBe('true');
      key('ArrowDown');
      key('ArrowDown');
      expect(active()).toBe('<img src=x onerror=alert(1)>');
      key('ArrowDown');
      expect(active()).toBe('Home/home');
      key('ArrowUp');
      expect(active()).toBe('<img src=x onerror=alert(1)>');
      expect(document.querySelectorAll('[aria-selected="true"]')).toHaveLength(1);
    });

    it('Enter picks the active option into the field without submitting, and fires the events', () => {
      const picks: unknown[] = [];
      const inputs: string[] = [];
      root().addEventListener('nowo-ui:combobox:pick', (e) => picks.push((e as CustomEvent).detail));
      field().addEventListener('input', () => inputs.push(field().value));
      key('ArrowDown');
      const enter = key('Enter');
      expect(enter.defaultPrevented).toBe(true);
      expect(field().value).toBe('1');
      expect(picks).toEqual([{ value: '1', label: 'Home', description: '/home', group: 'This wiki' }]);
      expect(inputs).toEqual(['1']);
      expect(field().getAttribute('aria-expanded')).toBe('false');
      expect(field().hasAttribute('aria-activedescendant')).toBe(false);
      expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('Enter without an active option is left to the form', () => {
      expect(key('Enter').defaultPrevented).toBe(false);
      expect(field().getAttribute('aria-expanded')).toBe('false');
    });

    it('a cancelled pick changes nothing', () => {
      root().addEventListener('nowo-ui:combobox:pick', (e) => e.preventDefault());
      key('ArrowDown');
      key('Enter');
      expect(field().value).toBe('ab');
      expect(pick(root(), 99)).toBe(false);
    });

    it('Escape closes the list only, and stops there', () => {
      const outer = vi.fn();
      document.addEventListener('keydown', outer);
      const escape = key('Escape');
      expect(escape.defaultPrevented).toBe(true);
      expect(field().getAttribute('aria-expanded')).toBe('false');
      expect(outer).not.toHaveBeenCalled();
      const again = key('Escape');
      expect(again.defaultPrevented).toBe(false);
      document.removeEventListener('keydown', outer);
    });

    it('Down reopens the same results, Up from closed starts at the last, Alt+Down opens without moving, Alt+Up closes', () => {
      key('Escape');
      key('ArrowDown');
      expect(field().getAttribute('aria-expanded')).toBe('true');
      expect(active()).toBe('Home/home');
      key('Escape');
      key('ArrowUp');
      expect(active()).toBe('<img src=x onerror=alert(1)>');
      key('ArrowUp', { altKey: true });
      expect(field().getAttribute('aria-expanded')).toBe('false');
      key('ArrowDown', { altKey: true });
      expect(field().getAttribute('aria-expanded')).toBe('true');
      expect(active()).toBeNull();
      key('ArrowDown', { altKey: true });
      expect(field().getAttribute('aria-expanded')).toBe('true');
    });

    it('Down with a changed text searches at once', () => {
      key('Escape');
      field().value = 'abc';
      vi.useFakeTimers();
      key('ArrowDown');
      vi.advanceTimersByTime(0);
      expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('the caret keys give the focus back to the text, Tab closes, other keys and IME are left alone', () => {
      key('ArrowDown');
      key('ArrowLeft');
      expect(active()).toBeNull();
      expect(field().hasAttribute('aria-activedescendant')).toBe(false);
      key('a');
      key('ArrowDown', { isComposing: true });
      expect(active()).toBeNull();
      key('Tab');
      expect(field().getAttribute('aria-expanded')).toBe('false');
    });

    it('keys on other elements are ignored', () => {
      const event = new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true, cancelable: true });
      el('outside').dispatchEvent(event);
      expect(event.defaultPrevented).toBe(false);
    });
  });

  describe('value input', () => {
    beforeEach(async () => {
      root().setAttribute('data-nowo-ui-combobox-value-input', 'page-id');
      field().value = 'ab';
      await search(root(), 'ab');
    });

    it('the field shows the label and the hidden input takes the value; typing empties it', () => {
      expect(pick(root(), 1)).toBe(true);
      expect(field().value).toBe('Install');
      expect(el<HTMLInputElement>('page-id').value).toBe('2');
      vi.useFakeTimers();
      type('Instal');
      expect(el<HTMLInputElement>('page-id').value).toBe('');
      type('Insta');
      expect(el<HTMLInputElement>('page-id').value).toBe('');
    });

    it('RU-I1: is ignored when neither input is in a form', async () => {
      document.body.innerHTML = MARKUP.replace('<form id="form">', '<div id="form">').replace('</form>', '</div>');
      enhanceAll();
      root().setAttribute('data-nowo-ui-combobox-value-input', 'page-id');
      expect(field().form).toBeNull();
      field().value = 'ab';
      await search(root(), 'ab');
      expect(pick(root(), 0)).toBe(true);
      expect(field().value).toBe('1');
      expect(el<HTMLInputElement>('page-id').value).toBe('');
    });

    it('is ignored when it is not a hidden input of the same form', () => {
      el('page-id').setAttribute('type', 'text');
      pick(root(), 0);
      expect(field().value).toBe('1');
      el('page-id').setAttribute('type', 'hidden');
      document.body.append(el('page-id'));
      field().value = 'ab';
      pick(root(), 0);
      expect(field().value).toBe('1');
      expect(el<HTMLInputElement>('page-id').value).toBe('');
    });
  });

  describe('pointer', () => {
    beforeEach(async () => {
      field().focus();
      field().value = 'ab';
      await search(root(), 'ab');
    });

    it('a press in the list keeps the focus in the field, a click picks', () => {
      const option = document.querySelectorAll('[role="option"]')[1] as HTMLElement;
      const down = new Event('pointerdown', { bubbles: true, cancelable: true });
      option.firstElementChild!.dispatchEvent(down);
      expect(down.defaultPrevented).toBe(true);
      (option.firstElementChild as HTMLElement).click();
      expect(field().value).toBe('2');
      expect(document.activeElement).toBe(field());
    });

    it('a click outside closes, a press outside is left alone', () => {
      const down = new Event('pointerdown', { bubbles: true, cancelable: true });
      el('outside').dispatchEvent(down);
      expect(down.defaultPrevented).toBe(false);
      el('outside').click();
      expect(field().getAttribute('aria-expanded')).toBe('false');
    });

    it('focus leaving the field closes the list, focus moving inside it does not', () => {
      field().dispatchEvent(new FocusEvent('focusout', { bubbles: true, relatedTarget: el('list') }));
      expect(field().getAttribute('aria-expanded')).toBe('true');
      field().dispatchEvent(new FocusEvent('focusout', { bubbles: true, relatedTarget: el('outside') }));
      expect(field().getAttribute('aria-expanded')).toBe('false');
    });

    it('a second combobox opening closes the first', async () => {
      const wrap = document.createElement('section');
      wrap.innerHTML = MARKUP.replace(/id="/g, 'id="b-');
      document.body.append(wrap);
      enhanceAll(wrap);
      el<HTMLInputElement>('b-field').value = 'ab';
      await search(el('b-root'), 'ab');
      expect(el('b-field').getAttribute('aria-expanded')).toBe('true');
      expect(field().getAttribute('aria-expanded')).toBe('false');
    });

    it('follows the page on scroll and resize, not on its own scroll', () => {
      const spy = vi.spyOn(el('list').style, 'setProperty');
      window.dispatchEvent(new Event('resize'));
      expect(spy).toHaveBeenCalled();
      spy.mockClear();
      el('list').dispatchEvent(new Event('scroll'));
      expect(spy).not.toHaveBeenCalled();
      closeList(root());
      window.dispatchEvent(new Event('resize'));
      expect(spy).not.toHaveBeenCalled();
    });

    it('a disabled field picks nothing', () => {
      field().disabled = true;
      expect(pick(root(), 0)).toBe(false);
    });
  });

  it('RU-L1: leaving the field drops the planned search and the request in flight', async () => {
    vi.useFakeTimers();
    field().focus();
    type('ab');
    field().dispatchEvent(new FocusEvent('focusout', { bubbles: true, relatedTarget: el('outside') }));
    vi.advanceTimersByTime(1000);
    expect(fetchMock).not.toHaveBeenCalled();
    vi.useRealTimers();
    let release: (r: Response) => void = () => undefined;
    let signal: AbortSignal | undefined;
    fetchMock.mockImplementationOnce((_url: string, init: RequestInit) => {
      signal = init.signal!;
      return new Promise<Response>((resolve) => {
        release = resolve;
      });
    });
    const pending = search(root(), 'ab');
    expect(root().getAttribute('aria-busy')).toBe('true');
    field().dispatchEvent(new FocusEvent('focusout', { bubbles: true, relatedTarget: el('outside') }));
    expect(signal!.aborted).toBe(true);
    expect(root().hasAttribute('aria-busy')).toBe(false);
    release(json(ANSWER));
    await pending;
    expect(field().getAttribute('aria-expanded')).toBe('false');
    expect(el('list').hidden).toBe(true);
  });

  it('nextIndex wraps and handles an empty list', () => {
    expect(nextIndex('ArrowDown', -1, 0)).toBe(-1);
    expect(nextIndex('ArrowDown', -1, 3)).toBe(0);
    expect(nextIndex('ArrowDown', 2, 3)).toBe(0);
    expect(nextIndex('ArrowUp', -1, 3)).toBe(2);
    expect(nextIndex('ArrowUp', 0, 3)).toBe(2);
    expect(nextIndex('ArrowUp', 2, 3)).toBe(1);
  });

  it('broken labels fall back to the default wording', async () => {
    root().setAttribute('data-nowo-ui-combobox-labels', '{');
    fetchMock.mockImplementation(() => Promise.resolve(json([])));
    field().value = 'ab';
    await search(root(), 'ab');
    expect(el('status').textContent).toBe('No results');
    root().setAttribute('data-nowo-ui-combobox-labels', '{"none": 3}');
    await search(root(), 'ab');
    expect(el('status').textContent).toBe('No results');
    await flush();
  });
});
