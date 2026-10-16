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
});
