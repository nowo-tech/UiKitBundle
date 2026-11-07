import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  ALERT_ATTR,
  DEFAULT_SETTINGS,
  dialogFooter,
  firstInvalid,
  formDataWith,
  hasUnsafeSegments,
  isFragment,
  headerName,
  isPlainClick,
  clearBack,
  liftActions,
  liftBack,
  MAX_STEPS,
  NATIVE_ATTR,
  nestedLayerOwnsEscape,
  openerTitle,
  parseFragment,
  remoteNavigation,
  RemoteFormSession,
  sameOriginHref,
  sameOriginPath,
  submitButtons,
  submitTarget,
  urlEncoded,
  type RemoteFormSettings,
} from './nowo-ui-remote-form-core';

const tick = (): Promise<void> => new Promise((r) => setTimeout(r, 0));

export function polyfill(dialog: HTMLDialogElement): void {
  dialog.showModal = function showModal() {
    this.setAttribute('open', '');
  };
  dialog.close = function close() {
    if (!this.hasAttribute('open')) return;
    this.removeAttribute('open');
    this.dispatchEvent(new Event('close'));
  };
  Object.defineProperty(dialog, 'open', {
    configurable: true,
    get() {
      return this.hasAttribute('open');
    },
  });
}

type Reply = { status?: number; type?: string; headers?: Record<string, string>; body?: string };

export function reply({ status = 200, type = 'basic', headers = { 'content-type': 'text/html; charset=UTF-8' }, body = '' }: Reply = {}): Response {
  return { status, type, ok: status >= 200 && status < 300, headers: new Headers(headers), text: () => Promise.resolve(body) } as unknown as Response;
}

/**
 * What a browser does for a control named like a form property (happy-dom does not): the control wins over the DOM's
 * own property. happy-dom calls hasAttribute, getAttribute and setAttribute on `this` itself, so those three cannot be
 * shadowed here. All names were checked with real shadowing in Chromium, Firefox and WebKit before this was released.
 */
function shadow(form: HTMLFormElement, ...names: string[]): void {
  names.forEach((name) => {
    const control = document.createElement('input');
    control.name = name;
    Element.prototype.append.call(form, control);
    Object.defineProperty(form, name, { configurable: true, value: control });
  });
}

