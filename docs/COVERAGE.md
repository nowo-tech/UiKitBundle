# Coverage policy

## Table of contents

- [PHP line coverage gate](#php-line-coverage-gate)
- [TypeScript coverage](#typescript-coverage)
- [Justified exclusions](#justified-exclusions)
- [How to refresh](#how-to-refresh)

## PHP line coverage gate

`make coverage-check` / `composer coverage-check` enforce **≥ 99%** line coverage on the PHPUnit includable `src/` set (`REQ-TEST-003` / `REQ-TEST-006`).

Latest local run (2026-08-04): **100%** PHP lines (25 tests).

Published README percentage must match the latest `coverage-output.txt` / CI artifact.

## TypeScript coverage

`make test-ts` / `pnpm run test:coverage` reports Vitest v8 coverage for `src/Resources/assets/src/*.ts` (top-level IIFE entrypoints; excluding `*.test.ts`). Thresholds: lines/statements/functions ≥ 90%, branches ≥ 85%.

Latest local run: **~95.5%** lines / **~88.1%** branches (seven IIFE entrypoints).

## Justified exclusions

No `<source><exclude>` entries in `phpunit.xml.dist` at this time — target aggregate line coverage is **≥99%** (prefer **100%**) on PHPUnit-includable `src/`.

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
