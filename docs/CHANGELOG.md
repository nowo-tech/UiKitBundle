# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## Table of contents

- [Unreleased](#unreleased)
- [1.3.0 - 2026-08-03](#130---2026-08-03)
  - [Added](#added-3)
  - [Changed](#changed-1)
- [1.2.1 - 2026-08-03](#121---2026-08-03)
  - [Fixed](#fixed)
- [1.2.0 - 2026-08-03](#120---2026-08-03)
  - [Changed](#changed)
  - [Added](#added-2)
- [1.1.0 - 2026-08-03](#110---2026-08-03)
  - [Added](#added-1)
- [1.0.0 - 2026-08-03](#100---2026-08-03)
  - [Added](#added)

## [Unreleased]

## [1.3.0] - 2026-08-03

### Added

- **FrankenPHP demos**: `demo/symfony8` (port **8092**, Bootstrap) and `demo/symfony8-tailwind` (port **8093**) with `FRANKENPHP_MODE` (default `worker`), DNS, path repo sync, WebProfiler / Debug / Twig Inspector.
- Root Docker (`Dockerfile`, `docker-compose.yml`) and full Makefile (`ensure-up`, `release-check`, `coverage-check`, `setup-hooks`, `validate-translations`, …).
- Flex recipe `.symfony/recipe/nowo-tech/ui-kit-bundle/1.0/`.
- GitHub automation: `release.yml`, `sync-releases.yml`, Dependabot, PR lint, stale, CodeRabbit, demo-smoke, Scrutinizer, Copilot instructions, CODEOWNERS.
- Spec Kit baseline (`specs/001-baseline/`) + `.specify/` + Cursor rules/skills (REQ-SPECKIT / REQ-IDE).
- Docs: INSTALLATION, CONTRIBUTING, SECURITY, COVERAGE, DEMO-FRANKENPHP, GITHUB_CI, RECIPE, SPEC-KIT, SPEC-DRIVEN-DEVELOPMENT, ENGRAM.
- GIT-001 hooks (`.githooks/commit-msg`) and coverage scripts (PHP ≥99%, TS summary).

### Changed

- README follows canonical section order (REQ-DOCS-019) with FrankenPHP banner.
- CI: PHP × Symfony matrix (7.4 / 8.0 / 8.1), git-hygiene, coverage gate, assets/Vitest coverage job.

## [1.2.1] - 2026-08-03

### Fixed

- CI: `pnpm/action-setup@v4` no longer sets `version: 9` alongside `package.json` `packageManager` (`pnpm@9.15.0`), which caused `ERR_PNPM_BAD_PM_VERSION` / multiple-version install failures on GitHub Actions.

## [1.2.0] - 2026-08-03

### Changed

- Frontend assets: **TypeScript + Vite (pnpm)** — sources under `src/Resources/assets/src/`; built IIFEs in `src/Resources/public/js/` (`nowo-ui-modal.js`, `nowo-ui-shell.js`). Twig `asset(..., 'nowo_ui_kit')` paths unchanged.

### Added

- Vitest coverage for modal/shell helpers; CI job for `pnpm` typecheck / test / build.
- [docs/ROADMAP.md](ROADMAP.md): gap analysis vs Nowo Twig bundles and symfony-beacon admin; phased plan (toasts, confirm, cards, filters, nested nav, theme/locale helpers, consolidation).

## [1.1.0] - 2026-08-03

### Added

- **App chrome**: left aside, burger toggle, avatar, user dropdown (with logout), and footer.
- Partials: `_aside`, `_burger`, `_avatar`, `_user_menu`, `_footer`, composed `_shell`.
- Macros: `shell`, `header`, `aside`, `avatar`, `user_menu`, `burger`, `footer`.
- Asset `js/nowo-ui-shell.js` (desktop collapse + mobile drawer).
- Kitchen sink demo renders inside the shell chrome.

## [1.0.0] - 2026-08-03

### Added

- Initial public release of **UiKit Bundle** (`nowo-tech/ui-kit-bundle`).
- **Twig macros** (`@NowoUiKitBundle/macros/ui.html.twig`): buttons, toolbar, page header, search/input, list, table wrap/table, row actions, badge, muted/empty, flash, modal data attributes, pagination wrap, tabs — multi-framework class helpers (`bootstrap5`, `bootstrap4`, `tabler`, `tailwind`, `foundation`, `custom`, `none`) with optional per-call `framework` override.
- **Partials**: pagination, empty state, flashes, row actions, page header, tabs, modal shell; **icon** component by `icon_set`.
- **Assets** (named package `nowo_ui_kit`): `css/nowo-ui.css` (semantic `nowo-ui-*` + CSS variables) and `js/nowo-ui-modal.js` (custom/none/tailwind stacks).
- **Configuration** (`nowo_ui_kit`): `css_framework`, `icon_set`; Twig globals `nowo_ui_kit_css_framework` / `nowo_ui_kit_icon_set`.
- **i18n** domain `NowoUiKitBundle` with key parity for `en`, `es`, `it`, `fr`, `pt`, `de`, `nl`.
- **Twig namespace** `NowoUiKitBundle` with application override path support (REQ-TWIG-001/002).
- Kitchen sink demo template and minimal Symfony 8 demo under `demo/symfony8`.

[Unreleased]: https://github.com/nowo-tech/UiKitBundle/compare/v1.3.0...HEAD
[1.3.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.2.1...v1.3.0
[1.2.1]: https://github.com/nowo-tech/UiKitBundle/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/nowo-tech/UiKitBundle/releases/tag/v1.0.0
