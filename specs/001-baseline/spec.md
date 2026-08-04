# Feature Specification: UiKitBundle baseline (100% code coverage)

**Feature Branch**: `001-baseline`  
**Created**: 2026-08-03  
**Updated**: 2026-08-04  
**Status**: Active  
**Input**: Backfill GitHub Spec Kit baseline documenting 100% of production code in `src/`.

**Related docs**: [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/USAGE.md`](../../docs/USAGE.md), [`docs/STIMULUS.md`](../../docs/STIMULUS.md), [`docs/THIRD_PARTY.md`](../../docs/THIRD_PARTY.md), [`docs/ROADMAP.md`](../../docs/ROADMAP.md)  
**Code inventory (traceability)**: [`code-inventory.md`](code-inventory.md)

---

## Summary

**Package**: `nowo-tech/ui-kit-bundle`  
**Configuration root**: `nowo_ui_kit`  
**Target release**: **1.4.0** (phases A–D on the kit side)

Canonical **admin UI kit** for Nowo Symfony bundles and host apps: Twig macros, semantic `nowo-ui-*` CSS, pagination/tabs/modals/toasts/confirm/page loader, shell chrome (aside, width toggle, theme), Thinking Orbs (local MIT canvas), and multi-framework class helpers (`bootstrap5`, `tailwind`, `foundation`, `custom`, …) per **REQ-UI-001**. No manage/CRUD routes (REQ-UI-002 N/A).

---

## User Scenarios & Testing

### User Story 1 — Multi-framework button / toolbar classes (Priority: P1)

As a Twig author, I import `@NowoUiKitBundle/macros/ui.html.twig` and call `ui.btn('primary')` so buttons get framework-aware classes plus semantic `nowo-ui-*` hooks.

**Independent Test**: Configure `css_framework: bootstrap5` → render macro → HTML contains Bootstrap button classes and `nowo-ui-btn`.

**Acceptance Scenarios**:

1. **Given** `css_framework: bootstrap5`, **When** `ui.btn('primary')` renders, **Then** output includes Bootstrap primary button classes.
2. **Given** per-call `framework: tailwind`, **When** macro renders, **Then** Tailwind-oriented classes are used for that call.
3. **Given** `css_framework: custom`, **When** macros render, **Then** semantic `nowo-ui-*` classes remain usable with host CSS.

---

### User Story 2 — Shell chrome (aside / burger / avatar / footer / width) (Priority: P1)

As an admin host, I include `_shell` / macros so aside, burger, avatar, user menu, footer, and optional width toggle share one look-and-feel.

**Independent Test**: Kitchen sink demo with shell JS → burger toggles drawer; aside collapses smoothly on desktop; width toggle switches full ↔ content.

**Acceptance Scenarios**:

1. **Given** `_shell.html.twig` included, **When** page loads with `js/nowo-ui-shell.js`, **Then** burger toggles mobile drawer and desktop collapse animates smoothly.
2. **Given** user menu partial, **When** rendered with logout link, **Then** avatar + dropdown markup is present.
3. **Given** `_width_toggle.html.twig` in the header, **When** toggled, **Then** shell gains/loses `is-main-content` and preference may persist in `localStorage`.

---

### User Story 3 — Modal shell + TypeScript helper (Priority: P1)

As an integrator on `custom` / `tailwind` / `none` stacks, I use `_modal_shell` + `nowo-ui-modal.js` for accessible open/close without Bootstrap JS.

**Independent Test**: Vitest covers modal helpers; demo opens modal via data attributes.

**Acceptance Scenarios**:

1. **Given** modal partial + built IIFE, **When** trigger clicked, **Then** dialog opens and focus is managed.
2. **Given** Escape / backdrop click, **When** handled, **Then** modal closes.

---

### User Story 4 — Configure css_framework / icon_set / row_actions_display (Priority: P2)

As an integrator, I set `nowo_ui_kit.css_framework`, `icon_set`, and `row_actions_display` in YAML; Twig globals expose the values.

**Acceptance Scenarios**:

1. **Given** valid enum values, **When** container compiles, **Then** globals `nowo_ui_kit_css_framework` / `nowo_ui_kit_icon_set` / `nowo_ui_kit_row_actions_display` are set.
2. **Given** invalid framework string, **When** config is loaded, **Then** configuration validation fails at compile time.
3. **Given** `icon_set` other than `ux_icon`, **When** `_icon.html.twig` compiles without `symfony/ux-icons`, **Then** templates compile (ux call lives only in `_icon_ux.html.twig`).
4. **Given** `row_actions_display: text` (or per-include `display: text`), **When** `_row_actions` renders, **Then** actions show visible labels without icon glyphs.

---

### User Story 5 — i18n parity (Priority: P2)

As a multilingual host, I override domain `NowoUiKitBundle` translations for `en`, `es`, `it`, `fr`, `pt`, `de`, `nl`.

**Acceptance Scenarios**:

1. **Given** all seven locale YAML files, **When** keys are compared, **Then** key sets match (REQ-I18N-002), including `orb.*` / `layout.*` / theme / loader keys.
2. **Given** app override under `translations/`, **When** locale is `es`, **Then** overridden strings win (REQ-I18N-001).

---

### User Story 6 — Feedback surfaces (toasts / confirm / page loader) (Priority: P1)

As an admin host, I include toast stack, confirm dialog, and page loader partials with matching IIFEs.

**Acceptance Scenarios**:

1. **Given** `_toasts.html.twig` + `nowo-ui-toast.js`, **When** dismiss is clicked, **Then** toast is removed.
2. **Given** `_confirm.html.twig` + `nowo-ui-confirm.js`, **When** open trigger fires, **Then** dialog opens; host owns POST+CSRF in footer.
3. **Given** `_page_loader.html.twig` with `visual: spinner` (default), **When** show trigger fires, **Then** overlay is active.
4. **Given** `_page_loader.html.twig` with `visual: orb`, **When** `nowo-ui-orb.js` is loaded, **Then** Thinking Orb canvas mounts inside the loader.

---

### User Story 7 — Thinking Orbs (Priority: P1)

As an AI/agent UI host, I render dotted thought-orbs via `_thinking_orb.html.twig` + local `nowo-ui-orb.js` (no CDN, no React package).

**Independent Test**: Vitest mounts canvas with mocked 2D context; kitchen sink `#orbs` section shows nine states.

**Acceptance Scenarios**:

1. **Given** `canvas[data-nowo-ui-orb]` and `nowo-ui-orb.js`, **When** DOM loads, **Then** orb auto-mounts with `role="img"` and aria-label.
2. **Given** `data-state` / `data-size` (`20`|`64`) / `data-theme`, **When** mount runs, **Then** animation uses the corresponding preset.
3. **Given** `prefers-reduced-motion: reduce`, **When** mounted, **Then** a static frame is painted (no continuous rAF).
4. **Given** MIT vendor attribution, **When** docs are read, **Then** [THIRD_PARTY.md](../../docs/THIRD_PARTY.md) documents thinking-orbs.

---

### Edge Cases

- Twig namespace `NowoUiKitBundle` with app override path `templates/bundles/NowoUiKitBundle/` (REQ-TWIG-001/002).
- Asset package `nowo_ui_kit` base path `/bundles/nowouikit`.
- TypeScript sources under `Resources/assets/src/` (including `orb/` engine); published IIFEs under `Resources/public/js/` (REQ-ASSETS-001/002). Seven Vite entries: modal, shell, toast, confirm, page-loader, theme, orb.
- Co-located `*.test.ts` files are **test artifacts** (FR-TEST-*), not runtime production units.
- `symfony/ux-icons` is optional (`suggest`); only required when `icon_set: ux_icon`.

---

## Requirements (functional)

### Bundle & DI

- **FR-BUNDLE-001**: `NowoUiKitBundle` registers the extension and compiler pass.
- **FR-CFG-001**: `Configuration` defines `css_framework`, `icon_set`, and `row_actions_display` enums.
- **FR-CFG-002**: `NowoUiKitExtension` loads services, sets Twig globals, registers asset package.
- **FR-TWIG-001**: `TwigPathsPass` ensures namespace `NowoUiKitBundle`.
- **FR-ENUM-001**: `CssFramework` / `IconSet` / `RowActionsDisplay` backed enums used by configuration.

### Twig & assets

- **FR-MACRO-001**: `macros/ui.html.twig` exposes btn, toolbar, table, pagination, tabs, shell, card, filters, progress, spinner, badge variants, modal/confirm attrs, etc.
- **FR-PARTIAL-001**: Partials for pagination, empty, flashes, toasts, confirm, page loader, card, filters, brand, theme/width/locale/kebab toggles, modal, shell chrome, thinking orb; `_row_actions` supports `display` `icon`|`text`|`icon_text`.
- **FR-ICON-001**: `_icon.html.twig` branches on `icon_set`; `_icon_ux.html.twig` isolates optional `ux_icon()` compile dependency.
- **FR-CSS-001**: `css/nowo-ui.css` semantic styles + CSS variables (incl. shell transitions, orb, width, dark tokens).
- **FR-JS-001**: Built IIFEs `nowo-ui-modal.js`, `nowo-ui-shell.js`, `nowo-ui-toast.js`, `nowo-ui-confirm.js`, `nowo-ui-page-loader.js`, `nowo-ui-theme.js`, `nowo-ui-orb.js` from Vite TS entries.
- **FR-ORB-001**: Local Thinking Orbs engine under `assets/src/orb/` (MIT adapted from thinking-orbs); `mountThinkingOrb` + auto-mount on `[data-nowo-ui-orb]`.
- **FR-ORB-002**: Page loader supports `visual: spinner|orb` (default `spinner` for BC).
- **FR-I18N-001**: Translation domain files for seven locales (incl. `orb.*`, `layout.*`, theme, loader, toast, filters).

### Tests (non-packaged runtime)

- **FR-TEST-001**: Vitest suites for modal, shell, toast, confirm, page-loader, theme, orb.
- **FR-BUILD-001**: `vite.config.ts` / `pnpm run build` emit seven IIFE outputs into `public/js/`.

---

## Success criteria

- Every production file under `src/` (excluding `*.test.ts`) appears in [`code-inventory.md`](code-inventory.md).
- PHPUnit + Vitest + `coverage-check` (≥99% PHP lines) pass in CI.
- FrankenPHP demos boot on ports 8092 / 8093 with HTTP 200.
- Kitchen sink demonstrates feedback surfaces, shell width toggle, and Thinking Orbs states.
