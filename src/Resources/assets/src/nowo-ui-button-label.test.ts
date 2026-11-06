import { afterEach, describe, expect, it } from 'vitest';
import { setButtonLabel } from './nowo-ui-button-label';

const button = (attrs: string, inner: string): HTMLElement => {
  document.body.innerHTML = `<button ${attrs}>${inner}</button>`;
  return document.querySelector('button') as HTMLElement;
};

describe('setButtonLabel', () => {
  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('replaces the text of a text-only button', () => {
    const el = button('', ' Copy ');
    setButtonLabel(el, 'Copied');
    expect(el.innerHTML).toBe('Copied');
  });

  it('leaves the title of a text-only button alone', () => {
    const el = button('title="Tip"', 'Copy');
    setButtonLabel(el, 'Copied');
    expect(el.getAttribute('title')).toBe('Tip');
  });

  it.each(['nowo-ui-action__label', 'visually-hidden'])('writes to the kit label node .%s and the title', (name) => {
    const el = button('title="Copy"', `<i class="i"></i><span class="${name}">Copy</span>`);
    setButtonLabel(el, 'Copied');
    expect(el.innerHTML).toBe(`<i class="i"></i><span class="${name}">Copied</span>`);
    expect(el.getAttribute('title')).toBe('Copied');
  });

  it('writes to a host label node marked with data-nowo-ui-label', () => {
    const el = button('', '<i class="i"></i><b data-nowo-ui-label>Copy</b><em>note</em>');
    setButtonLabel(el, 'Copied');
    expect(el.innerHTML).toBe('<i class="i"></i><b data-nowo-ui-label="">Copied</b><em>note</em>');
  });

  it('updates a bare text node next to an icon', () => {
    const el = button('', '<svg><title>glyph</title></svg> Copy');
    setButtonLabel(el, 'Copied');
    expect(el.innerHTML).toBe('<svg><title>glyph</title></svg>Copied');
  });

  it('updates the text of an unmarked host span', () => {
    const el = button('', '<i class="i"></i><span>Copy</span>');
    setButtonLabel(el, 'Copied');
    expect(el.innerHTML).toBe('<i class="i"></i><span>Copied</span>');
  });

  it('adds no visible text to an icon-only button', () => {
    const el = button('aria-label="Copy"', '<i class="i"></i>');
    setButtonLabel(el, 'Copied');
    expect(el.innerHTML).toBe('<i class="i"></i>');
  });

  it('is not fooled by clobbered properties of a form target', () => {
    document.body.innerHTML = '<form><input name="querySelector"><input name="textContent"><input name="getAttribute"><span class="visually-hidden">Copy</span></form>';
    const form = document.querySelector('form') as HTMLElement;
    setButtonLabel(form, 'Copied');
    expect(document.querySelector('span')?.textContent).toBe('Copied');
    expect(document.querySelectorAll('input')).toHaveLength(3);
  });
});
