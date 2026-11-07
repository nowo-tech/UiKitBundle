import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { bindNowoUiShowMore, setExpanded, syncAll, syncBlock } from './nowo-ui-show-more';

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

describe('nowo-ui-show-more', () => {
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

  it('focus entering the fold opens it, so nothing focusable is out of sight', () => {
    el('link').dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
    expect(el('toggle').getAttribute('aria-expanded')).toBe('true');
    el('link').dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
    expect(el('toggle').getAttribute('aria-expanded')).toBe('true');
  });

  it('with nothing clipped, focus changes nothing', () => {
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
});
