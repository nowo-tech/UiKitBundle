# Page contract (REQ-UI-003)

UiKit 2.0 owns the **page shell** of every Nowo UI bundle: canonical layouts, one **scaffold** per view type, immutable
**view models** and a **global theme** (classes, attributes, partials, tokens). A host themes each view type once and
every bundle page inherits it; a bundle ships data and data blocks, not chrome.

Normative spec: REQ-UI-003 in `BUNDLES_FULL_SPECS_DETAILS.md` · design and migration plan:
`BUNDLES_UI_PAGE_CONTRACT.md` · slot vocabulary: [`THEME_SLOTS.md`](THEME_SLOTS.md) · bundle recipe:
[`MIGRATING_BUNDLES.md`](MIGRATING_BUNDLES.md) · live demo: `demo/symfony8` → `/page-contract`.

## Table of contents

- [Concepts](#concepts)
- [Configuration](#configuration)
- [Twig API](#twig-api)
- [Layouts](#layouts)
- [Scaffolds](#scaffolds)
  - [List](#list)
  - [Show](#show)
  - [Form](#form)
  - [Settings](#settings)
  - [Dashboard](#dashboard)
  - [Confirm](#confirm)
  - [Wizard](#wizard)
  - [Auth](#auth)
- [View models](#view-models)
- [Partials](#partials)
- [Tokens and CSS](#tokens-and-css)
  - [Cascade layers](#cascade-layers)
- [Example: a bundle page](#example-a-bundle-page)
- [Example: host integration](#example-host-integration)
- [Contract test](#contract-test)
- [Compatibility and versioning](#compatibility-and-versioning)
- [Deviations from REQ-UI-003](#deviations-from-req-ui-003)

## Concepts

```
host app                              UiKit                                        UI bundle
──────────────────────────            ───────────────────────────────────────      ─────────────────────────────
layouts/admin.html.twig  ◄─────────── nowo_ui_kit.layouts.admin                   controller: new ListPage(…)
(renders the nowo_ui_* blocks)        @NowoUiKit/layout/{document,admin,guest}      │
                                      @NowoUiKit/page/{list,show,form,…}  ◄─────────┘ items/index.html.twig:
nowo_ui_kit.theme (once):               header, filters, table, row actions,        {% extends '@NowoUiKit/page/list.html.twig' %}
  classes / attributes / partials /     pagination, empty state, confirm dialogs    {% block nowo_ui_list_row %}…{% endblock %}
  tokens (+ per-bundle exceptions)      rendered from the view model with slots
optional: templates/bundles/          View\{PageHeader, Action, Confirm, …}
  NowoUiKitBundle/page/*.html.twig    Test\PageContractTestCase  ◄──────────────── tests/PageContractTest.php
```

| Term | Meaning |
| --- | --- |
| **Layout** | The document / page shell. Scaffolds extend `nowo_ui_layout.admin` (or `.guest`), which the host points at its own layout or at a bridge that renders the canonical blocks. |
| **Scaffold** | `@NowoUiKit/page/<type>.html.twig`: renders every structural region of a view type from a view model (`page`) and exposes **data blocks** for bundle content. Overriding a scaffold in `templates/bundles/NowoUiKitBundle/page/` re-skins that view type for every bundle. |
| **View model** | Immutable `Nowo\UiKitBundle\View\*` object the controller builds (labels as translation keys, no services, no request state — worker safe). |
| **Data block** | A Twig block of the scaffold a bundle page fills (row cells, detail sections, form body…). Block names and context variables are part of the published contract. |
| **Theme slot** | A named styling hook (`table`, `btn_primary`, `page_header`…). Markup prints `nowo_ui_class('slot')` = semantic `nowo-ui-*` class + framework defaults (per `css_framework`) + host classes. Vocabulary: [`THEME_SLOTS.md`](THEME_SLOTS.md). |
| **Theme partial** | A UiKit partial resolved through `nowo_ui_partial('name')`, replaceable once by the host (`theme.partials`). |
| **Token** | `--nowo-ui-<name>` custom property; `theme.tokens` sets them once for UiKit and every bundle. |
| **Per-bundle exception** | `theme.bundles.<Bundle>`: classes replace, attributes merge, partials replace, tokens are scoped to `[data-nowo-ui-bundle="<Bundle>"]`. View models carry `bundle` to select it. |

Rules for bundle pages (summary of REQ-UI-003): extend a scaffold (or, for a genuinely unique page, the layout
directly — listed in `docs/TEMPLATES.md`); no `<html>`, no CDN assets, no inline `on*=` / `confirm()`; framework
classes only through slots; no local copies of UiKit partials; no own layout global.

## Configuration

```yaml
nowo_ui_kit:
  css_framework: bootstrap5           # bootstrap5 | bootstrap4 | tailwind | foundation | tabler | custom | none
  layouts:
    admin: 'layouts/admin.html.twig'  # default '@NowoUiKit/layout/admin.html.twig'
    guest: 'layouts/guest.html.twig'  # default '@NowoUiKit/layout/guest.html.twig'
  breadcrumbs:
    breadcrumb_kit: true              # render PageHeader trails with BreadcrumbKit's breadcrumb_render() when installed
    collection: null                  # BreadcrumbKit collection (PageHeader::breadcrumbCollection overrides)
  theme:
    framework_defaults: true                                           # false = no UiKit framework classes on any slot
    classes:    { btn_primary: 'btn-moss', table: 'data-table' }        # slot => classes (string or list)
    #           badge_success: { value: 'acme-badge', framework_defaults: false }   # per slot: drop btn/badge/card… defaults
    attributes: { toasts: { data-controller: toast-stack } }           # slot => attributes (no on*)
    partials:   { pagination: 'kit/_pagination.html.twig' }            # partial name => template
    tokens:      { primary: 'var(--color-moss)', radius: '0.75rem' }   # --nowo-ui-<name> on :root
    tokens_dark: { primary: '#9c9' }                                   # on :root[data-theme="dark"]
    bundles:
      NowoHttpLogBundle: { classes: { table: 'data-table data-table--dense' } }
```

Validation (container compile time): unknown slots and partial names, class entries with keys other than `value` /
`framework_defaults` or a non-boolean `framework_defaults`, `on*` attributes, attribute names outside
`[a-zA-Z_:][a-zA-Z0-9_:.-]*`, class lists with `"`, `<` or `>`, token names outside `[a-z0-9-]` and token values with
`{ } ; < >` are rejected with an explicit message. Full reference: [`THEME_SLOTS.md`](THEME_SLOTS.md).

## Twig API

| Function / filter / global | Result |
| --- | --- |
| `nowo_ui_class(slot, bundle = null, framework = null)` | semantic `nowo-ui-*` + framework defaults (`css_framework`, or `framework`; unless `framework_defaults` drops them) + host classes (or the bundle's exception) |
| `nowo_ui_attrs(slot, bundle = null, extra = {})` | escaped attribute string with leading spaces (`true` = bare attribute, `false`/`null` = omitted) |
| `nowo_ui_partial(name, bundle = null)` | template name of a UiKit partial (bundle > host > UiKit default) |
| `nowo_ui_theme_tokens(nonce = null)` | `<style data-nowo-ui-theme-tokens>` with the tokens ('' without tokens; nonce defaults to the `csp_nonce` request attribute) |
| `nowo_ui_theme_css()` | the token stylesheet text (for a host that inlines it elsewhere) |
| `nowo_ui_breadcrumb_kit(collection = null)` / `nowo_ui_breadcrumb_kit_available()` | BreadcrumbKit bridge used by the page header |
| `value\|nowo_ui_trans(params = {})` | translates a `View\Label` (or `{key, domain, params}`); plain strings pass through (escaped by Twig) |
| global `nowo_ui_layout` | `{admin, guest}` — the configured layouts |
| global `nowo_ui_theme` | the `Theme\Theme` object (used by the 1.x partials: `macroClasses()`, `extraClasses()`, `frameworkDefaults()`, `renderAttributes()`, `partial()`, `hasTokens()`) |
| global `nowo_ui_breadcrumbs` | `{breadcrumb_kit, collection}` |

## Layouts

| Template | Canonical blocks | Notes |
| --- | --- | --- |
| `@NowoUiKit/layout/document.html.twig` | `nowo_ui_document_title`, `nowo_ui_head`, `nowo_ui_styles`, `nowo_ui_scripts`, `nowo_ui_body` | `<html>` skeleton: theme boot, `nowo-ui.css` + `nowo_ui_theme_tokens()`, UiKit scripts (asset package `nowo_ui_kit`, never a CDN); `<html data-nowo-ui-host-tokens>` when the theme has tokens |
| `@NowoUiKit/layout/admin.html.twig` | + `nowo_ui_navigation`, `nowo_ui_page_header`, `nowo_ui_flashes`, `nowo_ui_content`, `nowo_ui_modals` | `nowo_ui_navigation` is empty (hosts put their shell / DashboardMenu there); flashes default to `nowo_ui_partial('flashes')` |
| `@NowoUiKit/layout/guest.html.twig` | same as admin without `nowo_ui_navigation` | login, reset, setup wizard, maintenance, public status |

Variables: `nowo_ui_document_title` (title when the block is not overridden), `nowo_ui_theme_boot` (`false` skips
the pre-paint theme boot), `nowo_ui_bundle` (set by every scaffold from `page.bundle`; the layouts print it as
`data-nowo-ui-bundle` on `<main>` so bundle-scoped tokens apply). Stack assets with `{{ parent() }}`:

```twig
{% block nowo_ui_scripts %}{{ parent() }}<script src="{{ asset('js/board.js') }}" defer></script>{% endblock %}
```

## Scaffolds

Every scaffold: `{% extends page.layout ?? nowo_ui_layout.admin %}` (`.guest` for auth), sets `nowo_ui_bundle`,
fills `nowo_ui_document_title` with the page title and renders the page header through
`nowo_ui_partial('page_header')`. Context variables of data blocks are listed in parentheses.

### List

`@NowoUiKit/page/list.html.twig` · `page: ListPage` · `data-nowo-ui-page="list"`

| Region | From |
| --- | --- |
| header + actions + breadcrumbs | `page.header` (`PageHeader`) |
| filters (GET form, apply / clear) | `page.filters` (`FormView`, REQ-TWIG-003 child loop), `page.filtersClearHref` |
| table frame, sortable headers (`aria-sort`) | `page.columns` (`Column`), `page.sort`, `page.direction`, `page.caption` |
| row actions + confirm `<dialog>`s | `page.rowActions` (list of `Action`, or `callable(row, key): list<Action>`) |
| pagination | `page.pagination` (`Pagination`) |
| empty state | `page.empty` (`EmptyState`) when `page.rows` is empty |

| Data block | Default | Context |
| --- | --- | --- |
| `nowo_ui_list_head` | one `<th>` per column (sort links) | `page` |
| `nowo_ui_list_row` | one `<td>` per column: `row[column.key]` | `row`, `row_key`, `loop` |
| `nowo_ui_list_after` | empty (after the table / empty state) | `page` |

### Show

`page/show.html.twig` · `page: ShowPage` (`header`, `sections: list<DetailSection>`, `meta: list<DetailItem>`)

| Data block | Default | Context |
| --- | --- | --- |
| `nowo_ui_show_sections` | one card + definition list per `DetailSection` (booleans as ✓ / —, links via `DetailItem::href`) | `page` |
| `nowo_ui_show_after` | empty (related tables, history) | `page` |

### Form

`page/form.html.twig` · `page: FormPage` (`header`, `form`, `submitLabel`, `cancelHref`, `cancelLabel`,
`secondaryActions`, `deleteAction`). The form theme comes from the host / FormKit profile (`form_start` /
`form_end` with the `form` slot class).

| Data block | Default | Context |
| --- | --- | --- |
| `nowo_ui_form_body` | REQ-TWIG-003 loop: `form_row()` of every child not yet rendered (call `{{ parent() }}` after custom rows) | `form`, `page` |
| `nowo_ui_form_aside` | empty (side column after the main column) | `page` |

Submit + cancel render in the `form_actions` slot; `secondaryActions` in a `toolbar`; `deleteAction` in the
`danger_zone` through the confirm contract.

### Settings

`page/settings.html.twig` · `page: SettingsPage` (`header`, `sections: list<SettingsSection>`, `current`). Tabs render
when sections have `href`s (`tabs`, `tab`, `tab_active` slots); every visible section (the current one when tabs are
links) renders as a card.

| Data block | Default | Context |
| --- | --- | --- |
| `nowo_ui_settings_section` | `section.form` with the child loop and a submit button (`section.submitLabel`) | `section`, `page` |

### Dashboard

`page/dashboard.html.twig` · `page: DashboardPage` (`header`, `kpis: list<KpiTile>`). KPI tiles render in the `kpis`
/ `kpi` slots (`nowo-ui-kpi--<variant>`: `neutral`, `success`, `warning`, `danger`, `info`).

| Data block | Default | Context |
| --- | --- | --- |
| `nowo_ui_dashboard_cards` | empty, inside the `cards` grid — include `nowo_ui_partial('card')` per card (`title`, `body`, `actions`, `bundle: page.bundle`), or use the `card` / `card_header` / `card_body` slots | `page` |

### Confirm

`page/confirm.html.twig` · `page: ConfirmPage` (`header`, `confirm: Confirm`, `action: Action` (non-GET),
`cancelHref`). Full-page fallback of the confirm dialog (no JS): cancel link + POST form (CSRF `FormView` or token).
No data blocks.

### Wizard

`page/wizard.html.twig` · `page: WizardPage` (`header`, `steps: list<WizardStep>`, `current`, `form`, `backHref`,
`nextLabel`, `backLabel`). Steps indicator (`wizard_steps`, `wizard_step`, `wizard_step_active`; done steps link to
their `href`), "Step n of m", one card with the current step.

| Data block | Default | Context |
| --- | --- | --- |
| `nowo_ui_wizard_step` | `page.form` child loop + back link + next button | `step` (current `WizardStep`), `page` |

### Auth

`page/auth.html.twig` · `page: AuthPage` (`title`, `form`, `intro`, `brand`, `error`, `submitLabel`, `links:
list<Link>`) on the **guest** layout; panel in the `auth_panel` slot.

| Data block | Default | Context |
| --- | --- | --- |
| `nowo_ui_auth_fields` | child loop of `page.form` | `form`, `page` |
| `nowo_ui_auth_actions` | submit button (`page.submitLabel`) | `form`, `page` |
| `nowo_ui_auth_footer` | `page.links` | `page` |

## View models

All `final readonly` under `Nowo\UiKitBundle\View\`, no services (worker safe). `Label|string` fields take a
`Label(key, domain = 'messages', params = [])` that the scaffolds translate (REQ-I18N-003: pass the bundle domain),
or a plain string printed escaped. Never pre-render HTML into a view model.

| Class | Constructor |
| --- | --- |
| `Label` | `(key, domain = 'messages', params = [])`, `Label::kit(key)` (UiKit domain), `withParams()` |
| `PageHeader` | `(title, intro = null, actions = [], breadcrumbs = [], breadcrumbCollection = null)` |
| `Action` | `(kind = 'custom', label = null, href = null, route = null, params = [], method = 'GET', csrfToken = null, confirm = null, icon = null, variant = null, attributes = [], form = null, csrfField = '_token', modalId = null, disabled = false, visible = true)`; factories `create()`, `edit()`, `view()`, `delete()`, `route()`; `isPost()`, `resolvedVariant()`, `confirmId()`, `toArray()` |
| `Confirm` | `(title, message = null, confirmLabel = null, variant = 'danger', cancelLabel = null, id = null)`, `Confirm::delete(message = null)` |
| `Crumb` | `(label, href = null)` |
| `Link` | `(label, href, attributes = [])` |
| `Pagination` | `(page, perPage, total, totalPages = null, route = null, query = [], routeParams = null, pageParam = 'page', perPageParam = 'per_page')`; `fromArray()`, `fromPagerfanta()`, `fromKnp()` (duck typed); `from()`, `to()`, `hasPrevious()`, `hasNext()`, `toArray()` — the **only** pagination shape |
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

`bundle` selects the per-bundle theme exception (`theme.bundles.<bundle>`); `layout` is the bundle's
`web_ui.layout_template` override (null = `nowo_ui_layout.admin`, `.guest` for `AuthPage`).

`Action` kinds: `create`, `edit`, `delete`, `view`, `export`, `import`, `custom`, `copy`, `save`, `cancel`, `restore`,
`share`, `config`, `filter`, `prev`, `next`, `close`; variants `primary`, `secondary`, `danger`, `outline`, `link`.
Non-GET actions render a POST form (CSRF-only `FormView` in `form`, REQ-TWIG-005, or `csrfToken`); an action with a
`Confirm` renders a toggle button + UiKit confirm `<dialog>` (`nowo-ui-confirm.js`) — never an inline `confirm()`.

## Partials

New in 2.0: `_action` (one `Action`: link, modal trigger, POST form, confirm `<dialog>`), `_action_post`,
`_breadcrumbs`. Upgraded (1.x inputs unchanged): `_card` (`card`, `card_header`, `card_body`, `card_footer`, `cards`
slots; `Label` title / intro, `actions`, `id`, `attr`, `cards` grid), `_pagination` (`Pagination`), `_page_header` (`header`),
`_row_actions` (`Action` objects), `_confirm` (`confirm`, `action`), `_empty` (`empty`), `_toasts` / `_flashes`
(`trans_domain`, slot hooks), `_shell` / `_burger` (`root_class`, `label_attr_names`, `attr`, `header_class`,
`main_class`, `burger_*`). All accept `bundle`. Bundles include them through `nowo_ui_partial('<name>')` so a host
override applies everywhere — never a local copy. Names: [`THEME_SLOTS.md#partials`](THEME_SLOTS.md#partials).

## Tokens and CSS

`nowo-ui.css` paints every component from `--nowo-ui-*` tokens ([`THEMING.md`](THEMING.md)). Its palette is
declared on `:root` for hosts without tokens. With `<html data-nowo-ui-host-tokens>` (set by `layout/document` when
`theme.tokens` is configured, or by the host layout) the palette blocks have **zero specificity**, so the host's
`nowo_ui_theme_tokens()` output — or any host declaration — wins regardless of load order: no `!important` remaps.

### Cascade layers

`nowo-ui.css` ships inside the cascade layer **`nowo-ui`** and starts with the order statement
`@layer theme, base, nowo-ui, components, utilities;` (Tailwind v4 layer names). Consequences:

- **Tailwind v4 hosts** (Vite, `@import "tailwindcss"`): utilities placed in theme slots (`theme.classes.*`) beat the
  kit rules, and the kit still beats Tailwind's preflight. `nowo-ui` must stay **above `base`** — below it, the
  preflight reset of `<dialog>` breaks the confirm / row-detail dialog positioning. The order is fixed by the
  **first** stylesheet that names the layers, so either link `nowo-ui.css` before the Tailwind stylesheet (the
  UiKit document layout does: `nowo_ui_styles` prints it first, host styles follow with `{{ parent() }}`), or put the
  same statement at the very top of the host stylesheet:

  ```css
  /* assets/app.css */
  @layer theme, base, nowo-ui, components, utilities;
  @import "tailwindcss";
  ```

  Importing the kit from the host bundle works too:
  `@import "../public/bundles/nowouikit/css/nowo-ui.unlayered.css" layer(nowo-ui);` after the statement above.
- **Hosts without layers** (Bootstrap / Tabler from a CDN, a plain host theme file): unlayered CSS always beats
  layered CSS, so host rules win over the kit whatever their specificity or load order. That includes the framework:
  where 1.x kit rules overrode an unlayered framework rule of equal specificity (e.g. pagination `.page-link`
  colours, button paddings), the framework now wins. Hosts that want the 1.x cascade link
  **`nowo-ui.unlayered.css`** (same rules, no layer) instead of `nowo-ui.css`.

`nowo-ui.css` is generated from `nowo-ui.unlayered.css` (`pnpm run build:css`); token fallbacks on `:root` and the
`data-nowo-ui-host-tokens` zero-specificity palette work the same in both files.

## Example: a bundle page

Controller (data only):

```php
use Nowo\UiKitBundle\View\{Action, Column, Confirm, EmptyState, Label, ListPage, PageHeader, Pagination};

$t = static fn (string $key): Label => new Label($key, 'NowoHttpLogBundle');

return $this->render('@NowoHttpLog/admin/index.html.twig', ['page' => new ListPage(
    new PageHeader($t('http_log.title'), $t('http_log.intro'), [Action::route('export', 'nowo_http_log_export', label: $t('http_log.export'))]),
    [new Column('method', $t('http_log.method')), new Column('url', $t('http_log.url')), new Column('status', $t('http_log.status'), true, 'end')],
    $pager->getCurrentPageResults(),
    $filters->createView(),
    fn (HttpLog $log): array => [
        Action::view($this->generateUrl('nowo_http_log_show', ['id' => $log->getId()])),
        Action::delete($this->generateUrl('nowo_http_log_delete', ['id' => $log->getId()]), form: $this->csrfForm($log), confirm: Confirm::delete()),
    ],
    Pagination::fromPagerfanta($pager, 'nowo_http_log_index', $request->query->all()),
    new EmptyState($t('http_log.empty')),
    $this->generateUrl('nowo_http_log_index'),
    bundle: 'NowoHttpLogBundle',
    layout: $this->webUi['layout_template'],   // null = UiKit layout (nowo_ui_layout.admin)
)]);
```

Template (data blocks only, slots for anything styled):

```twig
{% extends '@NowoUiKit/page/list.html.twig' %}

{% block nowo_ui_list_row %}
    <td class="{{ nowo_ui_class('table_cell', page.bundle) }}"><code>{{ row.method }}</code></td>
    <td class="{{ nowo_ui_class('table_cell', page.bundle) }}">{{ row.url }}</td>
    <td class="{{ nowo_ui_class('table_cell', page.bundle) }} nowo-ui-cell--end">
        <span class="{{ nowo_ui_class(row.status >= 500 ? 'badge_danger' : 'badge_success', page.bundle) }}">{{ row.status }}</span>
    </td>
{% endblock %}
```

A unique page (kanban board, canvas) extends the layout directly and still fills the canonical blocks:

```twig
{% extends nowo_ui_layout.admin %}
{% set nowo_ui_bundle = 'NowoTaskBoardBundle' %}
{% block nowo_ui_document_title %}{{ 'board.title'|trans({}, 'NowoTaskBoardBundle') }}{% endblock %}
{% block nowo_ui_page_header %}{{ include(nowo_ui_partial('page_header', nowo_ui_bundle), {header: header, bundle: nowo_ui_bundle}) }}{% endblock %}
{% block nowo_ui_content %}<div class="{{ nowo_ui_class('cards', nowo_ui_bundle) }} nowo-task-board">…</div>{% endblock %}
```

## Example: host integration

1. **Layouts — once.** Point the layouts at your layout or a one-file bridge. A host with its own layout must render
   every canonical block, add `data-nowo-ui-host-tokens` on `<html>` and print `nowo_ui_theme_tokens()` after
   `nowo-ui.css`:

   ```yaml
   nowo_ui_kit:
     layouts: { admin: 'layouts/admin.html.twig', guest: 'layouts/guest.html.twig' }
   ```

   ```twig
   {# templates/layouts/admin.html.twig — bridge into the project base #}
   {% extends 'base.html.twig' %}
   {% block html_attributes %}{% if nowo_ui_theme.hasTokens() %} data-nowo-ui-host-tokens{% endif %}{% endblock %}
   {% block title %}{% block nowo_ui_document_title %}{% endblock %} · Acme{% endblock %}
   {% block stylesheets %}
       <link rel="stylesheet" href="{{ asset('css/nowo-ui.css', 'nowo_ui_kit') }}">
       {{ nowo_ui_theme_tokens() }}
       {{ parent() }}
       {% block nowo_ui_head %}{% endblock %}{% block nowo_ui_styles %}{% endblock %}
   {% endblock %}
   {% block body %}
       <aside>{% block nowo_ui_navigation %}{{ include('_sidebar.html.twig') }}{% endblock %}</aside>
       <main id="nowo-ui-content"{% if nowo_ui_bundle|default(null) %} data-nowo-ui-bundle="{{ nowo_ui_bundle }}"{% endif %}>
           {% block nowo_ui_page_header %}{% endblock %}
           {% block nowo_ui_flashes %}{{ include(nowo_ui_partial('flashes', nowo_ui_bundle|default(null)), {bundle: nowo_ui_bundle|default(null)}) }}{% endblock %}
           {% block nowo_ui_content %}{% endblock %}
       </main>
       {% block nowo_ui_modals %}{% endblock %}
   {% endblock %}
   {% block javascripts %}
       {{ parent() }}
       {% for s in ['nowo-ui-modal', 'nowo-ui-confirm', 'nowo-ui-toast', 'nowo-ui-tabs'] %}
           <script src="{{ asset('js/' ~ s ~ '.js', 'nowo_ui_kit') }}" defer></script>
       {% endfor %}
       {% block nowo_ui_scripts %}{% endblock %}
   {% endblock %}
   ```

2. **Theme — once.** `nowo_ui_kit.theme.tokens` (~15–20 tokens) + `theme.classes` for the slots your design system
   restyles, optionally `theme.partials` for a structural change (e.g. your page header). See
   [`THEME_SLOTS.md`](THEME_SLOTS.md).
3. **Scaffold overrides — optional, once per view type.** `templates/bundles/NowoUiKitBundle/page/list.html.twig`
   re-skins every bundle list page; keep the data block names and the `page` variable.
4. **No bundle page overrides.** For a genuinely bundle-specific need use the bundle's `web_ui.templates.<page>`
   with a thin `{% extends '@!NowoXxxBundle/…' %}` + block override, and record it in your override inventory.

The demo (`demo/symfony8`, `/page-contract`) is a complete host integration: bridges in
`templates/page_contract/layouts/`, theme in `config/packages/nowo_ui_kit.yaml`, partial override in
`templates/page_contract/theme/_page_header.html.twig`, host CSS in `public/css/acme-theme.css`.

| Host theme | Default theme |
| --- | --- |
| ![Orders list with the host theme](images/demo/page-contract-list.webp) | ![Orders list with UiKit's default theme](images/demo/page-contract-default-list.webp) |

Every scaffold with the host theme: [overview](images/demo/page-contract-overview.webp) ·
[list](images/demo/page-contract-list.webp) · [show](images/demo/page-contract-show.webp) ·
[form](images/demo/page-contract-form.webp) · [settings](images/demo/page-contract-settings.webp) ·
[dashboard](images/demo/page-contract-dashboard.webp) · [confirm](images/demo/page-contract-confirm.webp) ·
[wizard](images/demo/page-contract-wizard.webp) · [auth](images/demo/page-contract-auth.webp).

## Contract test

`Nowo\UiKitBundle\Test\PageContractTestCase` (shipped in `src/Test`, used as a **dev** dependency: PHPUnit is not a
runtime requirement of UiKit, so a bundle needs `phpunit/phpunit` — and `symfony/form` for form pages — in
`require-dev`). One test class per UI bundle:

```php
use Nowo\UiKitBundle\Test\{PageContractTestCase, PageFixture};

final class PageContractTest extends PageContractTestCase
{
    public static function pageFixtures(): iterable
    {
        yield 'index' => new PageFixture('@NowoHttpLog/admin/index.html.twig', ['page' => new ListPage(/* … */)]);
        yield 'show' => new PageFixture('@NowoHttpLog/admin/show.html.twig', ['page' => new ShowPage(/* … */)]);
    }

    protected static function templatePaths(): array { return ['NowoHttpLog' => \dirname(__DIR__).'/src/Resources/views']; }
    protected static function translationDirs(): array { return [\dirname(__DIR__).'/src/Resources/translations']; }
    protected static function templatesDocument(): ?string { return \dirname(__DIR__).'/docs/TEMPLATES.md'; }
}
```

Each fixture is rendered with a real Twig environment (UiKit + bundle views, translator with UiKit's and the bundle's
catalogues, Symfony Form when installed, stub `path` / `url` / `asset` / `csrf_token` / `is_granted` /
`logout_path`) inside a **stub host layout** (every canonical block wrapped in a marker, no `<html>`) and a **stub
theme** (marker class `pc-<slot>` and attribute `data-pc-<slot>` on every slot, one token, `page_header` partial
override). Six tests per fixture:

| Test | Fails when |
| --- | --- |
| `testPageExtendsAScaffoldOrIsDeclaredUnique` | no UiKit scaffold in the inheritance chain and the template is not under "Unique pages" in `docs/TEMPLATES.md`, or a unique page does not extend the layout |
| `testPageFillsTheCanonicalBlocks` | the page does not render through the host layout exactly once, a canonical block is missing, or `nowo_ui_document_title` / `nowo_ui_content` is empty |
| `testPageHasNoForbiddenMarkup` | `<html>` / `<head>` / `<body>` / doctype, CDN `<script src>` / `<link rel=stylesheet\|preload>`, inline `on*=`, `confirm(`, `javascript:` URLs |
| `testPageUsesFrameworkClassesOnlyThroughThemeSlots` | a Bootstrap / Tabler / Tailwind / Foundation class that is not a slot default of the rendered `css_framework` (rendered with every framework of `cssFrameworks()`, default `none`, `bootstrap5`, `tailwind`) |
| `testPageReflectsTheStubTheme` | no `pc-*` slot class or `data-pc-*` slot attribute in `nowo_ui_content`, or a page header that bypasses `nowo_ui_partial('page_header')` |
| `testPageHasNoRawTranslationKeys` | a dotted id the translator could not find, or a `*.label\|placeholder\|help\|title` key in the output |

Hooks: `templatePaths()`, `inlineTemplates()`, `translationDirs()`, `templatesDocument()`, `cssFrameworks()`,
`allowedClasses()` (documented exceptions), `formThemes()` (null disables the Form extension; add your FormKit
theme), `locale()`, `configureTwig(Environment)` (register the bundle's Twig extensions; functions added there
override the stubs). The assertion helpers (`assertExtendsScaffoldOrUnique()`, `assertCanonicalBlocksFilled()`,
`assertNoForbiddenMarkup()`, `assertNoFrameworkUtilityClasses()`, `assertStubThemeReflected()`,
`assertNoRawTranslationKeys()`) and `renderPage()` / `createTwig()` are protected for the bundle's own tests.
Fixtures are rendered several times; their `FormView`s are reset between renders.

## Compatibility and versioning

- Opt-in: hosts and bundles that do not use the layouts / scaffolds / theme see no change; 1.x partials and macros
  keep their inputs and output.
- Adding an optional view-model field, a slot, a partial name or a data block is **minor**; renaming or removing one,
  or changing scaffold markup a host may depend on, is **breaking** and documented under **"Template contract"** in
  [`UPGRADING.md`](UPGRADING.md) and the CHANGELOG.

## Deviations from REQ-UI-003

- Template namespace: config defaults use `@NowoUiKit/…` (Symfony's bundle namespace); internal includes keep
  `@NowoUiKitBundle/…` like the 1.x partials. Both resolve to the same files and host overrides.
- Token keys are the UiKit token names without the prefix (`primary`, `surface`, `radius`…); the spec example
  `color-primary` would emit `--nowo-ui-color-primary`, which the kit does not read. `tokens_dark` was added.
- `Action` POSTs prefer a CSRF-only `FormView` (`form`, REQ-TWIG-005); the `csrfToken` fallback renders a small
  `<form>` inside UiKit's own partial (as `_row_actions` already did).
- BreadcrumbKit renders its own configured trail (it cannot take `PageHeader::breadcrumbs`); it is used when
  installed and `breadcrumbs.breadcrumb_kit` is true, otherwise the plain trail.
- The 1.x pagination list markup (`ul.pagination > li.page-item > .page-link`) stays for every framework (BC); the
  contract test allows these three classes.