describe('remote form helpers', () => {
  it('headerName keeps header tokens only (2.33)', () => {
    expect(headerName('X-Beacon-Dialog', 'X-Nowo-Dialog')).toBe('X-Beacon-Dialog');
    for (const bad of [null, undefined, '', 'X Beacon', 'X:y', 'X-\r\nSet-Cookie', '-X', 'X'.repeat(65)]) {
      expect(headerName(bad, 'X-Nowo-Dialog')).toBe('X-Nowo-Dialog');
    }
  });

  it('accepts only same-origin path-absolute redirect targets', () => {
    expect(sameOriginPath('/a?b=1#c')).toBe('/a?b=1#c');
    expect(sameOriginPath('/')).toBe('/');
    for (const bad of [null, undefined, '', '//evil.test/x', '/\\evil.test', '/a\\b', 'https://evil.test/', `${window.location.origin}/same`, 'javascript:alert(1)', 'a/relative', '/a\u0000b', '/a\nb', `/${'x'.repeat(2048)}`]) {
      expect(sameOriginPath(bad)).toBeNull();
    }
    expect(sameOriginPath('/%0d%0a')).toBe('/%0d%0a');
  });

  it('refuses dot segments that would normalise to another origin (REQ-SEC-004 ES-M1)', () => {
    for (const dotted of [
      '/.//evil.example/x',
      '/..//evil.example/x',
      '/%2e//evil.example/x',
      '/%2E%2E//evil.example/x',
      '/a/..//evil.example/x',
      '/%2e%2E//evil.example/x',
      '/.%2e//evil.example/x',
      '/a/%2E/b',
      '/a/./b',
      '/a/..',
      '/.',
      '/%252e//evil.example/x',
      '/%2F%2Fevil.example',
      '/a%5c..%5cb',
      '/./?x=1',
      '/%E0%A4%A',
      '/files/a%2Eb',
      '/x%2etxt',
    ]) {
      expect(sameOriginPath(dotted), dotted).toBeNull();
    }
    expect(hasUnsafeSegments('/a/b')).toBe(false);
    expect(sameOriginPath('/files/a..b/.well-known/x.y')).toBe('/files/a..b/.well-known/x.y');
    expect(sameOriginPath('/x?next=/../evil#..')).toBe('/x?next=/../evil#..');
  });

  it('strips what could run or navigate from a fragment', () => {
    const fragment = parseFragment(`<form onsubmit="x()"><input onfocus="y()" name="a"></form><iframe srcdoc="<script>1</script>"></iframe>
      <iframe src="/frame"></iframe><meta http-equiv="refresh" content="0;url=https://evil.example"><base href="https://evil.example/">
      <object data="x"></object><embed src="x"><img src="x" ONERROR="z()">`);
    expect(fragment.querySelector('iframe[srcdoc], meta, base, object, embed')).toBeNull();
    expect(fragment.querySelector('iframe[src="/frame"]')).not.toBeNull();
    expect(fragment.querySelector('form')?.hasAttribute('onsubmit')).toBe(false);
    expect(fragment.querySelector('input')?.hasAttribute('onfocus')).toBe(false);
    expect(fragment.querySelector('img')?.attributes.length).toBe(1);
  });

  describe('fragment filter (audit GT)', () => {
    it.each([
      ['plain', '<iframe src="javascript:alert(1)"></iframe>'],
      ['tab and blank', '<iframe src=" java&#9;script:alert(1)"></iframe>'],
      ['newline', '<iframe src="java&#10;script:alert(1)"></iframe>'],
      ['mixed case', '<IFRAME SRC="JaVaScRiPt:alert(1)"></IFRAME>'],
      ['inside foreignObject', '<svg><foreignObject><iframe src="javascript:alert(1)"></iframe></foreignObject></svg>'],
      ['data', '<iframe src="data:text/html,<script>alert(1)</script>"></iframe>'],
      ['about', '<iframe src="about:blank"></iframe>'],
      ['unparsable', '<iframe src="http://["></iframe>'],
    ])('GT-L1: removes an iframe whose src is not http(s): %s', (_name, html) => {
      const fragment = parseFragment(`<p>x</p>${html}`);
      expect(fragment.querySelector('iframe')).toBeNull();
      expect(fragment.querySelector('p')?.textContent).toBe('x');
    });

    it.each([
      ['relative', '<iframe src="/frame"></iframe>'],
      ['empty', '<iframe src=""></iframe>'],
      ['other origin', '<iframe src="https://maps.example/embed"></iframe>'],
      ['protocol relative', '<iframe src="//maps.example/embed"></iframe>'],
      ['no src', '<iframe name="target"></iframe>'],
    ])('GT-L1: keeps a legitimate iframe: %s', (_name, html) => {
      expect(parseFragment(html).querySelector('iframe')).not.toBeNull();
    });

    it.each([
      ['a', '<a id="t" href="javascript:alert(1)">x</a>', 'href'],
      ['a obfuscated', '<a id="t" href=" java&#9;script:alert(1)">x</a>', 'href'],
      ['a vbscript', '<a id="t" href="VBScript:x">x</a>', 'href'],
      ['area', '<map><area id="t" href="javascript:alert(1)"></map>', 'href'],
      ['svg a', '<svg><a id="t" href="javascript:alert(1)"><rect></rect></a></svg>', 'href'],
      ['svg a xlink', '<svg><a id="t" xlink:href="javascript:alert(1)"><rect></rect></a></svg>', 'xlink:href'],
      ['form', '<form id="t" method="post" action="javascript:alert(1)"></form>', 'action'],
      ['button', '<form><button id="t" formaction="javascript:alert(1)">x</button></form>', 'formaction'],
      ['input', '<form><input id="t" type="submit" formaction="javascript:alert(1)"></form>', 'formaction'],
    ])('GT-I1: strips a script URL: %s', (_name, html, attribute) => {
      const element = parseFragment(html).querySelector('#t');
      expect(element).not.toBeNull();
      expect(element?.hasAttribute(attribute)).toBe(false);
    });

    it.each([
      ['relative', '/x'],
      ['query', '?a=javascript:b'],
      ['hash', '#javascript:b'],
      ['other origin', 'https://other.example/x'],
      ['mailto', 'mailto:a@example.com'],
      ['tel', 'tel:+34600000000'],
      ['data image', 'data:image/png;base64,AAAA'],
      ['unparsable', 'http://['],
    ])('GT-I1: keeps a legitimate URL: %s', (_name, url) => {
      const fragment = parseFragment(`<a id="a" href="${url}">x</a><form id="f" action="${url}"><button id="b" formaction="${url}">x</button></form>`);
      expect(fragment.querySelector('#a')?.getAttribute('href')).toBe(url);
      expect(fragment.querySelector('#f')?.getAttribute('action')).toBe(url);
      expect(fragment.querySelector('#b')?.getAttribute('formaction')).toBe(url);
    });

    it('GT-I1: removes SVG animations of href and keeps the others', () => {
      const fragment = parseFragment(`<svg><a id="a"><animate attributeName="href" to="javascript:alert(1)"></animate><set attributeName="xlink:href" to="javascript:alert(1)"></set>
        <animate attributeName=" HREF " values="/x;javascript:alert(1)"></animate><rect><animate id="keep" attributeName="x" to="1"></animate><set id="keep2" attributeName="fill" to="red"></set></rect></a></svg>`);
      expect(fragment.querySelectorAll('animate').length).toBe(1);
      expect(fragment.querySelectorAll('set').length).toBe(1);
      expect(fragment.querySelector('#keep')).not.toBeNull();
      expect(fragment.querySelector('#keep2')).not.toBeNull();
    });

    it('GT-I1: filters nested template content at any depth', () => {
      const fragment = parseFragment(`<template id="t1"><img id="i" src="/x" onerror="x()"><script>x()</script><a id="l" href="javascript:x()">x</a>
        <iframe src="javascript:x()"></iframe><template id="t2"><button id="b" onclick="x()">x</button><meta http-equiv="refresh" content="0"></template></template>`);
      const outer = (fragment.querySelector('#t1') as HTMLTemplateElement).content;
      expect(outer.querySelector('#i')?.hasAttribute('onerror')).toBe(false);
      expect(outer.querySelector('#i')?.getAttribute('src')).toBe('/x');
      expect(outer.querySelector('script, iframe')).toBeNull();
      expect(outer.querySelector('#l')?.hasAttribute('href')).toBe(false);
      const inner = (outer.querySelector('#t2') as HTMLTemplateElement).content;
      expect(inner.querySelector('#b')?.hasAttribute('onclick')).toBe(false);
      expect(inner.querySelector('meta')).toBeNull();
    });

    // `getAttribute` is left to the browser e2e (fragment-filter.spec.ts): happy-dom itself calls it on the form.
    describe.each(['attributes', 'removeAttribute', 'getAttributeNames', 'remove', 'matches', 'localName', 'tagName', 'children', 'parentNode', 'querySelectorAll', 'content'])(
      'GT-L3: a form control named %s',
      (name) => {
        const form = `<form class="c" onsubmit="x()" onfocusin="x()" action="javascript:x()"><input name="${name}" autofocus><button formaction="javascript:x()">go</button></form>`;
        const names = (element: Element): string[] => Element.prototype.getAttributeNames.call(element);

        // happy-dom has no named properties on a form: behave as browsers do, for the control this block names.
        beforeEach(() => {
          Object.defineProperty(HTMLFormElement.prototype, name, {
            configurable: true,
            get(this: HTMLFormElement) {
              return Element.prototype.querySelector.call(this, `[name="${name}"]`);
            },
          });
        });
        afterEach(() => {
          delete (HTMLFormElement.prototype as unknown as Record<string, unknown>)[name];
        });

        it('does not hide the form from the filter', () => {
          const element = parseFragment(form).querySelector('.c') as Element;
          expect(names(element)).toEqual(['class']);
          expect(element.querySelector('button')?.hasAttribute('formaction')).toBe(false);
          expect(element.querySelector('input')?.getAttribute('name')).toBe(name);
        });

        it('does not hide it inside a nested template', () => {
          const outer = (parseFragment(`<template id="o"><template id="i">${form}</template>${form}</template>`).querySelector('#o') as HTMLTemplateElement).content;
          expect(names(outer.querySelector('form.c') as Element)).toEqual(['class']);
          const inner = (outer.querySelector('#i') as HTMLTemplateElement).content;
          expect(names(inner.querySelector('.c') as Element)).toEqual(['class']);
        });

        it('keeps the legitimate attributes of such a form', () => {
          const element = parseFragment(`<form class="c" method="post" action="/save"><input name="${name}"></form>`).querySelector('.c') as Element;
          expect(names(element)).toEqual(['class', 'method', 'action']);
        });
      },
    );

    it('leaves an ordinary form fragment untouched (no behaviour change for legitimate content)', () => {
      const html = `<div id="c" data-title="T"><form method="post" action="/save" class="f"><input type="hidden" name="_token" value="abc"><label for="n">N</label><input id="n" name="n" required autofocus>
        <a href="/help" target="_blank" rel="noopener">Help</a><template id="row"><div class="row"><input name="r[__name__]"></div></template><div class="a"><button type="submit">Save</button><button type="button" data-nowo-ui-dialog-close>Cancel</button></div></form></div>`;
      const reference = document.createElement('template');
      reference.innerHTML = html;
      const wrapper = document.createElement('div');
      wrapper.append(parseFragment(html));
      const expected = document.createElement('div');
      expected.append(reference.content);
      expect(wrapper.innerHTML).toBe(expected.innerHTML);
    });
  });

  it('resolves submit targets and data like a native submit (ES-L2, ES-I1)', () => {
    document.body.innerHTML = `<form id="f" method="post" action="/save"><input type="hidden" name="op" value="hidden">
      <button id="plain" type="submit">Save</button>
      <button id="again" type="submit" name="op" value="again">Save + new</button>
      <button id="del" type="submit" name="del" value="1" formaction="/delete" formmethod="post" formenctype="application/x-www-form-urlencoded">Delete</button>
      <button id="get" type="submit" formmethod="get">Search</button></form>
      <form id="bare" method="post"></form>`;
    const form = document.getElementById('f') as HTMLFormElement;
    const button = (id: string): HTMLElement => document.getElementById(id) as HTMLElement;
    expect(submitTarget(form, button('plain'), '/dialog')).toMatchObject({ action: `${window.location.origin}/save`, method: 'post' });
    expect(submitTarget(form, button('del'), '/dialog')).toMatchObject({ action: `${window.location.origin}/delete`, method: 'post', enctype: 'application/x-www-form-urlencoded' });
    expect(submitTarget(form, button('get'), '/dialog').method).toBe('get');
    expect(submitTarget(document.getElementById('bare') as HTMLFormElement, null, '/dialog').action).toBe('/dialog');
    expect(formDataWith(form, button('again')).getAll('op')).toEqual(['hidden', 'again']);
    expect(formDataWith(form, button('plain')).getAll('op')).toEqual(['hidden']);
    expect(formDataWith(form, null).getAll('op')).toEqual(['hidden']);
    const encoded = urlEncoded(formDataWith(form, button('del')));
    expect(encoded.get('del')).toBe('1');
    const withFile = new FormData();
    withFile.append('file', new File(['x'], 'a.txt'));
    expect(urlEncoded(withFile).get('file')).toBe('a.txt');
  });

  it('falls back when FormData ignores or refuses the submitter', () => {
    document.body.innerHTML = '<form id="f"><button id="b" name="go" value="1">Go</button></form>';
    const Native = window.FormData;
    class NoSubmitter extends Native {
      constructor(form?: HTMLFormElement, submitter?: HTMLElement | null) {
        if (submitter) throw new TypeError('unsupported');
        super(form);
      }
    }
    vi.stubGlobal('FormData', NoSubmitter);
    try {
      expect(formDataWith(document.getElementById('f') as HTMLFormElement, document.getElementById('b')).getAll('go')).toEqual(['1']);
    } finally {
      vi.unstubAllGlobals();
    }
  });

  it('resolves fetch targets on this origin only', () => {
    expect(sameOriginHref('/form')).toBe(`${window.location.origin}/form`);
    expect(sameOriginHref('https://evil.test/form')).toBeNull();
    expect(sameOriginHref('javascript:alert(1)')).toBeNull();
    expect(sameOriginHref('  ')).toBeNull();
    expect(sameOriginHref(null)).toBeNull();
    expect(sameOriginHref('http://[::1')).toBeNull();
  });

  it('tells fragments from whole documents and non-HTML answers', () => {
    expect(isFragment('text/html; charset=UTF-8', '<form></form>')).toBe(true);
    expect(isFragment('text/html', '<!DOCTYPE html><html></html>')).toBe(false);
    expect(isFragment('text/html', '  <HTML lang="en">')).toBe(false);
    expect(isFragment('application/json', '{}')).toBe(false);
    expect(isFragment(null, '<p></p>')).toBe(false);
  });

  it('parses in an inert template and drops scripts', () => {
    const fragment = parseFragment('<p id="x">a</p><script>window.__evil = 1</script>');
    expect(fragment.querySelector('script')).toBeNull();
    expect(fragment.querySelector('#x')?.textContent).toBe('a');
  });

  it('finds the first invalid control, then the host selector', () => {
    document.body.innerHTML = '<div id="r"><div class="err"><input id="b"></div><input id="a" aria-invalid="true"></div>';
    const root = document.getElementById('r') as HTMLElement;
    expect(firstInvalid(root)?.id).toBe('a');
    root.querySelector('#a')?.removeAttribute('aria-invalid');
    expect(firstInvalid(root)).toBeNull();
    expect(firstInvalid(root, '.err input')?.id).toBe('b');
    expect(firstInvalid(root, '[[bad')).toBeNull();
  });

  it('reads opener titles and plain clicks', () => {
    const a = document.createElement('a');
    a.textContent = '  Add  ';
    expect(openerTitle(a, undefined)).toBe('Add');
    a.setAttribute('aria-label', 'Add destination');
    expect(openerTitle(a, undefined)).toBe('Add destination');
    expect(openerTitle(a, 'Explicit')).toBe('Explicit');
    expect(isPlainClick(new Event('click'))).toBe(true);
    expect(isPlainClick(new MouseEvent('click', { button: 0 }))).toBe(true);
    expect(isPlainClick(new MouseEvent('click', { button: 0, ctrlKey: true }))).toBe(false);
    expect(isPlainClick(new MouseEvent('click', { button: 1 }))).toBe(false);
  });

  it('lets nested layers own Escape', () => {
    document.body.innerHTML = `<dialog id="d"><div class="ts-wrapper dropdown-active"><input id="ts"></div><input id="plain">
      <dialog id="inner" open><input id="in"></dialog></dialog><input id="out">`;
    const dialog = document.getElementById('d') as HTMLDialogElement;
    expect(nestedLayerOwnsEscape(dialog, document.getElementById('ts'))).toBe(true);
    expect(nestedLayerOwnsEscape(dialog, document.getElementById('plain'))).toBe(false);
    expect(nestedLayerOwnsEscape(dialog, document.getElementById('in'))).toBe(true);
    expect(nestedLayerOwnsEscape(dialog, document.getElementById('out'))).toBe(false);
    expect(nestedLayerOwnsEscape(dialog, null)).toBe(false);
  });
});

