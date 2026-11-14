// @vitest-environment node
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

import { describe, expect, it } from 'vitest';

const publicCss = fileURLToPath(new URL('../../public/css/', import.meta.url));

/** Body of the block opened by the first `{` after `header`, with balanced braces. */
function block(css: string, header: string): string {
  const at = css.indexOf(header);
  expect(at, header).toBeGreaterThan(-1);
  const open = css.indexOf('{', at);
  let depth = 0;
  for (let i = open; i < css.length; i++) {
    if (css[i] === '{') depth++;
    if (css[i] === '}' && --depth === 0) return css.slice(open + 1, i);
  }
  throw new Error(`unbalanced block: ${header}`);
}

describe.each(['nowo-ui.unlayered.css', 'nowo-ui.css'])('edge-tabs chevron fallback (%s)', (file) => {
  const css = readFileSync(publicCss + file, 'utf8');

  it('ships a non-empty @supports not (d: path(...)) fallback with the transition and the rotate', () => {
    const fallback = block(css, "@supports not (d: path('M0 0'))");
    expect(fallback.trim()).not.toBe('');
    expect(fallback).toContain('.nowo-ui-edge-tabs-launcher--chevron .nowo-ui-edge-tab__icon {');
    expect(fallback).toMatch(/transition: transform var\(--nowo-ui-edge-tabs-icon-duration, 240ms\) var\(--nowo-ui-edge-tabs-icon-easing, cubic-bezier\(0\.34, 1\.56, 0\.64, 1\)\);/);
    expect(fallback).toMatch(/\.nowo-ui-edge-tabs-launcher--chevron\[aria-expanded='true'\] \.nowo-ui-edge-tab__icon \{\s*transform: rotate\(-90deg\);/);
  });

  it('turns the icon transition off under prefers-reduced-motion: reduce', () => {
    const start = css.indexOf('@media (prefers-reduced-motion: reduce)', css.indexOf('@keyframes nowo-ui-edge-tab-fold'));
    expect(start).toBeGreaterThan(-1);
    const reduced = block(css.slice(start), '@media (prefers-reduced-motion: reduce)');
    expect(reduced).toContain('.nowo-ui-edge-tabs-launcher .nowo-ui-edge-tab__icon');
    expect(reduced).toMatch(/transition: none;/);
  });

  it('adds --nowo-ui-edge-tabs-launcher-gap (default 0) to the unfolded tabs top, for always and phone', () => {
    const uses = css.match(/\+ var\(--nowo-ui-edge-tabs-launcher-gap, 0px\)\);/g) ?? [];
    expect(uses.length).toBe(2);
  });
});
