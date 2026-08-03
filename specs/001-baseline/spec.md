# Feature Specification: UiKitBundle baseline (100% code coverage)

**Feature Branch**: `001-baseline`  
**Created**: 2026-08-03  
**Status**: Active  
**Input**: Backfill GitHub Spec Kit baseline documenting 100% of production code in `src/`.

**Related docs**: [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/USAGE.md`](../../docs/USAGE.md)  
**Code inventory (traceability)**: [`code-inventory.md`](code-inventory.md)

---

## Summary

**Package**: `nowo-tech/ui-kit-bundle`  
**Configuration root**: `nowo_ui_kit`

Canonical **admin UI kit** for Nowo Symfony bundles and host apps: Twig macros, semantic `nowo-ui-*` CSS, pagination/tabs/modals/shell chrome, and multi-framework class helpers (`bootstrap5`, `tailwind`, `foundation`, `custom`, …) per **REQ-UI-001**. No manage/CRUD routes (REQ-UI-002 N/A).

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

### User Story 2 — Shell chrome (aside / burger / avatar / footer) (Priority: P1)

As an admin host, I include `_shell` / macros so aside, burger, avatar, user menu, and footer share one look-and-feel.

**Independent Test**: Kitchen sink demo with shell JS → burger toggles drawer; aside collapses on desktop.

**Acceptance Scenarios**:

1. **Given** `_shell.html.twig` included, **When** page loads with `js/nowo-ui-shell.js`, **Then** burger toggles mobile drawer.
2. **Given** user menu partial, **When** rendered with logout link, **Then** avatar + dropdown markup is present.

---

### User Story 3 — Modal shell + TypeScript helper (Priority: P1)

As an integrator on `custom` / `tailwind` / `none` stacks, I use `_modal_shell` + `nowo-ui-modal.js` for accessible open/close without Bootstrap JS.

**Independent Test**: Vitest covers modal helpers; demo opens modal via data attributes.

**Acceptance Scenarios**:

1. **Given** modal partial + built IIFE, **When** trigger clicked, **Then** dialog opens and focus is managed.
2. **Given** Escape / backdrop click, **When** handled, **Then** modal closes.

---

### User Story 4 — Configure css_framework / icon_set (Priority: P2)

As an integrator, I set `nowo_ui_kit.css_framework` and `icon_set` in YAML; Twig globals expose the values.

**Acceptance Scenarios**:

1. **Given** valid enum values, **When** container compiles, **Then** globals `nowo_ui_kit_css_framework` / `nowo_ui_kit_icon_set` are set.
2. **Given** invalid framework string, **When** config is loaded, **Then** configuration validation fails at compile time.

---

### User Story 5 — i18n parity (Priority: P2)

As a multilingual host, I override domain `NowoUiKitBundle` translations for `en`, `es`, `it`, `fr`, `pt`, `de`, `nl`.

**Acceptance Scenarios**:

1. **Given** all seven locale YAML files, **When** keys are compared, **Then** key sets match (REQ-I18N-002).
2. **Given** app override under `translations/`, **When** locale is `es`, **Then** overridden strings win (REQ-I18N-001).

---

### Edge Cases

- Twig namespace `NowoUiKitBundle` with app override path `templates/bundles/NowoUiKitBundle/` (REQ-TWIG-001/002).
- Asset package `nowo_ui_kit` base path `/bundles/nowouikit`.
- TypeScript sources under `Resources/assets/src/`; published IIFEs under `Resources/public/js/` (REQ-ASSETS-001/002).
- Co-located `*.test.ts` files are **test artifacts** (FR-TEST-*), not runtime production units.

---

## Requirements (functional)

### Bundle & DI

- **FR-BUNDLE-001**: `NowoUiKitBundle` registers the extension and compiler pass.
- **FR-CFG-001**: `Configuration` defines `css_framework` and `icon_set` enums.
- **FR-CFG-002**: `NowoUiKitExtension` loads services, sets Twig globals, registers asset package.
- **FR-TWIG-001**: `TwigPathsPass` ensures namespace `NowoUiKitBundle`.
- **FR-ENUM-001**: `CssFramework` / `IconSet` backed enums used by configuration.

### Twig & assets

- **FR-MACRO-001**: `macros/ui.html.twig` exposes btn, toolbar, table, pagination, tabs, shell, etc.
- **FR-PARTIAL-001**: Partials for pagination, empty, flashes, modal, shell chrome pieces.
- **FR-ICON-001**: `_icon.html.twig` branches on `icon_set`.
- **FR-CSS-001**: `css/nowo-ui.css` semantic styles + CSS variables.
- **FR-JS-001**: Built `nowo-ui-modal.js` / `nowo-ui-shell.js` from Vite TS entries.
- **FR-I18N-001**: Translation domain files for seven locales.

### Tests (non-packaged runtime)

- **FR-TEST-001**: Vitest suites `nowo-ui-modal.test.ts` / `nowo-ui-shell.test.ts`.
- **FR-BUILD-001**: `vite.config.ts` builds IIFE outputs into `public/js/`.

---

## Success criteria

- Every production file under `src/` (excluding `*.test.ts`) appears in [`code-inventory.md`](code-inventory.md).
- PHPUnit + Vitest + `coverage-check` (≥99% PHP lines) pass in CI.
- FrankenPHP demos boot on ports 8092 / 8093 with HTTP 200.
