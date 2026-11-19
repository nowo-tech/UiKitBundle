// @vitest-environment node
import { fileURLToPath } from 'node:url';

import { compileString } from 'sass';
import { describe, expect, it } from 'vitest';

const dir = fileURLToPath(new URL('.', import.meta.url));

function compile(source: string): string {
  return compileString(`@use 'thin-scrollbars' as thin;\n${source}`, { loadPaths: [dir] }).css;
}

describe('thin-scrollbars mixin', () => {
  it('emits the Firefox fallback and the WebKit pseudo-elements for every selector', () => {
    const css = compile("@include thin.thin-scrollbars('html, .app-main');");
    expect(css).toContain('@supports not selector(::-webkit-scrollbar)');
    expect(css).toContain('scrollbar-width: thin');
    expect(css).toContain('html::-webkit-scrollbar, .app-main::-webkit-scrollbar');
    expect(css).toContain('width: 6px');
    expect(css).toContain('html::-webkit-scrollbar-button, .app-main::-webkit-scrollbar-button');
    expect(css).toContain('.app-main::-webkit-scrollbar-corner');
    expect(css).toContain('color-mix(in srgb, var(--nowo-ui-text, currentColor) 28%, transparent)');
    expect(css).toContain('.app-main::-webkit-scrollbar-thumb:hover');
  });

  it('accepts size, opacity and ink overrides', () => {
    const css = compile("@include thin.thin-scrollbars('.rail', $size: 3px, $thumb: 22%, $thumb-hover: 40%, $ink: var(--color-ink));");
    expect(css).toContain('width: 3px');
    expect(css).toContain('color-mix(in srgb, var(--color-ink) 22%, transparent)');
    expect(css).toContain('color-mix(in srgb, var(--color-ink) 40%, transparent)');
  });

  it('scrollbar() reads the public tokens with the kit fallbacks, Firefox properties only under @supports', () => {
    const css = compile("@include thin.scrollbar('.chat-list');");
    expect(css).toContain('@supports not selector(::-webkit-scrollbar)');
    expect(css).toContain('scrollbar-width: thin');
    expect(css).toContain('scrollbar-color: var(--nowo-ui-scrollbar-thumb, color-mix(in srgb, var(--nowo-ui-text, currentColor) 28%, transparent)) var(--nowo-ui-scrollbar-track, transparent)');
    expect(css).toContain('.chat-list::-webkit-scrollbar');
    expect(css).toContain('width: var(--nowo-ui-scrollbar-size, 6px)');
    expect(css).toContain('.chat-list::-webkit-scrollbar-button');
    expect(css).toContain('var(--nowo-ui-scrollbar-thumb-hover, color-mix(in srgb, var(--nowo-ui-text, currentColor) 45%, transparent))');
    // the standard properties never leave the @supports block
    expect(css.replace(/@supports not selector\(::-webkit-scrollbar\) \{[^}]*\}\s*\}/s, '')).not.toContain('scrollbar-width');
  });

  it('scrollbar-none() hides the bar, Firefox property only under @supports', () => {
    const css = compile("@include thin.scrollbar-none('.tabs-row');");
    expect(css).toContain('.tabs-row::-webkit-scrollbar');
    expect(css).toContain('display: none');
    expect(css).toContain('scrollbar-width: none');
    expect(css.indexOf('scrollbar-width')).toBeGreaterThan(css.indexOf('@supports not selector(::-webkit-scrollbar)'));
  });
});