describe('dialog back button', () => {
  const setup = (body: string, header = ''): { dialog: HTMLDialogElement; body: HTMLElement; header: HTMLElement } => {
    document.body.innerHTML = `<dialog id="d"><header id="h">${header}<h2 id="t">Title</h2><button id="x" type="button">×</button></header><div id="b">${body}</div></dialog>`;
    return { dialog: document.getElementById('d') as HTMLDialogElement, body: document.getElementById('b') as HTMLElement, header: document.getElementById('h') as HTMLElement };
  };

  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('moves the button itself into the header, before the title, and it keeps its listeners', () => {
    const { dialog, body, header } = setup('<form><button id="back" type="button" data-nowo-ui-dialog-back>Back</button></form>');
    const back = document.getElementById('back') as HTMLButtonElement;
    const clicked = vi.fn();
    back.addEventListener('click', clicked);
    expect(liftBack(body, dialog)).toBe(true);
    expect(document.getElementById('back')).toBe(back);
    expect(header.firstElementChild).toBe(back);
    expect(body.contains(back)).toBe(false);
    expect(back.classList.contains('nowo-ui-dialog__back')).toBe(true);
    back.click();
    expect(clicked).toHaveBeenCalledTimes(1);
  });

  it('a new fragment replaces the button, and one without any takes it away', () => {
    const { dialog, body, header } = setup('<button id="first" type="button" data-nowo-ui-dialog-back>1</button>');
    liftBack(body, dialog);
    body.innerHTML = '<button id="second" type="button" data-nowo-ui-dialog-back>2</button>';
    expect(liftBack(body, dialog)).toBe(true);
    expect(document.getElementById('first')).toBeNull();
    expect(header.firstElementChild?.id).toBe('second');
    body.innerHTML = '<p>no back here</p>';
    expect(liftBack(body, dialog)).toBe(false);
    expect(document.getElementById('second')).toBeNull();
    expect(header.firstElementChild?.id).toBe('t');
  });

  it.each([
    ['a submit button', '<button id="n" data-nowo-ui-dialog-back>x</button>'],
    ['an explicit submit', '<button id="n" type="submit" data-nowo-ui-dialog-back>x</button>'],
    ['a link', '<a id="n" href="/x" data-nowo-ui-dialog-back>x</a>'],
  ])('leaves %s where it is', (_name, html) => {
    const { dialog, body } = setup(html);
    expect(liftBack(body, dialog)).toBe(false);
    expect(body.contains(document.getElementById('n'))).toBe(true);
  });

  it('hides the back_href arrow while a button is there, and shows it again', () => {
    const { dialog, body } = setup('<button id="back" type="button" data-nowo-ui-dialog-back>B</button>', '<a id="arrow" class="nowo-ui-dialog__back" href="/up">↑</a>');
    liftBack(body, dialog);
    expect((document.getElementById('arrow') as HTMLElement).hidden).toBe(true);
    clearBack(dialog);
    expect((document.getElementById('arrow') as HTMLElement).hidden).toBe(false);
    expect(document.getElementById('back')).toBeNull();
  });

  it('a form in the fragment with controls named like DOM members hides nothing', () => {
    const { dialog, body, header } = setup('<form id="f"><input name="querySelector"><input name="closest"><button id="back" type="button" data-nowo-ui-dialog-back>B</button></form>');
    expect(liftBack(body, dialog)).toBe(true);
    expect(header.firstElementChild?.id).toBe('back');
  });

  it('a dialog with no header does nothing', () => {
    document.body.innerHTML = '<dialog id="d"><div id="b"><button id="back" type="button" data-nowo-ui-dialog-back>B</button></div></dialog>';
    const dialog = document.getElementById('d') as HTMLDialogElement;
    clearBack(dialog);
    expect(liftBack(document.getElementById('b') as HTMLElement, dialog)).toBe(false);
  });
});

