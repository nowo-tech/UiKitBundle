# Coverage policy

## Table of contents

- [PHP line coverage gate](#php-line-coverage-gate)
- [TypeScript coverage](#typescript-coverage)
- [Justified exclusions](#justified-exclusions)
- [How to refresh](#how-to-refresh)

## PHP line coverage gate

`make coverage-check` / `composer coverage-check` enforce **100%** line coverage on the PHPUnit includable `src/` set (`REQ-TEST-003` / `REQ-TEST-006`).

Latest run (2026-11-01, 2.21.x + `chore/req-100`): **100%** PHP lines (3253/3253, 1109 tests, `tests/Unit` + `tests/Integration`).

Published README percentage must match the latest `coverage-output.txt` / CI artifact.

## TypeScript coverage

`make test-ts` / `pnpm run test:coverage` reports Vitest v8 coverage for the gated set in `vitest.config.ts`: every top-level IIFE source `src/Resources/assets/src/*.ts`, the Stimulus peers listed in `coverage.include` and `morphicons/index.ts` (excluding `*.test.ts`). Thresholds: lines/statements/functions ≥ 90%, branches ≥ 85%.

Latest run (2026-11-01): **100%** lines (2810/2810) / statements (2884/2884) / functions (580/580), **95.76%** branches (2036/2126).

Outside the gate: the vendored orb engine (`src/orb/`, see below) and the peers not yet listed in `coverage.include` (`clipboard_copy_controller.ts`, `confirm_dialog_controller.ts`, `release_status_controller.ts`, `toast_stack_controller.ts`).

## Justified exclusions

No `<source><exclude>` entries in `phpunit.xml.dist` at this time — target aggregate line coverage is **100%** on PHPUnit-includable `src/`.

**TypeScript:** `src/Resources/assets/src/orb/` (Thinking Orbs canvas engine + mount helpers, MIT — see [THIRD_PARTY.md](THIRD_PARTY.md)) is outside the Vitest coverage gate. Smoke coverage remains via `nowo-ui-orb.test.ts` / `nowo-ui-orb.ts`.

Do **not** add new `@codeCoverageIgnore` or PHPUnit exclusions without updating this document.

## How to refresh

```bash
make coverage-check
make test-ts
# or
composer coverage-check
pnpm run test:coverage
```
