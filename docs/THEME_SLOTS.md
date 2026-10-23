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
- [Per-page-type variation](#per-page-type-variation)
- [Per-bundle exceptions](#per-bundle-exceptions)
- [Configuration reference](#configuration-reference)
- [Contract-test allow-lists](#contract-test-allow-lists)

## How a slot resolves

```twig
<table class="{{ nowo_ui_class('table', page.bundle) }}"{{ nowo_ui_attrs('table', page.bundle) }}>
```

`nowo_ui_class(slot, bundle = null, framework = null, page = null, defaults = true)` returns, de-duplicated and in this
order (`defaults: false`, 2.3: without step 2 — host-only mode):

1. the **semantic** class(es) of the slot (`nowo-ui-*`, always present — `nowo-ui.css` styles them from tokens);
2. the **UiKit framework defaults** for `nowo_ui_kit.css_framework` (or the `framework` argument): `bootstrap5`,
   `bootstrap` and `tabler` share the Bootstrap 5 defaults, `bootstrap4` has its own badge classes and falls back to
   Bootstrap 5 for the rest, `tailwind` only carries layout / spacing / type-scale utilities (never palette or
   radius), `foundation` its own; `custom` and `none` get the semantic class only — unless the theme drops them for
   that slot ([below](#dropping-the-framework-defaults));
3. the **host** classes of `theme.classes.<slot>` — or, on a page type whose `theme.pages.<type>.classes` defines
   the slot, the **page-type classes instead**, or, when `bundle` is given and `theme.bundles.<bundle>.classes`
   defines the slot, the **bundle exception instead** (it replaces both). `page` defaults to the
   `nowo_ui_page_type` Twig variable every scaffold sets ([below](#per-page-type-variation)).

Example (`css_framework: bootstrap5`, `theme.classes.table: 'data-table'`):
`nowo-ui-table nowo-ui-table--striped table table-hover align-middle data-table`.

The 1.x partials keep their macro classes and append only the host / bundle classes
(`nowo_ui_theme.macroClasses(slot, macroClasses, bundle)`), so their output is unchanged until a theme is configured.

Class values may contain any printable character — Tailwind arbitrary and child variants such as `[&>li]:mt-1`,
`content-['·']` or `data-[state=open]:block` included. UiKit prints slot classes HTML-escaped inside a quoted `class`
attribute (`"`, `'`, `<`, `>` and `&` become entities), so a value cannot leave its attribute; only control characters
(other than whitespace) and invalid UTF-8 are rejected. Host templates print them the same way:
`class="{{ nowo_ui_class('…') }}"`, never through `|raw`.

### Dropping the framework defaults

A host whose design system already styles a slot can drop UiKit's framework defaults for it, so no Bootstrap /
Tailwind / Foundation class (e.g. `badge bg-success-subtle`, `btn btn-primary`, `card`) fights the host classes. The
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

The most specific setting wins: bundle slot entry > bundle `framework_defaults` > page-type slot entry > page-type
`framework_defaults` > host slot entry > host `framework_defaults`. With `css_framework: bootstrap5`:

| Config | `nowo_ui_class('badge_success')` |
| --- | --- |
| none | `nowo-ui-badge nowo-ui-badge--success badge bg-success-subtle text-success-emphasis border border-success-subtle` |
| `classes.badge_success: 'acme-badge'` | `nowo-ui-badge nowo-ui-badge--success badge bg-success-subtle text-success-emphasis border border-success-subtle acme-badge` |
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
| `breadcrumb_link` | `nowo-ui-breadcrumbs__link` | — | — | — | — |
| `breadcrumb_separator` | `nowo-ui-breadcrumbs__separator` | — | — | — | — |
| `page_header` | `nowo-ui-page-header` | `d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3` | `d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3` | `flex flex-wrap items-start justify-between gap-2 mb-3` | `grid-x grid-margin-x align-middle mb-3` |
| `page_title` | `nowo-ui-page-header__title` | `h3 mb-1` | `h3 mb-1` | `text-xl font-semibold mb-1` | — |
| `page_intro` | `nowo-ui-page-header__intro` | `mb-0` | `mb-0` | `text-sm` | — |
| `toolbar` | `nowo-ui-toolbar` | `d-flex flex-wrap align-items-center gap-1 mb-2` | `d-flex flex-wrap align-items-center gap-1 mb-2` | `flex flex-wrap items-center gap-1 mb-2` | `grid-x grid-margin-x align-middle mb-2` |
| `btn` | `nowo-ui-btn` | `btn` | `btn` | `inline-flex items-center gap-1 font-medium px-3 py-1.5 text-sm` | `button` |
| `btn_primary` | `nowo-ui-btn nowo-ui-btn--primary` | `btn btn-primary` | `btn btn-primary` | `inline-flex items-center gap-1 font-medium px-3 py-1.5 text-sm` | `button primary` |
| `btn_secondary` | `nowo-ui-btn nowo-ui-btn--secondary` | `btn btn-outline-secondary` | `btn btn-outline-secondary` | `inline-flex items-center gap-1 font-medium px-3 py-1.5 text-sm border` | `button secondary` |
| `btn_danger` | `nowo-ui-btn nowo-ui-btn--danger` | `btn btn-outline-danger` | `btn btn-outline-danger` | `inline-flex items-center gap-1 font-medium px-3 py-1.5 text-sm border` | `button alert` |
| `btn_link` | `nowo-ui-btn nowo-ui-btn--link` | `btn btn-link` | `btn btn-link` | `inline-flex items-center gap-1 font-medium px-3 py-1.5 text-sm underline-offset-2 px-0` | `button secondary` |
| `btn_danger_solid` | `nowo-ui-btn nowo-ui-btn--danger nowo-ui-btn--solid` | `btn btn-danger` | `btn btn-danger` | `inline-flex items-center gap-1 font-medium px-3 py-1.5 text-sm` | `button alert` |
| `btn_sm` | `nowo-ui-btn--sm` | `btn-sm` | `btn-sm` | — | `tiny` |
| `back_link` | `nowo-ui-back-link` | `btn btn-link btn-sm px-0 text-decoration-none` | `btn btn-link btn-sm px-0 text-decoration-none` | `inline-flex items-center gap-1 text-sm font-medium` | `button clear tiny` |
| `btn_icon` | `nowo-ui-btn--icon` | — | — | — | — |
| `table_wrap` | `nowo-ui-table-wrap` | `table-responsive` | `table-responsive` | `overflow-x-auto` | — |
| `table` | `nowo-ui-table nowo-ui-table--striped` | `table table-striped table-hover align-middle` | `table table-striped table-hover align-middle` | `min-w-full text-sm text-left` | `stack table` |
| `table_caption` | `nowo-ui-table__caption` | — | — | — | — |
| `table_head` | `nowo-ui-table__head` | — | — | — | — |
| `table_head_row` | `nowo-ui-table__head-row` | — | — | — | — |
| `table_head_cell` | `nowo-ui-table__head-cell` | — | — | — | — |
| `sortable_header` | `nowo-ui-table__head-cell--sortable` | — | — | — | — |
| `sort_link` | `nowo-ui-sort` | — | — | — | — |
| `table_actions_head` | `nowo-ui-table__actions` | — | — | — | — |
| `table_actions_cell` | `nowo-ui-table__actions` | — | — | — | — |
| `table_row` | `nowo-ui-table__row` | — | — | — | — |
| `table_cell` | `nowo-ui-table__cell` | — | — | — | — |
| `table_cell_muted` | `nowo-ui-table__cell--muted` | `text-muted` | `text-muted` | — | `text-secondary` |
| `row_actions` | `nowo-ui-row-actions` | `d-flex gap-1` | `d-flex gap-1` | `flex gap-1` | `button-group` |
| `action` | `nowo-ui-action` | — | — | — | — |
| `row_action_disabled` | `nowo-ui-action--disabled` | — | — | — | — |
| `action_primary` | `nowo-ui-btn--primary` | — | — | — | — |
| `action_secondary` | `nowo-ui-btn--secondary` | — | — | — | — |
| `action_danger` | `nowo-ui-btn--danger` | — | — | — | — |
| `action_neutral` | `nowo-ui-btn--secondary` | — | — | — | — |
| `badge` | `nowo-ui-badge` | `badge bg-body-tertiary text-body-emphasis border` | `badge badge-light border` | `inline-flex border px-2 py-0.5 text-xs` | `badge secondary` |
| `badge_primary` | `nowo-ui-badge nowo-ui-badge--primary` | `badge bg-primary-subtle text-primary-emphasis border border-primary-subtle` | `badge badge-primary` | `inline-flex border px-2 py-0.5 text-xs` | `badge primary` |
| `badge_success` | `nowo-ui-badge nowo-ui-badge--success` | `badge bg-success-subtle text-success-emphasis border border-success-subtle` | `badge badge-success` | `inline-flex border px-2 py-0.5 text-xs` | `badge success` |
| `badge_warning` | `nowo-ui-badge nowo-ui-badge--warning` | `badge bg-warning-subtle text-warning-emphasis border border-warning-subtle` | `badge badge-warning` | `inline-flex border px-2 py-0.5 text-xs` | `badge warning` |
| `badge_danger` | `nowo-ui-badge nowo-ui-badge--danger` | `badge bg-danger-subtle text-danger-emphasis border border-danger-subtle` | `badge badge-danger` | `inline-flex border px-2 py-0.5 text-xs` | `badge alert` |
| `badge_info` | `nowo-ui-badge nowo-ui-badge--info` | `badge bg-primary-subtle text-primary-emphasis border border-primary-subtle` | `badge badge-info` | `inline-flex border px-2 py-0.5 text-xs` | `badge primary` |
| `badge_neutral` | `nowo-ui-badge nowo-ui-badge--neutral` | `badge bg-body-tertiary text-body-secondary border` | `badge badge-light border` | `inline-flex border px-2 py-0.5 text-xs` | `badge secondary` |
| `filters` | `nowo-ui-filters nowo-ui-search` | `d-grid gap-3 mb-3` | `d-grid gap-3 mb-3` | `grid gap-3 mb-4` | — |
| `filters_actions` | `nowo-ui-filters__actions` | `d-flex flex-wrap align-items-end gap-2` | `d-flex flex-wrap align-items-end gap-2` | `flex flex-wrap items-end gap-2` | — |
| `filters_field` | `nowo-ui-filters__field` | — | — | — | — |
| `input` | `nowo-ui-input` | `form-control` | `form-control` | `block w-full max-w-xs border px-3 py-1.5 text-sm` | — |
| `check` | `nowo-ui-check` | `form-check` | `form-check` | `inline-flex items-center gap-2` | — |
| `check_input` | `nowo-ui-check__input` | `form-check-input` | `form-check-input` | — | — |
| `input_invalid` | `nowo-ui-input--invalid` | `is-invalid` | `is-invalid` | — | — |
| `form_error` | `nowo-ui-form-error` | `invalid-feedback d-block` | `invalid-feedback d-block` | `text-sm mt-1` | — |
| `form_errors` | `nowo-ui-form-errors` | — | — | — | — |
| `form_row` | `` | — | — | — | — |
| `form_row_check` | `` | — | — | — | — |
| `form_label` | `` | — | — | — | — |
| `input_wide` | `nowo-ui-input nowo-ui-input--wide` | `form-control` | `form-control` | `block w-full max-w-xl border px-3 py-1.5 text-sm` | — |
| `input_full` | `nowo-ui-input nowo-ui-input--full` | `form-control` | `form-control` | `block w-full border px-3 py-1.5 text-sm` | — |
| `pagination` | `nowo-ui-pagination` | `d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3` | `d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3` | `flex flex-wrap items-center justify-between gap-2 mt-3` | — |
| `pagination_link` | `nowo-ui-pagination__link` | `page-link` | `page-link` | — | — |
| `pagination_active` | `nowo-ui-pagination__link is-active` | `page-link` | `page-link` | — | — |
| `pagination_info` | `nowo-ui-pagination__info` | — | — | — | — |
| `pagination_compact` | `nowo-ui-pagination nowo-ui-pagination--compact` | `d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3` | `d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3` | `flex flex-wrap items-center justify-between gap-2 mt-3` | — |
| `pagination_page` | `nowo-ui-pagination__page` | — | — | — | — |
| `pagination_per_page` | `nowo-ui-pagination__per-page-form` | — | — | — | — |
| `pagination_select` | `nowo-ui-pagination__select` | `form-select form-select-sm` | `form-select form-select-sm` | `border px-2 py-1 text-sm` | — |
| `code` | `nowo-ui-code` | — | `text-body` | `font-mono text-sm` | — |
| `code_block` | `nowo-ui-code-block` | `border p-3` | `border p-3` | `font-mono text-sm p-3 border` | — |
| `muted` | `nowo-ui-muted` | `text-muted` | `text-muted` | — | `text-secondary` |
| `empty` | `nowo-ui-empty nowo-ui-muted` | `text-muted` | `text-muted` | — | `text-secondary` |
| `flashes` | `nowo-ui-flashes` | — | — | — | — |
| `flash` | `nowo-ui-flash` | — | — | — | — |
| `flash_success` | `nowo-ui-flash nowo-ui-flash--success` | `alert alert-success` | `alert alert-success` | `px-4 py-3 text-sm mb-2 border` | `callout success` |
| `flash_error` | `nowo-ui-flash nowo-ui-flash--error` | `alert alert-danger` | `alert alert-danger` | `px-4 py-3 text-sm mb-2 border` | `callout alert` |
| `flash_warning` | `nowo-ui-flash nowo-ui-flash--warning` | `alert alert-warning` | `alert alert-warning` | `px-4 py-3 text-sm mb-2 border` | `callout warning` |
| `flash_info` | `nowo-ui-flash nowo-ui-flash--info` | `alert alert-info` | `alert alert-info` | `px-4 py-3 text-sm mb-2 border` | `callout alert` |
| `toasts` | `nowo-ui-toast-stack` | — | — | — | — |
| `progress` | `nowo-ui-progress-field` | — | — | — | — |
| `progress_meter` | `nowo-ui-progress-meter` | — | — | — | — |
| `card` | `nowo-ui-card` | `card` | `card` | `border` | `callout` |
| `card_header` | `nowo-ui-card__header` | `card-header d-flex flex-wrap align-items-start justify-content-between gap-2` | `card-header d-flex flex-wrap align-items-start justify-content-between gap-2` | `flex flex-wrap items-start justify-between gap-2 border-b px-4 py-3` | — |
| `card_body` | `nowo-ui-card__body` | `card-body` | `card-body` | `px-4 py-3` | — |
| `card_footer` | `nowo-ui-card__footer` | `card-footer` | `card-footer` | `border-t px-4 py-3` | — |
| `card_title` | `nowo-ui-card__title` | — | — | — | — |
| `card_intro` | `nowo-ui-card__intro` | — | — | — | — |
| `cards` | `nowo-ui-cards` | — | — | — | — |
| `section` | `nowo-ui-section` | `mb-4` | `mb-4` | `mb-6` | — |
| `detail_list` | `nowo-ui-detail-list` | — | — | — | — |
| `detail_item` | `nowo-ui-detail-list__item` | — | — | — | — |
| `detail_term` | `nowo-ui-detail-list__term` | — | — | — | — |
| `detail_value` | `nowo-ui-detail-list__value` | — | — | — | — |
| `detail_list_meta` | `nowo-ui-detail-list--meta` | — | — | — | — |
| `kpis` | `nowo-ui-kpis` | — | — | — | — |
| `kpi` | `nowo-ui-kpi` | `card` | `card` | `border` | `callout` |
| `kpi_label` | `nowo-ui-kpi__label` | — | — | — | — |
| `kpi_value` | `nowo-ui-kpi__value` | — | — | — | — |
| `kpi_hint` | `nowo-ui-kpi__hint` | — | — | — | — |
| `kpi_link` | `nowo-ui-kpi--link` | — | — | — | — |
| `tabs` | `nowo-ui-tabs` | `nav nav-tabs mb-3` | `nav nav-tabs mb-3` | `flex flex-wrap gap-2 border-b mb-3` | `tabs` |
| `tab` | `nowo-ui-tabs__item` | `nav-link` | `nav-link` | `px-3 py-2 text-sm no-underline` | `tabs-title` |
| `tab_active` | `nowo-ui-tabs__item nowo-ui-tabs__item--active` | `nav-link active` | `nav-link active` | `px-3 py-2 text-sm no-underline border-b-2` | `tabs-title is-active` |
| `tabs_secondary` | `nowo-ui-tabs nowo-ui-tabs--secondary` | `nav nav-pills mb-3` | `nav nav-pills mb-3` | `flex flex-wrap gap-2 mb-3` | `menu` |
| `locale_tabs_nav` | `nowo-ui-tabs nowo-ui-locale-tabs__nav` | `nav nav-tabs` | `nav nav-tabs` | `flex flex-wrap gap-2 border-b` | `tabs` |
| `tab_panel` | `nowo-ui-tabs__panel` | — | — | — | — |
| `menu` | `nowo-ui-menu` | — | — | — | — |
| `menu_toggle` | `nowo-ui-menu__toggle` | `btn btn-outline-secondary btn-sm` | `btn btn-outline-secondary btn-sm` | `inline-flex items-center gap-1 font-medium px-2 py-1 text-sm border` | `button secondary tiny` |
| `menu_panel` | `nowo-ui-menu__panel` | — | — | — | — |
| `menu_item` | `nowo-ui-menu__item` | — | — | — | — |
| `popover` | `nowo-ui-popover` | — | — | — | — |
| `segmented` | `nowo-ui-segmented` | — | — | — | — |
| `segmented_option` | `nowo-ui-segmented__option` | — | — | — | — |
| `modal` | `nowo-ui-modal` | — | — | — | — |
| `modal_panel` | `nowo-ui-modal__panel` | — | — | — | — |
| `modal_header` | `nowo-ui-modal__header` | — | — | — | — |
| `modal_body` | `nowo-ui-modal__body` | — | — | — | — |
| `modal_footer` | `nowo-ui-modal__footer` | — | — | — | — |
| `dialog` | `nowo-ui-dialog` | — | — | — | — |
| `dialog_header` | `nowo-ui-dialog__header` | — | — | — | — |
| `dialog_title` | `nowo-ui-dialog__title` | — | — | — | — |
| `dialog_body` | `nowo-ui-dialog__body` | — | — | — | — |
| `dialog_body_muted` | `nowo-ui-dialog__body--muted` | — | — | — | — |
| `dialog_footer` | `nowo-ui-dialog__footer` | — | — | — | — |
| `confirm` | `nowo-ui-confirm` | — | — | — | — |
| `confirm_page` | `nowo-ui-confirm-page` | — | — | — | — |
| `confirm_panel` | `nowo-ui-confirm__panel` | — | — | — | — |
| `confirm_header` | `nowo-ui-confirm__header` | — | — | — | — |
| `confirm_title` | `nowo-ui-confirm__title` | — | — | — | — |
| `confirm_close` | `nowo-ui-confirm__close` | — | — | — | — |
| `confirm_body` | `nowo-ui-confirm__body` | — | — | — | — |
| `confirm_message` | `nowo-ui-confirm__message` | — | — | — | — |
| `confirm_expect` | `nowo-ui-confirm__expect` | — | — | — | — |
| `confirm_expect_label` | `nowo-ui-confirm__expect-label` | — | — | — | — |
| `confirm_footer` | `nowo-ui-confirm__footer` | — | — | — | — |
| `form` | `nowo-ui-form` | — | — | — | — |
| `form_panel` | `nowo-ui-form-panel` | — | — | — | — |
| `form_actions` | `nowo-ui-form__actions` | `d-flex flex-wrap gap-2` | `d-flex flex-wrap gap-2` | `flex flex-wrap gap-2` | — |
| `form_columns` | `nowo-ui-form__columns` | — | — | — | — |
| `danger_zone` | `nowo-ui-danger-zone` | `mt-4` | `mt-4` | `mt-6` | — |
| `danger_zone_title` | `nowo-ui-danger-zone__title` | — | — | — | — |
| `danger_zone_intro` | `nowo-ui-danger-zone__intro` | — | — | — | — |
| `wizard_steps` | `nowo-ui-wizard-steps` | — | — | — | — |
| `wizard_step` | `nowo-ui-wizard-steps__item` | — | — | — | — |
| `wizard_step_active` | `nowo-ui-wizard-steps__item nowo-ui-wizard-steps__item--active` | — | — | — | — |
| `auth_panel` | `nowo-ui-auth-panel` | `card` | `card` | `border` | `callout` |
| `auth_footer` | `nowo-ui-auth-panel__footer` | — | — | — | — |
| `auth_links` | `nowo-ui-auth-panel__links` | — | — | — | — |
| `skip_link` | `nowo-ui-skip-link` | — | — | — | — |
| `error_page` | `nowo-ui-error-page` | — | — | — | — |
| `error_top` | `nowo-ui-error-page__top` | — | — | — | — |
| `error_main` | `nowo-ui-error-page__main` | — | — | — | — |
| `error_card` | `nowo-ui-error-page__card` | — | — | — | — |
| `error_figure` | `nowo-ui-error-page__figure` | — | — | — | — |
| `error_image` | `nowo-ui-error-page__img` | — | — | — | — |
| `error_code` | `nowo-ui-error-page__code` | — | — | — | — |
| `error_title` | `nowo-ui-error-page__title` | — | — | — | — |
| `error_lead` | `nowo-ui-error-page__lead` | — | — | — | — |
| `error_hint` | `nowo-ui-error-page__hint` | — | — | — | — |
| `error_actions` | `nowo-ui-error-page__actions` | `d-flex flex-wrap gap-2` | `d-flex flex-wrap gap-2` | `flex flex-wrap gap-2` | — |
| `error_home` | `nowo-ui-btn nowo-ui-btn--primary` | `btn btn-primary` | `btn btn-primary` | `inline-flex items-center gap-1 font-medium px-3 py-1.5 text-sm` | `button primary` |
| `error_action` | `nowo-ui-btn nowo-ui-btn--secondary` | `btn btn-outline-secondary` | `btn btn-outline-secondary` | `inline-flex items-center gap-1 font-medium px-3 py-1.5 text-sm border` | `button secondary` |
| `confirm_dialog_root` | `nowo-ui-confirm-dialog-root` | — | — | — | — |
| `confirm_dialog` | `nowo-ui-confirm-dialog` | — | — | — | — |
| `confirm_dialog_trigger` | `nowo-ui-btn nowo-ui-btn--secondary` | `btn btn-outline-secondary` | `btn btn-outline-secondary` | `inline-flex items-center gap-1 font-medium px-3 py-1.5 text-sm border` | `button secondary` |
| `app_shell` | `nowo-ui-app-shell` | — | — | — | — |
| `app_header` | `nowo-ui-app-shell__header` | — | — | — | — |
| `app_header_start` | `nowo-ui-app-shell__header-start` | — | — | — | — |
| `app_header_end` | `nowo-ui-app-shell__header-end` | — | — | — | — |
| `app_brand` | `nowo-ui-app-shell__brand` | — | — | — | — |
| `app_burger` | `nowo-ui-app-shell__burger` | — | — | — | — |
| `app_burger_icon` | `nowo-ui-burger__icon` | — | — | — | — |
| `app_burger_morph` | `nowo-ui-burger__morph` | — | — | — | — |
| `app_aside` | `nowo-ui-app-shell__aside` | — | — | — | — |
| `app_aside_inner` | `nowo-ui-app-shell__aside-inner` | — | — | — | — |
| `app_aside_label` | `nowo-ui-app-shell__aside-label` | — | — | — | — |
| `app_aside_nav` | `nowo-ui-app-shell__aside-nav` | — | — | — | — |
| `app_main` | `nowo-ui-app-shell__main` | — | — | — | — |
| `app_backdrop` | `nowo-ui-app-shell__backdrop` | — | — | — | — |

Slot groups: document / layout (`document` … `breadcrumb_separator`), page header (`page_header` … `toolbar`), buttons (`btn`
… `btn_icon`; variants are complete button classes — `btn_link` included —, `btn_sm` a size modifier, `btn_icon` the icon-only modifier), tables (`table_wrap` … `row_action_disabled`),
badges (Bootstrap variants use `text-bg-*`, Bootstrap 5.2+, which sets text and background together so the colour
pair never mixes with the semantic `nowo-ui-badge--*` palette; on 5.0 / 5.1 the semantic palette paints the badge;
Bootstrap 4 / Foundation variant classes set both colours too), filters / inputs, pagination, code (`code`, `code_block`), feedback (`muted`, `empty`, flashes, `toasts`), surfaces (`card` … `modal_footer`, `dialog` … `dialog_footer`,
`confirm` … `confirm_footer`), forms (`form`, `form_actions`, `danger_zone`), wizard / auth (`auth_panel` … `auth_links`);
2.6: `skip_link`, error pages (`error_page` … `error_action`), confirm dialog wrapper (`confirm_dialog_root` …
`confirm_dialog_trigger`), app shell (`app_shell` … `app_backdrop`; the semantic classes carry no layout CSS — only the burger glyph swap — the kit shell look comes
from `nowo_ui_kit.shell.kit_styles`), compact pagination (`pagination_compact` … `pagination_select`).

Code: `code` for inline `<code>` (identifiers, keys, short values) and `code_block` for a `<pre>` holding a payload,
JSON or a log excerpt. UiKit's CSS sets a monospace font (`--nowo-ui-font-mono`), the muted surface
(`--nowo-ui-code-bg`, `--nowo-ui-code-text`), a border and radius on the block (`--nowo-ui-code-border`,
`--nowo-ui-code-radius`), and scrolls the block in both directions above `--nowo-ui-code-block-max-height` (default
`32rem`). Lines are not wrapped (`white-space: pre`); add `nowo-ui-code-block--wrap` to wrap long lines. A `<code>`
inside the block inherits the block's font and colours. Bootstrap and Foundation already style `<code>` / `<pre>`
(their element rules win over the `nowo-ui` layer); the Bootstrap `code_block` default only adds `border p-3`.

```twig
<p>Key <code class="{{ nowo_ui_class('code', bundle) }}"{{ nowo_ui_attrs('code', bundle) }}>{{ log.key }}</code></p>
<pre class="{{ nowo_ui_class('code_block', bundle) }}"{{ nowo_ui_attrs('code_block', bundle) }}><code>{{ log.payload|json_encode(constant('JSON_PRETTY_PRINT') b-or constant('JSON_UNESCAPED_SLASHES')) }}</code></pre>
```

Wide forms (2.1): `input_wide` (max width `36rem`) and `input_full` (no max width) are **complete** input class lists —
use one of them instead of `input` on a widget (e.g. `attr: {class: nowo_ui_class('input_wide')}`), like the button
variants; the semantic modifiers `nowo-ui-input--wide` / `nowo-ui-input--full` work without framework CSS. Muted
dialog text: `dialog_body_muted` (the `dialog` partial's `body_muted: true`) and the `nowo-ui-confirm__body--muted`
modifier (e.g. on the full-page confirm through the `confirm_body` classes) paint `--nowo-ui-muted` without relying on
Bootstrap's `text-muted`; for inline text use the `muted` slot.

Form errors (2.3, UiKit form theme `@NowoUiKit/form/nowo_ui_layout.html.twig`): a field with errors gets
`input_invalid` on its widget (Bootstrap `is-invalid`; `aria-invalid="true"`) and its messages render as a list in the
`form_error` slot (Bootstrap `invalid-feedback d-block`); root errors render as a plain `nowo-ui-form-errors__list`,
which the auth scaffold wraps in the `form_errors` slot (a danger box painted by nowo-ui.css; Bootstrap form themes
print their own `alert` instead). **PasswordToggle bridge**: the `<input>` of nowo-tech/password-toggle-bundle's
`toggle_password_widget` gets the `input` (+ `input_invalid`) slot when the UiKit form theme is in the form's theme
chain (its `widget_attributes` adds them for the `toggle_password` block prefix only); a theme above it that redefines
`widget_attributes` (e.g. `bootstrap_5_layout`) keeps its own classes — set the field's `attr.class` there.

Floating widgets (2.3): the `menu` partial renders a native `<details data-nowo-ui-menu>` disclosure (CSP-safe, no
script needed; `nowo-ui-dialog.js` closes open menus on an outside click or Escape) with the slots `menu` (root),
`menu_toggle` (`<summary>`; Bootstrap `btn btn-outline-secondary btn-sm`), `menu_panel` (item list) and `menu_item`
(each item), or `popover` (the panel of a menu with free `content`). Host-only classes: `nowo_ui_class('menu_toggle',
bundle, defaults: false)` returns the semantic and host / bundle classes without the UiKit framework defaults — for a
compact control inside a host widget that must not get `btn …`.

```twig
{{ include(nowo_ui_partial('menu', bundle), {label: 'Export'|trans, icon: 'export', items: [
    link_csv, link_json, action_purge
], bundle: bundle}, false) }}
```

Minor hooks for kit class maps: `btn_link` (toolbar `Action` with `variant: 'link'`; same classes as
`ui.btn('link')`); `muted` (same classes as `ui.muted()`; UiKit prints it on the `—` placeholder of empty detail-list
values); `breadcrumb_link` / `breadcrumb_separator` (plain trail: crumb links and an `aria-hidden` `/` before every item
but the first — a host `breadcrumbs` partial without separator elements keeps the CSS `::before` separator);
`sortable_header` (added to `table_head_cell` on sortable columns) and `sort_link` (the header link, plus
`nowo-ui-sort--asc|desc` on the active column); `table_cell_muted` (added to `table_cell` when a default list cell has
no value — `null`, `''`, `false` — and prints a muted `—`); `row_action_disabled` (disabled row actions: semantic +
host classes on `Action` objects, host classes on 1.x hashes); `modal`, `modal_panel`, `modal_header`, `modal_body`,
`modal_footer` (the `modal_shell` partial keeps its 1.x structure classes `nowo-ui-modal modal fade` / `modal-content` /
`modal-header` / `modal-body` / `modal-footer`, which Bootstrap JS and the kit's modal CSS rely on, and appends the host
classes and attributes of these slots; the semantic `nowo-ui-modal__*` classes are for modals a host builds with
`nowo_ui_class()`).

Internals: `card_title` / `card_intro` (card heading, also the show and settings section cards); `detail_item`,
`detail_term`, `detail_value` (one `<div>` + `<dt>` + `<dd>` per `DetailItem`; the item is `display: contents` at zero
specificity, so a host class on `detail_item` gives it a box, and the `nowo-ui-detail-list--stacked` modifier — e.g.
`classes.detail_list: 'nowo-ui-detail-list--stacked'` — puts each label over its value); `detail_list_meta` (added to
the `detail_list` classes of the show scaffold's audit meta list, `ShowPage::meta`); `confirm` is the `<dialog>` root
only and `confirm_page` the full-page confirm root (with `card`): dialog styles on `confirm` — e.g.
`.confirm-dialog:not([open]) { display: none }` — never reach the page; `confirm_panel`, `confirm_header`,
`confirm_title`, `confirm_body`, `confirm_message`, `confirm_expect` (type-to-confirm label + input wrapper),
`confirm_expect_label`, `confirm_footer` (the `_confirm` dialog; on the full-page confirm scaffold the panel / header /
body / footer slots add only their host classes to the page's own wrappers — `card_body`,
`nowo-ui-confirm-page__header`, `nowo-ui-confirm-page__body`, `form_actions` — so the dialog-box styles never apply to
the page; a 1.x `body` passed to `_confirm` is wrapped in a `confirm_message` `<div>` only when the theme sets classes or
attributes for that slot; `theme.confirm_message_in_header` / `Confirm::messageInHeader` render the message under the
title inside the header); `kpi_link` (only on a whole-tile KPI `<a>`; the
kit rule `:where(a.nowo-ui-kpi--link)` has zero specificity, so host link colours and hover win).

### Badges

Since 2.2 the badge slots form **one subtle set** on every stack (tinted background, emphasis text, tinted border; no
margin utilities, so badges sit flush in cells and lists): Bootstrap 5.3 `bg-<tone>-subtle text-<tone>-emphasis border
border-<tone>-subtle` (on 5.0–5.2 the semantic `nowo-ui-badge--*` palette paints them), Bootstrap 4 its badge colours,
Tailwind / custom / none the semantic palette. `badge_info` and `badge_primary` follow the primary colour. The 1.x
`ui.badge()` macro keeps its 1.x (solid) output. A host that prefers **solid** badges sets the slots, e.g.:

```yaml
nowo_ui_kit:
  theme:
    classes:
      badge_success: { value: 'badge text-bg-success', framework_defaults: false }
      badge_danger: { value: 'badge text-bg-danger', framework_defaults: false }
```

### 2.5 slots

**Form spacing.** Every form row is spaced by tokens, not by margin utilities: the `form` slot (`<form class="nowo-ui-form">`)
is a flex column with `gap: var(--nowo-ui-form-row-gap)` (no sibling margins any more, so a gap the host puts on the
slot replaces it instead of adding to it); `form_actions` lost its `mt-3` / `mt-4` default and sits
`--nowo-ui-form-actions-gap` below the last row (also when rendered after the `</form>`); new `form_columns` slot
(`nowo-ui-form__columns`): side-by-side fields on an auto-fit grid, `--nowo-ui-form-column-gap` between columns
(`--nowo-ui-form-column-min` per column); `.form-label` / `.nowo-ui-form__label` sit `--nowo-ui-form-label-gap` above
their control. Tokens in [THEMING](THEMING.md#default-theme-polish-tokens-22).

`locale_tabs_nav` *(2.5.3)* is the tab list of the `locale_tabs` partial: the `tabs` classes without their bottom
margin (`mb-3` by default, or whatever margin a host puts on `tabs`), because the partial spaces the list from the
panels with `--nowo-ui-locale-tabs-gap`. `tab_panel` (each in-page panel of the `locale_tabs` partial, next to the list whose `tab` / `tab_active`
buttons `nowo-ui-tabs.js` swaps on selection: the partial passes both class sets as `data-nowo-ui-tabs-tab-class` /
`data-nowo-ui-tabs-tab-active-class`).

### 2.2 slots

`btn_danger_solid` (solid destructive button: the confirm button of a danger `Confirm`, dialog and full page);
`table_caption` (list table `<caption>`: left, muted, small; `ListPage::captionVisible = false` hides it visually);
`tabs_secondary` (second tab level: section tabs under an area nav, pills / sub-nav; the settings scaffold uses it when
`PageHeader::tabs` is set, the `tabs` partial with `level: 'secondary'`); `confirm_close` (✕ of a 2.0 confirm dialog);
`badge_primary`. The `table` slot adds Bootstrap's `table-striped` (zebra rows, as its semantic `nowo-ui-table--striped`
promises) and the `filters` slot became a grid (`d-grid gap-3 mb-3` / `grid gap-3 mb-4`; the fields auto-fit, the actions
are bottom-aligned right after the last field).

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
| `app_shell` *(2.6)* | `shell/_shell.html.twig` | the options of `app_shell_open` / `app_shell_close` + `main`; blocks `nowo_ui_shell_brand`, `nowo_ui_shell_header_actions`, `nowo_ui_shell_aside`, `nowo_ui_shell_main`, `nowo_ui_shell_footer` (`{% embed %}`) | signed-in app shell in one template |
| `app_shell_burger` *(2.6)* | `shell/_burger.html.twig` | `controls`, `expanded`, `rail`, `rail_mode`, `label`, `label_expand` / `label_rail` / `label_hide`, `label_attr_names`, `icon` (`svg` \| `morph` \| `none`), `static_icons`, `attr`, `kit_styles`, `bundle`; slots `app_burger`, `app_burger_icon`, `app_burger_morph` | app shell header |
| `app_shell_close` *(2.6)* | `shell/_close.html.twig` | `footer_html` (`false` = none) or `footer_brand` / `footer_links` / `footer_copyright`, `kit_styles`, `bundle`; slot `app_backdrop` | base layouts (closing half) |
| `app_shell_open` *(2.6)* | `shell/_open.html.twig` | `brand_html` / `brand`, `header_end`, `aside_html` or `aside_items` + `aside_label`, `aside_id`, `collapsed` / `rail` (default: `nowo_ui_kit.shell.sidebar_cookie`), `rail_mode`, `content_width`, `show_user_menu` + user fields, `attr` / `shell_attr`, `kit_styles`, `burger_*`, `bundle`; slots `app_shell`, `app_header*`, `app_brand`, `app_aside*`, `app_main` | base layouts (opening half; the page `{% block body %}` stays uncaptured) |
| `back_link` *(2.7)* | `partials/_back_link.html.twig` | `href`, `label` (string or `View\Label`), `icon` (default `prev`), `class`, `testid`, `bundle`; slot `back_link` | page header (`PageHeader::back`), host pages |
| `breadcrumbs` | `partials/_breadcrumbs.html.twig` | `header` (`PageHeader`; 2.2 `breadcrumbsMode` `auto` \| `kit` \| `own`: an empty BreadcrumbKit trail falls back to `header.breadcrumbs`; the kit's output is wrapped in a plain `<div class="nowo-ui-breadcrumbs" data-nowo-ui-breadcrumbs="kit">` without the slot's host classes), `bundle` | page header |
| `burger` | `partials/_burger.html.twig` | `controls`, `expanded`, `label`, `root_class`, `label_attr_names`, `attr`, `bundle` | shell |
| `card` | `partials/_card.html.twig` | `title` / `intro` (`Label` or string), `body`, `footer`, `toolbar`, `actions` (list of `View\Action`), `id`, `attr`, `cards` (list of card hashes → `cards` grid), `bundle`; slots `card`, `card_header`, `card_body`, `card_footer`, `cards` | bundle cards, dashboard cards |
| `confirm` | `partials/_confirm.html.twig` | `id`, `confirm` (`View\Confirm`), `action`, `action_href`, `bundle`; 2.2: `dismissible` (✕ in the header, slot `confirm_close`; default on with `confirm`), `close_label`, `size` (`sm` \| `md` \| `lg`, default `confirm.size`); the button of a danger `Confirm` reads `btn_danger_solid` | actions with a `Confirm`; list scaffold (once per row action, after the table) |
| `confirm_dialog` *(2.6)* | `partials/_confirm_dialog.html.twig` | `only_dialog`, `wrapper`, `trigger_label` / `trigger_class` / `trigger_title` / `trigger_aria_label` / `trigger_testid` / `trigger_disabled`, `expected_value`, `open_on_connect`, `dialog_id`, `dialog_testid`, `aria_labelledby`, `dialog_modifier`, `attr`, `dialog_attr`, `bundle`; blocks `trigger`, `form` (`{% embed %}`); slots `confirm_dialog_root`, `confirm_dialog`, `confirm_dialog_trigger` | Stimulus `confirm-dialog` wrapper around a form dialog |
| `detail_list` | `page/_detail_list.html.twig` | `items` (list of `View\DetailItem`), `modifier`, `bundle` | show scaffold (sections, meta) |
| `dialog` *(2.1)* | `partials/_dialog.html.twig` | `id`, `title` (string or `Label`), `body` / `footer` (HTML), `size` (`sm` \| `md` \| `lg` \| `xl`), `body_muted`, `dismissible`, `backdrop_close`, `close_label`, `attr`, `bundle`, 2.2: `title_html` (rich title, raw), `title_attr` / `body_attr` (attributes of the title / body); block `nowo_ui_dialog_body`; slots `dialog`, `dialog_header`, `dialog_title`, `dialog_body`, `dialog_footer` | framework-free native `<dialog>` (opener `data-nowo-ui-dialog-open="<id>"`, `js/nowo-ui-dialog.js`) — preferred over `modal_shell` in page-contract bundles |
| `empty` | `partials/_empty.html.twig` | `empty` (`View\EmptyState`) **and** `message` (translated), `region`, `bundle` | list scaffold |
| `filters` | `partials/_filters.html.twig` | `action`, `method`, `q`, `placeholder`, `actions`, `clear_href`, `collapsible` | bundle filter bars |
| `flashes` | `partials/_flashes.html.twig` | `flash_messages` (2.2, default `app.flashes`), `flashes` (deprecated: ignored inside page scaffolds), `trans_domain`, `bundle` | layouts (`nowo_ui_flashes`) |
| `locale_tabs` *(2.5)* | `partials/_locale_tabs.html.twig` | `panels` (list of `{locale, content (HTML Markup; plain strings escaped), label?, name?, invalid?, required?, dir?, attr?}`), `id`, `label` (tab list `aria-label`, default `locale_tabs.label`), `default_locale` (★, listed first unless `default_first: false`), `required_locales` (*), `active_locale`, `activate_invalid`, `mark_default`, `default_marker` / `required_marker` / `error_marker`, `legend`, `progressive` (default true: stacked fieldsets until `nowo-ui-tabs.js` upgrades the root), `stimulus` (peer identifier), `attr`, `bundle`; slots `locale_tabs_nav`, `tab`, `tab_active`, `tab_panel` | one in-page panel per locale (FormKit `locale_tabs`, per-locale CRUD fields); see [USAGE](USAGE.md#locale-tabs-25) |
| `list_filters` | `page/_list_filters.html.twig` | `page` (`View\ListPage`, `page.filters` FormView), `region`, `bundle` | list scaffold |
| `menu` *(2.3)* | `partials/_menu.html.twig` | `items` (`View\Link` / `View\Action` / `{label, href, attr, current, danger}`) or `content` (HTML, `popover` panel), `label`, `icon`, `display`, `align` (`end` \| `start`), `popover` *(2.4; default `theme.popover_menus`)*: native `[popover]` panel in the top layer (never clipped by table scrollers), placed by `nowo-ui-popover.js`, `confirm_external` *(2.4)*, `open`, `attr`, `bundle` | dropdown / overflow menus, popovers (native `<details>`, no JS required; light dismiss by `nowo-ui-dialog.js`) |
| `modal_shell` | `partials/_modal_shell.html.twig` | `id`, `title`, `body`, `footer` | modals |
| `page_header` | `partials/_page_header.html.twig` | scaffolds pass **both** `header` (`View\PageHeader`) + `actions` (toolbar `View\Action`s) **and** the 1.x `title` / `intro` (translated) / `toolbar` (pre-rendered actions) with `header_actions_rendered: true`; `region`, `bundle` | every scaffold (`nowo_ui_page_header`) |
| `pagination` | `partials/_pagination.html.twig` | scaffolds pass the 1.x array (`pagination.toArray()`) + `pagination_model` (`View\Pagination`), `item_count`, `per_page_choices` / `per_page_selector` *(2.4: per-page links; the selector uses `Pagination::fromTotal()`'s allowed values)*, the 1.x top-level `route` / `route_params` / `query` (request query + `Pagination::query`), `page_param`, `per_page_param`, `region`, `bundle`; also accepts the object | list scaffold |
| `pagination_compact` *(2.6)* | `partials/_pagination_compact.html.twig` | the `pagination` input (`View\Pagination` or hash), `per_page_choices` (default `allowedPerPage`), `per_page_selector`, `id`; slots `pagination_compact`, `pagination_link`, `pagination_page`, `pagination_per_page`, `pagination_info`, `pagination_select` | `pagination` partial for a compact model / `variant: 'compact'` |
| `progress` *(2.2)* | `partials/_progress.html.twig` | `percent` (0–100, null = indeterminate), `indeterminate`, `label` (`Label` or string; default `progress.label`), `show_value`, `id`, `attr`, `bundle`; slots `progress`, `progress_meter` (native `<progress>`) | wizard / backup / restore progress |
| `row_actions` | `partials/_row_actions.html.twig` | scaffolds pass 1.x hashes (translated `label`, resolved `href`, `method` POST/GET, `csrf_token` / `csrf_field` also from a CSRF `FormView`, `confirm_id`…) with the `View\Action` as `view`, and `confirm_external: true`; also accepts `View\Action` objects; `bundle` | list scaffold |; `menu` *(2.4: the actions in one overflow `menu`, `menu_label`)*, `popover` *(2.4)*
| `scripts` *(2.2)* | `partials/_scripts.html.twig` | `script_names` (default `nowo_ui_script_names()`: `modal`, `confirm`, `dialog`, `toast`, `tabs`, `theme`, 2.4: `popover`, `segmented`, 2.6: `autosubmit`), `nonce` | document layout; host layouts with their own script list (`{{ include(nowo_ui_partial('scripts')) }}`) |
| `segmented` *(2.4)* | `partials/_segmented.html.twig` | `options` (two `{label, checked, title?, attr?}`), `field` (checkbox `FormView`) or `name` / `checked` / `value` / `id`, `label` or `labelled_by`, `input_attr` (e.g. Live Component `data-model`), `attr`, `bundle`; slots `segmented`, `segmented_option` | two-option choice backed by one checkbox (radiogroup; `nowo-ui-segmented.js` syncs the checkbox and dispatches `change`) |
| `section_form` | `page/_section_form.html.twig` | `form` (FormView), `submit_label`, `actions` (2.2: `View\Action`s after the submit button), `extra_actions`, `bundle` | settings scaffold |
| `shell` | `partials/_shell.html.twig` | `brand_html`, `aside_items`, `main`, `root_class`, `header_class`, `attr`, `burger_root_class`, `bundle` | host shells |
| `table` *(2.2)* | `partials/_table.html.twig` | `table` (`View\Table`: columns, rows, rowActions, caption, sort, actions column, responsive), `route`, `head_html` / `rows_html` (pre-rendered cells), `bundle`; blocks `nowo_ui_table_head` / `nowo_ui_table_row` through `{% embed %}`; slots `table_wrap`, `table`, `table_caption`, `table_head*`, `table_row`, `table_cell*`, `table_actions_*` | list scaffold (`ListPage::$table`); show pages and bundle pages (`nowo_ui_show_after`) |
| `tabs` | `partials/_tabs.html.twig` | `items` (`{label, href, current, attr}`, label also a `Label`), `aria_label`, `region`, `bundle`, `attr` (2.1: `<nav>` attributes such as `data-turbo: 'false'`, merged over the `tabs` slot attributes), `level` (2.2: `primary` \| `secondary` → slot `tabs_secondary`); slots `tabs` / `tabs_secondary`, `tab`, `tab_active` | settings scaffold, bundle tab bars |
| `toasts` | `partials/_toasts.html.twig` | `flashes`, `trans_domain`, `bundle` | layouts / hosts |
| `wizard_form` | `page/_wizard_form.html.twig` | `page` (`View\WizardPage`), `bundle` | wizard scaffold |

Every partial also receives `nowo_ui_page_type` from the scaffolds. Internal templates that are not theme partials
(implementation details, not public includes): `_action_post`, `_action_form_view`, `_action_inner`,
`page/_scaffold` (scaffold macros).

## Tokens

`theme.tokens` (light, on `:root`) and `theme.tokens_dark` (on `[data-theme="dark"]`) are emitted by
`nowo_ui_theme_tokens()` (or `nowo_ui_tokens()`, below) as `--nowo-ui-<name>`. Keys are written without the prefix (`primary`) or with it
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

**Precedence with the appearance SPI.** `appearance` (and its `token_aliases`) also sets `--nowo-ui-*`. The theme
tokens are the host defaults; the appearance (user-chosen) overrides them. Both use the same specificity (`:root`,
`[data-theme="dark"]`), so the later `<style>` wins: `nowo_ui_tokens()` prints the theme tokens, then
`nowo_ui_appearance_style()` when the appearance is enabled — the UiKit document layout calls it after
`nowo-ui.css`; host layouts should call it instead of the two functions.

## Per-page-type variation

`theme.pages.<type>` varies a slot by **page type** — the scaffold that renders it: `list`, `show`, `form`,
`settings`, `dashboard`, `confirm`, `wizard`, `auth` (`ThemeSlots::PAGE_TYPES`; unknown types are rejected).

| Key | Semantics |
| --- | --- |
| `classes` | **replace** the host classes of that slot on that page type (same entry forms as `theme.classes`) |
| `framework_defaults` | overrides `theme.framework_defaults` on that page type (unset = inherit) |
| `attributes` | **merge** over the host attributes of that slot; bundle attributes merge over them |
| `header_actions_in_filters` | `list` only (bool, default `false`): the list scaffold renders the page-header actions at the end of the filter row (when the page has filters); `ListPage::headerActionsInFilters` wins when set |

Precedence: **bundle page slot > bundle slot > bundle page > bundle > page-type slot > page type > host slot > host**
(classes: the most specific entry replaces the others; attributes: merged host → page type → bundle → bundle page
type → call site). Bundle page types: see [Per-bundle exceptions](#per-bundle-exceptions).

```yaml
nowo_ui_kit:
  theme:
    classes: { table: 'data-table', page_header: 'app-header' }
    pages:
      list:      { classes: { table: 'data-table data-table--sticky' }, attributes: { table: { data-controller: 'sticky-head' } } }
      dashboard: { framework_defaults: false, classes: { page_header: 'app-header app-header--hero' } }
```

Every scaffold sets `{% set nowo_ui_page_type = '<type>' %}` before its blocks and passes `nowo_ui_page_type` to the
partials it includes, so slots read in partials (`_pagination`, `_row_actions`, `_empty`…), in layouts and in the data
blocks a bundle overrides resolve with the page type without extra arguments. Pass `page` explicitly
(`nowo_ui_class('card', bundle, null, 'dashboard')`) in templates that are not scaffolds.

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
| `pages.<type>` *(2.1)* | per-bundle variation by page type: `classes` (replace every other entry of the slot on that bundle's pages of that type), `framework_defaults` (overrides the bundle setting there), `attributes` (merge last, before the call site); no `partials`, `tokens` or `header_actions_in_filters` |

```yaml
nowo_ui_kit:
  theme:
    classes: { table: 'data-table' }
    bundles:
      NowoHttpLogBundle:
        classes: { table: 'data-table data-table--dense' }   # replaces 'data-table' for HttpLog pages only
        attributes: { table: { data-density: 'compact' } }
        tokens: { radius: '0.25rem' }                        # [data-nowo-ui-bundle="NowoHttpLogBundle"] { --nowo-ui-radius: … }
        pages:
          show: { classes: { detail_list: 'nowo-ui-detail-list--stacked log-details' }, framework_defaults: false }
```

Precedence with bundle page types (classes and framework defaults): **bundle page slot > bundle slot > bundle page >
bundle > page-type slot > page type > host slot > host**.

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
    confirm_message_in_header: false   # true = confirm dialogs / page print the message under the title (Confirm::messageInHeader wins)
    popover_menus: false               # 2.4: true = menu / kebab / row-action menus open as native [popover] panels (top layer)
    bundles:
      <BundleShortName>:
        { framework_defaults: bool, classes: …, attributes: …, partials: …, tokens: …, tokens_dark: … }
        pages:   # 2.1
          <list|show|form|settings|dashboard|confirm|wizard|auth>: { framework_defaults: bool, classes: …, attributes: … }
    pages:
      <list|show|form|settings|dashboard|confirm|wizard|auth>: { framework_defaults: bool, classes: …, attributes: … }
      list: { header_actions_in_filters: bool }   # list page type only
```

## Contract-test allow-lists

`Nowo\UiKitBundle\Test\FrameworkClasses` derives the per-framework allow-list of the contract test from this table:
the slot defaults of the rendered `css_framework`, the classes the `ui.btn` macro adds for the `outline` / `link`
variants (Bootstrap `btn-outline-primary`, `btn-link`; Tailwind `underline-offset-2`, `px-0`) and UiKit's own helpers
(`visually-hidden`, state classes, the 1.x pagination list `pagination` / `page-item` / `page-link`) and, since 2.1, the
structure of the `modal_shell` partial (`FrameworkClasses::MODAL_SHELL`: `modal`, `fade`, `modal-dialog`,
`modal-content`, `modal-header`, `modal-title`, `btn-close`, `modal-body`, `modal-footer` — a documented structural
exception: Bootstrap JS and the kit's modal CSS rely on them and the 1.x output stays identical, so bundles no longer
allow-list them; page-contract bundles should prefer the framework-free `dialog` partial). Any other class
that looks like a Bootstrap / Tabler / Tailwind / Foundation class fails `testPageUsesFrameworkClassesOnlyThroughThemeSlots`.
Change the defaults here and the allow-lists follow (the allow-list does not depend on `framework_defaults`: dropping
defaults only removes classes).
