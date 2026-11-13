# Feature Specification: UiKitBundle baseline (100% code coverage)

**Feature Branch**: `001-baseline`  
**Created**: 2026-08-03  
**Updated**: 2026-11-01 (2.21.0 — inventory re-audited, 306/306 production files; 2.x surfaces added: page contract,
appearance, listing customizer, tour, error pages, Form Types, sortable headers, detail page patterns, font preload)  
**Status**: Active  
**Input**: Backfill GitHub Spec Kit baseline documenting 100% of production code in `src/`.

**Related docs**: [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/USAGE.md`](../../docs/USAGE.md), [`docs/PAGE_CONTRACT.md`](../../docs/PAGE_CONTRACT.md), [`docs/THEMING.md`](../../docs/THEMING.md), [`docs/STIMULUS.md`](../../docs/STIMULUS.md), [`docs/THIRD_PARTY.md`](../../docs/THIRD_PARTY.md), [`docs/ROADMAP.md`](../../docs/ROADMAP.md), [`docs/FRANKENPHP-WORKER-AUDIT.md`](../../docs/FRANKENPHP-WORKER-AUDIT.md), [`docs/UPGRADING.md`](../../docs/UPGRADING.md)  
**Code inventory (traceability)**: [`code-inventory.md`](code-inventory.md)  
**Feature specs**: [`002-theming-tokens`](../002-theming-tokens/spec.md) (1.13), [`003-host-chrome-extractions`](../003-host-chrome-extractions/spec.md) (1.15), [`004-appearance-spi`](../004-appearance-spi/spec.md) (1.16), [`005-host-chrome-tables-filters`](../005-host-chrome-tables-filters/spec.md) (1.17), [`006-action-csrf-forms`](../006-action-csrf-forms/spec.md) (2.18), [`007-form-types-everywhere`](../007-form-types-everywhere/spec.md) (2.19), [`008-sortable-columns`](../008-sortable-columns/spec.md) (2.20), [`009-detail-page-patterns`](../009-detail-page-patterns/spec.md) (2.21)

---

## Summary

**Package**: `nowo-tech/ui-kit-bundle`  
**Configuration root**: `nowo_ui_kit`  
**Target release**: **2.21.x** (baseline kept current with shipped surfaces)

Canonical **admin UI kit** for Nowo Symfony bundles and host apps:

- **1.x building blocks** (REQ-UI-001): Twig macros, semantic `nowo-ui-*` CSS and `--nowo-ui-*` tokens, partials
  (pagination, tabs, modals, toasts, confirm, page loader, shell chrome, filters, responsive tables), Thinking Orbs
  (local MIT canvas) and multi-framework class helpers (`bootstrap5`, `tailwind`, `foundation`, `custom`, …).
- **2.x standard page contract** (REQ-UI-003): canonical layouts, page scaffolds (list, show, form, settings,
  dashboard, confirm, wizard, auth), immutable view models (`View\*`), one global host theme (slot classes,
  attributes, partial overrides, tokens, per-page-type and per-bundle exceptions), kit error pages, app shell, and the
  `PageContractTestCase` test kit for UI bundles.
- **Opt-in services**: appearance SPI, listing customizer (cookie preferences), product tour, area navigation
  providers, release check, panel path rewrites, route links, font preload.
- **Forms** (REQ-TWIG-005): every form the kit renders is a Symfony Form Type (2.18 / 2.19); deprecated raw fallbacks
  remain until 3.0 for hosts without `symfony/form`.
- **Frontend**: 17 Vite IIFEs (no build needed by hosts) plus optional Stimulus peers and morphicons helpers.

No manage/CRUD routes (REQ-UI-002 N/A). No Doctrine entity; persistence is always the host's.

---

## User Scenarios & Testing

### US-001 — Multi-framework button / toolbar classes (Priority: P1)

As a Twig author, I import `@NowoUiKitBundle/macros/ui.html.twig` and call `ui.btn('primary')` so buttons get framework-aware classes plus semantic `nowo-ui-*` hooks.

**Independent Test**: Configure `css_framework: bootstrap5` → render macro → HTML contains Bootstrap button classes and `nowo-ui-btn`.

**Acceptance Scenarios**:

1. **Given** `css_framework: bootstrap5`, **When** `ui.btn('primary')` renders, **Then** output includes Bootstrap primary button classes.
2. **Given** per-call `framework: tailwind`, **When** macro renders, **Then** Tailwind-oriented classes are used for that call.
3. **Given** `css_framework: custom`, **When** macros render, **Then** semantic `nowo-ui-*` classes remain usable with host CSS.

---

### US-002 — Shell chrome (aside / burger / avatar / footer / width) (Priority: P1)

As an admin host, I include `_shell` / macros so aside, burger, avatar, user menu, footer, and optional width toggle share one look-and-feel.

**Independent Test**: Kitchen sink demo with shell JS → burger toggles drawer; aside collapses smoothly on desktop; width toggle switches full ↔ content.

**Acceptance Scenarios**:

1. **Given** `_shell.html.twig` included, **When** page loads with `js/nowo-ui-shell.js`, **Then** burger toggles mobile drawer and desktop collapse animates smoothly.
2. **Given** user menu partial, **When** rendered with logout link, **Then** avatar + dropdown markup is present.
3. **Given** `_width_toggle.html.twig` in the header, **When** toggled, **Then** shell gains/loses `is-main-content` and preference may persist in `localStorage`.

---

### US-003 — Modal shell + TypeScript helper (Priority: P1)

As an integrator on `custom` / `tailwind` / `none` stacks, I use `_modal_shell` + `nowo-ui-modal.js` for accessible open/close without Bootstrap JS.

**Independent Test**: Vitest covers modal helpers; demo opens modal via data attributes.

**Acceptance Scenarios**:

1. **Given** modal partial + built IIFE, **When** trigger clicked, **Then** dialog opens and focus is managed.
2. **Given** Escape / backdrop click, **When** handled, **Then** modal closes.

---

### US-004 — Configure css_framework / icon_set / row_actions_display (Priority: P2)

As an integrator, I set `nowo_ui_kit.css_framework`, `icon_set`, and `row_actions_display` in YAML; Twig globals expose the values.

**Acceptance Scenarios**:

1. **Given** valid enum values, **When** container compiles, **Then** globals `nowo_ui_kit_css_framework` / `nowo_ui_kit_icon_set` / `nowo_ui_kit_row_actions_display` are set.
2. **Given** invalid framework string, **When** config is loaded, **Then** configuration validation fails at compile time.
3. **Given** `icon_set` other than `ux_icon`, **When** `_icon.html.twig` compiles without `symfony/ux-icons`, **Then** templates compile (ux call lives only in `_icon_ux.html.twig`).
4. **Given** `row_actions_display: text` (or per-include `display: text`), **When** `_row_actions` renders, **Then** actions show visible labels without icon glyphs.

---

### US-005 — i18n parity (Priority: P2)

As a multilingual host, I override domain `NowoUiKitBundle` translations for `en`, `es`, `it`, `fr`, `pt`, `de`, `nl`.

**Acceptance Scenarios**:

1. **Given** all seven locale YAML files, **When** keys are compared, **Then** key sets match (REQ-I18N-002), including `orb.*` / `layout.*` / theme / loader / `table.sort.*` keys.
2. **Given** app override under `translations/`, **When** locale is `es`, **Then** overridden strings win (REQ-I18N-001).

---

### US-006 — Feedback surfaces (toasts / confirm / page loader) (Priority: P1)

As an admin host, I include toast stack, confirm dialog, and page loader partials with matching IIFEs.

**Acceptance Scenarios**:

1. **Given** `_toasts.html.twig` + `nowo-ui-toast.js`, **When** dismiss is clicked, **Then** toast is removed.
2. **Given** `_confirm.html.twig` + `nowo-ui-confirm.js`, **When** open trigger fires, **Then** dialog opens; host owns POST+CSRF in footer.
3. **Given** `_page_loader.html.twig` with `visual: spinner` (default), **When** show trigger fires, **Then** overlay is active.
4. **Given** `_page_loader.html.twig` with `visual: orb`, **When** `nowo-ui-orb.js` is loaded, **Then** Thinking Orb canvas mounts inside the loader.
5. **Given** `_page_loader.html.twig` with a minimal visual (`bar`, `bar_loop`, `bar_spinner`, `corner_spinner`, `dots`, `glow`, `corner_mark`), **When** the page loads fast, **Then** nothing is visible and the page is never blocked.
6. **Given** `_page_transition.html.twig` in `<head>` on two pages, **When** navigating between them in a browser with cross-document View Transitions, **Then** the pages cross-fade with the chosen style (none under reduced motion).
7. **Given** `_open_modal_on_load.html.twig` with `modal` (+ optional `trigger`) and a request carrying `?new=1`, **When** `nowo-ui-open-modal.js` runs, **Then** the modal opens (trigger click first, direct open as fallback, retried while kit scripts boot) and `new` is removed from the URL; without the parameter nothing is rendered.
8. **Given** `data-controller="auto-refresh"` with `[data-auto-refresh-region]` children carrying ids, **When** the interval elapses on a visible tab and refresh is not paused, **Then** each region is replaced by the same-id element of a same-origin GET of the page.
9. **Given** a `<form class="nowo-ui-filter-bar">` with `_filter_actions.html.twig` (`pager` passed), **When** rendered in any engine, **Then** single-line controls and actions share one height (38px, compact 34px) and pager + Filter + Clear stay on one row; markup without the new classes is unaffected.

---

### US-007 — Thinking Orbs (Priority: P1)

As an AI/agent UI host, I render dotted thought-orbs via `_thinking_orb.html.twig` + local `nowo-ui-orb.js` (no CDN, no React package).

**Independent Test**: Vitest mounts canvas with mocked 2D context; kitchen sink `#orbs` section shows nine states.

**Acceptance Scenarios**:

1. **Given** `canvas[data-nowo-ui-orb]` and `nowo-ui-orb.js`, **When** DOM loads, **Then** orb auto-mounts with `role="img"` and aria-label.
2. **Given** `data-state` / `data-size` (`20`|`64`) / `data-theme`, **When** mount runs, **Then** animation uses the corresponding preset.
3. **Given** `prefers-reduced-motion: reduce`, **When** mounted, **Then** a static frame is painted (no continuous rAF).
4. **Given** MIT vendor attribution, **When** docs are read, **Then** [THIRD_PARTY.md](../../docs/THIRD_PARTY.md) documents thinking-orbs.

---

### US-008 — FrankenPHP worker without kernel reset (Priority: P1)

As an integrator running FrankenPHP worker mode with the Symfony kernel **not** reset between requests, I can depend on UiKit without request-state leaks.

**Independent Test**: PHPStan worker ruleset clean; [FRANKENPHP-WORKER-AUDIT.md](../../docs/FRANKENPHP-WORKER-AUDIT.md) verdict viable under scenario B; demos boot with `FRANKENPHP_MODE=worker`.

**Acceptance Scenarios**:

1. **Given** the bundle is installed, **When** the PHP sources are reviewed, **Then** services are readonly / stateless and no mutable statics or captured requests exist (the font preload manifest memo is per build, not per request).
2. **Given** Twig globals `nowo_ui_kit_*`, **When** rendered across users/requests on one worker, **Then** values remain compile-time config scalars (not per-user).
3. **Given** templates that need the current request, **When** rendered, **Then** they read `app.request` / `app.flashes` lazily (no captured request in a shared service).

---

### US-009 — Standard page contract for UI bundles (Priority: P1)

As a UI bundle author, I render my pages through the UiKit scaffolds and view models, and as a host I restyle every
bundle through one `nowo_ui_kit.theme`.

**Independent Test**: `PageContractTestCase` subclass in a bundle renders each page fixture; `ScaffoldRenderTest`
renders every scaffold for the bootstrap5, tailwind and custom frameworks.

**Acceptance Scenarios**:

1. **Given** a controller passing `page: new ListPage(...)` to a template extending `@NowoUiKit/page/list.html.twig`,
   **When** rendered, **Then** the page header, filters, table, row actions, pagination and empty state come from the
   kit with the theme's slot classes.
2. **Given** `theme.classes.<slot>` and `theme.bundles.<Bundle>.classes.<slot>`, **When** a bundle page renders,
   **Then** the bundle exception replaces the host classes of that slot for that bundle only.
3. **Given** a bundle page that prints `<html>`, a CDN asset, an inline `on*=` handler or a framework utility class
   outside a slot, **When** `PageContractTestCase` runs, **Then** the contract test fails.

---

### US-010 — POST actions without a raw `<form>` (2.18) (Priority: P1)

As a host with a strict "no raw form markup" rule (REQ-TWIG-005), I pass `Action(method: 'POST', csrfToken: ...)` and
UiKit renders it through `form_start` / `form_end`.

**Independent Test**: `ActionPostFormTest`.

**Acceptance Scenarios**:

1. **Given** a POST `Action` with a `csrfToken` and no `form`, **When** rendered with symfony/form installed, **Then**
   the form is built by `ActionCsrfType` and the request keeps URL, `method="post"`, `_token` (or `csrfField`) and
   `_method`.
2. **Given** symfony/form is missing, **When** the same action renders, **Then** the deprecated raw fallback is printed
   and a deprecation is triggered.

---

### US-011 — Every UiKit form is a Form Type (2.19) (Priority: P1)

As a host, I can enforce `make check-no-raw-html-form` on my templates because no kit partial prints hand-written form
controls any more.

**Independent Test**: `RawFormPartialsTest`; `make check-no-raw-html-form` in `release-check`.

**Acceptance Scenarios**:

1. **Given** the `filters`, `pagination_compact`, `row_actions` (1.x POST hash), `user_menu`, `locale_switcher` and
   `segmented` (plain checkbox) partials, **When** rendered with symfony/form, **Then** each form is a Form Type view
   and the submitted request (URL, method, field names, values, order) is unchanged from 2.18.
2. **Given** the kit views, **When** `make check-no-raw-html-form` runs, **Then** no raw `<form` / `<input` /
   `<textarea` / `<select` is found outside the form themes, the `*_legacy` fallbacks and `twig-005-ignore` controls.

---

### US-012 — Sortable column headers (2.20) (Priority: P1)

As a host listing records, I make columns sortable on the server (paginated lists) or in the browser (lists rendered
whole).

**Independent Test**: `SortRequestTest`, `SortableHeadersTest`, `SortableColumnTest`, `table_sort_controller.test.ts`.

**Acceptance Scenarios**:

1. **Given** `SortRequest::fromRequest($request, ['name' => 'asc', 'createdAt' => 'desc'], 'createdAt')` and
   `?sort=unknown`, **When** the list is built, **Then** the default sort applies (never an error).
2. **Given** `ListPage(sorting: $sort)` with sortable columns, **When** rendered, **Then** each sortable header links to
   the next order, keeps filters and rows per page, drops the page parameter, and the sorted `<th>` carries `aria-sort`.
3. **Given** `Table(clientSort: true)`, **When** the header button is clicked, **Then** the `table-sort` peer sorts the
   rows (text / number / date, empty values last, stable) and dispatches `table-sort:sorted`.

---

### US-013 — Detail page patterns (2.21) (Priority: P2)

As a host rendering a record page, I show a status badge next to the title, icon actions with one primary button and a
"more" menu, a row of key figures and a compact facts grid.

**Independent Test**: `DetailPagePatternsTest`; `DetailPagePatternsBcTest` against the `tests/Fixtures/bc-2.20/`
renders; demo e2e `detail-page.spec.ts` with axe.

**Acceptance Scenarios**:

1. **Given** `PageHeader::withStatus(new PageStatus('Up', 'success'))`, **When** rendered, **Then** a `badge_success`
   badge follows the title inside the title row.
2. **Given** `PageHeader::withSplitActions()` with icon, primary and danger actions, **When** rendered, **Then** icon
   buttons carry `aria-label` + `title`, at most one primary button is filled and the rest is in the square "more"
   menu with destructive actions last; confirm dialogs render outside the popover panel.
3. **Given** a page without any of the new options, **When** rendered, **Then** the markup is byte-identical to 2.20.

---

### US-014 — Font preload (2.21) (Priority: P3)

As a host with self-hosted fonts, I configure `nowo_ui_kit.font_preload` so the document and error layouts preload the
fonts painted above the fold.

**Independent Test**: `FontPreloadTest`.

**Acceptance Scenarios**:

1. **Given** `font_preload.fonts: ['/fonts/inter-400.woff2']`, **When** the document layout renders, **Then** one
   `<link rel="preload" as="font" type="font/woff2" crossorigin>` follows the viewport meta.
2. **Given** `font_preload.manifest` (Vite manifest, `entry`, `prefixes`), **When** rendered, **Then** the matching
   font assets of the entry are preloaded, read once per build.

---

### Edge Cases

- Twig namespace `NowoUiKitBundle` (alias `@NowoUiKit`) with app override path `templates/bundles/NowoUiKitBundle/` (REQ-TWIG-001/002).
- Asset package `nowo_ui_kit` base path `/bundles/nowouikit`.
- TypeScript sources under `Resources/assets/src/` (including `orb/` engine); published IIFEs under `Resources/public/js/` (REQ-ASSETS-001/002). Vite entries: modal, shell, toast, confirm, page-loader, theme, theme-boot, orb, clipboard, tabs, release, open-modal, dialog, popover, segmented, autosubmit, error-retry.
- Co-located `*.test.ts` files are **test artifacts** (FR-TEST-001), not runtime production units.
- `symfony/ux-icons`, `symfony/form`, `symfony/validator`, `symfony/http-client` are optional (`suggest`); services that need them are registered only when installed. Without symfony/form the partials print the deprecated raw fallbacks (removed in 3.0).
- `nowo-ui.css` is the generated `@layer nowo-ui` copy of `nowo-ui.unlayered.css` (use the unlayered file for the 1.x cascade).

---

## Requirements (functional)

### Bundle & DI

- **FR-BUNDLE-001**: `NowoUiKitBundle` registers the extension and compiler pass.
- **FR-CFG-001**: `Configuration` defines `css_framework`, `icon_set`, and `row_actions_display` enums, `panel_path_rewrites`, the optional `release_check` node and (2.x) `route_links`, `font_preload`, `appearance`, `layouts`, `breadcrumbs`, `theme`, `error_pages` and `shell`.
- **FR-CFG-002**: `NowoUiKitExtension` loads services, sets Twig globals, registers asset package; optional services are registered only when their dependency is installed or their feature enabled.
- **FR-TWIG-001**: `TwigPathsPass` ensures namespace `NowoUiKitBundle`.
- **FR-ENUM-001**: `CssFramework` / `IconSet` / `RowActionsDisplay` backed enums used by configuration.
- **FR-ROUTING-001**: `panel_path_rewrites` rewrites route path prefixes at load time (`PanelPathRewriter` + `PanelPathRewritingLoader`, segment boundaries, longest prefix wins, no-op when empty).
- **FR-ROUTING-002**: `LegacyPanelPathRedirectSubscriber` permanently redirects legacy prefixes (301 GET/HEAD, 308 other methods).

### Page contract — layouts, scaffolds, view models (REQ-UI-003)

- **FR-LAYOUT-001**: `layout/document`, `layout/admin`, `layout/guest` define the canonical blocks (`nowo_ui_document_title`, `nowo_ui_head`, `nowo_ui_styles`, `nowo_ui_page_header`, `nowo_ui_flashes`, `nowo_ui_content`, `nowo_ui_modals`, …), load only the kit asset package (never a CDN); `layout/_bridge` gives host shells the same plumbing (2.11).
- **FR-LAYOUT-002**: `layout/status` is a self-contained HTML document (inline critical CSS, no external request, no session) for maintenance / 503 pages.
- **FR-SCAFFOLD-001**: Page scaffolds `page/{list,show,form,settings,wizard,dashboard,auth,confirm}.html.twig` render one view model each; internal helpers live in `page/_*.html.twig`; form functions sit in separate files so scaffolds compile without symfony/form.
- **FR-VIEW-001**: View models are immutable; text is a `Label` (key + domain + parameters) translated by the partials or a plain string printed escaped; `Link` and the internal `ViewList` normaliser support them.
- **FR-ACTION-001**: `View\Action` (href or route + params, variant, icon, display, visibility, confirm) renders through `partials/_action.html.twig`; non-GET actions are POST forms with `_method` spoofing; confirm actions open the kit `<dialog>` (never inline `confirm()`).
- **FR-CONFIRM-001**: `View\Confirm` (type-to-confirm `expectedValue`, message placement) + `_confirm` / `_confirm_dialog` + `page/confirm` (`ConfirmPage`, no-JS fallback) + `ask_confirm.ts` cover destructive confirmations.
- **FR-PAGEHEADER-001**: `View\PageHeader` (title, intro, actions, `Crumb` trail or BreadcrumbKit by `breadcrumbsMode`, `BackLink`, area tabs) renders through the public `page/_page_header.html.twig` contract and `nowo_ui_page_header()`.
- **FR-AREANAV-001**: `AreaNav` / `AreaNavItem` (2.17 `icon`) tab bars come from the bundle or from tagged `AreaNavProviderInterface` services through `AreaNavRegistry` and `nowo_ui_area_nav()`.
- **FR-LIST-001**: `ListPage`, `Table`, `Column`, `FilterField`, `EmptyState` drive the list scaffold and the `table` partial (caption, row actions column, confirm dialogs after the table, responsive card mode by column priority).
- **FR-PAGINATION-001**: `View\Pagination` is the only pagination shape (`fromArray`, `fromPagerfanta`, `fromKnp`, `fromTotal`), rendered by `_pagination` or the compact variant `_pagination_compact` (per-page GET select keeping the other query parameters, back to page 1).
- **FR-SHOW-001**: `ShowPage`, `DetailSection`, `DetailItem` (2.2 `badge` tone) render detail sections as definition lists with escaped values plus audit meta.
- **FR-FORMPAGE-001**: `FormPage`, `SettingsPage` / `SettingsSection` (2.17 `icon`), `WizardPage` / `WizardStep` render forms with the REQ-TWIG-003 child loop, form themes, secondary actions and a danger zone.
- **FR-DASHBOARD-001**: `DashboardPage` + `KpiTile` (variants, link tile without nested links) render the dashboard scaffold.
- **FR-AUTH-001**: `AuthPage` renders the guest auth scaffold (form themes, form attributes, root errors, footer links; `scripts: false` renders a JS-free page).
- **FR-ERROR-001**: Kit error pages (2.6, `error_pages`): `error/status` + `error/layout`, `nowo_ui_error_page()` → `ErrorPage` (illustration `ErrorIllustration` as `<picture>` with WebP, 429 `Retry-After`, "Try again" on 408 / 5xx GET, same-site "Go back"), `nowo_ui_error_text()` with host-domain fallback to the kit catalogue.
- **FR-ERROR-002**: A 5xx on GET counts down (default 10 minutes) and reloads the URL (`nowo-ui-error-retry` IIFE, core module, `error-retry-countdown` peer); no live region.
- **FR-APPSHELL-001**: Signed-in app shell partials `shell/{_shell,_open,_close,_burger}` (2.6): header, burger with next-action labels, brand, aside, main, footer and mobile backdrop, split in two halves so base layouts keep their own blocks.
- **FR-TESTKIT-001**: `Test\PageContractTestCase` (+ `PageFixture`, `RenderedPage`, `StubHost`, `ContractStubExtension`, `FrameworkClasses`) lets each UI bundle assert the page contract: scaffold extension, canonical blocks, no `<html>` / CDN / inline handlers / `confirm(`, no framework utility classes outside slots, missing translation ids.

### Theme & tokens

- **FR-THEME-001**: Every colour, radius, font, focus ring, overlay and shadow painted by `nowo-ui.css`, the `ui.*` macros and the partials reads a documented `--nowo-ui-*` token (base tokens with light + dark defaults; component tokens optional, resolved through `var()` fallbacks); macros emit no Tailwind palette / radius / divide utilities; opt-in `.nowo-ui-bs-tokens` Bootstrap bridge. Detailed in [`../002-theming-tokens/spec.md`](../002-theming-tokens/spec.md) and `docs/THEMING.md`.
- **FR-THEME-002**: `Theme` + `ThemeSlots` + `ThemeExtension` resolve slot classes (semantic class + framework defaults + host classes; page-type, bundle and bundle page-type exceptions), slot attributes, partial overrides (`nowo_ui_partial()`) and tokens (`nowo_ui_tokens()`, `nowo_ui_theme_css()`); request-independent.
- **FR-APPEARANCE-001**: `appearance` (off by default): immutable `Appearance` with light / dark `AppearancePalette`, `CornerStyle`, `BorderStrength`. See [`../004-appearance-spi/spec.md`](../004-appearance-spi/spec.md).
- **FR-APPEARANCE-002**: `AppearanceProviderInterface` is the host storage SPI; `ConfigAppearanceProvider` serves `appearance.defaults`.
- **FR-APPEARANCE-003**: `AppearancePresetRegistry` orders config presets, tagged `AppearancePresetProviderInterface` services, then enabled `BuiltInAppearancePresets`; `AppearancePreset` is mode-tagged.
- **FR-APPEARANCE-004**: `AppearanceCssEmitter` writes sanitised `--nowo-ui-*` tokens (+ `token_aliases`) for configurable root / light / dark selectors.
- **FR-APPEARANCE-005**: Twig `nowo_ui_appearance()`, `nowo_ui_appearance_css()`, `nowo_ui_appearance_style(nonce)`, `nowo_ui_appearance_presets(mode)`, registered only when enabled.
- **FR-APPEARANCE-006**: `AppearanceType` / `AppearancePaletteType` forms, registered only with symfony/form.
- **FR-FONTPRELOAD-001** (2.21): `font_preload` (`fonts` and / or a Vite `manifest` with `entry`, `prefixes`, `base`) + `Theme\FontPreload` + `nowo_ui_font_preload()` print `<link rel="preload" as="font" type crossorigin>` after the viewport meta of the document and error layouts; the manifest is read once per build (worker safe); no CSP nonce needed.

### Forms (REQ-TWIG-005)

- **FR-FORM-001** (2.18): A POST `Action` with a `csrfToken` and no `form` renders a nameless CSRF-only `ActionCsrfType` view (one hidden `csrfField`, default `_token`; form CSRF off) through `form_start` / `form_end` (`_action_form_view`); `nowo_ui_action_form(action)` returns the view; the factory `nowo_ui_kit.action_form_factory` uses core types only. See [`../006-action-csrf-forms/spec.md`](../006-action-csrf-forms/spec.md).
- **FR-FORM-002** (2.19): `HiddenFieldsType`, `SearchFilterType`, `PerPageType` and `UiFormViews` build the forms of `row_actions`, `user_menu`, `locale_switcher`, `filters`, `pagination_compact` and `segmented`; `nowo_ui_form(kind, options)`, `nowo_ui_form_hidden(field)`, theme `form/nowo_ui_kit_fields.html.twig`, dispatcher `_ui_form`; requests unchanged. See [`../007-form-types-everywhere/spec.md`](../007-form-types-everywhere/spec.md).
- **FR-FORM-003**: The `*_legacy` raw fallbacks (`_action_post_legacy` 2.18; `_row_actions_legacy`, `_user_menu_logout_legacy`, `_locale_switcher_legacy`, `_filters_legacy`, `_pagination_per_page_legacy`, `_segmented_legacy_checkbox` 2.19) render only without symfony/form (or with an invalid field name), trigger a deprecation and are removed in 3.0.
- **FR-FORM-004** (2.19): `make check-no-raw-html-form` (`.scripts/check-no-raw-html-form.sh`, part of `release-check` and CI) fails on raw form controls in `src/Resources/views` and `demo/*/templates` outside the documented exceptions.
- **FR-FORM-005**: `form/nowo_ui_layout.html.twig` (2.2) is the kit form theme for `none` / `custom` / `tailwind` hosts (form_div_layout + kit slots).
- **FR-DATERANGE-001** (2.15): `DateRangeType` (two native dates under host-chosen names, `nowo_ui_date_range_*` widget), `DateRangeSubmitExtension` (flat sibling parameters), `DateRangeRequestHandler` (a range-only URL submits the nameless form) and the `date-range` peer (Cally calendar on demand, locale display, clear button).
- **FR-MULTISELECT-001** (2.14): ChoiceType option `nowo_ui_multi_select` (`MultiSelectTypeExtension`) + `multi-select` peer (Tom Select chips, native fallback) + standalone `nowo-ui-multi-select.css`.

### Listing, tables & sorting

- **FR-LISTING-001** (2.13): `ListingDefinitionInterface` services (tag `nowo_ui_kit.listing_definition`) declare `ListingField` columns / filters; `ListingRegistry` finds them by key.
- **FR-LISTING-002**: `ListingLayoutMerger` merges stored preferences leniently and validates submitted ones strictly into a `ListingLayout` of `ListingFieldState` (visible, merged); hidden filters are not applied.
- **FR-LISTING-003**: `ListingPreferenceStoreInterface` (stateless) with the default `CookieListingPreferenceStore` (`nowo_ui_listing_<key>` URI-encoded JSON, `listing_cookie.ts` on the client).
- **FR-LISTING-004**: `ListingCustomizerViewBuilder` → `ListingCustomizerView`; partials `_listing_customizer`, `_listing_head`, `_listing_cells`, `_listing_filter_fields` and the `listing-customizer` peer (show / hide / reorder / stack, keyboard reorder, reset).
- **FR-RESPTABLE-001** (1.17): `ListingTableMode` + `_responsive_table` / `_row_detail_head` / `_row_detail_cell` + `responsive-table` peer (scroll ↔ responsive, row detail dialog). See [`../005-host-chrome-tables-filters/spec.md`](../005-host-chrome-tables-filters/spec.md) FR-001.
- **FR-SORT-001** (2.20): `Listing\SortRequest` validates `sort` / `dir` against a whitelist (unknown key → default sort, unknown direction → first-click direction, never an error) and offers `fieldsOf()`, `nextDirection()`, `query()`, `linkQuery()`, `withPageParam()`, `sortRows()`. See [`../008-sortable-columns/spec.md`](../008-sortable-columns/spec.md).
- **FR-SORT-002** (2.20): `ListPage` / `Table` `sorting` render sortable headers as links to the next order (filters and rows per page kept, page parameter dropped, `aria-sort` on the sorted `<th>`) through the `sort_header` partial; `Column` / `ListingField` carry `sortKey`, `sortFirst` (+ `sortType`, `sortValueKey`).
- **FR-SORT-003** (2.20): `clientSort: true` renders `table-sort` markup and the `table-sort` peer sorts whole tables in the browser (text / number / date, `data-sort-value`, empty last, stable, pinned rows, `table-sort:sorted`); a multi-page `ListPage` with `clientSort` is rejected.

### Twig partials & assets

- **FR-MACRO-001**: `macros/ui.html.twig` exposes btn, toolbar, table, pagination, tabs, shell, card, filters, progress, spinner, badge variants, modal/confirm attrs, etc.
- **FR-PARTIAL-001**: Partials for pagination, empty, flashes, toasts, confirm, page loader, card, filters, brand, theme/width/locale/kebab toggles, modal, shell chrome, thinking orb; `_row_actions` supports `display` `icon`|`text`|`icon_text`.
- **FR-ICON-001**: `_icon.html.twig` branches on `icon_set` with canonical icon kinds in every set (incl. `sort`, `sort_asc`, `sort_desc`, `more`); `_icon_ux.html.twig` isolates optional `ux_icon()` compile dependency.
- **FR-CSS-001**: `css/nowo-ui.unlayered.css` semantic styles + CSS variables (incl. shell transitions, orb, width, dark tokens) — the 1.x cascade source.
- **FR-CSS-002**: `css/nowo-ui.css` ships the same rules inside `@layer nowo-ui` (2.0) and is generated from the unlayered file.
- **FR-CSS-003**: Optional `css/nowo-ui-tom-select.css` (2.2) paints Tom Select from the primary token (load after Tom Select's CSS).
- **FR-JS-001**: Built IIFEs `nowo-ui-modal.js`, `nowo-ui-shell.js`, `nowo-ui-toast.js`, `nowo-ui-confirm.js`, `nowo-ui-page-loader.js`, `nowo-ui-theme.js`, `nowo-ui-orb.js` from Vite TS entries.
- **FR-ORB-001**: Local Thinking Orbs engine under `assets/src/orb/` (MIT adapted from thinking-orbs); `mountThinkingOrb` + auto-mount on `[data-nowo-ui-orb]`.
- **FR-ORB-002**: Page loader supports `visual: spinner|orb` (default `spinner` for BC).
- **FR-LOADER-001**: Page loader minimal visuals `bar` | `bar_loop` | `bar_spinner` | `corner_spinner` | `dots` | `glow` | `corner_mark` (`mark_src`): no veil, no pointer capture, delayed fade-in, children animated with keyframes only; `stimulus: true` attaches the `page-loader` peer with matching timing (`min_visible_ms` / `leave_ms` overrides). The peer ends the leave only on the overlay's own `opacity` `transitionend`.
- **FR-TRANSITION-001**: `_page_transition.html.twig` emits one nonce-aware `<style>` opting into cross-document View Transitions (`style`: `fade_slide` default, `fade`, `slide`, `zoom`, `blur`, `wipe`, `none`), optional `persist` selector → name map; no JS; disabled under `prefers-reduced-motion: reduce`.
- **FR-OPENMODAL-001**: `_open_modal_on_load.html.twig` emits `script[type=application/json]#nowo-ui-open-modal-on-load` (`modal` required, optional `trigger`, `param` default `new` — rendered only when the request query carries it truthy, `param: false` always; JSON hex-escaped; nonce from `nonce` or request `csp_nonce`). `nowo-ui-open-modal-core.ts` (side-effect free: `readOpenModalConfig`, `isModalOpen`, `tryOpenModal`, `clearQueryParam`, `openModalOnLoad`) + IIFE `nowo-ui-open-modal.js` (`window.nowoUiOpenModalOnLoad`).
- **FR-FILTERBAR-001**: Opt-in CSS `.nowo-ui-filter-bar` (explicit control height `--nowo-ui-control-height` 38px / compact 34px via `--compact` or `[data-ui-density=compact]`, `__field`, `__help`, `__actions`, `__actions--pager`, `__pager`, `__submit`, `__clear`) and `table.nowo-ui-data-table` (`--compact`, `--nowo-ui-table-*` tokens); `_filter_actions.html.twig` renders optional pre-rendered `pager` + Filter + Clear (no Symfony Form dependency; 1.15 `submit_icon` / `clear_icon` / `icon_only`). No existing selector changes.
- **FR-THEMEBOOT-001** (1.15): Pre-paint theme boot (`nowo-ui-theme-boot.ts`, shared `nowo-ui-theme-core.ts`, `_theme_boot` inline with CSP nonce or external) sets theme / density / motion / font scale / contrast on `<html>` before paint. See [`../003-host-chrome-extractions/spec.md`](../003-host-chrome-extractions/spec.md) FR-002.
- **FR-SIDEBAR-001** (1.15): Opt-in three-mode sidebar rail (`expanded` / `rail` / `hidden`) with side-effect-free helpers in `nowo-ui-sidebar-core.ts`, rail-aware burger labels. See 003 FR-003.
- **FR-SCRIPTS-001** (2.2): `_scripts` prints one deferred `<script>` per kit runtime from the kit asset package (`nowo_ui_script_names()`); the document layout uses it.
- **FR-DIALOG-001** (2.1): `_dialog` generic native `<dialog>` + `nowo-ui-dialog.ts` (open / close by data attributes, backdrop close, focus return, 2.12 side-panel placement).
- **FR-MENU-001** (2.3 / 2.4): `_menu` (`<details>` menu / popover) and `_kebab`; `popover: true` uses native `[popover]` panels placed and keyboard-driven by `nowo-ui-popover.ts`.
- **FR-SEGMENTED-001** (2.4): `_segmented` two-option control backed by one checkbox (`_segmented_field`, 2.19 `_segmented_checkbox_form`) + `nowo-ui-segmented.ts` (radio group, roving tabindex, bubbling `change`).
- **FR-AUTOSUBMIT-001** (2.6): `[data-nowo-ui-autosubmit]` controls submit their form on change (one delegated listener, `requestSubmit()`, `<noscript>` fallback).
- **FR-PROGRESS-001** (2.2): `_progress` renders a label, a native `<progress>` and the percentage (indeterminate without value), no inline style.
- **FR-TABS-001**: `_tabs` (link tabs, 2.16 icons and host tab style), `nowo-ui-tabs.ts` in-page panels and the `tabs` peer.
- **FR-CLIPBOARD-001**: `nowo-ui-clipboard.ts` (text or same-origin URL body, flash label) and the `clipboard-copy` peer.
- **FR-LOCALE-001** (2.5): `_locale_tabs` per-locale panels behind a WAI-ARIA tab list; `LocaleNames` (`nowo_ui_locale_name()` / `nowo_ui_locale_dir()`, ext-intl or the code).
- **FR-FILTERCOLLAPSE-001** (1.17): `_filters_collapse` + `filters-collapse` peer. See 005 FR-002.
- **FR-CORNERTAB-001** (1.17): `_corner_tab` card corner tab. See 005 FR-003.
- **FR-DISCLOSURE-001** (1.17): `_disclosure_icon` + `disclosure-icon` peer. See 005 FR-004.
- **FR-SCROLLBAR-001** (1.17): thin scrollbars CSS + SCSS mixin `assets/styles/_thin-scrollbars.scss`. See 005 FR-005.
- **FR-EDGETAB-001** (1.17): `_edge_tab` page-edge tab (2.13.2 eight slots, 2.12.1 `attr`) + `nowo_ui_route_link()` from `route_links` (`RouteLinkExtension`). See 005 FR-006.
- **FR-MORPH-001** (1.17): optional `assets/morphicons/index.ts` + `icon_state.ts` bridge (peers never import an icon library). See 005 FR-007.
- **FR-TOUR-001** (2.13): `Tour` / `TourBuilder` / `TourStep` + `_tour` partial (hidden until the peer connects, 2.17.5) + `product-tour` peer (driver.js on demand; steps whose selector matches nothing are skipped; seen state per key).
- **FR-STATUS-001** (2.21): `View\PageStatus` on `PageHeader::$status` renders the `page_status` partial (badge `badge_<tone>`, optional tooltip) inside the `page_title_row`. See [`../009-detail-page-patterns/spec.md`](../009-detail-page-patterns/spec.md).
- **FR-HEADERACTIONS-001** (2.21): `PageHeader::$actionsLayout` `split` + `View\HeaderActions::split()` + `_header_actions` + `nowo_ui_header_actions()`: icon buttons (`Action::asIcon()` / `withDisplay()`), at most one primary button, square "more" menu (`_menu` `square` / `menu_items`) with destructive actions last; confirm dialogs outside the popover.
- **FR-STAT-001** (2.21): `View\Stat` + `ShowPage::$stats` render the `stat_row` partial (a `<dl>` of compact tiles: label, escaped value, hint, tone, optional link).
- **FR-FACTS-001** (2.21): `DetailSection` layout `facts` renders the `detail_list` partial with `modifier: 'facts'` (label over value, several per row).
- **FR-DEMO-001**: `demo/kitchen_sink*.html.twig` show every surface; their controls are FormViews passed by the demo controller (skipped without symfony/form). Illustrative, not public API.
- **FR-I18N-001**: Translation domain files for seven locales (incl. `orb.*`, `layout.*`, theme, loader, toast, filters, `table.sort.*`, `error.*`).
- **FR-I18N-002**: `TranslatableText` and the `nowo_ui_translatable` / `nowo_ui_trans` filters translate a `Label` exactly once, also when a Symfony form theme translates again.
- **FR-FRANKENPHP-001**: Bundle PHP is safe under FrankenPHP worker with kernel not reset between requests (readonly / stateless services, no per-request mutable state); documented in `docs/FRANKENPHP-WORKER-AUDIT.md`.
- **FR-FRANKENPHP-002**: PHPStan includes `phpstan-frankenphp` classic + worker rulesets.

### Release check (optional)

- **FR-RELEASE-001**: Optional `release_check` (off by default): `ReleaseUpdateChecker` compares `current_version` (clean semver) with GitHub `releases/latest` of `github_repo` (`owner/name` only), counts newer non-prerelease releases, caches per repo+version (`cache_ttl`, `cache.app`), soft-fails to `skipped`/`error`; registered only when enabled (requires `symfony/http-client`).
- **FR-RELEASE-002**: `ReleaseStatusController` (`nowo_ui_kit_release_status`, `GET /_nowo-ui/release/status`, opt-in route import) returns the JSON payload with `private, no-store`; answers 404 while disabled.
- **FR-RELEASE-003**: `_release_version.html.twig` renders nothing unless enabled + version set; dialog loads lazily on open via `nowo-ui-release.js` or Stimulus peer `release-status`; only `https://github.com/` links rendered; no inline JS.

### Stimulus peers

- **FR-PEER-001**: Stimulus peers (`stimulus-peers/*.ts`, catalogue in `stimulus-peers/README.md`) are type-checked and covered by Vitest; `confirm-submit` supports form `submit` and single-button `click` hooks plus `blocked`.
- **FR-PEER-002**: Stimulus peer `auto-refresh` (`auto_refresh_controller.ts`): values `interval` (default 10000 ms, floor 2000) and `url`; targets `toggle` (`aria-pressed`) and `status`; replaces id-carrying `[data-auto-refresh-region]` from a same-origin GET (`mode: 'same-origin'`, a `url` on another origin refused, each region through the fragment filter); skips hidden tabs, paused state and overlapping requests; dispatches `auto-refresh:refreshed`. Gated at 100% Vitest coverage.
- **FR-PEER-003** (1.15): `collapse-panel` keeps each panel's open state in one localStorage map; server default without saved state.
- **FR-PEER-004** (1.15): `temporary-reveal` shows a masked secret (URL credentials or whole value) temporarily, then auto-hides and purges copy attributes.
- **FR-PEER-005** (1.15): `datatable` enhances server-rendered tables with DataTables Responsive (optional peer dependencies; host loads the stylesheets).

### Tests & build (non-packaged runtime)

- **FR-TEST-001**: Vitest suites co-located as `*.test.ts` (42 files) cover the IIFE sources, orb, morphicons and peers; PHPUnit under `tests/` covers PHP, partials, scaffolds and BC fixtures.
- **FR-BUILD-001**: `vite.config.ts` / `pnpm run build` emit seventeen IIFE outputs into `public/js/` (modal, shell, toast, confirm, page-loader, theme, theme-boot, orb, clipboard, tabs, release, open-modal, dialog, popover, segmented, autosubmit, error-retry).
- **FR-BUILD-002**: `pnpm run build:css` (`.scripts/build-layered-css.mjs`) generates `nowo-ui.css` from `nowo-ui.unlayered.css`.
- **FR-BUILD-003**: `pnpm run build:theme-boot` copies the theme-boot IIFE to `views/inline/nowo-ui-theme-boot.js` for inline printing.

---

## Key Entities

- **Theme** (`Theme\Theme`, `ThemeSlots`): host slot classes, attributes, partial overrides, tokens.
- **View models** (`View\*`): `ListPage`, `ShowPage`, `FormPage`, `SettingsPage`, `WizardPage`, `DashboardPage`, `AuthPage`, `ConfirmPage`, `PageHeader`, `Action`, `Table`, `Column`, `Pagination`, `PageStatus`, `HeaderActions`, `Stat`, …
- **Listing** (`Listing\*`): definitions, layout, preference store, `SortRequest`.
- **Appearance** (`Appearance\*`): site look and feel, presets, CSS emitter.
- **Tour** (`Tour\*`), **AreaNav** (`Navigation\*`, `View\AreaNav`), **ErrorPage** (`View\ErrorPage`).

---

## Success criteria

- **SC-001**: Every production file under `src/` (excluding the 42 co-located `*.test.ts`) appears exactly once in [`code-inventory.md`](code-inventory.md) with semantic requirement IDs: **306/306 mapped** (101 PHP, 60 TS, 112 Twig, 9 YAML, 4 CSS, 1 SCSS, 18 built JS, 1 Markdown), audited 2026-11-01 on 2.21.0.
- **SC-002**: Every FR id used in the inventory is defined in this spec (domain-grouped list above).
- **SC-003**: PHPUnit + Vitest + `coverage-check` (100% PHP lines) pass in CI; `make release-check` includes `check-no-raw-html-form`.
- **SC-004**: FrankenPHP demos boot on ports 8092 / 8093 with HTTP 200; the order page passes the Playwright e2e with axe.
- **SC-005**: Kitchen sink demonstrates feedback surfaces, shell width toggle, Thinking Orbs states, tables (server and client sort) and forms.
- **SC-006**: FrankenPHP worker audit documents **100%** compatibility with kernel not reset (`FR-FRANKENPHP-001`).
- **SC-007**: Pages using none of the 2.21 options render byte-identical to 2.20 (`DetailPagePatternsBcTest`, `tests/Fixtures/bc-2.20/`).

---

## Assumptions

- Hosts load their own CSS framework (Bootstrap, Tailwind, Foundation) when they pick one; the kit only emits classes.
- Persistence (appearance, listing preferences server-side, tour seen state beyond the browser) belongs to the host.
- Optional packages (`symfony/form`, `symfony/validator`, `symfony/http-client`, `symfony/ux-icons`, BreadcrumbKit, Tom Select, Cally, driver.js, DataTables, morphicons) are installed by hosts that use the matching feature.

## Explicit non-goals

- Admin CRUD routes, Doctrine entities, controllers for host data (REQ-UI-002 N/A).
- Replacing host CSS frameworks; bundling third-party stylesheets (DataTables, Tom Select).
- Client-side paging of server lists (sorting a paginated list is always server-side).
- `demo/` apps and the kitchen sink are illustrative, not stable API.

## Validation

```bash
make test            # PHPUnit
make test-ts         # Vitest
make phpstan
make check-no-raw-html-form
make release-check   # full gate (clean tree, CS, Rector, PHPStan, coverage, Vitest, demos)
git ls-files src | grep -v '\.test\.ts$' | wc -l   # 306 = inventory total
```
