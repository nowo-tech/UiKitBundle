// @vitest-environment node
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

import { describe, expect, it } from 'vitest';

const publicCss = fileURLToPath(new URL('../../public/css/', import.meta.url));

function rule(css: string, selector: string): string {
  const start = css.indexOf(`\n${selector} {`);
  expect(start, `${selector} rule`).toBeGreaterThan(-1);
  return css.slice(start, css.indexOf('}', start));
}

describe.each(['nowo-ui.unlayered.css', 'nowo-ui.css'])('image input phone layout (%s)', (file) => {
  const css = readFileSync(publicCss + file, 'utf8');

  it('is a single shrinkable column so it cannot outgrow its card at 320-390 px', () => {
    const root = rule(css, '.nowo-ui-image-input');
    expect(root).toContain('grid-template-columns: minmax(0, 1fr)');
    expect(root).toContain('max-width: 100%');
  });

  it('lets the controls and their children shrink and wrap long file names', () => {
    const controls = rule(css, '.nowo-ui-image-input__controls');
    expect(controls).toContain('min-width: 0');
    expect(controls).toContain('max-width: 100%');
    const children = rule(css, '.nowo-ui-image-input__controls > *');
    expect(children).toContain('min-width: 0');
    expect(children).toContain('max-width: 100%');
    expect(children).toContain('overflow-wrap: anywhere');
  });
});
