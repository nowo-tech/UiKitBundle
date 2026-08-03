# Release

This checklist helps maintainers prepare and publish a release safely.

## Table of contents

- [Pre-release](#pre-release)
- [Security checklist (12.4.1)](#security-checklist-1241)
- [Tag and publish](#tag-and-publish)
- [Post-release checks](#post-release-checks)
- [Coverage goals](#coverage-goals)

## Pre-release

Run the full release pipeline (Docker-backed Makefile):

```bash
make release-check
```

Expected steps:

- Asset build (`pnpm run build`)
- Composer validation and lock sync
- Code style checks
- Static analysis (Rector dry run + PHPStan)
- PHP and TypeScript test suites with coverage (≥99% PHP lines)
- Demo verification (`demo/Makefile` `release-check`)
- Git hygiene: no Cursor co-author trailers (**REQ-GIT-001**) via `make check-no-cursor-coauthor`

Alternatively (host PHP already installed):

```bash
composer qa
composer phpstan
composer test-coverage
composer coverage-check
```

## Security checklist (12.4.1)

Before tagging, confirm each item in [SECURITY.md — Release security checklist](SECURITY.md#release-security-checklist-1241). Note confirmation in the release PR or tag message.

## Tag and publish

1. Move `[Unreleased]` entries in `docs/CHANGELOG.md` to a new `## [X.Y.Z] - YYYY-MM-DD` section.
2. Update `docs/UPGRADING.md` if consumers must change code or configuration.
3. Commit on a clean tree (no Cursor co-author trailers — REQ-GIT-001):

   ```bash
   git status
   git add -A && git commit -m "Release vX.Y.Z"
   ```

4. Create an **annotated** tag: `git tag -a vX.Y.Z -m "Release vX.Y.Z"`.
5. Run `make check-no-cursor-coauthor` again **before** `git push` (REQ-GIT-001). The release commit itself is not covered by an earlier `release-check` run.
6. Push: `git push origin main` and `git push origin vX.Y.Z`.
7. Confirm GitHub workflows `release.yml` and `sync-releases.yml` completed successfully.
8. Prefer `gh -R nowo-tech/UiKitBundle …` if the local remote hostname is not recognized by `gh`.

## Post-release checks

- Verify Packagist metadata is updated: [nowo-tech/ui-kit-bundle](https://packagist.org/packages/nowo-tech/ui-kit-bundle).
- Confirm the GitHub release contains the tag message and changelog section.
- Validate installation in a clean Symfony app:

```bash
composer require nowo-tech/ui-kit-bundle
```

- Smoke-test kitchen sink demos (Bootstrap **8092** and Tailwind **8093**).

## Coverage goals

- **PHP**: **≥99%** line coverage (prefer **100%**; `make test-coverage` / `make coverage-check`)
- **TypeScript**: enforce via `make test-ts` (Vitest + `.scripts/ts-coverage-percent.sh`)

Update README **Tests and coverage** percentages after each release when coverage changes materially.
