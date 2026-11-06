import { afterEach, describe, expect, it } from 'vitest';
import { DROPPED_ELEMENTS, sanitizeElement, sanitizeFragment } from './nowo-ui-fragment-filter';
import { DROPPED_ELEMENTS as FROM_CORE, parseFragment } from './nowo-ui-remote-form-core';

const element = (html: string): Element => {
  const template = document.createElement('template');
  template.innerHTML = html;
  return template.content.firstElementChild as Element;
};

describe('fragment filter', () => {
  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('is what the remote form core exports and uses', () => {
    expect(FROM_CORE).toBe(DROPPED_ELEMENTS);
    expect(parseFragment('<a href="javascript:x()" onclick="x()">a</a><script>x()</script>').querySelector('script')).toBeNull();
  });

  it.each(['<script id="r"></script>', '<iframe id="r" srcdoc="x"></iframe>', '<iframe id="r" src="javascript:x()"></iframe>', '<iframe id="r" src="data:text/html,x"></iframe>', '<meta id="r" http-equiv="refresh" content="0;url=/x">', '<base id="r" href="/x/">', '<object id="r" data="/x"></object>', '<embed id="r" src="/x">', '<svg><animate id="r" attributeName="href" to="javascript:x()"></animate></svg>'])('drops %s, also as the root', (html) => {
    const holder = element(`<div>${html}</div>`);
    expect(sanitizeElement(holder)).toBe(true);
    expect(holder.querySelector('#r')).toBeNull();
    const root = html.startsWith('<svg') ? (element(html).firstElementChild as Element) : element(html);
    expect(sanitizeElement(root)).toBe(false);
  });

  it('removes on* and script URLs on the root and below, and enters templates at every depth', () => {
    const root = element('<div onclick="x()"><a href="javascript:x()">a</a><template><img src="/i.png" onerror="x()"><template><form action="vbscript:x"><script></script></form></template></template></div>');
    expect(sanitizeElement(root)).toBe(true);
    expect(root.getAttributeNames()).toEqual([]);
    expect(root.querySelector('a')?.hasAttribute('href')).toBe(false);
    const outer = root.querySelector('template') as HTMLTemplateElement;
    expect(outer.content.querySelector('img')?.getAttributeNames()).toEqual(['src']);
    const inner = outer.content.querySelector('template') as HTMLTemplateElement;
    expect(inner.content.querySelector('form')?.hasAttribute('action')).toBe(false);
    expect(inner.content.querySelector('script')).toBeNull();
  });

  it('enters a root template too', () => {
    const root = element('<template><img src="/i.png" onerror="x()"></template>') as HTMLTemplateElement;
    expect(sanitizeElement(root)).toBe(true);
    expect(root.content.querySelector('img')?.hasAttribute('onerror')).toBe(false);
  });

  it('applies a stricter rule before deciding on the element', () => {
    const fragment = document.createDocumentFragment();
    fragment.append(element('<iframe srcdoc="x" title="t"></iframe>'));
    sanitizeFragment(fragment, (name) => name === 'srcdoc');
    expect(fragment.querySelector('iframe')?.getAttributeNames()).toEqual(['title']);
  });

  it('leaves legitimate markup byte-identical', () => {
    const markup = '<tr id="r1" class="row" data-id="1"><td data-label="Name"><a href="/row/1?x=1#y" title="t">Row</a> <a href="mailto:a@example.test">m</a> <a href="tel:+34600000000">t</a> <a href="">e</a></td><td><img src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" alt=""><svg viewBox="0 0 1 1"><use href="#icon"></use><animate attributeName="opacity" to="1"></animate></svg></td><td><form method="post" action="/row/1/delete" data-controller="confirm-submit"><input type="hidden" name="_token" value="placeholder"><button type="submit" formaction="/row/1/archive">Go</button></form><iframe src="https://example.test/embed"></iframe><iframe title="no src"></iframe><meta itemprop="position" content="1"><template><b>kept</b></template></td></tr>';
    const table = element(`<table><tbody>${markup}</tbody></table>`);
    const expected = table.outerHTML;
    expect(sanitizeElement(table)).toBe(true);
    expect(table.outerHTML).toBe(expected);
  });
});