describe('dialog footer (2.29)', () => {
  afterEach(() => {
    document.body.innerHTML = '';
  });

  describe('controls named like form properties (GT-I7)', () => {
    it('still lifts the actions with a shadowed id, ownerDocument, isConnected or closest', () => {
      document.body.innerHTML = '<div id="b"><form id="f"><div class="nowo-ui-form__actions"><button type="submit" id="go">Go</button></div></form></div><footer id="foot" hidden></footer>';
      const form = document.getElementById('f') as HTMLFormElement;
      shadow(form, 'id', 'ownerDocument', 'isConnected', 'closest');
      const footer = document.getElementById('foot') as HTMLElement;
      expect(liftActions(document.getElementById('b') as HTMLElement, footer)).toBe(true);
      expect(footer.querySelector('#go')?.getAttribute('form')).toBe('f');
    });

    it('gives an anonymous form an id through the prototype', () => {
      document.body.innerHTML = '<div id="b"><form><div class="nowo-ui-form__actions"><button type="submit" id="go">Go</button></div></form></div><footer id="foot" hidden></footer>';
      const form = document.querySelector('form') as HTMLFormElement;
      shadow(form, 'id');
      expect(liftActions(document.getElementById('b') as HTMLElement, document.getElementById('foot'))).toBe(true);
      const id = Element.prototype.getAttribute.call(form, 'id') as string;
      expect(id).toMatch(/^nowo-ui-remote-form-\d+$/);
      expect(document.getElementById('go')?.getAttribute('form')).toBe(id);
    });

    it('lifts a form that is its own actions block', () => {
      document.body.innerHTML = '<div id="b"><form id="f" data-nowo-ui-dialog-actions><button type="submit" id="go">Go</button></form></div><footer id="foot" hidden></footer>';
      const form = document.getElementById('f') as HTMLFormElement;
      shadow(form, 'closest', 'querySelectorAll', 'childNodes', 'remove', 'append');
      expect(liftActions(document.getElementById('b') as HTMLElement, document.getElementById('foot'))).toBe(true);
      expect(document.getElementById('foot')?.querySelector('#go')).not.toBeNull();
    });

    it('resolves the submit target and the submit buttons', () => {
      document.body.innerHTML = '<form method="post" action="/save" enctype="multipart/form-data"><button type="submit" id="go">Go</button></form>';
      const form = document.querySelector('form') as HTMLFormElement;
      shadow(form, 'action', 'method', 'enctype', 'elements');
      expect(submitTarget(form, null, null)).toEqual({ action: `${window.location.origin}/save`, method: 'post', enctype: 'multipart/form-data' });
      expect(submitButtons(form).map((button) => button.id)).toEqual(['go']);
    });
  });

  it('moves the form actions into the footer and binds them to the form', () => {
    document.body.innerHTML = `<div id="b"><form method="post" action="/x"><input name="n"><div class="nowo-ui-form__actions d-flex"><a href="/back" id="cancel">Cancel</a><button id="save">Save</button><button type="button" id="other" form="own">Other</button></div></form></div><footer id="f" hidden>stale</footer>`;
    const body = document.getElementById('b') as HTMLElement;
    const footer = document.getElementById('f') as HTMLElement;
    expect(liftActions(body, footer)).toBe(true);
    const form = body.querySelector('form') as HTMLFormElement;
    expect(form.id).toMatch(/^nowo-ui-remote-form-\d+$/);
    expect(footer.hidden).toBe(false);
    expect(Array.from(footer.children).map((child) => child.id)).toEqual(['cancel', 'save', 'other']);
    expect(footer.textContent).not.toContain('stale');
    expect(document.getElementById('save')?.getAttribute('form')).toBe(form.id);
    expect(document.getElementById('other')?.getAttribute('form')).toBe('own');
    expect(document.getElementById('cancel')?.hasAttribute('form')).toBe(false);
    expect(body.querySelector('.nowo-ui-form__actions')).toBeNull();
    expect(submitButtons(form).map((button) => button.id)).toEqual(['save']);
  });

  it('prefers the explicit actions container, keeps a form id and honours the opt-out', () => {
    document.body.innerHTML = `<div id="b"><form id="mine" method="post"><div class="nowo-ui-form__actions"><button id="inner">Inner</button></div><div data-nowo-ui-dialog-actions><button id="explicit">Go</button></div></form></div><footer id="f"></footer>`;
    const body = document.getElementById('b') as HTMLElement;
    const footer = document.getElementById('f') as HTMLElement;
    expect(liftActions(body, footer)).toBe(true);
    expect(footer.querySelector('#explicit')?.getAttribute('form')).toBe('mine');
    expect(body.querySelector('#inner')).not.toBeNull();

    body.innerHTML = '<form data-nowo-ui-dialog-keep-actions><div class="nowo-ui-form__actions"><button>Save</button></div></form>';
    expect(liftActions(body, footer)).toBe(false);
    expect(footer.hidden).toBe(true);
    expect(footer.childNodes).toHaveLength(0);

    body.innerHTML = '<div data-nowo-ui-dialog-actions><a href="/x">Close</a></div><form><div class="nowo-ui-form__actions"></div></form>';
    expect(liftActions(body, footer)).toBe(true);
    expect(footer.querySelector('a')?.hasAttribute('form')).toBe(false);

    body.innerHTML = '<form><div class="nowo-ui-form__actions"></div></form>';
    expect(liftActions(body, footer)).toBe(false);
    expect(liftActions(body, null)).toBe(false);
  });

  it('gives the fragment form its own id when another form of the page holds it, and rebinds its references (FK-L1)', () => {
    document.body.innerHTML = `<form id="edit" method="post" action="/page"><button id="page-save">Save</button></form><div id="b"><form id="edit" method="post" action="/x"><input name="n"><div class="nowo-ui-form__actions"><button id="save">Save</button><button type="button" id="bound" form="edit">Other</button></div></form></div><footer id="f" hidden></footer>`;
    const body = document.getElementById('b') as HTMLElement;
    const footer = document.getElementById('f') as HTMLElement;
    const form = body.querySelector('form') as HTMLFormElement;
    const page = document.body.querySelector('form') as HTMLFormElement;
    expect(liftActions(body, footer)).toBe(true);
    expect(form.id).toMatch(/^nowo-ui-remote-form-\d+$/);
    // The original id stays readable for host CSS / scripts / tests (2.29.1, FK-I7); the page form keeps its own.
    expect(form.getAttribute('data-nowo-ui-form-id')).toBe('edit');
    expect(page.hasAttribute('data-nowo-ui-form-id')).toBe(false);
    expect(page.id).toBe('edit');
    expect((footer.querySelector('#save') as HTMLButtonElement).form).toBe(form);
    expect((footer.querySelector('#bound') as HTMLButtonElement).form).toBe(form);
    expect(submitButtons(page).map((button) => button.id)).toEqual(['page-save']);
    expect(submitButtons(form).map((button) => button.id)).toEqual(['save']);
  });

  it('gives the fragment form its own id when a non-form element of the page holds it (FK-L1)', () => {
    document.body.innerHTML = `<div id="edit">not a form</div><div id="nowo-ui-remote-form-taken"></div><div id="b"><form id="edit" method="post" action="/x"><div class="nowo-ui-form__actions"><button id="save">Save</button></div></form></div><footer id="f" hidden></footer>`;
    const body = document.getElementById('b') as HTMLElement;
    const footer = document.getElementById('f') as HTMLElement;
    const form = body.querySelector('form') as HTMLFormElement;
    expect(liftActions(body, footer)).toBe(true);
    expect(form.id).not.toBe('edit');
    expect(document.querySelector('[data-nowo-ui-form-id="edit"]')).toBe(form);
    expect(document.querySelectorAll(`[id="${form.id}"]`)).toHaveLength(1);
    expect((footer.querySelector('#save') as HTMLButtonElement).form).toBe(form);
  });

  it('skips a generated id another element already holds (FK-L1)', () => {
    document.body.innerHTML = '<div id="b"><form><div class="nowo-ui-form__actions"><button id="probe">Save</button></div></form></div><footer id="f"></footer>';
    const body = document.getElementById('b') as HTMLElement;
    const footer = document.getElementById('f') as HTMLElement;
    expect(liftActions(body, footer)).toBe(true);
    const next = Number((body.querySelector('form') as HTMLFormElement).id.replace('nowo-ui-remote-form-', '')) + 1;
    body.innerHTML = '<form><div class="nowo-ui-form__actions"><button id="save">Save</button></div></form>';
    const taken = document.createElement('span');
    taken.id = `nowo-ui-remote-form-${next}`;
    document.body.append(taken);
    expect(liftActions(body, footer)).toBe(true);
    const form = body.querySelector('form') as HTMLFormElement;
    expect(form.id).toBe(`nowo-ui-remote-form-${next + 1}`);
    expect((footer.querySelector('#save') as HTMLButtonElement).form).toBe(form);
  });

  it('leaves the actions in place when the moved controls would not resolve to the form (FK-L1)', () => {
    document.body.innerHTML = '<form id="edit" action="/page"></form><footer id="f"></footer>';
    const footer = document.getElementById('f') as HTMLElement;
    const body = document.createElement('div');
    body.innerHTML = '<form id="edit" method="post" action="/x"><div class="nowo-ui-form__actions"><button id="save">Save</button><button type="button" id="own" form="other">Other</button></div></form>';
    expect(liftActions(body, footer)).toBe(false);
    expect(footer.hidden).toBe(true);
    expect(footer.childNodes).toHaveLength(0);
    const save = body.querySelector('.nowo-ui-form__actions #save') as HTMLButtonElement;
    expect(save).not.toBeNull();
    expect(save.hasAttribute('form')).toBe(false);
    expect(save.form).toBe(body.querySelector('form'));
    expect(body.querySelector('#own')?.getAttribute('form')).toBe('other');
  });

  it('finds the footer among the dialog children only', () => {
    document.body.innerHTML = '<dialog id="d"><div><footer data-nowo-ui-dialog-footer id="nested"></footer></div><footer data-nowo-ui-dialog-footer id="own"></footer></dialog><dialog id="e"></dialog>';
    expect(dialogFooter(document.getElementById('d') as HTMLDialogElement)?.id).toBe('own');
    expect(dialogFooter(document.getElementById('e') as HTMLDialogElement)).toBeNull();
  });
});

