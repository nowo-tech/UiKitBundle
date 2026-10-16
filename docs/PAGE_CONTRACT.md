# Page contract (REQ-UI-003) — API stub

> **Status:** stub listing the API shipped by the phase-0 core (branch `feature/ui-page-contract`). The full guide,
> `THEME_SLOTS.md` and `MIGRATING_BUNDLES.md` come in the next step. Normative spec: REQ-UI-003 in
> `BUNDLES_FULL_SPECS_DETAILS.md`; design: `BUNDLES_UI_PAGE_CONTRACT.md`.

## Table of contents

- [Configuration](#configuration)
- [Twig API](#twig-api)
- [Theme slots](#theme-slots)
- [View models](#view-models)
- [Layouts](#layouts)
- [Scaffolds](#scaffolds)
- [Partials](#partials)
- [Tokens and CSS](#tokens-and-css)
- [Deviations from REQ-UI-003](#deviations-from-req-ui-003)

## Configuration

```yaml
nowo_ui_kit:
  layouts:
    admin: '@NowoUiKit/layout/admin.html.twig'   # host layout or bridge
    guest: '@NowoUiKit/layout/guest.html.twig'
  breadcrumbs:
    breadcrumb_kit: true    # use BreadcrumbKit's breadcrumb_render() when it exists
    collection: null        # BreadcrumbKit collection (PageHeader::breadcrumbCollection overrides)
  theme:
    classes: { btn_primary: 'btn btn-moss', table: 'data-table' }      # slot => classes (string or list)
    attributes: { toasts: { data-controller: toast-stack } }            # slot => attrs (no on*)
    partials: { pagination: 'kit/_pagination.html.twig' }               # partial name => template
    tokens: { primary: 'var(--color-moss)' }                            # --nowo-ui-<name> on :root
    tokens_dark: { primary: '#9c9' }                                    # on :root[data-theme="dark"]
    bundles:
      NowoHttpLogBundle: { classes: { table: 'data-table data-table--dense' } }
```

Per-bundle exceptions take the same keys: a bundle class **replaces** the host class of that slot (UiKit semantic +
framework defaults stay), attributes merge, partials replace, tokens are scoped to `[data-nowo-ui-bundle="<Bundle>"]`.

## Twig API

| Function / filter / global | Result |
| --- | --- |
| `nowo_ui_class(slot, bundle = null, framework = null)` | semantic `nowo-ui-*` + framework defaults (`css_framework`) + host / bundle classes |
| `nowo_ui_attrs(slot, bundle = null, extra = {})` | escaped attribute string (leading spaces) |
| `nowo_ui_partial(name, bundle = null)` | template of a UiKit partial (bundle > host > default) |
| `nowo_ui_theme_tokens(nonce = null)` | `<style data-nowo-ui-theme-tokens>` ('' without tokens; nonce defaults to request attribute `csp_nonce`) |
| `nowo_ui_theme_css()` | token stylesheet text |
| `nowo_ui_breadcrumb_kit(collection = null)` / `nowo_ui_breadcrumb_kit_available()` | BreadcrumbKit bridge |
| `value\|nowo_ui_trans(params = {})` | translates a `View\Label` (strings pass through) |
| `nowo_ui_layout` | `{admin, guest}` |
| `nowo_ui_theme` | `Theme\Theme` (used by the 1.x partials: `extraClasses()`, `renderAttributes()`, `partial()`) |
| `nowo_ui_breadcrumbs` | `{breadcrumb_kit, collection}` |

## Theme slots

Single source: `Nowo\UiKitBundle\Theme\ThemeSlots::SLOTS` — `document`, `body`, `page`, `navigation`, `content`,
`shell`, `burger`, `breadcrumbs`, `page_header`, `page_title`, `page_intro`, `toolbar`, `btn`, `btn_primary`,
`btn_secondary`, `btn_danger`, `btn_sm`, `table_wrap`, `table`, `table_head`, `table_row`, `table_cell`,
`row_actions`, `action`, `badge`, `badge_{success,warning,danger,info,neutral}`, `filters`, `filters_actions`,
`input`, `pagination`, `pagination_link`, `pagination_active`, `pagination_info`, `empty`, `flashes`, `flash`,
`flash_{success,error,warning,info}`, `toasts`, `card`, `card_header`, `card_body`, `cards`, `section`,
`detail_list`, `kpis`, `kpi`, `tabs`, `tab`, `tab_active`, `modal`, `confirm`, `form`, `form_actions`,
`danger_zone`, `wizard_steps`, `wizard_step`, `wizard_step_active`, `auth_panel`.

Partial names (`ThemeSlots::PARTIALS`): `action`, `breadcrumbs`, `burger`, `card`, `confirm`, `empty`, `filters`,
`flashes`, `modal_shell`, `page_header`, `pagination`, `row_actions`, `shell`, `tabs`, `toasts`.

## View models

All `final readonly`, no services (worker safe). `Label|string`: a `Label(key, domain = 'messages', params = [])`
is translated by the templates; a plain string is printed escaped.

| Class | Constructor |
| --- | --- |
| `Label` | `(key, domain = 'messages', params = [])`, `Label::kit(key)`, `withParams()` |
| `PageHeader` | `(title, intro = null, actions = [], breadcrumbs = [], breadcrumbCollection = null)` |
| `Action` | `(kind = 'custom', label = null, href = null, route = null, params = [], method = 'GET', csrfToken = null, confirm = null, icon = null, variant = null, attributes = [], form = null, csrfField = '_token', modalId = null, disabled = false, visible = true)`; `create()`, `edit()`, `view()`, `delete()`, `route()`; `isPost()`, `resolvedVariant()`, `confirmId()`, `toArray()` |
| `Confirm` | `(title, message = null, confirmLabel = null, variant = 'danger', cancelLabel = null, id = null)`, `Confirm::delete()` |
| `Crumb` | `(label, href = null)` |
| `Link` | `(label, href, attributes = [])` |
| `Pagination` | `(page, perPage, total, totalPages = null, route = null, query = [], routeParams = null, pageParam = 'page', perPageParam = 'per_page')`; `fromArray()`, `fromPagerfanta()`, `fromKnp()`; `from()`, `to()`, `hasPrevious()`, `hasNext()`, `toArray()` |
| `Column` | `(key, label = null, sortable = false, align = 'start', class = null, required = false)` |
| `EmptyState` | `(title = null, message = null, action = null)` |
| `DetailItem` / `DetailSection` | `(label, value, href = null)` / `(title = null, items = [], id = null)` |
| `KpiTile` | `(label, value, hint = null, href = null, variant = 'neutral')` |
| `SettingsSection` | `(id, label, href = null, form = null, intro = null, submitLabel = null)` |
| `WizardStep` | `(id, label, href = null)` |
| `ListPage` | `(header, columns = [], rows = [], filters = null, rowActions = [] \| callable(row, key), pagination = null, empty = null, filtersClearHref = null, sort = null, direction = 'asc', caption = null, bundle = null, layout = null)` |
| `ShowPage` | `(header, sections = [], meta = [], bundle = null, layout = null)` |
| `FormPage` | `(header, form, submitLabel = null, cancelHref = null, cancelLabel = null, secondaryActions = [], deleteAction = null, bundle = null, layout = null)` |
| `SettingsPage` | `(header, sections = [], current = null, bundle = null, layout = null)` |
| `DashboardPage` | `(header, kpis = [], bundle = null, layout = null)` |
| `ConfirmPage` | `(header, confirm, action (non-GET), cancelHref = null, bundle = null, layout = null)` |
| `WizardPage` | `(header, steps, current, form = null, backHref = null, nextLabel = null, backLabel = null, bundle = null, layout = null)` |
| `AuthPage` | `(title, form = null, intro = null, brand = null, error = null, submitLabel = null, links = [], bundle = null, layout = null)` |

`bundle` selects the per-bundle theme exceptions; `layout` is the per-bundle `web_ui.layout_template` override
(null = `nowo_ui_layout.admin`, or `.guest` for `AuthPage`).

## Layouts

| Template | Blocks |
| --- | --- |
| `layout/document.html.twig` | `nowo_ui_document_title`, `nowo_ui_head`, `nowo_ui_styles`, `nowo_ui_scripts`, `nowo_ui_body` |
| `layout/admin.html.twig` | + `nowo_ui_navigation`, `nowo_ui_page_header`, `nowo_ui_flashes`, `nowo_ui_content`, `nowo_ui_modals` |
| `layout/guest.html.twig` | same as admin without `nowo_ui_navigation` |

Variables: `nowo_ui_document_title`, `nowo_ui_theme_boot` (false skips the theme boot), `nowo_ui_bundle`
(set by the scaffolds from `page.bundle`). Assets come from the `nowo_ui_kit` asset package only.

## Scaffolds

| Scaffold | Data blocks (context) |
| --- | --- |
| `page/list.html.twig` | `nowo_ui_list_head`, `nowo_ui_list_row` (`row`, `row_key`, `loop`), `nowo_ui_list_after` |
| `page/show.html.twig` | `nowo_ui_show_sections`, `nowo_ui_show_after` |
| `page/form.html.twig` | `nowo_ui_form_body` (`form`; default REQ-TWIG-003 loop), `nowo_ui_form_aside` |
| `page/settings.html.twig` | `nowo_ui_settings_section` (`section`) |
| `page/dashboard.html.twig` | `nowo_ui_dashboard_cards` |
| `page/confirm.html.twig` | — |
| `page/wizard.html.twig` | `nowo_ui_wizard_step` (`step`) |
| `page/auth.html.twig` (guest) | `nowo_ui_auth_fields` (`form`), `nowo_ui_auth_actions`, `nowo_ui_auth_footer` |

## Partials

New: `_action` (one `View\Action`: link, modal trigger, POST form, confirm `<dialog>`), `_action_post`,
`_breadcrumbs`. Upgraded (1.x inputs unchanged): `_pagination` (`Pagination`), `_page_header` (`header`),
`_row_actions` (`Action` objects), `_confirm` (`confirm`, `action`), `_empty` (`empty`), `_toasts` / `_flashes`
(`trans_domain`, slot hooks), `_shell` / `_burger` (`root_class`, `label_attr_names`, `attr`, `header_class`,
`main_class`, `burger_*`). All accept `bundle`.

## Tokens and CSS

`nowo-ui.css` keeps its palette for hosts without tokens. With `<html data-nowo-ui-host-tokens>` (set by
`layout/document` when `theme.tokens` is configured) the palette blocks have zero specificity, so any host
declaration wins regardless of load order.

## Deviations from REQ-UI-003

- Template namespace: config defaults use `@NowoUiKit/…` (Symfony's bundle namespace); internal includes keep
  `@NowoUiKitBundle/…` like the 1.x partials. Both resolve to the same files and host overrides.
- Token keys are the UiKit token names without the prefix (`primary`, `surface`, `radius`…); the spec example
  `color-primary` would emit `--nowo-ui-color-primary`, which the kit does not read. `tokens_dark` was added.
- `Action` POSTs prefer a CSRF-only `FormView` (`form`, REQ-TWIG-005); the `csrfToken` fallback renders a small
  `<form>` inside UiKit's own partial (as `_row_actions` already did).
- BreadcrumbKit renders its own configured trail (it cannot take `PageHeader::breadcrumbs`); it is used when
  installed and `breadcrumbs.breadcrumb_kit` is true, otherwise the plain trail.
- `PageContractTestCase`, the host-theme demo and the full docs are the next step.
