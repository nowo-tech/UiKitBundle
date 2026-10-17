# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## Table of contents

- [[Unreleased]](#unreleased)
- [[2.0.1] - 2026-10-18](#201---2026-10-18)
- [[2.0.0] - 2026-10-17](#200---2026-10-17)
- [[1.17.5] - 2026-10-17](#1175---2026-10-17)
- [[1.17.4] - 2026-10-16](#1174---2026-10-16)
- [[1.17.3] - 2026-10-16](#1173---2026-10-16)
- [[1.17.2] - 2026-10-16](#1172---2026-10-16)
- [[1.17.1] - 2026-10-16](#1171---2026-10-16)
- [[1.17.0] - 2026-10-16](#1170---2026-10-16)
- [[1.16.0] - 2026-10-13](#1160---2026-10-13)
- [[1.15.0] - 2026-10-13](#1150---2026-10-13)
- [[1.14.0] - 2026-10-13](#1140---2026-10-13)
- [[1.13.2] - 2026-10-13](#1132---2026-10-13)
- [[1.13.1] - 2026-10-13](#1131---2026-10-13)
- [[1.13.0] - 2026-10-13](#1130---2026-10-13)
- [[1.12.0] - 2026-10-12](#1120---2026-10-12)
- [[1.11.0] - 2026-10-09](#1110---2026-10-09)
- [[1.10.1] - 2026-10-09](#1101---2026-10-09)
- [[1.10.0] - 2026-10-09](#1100---2026-10-09)
- [[1.9.2] - 2026-10-09](#192---2026-10-09)
- [[1.9.1] - 2026-10-07](#191---2026-10-07)
- [[1.9.0] - 2026-10-07](#190---2026-10-07)
- [[1.8.5] - 2026-09-27](#185---2026-09-27)
- [[1.8.4] - 2026-09-25](#184---2026-09-25)
- [[1.8.3] - 2026-08-24](#183---2026-08-24)
- [[1.8.2] - 2026-08-19](#182---2026-08-19)
- [[1.8.1] - 2026-08-18](#181---2026-08-18)
- [[1.8.0] - 2026-08-15](#180---2026-08-15)
- [[1.7.0] - 2026-08-05](#170---2026-08-05)
- [[1.6.0] - 2026-08-04](#160---2026-08-04)
- [[1.5.1] - 2026-08-04](#151---2026-08-04)
- [[1.5.0] - 2026-08-04](#150---2026-08-04)
- [[1.4.0] - 2026-08-04](#140---2026-08-04)
- [[1.3.0] - 2026-08-03](#130---2026-08-03)
- [[1.2.1] - 2026-08-03](#121---2026-08-03)
- [[1.2.0] - 2026-08-03](#120---2026-08-03)
- [[1.1.0] - 2026-08-03](#110---2026-08-03)
- [[1.0.0] - 2026-08-03](#100---2026-08-03)

## [Unreleased]

### Fixed

- **`PageContractTestCase` no longer flags the `modal_shell` structure**: `modal`, `fade`, `modal-dialog`,
  `modal-content`, `modal-header`, `modal-title`, `btn-close`, `modal-body`, `modal-footer` are a documented structural
  exception (`FrameworkClasses::MODAL_SHELL`, like the pagination list), allowed for every `css_framework`; the partial
  keeps its 1.x output, so bundles drop them from `allowedClasses()`. Page-contract bundles should prefer the new
  framework-free `dialog` partial.
- **Header actions backed by a `FormView` keep their CSRF token** when a host `page_header` partial override renders
  `actions` itself (REQ-UI-003): the scaffolds pre-render the 1.x `toolbar`, which marked the form's fields rendered,
  so the override's `form_end` printed no `_token`. The `FormView` branch of the action partial now prints the hidden
  leaf fields of the form on every render (other children still render once through `form_end`); this also covers one
  CSRF `FormView` shared by every row.

### Added

- **No-JS fallback for confirm actions**: `Action::$fallbackHref` (last constructor argument, kept by
  `withConfirmScope()`, `fallback_href` in `toArray()`) prints a `<noscript>` link (class `nowo-ui-action__fallback`)
  to e.g. a `page/confirm` page next to the dialog toggle, in toolbars and row actions.
- **`theme.bundles.<Bundle>.pages.<type>`** (`classes`, `attributes`, `framework_defaults`): per-bundle variation by
  page type. Precedence: bundle page slot > bundle slot > bundle page > bundle > page-type slot > page type > host
  slot > host; attributes merge host → page type → bundle → bundle page type. Validated by the configuration and by
  `Theme`.
- **Per-page form themes**: `FormPage::$formThemes` and `SettingsSection::$formThemes` (lists of template names) are
  applied with `form_theme` inside the scaffold before the form renders (settings: before the
  `nowo_ui_settings_section` block), so bundles keep their form theme without overriding `nowo_ui_content`.
- **Area navigation** (`PageHeader::$tabs`, `View\AreaNav` / `View\AreaNavItem`, `PageHeader::withTabs()`): a tab bar
  between the sections of a bundle area, rendered by the `page_header` partial through the `tabs` partial (region
  `area-nav`, row `nowo-ui-page-header__nav`). **`Navigation\AreaNavProviderInterface`** (autoconfigured tag
  `nowo_ui_kit.area_nav_provider`), **`Navigation\AreaNavRegistry`** (service `nowo_ui_kit.area_nav_registry`) and the
  Twig function `nowo_ui_area_nav(area, context)` let other bundles (DashboardMenu) consume an area's navigation.
- **`_tabs` root `attr`**: attributes of the `<nav>` (e.g. `{'data-turbo': 'false'}` kept from a bundle's 1.x tab
  bar), merged over the `tabs` slot attributes (call site wins), also without the ThemeExtension; 1.x output unchanged.
- **Theme slots `code` and `code_block`** (REQ-UI-003): inline `<code>` (identifiers, keys) and a `<pre>` block for
  payloads / JSON / logs. Semantic classes `nowo-ui-code` / `nowo-ui-code-block` (monospace, muted surface; the block
  has a border, scrolls both ways above `--nowo-ui-code-block-max-height`, `nowo-ui-code-block--wrap` wraps lines);
  framework defaults Bootstrap `border p-3` (block), Tailwind `font-mono text-sm` (+ `p-3 border` on the block). New
  optional tokens `--nowo-ui-font-mono`, `--nowo-ui-code-bg`, `--nowo-ui-code-text`, `--nowo-ui-code-border`,
  `--nowo-ui-code-radius`, `--nowo-ui-code-block-max-height` (docs/THEME_SLOTS.md, docs/THEMING.md). Demo: webhook
  payload on the page contract show page.
- **`PageContractTestCase`**: the stub host's `page_header` override renders the header `actions` itself (after the
  scaffold's pre-rendered `toolbar`), and a seventh test per fixture, `testPageActionFormsKeepTheirHiddenFields`
  (helper `assertActionFormsKeepHiddenFields()`, `RenderedPage::$actionForms` / `hiddenFieldValues()`), fails when a
  rendered form of a `FormView`-backed `Action` of the fixture lacks (or duplicates) a hidden field of that form.
- Demo: the page contract show page has a `FormView`-backed header action (`Reopen`); the Playwright e2e checks its
  CSRF token through the host override and the default partial.

## [2.0.1] - 2026-10-18

Port of the 1.17.5 fix to 2.x.

### Fixed

- Responsive tables never hid a column on narrow screens (also in 2.0.0): under 30rem the cells and their descendants had `overflow-wrap: anywhere`, so the table could always shrink by breaking words at any character; the `responsive-table` peer's fit (`scrollWidth <= clientWidth`) never saw an overflow, every column stayed and words split letter by letter. The peer now measures under `data-rt-measuring` (CSS: `overflow-wrap: normal !important; word-break: normal !important` on cells and descendants, `white-space: normal` kept), decides the hidden columns, then removes the state; `overflow-wrap: anywhere` only applies after the fit as a last resort, under `[data-rt-overflow]:not([data-rt-measuring])` (set when the required columns still overflow), keeping 320px free of horizontal scroll. Source edited in `nowo-ui.unlayered.css`, `nowo-ui.css` regenerated (`pnpm run build:css`).
- Regression tests ported: Vitest with mocked layout widths (fails on 2.0.0), PHPUnit CSS assertions (measuring rule, `anywhere` only under `data-rt-overflow`, row detail column rule).
- Demo: `demo/symfony8-tailwind/config/reference.php` regenerated for the 2.0 configuration (layouts, breadcrumbs, theme).

[2.0.1]: https://github.com/nowo-tech/UiKitBundle/compare/v2.0.0...v2.0.1

## [2.0.0] - 2026-10-17

**Standard page contract (REQ-UI-003).** Canonical layouts, page scaffolds (list, show, form, settings, dashboard, confirm, wizard, auth), view models, one global theme (classes, attributes, partials, tokens, per-page-type and per-bundle exceptions) and `PageContractTestCase` for UI bundles — validated in a real host (symfony-beacon). 1.x partials and macros keep their inputs and output; `nowo-ui.css` now ships inside `@layer nowo-ui` (use `nowo-ui.unlayered.css` for the 1.x cascade). See [UPGRADING](UPGRADING.md#to-200) and [PAGE_CONTRACT.md](PAGE_CONTRACT.md).

### Added

- **Standard page contract (REQ-UI-003, phase 0 core)** — opt-in; nothing changes for hosts that do not use it. Summary of the API in [`PAGE_CONTRACT.md`](PAGE_CONTRACT.md).
  - Configuration: `nowo_ui_kit.layouts.{admin,guest}` (defaults `@NowoUiKit/layout/admin.html.twig` / `guest`), `nowo_ui_kit.breadcrumbs.{breadcrumb_kit,collection}` and the global theme `nowo_ui_kit.theme.{classes,attributes,partials,tokens,tokens_dark,bundles}` (unknown slot / partial, `on*` attributes, unsafe token values and bad bundle keys are rejected with explicit messages).
  - Theme: `Theme\ThemeSlots` (slot vocabulary + framework defaults per `css_framework`, identical to the `ui.*` macros) and `Theme\Theme` (semantic + framework + host classes, per-bundle exceptions, attributes, partial overrides, token CSS).
  - Twig: `nowo_ui_class()`, `nowo_ui_attrs()`, `nowo_ui_partial()`, `nowo_ui_theme_tokens()` (CSP nonce from the `csp_nonce` request attribute), `nowo_ui_theme_css()`, `nowo_ui_breadcrumb_kit()` / `nowo_ui_breadcrumb_kit_available()` (BreadcrumbKit bridge), filter `|nowo_ui_trans`; globals `nowo_ui_layout`, `nowo_ui_theme`, `nowo_ui_breadcrumbs`.
  - View models (`Nowo\UiKitBundle\View\`): `Label`, `PageHeader`, `Action`, `Confirm`, `Crumb`, `Link`, `Pagination` (`fromArray` / `fromPagerfanta` / `fromKnp`, duck typed), `Column`, `EmptyState`, `DetailItem`, `DetailSection`, `KpiTile`, `SettingsSection`, `WizardStep`, `ListPage`, `ShowPage`, `FormPage`, `SettingsPage`, `DashboardPage`, `ConfirmPage`, `WizardPage`, `AuthPage`.
  - Layouts `layout/{document,admin,guest}.html.twig` (canonical `nowo_ui_*` blocks, UiKit asset package only) and scaffolds `page/{list,show,form,settings,dashboard,confirm,wizard,auth}.html.twig` with the REQ-UI-003 data blocks.
  - New partials `_action` (one view-model action: link, POST form, confirm `<dialog>`, modal), `_action_post`, `_breadcrumbs`.
  - Upgraded partials accept view models and theme slot hooks: `_pagination` (`Pagination` object, page / per-page parameter names), `_page_header` (`header`), `_row_actions` (`Action` objects), `_confirm` (`confirm` + `action`), `_empty` (`empty`), `_toasts` / `_flashes` (`trans_domain`, slot attributes), `_shell` / `_burger` (`root_class`, `label_attr_names`, `attr`, theme partial for the burger). 1.x inputs render as in 1.17.4 (fixture-based BC tests).
  - CSS: the base palette drops to zero specificity when `<html data-nowo-ui-host-tokens>` is set (UiKit layout + `theme.tokens`), so host tokens win without `!important`; semantic styles for breadcrumbs, detail lists, KPI tiles, cards grid, wizard steps, auth panel, danger zone.
  - Translations `action.{confirm,back,next,submit,import}`, `confirm.delete_{title,message}`, `breadcrumbs.nav`, `table.actions`, `wizard.{steps,step_of}`, `form.danger_zone` (7 locales).
  - Contract test for UI bundles: `Nowo\UiKitBundle\Test\PageContractTestCase` (dev dependency — bundles add `phpunit/phpunit`, and `symfony/form` for form pages, to `require-dev`; listed in `suggest`). Bundles return `PageFixture`s (template + context); each page is rendered with a real Twig environment against a stub host layout and a stub theme (`StubHost`) and checked for: scaffold or unique page declared in `docs/TEMPLATES.md`, canonical blocks rendered and filled, no `<html>` / CDN assets / inline `on*=` / `confirm(` / `javascript:`, no framework utility classes outside theme slots (`FrameworkClasses` allow-lists per `css_framework` derived from `ThemeSlots`), stub theme classes / attributes / `page_header` partial reflected, no raw or missing translation keys. Hooks for template paths, catalogues, form themes, Twig extensions and allowed classes; assertion helpers reusable. Self-tested on UiKit's scaffolds for every framework, with one negative fixture per assertion.
  - Host-theme demo (`demo/symfony8`, `/page-contract`): an "Acme Shop" back office using every scaffold with a non-default host theme (host layout bridges, tokens, class map, table attribute, `page_header` partial override) and a default-theme view (`/page-contract/default/…`); the demo templates run the contract test; Playwright e2e (`make test-e2e`) and WebP screenshots of every scaffold (`make demo-screenshots`, `docs/images/demo/page-contract-*.webp`).
  - Theme `framework_defaults`: hosts can drop UiKit's framework defaults (e.g. Bootstrap `badge text-bg-success`, `btn btn-primary`, `card`) globally (`theme.framework_defaults: false`), per bundle (`theme.bundles.<B>.framework_defaults`) or per slot (`theme.classes.<slot>: {value: '…', framework_defaults: false}`, same in `theme.bundles.<B>.classes`); most specific wins, the semantic `nowo-ui-*` class always stays. `nowo_ui_class()` and the 1.x partials honour it (`Theme::macroClasses()`, `Theme::frameworkDefaults()`); the plain string / list form is unchanged.
  - `_card` reads the theme slots `card`, `card_header`, `card_body`, `card_footer` (new slot) and `cards`, and accepts `Label` titles / intros, `actions` (`View\Action` list), `id`, `attr`, `cards` (grid of cards through the `card` partial) and `bundle`; 1.x inputs render as in 1.17.4 (added to the legacy fixtures).
  - Docs: [`PAGE_CONTRACT.md`](PAGE_CONTRACT.md) (full guide), [`THEME_SLOTS.md`](THEME_SLOTS.md) (slot vocabulary with defaults per framework, partials, tokens, per-bundle exception semantics), [`MIGRATING_BUNDLES.md`](MIGRATING_BUNDLES.md) (bundle recipe).

- **Per-page-type theme variation** `theme.pages.<list|show|form|settings|dashboard|confirm|wizard|auth>.{classes,attributes,framework_defaults}` (validated; precedence bundle slot > bundle > page-type slot > page type > host slot > host). Scaffolds set `nowo_ui_page_type` and pass it to their partials; `nowo_ui_class()` / `nowo_ui_attrs()` gain an optional `page` argument (default: the context's `nowo_ui_page_type`); new `nowo_ui_html_attrs(map)` and `Theme::renderAttributeMap()`; `ThemeSlots::PAGE_TYPES`.

- **Confirm dialogs:** `_confirm` cancel / confirm buttons read the `btn_secondary` / `btn_<variant>` slots (and `framework_defaults`) instead of the bare `ui.btn()` macro (1.x output unchanged without host classes). Type-to-confirm `View\Confirm::expectedValue` (+ `expectedLabel`, `expectedPattern()`): input with `required` + `pattern` joined to the POST form, confirm button disabled until the value matches (`nowo-ui-confirm.js`: `syncExpected()`, `resetExpected()`, `syncAllExpected()`; CSP-safe), also on the full-page `ConfirmPage`. Translation `confirm.type_to_confirm` (7 locales); `_action_post` / `_action_form_view` accept `form_id`.
- **Row-action variant slots** `action_primary`, `action_secondary`, `action_danger`: host classes per resolved variant in row context (objects and 1.x hashes), e.g. a red delete on Tailwind.

- **Template contract — partial resolution, 1.x shapes, regions:** every scaffold / partial include resolves through the theme (`_page_header` → `breadcrumbs` / `action`; `_empty`, `_card`, `_row_actions` → `action`; settings tabs through the `tabs` partial, which now reads the `tabs` / `tab` / `tab_active` slots and `Label` labels); new partial names `list_filters`, `detail_list`, `section_form`, `wizard_form`. Scaffolds pass the 1.x shapes next to the view models (`page_header`: `title` / `intro` / pre-rendered `toolbar`; `pagination`: `Pagination::toArray()` + `pagination_model`; `row_actions`: 1.x hashes + `view`, row confirm dialogs rendered once after the table); `Pagination` and `Action` implement read-only `ArrayAccess` with the 1.x keys. `data-nowo-ui-region` on the standard regions and `attributes` on every page model (scaffold root). Legacy BC fixture for `_tabs`.

- **List filters:** data block `nowo_ui_list_filters`; `View\FilterField` (placeholder, width, widget / row classes and attributes) via `ListPage::filterFields`; `filtersSubmit` (label / icon / variant of the apply button, icon through the icon partial), `filtersClear` and `filterActions` as `Action`s; slot `filters_field`.
- **New slots** `table_head_row`, `table_head_cell`, `table_actions_head` (+ `ListPage::actionsLabel` / `actionsLabelVisible`), `kpi_label`, `kpi_value`, `kpi_hint` (+ `KpiTile::linkTile`), `form_panel` (form scaffold wrapper), `danger_zone_title`, `danger_zone_intro` (+ `FormPage::dangerZoneTitle` / `dangerZoneIntro`).
- **Responsive list tables (CSS only):** `ListPage::responsive` (`cards` default, `scroll`) — the table wrap is a size container, `Column::priority` 2 / 3 hide below 48rem / 60rem, below 40rem rows become labelled cards (`td[data-label]`).
- **Form secondary actions in the page header:** `FormPage::secondaryActionsInHeader` (default `true`; `false` keeps them below the form).

- **Action display modes everywhere** (validation round 3): `Action::display` (`icon` | `text` | `icon_text`, `Action::DISPLAYS`) is honoured in toolbar / header / card / filter context too (icon-only: visually-hidden label + `aria-label` / `title`, kind icon fallback), the `action` partial takes a `display` context option, `ListPage::filtersDisplay` sets it for the apply / clear / extra filter buttons, and the new slot `btn_icon` (`nowo-ui-btn--icon`) marks icon-only toolbar buttons.
- **List primary actions in the filter row:** `ListPage::headerActionsInFilters` (null = the new theme option `theme.pages.list.header_actions_in_filters`, default `false`) renders the page-header actions at the end of the filter row (`nowo-ui-filters__header-actions`, region `header-actions`) instead of the page header; `Theme::listHeaderActionsInFilters()`, `PageHeader::withActions()`, `list_filters` partial input `header_actions`, scaffold macro `page_header(…, header_actions)` (the page header then receives a `header` without actions).
- **Internal slots** (validation round 3): `card_title`, `card_intro` (`_card`, show / settings section cards); `detail_item`, `detail_term`, `detail_value` (+ `nowo-ui-detail-list--stacked` modifier, label over value); `confirm_panel`, `confirm_header`, `confirm_title`, `confirm_body`, `confirm_message`, `confirm_expect_label`, `confirm_footer` (dialog and full-page confirm); `auth_footer`, `auth_links`; `kpi_link` (whole-tile KPI link only).
- **Actions column, neutral variant, card mode** (validation round 3): `ListPage::actionsAlign` (`start` | `center` | `end`, default `end`) + slot `table_actions_cell` replace the hard-coded `nowo-ui-cell--end` (the action cluster follows the alignment); variant `neutral` (`Action::resolvedVariant()` default for kinds other than delete / create / save) + slot `action_neutral`, so an explicit `secondary` is distinguishable; card-mode CSS keeps inline cell content (badges) at its own width and `nowo-ui-table-wrap--flush` (via the `table_wrap` slot) drops the per-card border inside a bordered panel.
- **Demo (round 3):** the host-theme list puts its primary actions in the filter row with an icon-only export and clear button, show pages use stacked detail lists, the KPI link tile and the confirm title use the new slots; e2e covers per-row confirm dialogs, filters kept by pagination links, the actions column alignment and card-mode badges; list / show / dashboard / confirm / default-list screenshots regenerated.
- **Token precedence** `nowo_ui_tokens(nonce)`: theme tokens (host defaults) then the appearance SPI style (user choice); the document layout prints it after `nowo-ui.css`.
- **Confirm and show hooks** (final Beacon validation): slot `confirm_expect` on the type-to-confirm wrapper (`nowo-ui-confirm__expect`, dialog and page); slot `detail_list_meta` on the show scaffold's meta list (`nowo-ui-detail-list--meta`, added to `detail_list`); a 1.x `body` passed to `_confirm` is wrapped in a `<div>` with the `confirm_message` slot when the theme sets classes / attributes for it (1.x markup unchanged with an empty theme); `Confirm::messageInHeader` (null = the new `theme.confirm_message_in_header`, default `false`; `Theme::confirmMessageInHeader()`) renders the message under the title inside the header (dialog and page); `confirm_message` / `confirm_expect` slot attributes are now rendered.
- **Minor slots for kit class maps** (final Beacon validation): `btn_link` (toolbar `Action` variant `link`, defaults = `ui.btn('link')`), `muted` (defaults = `ui.muted()`; on the `—` placeholder of empty detail-list values), `modal_panel` / `modal_header` / `modal_body` / `modal_footer` (+ the `modal` slot, host classes and attributes in `_modal_shell`, which also accepts `bundle`; 1.x markup unchanged with an empty theme, legacy fixture added), `breadcrumb_link` / `breadcrumb_separator` (plain trail; the separator is now an `aria-hidden` element, the CSS `::before` stays for overrides without it), `table_cell_muted` (default list cells without a value print a muted `—`), `row_action_disabled` (disabled row actions; objects and 1.x hashes), `sortable_header` / `sort_link` (list sortable headers).
- **Demo (final validation):** the host theme hides closed dialogs through the `confirm` slot (`.acme-dialog:not([open]) { display: none }`) while the full-page confirm uses `confirm_page`, styles the show meta list (`detail_list_meta`) and puts a Tailwind child variant in `sort_link`; e2e covers the confirm page staying visible, the dialogs opening, the escaped arbitrary variant and the header actions right after the filter actions. Screenshots refreshed (list, show, settings, confirm).

### Changed

- **Template contract — theme dark tokens** are emitted on `[data-theme="dark"]` (was `:root[data-theme="dark"]`): same specificity as the appearance SPI, so the appearance printed after them wins.
- **Template contract — CSS cascade layer:** `nowo-ui.css` ships inside `@layer nowo-ui` with the order statement `@layer theme, base, nowo-ui, components, utilities;` so Tailwind v4 utilities placed in theme slots beat the kit while the kit stays above Tailwind's preflight (dialogs). `nowo-ui.unlayered.css` keeps the 1.x unlayered stylesheet for hosts that relied on the kit beating unlayered framework CSS. `nowo-ui.css` is generated from it (`pnpm run build:css`, checked by `LayeredCssTest`).
- **Template contract — theme class lists accept any printable character** (final Beacon validation): `"`, `'`, `<`, `>` (and `&`) are no longer rejected in `theme.classes`, `theme.pages.<type>.classes` and `theme.bundles.<B>.classes`, so Tailwind arbitrary and child variants (`[&>li]:mt-1`, `content-['·']`, `data-[state=open]:…`) can live in the theme. Class lists are always printed HTML-escaped inside their quoted `class` attribute (`ClassEscapingTest` renders every scaffold and partial with a break-out payload in every slot, on the host, per page type and per bundle: no attribute / element injection, no double escaping). Control characters (other than whitespace) and invalid UTF-8 are still refused (`class lists must be valid UTF-8 without control characters.`).
- **Template contract — full-page confirm root slot** (final Beacon validation): `page/confirm.html.twig` printed the dialog root slot `confirm` (classes + attributes, and UiKit's own `.nowo-ui-confirm` `<dialog>` box rules) on its `<section>`, so host dialog CSS such as `.confirm-dialog:not([open]) { display: none }` hid the page. The page now prints the new slot **`confirm_page`** (`nowo-ui-confirm-page`) next to `card`; `confirm` stays on the `<dialog>` only.
- **Template contract — list header actions in the filter row** sit right after the filter actions (same `filters_actions` group): the group no longer grows (`flex: 1 1 auto` dropped) and `nowo-ui-filters__header-actions` no longer pushes itself to the row end (`margin-inline-start: auto` dropped). PAGE_CONTRACT documents the filter row layout and `flex-none` on `filters_actions` for themes whose fields grow.

### Fixed

- **Confirm dialog ids of row actions** (REQ-UI-003 validation round 3): `Action::confirmId()` hashed only kind / href / route / params, so two row actions with the same target but a different `Confirm` (or the same static action on every row) printed duplicate dialog ids and every toggle opened the first dialog. The derived id now covers the whole `Confirm` content, the CSRF token and a scope (`Action::$confirmScope`, `withConfirmScope()`, `confirmKey()`); `ListPage::rowActions()` scopes actions by row key and the list scaffold renders one dialog per id (`ListPage::confirmDialogs()`; an explicit `Confirm::$id` shared by different content throws).
- **List pagination keeps filters in 1.x overrides** (validation round 3): the list scaffold passed only the `pagination` array, so an untouched 1.x `_pagination` override (which reads the top-level `route`, `route_params`, `query`) lost the filters and sort. It now also passes `route`, `route_params` and `query` (current request query without the page parameters, merged with `Pagination::query`) plus `page_param` / `per_page_param`; the default partial accepts `page_param` / `per_page_param` as variables and, through the scaffold, now keeps the request's filters across pages too.
- Show scaffold: the meta detail list (`nowo-ui-detail-list--meta`) gets a bottom margin too, so content of `nowo_ui_show_after` no longer touches it.
- Badge contrast on Bootstrap 5 / Tabler: the `ui.badge()` macro and the `badge*` slot defaults used `bg-success` / `bg-danger` (background with `!important`) without a text utility, so the semantic `nowo-ui-badge--*` text colour (dark green / red) landed on the solid Bootstrap fill. They now use `text-bg-{success,warning,danger,info,light}` (Bootstrap 5.2+; text and background set together); on 5.0 / 5.1 the semantic palette paints the badge. `nowo-ui.css` also clears the tinted border on solid badges and gives 1.x `bg-*` badges without a text utility Bootstrap's badge text colour.

[2.0.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.17.4...v2.0.0

## [1.17.5] - 2026-10-17

Released from the `1.x` branch (`main` is 2.x).

### Fixed

- Responsive tables never hid a column on narrow screens (1.17.2–1.17.4): under 30rem the cells and their descendants had `overflow-wrap: anywhere`, so the table could always shrink by breaking words at any character; the peer's fit (`scrollWidth <= clientWidth`) never saw an overflow, every column stayed and words split letter by letter (e.g. at 390px). Root-cause fix:
  - the `responsive-table` peer measures under a `data-rt-measuring` state (CSS: `overflow-wrap: normal !important; word-break: normal !important` on cells and descendants, `white-space: normal` kept), so a cell's min-content is its longest word; it decides the hidden columns, then removes the state;
  - `overflow-wrap: anywhere` is no longer tied to the viewport width: it only applies AFTER the fit, as a last resort, when the required columns still overflow — the peer sets `data-rt-overflow` on the root and the visible cells may then break words (long URLs / ids), keeping 320px free of horizontal scroll with the required columns and the row detail button.
- Regression tests: Vitest with mocked layout widths where the table "fits" by breaking words outside the measuring state (fails on 1.17.4); CSS assertions that `anywhere` only exists under `[data-rt-overflow]:not([data-rt-measuring])` and that the row detail column shows under `[data-rt-has-hidden]`.

[1.17.5]: https://github.com/nowo-tech/UiKitBundle/compare/v1.17.4...v1.17.5

## [1.17.4] - 2026-10-16

Supersedes 1.17.3: same runtime code and CSS, release gate hardened so the tagged commit is exactly what was checked.

### Changed

- `make release-check` is check-only: it starts and ends with the new `check-clean-tree` target (fails on any `git status --porcelain` output), runs `cs-check` instead of `cs-fix` + `cs-check`, and `validate-phpdoc` no longer writes files.
- `.scripts/complete-public-phpdoc.php` (`make validate-phpdoc`) is a read-only gate: it fails when a documented public method returning `array` / `iterable` lacks `@return`, and no longer adds redundant `@return` tags that php-cs-fixer removes (the two tools used to fight and leave the tree dirty).
- Tests: php-cs-fixer quote style in `HostChromePartialsTest` (no behaviour change).

[1.17.4]: https://github.com/nowo-tech/UiKitBundle/compare/v1.17.3...v1.17.4

## [1.17.3] - 2026-10-16

### Fixed

- Responsive table mode (regression in 1.17.2): `overflow-wrap: anywhere` on cells and descendants at every width broke words mid-word ("Symf ony") instead of hiding columns — mid-word breaks lower the min-content width, so the table always "fit" and the row detail button never appeared (e.g. at 600px). Above 30rem cells now use `overflow-wrap: break-word` (no effect on min-content: the two-phase column fit hides columns before any word splits); `overflow-wrap: anywhere` (cells + descendants) only applies under 30rem, as in 1.17.1. `white-space: normal !important` on descendants (the 1.17.2 nowrap fix) is kept.

[1.17.3]: https://github.com/nowo-tech/UiKitBundle/compare/v1.17.2...v1.17.3

## [1.17.2] - 2026-10-16

### Fixed

- `.nowo-ui-confirm` dialogs (confirm, row detail) opened at the top-left under a `<dialog>` margin reset such as Tailwind's preflight: they now set `margin: auto` and `max-height: calc(100dvh - 2rem)` (next to the existing `max-width`), so they centre whatever the host reset.
- Responsive table mode: descendants of cells with `white-space: nowrap` (Tailwind `.whitespace-nowrap`, Bootstrap `.text-nowrap`) kept the table wider than its scroller (horizontal scroll on phones). In `[data-table-mode='responsive']` cells and their descendants now wrap (`white-space: normal !important`, `overflow-wrap: anywhere`) — part of the "no horizontal scroll at 320px and above" contract.

[1.17.2]: https://github.com/nowo-tech/UiKitBundle/compare/v1.17.1...v1.17.2

## [1.17.1] - 2026-10-16

### Added

- `partials/_responsive_table.html.twig`: `attr` — extra attributes on the root element (hash, attribute names escaped); `labels.save_error` (default: the existing `table_mode.save_error` translation) rendered as `messages.saveError`.
- `responsive-table` peer: `messages.saveError`; the `responsive-table:save-failed` event detail now carries `message` (the save error text, or empty) next to `mode` / `previous`, so hosts show their toast from a listener. `saveFailed(mode, previous)` stays protected and overridable.

### Changed

- `NowoUiKitExtension` accepts an optional `\Closure(): bool` (whether `symfony/http-client` is installed) so the "release check needs symfony/http-client" error path is tested; PHP line coverage back to 100 %. No behaviour change.

[1.17.1]: https://github.com/nowo-tech/UiKitBundle/compare/v1.17.0...v1.17.1

## [1.17.0] - 2026-10-16

### Added

- **Responsive table** (opt-in; Symfony Beacon spec 148, kit spec 005): `partials/_responsive_table.html.twig` (embed, block `table`), `_row_detail_head.html.twig`, `_row_detail_cell.html.twig` and the Stimulus peer `responsive-table` — Scroll ↔ Responsive toggle (corner tab or bar, static icons before JS), Responsive mode hides the last non-required columns until the table fits (two-phase, on resize; `data-required="1"` never hides), no horizontal scroll at 320px and above, row detail `<dialog>` (confirm chrome) that moves the cell contents in and back (forms and listeners intact), row title from the first two columns, tap-on-row on coarse pointers, default responsive on phones. Persistence is host-owned (`save_url`, `save_token`, `save_token_url`, `save_method`; JSON `{"mode"}` + `X-CSRF-TOKEN`, one token refresh on 403); a failed save reverts and dispatches `responsive-table:save-failed`.
- **Foldable filter card**: `partials/_filters_collapse.html.twig` + peer `filters-collapse` — corner tab under the fields, "N active filters" line (FormView count or `active_count`), state in a cookie read server-side (no flash), values kept while folded. `partials/_filters.html.twig`: `collapsible` / `collapse` options.
- **Card corner tab** `.nowo-ui-corner-tab` (`--under`, `.nowo-ui-corner-host`, `partials/_corner_tab.html.twig`) and tokens `--nowo-ui-corner-tab-{width,height,icon,accent}`.
- **Disclosure chevron** `partials/_disclosure_icon.html.twig` + peer `disclosure-icon` (follows `aria-expanded` or `<details open>`).
- **Page-edge tab** `partials/_edge_tab.html.twig` (slots, inline variant) and the Twig function `nowo_ui_route_link()` fed by the new `nowo_ui_kit.route_links` config (`base_url`, `default_testid`, `routes`; longest prefix wins).
- **Thin scrollbars**: `.nowo-ui-thin-scroll`, `html[data-nowo-ui-thin-scrollbars]` (page + kit scrollers), tokens `--nowo-ui-scrollbar-{size,thumb,thumb-hover}`; SCSS mixin `assets/styles/_thin-scrollbars.scss` for host selector lists.
- **Morphicons module** `assets/morphicons/index.ts` (optional peer dependencies `morphicons` ^1.7 and `lucide` ^1.0): `prepareMorphHost`, `asMorphIcon`, `syncMorphIcon` and sync helpers for theme, content width, sidebar, password, listing customizer, table mode, filters collapse, disclosure, search (magnifier ↔ X ↔ loader) and support (lifebuoy ↔ X); honours `data-motion="reduce"`. Icon state bridge `stimulus-peers/icon_state.ts` + `installMorphiconPeers()`.
- Canonical icon kinds `filter_off`, `scroll_horizontal`, `rows`, `chevron_up`, `chevron_down`, `chevron_right`, `book` in every icon set; recommended icon-only Filter / Clear pair (`tabler:filter` / `tabler:filter-off`, or `filter` / `filter_off`) documented.
- Translations `table_mode.*`, `filters_collapse.*`, `edge_tab.manual` (7 locales). Kitchen sink: *Host chrome* section; demos enable the thin scrollbars.
- Vitest suites for the new peers, the bridge, the morphicons module and the SCSS mixin (gated); PHPUnit for the partials, icons, CSS hooks, translations, configuration and Twig function.

[1.17.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.16.0...v1.17.0

## [1.16.0] - 2026-10-13

### Added

- **Appearance SPI** (opt-in `nowo_ui_kit.appearance`; Symfony Beacon spec 138 FR-005, kit spec 004): `Appearance` value object (brand name / eyebrow, light and dark `AppearancePalette`, theme ids, corner style, border strength, fixed footer; `with*()`, `toArray()` / `fromArray()`), `AppearanceProviderInterface` (host storage; default `ConfigAppearanceProvider` from `appearance.defaults`), `AppearancePresetRegistry` (config `presets`, tagged `AppearancePresetProviderInterface` services, built-ins `ocean`, `slate`, `sandstone`, `midnight`, `obsidian`, `aurora`, `ember`; `apply()`, `matchLightId()` / `matchDarkId()`), `CornerStyle` / `BorderStrength` enums (radius / border maps).
- `AppearanceCssEmitter`: `:root` + light + dark blocks of `--nowo-ui-*` tokens (radius, card radius, border width, primary / deep / hover / contrast, danger, warning, paper, text, surface, border, accent-soft, surface-muted, paper-accent, paper-ink, muted, brand-mark, shadow-rgb), colour sanitising, configurable `selectors`, `token_aliases` (extra property names with the same literal value) and `kit_tokens: false` (aliases only) — enough to reproduce a host emitter's legacy variable names exactly.
- Twig functions `nowo_ui_appearance()`, `nowo_ui_appearance_css()`, `nowo_ui_appearance_style(nonce)` (CSP nonce from the `csp_nonce` request attribute by default), `nowo_ui_appearance_presets(mode)`.
- Optional forms `AppearanceType` / `AppearancePaletteType` when `symfony/form` is installed (colour validation with `symfony/validator`); translations (7 locales). `symfony/form` and `symfony/validator` are only suggested (dev requirements for the test suite).
- Docs: THEMING.md — Appearance SPI (token table, storage / presets, legacy aliases); CONFIGURATION.md — Appearance.

[1.16.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.15.0...v1.16.0

## [1.15.0] - 2026-10-13

### Added

- **Icon filter actions** (`partials/_filter_actions.html.twig`): `submit_icon` / `clear_icon` (rendered through `components/_icon.html.twig` and `icon_set`) and `icon_only` (visually-hidden label + `title` / `aria-label`, square `.nowo-ui-filter-bar__action--icon-only`). Defaults unchanged.
- **Pre-paint theme boot**: `partials/_theme_boot.html.twig` (inline script with the CSP nonce, or `inline: false` → `js/nowo-ui-theme-boot.js`). Reads `data-nowo-ui-pref-{theme,density,motion,font-scale,contrast,sidebar}` (alias `data-user-*`) on `<html>`, falls back to the theme cookie / localStorage / OS and sets `data-theme`, `data-ui-density`, `data-motion`, `data-font-scale`, `data-contrast` before first paint; `window.nowoUiPrefs`, `window.nowoUiThemeBoot()`.
- Theme storage keys shared by the boot and the toggle: `data-nowo-ui-theme-storage` is also read from `<html>`, new optional `data-nowo-ui-theme-cookie` (`assets/src/nowo-ui-theme-core.ts`).
- **Sidebar rail** (opt-in `data-nowo-ui-sidebar-rail` on the shell, `_shell.html.twig` `sidebar_rail` / `sidebar_mode`): expanded / icons-only rail / hidden, burger ping-pong through the rail, phones keep the drawer, localStorage + optional cookie sync with configurable keys (`data-nowo-ui-sidebar-storage`, `-cookie`, `-hidden-value`, `-default`, `-rail-labels`), rail tooltips and initials, `nowo-ui:sidebar-mode` event, `window.nowoUiSetSidebarMode()`. CSS: `.nowo-ui-shell.is-aside-rail` (desktop only), `--nowo-ui-aside-rail-width`. Side-effect-free helpers for host shells: `assets/src/nowo-ui-sidebar-core.ts`. `_burger.html.twig` `rail: true` prints the next-action labels (`nav.sidebar_expand`, `nav.sidebar_rail`, `nav.sidebar_hide`; 7 locales).
- **Stimulus peers** `temporary-reveal` (masked secret, timed reveal, clear-on-hide purge), `collapse-panel` (persistent panels; storage key and server default as values) and `datatable` (responsive DataTables with server-side paging / sorting; `datatables.net-dt` and `datatables.net-responsive-dt` are optional peer dependencies). Ported from Symfony Beacon with neutral copy.
- Vitest suites for the new modules and peers (coverage-gated); PHPUnit for the new partial options.

### Changed

- Dev: Rector `RemoveConcatAutocastRector` applied to `Configuration::findInvalidRewrite()` (drops a redundant `(string)` cast; behaviour-neutral).

### Fixed

- `_burger.html.twig`: `expanded: false` (e.g. `_shell.html.twig` with `collapsed: true`) rendered `aria-expanded="true"` because of `|default(true)`.

[1.15.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.14.0...v1.15.0

## [1.14.0] - 2026-10-13

### Changed

- **Symfony floor raised to `^7.4 || ^8.0`** for every `symfony/*` requirement, matching the CI matrix (7.4, 8.0, 8.1). README badge and INSTALLATION requirements updated (PHP minimum documented as 8.2, as in `composer.json`).
- The extension no longer prepends `framework.translator` (`paths` and `fallbacks: [en]`): the bundle catalogue is auto-discovered, and the host's translator enablement and fallback order are left untouched. The bundle still boots with the translator disabled.
- Dev: vitest and `@vitest/coverage-v8` upgraded to ^4.1.11 (stimulus peer tests stub `window.confirm`, no longer provided by happy-dom under vitest 4).

### Fixed

- Release check: a failed GitHub fetch threw inside the cache callback, so failures were never cached and every status request triggered outbound GitHub calls. Failures are now negatively cached for 5 minutes (`ReleaseUpdateChecker::ERROR_CACHE_TTL`, capped by `cache_ttl`).

### Security

- Documented that `GET /_nowo-ui/release/status` is anonymous by default and discloses the installed version, and how to protect it (route prefix + `access_control`).

[1.14.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.13.2...v1.14.0

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

[Unreleased]: https://github.com/nowo-tech/UiKitBundle/compare/v2.0.0...HEAD
[1.10.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.8.4...v1.10.0
[1.8.4]: https://github.com/nowo-tech/UiKitBundle/compare/v1.8.3...v1.8.4
[1.4.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.2.1...v1.3.0
[1.2.1]: https://github.com/nowo-tech/UiKitBundle/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/nowo-tech/UiKitBundle/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/nowo-tech/UiKitBundle/releases/tag/v1.0.0