describe('RemoteFormSession', () => {
  let dialog: HTMLDialogElement;
  let session: RemoteFormSession;
  let settings: RemoteFormSettings;
  let events: Array<[string, Record<string, unknown>, boolean]>;
  let prevent: Set<string>;
  let assign: ReturnType<typeof vi.spyOn>;
  let reload: ReturnType<typeof vi.spyOn>;
  const body = (): HTMLElement => dialog.querySelector('[data-body]') as HTMLElement;
  const opener = (): HTMLElement => document.getElementById('opener') as HTMLElement;

  const submitForm = async (form: HTMLFormElement, submitter: HTMLElement | null = null): Promise<Event> => {
    const event = new Event('submit', { bubbles: true, cancelable: true }) as SubmitEvent;
    Object.defineProperty(event, 'submitter', { value: submitter });
    form.dispatchEvent(event);
    await tick();
    await tick();
    return event;
  };

  const openWith = async (response: Response, href = '/form'): Promise<boolean> => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(response));
    const opened = session.open(href, opener(), 'Edit');
    await tick();
    await tick();
    return opened;
  };

  beforeEach(() => {
    document.body.innerHTML = `<a id="opener" href="/form">Open</a>
      <dialog id="d"><h2 id="d-title"></h2><div data-body></div></dialog>`;
    dialog = document.getElementById('d') as HTMLDialogElement;
    polyfill(dialog);
    settings = { ...DEFAULT_SETTINGS, loading: 'Loading', error: 'Failed', loadError: 'Load failed' };
    events = [];
    prevent = new Set();
    session = new RemoteFormSession(
      dialog,
      () => document.getElementById('d-title'),
      body,
      () => settings,
      (name, detail, cancelable = false) => {
        events.push([name, detail, cancelable]);
        return !(cancelable && prevent.has(name));
      },
    );
    session.bind();
    assign = vi.spyOn(remoteNavigation, 'assign').mockImplementation(() => undefined);
    reload = vi.spyOn(remoteNavigation, 'reload').mockImplementation(() => undefined);
  });

  afterEach(() => {
    session.destroy();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    document.body.innerHTML = '';
    window.NowoUiOverlay?.releaseAll();
  });

  it('opens, sends the dialog headers, injects the fragment and focuses [autofocus]', async () => {
    const fetchMock = vi.fn().mockResolvedValue(reply({ body: '<form method="post" action="/form"><input id="f" autofocus></form>' }));
    vi.stubGlobal('fetch', fetchMock);
    expect(session.open('/form', opener(), 'Edit')).toBe(true);
    expect(dialog.open).toBe(true);
    expect(document.getElementById('d-title')?.textContent).toBe('Edit');
    expect(document.getElementById('d-title')?.getAttribute('tabindex')).toBe('-1');
    expect(body().getAttribute('aria-busy')).toBe('true');
    expect(body().querySelector('[role="status"]')?.textContent).toBe('Loading');
    expect(document.documentElement.getAttribute('data-nowo-overlay')).toBe('1');
    await tick();
    await tick();
    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe(`${window.location.origin}/form`);
    expect(init).toMatchObject({ credentials: 'same-origin', mode: 'same-origin', redirect: 'manual' });
    expect(init.headers).toMatchObject({ 'X-Nowo-Dialog': '1', 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' });
    expect(body().hasAttribute('aria-busy')).toBe(false);
    expect(document.activeElement?.id).toBe('f');
    expect(events[0]).toEqual(['loaded', { url: `${window.location.origin}/form` }, false]);
    expect(session.currentUrl()).toBe(`${window.location.origin}/form`);
  });

  it('fills the footer with the fragment actions, submits through them and empties it on reload and close (2.29)', async () => {
    const footer = document.createElement('footer');
    footer.setAttribute('data-nowo-ui-dialog-footer', '');
    footer.hidden = true;
    dialog.append(footer);
    await openWith(reply({ body: '<form method="post" action="/form"><input name="name" value="A"><div class="nowo-ui-form__actions"><button type="submit" id="save" name="go" value="1">Save</button></div></form>' }));
    expect(footer.hidden).toBe(false);
    const save = footer.querySelector('#save') as HTMLButtonElement;
    expect(save).not.toBeNull();
    const form = body().querySelector('form') as HTMLFormElement;
    expect(save.form).toBe(form);
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply({ status: 422, body: '<form method="post" action="/form"><input name="name" aria-invalid="true"><div class="nowo-ui-form__actions"><button type="submit" id="save2">Save</button></div></form>' })));
    const event = new Event('submit', { bubbles: true, cancelable: true }) as SubmitEvent;
    Object.defineProperty(event, 'submitter', { value: save });
    form.dispatchEvent(event);
    expect(save.disabled).toBe(true);
    await tick();
    await tick();
    expect(event.defaultPrevented).toBe(true);
    expect(footer.querySelector('#save')).toBeNull();
    expect(footer.querySelector('#save2')).not.toBeNull();
    session.open('/form', opener(), 'Edit');
    expect(footer.hidden).toBe(true);
    expect(footer.childNodes).toHaveLength(0);
    await tick();
    await tick();
    dialog.close();
    expect(footer.hidden).toBe(true);
    expect(footer.childNodes).toHaveLength(0);
  });

  it('empties the footer when a load fails and the dialog stays open (2.29)', async () => {
    const footer = document.createElement('footer');
    footer.setAttribute('data-nowo-ui-dialog-footer', '');
    dialog.append(footer);
    settings.followOnLoadError = false;
    await openWith(reply({ body: '<form method="post" action="/form"><div class="nowo-ui-form__actions"><button>Save</button></div></form>' }));
    expect(footer.hidden).toBe(false);
    await openWith(reply({ status: 500 }));
    expect(dialog.open).toBe(true);
    expect(body().querySelector('[role="alert"]')).not.toBeNull();
    expect(footer.hidden).toBe(true);
    expect(footer.childNodes).toHaveLength(0);
  });

  it('uses renamed headers', async () => {
    settings.requestHeader = 'X-Beacon-Dialog';
    const fetchMock = vi.fn().mockResolvedValue(reply({ body: '<p>x</p>' }));
    vi.stubGlobal('fetch', fetchMock);
    session.open('/form', opener(), 'Edit');
    await tick();
    expect((fetchMock.mock.calls[0] as [string, RequestInit])[1].headers).toMatchObject({ 'X-Beacon-Dialog': '1' });
  });

  it('does nothing for cross-origin openers, missing bodies or dialogs without showModal', () => {
    expect(session.open('https://evil.test/form', opener(), 'x')).toBe(false);
    expect(session.open(null, opener(), 'x')).toBe(false);
    (dialog as unknown as { showModal: unknown }).showModal = undefined;
    expect(session.supported()).toBe(false);
    expect(session.open('/form', opener(), 'x')).toBe(false);
    expect(dialog.open).toBe(false);
  });

  it('follows the link when the load fails (status, redirect, whole document, network)', async () => {
    for (const response of [reply({ status: 500 }), reply({ status: 0, type: 'opaqueredirect' }), reply({ body: '<!doctype html><html></html>' })]) {
      assign.mockClear();
      await openWith(response);
      expect(assign).toHaveBeenCalledWith('/form');
      expect(dialog.open).toBe(false);
    }
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));
    assign.mockClear();
    session.open('/form', opener(), 'Edit');
    await tick();
    await tick();
    expect(assign).toHaveBeenCalledWith('/form');
    expect(events.filter(([name]) => name === 'failed')[0]?.[1]).toEqual({ stage: 'load', status: null });
  });

  it('keeps the dialog open with the load alert when followOnLoadError is false', async () => {
    settings.followOnLoadError = false;
    await openWith(reply({ status: 404 }));
    expect(assign).not.toHaveBeenCalled();
    expect(dialog.open).toBe(true);
    expect(body().querySelector(`[${ALERT_ATTR}="load"]`)?.textContent).toBe('Load failed');
    settings.loadError = '';
    await openWith(reply({ status: 404 }));
    expect(body().querySelector(`[${ALERT_ATTR}]`)?.textContent).toBe('Failed');
  });

  it('ignores a late answer after close (abort) and never navigates', async () => {
    let resolve: (r: Response) => void = () => undefined;
    vi.stubGlobal('fetch', vi.fn().mockImplementation(() => new Promise<Response>((r) => (resolve = r))));
    session.open('/form', opener(), 'Edit');
    session.close();
    resolve(reply({ status: 500 }));
    await tick();
    expect(assign).not.toHaveBeenCalled();
    expect(body().childNodes.length).toBe(0);
    expect(document.activeElement).toBe(opener());
    expect(events[events.length - 1]?.[0]).toBe('closed');
    expect(document.documentElement.hasAttribute('data-nowo-overlay')).toBe(false);
  });

  it('ignores a late successful load after close', async () => {
    let resolve: (r: Response) => void = () => undefined;
    vi.stubGlobal('fetch', vi.fn().mockImplementation(() => new Promise<Response>((r) => (resolve = r))));
    session.open('/form', opener(), 'Edit');
    session.close();
    resolve(reply({ body: '<p id="late">late</p>' }));
    await tick();
    expect(document.getElementById('late')).toBeNull();
  });

  describe('submit', () => {
    const form = (): HTMLFormElement => body().querySelector('form') as HTMLFormElement;

    beforeEach(async () => {
      await openWith(reply({ body: '<form method="post" action="/form"><input name="name" value="A"><button type="submit" name="save" value="again" id="again">Save + new</button><button type="submit" id="save">Save</button></form>' }));
    });

    it('posts FormData with the submitter, then follows a valid redirect header', async () => {
      const fetchMock = vi.fn().mockResolvedValue(reply({ status: 204, headers: { 'X-Nowo-Redirect': '/settings?tab=alerts' } }));
      vi.stubGlobal('fetch', fetchMock);
      const event = await submitForm(form(), document.getElementById('again'));
      expect(event.defaultPrevented).toBe(true);
      const init = (fetchMock.mock.calls[0] as [string, RequestInit])[1];
      expect(init).toMatchObject({ method: 'POST', mode: 'same-origin', redirect: 'manual', credentials: 'same-origin' });
      const data = init.body as FormData;
      expect(data.get('name')).toBe('A');
      expect(data.get('save')).toBe('again');
      expect(assign).toHaveBeenCalledWith('/settings?tab=alerts');
      expect(dialog.open).toBe(false);
      expect(events.find(([name]) => name === 'saved')).toEqual(['saved', { redirect: '/settings?tab=alerts', status: 204 }, true]);
    });

    it.each(['action', 'method', 'enctype', 'elements'])('stays in the dialog with a control named %s (GT-I7)', async (name) => {
      shadow(form(), name);
      const fetchMock = vi.fn().mockResolvedValue(reply({ status: 204, headers: {} }));
      vi.stubGlobal('fetch', fetchMock);
      const event = await submitForm(form());
      expect(event.defaultPrevented).toBe(true);
      expect(fetchMock.mock.calls[0]?.[0]).toBe(`${window.location.origin}/form`);
      expect((fetchMock.mock.calls[0] as [string, RequestInit])[1]).toMatchObject({ method: 'POST', mode: 'same-origin' });
    });

    it('does not ask the form itself for the native marker, which a control could shadow (GT-I7)', async () => {
      await openWith(reply({ body: '<form method="post" action="/form" data-nowo-ui-remote-form-native></form>' }));
      const own = vi.spyOn(form(), 'hasAttribute');
      const fetchMock = vi.fn();
      vi.stubGlobal('fetch', fetchMock);
      const event = await submitForm(form());
      expect(event.defaultPrevented).toBe(false);
      expect(fetchMock).not.toHaveBeenCalled();
      expect(own).not.toHaveBeenCalledWith(NATIVE_ATTR);
    });

    it('reloads on 204 without a header, on an invalid header and on a 3xx', async () => {
      for (const response of [reply({ status: 204, headers: {} }), reply({ status: 204, headers: { 'X-Nowo-Redirect': 'https://evil.test/' } }), reply({ status: 0, type: 'opaqueredirect' })]) {
        await openWith(reply({ body: '<form method="post" action="/form"></form>' }));
        reload.mockClear();
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(response));
        await submitForm(form());
        expect(reload).toHaveBeenCalledTimes(1);
        expect(assign).not.toHaveBeenCalled();
        warn.mockRestore();
      }
    });

    it('follows a valid redirect header on a 2xx answer only (ES-I5)', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply({ status: 200, headers: { 'X-Nowo-Redirect': '/done' } })));
      await submitForm(form());
      expect(assign).toHaveBeenCalledWith('/done');
      assign.mockClear();
      await openWith(reply({ body: '<form method="post" action="/form"></form>' }));
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply({ status: 500, headers: { 'X-Nowo-Redirect': '/done' } })));
      await submitForm(form());
      expect(assign).not.toHaveBeenCalled();
      expect(body().querySelector('[role="alert"]')).not.toBeNull();
    });

    it('never navigates off-origin through a dot-segment redirect (ES-M1)', async () => {
      const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
      for (const dotted of ['/.//evil.example/x', '/..//evil.example/x', '/%2e//evil.example/x', '/%2E%2E//evil.example/x', '/a/..//evil.example/x']) {
        await openWith(reply({ body: '<form method="post" action="/form"></form>' }));
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply({ status: 204, headers: { 'X-Nowo-Redirect': dotted } })));
        await submitForm(form());
      }
      expect(assign).not.toHaveBeenCalled();
      expect(reload).toHaveBeenCalledTimes(5);
      warn.mockRestore();
    });

    it('honours the submitter: formaction, formmethod and the posted value (ES-L2)', async () => {
      body().innerHTML = `<form method="post" action="/form"><input type="hidden" name="op" value="hidden">
        <button id="again" type="submit" name="op" value="again">Again</button></form>`;
      const fetchMock = vi.fn().mockResolvedValue(reply({ status: 204, headers: {} }));
      vi.stubGlobal('fetch', fetchMock);
      await submitForm(form(), document.getElementById('again'));
      expect(((fetchMock.mock.calls[0] as [string, RequestInit])[1].body as FormData).getAll('op')).toEqual(['hidden', 'again']);
      await openWith(reply({ body: '<form method="post" action="/form"><button id="del" type="submit" name="del" value="1" formaction="/delete" formenctype="application/x-www-form-urlencoded">Delete</button><button id="ext" type="submit" formaction="https://evil.test/x">Ext</button><button id="get" type="submit" formmethod="get">Get</button><button id="pt" type="submit" formenctype="text/plain">T</button></form>' }));
      fetchMock.mockClear();
      vi.stubGlobal('fetch', fetchMock);
      await submitForm(form(), document.getElementById('del'));
      const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
      expect(url).toBe(`${window.location.origin}/delete`);
      expect((init.body as URLSearchParams).get('del')).toBe('1');
      await openWith(reply({ body: '<form method="post" action="/form"><button id="ext" type="submit" formaction="https://evil.test/x">Ext</button><button id="get" type="submit" formmethod="get">Get</button><button id="pt" type="submit" formenctype="text/plain">T</button></form>' }));
      for (const id of ['ext', 'get', 'pt']) {
        const event = await submitForm(form(), document.getElementById(id));
        expect(event.defaultPrevented, id).toBe(false);
      }
    });

    it('posts an action-less form to the URL loaded in the dialog, not the host page (ES-I1)', async () => {
      await openWith(reply({ body: '<form method="post"><button type="submit">Save</button></form>' }), '/dialog/form');
      const fetchMock = vi.fn().mockResolvedValue(reply({ status: 204, headers: {} }));
      vi.stubGlobal('fetch', fetchMock);
      await submitForm(form());
      expect((fetchMock.mock.calls[0] as [string, RequestInit])[0]).toBe(`${window.location.origin}/dialog/form`);
    });

    it('lets the host handle success (saved prevented): closes, no navigation', async () => {
      prevent.add('saved');
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply({ status: 204, headers: {} })));
      await submitForm(form());
      expect(dialog.open).toBe(false);
      expect(reload).not.toHaveBeenCalled();
    });

    it('re-renders a 422 and focuses the first invalid control', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply({ status: 422, body: '<form method="post"><input id="bad" aria-invalid="true"></form>' })));
      await submitForm(form());
      expect(document.activeElement?.id).toBe('bad');
      expect(events[events.length - 1]).toEqual(['invalid', { status: 422 }, false]);
    });

    it('re-renders a 200 fragment and focuses the title', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply({ status: 200, body: '<p id="step2">Step 2</p>' })));
      await submitForm(form());
      expect(document.getElementById('step2')).not.toBeNull();
      expect(document.activeElement?.id).toBe('d-title');
    });

    it('keeps the typed form with one alert on errors', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply({ status: 500 })));
      await submitForm(form());
      vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));
      await submitForm(form());
      expect(body().querySelectorAll(`[${ALERT_ATTR}]`)).toHaveLength(1);
      expect(body().querySelector('[role="alert"]')?.textContent).toBe('Failed');
      expect(form()).not.toBeNull();
      expect((document.getElementById('save') as HTMLButtonElement).disabled).toBe(false);
      expect(events.filter(([name]) => name === 'failed').map(([, detail]) => detail)).toEqual([
        { stage: 'submit', status: 500 },
        { stage: 'submit', status: null },
      ]);
    });

    it('treats a whole document answered with 200 as an error', async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply({ status: 200, body: '<!doctype html><html><body>page</body></html>' })));
      await submitForm(form());
      expect(body().querySelector('[role="alert"]')).not.toBeNull();
    });

    it('ignores double submits and disables the buttons meanwhile', async () => {
      let resolve: (r: Response) => void = () => undefined;
      const fetchMock = vi.fn().mockImplementation(() => new Promise<Response>((r) => (resolve = r)));
      vi.stubGlobal('fetch', fetchMock);
      const first = new Event('submit', { bubbles: true, cancelable: true });
      form().dispatchEvent(first);
      expect((document.getElementById('save') as HTMLButtonElement).disabled).toBe(true);
      const second = new Event('submit', { bubbles: true, cancelable: true });
      form().dispatchEvent(second);
      expect(second.defaultPrevented).toBe(true);
      expect(fetchMock).toHaveBeenCalledTimes(1);
      resolve(reply({ status: 204, headers: {} }));
      await tick();
      await tick();
      expect(reload).toHaveBeenCalled();
    });

    it('closing during a submit drops the answer', async () => {
      let resolve: (r: Response) => void = () => undefined;
      vi.stubGlobal('fetch', vi.fn().mockImplementation(() => new Promise<Response>((r) => (resolve = r))));
      form().dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
      session.close();
      resolve(reply({ status: 204, headers: { 'X-Nowo-Redirect': '/x' } }));
      await tick();
      expect(assign).not.toHaveBeenCalled();
    });

    it('leaves GET, native and cross-origin forms to the browser', async () => {
      body().innerHTML = `<form id="g" method="get" action="/search"></form><form id="n" method="post" data-nowo-ui-remote-form-native action="/x"></form>
        <form id="c" method="post" action="https://evil.test/x"></form><form id="self" method="post"></form>`;
      for (const id of ['g', 'n', 'c']) {
        const event = await submitForm(document.getElementById(id) as HTMLFormElement);
        expect(event.defaultPrevented).toBe(false);
      }
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply({ status: 204, headers: {} })));
      const own = await submitForm(document.getElementById('self') as HTMLFormElement);
      expect(own.defaultPrevented).toBe(true);
    });

    it('ignores submits of non-forms and of a closed dialog', async () => {
      expect(await session.submit(new Event('submit'))).toBe(false);
      session.close();
      expect(await session.submit(Object.assign(new Event('submit'), {}))).toBe(false);
    });
  });

  describe('steps (2.33, X-Nowo-Dialog-Location)', () => {
    const form = (): HTMLFormElement => body().querySelector('form') as HTMLFormElement;

    beforeEach(async () => {
      await openWith(reply({ body: '<form method="post" action="/connect"><input name="token" value="t"><button type="submit" id="save">Save</button></form>' }));
    });

    it('loads the next step into the same dialog, without closing or navigating', async () => {
      const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(reply({ status: 204, headers: { 'X-Nowo-Dialog-Location': '/connect/lists?board=7', 'X-Nowo-Redirect': '/elsewhere' } }))
        .mockResolvedValueOnce(reply({ body: '<form method="post"><select name="list" autofocus id="list"></select><button type="submit">Link</button></form>' }));
      vi.stubGlobal('fetch', fetchMock);
      await submitForm(form());
      await tick();
      await tick();
      expect(dialog.open).toBe(true);
      expect(assign).not.toHaveBeenCalled();
      expect(reload).not.toHaveBeenCalled();
      const [url, init] = fetchMock.mock.calls[1] as [string, RequestInit];
      expect(url).toBe(`${window.location.origin}/connect/lists?board=7`);
      expect(init).toMatchObject({ mode: 'same-origin', redirect: 'manual', credentials: 'same-origin' });
      expect(init.headers).toMatchObject({ 'X-Nowo-Dialog': '1' });
      expect(document.activeElement?.id).toBe('list');
      expect(document.getElementById('d-title')?.textContent).toBe('Edit');
      expect(events.find(([name]) => name === 'step')).toEqual(['step', { url: '/connect/lists?board=7', status: 204 }, true]);
      expect(events.some(([name]) => name === 'saved')).toBe(false);
      expect(session.currentUrl()).toBe(`${window.location.origin}/connect/lists?board=7`);
      // An action-less form of the step posts to the step URL.
      fetchMock.mockReset();
      fetchMock.mockResolvedValue(reply({ status: 204, headers: { 'X-Nowo-Redirect': '/board/7' } }));
      await submitForm(form());
      expect((fetchMock.mock.calls[0] as [string, RequestInit])[0]).toBe(`${window.location.origin}/connect/lists?board=7`);
      expect(assign).toHaveBeenCalledWith('/board/7');
    });

    it('a prevented step event leaves the dialog as it is', async () => {
      prevent.add('step');
      const fetchMock = vi.fn().mockResolvedValue(reply({ status: 204, headers: { 'X-Nowo-Dialog-Location': '/connect/lists' } }));
      vi.stubGlobal('fetch', fetchMock);
      await submitForm(form());
      expect(fetchMock).toHaveBeenCalledTimes(1);
      expect(dialog.open).toBe(true);
      expect(assign).not.toHaveBeenCalled();
      expect(reload).not.toHaveBeenCalled();
    });

    it('never loads another origin: an invalid location is ignored (warned) and the answer handled without it', async () => {
      for (const bad of ['https://evil.example/x', '//evil.example/x', '/\\evil.example', '/.//evil.example/x', '/%2e%2e//evil.example', 'javascript:alert(1)', '/a\nb']) {
        await openWith(reply({ body: '<form method="post" action="/connect"></form>' }));
        reload.mockClear();
        assign.mockClear();
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
        const fetchMock = vi.fn().mockResolvedValue(reply({ status: 204, headers: { 'X-Nowo-Dialog-Location': bad } }));
        vi.stubGlobal('fetch', fetchMock);
        await submitForm(form());
        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(reload).toHaveBeenCalledTimes(1);
        expect(assign).not.toHaveBeenCalled();
        warn.mockRestore();
      }
    });

    it('ignores the location of a non-2xx answer', async () => {
      const fetchMock = vi.fn().mockResolvedValue(reply({ status: 500, headers: { 'X-Nowo-Dialog-Location': '/connect/lists' } }));
      vi.stubGlobal('fetch', fetchMock);
      await submitForm(form());
      expect(fetchMock).toHaveBeenCalledTimes(1);
      expect(body().querySelector(`[${ALERT_ATTR}]`)).not.toBeNull();
    });

    it('a step whose load fails follows it as a full page', async () => {
      const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(reply({ status: 204, headers: { 'X-Nowo-Dialog-Location': '/connect/lists' } }))
        .mockResolvedValueOnce(reply({ status: 500 }));
      vi.stubGlobal('fetch', fetchMock);
      await submitForm(form());
      await tick();
      await tick();
      expect(assign).toHaveBeenCalledWith('/connect/lists');
      expect(dialog.open).toBe(false);
    });

    it('uses the configured location header name', async () => {
      settings = { ...settings, locationHeader: 'X-Beacon-Dialog-Location', requestHeader: 'X-Beacon-Dialog' };
      const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(reply({ status: 204, headers: { 'X-Nowo-Dialog-Location': '/ignored', 'X-Beacon-Dialog-Location': '/connect/lists' } }))
        .mockResolvedValueOnce(reply({ body: '<p id="lists">Lists</p>' }));
      vi.stubGlobal('fetch', fetchMock);
      await submitForm(form());
      await tick();
      await tick();
      expect((fetchMock.mock.calls[1] as [string, RequestInit])[0]).toBe(`${window.location.origin}/connect/lists`);
      expect((fetchMock.mock.calls[1] as [string, RequestInit])[1].headers).toMatchObject({ 'X-Beacon-Dialog': '1' });
      expect(document.getElementById('lists')).not.toBeNull();
    });
  });

  describe('steps on load (2.33)', () => {
    it('a load answered with a step follows it, at most MAX_STEPS times in a row', async () => {
      const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(reply({ status: 204, headers: { 'X-Nowo-Dialog-Location': '/token' } }))
        .mockResolvedValueOnce(reply({ body: '<p id="token">Token</p>' }));
      vi.stubGlobal('fetch', fetchMock);
      session.open('/connect', opener(), 'Connect');
      for (let i = 0; i < 6; i += 1) await tick();
      expect(document.getElementById('token')).not.toBeNull();
      expect(session.currentUrl()).toBe(`${window.location.origin}/token`);

      const loop = vi.fn().mockResolvedValue(reply({ status: 204, headers: { 'X-Nowo-Dialog-Location': '/loop' } }));
      vi.stubGlobal('fetch', loop);
      session.open('/loop', opener(), 'Loop');
      for (let i = 0; i < 30; i += 1) await tick();
      expect(loop).toHaveBeenCalledTimes(MAX_STEPS + 1);
      expect(assign).toHaveBeenCalledWith('/loop');
    });

    it('warns when a step chain passes the cap, and only then (GT-I4)', async () => {
      const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply({ status: 204, headers: { 'X-Nowo-Dialog-Location': '/loop' } })));
      session.open('/loop', opener(), 'Loop');
      for (let i = 0; i < 30; i += 1) await tick();
      expect(warn).toHaveBeenCalledTimes(1);
      expect(warn.mock.calls[0]?.[0]).toContain(`after ${MAX_STEPS} in a row`);

      warn.mockClear();
      const chain = vi.fn();
      for (let i = 0; i < MAX_STEPS; i += 1) chain.mockResolvedValueOnce(reply({ status: 204, headers: { 'X-Nowo-Dialog-Location': '/next' } }));
      chain.mockResolvedValueOnce(reply({ body: '<p id="last">Last</p>' }));
      vi.stubGlobal('fetch', chain);
      session.open('/next', opener(), 'Chain');
      for (let i = 0; i < 30; i += 1) await tick();
      expect(document.getElementById('last')).not.toBeNull();
      expect(warn).not.toHaveBeenCalled();
    });
  });

  it('closes on a backdrop click only with closeOnBackdrop', async () => {
    await openWith(reply({ body: '<p>x</p>' }));
    vi.spyOn(dialog, 'getBoundingClientRect').mockReturnValue({ left: 10, right: 100, top: 10, bottom: 100, width: 90, height: 90 } as DOMRect);
    dialog.dispatchEvent(new MouseEvent('click', { bubbles: true, clientX: 1, clientY: 1 }));
    expect(dialog.open).toBe(true);
    settings.closeOnBackdrop = true;
    dialog.dispatchEvent(new MouseEvent('click', { bubbles: true, clientX: 50, clientY: 50 }));
    expect(dialog.open).toBe(true);
    dialog.dispatchEvent(new MouseEvent('click', { bubbles: true, clientX: 1, clientY: 1 }));
    expect(dialog.open).toBe(false);
  });

  it('ignores backdrop clicks on an unlaid dialog and clicks inside', async () => {
    settings.closeOnBackdrop = true;
    await openWith(reply({ body: '<p id="in">x</p>' }));
    vi.spyOn(dialog, 'getBoundingClientRect').mockReturnValue({ left: 0, right: 0, top: 0, bottom: 0, width: 0, height: 0 } as DOMRect);
    dialog.dispatchEvent(new MouseEvent('click', { bubbles: true, clientX: 500, clientY: 500 }));
    document.getElementById('in')?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    expect(dialog.open).toBe(true);
  });

  it('prevents cancel while a nested layer owns Escape', async () => {
    await openWith(reply({ body: '<div class="ts-wrapper dropdown-active"><input id="ts"></div><input id="plain">' }));
    (document.getElementById('ts') as HTMLElement).focus();
    const cancel = new Event('cancel', { cancelable: true });
    dialog.dispatchEvent(cancel);
    expect(cancel.defaultPrevented).toBe(true);
    (document.getElementById('plain') as HTMLElement).focus();
    const second = new Event('cancel', { cancelable: true });
    dialog.dispatchEvent(second);
    expect(second.defaultPrevented).toBe(false);
  });

  it('a close queued before a reopen in the same task keeps the new session (ES-I3)', async () => {
    await openWith(reply({ body: '<p>x</p>' }));
    dialog.removeAttribute('open');
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply({ body: '<p id="second">y</p>' })));
    session.open('/form', opener(), 'Again');
    dialog.dispatchEvent(new Event('close'));
    await tick();
    await tick();
    expect(dialog.open).toBe(true);
    expect(document.getElementById('second')).not.toBeNull();
    expect(document.documentElement.getAttribute('data-nowo-overlay')).toBe('1');
  });

  it('empties the body on close and does not focus a removed opener', async () => {
    await openWith(reply({ body: '<p>x</p>' }));
    opener().remove();
    session.close();
    expect(body().childNodes.length).toBe(0);
    expect(document.activeElement).not.toBe(null);
  });

  it('works without title or body targets', () => {
    const bare = new RemoteFormSession(dialog, () => null, () => null, () => settings, () => true);
    expect(bare.open('/form', null, 'x')).toBe(false);
  });
});
