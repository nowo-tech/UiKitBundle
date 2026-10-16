# Theme slots (REQ-UI-003)

The published styling vocabulary of the UiKit page contract: **slots** (classes + attributes), **partials** and
**tokens**, configured once under `nowo_ui_kit.theme` and read by UiKit scaffolds and every UI bundle through
`nowo_ui_class()`, `nowo_ui_attrs()` and `nowo_ui_partial()`. Single source in code:
`Nowo\UiKitBundle\Theme\ThemeSlots` (`SLOTS`, `PARTIALS`). Concepts and scaffolds: [`PAGE_CONTRACT.md`](PAGE_CONTRACT.md).

Adding a slot or partial name is a **minor** change; renaming or removing one is **breaking** ("Template contract" in
[`UPGRADING.md`](UPGRADING.md)).

## Table of contents

- [How a slot resolves](#how-a-slot-resolves)
  - [Dropping the framework defaults](#dropping-the-framework-defaults)
- [Slots](#slots)
- [Attributes](#attributes)
- [Partials](#partials)
- [Tokens](#tokens)
- [Per-bundle exceptions](#per-bundle-exceptions)
- [Configuration reference](#configuration-reference)
- [Contract-test allow-lists](#contract-test-allow-lists)

## How a slot resolves

```twig
<table class="{{ nowo_ui_class('table', page.bundle) }}"{{ nowo_ui_attrs('table', page.bundle) }}>
```

`nowo_ui_class(slot, bundle = null, framework = null)` returns, de-duplicated and in this order:

1. the **semantic** class(es) of the slot (`nowo-ui-*`, always present — `nowo-ui.css` styles them from tokens);
2. the **UiKit framework defaults** for `nowo_ui_kit.css_framework` (or the `framework` argument): `bootstrap5`,
   `bootstrap` and `tabler` share the Bootstrap 5 defaults, `bootstrap4` has its own badge classes and falls back to
   Bootstrap 5 for the rest, `tailwind` only carries layout / spacing / type-scale utilities (never palette or
   radius), `foundation` its own; `custom` and `none` get the semantic class only — unless the theme drops them for
   that slot ([below](#dropping-the-framework-defaults));
3. the **host** classes of `theme.classes.<slot>` — or, when `bundle` is given and `theme.bundles.<bundle>.classes`
   defines the slot, the **bundle exception instead** (it replaces the host classes of that slot).

Example (`css_framework: bootstrap5`, `theme.classes.table: 'data-table'`):
`nowo-ui-table nowo-ui-table--striped table table-hover align-middle data-table`.

The 1.x partials keep their macro classes and append only the host / bundle classes
(`nowo_ui_theme.macroClasses(slot, macroClasses, bundle)`), so their output is unchanged until a theme is configured.

### Dropping the framework defaults

A host whose design system already styles a slot can drop UiKit's framework defaults for it, so no Bootstrap /
Tailwind / Foundation class (e.g. `badge text-bg-success`, `btn btn-primary`, `card`) fights the host classes. The
semantic `nowo-ui-*` class always stays.

```yaml
nowo_ui_kit:
  theme:
    framework_defaults: true                       # default; false = semantic + host classes for every slot
    classes:
      table: 'data-table'                          # string (or list): appended to the defaults, as before
      badge_success: { value: 'acme-badge acme-badge--ok', framework_defaults: false }   # this slot only
      card: { framework_defaults: false }          # drop the defaults, no host class
    bundles:
      NowoHttpLogBundle:
        framework_defaults: false                  # every slot of this bundle (unset = inherit)
        classes: { table: { value: 'dense', framework_defaults: true } }
```

The most specific setting wins: bundle slot entry > bundle `framework_defaults` > host slot entry > host
`framework_defaults`. With `css_framework: bootstrap5`:

| Config | `nowo_ui_class('badge_success')` |
| --- | --- |
| none | `nowo-ui-badge nowo-ui-badge--success badge text-bg-success ms-2` |
| `classes.badge_success: 'acme-badge'` | `nowo-ui-badge nowo-ui-badge--success badge text-bg-success ms-2 acme-badge` |
| `classes.badge_success: { value: 'acme-badge', framework_defaults: false }` | `nowo-ui-badge nowo-ui-badge--success acme-badge` |
| `framework_defaults: false` | `nowo-ui-badge nowo-ui-badge--success` |

The 1.x partials honour it too: when the defaults are dropped for a slot, only the `nowo-ui-*` and `is-*` classes of
the macro output are kept (`nowo_ui_theme.macroClasses()`); the `ui.*` macros called directly are not affected.
`nowo_ui_theme.frameworkDefaults(slot, bundle)` tells whether they apply.

## Slots

`—` = no default for that framework (semantic class only).

| Slot | Semantic (always) | bootstrap5 / tabler | bootstrap4 | tailwind | foundation |
| --- | --- | --- | --- | --- | --- |
| `document` | `nowo-ui-document` | — | — | — | — |
| `body` | `nowo-ui-body` | — | — | — | — |
| `page` | `nowo-ui-page` | — | — | — | — |
| `navigation` | `nowo-ui-navigation` | — | — | — | — |
| `content` | `nowo-ui-content` | — | — | — | — |
| `shell` | `nowo-ui-shell` | — | — | — | — |
| `burger` | `nowo-ui-burger nowo-ui-btn nowo-ui-btn--secondary nowo-ui-btn--sm` | `btn btn-outline-secondary btn-sm` | `btn btn-outline-secondary btn-sm` | `inline-flex items-center gap-1 font-medium px-2 py-1 text-sm border` | `button secondary tiny` |
| `breadcrumbs` | `nowo-ui-breadcrumbs` | — | — | — | — |
| `page_header` | `nowo-ui-page-header` | `d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3` | `d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3` | `flex flex-wrap items-start justify-between gap-2 mb-3` | `grid-x grid-margin-x align-middle mb-3` |
| `page_title` | `nowo-ui-page-header__title` | `h3 mb-1` | `h3 mb-1` | `text-xl font-semibold mb-1` | — |
| `page_intro` | `nowo-ui-page-header__intro` | `mb-0` | `mb-0` | `text-sm` | — |
| `toolbar` | `nowo-ui-toolbar` | `d-flex flex-wrap align-items-center gap-1 mb-2` | `d-flex flex-wrap align-items-center gap-1 mb-2` | `flex flex-wrap items-center gap-1 mb-2` | `grid-x grid-margin-x align-middle mb-2` |
| `btn` | `nowo-ui-btn` | `btn` | `btn` | `inline-flex items-center gap-1 font-medium px-3 py-1.5 text-sm` | `button` |
| `btn_primary` | `nowo-ui-btn nowo-ui-btn--primary` | `btn btn-primary` | `btn btn-primary` | `inline-flex items-center gap-1 font-medium px-3 py-1.5 text-sm` | `button primary` |
| `btn_secondary` | `nowo-ui-btn nowo-ui-btn--secondary` | `btn btn-outline-secondary` | `btn btn-outline-secondary` | `inline-flex items-center gap-1 font-medium px-3 py-1.5 text-sm border` | `button secondary` |
| `btn_danger` | `nowo-ui-btn nowo-ui-btn--danger` | `btn btn-outline-danger` | `btn btn-outline-danger` | `inline-flex items-center gap-1 font-medium px-3 py-1.5 text-sm border` | `button alert` |
| `btn_sm` | `nowo-ui-btn--sm` | `btn-sm` | `btn-sm` | — | `tiny` |
| `table_wrap` | `nowo-ui-table-wrap` | `table-responsive` | `table-responsive` | `overflow-x-auto` | — |
| `table` | `nowo-ui-table nowo-ui-table--striped` | `table table-hover align-middle` | `table table-hover align-middle` | `min-w-full text-sm text-left` | `stack table` |
| `table_head` | `nowo-ui-table__head` | — | — | — | — |
| `table_row` | `nowo-ui-table__row` | — | — | — | — |
| `table_cell` | `nowo-ui-table__cell` | — | — | — | — |
| `row_actions` | `nowo-ui-row-actions` | `d-flex gap-1` | `d-flex gap-1` | `flex gap-1` | `button-group` |
| `action` | `nowo-ui-action` | — | — | — | — |
| `badge` | `nowo-ui-badge` | `badge text-bg-light border ms-2` | `badge badge-light border ml-2` | `ml-2 inline-flex border px-2 py-0.5 text-xs` | `badge secondary` |
| `badge_success` | `nowo-ui-badge nowo-ui-badge--success` | `badge text-bg-success ms-2` | `badge badge-success ml-2` | `ml-2 inline-flex border px-2 py-0.5 text-xs` | `badge success` |
| `badge_warning` | `nowo-ui-badge nowo-ui-badge--warning` | `badge text-bg-warning ms-2` | `badge badge-warning ml-2` | `ml-2 inline-flex border px-2 py-0.5 text-xs` | `badge warning` |
| `badge_danger` | `nowo-ui-badge nowo-ui-badge--danger` | `badge text-bg-danger ms-2` | `badge badge-danger ml-2` | `ml-2 inline-flex border px-2 py-0.5 text-xs` | `badge alert` |
| `badge_info` | `nowo-ui-badge nowo-ui-badge--info` | `badge text-bg-info ms-2` | `badge badge-info ml-2` | `ml-2 inline-flex border px-2 py-0.5 text-xs` | `badge secondary` |
| `badge_neutral` | `nowo-ui-badge nowo-ui-badge--neutral` | `badge text-bg-light border ms-2` | `badge badge-light border ml-2` | `ml-2 inline-flex border px-2 py-0.5 text-xs` | `badge secondary` |
| `filters` | `nowo-ui-filters nowo-ui-search` | `d-flex flex-wrap gap-2 mb-3` | `d-flex flex-wrap gap-2 mb-3` | `flex flex-wrap gap-2 mb-4` | `grid-x grid-margin-x mb-3` |
| `filters_actions` | `nowo-ui-filters__actions` | `d-flex flex-wrap align-items-end gap-2` | `d-flex flex-wrap align-items-end gap-2` | `flex flex-wrap items-end gap-2` | — |
| `input` | `nowo-ui-input` | `form-control` | `form-control` | `block w-full max-w-xs border px-3 py-1.5 text-sm` | — |
| `pagination` | `nowo-ui-pagination` | `d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3` | `d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3` | `flex flex-wrap items-center justify-between gap-2 mt-3` | — |
| `pagination_link` | `nowo-ui-pagination__link` | `page-link` | `page-link` | — | — |
| `pagination_active` | `nowo-ui-pagination__link is-active` | `page-link` | `page-link` | — | — |
| `pagination_info` | `nowo-ui-pagination__info` | — | — | — | — |
| `empty` | `nowo-ui-empty nowo-ui-muted` | `text-muted` | `text-muted` | — | `text-secondary` |
| `flashes` | `nowo-ui-flashes` | — | — | — | — |
| `flash` | `nowo-ui-flash` | — | — | — | — |
| `flash_success` | `nowo-ui-flash nowo-ui-flash--success` | `alert alert-success` | `alert alert-success` | `px-4 py-3 text-sm mb-2 border` | `callout success` |
| `flash_error` | `nowo-ui-flash nowo-ui-flash--error` | `alert alert-danger` | `alert alert-danger` | `px-4 py-3 text-sm mb-2 border` | `callout alert` |
| `flash_warning` | `nowo-ui-flash nowo-ui-flash--warning` | `alert alert-warning` | `alert alert-warning` | `px-4 py-3 text-sm mb-2 border` | `callout warning` |
| `flash_info` | `nowo-ui-flash nowo-ui-flash--info` | `alert alert-info` | `alert alert-info` | `px-4 py-3 text-sm mb-2 border` | `callout alert` |
| `toasts` | `nowo-ui-toast-stack` | — | — | — | — |
| `card` | `nowo-ui-card` | `card` | `card` | `border` | `callout` |
| `card_header` | `nowo-ui-card__header` | `card-header d-flex flex-wrap align-items-start justify-content-between gap-2` | `card-header d-flex flex-wrap align-items-start justify-content-between gap-2` | `flex flex-wrap items-start justify-between gap-2 border-b px-4 py-3` | — |
| `card_body` | `nowo-ui-card__body` | `card-body` | `card-body` | `px-4 py-3` | — |
| `card_footer` | `nowo-ui-card__footer` | `card-footer` | `card-footer` | `border-t px-4 py-3` | — |
| `cards` | `nowo-ui-cards` | — | — | — | — |
| `section` | `nowo-ui-section` | `mb-4` | `mb-4` | `mb-6` | — |
| `detail_list` | `nowo-ui-detail-list` | — | — | — | — |
| `kpis` | `nowo-ui-kpis` | — | — | — | — |
| `kpi` | `nowo-ui-kpi` | `card` | `card` | `border` | `callout` |
| `tabs` | `nowo-ui-tabs` | `nav nav-tabs mb-3` | `nav nav-tabs mb-3` | `flex flex-wrap gap-2 border-b mb-3` | `tabs` |
| `tab` | `nowo-ui-tabs__item` | `nav-link` | `nav-link` | `px-3 py-2 text-sm no-underline` | `tabs-title` |
| `tab_active` | `nowo-ui-tabs__item nowo-ui-tabs__item--active` | `nav-link active` | `nav-link active` | `px-3 py-2 text-sm no-underline border-b-2` | `tabs-title is-active` |
| `modal` | `nowo-ui-modal` | — | — | — | — |
| `confirm` | `nowo-ui-confirm` | — | — | — | — |
| `form` | `nowo-ui-form` | — | — | — | — |
| `form_actions` | `nowo-ui-form__actions` | `d-flex flex-wrap gap-2 mt-3` | `d-flex flex-wrap gap-2 mt-3` | `flex flex-wrap gap-2 mt-4` | — |
| `danger_zone` | `nowo-ui-danger-zone` | `mt-4` | `mt-4` | `mt-6` | — |
| `wizard_steps` | `nowo-ui-wizard-steps` | — | — | — | — |
| `wizard_step` | `nowo-ui-wizard-steps__item` | — | — | — | — |
| `wizard_step_active` | `nowo-ui-wizard-steps__item nowo-ui-wizard-steps__item--active` | — | — | — | — |
| `auth_panel` | `nowo-ui-auth-panel` | `card` | `card` | `border` | `callout` |

Slot groups: document / layout (`document` … `breadcrumbs`), page header (`page_header` … `toolbar`), buttons (`btn`
… `btn_sm`; variants are complete button classes, `btn_sm` a size modifier), tables (`table_wrap` … `action`),
badges (Bootstrap variants use `text-bg-*`, Bootstrap 5.2+, which sets text and background together so the colour
pair never mixes with the semantic `nowo-ui-badge--*` palette; on 5.0 / 5.1 the semantic palette paints the badge;
Bootstrap 4 / Foundation variant classes set both colours too), filters / inputs, pagination, feedback (`empty`, flashes, `toasts`), surfaces (`card` … `modal`,
`confirm`), forms (`form`, `form_actions`, `danger_zone`), wizard / auth.

## Attributes

`theme.attributes.<slot>` adds attributes on the slot root, printed by `nowo_ui_attrs(slot, bundle, extra = {})`
with a leading space and escaped values:

```yaml
nowo_ui_kit:
  theme:
    attributes:
      toasts: { data-controller: 'toast-stack' }
      table: { data-turbo: false, data-density: 'compact' }   # false / null = omitted, true = bare attribute
```

Names must match `[a-zA-Z_:][a-zA-Z0-9_:.-]*`; `on*` event handlers are rejected (CSP). Merge order: host, then bundle
exception, then the call-site `extra`.

## Partials

`theme.partials.<name>` replaces one UiKit partial for every bundle; bundles and scaffolds include partials through
`nowo_ui_partial('<name>', bundle)` (never by path, never a local copy). An override receives the same variables
as the UiKit partial (documented in the header comment of each template) and should keep accepting them.

| Name | Default template | Main inputs (2.0) | Used by |
| --- | --- | --- | --- |
| `action` | `@NowoUiKitBundle/partials/_action.html.twig` | `action` (`View\Action`), `context` (`toolbar` \| `row`), `display`, `bundle` | header / form / empty-state actions |
| `breadcrumbs` | `partials/_breadcrumbs.html.twig` | `header` (`PageHeader`), `bundle` | page header |
| `burger` | `partials/_burger.html.twig` | `controls`, `expanded`, `label`, `root_class`, `label_attr_names`, `attr`, `bundle` | shell |
| `card` | `partials/_card.html.twig` | `title` / `intro` (`Label` or string), `body`, `footer`, `toolbar`, `actions` (list of `View\Action`), `id`, `attr`, `cards` (list of card hashes → `cards` grid), `bundle`; slots `card`, `card_header`, `card_body`, `card_footer`, `cards` | bundle cards, dashboard cards |
| `confirm` | `partials/_confirm.html.twig` | `id`, `confirm` (`View\Confirm`), `action`, `action_href`, `bundle` | actions with a `Confirm` |
| `empty` | `partials/_empty.html.twig` | `empty` (`View\EmptyState`) or `message`, `bundle` | list scaffold |
| `filters` | `partials/_filters.html.twig` | `action`, `method`, `q`, `placeholder`, `actions`, `clear_href`, `collapsible` | bundle filter bars |
| `flashes` | `partials/_flashes.html.twig` | `flashes` (default `app.flashes`), `trans_domain`, `bundle` | layouts (`nowo_ui_flashes`) |
| `modal_shell` | `partials/_modal_shell.html.twig` | `id`, `title`, `body`, `footer` | modals |
| `page_header` | `partials/_page_header.html.twig` | `header` (`View\PageHeader`) or `title` / `intro` / `toolbar`, `bundle` | every scaffold (`nowo_ui_page_header`) |
| `pagination` | `partials/_pagination.html.twig` | `pagination` (`View\Pagination` or 1.x array), `item_count`, `bundle` | list scaffold |
| `row_actions` | `partials/_row_actions.html.twig` | `actions` (list of `View\Action` or 1.x arrays), `bundle` | list scaffold |
| `shell` | `partials/_shell.html.twig` | `brand_html`, `aside_items`, `main`, `root_class`, `header_class`, `attr`, `burger_root_class`, `bundle` | host shells |
| `tabs` | `partials/_tabs.html.twig` | `items`, `aria_label` | bundle tab bars |
| `toasts` | `partials/_toasts.html.twig` | `flashes`, `trans_domain`, `bundle` | layouts / hosts |

## Tokens

`theme.tokens` (light, on `:root`) and `theme.tokens_dark` (on `:root[data-theme="dark"]`) are emitted by
`nowo_ui_theme_tokens()` as `--nowo-ui-<name>`. Keys are written without the prefix (`primary`) or with it
(`--nowo-ui-primary`); names are `[a-z0-9-]`, values may not contain `{ } ; < >`. Base tokens:

| Token | Purpose |
| --- | --- |
| `primary`, `primary-contrast` | accent (primary buttons, active tab / page, links, focus) and text on it |
| `surface`, `surface-muted` | cards, inputs, menus / shell background, hover rows, stripes |
| `text`, `muted` | body and secondary text |
| `border`, `border-strong` | dividers / surfaces and control borders |
| `radius` | every control / surface radius |
| `danger`, `danger-text`, `danger-bg`, `danger-border`, `danger-text-strong` | danger buttons, error flashes / badges |
| `success-bg`, `success-border`, `success-text` | success flashes / badges |
| `warning-bg`, `warning-border`, `warning-text` | warning flashes / badges |
| `info-bg`, `info-border`, `info-text` | info flashes / badges |

Component tokens (`btn-*`, `table-*`, `tabs-*`, `pagination-*`, `focus-ring-*`, `font-*`, …) and their defaults are
listed in [`THEMING.md`](THEMING.md); every one of them can be set through `theme.tokens` as well.

When tokens are configured, `<html data-nowo-ui-host-tokens>` (UiKit document layout, or added by the host layout)
drops UiKit's own palette to zero specificity, so the host values win without `!important`.

## Per-bundle exceptions

`theme.bundles.<Bundle>` (bundle class short name, e.g. `NowoHttpLogBundle`) takes the same keys as the theme and is
selected by the `bundle` argument / the view model's `bundle`:

| Key | Semantics |
| --- | --- |
| `classes` | **replace** the host classes of that slot (semantic + framework defaults stay); `''` removes the host classes for the bundle; `{value, framework_defaults}` also sets the framework defaults of the slot for the bundle |
| `framework_defaults` | `false` / `true` overrides `theme.framework_defaults` for the bundle (unset = inherit) |
| `attributes` | **merge** over the host attributes of that slot (`false` / `null` removes a host attribute) |
| `partials` | **replace** the host (or UiKit) partial for that bundle |
| `tokens`, `tokens_dark` | **scoped** to `[data-nowo-ui-bundle="<Bundle>"]` (the layouts put that attribute on `<main>` from `nowo_ui_bundle`) |

```yaml
nowo_ui_kit:
  theme:
    classes: { table: 'data-table' }
    bundles:
      NowoHttpLogBundle:
        classes: { table: 'data-table data-table--dense' }   # replaces 'data-table' for HttpLog pages only
        attributes: { table: { data-density: 'compact' } }
        tokens: { radius: '0.25rem' }                        # [data-nowo-ui-bundle="NowoHttpLogBundle"] { --nowo-ui-radius: … }
```

Deprecated per-bundle chrome maps (`AdminChrome`, `PanelChrome`, `DashboardUiConfig`…) are bridged into
`theme.bundles.<Bundle>` for one minor version ([`MIGRATING_BUNDLES.md`](MIGRATING_BUNDLES.md)).

## Configuration reference

```yaml
nowo_ui_kit:
  theme:
    framework_defaults: true | false
    classes:      { <slot>: 'classes' | ['class', …] | { value: 'classes' | ['class', …], framework_defaults: bool } }
    attributes:   { <slot>: { <name>: string | int | float | bool | null } }
    partials:     { <partial>: '<template>' }
    tokens:       { <token>: '<css value>' }
    tokens_dark:  { <token>: '<css value>' }
    bundles:
      <BundleShortName>: { framework_defaults: bool, classes: …, attributes: …, partials: …, tokens: …, tokens_dark: … }
```

## Contract-test allow-lists

`Nowo\UiKitBundle\Test\FrameworkClasses` derives the per-framework allow-list of the contract test from this table:
the slot defaults of the rendered `css_framework`, the classes the `ui.btn` macro adds for the `outline` / `link`
variants (Bootstrap `btn-outline-primary`, `btn-link`; Tailwind `underline-offset-2`, `px-0`) and UiKit's own helpers
(`visually-hidden`, state classes, the 1.x pagination list `pagination` / `page-item` / `page-link`). Any other class
that looks like a Bootstrap / Tabler / Tailwind / Foundation class fails `testPageUsesFrameworkClassesOnlyThroughThemeSlots`.
Change the defaults here and the allow-lists follow (the allow-list does not depend on `framework_defaults`: dropping
defaults only removes classes).
