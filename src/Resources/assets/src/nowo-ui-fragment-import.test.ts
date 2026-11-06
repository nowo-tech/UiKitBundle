import { afterEach, describe, expect, it, vi } from 'vitest';
import { importForPage } from './nowo-ui-fragment-filter';
import { parseFragment } from './nowo-ui-remote-form-core';

/**
 * In Chromium and WebKit a `style` attribute that arrives by cloning skips the page's CSP: `demo/symfony8/e2e/tests/imported-style.spec.ts`
 * sees that in real browsers. Here: the attribute must reach the copy by setAttribute in the page's document, never by the clone.
 */
describe('importForPage', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  const parsed = (html: string): Document => new DOMParser().parseFromString(html, 'text/html');

  it('imports with no style attribute, then sets each one on its own element in the page', () => {
    const doc = parsed('<div id="a" style="color: red"><i></i><b style="color: blue"><u style="color: green"></u></b></div>');
    const source = doc.getElementById('a')!;
    const seenAtClone: string[] = [];
    const real = Document.prototype.importNode;
    vi.spyOn(Document.prototype, 'importNode').mockImplementation(function (this: Document, node: Node, options?: boolean | ImportNodeOptions) {
      seenAtClone.push((node as Element).outerHTML);
      return real.call(this, node, options);
    });
    const set = vi.spyOn(Element.prototype, 'setAttribute');
    const copy = importForPage(source);
    expect(seenAtClone[0]).not.toContain(' style=');
    expect(copy.ownerDocument).toBe(document);
    expect(set.mock.calls.filter(([name]) => name === 'style').map(([, value]) => value)).toEqual(['color: red', 'color: blue', 'color: green']);
    expect(copy.getAttribute('style')).toBe('color: red');
    expect(copy.querySelector('i')!.hasAttribute('style')).toBe(false);
    expect(copy.querySelector('b')!.getAttribute('style')).toBe('color: blue');
    expect(copy.querySelector('u')!.getAttribute('style')).toBe('color: green');
  });

  it('keeps every other attribute, an empty style, and the structure', () => {
    const doc = parsed('<p id="a" class="c" data-x="1" style=""><span title="t">x</span></p>');
    const copy = importForPage(doc.getElementById('a')!);
    expect(copy.outerHTML).toBe('<p id="a" class="c" data-x="1" style=""><span title="t">x</span></p>');
  });

  it('takes text nodes as they are, and fragments', () => {
    const doc = parsed('<p>text</p>');
    const text = importForPage(doc.querySelector('p')!.firstChild!);
    expect(text.textContent).toBe('text');
    const fragment = doc.createDocumentFragment();
    const child = doc.createElement('em');
    child.setAttribute('style', 'color: red');
    fragment.append(child);
    const copy = importForPage(fragment);
    expect(copy.firstElementChild!.getAttribute('style')).toBe('color: red');
    expect(copy.firstElementChild!.ownerDocument).toBe(document);
  });

  it('a form with controls named like DOM members does not break it', () => {
    const doc = parsed('<form id="f" style="color: red"><input name="getAttribute"><input name="querySelectorAll"><input name="removeAttribute" style="color: blue"></form>');
    const copy = importForPage(doc.getElementById('f')!);
    expect(Element.prototype.getAttribute.call(copy, 'style')).toBe('color: red');
    expect(Element.prototype.querySelector.call(copy, '[name="removeAttribute"]')!.getAttribute('style')).toBe('color: blue');
  });

  it('parseFragment goes through it: in the page, with the attribute set there, and still filtered', () => {
    const set = vi.spyOn(Element.prototype, 'setAttribute');
    const fragment = parseFragment('<div style="color: red" onclick="x()">a<b style="color: blue"></b></div>');
    expect(set.mock.calls.filter(([name]) => name === 'style').map(([, value]) => value)).toEqual(['color: red', 'color: blue']);
    const div = fragment.firstElementChild!;
    expect(div.ownerDocument).toBe(document);
    expect(div.getAttribute('style')).toBe('color: red');
    expect(div.hasAttribute('onclick')).toBe(false);
    expect(div.querySelector('b')!.getAttribute('style')).toBe('color: blue');
  });

  it('HH-BL1: a custom element whose constructor adds children does not shift any style', () => {
    if (!customElements.get('x-grow-import')) {
      customElements.define(
        'x-grow-import',
        class extends HTMLElement {
          constructor() {
            super();
            const added = document.createElement('i');
            added.className = 'added';
            this.prepend(added);
          }
        },
      );
    }
    const doc = parsed('<div id="a"><x-grow-import id="g" style="color: red"></x-grow-import><p id="after" style="color: blue"></p><p id="sib" style="color: green"></p></div>');
    const copy = importForPage(doc.getElementById('a')!);
    const styleOf = (selector: string): string | null | undefined => copy.querySelector(selector)?.getAttribute('style');
    expect(styleOf('#g')).toBe('color: red');
    expect(styleOf('#after')).toBe('color: blue');
    expect(styleOf('#sib')).toBe('color: green');
    expect(styleOf('.added')).toBeNull();
    expect(copy.querySelector('[data-nowo-ui-import-style]')).toBeNull();
  });

  it('a mark that came in the fetched HTML picks nothing', () => {
    const doc = parsed('<div id="a"><p id="forged" data-nowo-ui-import-style="0"></p><p id="real" style="color: red"></p></div>');
    const copy = importForPage(doc.getElementById('a')!);
    expect(copy.querySelector('#forged')!.hasAttribute('style')).toBe(false);
    expect(copy.querySelector('#forged')!.hasAttribute('data-nowo-ui-import-style')).toBe(false);
    expect(copy.querySelector('#real')!.getAttribute('style')).toBe('color: red');
  });
});
