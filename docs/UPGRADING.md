# Upgrading

## Table of contents

- [Unreleased](#unreleased)
- [To 2.4.0](#to-240)
- [To 2.3.1](#to-231)
- [To 2.3.0](#to-230)
  - [Template contract (2.3)](#template-contract-23)
- [To 2.2.0](#to-220)
  - [Template contract (2.2)](#template-contract-22)
- [To 2.1.0](#to-210)
- [To 2.0.1](#to-201)
- [To 2.0.0](#to-200)
  - [Template contract](#template-contract)
- [To 1.17.5](#to-1175)
- [To 1.17.4](#to-1174)
- [To 1.17.3](#to-1173)
- [To 1.17.2](#to-1172)
- [To 1.17.1](#to-1171)
- [To 1.17.0](#to-1170)
- [To 1.16.0](#to-1160)
- [To 1.15.0](#to-1150)
- [To 1.14.0](#to-1140)
- [To 1.13.2](#to-1132)
- [To 1.13.1](#to-1131)
- [To 1.13.0](#to-1130)
- [To 1.12.0](#to-1120)
- [To 1.11.0](#to-1110)
- [To 1.10.1](#to-1101)
- [To 1.10.0](#to-1100)
- [To 1.9.2](#to-192)
- [To 1.9.1](#to-191)
- [To 1.9.0](#to-190)
- [To 1.8.5](#to-185)
- [From 1.8.3 to 1.8.4](#from-183-to-184)
- [From 1.8.2 to 1.8.3](#from-182-to-183)
- [To 1.8.2](#to-182)
- [To 1.8.1](#to-181)
- [To 1.8.0](#to-180)
  - [New optional scripts](#new-optional-scripts)
  - [Stimulus peers (optional)](#stimulus-peers-optional)
  - [Beacon hosts](#beacon-hosts)
- [To 1.6.0](#to-160)
  - [Row actions display (REQ-UI-001)](#row-actions-display-req-ui-001)
  - [Default `ui.action` variants](#default-uiaction-variants)
- [To 1.5.1](#to-151)
- [To 1.5.0](#to-150)
  - [Twig Extra Bundle (REQ-TWIG-004)](#twig-extra-bundle-req-twig-004)
  - [Twig-CS-Fixer (maintainers)](#twig-cs-fixer-maintainers)
- [From 1.3.x to 1.4.0](#from-13x-to-140)
- [From 1.2.x to 1.3.0](#from-12x-to-130)
- [From 1.2.0 to 1.2.1](#from-120-to-121)
- [From 1.1.x to 1.2.0](#from-11x-to-120)
- [From 1.0.x to 1.1.0](#from-10x-to-110)
- [From nothing to 1.0.0](#from-nothing-to-100)
  - [Install](#install)

## Unreleased

Additive (minor release); run `php bin/console assets:install` for the new `nowo-ui-tabs.js` and `nowo-ui.css`.
Notes:

- `nowo-ui-tabs.js` now only drives the triggers / panels of the nearest `[data-nowo-ui-tabs]` root. Markup that relied
  on an outer root switching the panels of a nested root must give the nested set its own root (or drop the inner
  `data-nowo-ui-tabs`).
- Triggers with `role="tab"` get a roving `tabindex` (only the selected tab is in the Tab order) and arrow keys, per
  the WAI-ARIA tabs pattern. Triggers without `role="tab"` behave as before.
- **Form spacing**: `form.nowo-ui-form` is now a flex column with `gap: var(--nowo-ui-form-row-gap)` (no sibling
  `margin-top`), and the `form_actions` slot lost its `mt-3` / `mt-4`. Hosts that relied on the margin, or that put
  their own gap / margin utilities on the `form` slot (e.g. `flex flex-col gap-4`) to compensate, should drop those
  and set the tokens instead: `--nowo-ui-form-row-gap`, `--nowo-ui-form-actions-gap`, `--nowo-ui-form-label-gap`,
  `--nowo-ui-form-column-gap`. At default tokens the scaffolds look the same as in 2.4 (checked pixel for pixel on
  the page-contract demo: form, settings, wizard, auth, confirm): rows of the Symfony Bootstrap form theme keep their
  `mb-3` and the gap gives that rem back, and the bottom margin of a row's last descendants is trimmed where it used
  to collapse into the row margin. Template overrides that render actions with `mt-3` in the class keep working.
- Optional: replace per-bundle locale tab markup (own `_locale_tabs` templates, Bootstrap `nav-tabs` + CDN JS, radio
  hacks, `locale-tabs:changed` hooks) with the `locale_tabs` partial and `nowo-ui:tabs:change` — see
  [USAGE](USAGE.md#migrating-your-locale-ui). A host layout listing UiKit scripts by hand keeps `js/nowo-ui-tabs.js`.

## To 2.4.0

From **2.3.1**:

```bash
composer update nowo-tech/ui-kit-bundle
```

Additive (minor release); nothing to change. Optional:

- Replace a copied `PagePagination::fromTotal($total, $page, $perPage)` with
  `Pagination::fromTotal($total, $page, $perPage)` (same page, per_page, total, total_pages; `offset()` instead of
  `['offset']`, which still works through `ArrayAccess`) and pass the object straight to the pagination partial.
- Row / overflow menus inside table scrollers: `popover: true` on `menu` / `_kebab`, `menu: true, popover: true` on
  `row_actions`, or `nowo_ui_kit.theme.popover_menus: true`; drop a local popover-menu Stimulus controller.
- A host layout that lists UiKit scripts by hand (instead of `{{ include(nowo_ui_partial('scripts')) }}`) adds
  `js/nowo-ui-popover.js` and `js/nowo-ui-segmented.js` when it uses these partials.

## To 2.3.1

From **2.3.0**:

```bash
composer update nowo-tech/ui-kit-bundle
```

- **State-hide rules are `!important`** (see [PAGE_CONTRACT — Cascade layers](PAGE_CONTRACT.md#cascade-layers)): if
  your CSS deliberately showed an element the kit hides in a state (e.g. a field of folded filters, a closed
  `.nowo-ui-dialog`), that rule now needs `!important` as well. Nothing to do otherwise.
- **Optional**: replace an empty `templates/bundles/NowoUiKitBundle/partials/_breadcrumbs.html.twig` (or a
  `theme.partials.breadcrumbs` pointing at an empty template) with

  ```yaml
  nowo_ui_kit:
      breadcrumbs:
          enabled: false
  ```

## To 2.3.0

From **2.2.0**:

```bash
composer update nowo-tech/ui-kit-bundle
```

Target **2.3.0** (`composer require nowo-tech/ui-kit-bundle:^2.3`). No PHP API is removed; new constructor arguments
are trailing and optional. Changes hosts and bundles may notice:

### Template contract (2.3)

- **List filters translate each text once**: a `FilterField::$placeholder` `Label` and the default "Any" option
  (`filters.any`) are handed to the form theme untranslated (`|nowo_ui_translatable`), so the theme's `|trans`
  translates them exactly once in their own domain (2.2 translated them and then the form theme translated the result
  again in the field's domain: a missing message per render). A **string** placeholder is now printed as given (2.2
  translated it in the field's domain); pass a `Label` to translate it. Fields rendered with
  `translation_domain: false` (or with `attr_translation_parameters`) get the pre-translated text as before.
- **Contract test, missing messages (opt-in)**: the default is unchanged (dotted ids only). Return true from the new
  `PageContractTestCase::strictMissingTranslations()` and `testPageHasNoRawTranslationKeys` fails on **every** message
  the translator could not find, which catches double translations and literals piped through `|trans` (e.g.
  humanized form labels of a form without `translation_domain`). **Strict becomes the default in 3.0: opt in now.**
  Accepted exceptions go in `ignoredMissingTranslations()` (`domain:id`, `id` or a `/regex/` on `domain:id`).
- **Auth scaffold**: the root form errors (`form_errors(form)`: CSRF, global errors) now render right after `<form>`
  in the new `form_errors` slot. A bundle that prints `form_errors(form)` itself shows them twice until it either drops
  its call or skips the scaffold's: `AuthPage(rootErrors: false)`, or `{% set nowo_ui_auth_root_errors = false %}` in
  the template extending `page/auth` (no view-model change). The form functions moved to `page/_auth_form.html.twig` (the scaffold compiles
  without symfony/form); the default `nowo_ui_auth_fields` content is the same child loop. The panel is centred at
  `min(28rem, 100%)` (`--nowo-ui-auth-panel-width`) in any guest layout and its fields are full width (Tailwind's
  `max-w-xs` from the `input` slot still caps them: set the slot per page type, `theme.pages.auth`). The footer link
  list reset uses `!important` (wins over an unlayered Bootstrap reboot).
- **1.x `page_header` partial**: with the ThemeExtension the title gets the full `page_title` slot resolution (semantic
  + framework defaults + host classes, e.g. Bootstrap `h3 mb-1`) instead of the semantic + host classes only — Bootstrap
  hosts no longer get a full-size `<h1>`. A host that relied on the bare title sets `framework_defaults: false` on the
  `page_title` slot. Rendered without the ThemeExtension the partial is unchanged.
- **Content padding**: `.nowo-ui-body { padding: 1rem 0 }` no longer applies to `<body>` (it drew a band above host
  navbars); the content region (slot `content`, `<main class="nowo-ui-content">`) gets
  `padding-block: var(--nowo-ui-content-padding-block, 1rem)` (zero specificity; 0 inside the 1.x shell's
  `.nowo-ui-main`). Non-body `.nowo-ui-body` wrappers (1.x) keep their padding.
- **`--nowo-ui-link`** is now declared next to the primary (`var(--nowo-ui-primary)`, light and dark palettes), so CSS
  can read it without a fallback. A host that remaps `--nowo-ui-primary` on a **scope** (not `:root`) remaps
  `--nowo-ui-link` there too.
- **Code**: inline `code` no longer turns pink under Bootstrap 5 (`--bs-code-color` follows `--nowo-ui-code-text`);
  the Bootstrap 4 `code` slot default is `text-body`.
- **Unique pages**: `nowo_ui_page_header(header)` (or `page/_page_header.html.twig`) renders the scaffolds' page
  header; the private scaffold macro now delegates to it (same output).
- **Menus**: new `menu` partial and slots `menu`, `menu_toggle`, `menu_panel`, `menu_item`, `popover`;
  `nowo-ui-dialog.js` now also closes open `<details data-nowo-ui-menu>` on an outside click / Escape (no effect on
  other `<details>`). `nowo_ui_class()` / `Theme::classFor()` take a trailing `defaults` argument (host-only mode).
- **UiKit form theme**: fields with errors get the `input_invalid` slot (Bootstrap `is-invalid`) and their messages
  the `form_error` slot (Bootstrap `invalid-feedback d-block`) instead of a bare `<ul>`; PasswordToggle inputs get the
  `input` slot.

## To 2.2.0

From **2.1.0**:

```bash
composer update nowo-tech/ui-kit-bundle
```

Target **2.2.0**. From **2.1.x**: `composer require nowo-tech/ui-kit-bundle:^2.2`. No PHP API is removed; new
constructor arguments are trailing and optional. Visual changes of the default theme are listed below so hosts can
review their screenshots.

### Template contract (2.2)

- **Confirm** (`_confirm` with a `View\Confirm`, and `page/confirm`): the confirm button of a **danger** `Confirm` now
  reads the slot **`btn_danger_solid`** (Bootstrap `btn btn-danger`) instead of `btn_danger`; a host that styled the
  confirm button through `btn_danger` sets `btn_danger_solid` too. 2.0 confirms get a **close button** in the header
  (`<button class="nowo-ui-confirm__close" data-nowo-confirm-close aria-label>`; `dismissible: false` removes it) and the
  class `nowo-ui-confirm--<size>` for `sm` / `lg`. 1.x inputs (`id` / `title` / `body`) render as before.
- **Badges**: the badge slot defaults changed to one subtle set and lost `ms-2` / `ml-2` (`badge` →
  `badge bg-body-tertiary text-body-emphasis border`, `badge_success` → `badge bg-success-subtle text-success-emphasis
  border border-success-subtle`, …, `badge_info` → primary subtle). Hosts that relied on the solid look or on the
  margin set the slots (THEME_SLOTS "Badges"). The `ui.badge()` macro is unchanged.
- **Tables**: the `table` slot adds `table-striped` on Bootstrap. The list scaffold renders its table through the new
  **`table` partial** (same markup; the `nowo_ui_list_head` / `nowo_ui_list_row` blocks still override the cells). The
  actions column header is **visible** by default — pass `actionsLabelVisible: false` to keep the 2.1 visually-hidden
  label. Header cells keep their `nowo-ui-cell--<align>` class and now honour it (CSS specificity fix).
- **Filters**: the `filters` slot defaults are a grid (`d-grid gap-3 mb-3` / `grid gap-3 mb-4`; Foundation lost
  `grid-x grid-margin-x mb-3`); text widgets get the `input` slot classes, choice widgets `nowo-ui-input` + host
  `input` classes; optional single selects without a placeholder show "Any" (`filters.any`). A host `filters` class map
  written for the flex row may need a review.
- **Settings**: with an area nav in the header (`PageHeader::tabs`), the section tabs use **`tabs_secondary`**
  (Bootstrap `nav nav-pills mb-3`). Sections with `actions` print the actions row right after `</form>` (submit button
  with `form="<id>"`, the form gets an `id`); without actions the markup is unchanged.
- **Breadcrumbs**: with BreadcrumbKit, the wrapper is `<div class="nowo-ui-breadcrumbs" data-nowo-ui-breadcrumbs="kit">`
  without the `breadcrumbs` slot host classes / attributes (they were printed twice with BreadcrumbKit ≥ its UiKit
  migration).
- **Scripts**: the document layout prints its scripts through the new `scripts` partial (same tags). Host layouts with
  their own script list should replace it by `{{ include(nowo_ui_partial('scripts')) }}`.
- **Flashes**: the `flashes` input of the `flashes` partial is deprecated in favour of `flash_messages` and is ignored
  inside page scaffolds (`nowo_ui_page_type` set).
- **Auth**: no empty `<footer>` without links / footer content.
- **Hidden fields** of FormView-backed actions always carry `value=""`.
- **Contract test**: the stub `page_header` renders `header.tabs` (pages with an `AreaNav` now render the stub theme's
  `tabs` slots); a bundle's `inlineTemplates()` entry with a stub template name replaces the stub template.
- **Tables** without a caption get `aria-label` (list scaffold: the page title). The row-actions column is
  `width: 1%; white-space: nowrap` (row actions no longer wrap).
- **`icon_set: none`**: actions render text-only (no icon-only / icon + text variants without icons).
- **Code**: `--nowo-ui-code-bg` defaults to a text-tinted surface and inline code gets a 1px inset border; set the
  token to restore the 2.1 look (`var(--nowo-ui-surface-muted)`).
- New slots: `check`, `check_input` (UiKit form theme `@NowoUiKit/form/nowo_ui_layout.html.twig`), `btn_danger_solid`, `table_caption`, `tabs_secondary`, `confirm_close`, `badge_primary`, `progress`,
  `progress_meter`; new partials: `scripts`, `table`, `progress`. Host class maps keep working; contract-test
  allow-lists need no change (slot defaults are allowed automatically).

## To 2.1.0

From **2.0.x**:

```bash
composer require nowo-tech/ui-kit-bundle:^2.1
```

No breaking change; everything below is opt-in except where noted.

- **Host layouts** that print their own script list (instead of the UiKit document layout's `nowo_ui_scripts`) add
  `js/nowo-ui-dialog.js` to use the new `dialog` partial. The UiKit document layout loads it (one more `<script defer>`).
- **Host `page_header` overrides** should render `header.tabs` (`AreaNav`) through `nowo_ui_partial('tabs')` when
  bundles start using area navigation (see PAGE_CONTRACT "Area navigation"); without it the tab bar is simply absent.
- **Action forms**: a `FormView`-backed action prints the hidden leaf fields of its form on every render (fix);
  overrides of `_action_form_view` are not a public partial and need no change.
- **Contract test (`PageContractTestCase`)**: one more test per fixture (`testPageActionFormsKeepTheirHiddenFields`),
  the stub `page_header` override renders `actions`, and the `modal_shell` structure classes no longer need to be in
  `allowedClasses()` (they can stay).
- **`Action` / `PageHeader` / `FormPage` / `SettingsSection` constructors** gained trailing optional arguments
  (`fallbackHref`, `tabs`, `formThemes`): positional calls keep working.

## To 2.0.1

From **2.0.0** — bug fix, nothing to change (same fix as 1.17.5 on the 1.x line). Responsive tables hide columns again
on narrow screens. New root attributes: `data-rt-overflow` (required columns still too wide: words may break) and
`data-rt-measuring` (set only during a fit). Host CSS must not force word breaking on responsive table cells while
`data-rt-measuring` is present; scope host `!important` rules with `:not([data-rt-measuring])`.

## To 2.0.0

From **1.17.x**:

```bash
composer require nowo-tech/ui-kit-bundle:^2.0
```

**Page contract (REQ-UI-003, new).** Everything below is additive; existing partials and macros keep their 1.x
inputs and output. Changes a host may notice are listed under [Template contract](#template-contract).

- New layouts: `@NowoUiKit/layout/document.html.twig`, `layout/admin.html.twig`, `layout/guest.html.twig`
  (blocks `nowo_ui_document_title`, `nowo_ui_head`, `nowo_ui_styles`, `nowo_ui_scripts`, `nowo_ui_body`,
  `nowo_ui_navigation` (admin), `nowo_ui_page_header`, `nowo_ui_flashes`, `nowo_ui_content`, `nowo_ui_modals`).
  Point `nowo_ui_kit.layouts.admin` / `guest` at your layout or a one-file bridge that renders these blocks.
- New scaffolds `@NowoUiKit/page/{list,show,form,settings,dashboard,confirm,wizard,auth}.html.twig` (variable
  `page`, data blocks `nowo_ui_list_head`, `nowo_ui_list_row`, `nowo_ui_list_after`, `nowo_ui_show_sections`,
  `nowo_ui_show_after`, `nowo_ui_form_body`, `nowo_ui_form_aside`, `nowo_ui_settings_section`,
  `nowo_ui_dashboard_cards`, `nowo_ui_wizard_step`, `nowo_ui_auth_fields`, `nowo_ui_auth_actions`,
  `nowo_ui_auth_footer`).
- New view models under `Nowo\UiKitBundle\View\` and theme config `nowo_ui_kit.theme` (classes, attributes,
  partials, tokens, tokens_dark, bundles) with the Twig functions `nowo_ui_class()`, `nowo_ui_attrs()`,
  `nowo_ui_partial()`, `nowo_ui_theme_tokens()`. See [`PAGE_CONTRACT.md`](PAGE_CONTRACT.md).
- Hosts that set `theme.tokens` and render the UiKit document layout get `data-nowo-ui-host-tokens` on `<html>`:
  the kit palette then never beats a host declaration, so `!important` remaps of `--nowo-ui-*` can be removed.
  Hosts with their own layout add the attribute themselves and print `{{ nowo_ui_theme_tokens() }}` after
  `nowo-ui.css`.
- UI bundles get a shared contract test, `Nowo\UiKitBundle\Test\PageContractTestCase` (add `phpunit/phpunit` to
  the bundle's `require-dev`); migration recipe in [`MIGRATING_BUNDLES.md`](MIGRATING_BUNDLES.md), slot vocabulary in
  [`THEME_SLOTS.md`](THEME_SLOTS.md).
- **Badge classes (Bootstrap 5 / Tabler).** `ui.badge()` and the `badge*` slots now print `text-bg-<variant>`
  instead of `bg-<variant>` (+ `text-dark`): contrast fix, text and background come from the same utility. Hosts that
  recoloured badges through `--bs-<variant>-rgb` and a plain `color` rule now see Bootstrap's `!important` text colour;
  move those badges to `theme.classes.badge_*: {value: '…', framework_defaults: false}` (no framework classes) or
  style `.text-bg-*`. Bootstrap < 5.2 has no `text-bg-*`: the semantic palette paints the badge.
- **`theme.framework_defaults`** (new, default `true`) and the class entry map `{value, framework_defaults}` (also
  under `theme.bundles.<B>`) drop UiKit's framework defaults globally, per bundle or per slot
  ([`THEME_SLOTS.md`](THEME_SLOTS.md#dropping-the-framework-defaults)). The processed config of a map entry is
  `{value: string, framework_defaults: bool}`; string / list entries stay strings.
- New slot `card_footer`; `_card` reads the `card*` slots and accepts view-model inputs (1.x inputs unchanged).
- Hosts that override `_pagination`, `_toasts`, `_flashes`, `_page_header`, `_row_actions`, `_confirm`, `_empty`,
  `_card`, `_shell` or `_burger` keep working; to pick up view-model inputs, re-sync from the bundle (or replace the fork with
  `theme.classes` / `theme.attributes` / `theme.partials`).

### Template contract

Changes to markup, inputs and cascade a host or a bundle may depend on (REQ-UI-003 validation in Symfony Beacon).
1.x partial inputs keep rendering as in 1.17.4 unless a bullet says otherwise.

- **CSS cascade layer.** `nowo-ui.css` is now wrapped in `@layer nowo-ui` and declares the order
  `theme, base, nowo-ui, components, utilities`. Tailwind v4 hosts: utilities in theme slots now beat the kit; keep
  `nowo-ui` above `base` (load `nowo-ui.css` first or put the statement at the top of your stylesheet —
  [`PAGE_CONTRACT.md#cascade-layers`](PAGE_CONTRACT.md#cascade-layers)). Hosts without layers: your unlayered CSS
  (and an unlayered framework such as Bootstrap from a CDN) now always beats the kit; to keep the 1.x cascade, link
  `css/nowo-ui.unlayered.css` (identical rules, no layer) instead of `css/nowo-ui.css`, or import the framework into
  the `base` layer (`@import url(…) layer(base)` after the order statement). Tailwind v3 / play CDN output is
  unlayered: use `nowo-ui.unlayered.css`. The kitchen sink links the unlayered file.
- **`_confirm` buttons** read the theme slots `btn_secondary` / `btn_<variant>` when the ThemeExtension is loaded
  (output identical to 1.17.4 with an empty theme; host classes and `framework_defaults: false` now apply). Hosts that
  styled `.nowo-ui-confirm .btn` to compensate can move that to `theme.classes`.
- **Type-to-confirm:** `Confirm::expectedValue` / `expectedLabel`; `_action_post` accepts `form_id`. Hosts that
  override `_confirm` render the expected input themselves (see the default partial) — the JS contract is
  `input[data-nowo-confirm-expected]` + `[data-nowo-confirm-submit]` inside the dialog or a `[data-nowo-confirm-scope]`.
- **Row-action variant slots** `action_primary`, `action_secondary`, `action_danger` (host classes added in row
  context by resolved variant; new slots = minor).
- **Every include goes through `nowo_ui_partial()`**: `_page_header` resolves `breadcrumbs` / `action`, `_empty`,
  `_card` and `_row_actions` resolve `action`, the settings scaffold renders its tabs through the `tabs` partial
  (which now reads the `tabs` / `tab` / `tab_active` slots; 1.x output unchanged with an empty theme). New partial
  names `list_filters`, `detail_list`, `section_form`, `wizard_form`. A `theme.partials.action` override now also
  applies inside page headers, empty states, cards and row actions.
- **1.x shapes for host overrides** ([`PAGE_CONTRACT.md#template-contract`](PAGE_CONTRACT.md#template-contract)):
  the list scaffold passes `pagination` as the 1.x array (+ `pagination_model`), row actions as 1.x hashes (+ the
  `Action` as `view`, `confirm_external: true`) and renders the row confirm dialogs once after the table; every
  scaffold passes `title` / `intro` / `toolbar` to `page_header` next to `header`. `Pagination` and `Action`
  implement read-only `ArrayAccess` with the 1.x keys. Overrides written for the 2.0 inputs: `header.actions` are
  still there, but render `actions` (or `toolbar`) to include FormPage secondary actions.
- **Regions and page attributes:** standard regions carry `data-nowo-ui-region="…"`; every page model takes
  `attributes` (rendered on the scaffold root, validated: no `on*`). `_page_header`, `_pagination`, `_empty` and
  `_tabs` accept `region`.
- **List scaffold:** new data block `nowo_ui_list_filters`; the filters render through the `list_filters` partial
  with per-field options (`View\FilterField`), every filter row gets the `filters_field` slot class, the apply button
  gets `nowo-ui-filters__submit` (and `filtersSubmit` label / icon / variant), `filtersClear` / `filterActions`
  render through the `action` partial. Header cells get the `table_head_cell` slot, the head row `table_head_row`,
  the actions header `table_actions_head` (`actionsLabel`, `actionsLabelVisible`). **Responsive by default:**
  `ListPage::responsive = 'cards'` adds `data-nowo-ui-responsive="cards"` to the table wrap, `nowo-ui-table--cards`
  to the table and `data-label` to the default cells — below 40rem of table width rows become cards; pass
  `responsive: 'scroll'` for the 1.x horizontal scroll. `Column::priority` (2 / 3) hides columns on mid widths.
- **Form scaffold:** the form is wrapped in the `form_panel` slot; **secondary actions move to the page-header
  toolbar by default** (`FormPage::secondaryActionsInHeader`, set `false` for the previous placement below the
  form); the danger zone title / intro use the `danger_zone_title` / `danger_zone_intro` slots
  (`dangerZoneTitle`, `dangerZoneIntro`).
- **Dashboard:** KPI label / value / hint read the `kpi_label` / `kpi_value` / `kpi_hint` slots;
  `KpiTile::linkTile` renders the whole tile as the link.
- New slots (minor): `table_head_row`, `table_head_cell`, `table_actions_head`, `filters_field`, `kpi_label`,
  `kpi_value`, `kpi_hint`, `form_panel`, `danger_zone_title`, `danger_zone_intro`.
- **Token precedence:** `theme.tokens_dark` is emitted on `[data-theme="dark"]` (was `:root[data-theme="dark"]`),
  the same specificity as the appearance SPI, and the new `nowo_ui_tokens()` prints the theme tokens then the
  appearance style; the document layout uses it, so a user-chosen appearance now overrides `theme.tokens` (before,
  the theme's dark tokens won by specificity and the light ones by order only when the host printed them last).
  Host layouts: replace `nowo_ui_theme_tokens()` + `nowo_ui_appearance_style()` with `{{ nowo_ui_tokens() }}`.
- **Toolbar display modes:** `Action::display` and the `action` partial's `display` option now apply outside rows
  (`ListPage::filtersDisplay` for filter buttons); icon-only toolbar buttons add the `btn_icon` slot class
  (`nowo-ui-btn--icon`) and `title` / `aria-label`. Without `display` the toolbar markup is unchanged. `theme.partials.action`
  overrides: read `action.display` (and the `display` variable) to support icon-only buttons. New slot `btn_icon` (minor).
- **List header actions in the filter row** (opt-in: `ListPage::headerActionsInFilters` or
  `theme.pages.list.header_actions_in_filters: true`): the page header then gets no toolbar (1.x `page_header`
  overrides receive `toolbar: null`; 2.0 overrides receive `actions: []` and a `header` without actions —
  `PageHeader::withActions([])` — so `actions|default(header.actions)` prints nothing) and the `list_filters` partial receives the actions as `header_actions`; a bundle
  that overrides `nowo_ui_list_filters` renders `page.header.actions` itself when `nowo_ui_header_actions_in_filters`
  is true. `theme.pages.<type>.header_actions_in_filters` is rejected on page types other than `list`.
- **Internal slots** `card_title`, `card_intro`, `detail_item`, `detail_term`, `detail_value`, `confirm_panel`,
  `confirm_header`, `confirm_title`, `confirm_body`, `confirm_message`, `confirm_expect_label`, `confirm_footer`,
  `auth_footer`, `auth_links`, `kpi_link` (minor; markup unchanged with an empty theme). CSS: `a.nowo-ui-kpi--link` and
  `.nowo-ui-detail-list__item { display: contents }` now have zero specificity (`:where()`), so host link colours on KPI
  tiles and host classes on detail items win. Full-page confirm: the title sits in a
  `<header class="nowo-ui-confirm-page__header">` and the message in `<div class="nowo-ui-confirm-page__body">`.
- **Neutral variant:** `Action::resolvedVariant()` returns `neutral` (was `secondary`) for kinds other than delete /
  create / save without an explicit variant. Rendering is unchanged (toolbar `btn_secondary`, row `ui.action`
  secondary classes), but row actions now read the new `action_neutral` slot instead of `action_secondary`: move
  `theme.classes.action_secondary` to `action_neutral` (or set both) to keep styling every plain row action;
  `action_secondary` now targets explicit `variant: 'secondary'` only. Code comparing `resolvedVariant()` with
  `'secondary'` checks `'neutral'` too.
- **Actions column:** header and cells use `nowo-ui-cell--<ListPage::actionsAlign>` (default `end`, as before) and
  the body cell gets the `table_actions_cell` slot (`nowo-ui-table__actions`, same class as before; class order in
  the cell changed). The row-action cluster is now aligned with the column (`justify-content: flex-end` by default:
  in table mode the buttons sit at the cell end, under the header).
- **Card-mode CSS:** direct children of card cells get `justify-self: start` (badges no longer stretch);
  `nowo-ui-table-wrap--flush` is new.
- **List pagination variables:** the list scaffold passes `route`, `route_params`, `query`, `page_param` and
  `per_page_param` to the `pagination` partial. `query` is the current request query (minus the page / per-page
  parameters) merged with `Pagination::query` (which wins per key), so page links now keep the active filters and
  sort by default; a list that must not carry them overrides the `pagination` partial (`theme.bundles.<B>.partials`).
- **Row confirm dialog ids** are derived from the target, the CSRF token, the whole `Confirm` content and the row
  key (`ListPage::rowActions()` returns `Action::withConfirmScope(key)` copies), so the `confirm_id` / dialog ids of
  list rows change value (still `nowo-ui-confirm-<12 hex>`). Tests or scripts that computed
  `Action::delete(…)->confirmId()` for a list row use `->withConfirmScope($rowKey)->confirmId()`, or set an explicit
  `Confirm::$id`. An explicit id shared by row actions with different content now throws a `LogicException`.
- **Page types.** Scaffolds set `nowo_ui_page_type` (`list`, `show`, `form`, `settings`, `dashboard`, `confirm`,
  `wizard`, `auth`) and pass it to the partials they include; `theme.pages.<type>.{classes,attributes,framework_defaults}`
  varies a slot per page type (bundle slot > bundle > page-type slot > page type > host slot > host).
  `nowo_ui_class()` / `nowo_ui_attrs()` take an optional `page` argument and read `nowo_ui_page_type` from the
  context; `Theme::classFor()`, `extraClasses()`, `frameworkDefaults()`, `macroClasses()`, `attributesFor()` and
  `renderAttributes()` take an optional trailing `page`. Host overrides of UiKit partials receive
  `nowo_ui_page_type` and should forward it to `nowo_ui_theme.macroClasses(…, bundle, nowo_ui_page_type)`.
- **Theme class validation relaxed:** class lists may contain `"`, `'`, `<`, `>` and `&` (Tailwind arbitrary / child
  variants); only control characters and invalid UTF-8 are rejected. Host overrides of UiKit partials must keep
  printing slot classes through Twig's escaping (`class="{{ nowo_ui_class(…) }}"`, never `|raw`).
- **Full-page confirm root:** the `<section>` of `page/confirm.html.twig` now carries `nowo-ui-confirm-page` + the new
  `confirm_page` slot (classes / attributes) and the `card` slot; it no longer carries `nowo-ui-confirm` nor the
  `confirm` slot classes / attributes (those are the `<dialog>`'s). Move page-only classes from `theme.classes.confirm`
  to `confirm_page`; UiKit's `.nowo-ui-confirm` dialog box rules (transparent background, no padding / border) no
  longer apply to the page, so it paints as a regular card.
- **Confirm hooks (minor):** new slots `confirm_page`, `confirm_expect`, `detail_list_meta`; `Confirm::messageInHeader`
  (last constructor argument) and `theme.confirm_message_in_header` move the message under the title. With a theme
  that sets `confirm_message` classes or attributes, a 1.x `body` passed to `_confirm` is wrapped in
  `<div class="nowo-ui-confirm__message …">`; `_confirm` overrides read `confirm.messageInHeader ??
  nowo_ui_theme.confirmMessageInHeader()` to support it.
- **Filter row with header actions:** the page-header actions moved into the filter row now follow the apply / clear
  / extra filter buttons directly instead of sitting alone at the far end of the row. To keep the previous split,
  add `ms-auto` / `ml-auto` (or `margin-inline-start: auto`) to `.nowo-ui-filters__header-actions` and let
  `filters_actions` grow (`flex-grow-1` / `grow`). Themes whose filter fields grow add `flex-none` to `filters_actions`.
- **Minor slots** `btn_link`, `muted`, `modal_panel`, `modal_header`, `modal_body`, `modal_footer`, `breadcrumb_link`,
  `breadcrumb_separator`, `table_cell_muted`, `row_action_disabled`, `sortable_header`, `sort_link`. Markup notes:
  toolbar link-variant actions read `btn_link` instead of `btn` (same default classes; move `theme.classes.btn` hacks
  for link buttons to `btn_link`); empty detail-list values wrap their `—` in `<span class="nowo-ui-muted …">`;
  default list cells with `null` / `''` / `false` print `—` (was empty) with `nowo-ui-table__cell--muted`; sortable
  header cells get `nowo-ui-table__head-cell--sortable`; disabled `Action` row actions get `nowo-ui-action--disabled`;
  the plain breadcrumb trail prints `<span class="nowo-ui-breadcrumbs__separator" aria-hidden="true">/</span>` (the
  CSS separator only applies to items without it); `_modal_shell` appends the `modal*` host classes / attributes
  (unchanged with an empty theme).

## To 1.17.5

From **1.17.4** — bug fix, nothing to change. Responsive tables hide columns again on narrow screens (1.17.2–1.17.4
broke words instead). New root attributes: `data-rt-overflow` (required columns still too wide: words may break) and
`data-rt-measuring` (set only during a fit). Host CSS must not force word breaking (`overflow-wrap: anywhere`,
`word-break: break-all`) on responsive table cells while `data-rt-measuring` is present — the kit rule wins with
`!important`; scope host `!important` rules with `:not([data-rt-measuring])`.

## To 1.17.4

From **1.17.3** — nothing to change for hosts: same runtime code and CSS (maintainer release gate only). Prefer 1.17.4
over 1.17.3.

## To 1.17.3

From **1.17.2** — CSS fix only, nothing to change. Responsive tables hide columns again before splitting words: above
30rem cell text wraps at spaces only (`overflow-wrap: break-word`), mid-word breaks (`anywhere`) are back to narrow
screens (≤ 30rem). Host workarounds for "Symf ony"-style breaks in responsive tables can be removed.

## To 1.17.2

From **1.17.1** — CSS fixes only, nothing to change. Kit dialogs (`.nowo-ui-confirm`, row detail) centre under
Tailwind preflight; in Responsive table mode `nowrap` content inside cells wraps. Hosts that worked around either
(their own `dialog { margin: auto }`, `.whitespace-nowrap` overrides in responsive tables) can drop the workaround.
Cell content that must never wrap in Responsive mode needs a host rule with higher specificity and `!important`.

## To 1.17.1

From **1.17.0** — patch, nothing to change. Optional: pass `attr` to `_responsive_table.html.twig` for extra root
attributes, and show your error toast from `responsive-table:save-failed` using `event.detail.message` (the
`labels.save_error` text, default `table_mode.save_error`) instead of subclassing the peer:

```ts
document.addEventListener('responsive-table:save-failed', (event) => {
  showToast({ title: (event as CustomEvent<{ message: string }>).detail.message, tone: 'error' });
});
```

## To 1.17.0

From **1.16.0** — host chrome: responsive tables, foldable filter cards, corner / edge tabs, thin scrollbars,
optional morphicons. Everything is opt-in (new partials, classes, peers, config key).

```bash
composer update nowo-tech/ui-kit-bundle
php bin/console assets:install
```

- No breaking changes; no new Composer dependency. New optional **JS** peer dependencies `morphicons` (^1.7) and
  `lucide` (^1.0), only for hosts that import `assets/morphicons`.
- Responsive tables: embed `partials/_responsive_table.html.twig`, add `data-column` (+ `data-required="1"`) to the
  header cells, `data-column` to the row cells, `_row_detail_head` / `_row_detail_cell` at the end of each row, and
  register the `responsive-table` peer. Persisting the mode needs a host endpoint (JSON `{"mode"}`, `X-CSRF-TOKEN`).
- Foldable filters: `partials/_filters_collapse.html.twig` as the first child of the filter form (or
  `collapsible: true` on `_filters.html.twig`) + the `filters-collapse` peer.
- Animated icons: call `installMorphiconPeers()` from `assets/morphicons` once in the Stimulus bootstrap and pass
  `morph: true` to the partials.
- Thin scrollbars: `data-nowo-ui-thin-scrollbars` on `<html>` (or `.nowo-ui-thin-scroll`, or the SCSS mixin).
- Route links: `nowo_ui_kit.route_links` + `nowo_ui_route_link()` with `partials/_edge_tab.html.twig`.
- Hosts that override `components/_icon.html.twig` should add the new canonical kinds (`filter_off`,
  `scroll_horizontal`, `rows`, `chevron_up`, `chevron_down`, `chevron_right`, `book`) or pass `icons` to the new
  partials.

## To 1.16.0

From **1.15.0** — optional appearance SPI. Nothing changes unless `nowo_ui_kit.appearance.enabled` is set.

```bash
composer update nowo-tech/ui-kit-bundle
```

- No breaking changes; no new required dependency (`symfony/form` / `symfony/validator` are only suggested for the optional form types).
- To adopt: enable `appearance`, point `appearance.provider` at a service implementing `AppearanceProviderInterface` (map your stored settings to an `Appearance`), register your own presets in `appearance.presets` (or a tagged `AppearancePresetProviderInterface`), and print `{{ nowo_ui_appearance_style() }}` after `nowo-ui.css`. Hosts that already emit their own variables can keep the names with `token_aliases` (+ `kit_tokens: false` for an identical stylesheet) — see THEMING.md — Appearance SPI.

## To 1.15.0

From **1.14.0** — icon filter actions, pre-paint theme boot, sidebar rail, three Stimulus peers. Everything is opt-in.

```bash
composer update nowo-tech/ui-kit-bundle
php bin/console assets:install
```

- No breaking changes. New options render nothing until used.
- `_burger.html.twig` now honours `expanded: false` (`aria-expanded="false"`); shells rendered with `collapsed: true` get the correct state.
- `nowo-ui-theme.js` also reads `data-nowo-ui-theme-storage` from `<html>` (the toggle attribute still wins) and writes the optional `data-nowo-ui-theme-cookie`.
- To use the theme boot, print your preference attributes on `<html>` and include `@NowoUiKitBundle/partials/_theme_boot.html.twig` first in `<head>` (see USAGE.md — Pre-paint theme boot). Hosts with their own boot script can keep it: the output attributes are the same (`data-theme`, `data-ui-density`, `data-motion`, `data-font-scale`, `data-contrast`).
- The `datatable` peer needs `datatables.net-dt` (^2.3) and `datatables.net-responsive-dt` (^3.0) in the host, plus their stylesheets; the other peers have no extra dependency.
- Moving from a host sidebar that stores `collapsed`: set `data-nowo-ui-sidebar-hidden-value="collapsed"` and keep your storage / cookie names through the `data-nowo-ui-sidebar-*` attributes, so stored state and server rendering keep working.

## To 1.14.0

From **1.13.2** — Symfony >=7.4, host translator settings respected, release-check failures cached, vitest 4.

```bash
composer update nowo-tech/ui-kit-bundle
```

- **Symfony floor raised to `^7.4 || ^8.0`** (was `^6.0 || ^7.0 || ^8.0`; only 7.4 / 8.x were ever tested in CI). Apps on Symfony 6.x or 7.0–7.3 stay on 1.13.x until they upgrade Symfony; Composer will not install this version for them.
- The bundle no longer prepends `framework.translator` (`paths` + `fallbacks: [en]`). Its `NowoUiKitBundle` catalogue is still auto-discovered by FrameworkBundle; your own `fallbacks` order is now respected (`en` is no longer forced first). If you relied on the bundle to turn the translator on (rare: only setups where FrameworkBundle does not enable it by default), set `framework.translator.enabled: true` yourself.
- Release check: failed GitHub fetches are now cached for 5 minutes (`status: error`) instead of being retried on every request. The `/_nowo-ui/release/status` endpoint is still anonymous by default and reveals the installed version — import it behind your admin firewall (see CONFIGURATION.md — Release check).

## To 1.13.2

From **1.13.1** — security: attribute-safe row-action confirm handler, panel path rewrite hardening.

```bash
composer update nowo-tech/ui-kit-bundle
```

- Hosts that override `_row_actions.html.twig` should apply the same `|e('html_attr')` fix to the `confirm()` handler.
- `panel_path_rewrites` entries starting with `//` or containing a backslash now fail config validation.

## To 1.13.1

From **1.13.0** — dependency refresh and repository maintenance.

```bash
composer update nowo-tech/ui-kit-bundle
```

- No breaking changes. No application upgrade steps.

## To 1.13.0

From **1.12.0** — theming tokens; no removed `nowo-ui-*` classes, macro arguments or partial variables.

```bash
composer update nowo-tech/ui-kit-bundle
php bin/console assets:install
php bin/console cache:clear
```

- **Default look is unchanged** in light mode for every stack (same base token values). Visible differences are limited to new interaction states (`:focus-visible` rings, hover on kit-painted buttons / pagination / inactive tabs / plain table rows), dark-mode defaults for warning / info and for `.nowo-ui-input`, and the pagination list laid out as a row.
- **Tailwind hosts:** macros no longer emit palette / radius / divide utilities (`bg-blue-600`, `border-slate-300`, `rounded-md`, `divide-y`, …). If your CSS targeted those classes inside kit output (e.g. `.nowo-ui-btn--primary.bg-blue-600`, `.text-slate-500`, `.hover\:bg-blue-500:hover`, `.divide-slate-200 > * + *`), replace the rules with tokens — see [THEMING.md](THEMING.md). If you relied on them being present in markup (tests, JS selectors), select the `nowo-ui-*` class instead.
- **Theme with tokens instead of selector remaps:** move `--nowo-ui-*` remaps to one `html:root { … }` block (beats the kit's `:root` and `[data-theme="dark"]` regardless of load order) and delete colour overrides of `.nowo-ui-btn*`, `.nowo-ui-input`, `.nowo-ui-table*`, `.nowo-ui-tabs__item*`, `.nowo-ui-pagination__link*`, `.nowo-ui-list*`, `.nowo-ui-badge*`. Remaining host CSS should only be product layout (heights, paddings, spacing).
- **Bootstrap hosts:** nothing changes unless you add `class="nowo-ui-bs-tokens"` on an ancestor; then Bootstrap's components follow the `--nowo-ui-*` tokens.
- `ui.btn('outline')` now maps to `btn-outline-primary` on Bootstrap stacks (was the generic `btn-outline-secondary`) and to the new `.nowo-ui-btn--outline` elsewhere.

## To 1.12.0

From **1.11.0** — new optional features only; no breaking changes.

```bash
composer update nowo-tech/ui-kit-bundle
php bin/console assets:install
php bin/console cache:clear
```

- **No action required.** The new CSS classes (`.nowo-ui-filter-bar*`, `.nowo-ui-data-table*`), partials and script only apply when used.
- `?new=1` create-in-modal deep links: include `_open_modal_on_load.html.twig` (`modal`, `trigger`) and load `js/nowo-ui-open-modal.js` (or call `openModalOnLoad()` from `assets/src/nowo-ui-open-modal-core.ts`). Hosts with their own island (e.g. Beacon `#beacon-open-modal-on-load`) switch to the kit id `nowo-ui-open-modal-on-load`. See [USAGE.md](USAGE.md#open-a-modal-on-load-deep-link).
- Hosts with a local `auto-refresh` Stimulus controller can register the kit peer instead (same identifier, values, targets and region attribute). See [STIMULUS.md](STIMULUS.md#auto-refresh-stimulus-peer-only).
- Filter bars / list tables: add `.nowo-ui-filter-bar` / `.nowo-ui-data-table` and `_filter_actions.html.twig`. See [USAGE.md](USAGE.md#filter-bar-and-data-table-opt-in).

## To 1.11.0

From **1.10.1** — new optional page loader visuals and page transitions; no breaking changes.

```bash
composer update nowo-tech/ui-kit-bundle
php bin/console assets:install
php bin/console cache:clear
```

- **No action required.** `visual: spinner` / `orb` render exactly as before.
- Veil loaders driven by the `page-loader` peer now fade out (`.nowo-ui-page-loader.is-leaving`) instead of disappearing at once; override that rule if you relied on the instant hide.
- Hosts with their own minimal loader / View Transitions CSS can switch to `visual: …` + `_page_transition.html.twig` ([USAGE.md](USAGE.md#page-loader-and-page-transitions)).

## To 1.10.1

From **1.10.0** — `page-loader` Stimulus peer fixes only; no breaking changes.

```bash
composer update nowo-tech/ui-kit-bundle
```

- Rebuild host assets that import the peer (`page_loader_controller.ts`).
- Hosts that worked around the early dismissal (children animated with keyframes only, never transitions) can keep it or go back to transitions.

## To 1.10.0

From **1.9.2** — new optional features only; no breaking changes.

```bash
composer update nowo-tech/ui-kit-bundle
php bin/console assets:install
php bin/console cache:clear
```

- **No action required.** `release_check` is disabled by default and registers only an inert controller.
- To show the installed version + GitHub “newer release?” dialog: `composer require symfony/http-client` (if missing), set `nowo_ui_kit.release_check` (`enabled: true`, `github_repo`, `current_version`), import `@NowoUiKitBundle/Resources/config/routes/release_check.yaml`, include `@NowoUiKitBundle/partials/_release_version.html.twig` and load `js/nowo-ui-confirm.js` + `js/nowo-ui-release.js` (or render with `stimulus: true` and register the `confirm-dialog` + `release-status` peers). See [CONFIGURATION.md](CONFIGURATION.md#release-check).
- Apps keeping copies of `confirm_submit`, `confirm_dialog`, `tabs`, `toast_stack`, `clipboard_copy`, `page_loader` (or a `release_status`) controllers can re-export the vendor peers instead ([STIMULUS.md](STIMULUS.md#stimulus-peers--import-examples)). The peers are supersets of the Beacon/podologiapriego copies: legacy `data-confirm-dialog-close`, `data-tab-id` and `data-toast-stack-target="toast"` keep working.
- Migrating an app-level release checker: map `app.version` → `current_version`, `app.release_github_repo` → `github_repo`, `app.update_check` → `enabled`. The JSON payload keys are unchanged; the route becomes `nowo_ui_kit_release_status` and translations move to the `NowoUiKitBundle` domain (`release.*`). The compare target when up to date is `default_branch` (default `main`).

## To 1.9.2

From **1.9.1** — dependency updates only.

```bash
composer update nowo-tech/ui-kit-bundle
```

No breaking changes. **No application upgrade steps.**

## To 1.9.1

From **1.9.0** — legacy panel redirect runs before the router (priority 64).

```bash
composer update nowo-tech/ui-kit-bundle
php bin/console cache:clear
```

- Remove any host workaround that re-tagged `LegacyPanelPathRedirectSubscriber` above priority 32; the kit now listens at 64.

## To 1.9.0

From **1.8.5** — optional `panel_path_rewrites` (route path rewriting + legacy 301 redirects).

```bash
composer update nowo-tech/ui-kit-bundle
php bin/console cache:clear
```

- **No action required.** The default (`panel_path_rewrites: {}`) registers nothing and behaves exactly like 1.8.5.
- To adopt it, add a prefix map (see [CONFIGURATION.md](CONFIGURATION.md#panel-path-rewrites)). If you already have a host-side routing loader decorator and redirect subscriber doing the same, remove them to avoid rewriting twice.

## To 1.8.5

From **1.8.4** — REQ-CS-008 Igor FrankenPHP worker audit (igor-php require-dev, igor.json, make igor).

```bash
composer update nowo-tech/ui-kit-bundle
php bin/console cache:clear
```

- No application upgrade steps for require-dev Igor tooling (REQ-CS-008). Consumers do not pull `igor-php/igor-php` transitively.

## From 1.8.3 to 1.8.4

**Documentation / QA only** — no Twig/CSS/JS API changes.

```bash
composer update nowo-tech/ui-kit-bundle
```

Optional reading: [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md) if you run FrankenPHP worker without kernel reset.

## From 1.8.2 to 1.8.3

Review the [CHANGELOG](CHANGELOG.md) entry. PHP **8.2+** may now be required.

```bash
composer update nowo-tech/ui-kit-bundle
```

## To 1.8.2

From **1.8.1** — No application upgrade steps.

```bash
composer update nowo-tech/ui-kit-bundle
```

## To 1.8.1

From **1.8.0** — No application upgrade steps. **Demos only:** Hot Reload Bundle `^1.4` (FrankenPHP Mercure/`hot_reload`, `dev`/`test`).

```bash
composer update nowo-tech/ui-kit-bundle
```

## To 1.8.0

From **1.7.x** — **Additive** clipboard/tabs IIFEs and optional Stimulus peer TypeScript sources. No breaking Twig/CSS API changes. Contributors: `pnpm run build` emits **nine** IIFEs (adds `clipboard`, `tabs`). Optional Stimulus peers under `src/Resources/assets/stimulus-peers/`. See [STIMULUS.md](STIMULUS.md).

```bash
composer update nowo-tech/ui-kit-bundle
php bin/console assets:install
```

### New optional scripts

```twig
<script src="{{ asset('js/nowo-ui-clipboard.js', 'nowo_ui_kit') }}" defer></script>
<script src="{{ asset('js/nowo-ui-tabs.js', 'nowo_ui_kit') }}" defer></script>
```

### Stimulus peers (optional)

If the host uses Symfony UX Stimulus, you may import peers from:

`vendor/nowo-tech/ui-kit-bundle/src/Resources/assets/stimulus-peers/`

and **omit** the matching IIFE for that behaviour. See [STIMULUS.md](STIMULUS.md).

### Beacon hosts

Prefer `data-nowo-ui-*` contracts. Legacy Beacon attrs (`data-confirm-dialog-close`, `data-tab-id`, Stimulus value attrs) remain documented for migration; delete duplicated host controllers once re-exported from the vendor peers.

## To 1.6.0

From **1.5.x** — **Row action display** (`icon` / `text` / `icon_text`) and the expanded canonical `_row_actions` API. Default display remains **icon-only** (same as 1.4/1.5).

```bash
composer update nowo-tech/ui-kit-bundle
php bin/console cache:clear
php bin/console assets:install
```

### Row actions display (REQ-UI-001)

Optional additive config:

```yaml
nowo_ui_kit:
    row_actions_display: text   # or icon | icon_text
```

`_row_actions.html.twig` also accepts a per-include `display` argument and the full action hash (`tag`, `method`, `confirm_id`, …). See [ROW_ACTIONS.md](ROW_ACTIONS.md) and [USAGE.md](USAGE.md).

### Default `ui.action` variants

`ui.action(kind)` defaults: `edit` / most kinds → **secondary**; `delete` → danger; `create`/`save` → primary. Pass a third macro arg `variant` to override.

## To 1.5.1

From **1.5.0** — CI/test-only fix for Thinking Orb TypeScript types. No host migration.

```bash
composer update nowo-tech/ui-kit-bundle
```

## To 1.5.0

From **1.4.0** — Adds required Twig Extra (REQ-TWIG-004) and Twig-CS-Fixer. Register TwigExtraBundle if Flex did not.

```bash
composer update nowo-tech/ui-kit-bundle
php bin/console cache:clear
```

### Twig Extra Bundle (REQ-TWIG-004)

Hosts that render this bundle's Twig templates must install:

```bash
composer require twig/extra-bundle twig/string-extra
```

and enable `Twig\Extra\TwigExtraBundle\TwigExtraBundle`. Flex recipes usually register it automatically.

### Twig-CS-Fixer (maintainers)

Package maintainers: `composer twig:lint` / `composer twig:fix` use `.twig-cs-fixer.php` over `src/` (and `templates/` when present).

## From 1.3.x to 1.4.0

**Additive** Twig/CSS/JS API — no breaking changes for existing macros/partials.

```bash
composer update nowo-tech/ui-kit-bundle
php bin/console assets:install
```

Optional:

- Include new scripts (`nowo-ui-toast.js`, `nowo-ui-confirm.js`, `nowo-ui-page-loader.js`, `nowo-ui-theme.js`, `nowo-ui-orb.js`) where you adopt those partials.
- Page loader: pass `visual: 'orb'` to use Thinking Orbs instead of the CSS spinner (default remains `spinner`).
- Shell: include `_width_toggle.html.twig` for full ↔ content main width (persisted in `localStorage`).
- Migrate host/feature UI toward kit partials ([ADOPTION.md](ADOPTION.md), [STIMULUS.md](STIMULUS.md)).
- Contributors: `pnpm run build` now emits **seven** IIFEs (adds `orb`). See [THIRD_PARTY.md](THIRD_PARTY.md) for the Thinking Orbs MIT notice.

`ui.badge('success')` sets a variant; `ui.badge('tailwind')` remains a framework override (unchanged BC).

If `icon_set: ux_icon`, install `symfony/ux-icons` — the Twig call lives in `_icon_ux.html.twig` so other icon sets compile without that package.

## From 1.2.x to 1.3.0

**No breaking Twig/CSS/JS API changes** for application consumers.

```bash
composer update nowo-tech/ui-kit-bundle
php bin/console assets:install
```

Optional:

- Adopt Flex recipe defaults in `config/packages/nowo_ui_kit.yaml` if you previously copied config by hand ([RECIPE.md](RECIPE.md)).
- Contributors: use `make setup-hooks`, Docker `make test` / `make coverage-check`, and FrankenPHP demos (`make -C demo up-symfony8`).

## From 1.2.0 to 1.2.1

No consumer-facing changes. CI-only fix for `pnpm/action-setup` vs `packageManager`.

```bash
composer update nowo-tech/ui-kit-bundle
```

## From 1.1.x to 1.2.0

Additive for consumers of published `js/*.js` paths (same asset URLs).

Maintainers / contributors building from source:

```bash
pnpm install
pnpm run build
```

Hand-edited `src/Resources/public/js/*.js` is no longer the source of truth — edit TypeScript under `src/Resources/assets/src/` and rebuild.

## From 1.0.x to 1.1.0

Additive release — no breaking Twig/CSS API changes.

1. `composer update nowo-tech/ui-kit-bundle`
2. `php bin/console assets:install` (rebuild assets with `pnpm run build` if developing from git)
3. Optionally adopt chrome partials:

```twig
<script src="{{ asset('js/nowo-ui-shell.js', 'nowo_ui_kit') }}" defer></script>
{% include '@NowoUiKitBundle/partials/_shell.html.twig' with { … } %}
```

## From nothing to 1.0.0

Initial release — no upgrade path.

### Install

```bash
composer require nowo-tech/ui-kit-bundle:^1.0
php bin/console assets:install
```

```yaml
# config/packages/nowo_ui_kit.yaml
nowo_ui_kit:
    css_framework: bootstrap5   # or: tailwind | foundation | custom | …
    icon_set: bootstrap-icons
```

Twig namespace: `@NowoUiKitBundle/…`  
Overrides: `templates/bundles/NowoUiKitBundle/<subpath>`  
Asset package: `nowo_ui_kit` (`asset('css/nowo-ui.css', 'nowo_ui_kit')`).

See [USAGE.md](USAGE.md) and [CONFIGURATION.md](CONFIGURATION.md).
