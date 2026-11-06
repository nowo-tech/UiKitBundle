import { afterEach, describe, expect, it } from 'vitest';
import { keepOnlyIconAndLabel, setButtonLabel } from './nowo-ui-button-label';

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
    expect(el.innerHTML).toBe('<svg><title>glyph</title></svg> Copied');
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

  it.each([
    ['a badge before the text', '<span class="badge">3</span> Copy', '<span class="badge">3</span> Copied'],
    ['a badge after the text', 'Copy <span class="badge">3</span>', 'Copied <span class="badge">3</span>'],
    ['a ligature icon before the text', '<i class="material-icons">content_copy</i> Copy', '<i class="material-icons">content_copy</i> Copied'],
    ['a ligature icon alone', '<i class="material-icons">content_copy</i>', '<i class="material-icons">content_copy</i>'],
    ['a badge alone', '<i class="x"></i><span class="badge">3</span>', '<i class="x"></i><span class="badge">3</span>'],
    ['an aria-hidden glyph alone', '<span aria-hidden="true">+</span>', '<span aria-hidden="true">+</span>'],
  ])('keeps %s', (_name, inner, expected) => {
    const el = button('', inner);
    setButtonLabel(el, 'Copied');
    expect(el.innerHTML).toBe(expected);
  });

  it('writes only the first label node, preferring the visible label over a screen-reader hint', () => {
    const el = button('', '<span class="visually-hidden">opens a dialog</span><span class="nowo-ui-action__label">Copy</span><span class="nowo-ui-action__label">other</span>');
    setButtonLabel(el, 'Copied');
    expect(el.innerHTML).toBe('<span class="visually-hidden">opens a dialog</span><span class="nowo-ui-action__label">Copied</span><span class="nowo-ui-action__label">other</span>');
  });

  it('keeps a title that is a hint and follows one that mirrors the label', () => {
    const hint = button('title="Copies the link"', '<i class="i"></i><span class="nowo-ui-action__label">Copy</span>');
    setButtonLabel(hint, 'Copied');
    expect(hint.getAttribute('title')).toBe('Copies the link');

    const iconOnly = button('title="Copy" aria-label="Copy"', '<i class="i"></i>');
    setButtonLabel(iconOnly, 'Copied');
    expect(iconOnly.getAttribute('title')).toBe('Copied');
  });

  it('treats a label with $ patterns as plain text', () => {
    const el = button('', '<i class="i"></i> Copy');
    setButtonLabel(el, "$& <b>$'");
    expect(el.querySelector('b')).toBeNull();
    expect(el.lastChild?.textContent).toBe(" $& <b>$'");
  });

  it('is not fooled by clobbered properties of a form target', () => {
    document.body.innerHTML = '<form><input name="querySelector"><input name="textContent"><input name="getAttribute"><span class="visually-hidden">Copy</span></form>';
    const form = document.querySelector('form') as HTMLElement;
    setButtonLabel(form, 'Copied');
    expect(document.querySelector('span')?.textContent).toBe('Copied');
    expect(document.querySelectorAll('input')).toHaveLength(3);
  });
});

describe('keepOnlyIconAndLabel', () => {
  afterEach(() => {
    document.body.innerHTML = '';
  });

  it.each([
    ['text only', 'S3CR3T', 'Hidden'],
    ['icon and label', '<i class="i"></i><span class="nowo-ui-action__label">S3CR3T</span>', '<i class="i"></i><span class="nowo-ui-action__label">Hidden</span>'],
    ['icon only', '<svg class="nowo-ui-icon"><path d="M0"></path></svg>', '<svg class="nowo-ui-icon"><path d="M0"></path></svg>'],
    ['a value next to the label', '<span class="nowo-ui-action__label">Show</span><code>S3CR3T</code>', '<span class="nowo-ui-action__label">Hidden</span>'],
    ['a bare value next to the icon', '<i class="i"></i> S3CR3T', '<i class="i"></i>Hidden'],
    ['a second label node', '<span class="visually-hidden">S3CR3T</span><span class="nowo-ui-action__label">Show</span>', '<span class="nowo-ui-action__label">Hidden</span>'],
    ['a label inside a wrapper with a value', '<div><span class="nowo-ui-action__label">Show</span><code>S3CR3T</code></div>', '<span class="nowo-ui-action__label">Hidden</span>'],
    ['an icon inside a wrapper', '<span class="w"><i class="i"></i></span><code>S3CR3T</code>', '<span class="w"><i class="i"></i></span>Hidden'],
    ['a wrapper with an icon and a value', '<span class="w"><i class="i"></i>S3CR3T</span>', 'Hidden'],
    ['text inside an svg', '<svg><title>S3CR3T</title><text>S3CR3T</text><path d="M0"></path></svg>', '<svg><path d="M0"></path></svg>'],
  ])('%s', (_name, inner, expected) => {
    document.body.innerHTML = `<button>${inner}</button>`;
    const el = document.querySelector('button') as HTMLElement;
    keepOnlyIconAndLabel(el, 'Hidden');
    expect(el.innerHTML).toBe(expected);
    expect(document.body.innerHTML).not.toContain('S3CR3T');
  });
});
