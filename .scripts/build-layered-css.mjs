#!/usr/bin/env node
/**
 * Builds src/Resources/public/css/nowo-ui.css (cascade layer `nowo-ui`) from nowo-ui.unlayered.css (the edited
 * source, identical to the 1.x stylesheet). `pnpm run build:css`; tests/Unit/LayeredCssTest.php fails when the two
 * files drift. Format: header comment, the layer order statement, `@layer nowo-ui {`, the source verbatim, `}`.
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = resolve(dirname(fileURLToPath(import.meta.url)), '../src/Resources/public/css');
const source = readFileSync(resolve(dir, 'nowo-ui.unlayered.css'), 'utf8');
const header = readFileSync(resolve(dirname(fileURLToPath(import.meta.url)), 'nowo-ui-layer-header.css'), 'utf8');

writeFileSync(resolve(dir, 'nowo-ui.css'), `${header}@layer nowo-ui {\n${source.endsWith('\n') ? source : `${source}\n`}}\n`);
