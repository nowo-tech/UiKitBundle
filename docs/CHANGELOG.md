# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## Table of contents

- [Unreleased](#unreleased)
- [1.8.0 - 2026-08-15](#180---2026-08-15)
- [1.7.0 - 2026-08-05](#170---2026-08-05)
- [1.6.0 - 2026-08-04](#160---2026-08-04)
  - [Added](#added)
  - [Changed](#changed)
- [1.5.1 - 2026-08-04](#151---2026-08-04)
  - [Fixed](#fixed)
- [1.5.0 - 2026-08-04](#150---2026-08-04)
  - [Added](#added-1)
- [1.4.0 - 2026-08-04](#140---2026-08-04)
  - [Added](#added-2)
  - [Changed](#changed-1)
  - [Fixed](#fixed-1)
- [1.3.0 - 2026-08-03](#130---2026-08-03)
  - [Added](#added-3)
  - [Changed](#changed-2)
- [1.2.1 - 2026-08-03](#121---2026-08-03)
  - [Fixed](#fixed-2)
- [1.2.0 - 2026-08-03](#120---2026-08-03)
  - [Changed](#changed-3)
  - [Added](#added-4)
- [1.1.0 - 2026-08-03](#110---2026-08-03)
  - [Added](#added-5)
- [1.0.0 - 2026-08-03](#100---2026-08-03)
  - [Added](#added-6)

## [Unreleased]

## [1.8.0] - 2026-08-15

### Added

- **Clipboard IIFE:** `nowo-ui-clipboard.js` (`data-nowo-ui-clipboard*`) → `nowoUiCopyText`.
- **In-page tabs IIFE:** `nowo-ui-tabs.js` (`data-nowo-ui-tabs*`) → `nowoUiActivateTab`. Twig `_tabs` remains link-based navigation.
- **Stimulus peer sources** under `src/Resources/assets/stimulus-peers/`: `clipboard_copy`, `confirm_dialog`, `confirm_submit`, `page_loader`, `toast_stack`, `tabs` (optional; hosts import via AssetMapper/Vite). Docs: [STIMULUS.md](STIMULUS.md).

### Changed

- Vite/`pnpm run build` emits **nine** IIFEs (adds `clipboard`, `tabs`).
- [STIMULUS.md](STIMULUS.md): peer import examples + Beacon attribute migration notes.

[1.8.0]: https://github.com/nowo-tech/UiKitBundle/releases/tag/v1.8.0

## [1.7.0] - 2026-08-05

### Changed

- **`_pagination`:** previous/next use `«` / `»` with translated `aria-label`s; wrap links in `ul.pagination` / `li.page-item` / `a.page-link` for Bootstrap-friendly markup. Optional `trans_domain` / `nav_trans_domain`; `route` defaults to the current request route when omitted.

[1.7.0]: https://github.com/nowo-tech/UiKitBundle/releases/tag/v1.7.0

## [1.6.0] - 2026-08-04

### Added
- **`row_actions_display`** (`icon` \| `text` \| `icon_text`): kit config + Twig global `nowo_ui_kit_row_actions_display`; `_row_actions` emits glyph-only, label-only, or both (REQ-UI-001). Per-include `display` override. CSS modifiers `--icon` / `--text` / `--icon-text` + `nowo-ui-action__label`. Kitchen sink shows all three modes.
- **Canonical row-action API:** `_row_actions` supports `tag` (`a`/`button`/`form`/`auto`), `method` POST + CSRF, `confirm_id` / `modal_id`, `confirm_message` (transitional), `variant` / `icon` / `label` overrides. Expanded kinds: `create`, `save`, `cancel`, `restore`, `share`, `config`, `export`, `filter`. Docs: [ROW_ACTIONS.md](ROW_ACTIONS.md).

### Changed
- `ui.action(kind)` default variant: `edit` / most kinds → **secondary**; `delete` → danger; `create`/`save` → primary (was: edit primary). Third macro arg `variant` overrides.

[1.6.0]: https://github.com/nowo-tech/UiKitBundle/releases/tag/v1.6.0

## [1.5.1] - 2026-08-04

### Fixed
- **Vite / TypeScript CI:** orb remount test used removed state `"idle"`; use a valid `OrbState` (`listening`).

[1.5.1]: https://github.com/nowo-tech/UiKitBundle/releases/tag/v1.5.1

## [1.5.0] - 2026-08-04

### Added
- **REQ-TWIG-004:** require `twig/extra-bundle` + `twig/string-extra`; `make check-twig-extra` in `release-check`; demos register `TwigExtraBundle`.
- **Twig-CS-Fixer:** `vincentlanglet/twig-cs-fixer`, `.twig-cs-fixer.php`, `composer twig:lint` / `twig:fix`.

[1.5.0]: https://github.com/nowo-tech/UiKitBundle/releases/tag/v1.5.0

## [1.4.0] - 2026-08-04

### Added

- **Phase A — feedback:** `_toasts`, `_confirm`, `_page_loader` partials; CSS; TypeScript IIFEs `nowo-ui-toast.js`, `nowo-ui-confirm.js`, `nowo-ui-page-loader.js`.
- **Phase B — layout:** `_card`, `_filters`, `_brand`; nested aside `children` + shell nav-group toggle; card/filters macros.
- **Phase C — host chrome:** `_theme_toggle`, `_locale_switcher`, `_kebab`; `progress` / `progress_bar` / `spinner` macros; `nowo-ui-theme.js`; dark `[data-theme="dark"]` token defaults.
- **Thinking Orbs:** `_thinking_orb.html.twig`, `nowo-ui-orb.js` (local MIT canvas engine from [thinking-orbs](https://orbs.jakubantalik.com)); page loader `visual: orb|spinner` (default `spinner`); [THIRD_PARTY.md](THIRD_PARTY.md).
- **Shell UX:** smoother aside transitions; `_width_toggle.html.twig` (full ↔ content main width) + `nowoUiSetMainWidth` / `nowoUiToggleMainWidth`.
- Badge variants (`success`, `warning`, `danger`, `info`, `neutral`) with BC for `ui.badge('tailwind')`.
- Confirm Twig helpers: `confirm_toggle_attrs`, `confirm_target_attr`, `confirm_dismiss_attrs`.
- Docs: [ADOPTION.md](ADOPTION.md), [STIMULUS.md](STIMULUS.md); kitchen sink covers new surfaces (orbs section).
- i18n keys for toast/loader/filters/theme/locale/layout/orb/cancel/more (`en`, `es`, `it`, `fr`, `pt`, `de`, `nl`).

### Changed

- [ROADMAP.md](ROADMAP.md): phases A–D marked complete on the kit side (consumer migration tracked in ADOPTION).
- Vite/`pnpm run build` emits seven IIFE entrypoints (adds `orb`).
- `_icon.html.twig`: `ux_icon` call deferred to `_icon_ux.html.twig` so hosts without `symfony/ux-icons` can compile templates.

### Fixed

- Demo `config/routes.yaml`: controller path `../src/Controller/` (was `../../…`).
- Demo `web_profiler.yaml`: removed deprecated `framework.profiler.collect_serializer_data` (Symfony 8.1).
- TypeScript coverage gate: Vitest include limited to top-level IIFE sources; `ts-coverage-percent.sh` enforces branches ≥85% (aligned with Vitest / COVERAGE.md).

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

[Unreleased]: https://github.com/nowo-tech/UiKitBundle/compare/v1.4.0...HEAD
[1.4.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.2.1...v1.3.0
[1.2.1]: https://github.com/nowo-tech/UiKitBundle/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/nowo-tech/UiKitBundle/releases/tag/v1.0.0
