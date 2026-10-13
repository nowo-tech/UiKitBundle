# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## Table of contents

- [Unreleased](#unreleased)

- [[1.13.2] - 2026-10-13](#1132---2026-10-13)
- [[1.13.1] - 2026-10-13](#1131---2026-10-13)
- [[1.13.0] - 2026-10-13](#1130---2026-10-13)
- [[1.12.0] - 2026-10-12](#1120---2026-10-12)
- [[1.11.0] - 2026-10-09](#1110---2026-10-09)
- [[1.10.1] - 2026-10-09](#1101---2026-10-09)
- [[1.10.0] - 2026-10-09](#1100---2026-10-09)
- [1.9.2 - 2026-10-09](#192---2026-10-09)
- [1.9.1 - 2026-10-07](#191---2026-10-07)
- [1.9.0 - 2026-10-07](#190---2026-10-07)
- [1.8.4 - 2026-09-25](#184---2026-09-25)
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

## [1.13.2] - 2026-10-13

### Security

- Row actions: the `confirm_message` `onsubmit` handler is now HTML-attribute escaped. Messages containing double quotes previously broke the attribute (the destructive POST was sent without confirmation) and allowed attribute injection / XSS.
- `panel_path_rewrites`: a root target (`'/admin': '/'`) no longer produces protocol-relative `Location: //host` redirects; redirect targets always start with a single `/`. Config validation now rejects prefixes starting with `//` or containing backslashes.

[1.13.2]: https://github.com/nowo-tech/UiKitBundle/compare/v1.13.1...v1.13.2

## [1.13.1] - 2026-10-13

### Changed

- Repository: `.twig-cs-fixer.cache` is now git-ignored.
- Assets: the six `src/Resources/public/js/nowo-ui-*.js` builds were re-minified by Vite 8.3.4 (equivalent output; no behaviour change).

### Dependencies

- `vite` 8.3.2 → 8.3.4 (frontend tooling; #21).
- `happy-dom` 20.14.5 → 20.14.6 (frontend tooling; #22).

[1.13.1]: https://github.com/nowo-tech/UiKitBundle/compare/v1.13.0...v1.13.1

## [1.13.0] - 2026-10-13

### Added

- **Complete `--nowo-ui-*` theming tokens** (Symfony Beacon spec 134 FR-002): every colour, radius, font, focus ring, overlay and shadow used by `nowo-ui.css`, the `ui.*` macros and the partials reads a documented token. New base tokens with light + dark defaults: `--nowo-ui-warning-{bg,border,text}`, `--nowo-ui-info-{bg,border,text}` (were fixed hex). New optional component tokens, resolved at the use site through `var()` fallbacks to the base tokens (so they also work on a scope such as `.kit-admin` and follow dark mode): typography (`--nowo-ui-font-family`, `--nowo-ui-font-display`, `--nowo-ui-heading-weight`), focus (`--nowo-ui-focus-ring-color`, `--nowo-ui-focus-ring`), links (`--nowo-ui-link`, `--nowo-ui-link-hover`, `--nowo-ui-primary-hover`, `--nowo-ui-accent`), radii / overlays / shadows (`--nowo-ui-badge-radius`, `--nowo-ui-modal-radius`, `--nowo-ui-card-radius`, `--nowo-ui-overlay`, `--nowo-ui-shadow-{popover,toast,dialog}`), buttons (`--nowo-ui-btn-*`: base, hover, primary, danger, outline), inputs (`--nowo-ui-input-{bg,text,border,radius,placeholder,focus-border}`), tables (`--nowo-ui-table-{border,head-bg,head-text,stripe-bg,hover-bg}`), tabs (`--nowo-ui-tabs-{border,gap}`, `--nowo-ui-tab-*` incl. active / hover), pagination (`--nowo-ui-pagination-*` incl. hover / active), lists (`--nowo-ui-list-*`), cards, aside current link (`--nowo-ui-nav-active-{bg,text}`), progress.
- **Theming guide** [docs/THEMING.md](THEMING.md): every token with its default, framework notes, a generic host theme and the Symfony Beacon (moss / sand) mapping.
- `.nowo-ui-btn--outline` variant (`ui.btn('outline')`; Bootstrap: `btn-outline-primary`), `.nowo-ui-link` (`ui.link()`), `.nowo-ui-check` checkbox / radio label (`ui.check()`), `accent-color` on checkboxes / radios inside kit surfaces.
- `:focus-visible` rings (token-driven) on buttons, links, tabs, pagination links, toggles, menus, modal close, aside links; focused `.nowo-ui-input` gets the primary border + ring.
- Hover states for the kit-painted stacks (`custom` / `none` / `tailwind`): buttons (replacing the removed Tailwind `hover:bg-*` utilities, same default colours), pagination links, inactive tabs, plain `.nowo-ui-table` rows (matching the `table-hover` the Bootstrap branch already emits).
- **Opt-in Bootstrap token bridge** `.nowo-ui-bs-tokens`: on an ancestor, paints Bootstrap 5.3 buttons, forms, tables, list groups, pagination, nav tabs, cards, modals, progress, alerts, `text-muted` and kit badges from the same tokens. No effect without the class.
- Twig tests guarding the contract: no palette / radius / divide utilities in any macro output (tailwind, custom, none, foundation), no hex in partials / components / macros or outside the two token blocks of `nowo-ui.css`, base tokens present in light + dark, component tokens never declared, bridge rules scoped, every token documented.

### Changed

- **Tailwind macro output no longer carries palette, radius or divide utilities** (`bg-blue-600`, `hover:bg-blue-500`, `text-white`, `border-red-500`, `text-red-600`, `hover:bg-red-50`, `text-blue-600`, `hover:underline`, `border-slate-*`, `text-slate-*`, `hover:bg-slate-50`, `bg-emerald/amber/sky/red-*`, `divide-y`, `divide-slate-200`, `rounded-md`, `rounded-full`, `bg-white`, `bg-slate-200`, `border-blue-600`, `text-blue-700`, `border-t-blue-600`, `animate-spin`). The semantic `nowo-ui-*` classes (unchanged) paint the same default colours from tokens; layout / spacing / type-scale utilities stay. `ui.muted()` in Tailwind no longer appends `text-slate-500` (`.nowo-ui-muted` has the same `#64748b`).
- `.nowo-ui-input` (non-Bootstrap) now paints `--nowo-ui-input-bg` / `--nowo-ui-input-text` (white / text in light — same as the browser default; dark mode now gets the dark surface instead of a white field).
- Warning / info flashes and badges have dark-mode defaults (they kept the light hex before).
- `_pagination.html.twig`'s `<ul class="pagination">` lays out as a wrapping row with a 0.25rem gap on every stack (zero-specificity rule; it stacked vertically on `custom` / `tailwind` unless the host fixed it).
- Modal hex fallbacks (`var(--nowo-ui-surface, #ffffff)` …) removed — base tokens are always declared.

[1.13.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.12.0...v1.13.0

## [1.12.0] - 2026-10-12

### Added

- **Open modal on load (deep link):** `partials/_open_modal_on_load.html.twig` emits a CSP-safe JSON island `script#nowo-ui-open-modal-on-load` (`modal`, optional `trigger`, `param` default `new` — rendered only when `?new=1`; hex-escaped JSON; request `csp_nonce`). IIFE `js/nowo-ui-open-modal.js` (`window.nowoUiOpenModalOnLoad`) clicks the trigger / opens the modal directly, retries while kit scripts boot, then drops the parameter from the URL. Side-effect-free helpers for bundled hosts in `assets/src/nowo-ui-open-modal-core.ts` (`openModalOnLoad`, `readOpenModalConfig`, `tryOpenModal`, `isModalOpen`, `clearQueryParam`, `OPEN_MODAL_ISLAND_ID`). Ported from Symfony Beacon.
- **Stimulus peer `auto-refresh`** (`stimulus-peers/auto_refresh_controller.ts`): periodic same-origin re-render of `[data-auto-refresh-region]` elements by id; `interval` / `url` values, `toggle` / `status` targets, pauses on hidden tabs, `auto-refresh:refreshed` event. Ported from Symfony Beacon.
- **Opt-in filter bar / data table CSS:** `.nowo-ui-filter-bar` (explicit 38px control height, compact 34px via `--compact` or `[data-ui-density="compact"]`, actions on the field baseline, `__actions--pager` keeps pager + Filter + Clear on one row, `__help` visually hidden) and `table.nowo-ui-data-table` (`--compact`, `--nowo-ui-table-*` tokens). Partial `partials/_filter_actions.html.twig` (pre-rendered `pager`, `clear_href`, label / class overrides). Existing selectors unchanged.
- Vitest coverage for the new modules and the `auto-refresh` peer (gated at 100%); Twig tests for both partials.

[1.12.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.11.0...v1.12.0

## [1.11.0] - 2026-10-09

### Added

- **Page loader minimal visuals:** `_page_loader.html.twig` `visual: bar | bar_loop | bar_spinner | corner_spinner | dots | glow | corner_mark` (`mark_src` / `mark_alt`). No veil, no pointer capture, delayed fade-in (fast loads show nothing), keyframe-only children. CSS variables `--nowo-ui-page-loader-color`, `--nowo-ui-page-loader-corner-bottom`, `--nowo-ui-page-loader-corner-left`.
- `_page_loader.html.twig` `stimulus: true`: attaches the `page-loader` peer with timing that matches the visual (`min_visible_ms` / `leave_ms` overrides).
- **Page transitions:** `partials/_page_transition.html.twig` — cross-document View Transitions opt-in in one nonce-aware `<style>` (`style: fade_slide | fade | slide | zoom | blur | wipe | none`, optional `persist` selector → name map). No JS; off under `prefers-reduced-motion: reduce`.
- CSS: `.nowo-ui-page-loader.is-leaving` fade for the veil when driven by the Stimulus peer.

[1.11.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.10.1...v1.11.0

## [1.10.1] - 2026-10-09

### Fixed

- `page-loader` peer: the leave only ends on the overlay's own `opacity` `transitionend`. The event bubbles, so a child transition (logo, label…) finishing first used to hide the veil mid-fade (visible cut).
- `page-loader` peer: the leave fallback timer no longer hides an overlay that was shown again before it fired (e.g. quick back-to-back navigations).

### Added

- Vitest coverage for the `page-loader` peer (`stimulus-peers/page_loader_controller.test.ts`).

[1.10.1]: https://github.com/nowo-tech/UiKitBundle/compare/v1.10.0...v1.10.1

## [1.10.0] - 2026-10-09

### Added

- **Release check (optional, off by default):** `nowo_ui_kit.release_check` (`enabled`, `github_repo`, `current_version`, `cache_ttl`, `default_branch`, `user_agent`). `ReleaseUpdateChecker` compares the installed clean semver with GitHub `releases/latest` (cached, soft-fail, counts newer non-prerelease releases); `ReleaseStatusController` + opt-in route file `@NowoUiKitBundle/Resources/config/routes/release_check.yaml` (`GET /_nowo-ui/release/status`, 404 while disabled); partial `partials/_release_version.html.twig`; IIFE `js/nowo-ui-release.js` (`window.nowoUiLoadReleaseStatus`); Stimulus peer `release_status_controller.ts`; `release.*` translations (7 locales); `.nowo-ui-release*` CSS. Ported from the podologiapriego-web footer.
- Stimulus peers are now type-checked (`tsconfig.json`) and tested with Vitest (`stimulus-peers/peers.test.ts`; dev dependency `@hotwired/stimulus`).
- Dev dependency `symfony/http-client` (tests / PHPStan for the release check).

### Changed

- `confirm-submit` peer: documented single-button usage (`click->confirm-submit#confirm`) next to form `submit`, plus `blocked` / empty-message behaviour (no API change).

### Fixed

- `tabs` peer: `activeTabValue` was declared `readonly`, so `open()` failed type-checking when the peer was compiled by a strict host TypeScript build.

## [1.9.2] - 2026-10-09

### Dependencies

- Bundle lockfile: Symfony 8.1.8, `twig/twig` 3.30.0; dev `phpstan/phpstan` 2.3.1, `phpstan/phpstan-symfony` 2.1.0, `nowo-tech/phpstan-frankenphp` 1.2.3, `rector/rector` 2.7.0.
- Demos (`demo/symfony8`, `demo/symfony8-tailwind`): Symfony 8.1.8, `twig/twig` 3.30.0; regenerated `config/reference.php`.

[1.9.2]: https://github.com/nowo-tech/UiKitBundle/compare/v1.9.1...v1.9.2

## [1.9.1] - 2026-10-07

### Fixed

- `LegacyPanelPathRedirectSubscriber` now listens at `kernel.request` priority **64** (above `RouterListener` at 32) so legacy `/admin/…` paths 301 instead of 404 when the rewritten route is not matched first.

[1.9.1]: https://github.com/nowo-tech/UiKitBundle/compare/v1.9.0...v1.9.1

## [1.9.0] - 2026-10-07

### Added

- **`nowo_ui_kit.panel_path_rewrites`** (default `{}`): map of legacy path prefix → new prefix.
- **`PanelPathRewriter`** and **`PanelPathRewritingLoader`**: when the map is non-empty, `routing.loader` is decorated and route paths are rewritten (longest prefix first, segment-boundary matching).
- **`LegacyPanelPathRedirectSubscriber`**: permanent redirect (301 for GET/HEAD, 308 otherwise) from legacy prefixes to the new ones, preserving sub-path and query string.
- Parameter `%nowo_ui_kit.panel_path_rewrites%`; config validation rejects non-absolute prefixes and targets nested under their source (redirect loops).

### Changed

- None for existing setups: an empty map registers no routing services (BC).

[1.9.0]: https://github.com/nowo-tech/UiKitBundle/releases/tag/v1.9.0

## [1.8.5] - 2026-09-27

### Added

- **REQ-CS-008:** `igor-php/igor-php` (require-dev only), root `igor.json`, Composer/`Makefile` `igor` target, and `release-check` wiring for FrankenPHP worker-state audit.

### Changed

- **Worker safety (Igor):** justified `// @igor-ignore` annotations and/or `ResetInterface` / request-scoped fixes so `make igor` passes on package `src/`.

[1.8.5]: https://github.com/nowo-tech/UiKitBundle/releases/tag/v1.8.5

## [1.8.4] - 2026-09-25

### Added

- **Docs:** [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md) — full audit confirming **100%** compatibility with FrankenPHP worker mode when the Symfony kernel is **not** reset between requests (no runtime PHP services / no per-request mutable state).
- Spec baseline: `FR-FRANKENPHP-001` / `FR-FRANKENPHP-002` + user story for worker / `reset_kernel=false`.

### Changed

- README, [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md), and [SECURITY.md](SECURITY.md) link the worker audit; README states worker friendliness including no kernel reset.
- Rector: `NowoUiKitExtension` translations path uses `__DIR__.'/../Resources/translations'`.

## [1.8.3] - 2026-08-24

### Changed

- Raise minimum PHP to **8.2** and sync README badge (REQ-SF-001).
- **QA:** add `phpstan-frankenphp` extension (REQ-CS-005).
- **Assets:** English JSDoc on TypeScript sources (REQ-ASSETS-002).
- **Docs:** PHP-FIG PSR evaluation (REQ-CS-007).

### Notes

- **No API or configuration changes** for integrators unless noted above.

[1.8.3]: https://github.com/nowo-tech/UiKitBundle/releases/tag/v1.8.3

## [1.8.2] - 2026-08-19

### Security

- **CI:** run `composer audit --locked` after dependency install (REQ-SEC / P3).

### Changed

- **Maintainer:** PR template checklist; keep `tests/Integration` for REQ-TEST-002.

## [1.8.1] - 2026-08-18

### Changed

- **Demos:** pin `nowo-tech/hot-reload-bundle` to `^1.4` with FrankenPHP Mercure/`hot_reload` (`dev`/`test` only).

[1.8.1]: https://github.com/nowo-tech/UiKitBundle/releases/tag/v1.8.1

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

[Unreleased]: https://github.com/nowo-tech/UiKitBundle/compare/v1.11.0...HEAD
[1.10.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.8.4...v1.10.0
[1.8.4]: https://github.com/nowo-tech/UiKitBundle/compare/v1.8.3...v1.8.4
[1.4.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.2.1...v1.3.0
[1.2.1]: https://github.com/nowo-tech/UiKitBundle/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/nowo-tech/UiKitBundle/releases/tag/v1.0.0
