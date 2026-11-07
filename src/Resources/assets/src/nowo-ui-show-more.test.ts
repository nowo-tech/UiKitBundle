import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { bindNowoUiShowMore, isClipped, setExpanded, syncAll, syncBlock } from './nowo-ui-show-more';

const MARKUP = `
<div id="root" class="nowo-ui-show-more--lines-3" data-nowo-ui-show-more>
  <div id="content" data-nowo-ui-show-more-content><a id="link" href="#x">a link</a> text</div>
  <button id="toggle" type="button" data-nowo-ui-show-more-toggle data-nowo-ui-show-more-more="More" data-nowo-ui-show-more-less="Less" aria-expanded="false" aria-controls="content" hidden>More</button>
</div>`;

const el = (id: string): HTMLElement => document.getElementById(id) as HTMLElement;

/** happy-dom has no layout: a block overflows when told to. */
function overflow(id: string, overflows: boolean): void {
  Object.defineProperty(el(id), 'scrollHeight', { configurable: true, value: overflows ? 200 : 60 });
  Object.defineProperty(el(id), 'clientHeight', { configurable: true, value: 60 });
}

const rect = (top: number, bottom: number): DOMRect => ({ top, bottom, left: 0, right: 100, width: 100, height: bottom - top, x: 0, y: top, toJSON: () => ({}) }) as DOMRect;

/** Where the link is, against the fold's 0 to 60 px. */
function linkAt(top: number, bottom: number): void {
  vi.spyOn(Element.prototype, 'getBoundingClientRect').mockImplementation(function (this: Element) {
    return this.id === 'content' ? rect(0, 60) : rect(top, bottom);
  });
}

describe('nowo-ui-show-more', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  beforeEach(() => {
    document.body.innerHTML = MARKUP;
    bindNowoUiShowMore();
    overflow('content', true);
    syncBlock(el('root'));
  });

  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('turns the fold on, and shows the button when the text overflows', () => {
    expect(el('root').hasAttribute('data-nowo-ui-show-more-ready')).toBe(true);
    expect(el('toggle').hidden).toBe(false);
  });

  it('keeps the button hidden when everything fits, and follows a change of width', () => {
    overflow('content', false);
    syncBlock(el('root'));
    expect(el('toggle').hidden).toBe(true);
    overflow('content', true);
    syncAll();
    expect(el('toggle').hidden).toBe(false);
  });

  it('a click opens and folds, with aria-expanded, label, class and event', () => {
    const seen: boolean[] = [];
    el('root').addEventListener('nowo-ui:show-more:toggle', (e) => seen.push((e as CustomEvent).detail.expanded));
    el('toggle').click();
    expect(el('toggle').getAttribute('aria-expanded')).toBe('true');
    expect(el('toggle').textContent).toBe('Less');
    expect(el('root').classList.contains('is-expanded')).toBe(true);
    el('toggle').click();
    expect(el('toggle').getAttribute('aria-expanded')).toBe('false');
    expect(el('toggle').textContent).toBe('More');
    expect(el('root').classList.contains('is-expanded')).toBe(false);
    expect(seen).toEqual([true, false]);
  });

  it('stays open and keeps its button when synced open', () => {
    el('toggle').click();
    overflow('content', false);
    syncBlock(el('root'));
    expect(el('toggle').hidden).toBe(false);
  });

  it('HR-L9: focus on a visible link leaves the fold alone', () => {
    linkAt(10, 30);
    el('link').dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
    expect(el('toggle').getAttribute('aria-expanded')).toBe('false');
  });

  it('HR-L9: isClipped sees below, above, and a fold the browser has scrolled', () => {
    linkAt(10, 30);
    expect(isClipped(el('link'), el('content'))).toBe(false);
    linkAt(100, 120);
    expect(isClipped(el('link'), el('content'))).toBe(true);
    linkAt(-40, -20);
    expect(isClipped(el('link'), el('content'))).toBe(true);
    linkAt(10, 30);
    el('content').scrollTop = 40;
    expect(isClipped(el('link'), el('content'))).toBe(true);
  });

  it('focus reaching something clipped opens the fold, so nothing focusable is out of sight', () => {
    linkAt(100, 120);
    el('link').dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
    expect(el('toggle').getAttribute('aria-expanded')).toBe('true');
    el('link').dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
    expect(el('toggle').getAttribute('aria-expanded')).toBe('true');
  });

  it('with nothing clipped, focus changes nothing', () => {
    linkAt(100, 120);
    overflow('content', false);
    syncBlock(el('root'));
    el('link').dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
    expect(el('toggle').getAttribute('aria-expanded')).toBe('false');
  });

  it('a block inserted later is picked up', async () => {
    const wrap = document.createElement('div');
    wrap.innerHTML = MARKUP.replace(/id="/g, 'id="late-');
    document.body.append(wrap);
    await vi.waitFor(() => expect(el('late-root').hasAttribute('data-nowo-ui-show-more-ready')).toBe(true));
  });

  it('syncAll takes the element itself', () => {
    el('root').removeAttribute('data-nowo-ui-show-more-ready');
    syncAll(el('root'));
    expect(el('root').hasAttribute('data-nowo-ui-show-more-ready')).toBe(true);
  });

  it('a block without its parts does nothing, and a missing label keeps the text', () => {
    document.body.innerHTML = '<div id="bare" data-nowo-ui-show-more></div><div id="half" data-nowo-ui-show-more><button id="t" data-nowo-ui-show-more-toggle aria-expanded="false">same</button></div>';
    syncBlock(el('bare'));
    setExpanded(el('bare'), true);
    expect(el('bare').hasAttribute('data-nowo-ui-show-more-ready')).toBe(false);
    setExpanded(el('half'), true, false);
    expect(el('t').textContent).toBe('same');
    el('t').click();
    expect(el('t').getAttribute('aria-expanded')).toBe('false');
  });

  it('HR-L7: a form named querySelectorAll in a batch does not stop a fold inserted with it', () => {
    const wrap = document.createElement('div');
    wrap.innerHTML = `<form id="bad"><input name="querySelectorAll"><input name="matches"></form>${MARKUP.replace(/id="/g, 'id="next-')}`;
    document.body.append(wrap);
    expect(() => syncAll(el('bad'))).not.toThrow();
    syncAll(wrap);
    expect(el('next-root').hasAttribute('data-nowo-ui-show-more-ready')).toBe(true);
  });

  it('HR-L7: an image named querySelectorAll in the document does not stop the folds', () => {
    const img = document.createElement('img');
    Object.defineProperty(document, 'querySelectorAll', { configurable: true, value: img });
    el('root').removeAttribute('data-nowo-ui-show-more-ready');
    try {
      syncAll();
      expect(el('root').hasAttribute('data-nowo-ui-show-more-ready')).toBe(true);
    } finally {
      delete (document as unknown as Record<string, unknown>).querySelectorAll;
    }
  });
});
