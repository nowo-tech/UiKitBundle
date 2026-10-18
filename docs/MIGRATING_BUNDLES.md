# Migrating a UI bundle to the page contract (REQ-UI-003)

Recipe for a Nowo bundle that ships HTML pages (admin CRUD, panels, dashboards, guest pages) to adopt the UiKit 2.0
page contract: layouts, scaffolds, view models, theme slots, canonical config keys and the shared contract test. Each
bundle migrates in a **minor** release (old keys / chrome maps deprecated and bridged); the removals land in its next
major. Reference: [`PAGE_CONTRACT.md`](PAGE_CONTRACT.md), [`THEME_SLOTS.md`](THEME_SLOTS.md), REQ-UI-003 and the
migration matrix in `BUNDLES_UI_PAGE_CONTRACT.md`.

## Table of contents

- [0. Prerequisites](#0-prerequisites)
- [1. Inventory the pages](#1-inventory-the-pages)
- [2. Controller → view model](#2-controller--view-model)
- [3. Template → scaffold + data blocks](#3-template--scaffold--data-blocks)
- [4. Utility classes → theme slots](#4-utility-classes--theme-slots)
- [5. Local chrome map → theme.bundles bridge](#5-local-chrome-map--themebundles-bridge)
- [6. Canonical configuration keys (aliases)](#6-canonical-configuration-keys-aliases)
- [7. Contract test](#7-contract-test)
- [8. docs/TEMPLATES.md](#8-docstemplatesmd)
- [9. Demo, changelog and checklist](#9-demo-changelog-and-checklist)

## 0. Prerequisites

```json
"require": { "nowo-tech/ui-kit-bundle": "^2.0" },
"require-dev": { "phpunit/phpunit": "^11.0", "symfony/form": "^7.4 || ^8.0" }
```

`Nowo\UiKitBundle\Test\PageContractTestCase` lives in UiKit's `src/Test` but UiKit does not require PHPUnit at
runtime: the bundle brings it in `require-dev` (plus `symfony/form` when pages render forms).

## 1. Inventory the pages

List every page template and map it to a view type; anything that does not fit (canvas, kanban, gantt, PDF
workspace, chat) is a **unique page**.

| Today | View type → scaffold |
| --- | --- |
| `admin/index.html.twig` (table + filters + pagination) | list → `page/list` |
| `admin/show.html.twig` | show → `page/show` |
| `admin/new.html.twig`, `admin/edit.html.twig` | form → `page/form` (one template is usually enough) |
| `admin/settings.html.twig` (tabs) | settings → `page/settings` |
| `admin/dashboard.html.twig` | dashboard → `page/dashboard` |
| `admin/delete.html.twig` (full-page confirm) | confirm → `page/confirm` (or drop it: row actions get the confirm dialog) |
| `setup/step*.html.twig` | wizard → `page/wizard` |
| `security/login.html.twig`, `reset.html.twig` | auth → `page/auth` (guest layout) |
| `board.html.twig` | unique → extends `nowo_ui_layout.admin`, listed in `docs/TEMPLATES.md` |

Also list the local chrome to delete: `_pagination`, `_flashes`, `_page_header`, `_confirm` / inline `confirm()`,
`_modal`, `_empty`, filter panels, default layouts loading CSS frameworks from CDNs, per-framework template copies.

## 2. Controller → view model

Before — the controller passes loose variables and a layout:

```php
return $this->render('@NowoXxx/admin/index.html.twig', [
    'layout' => $this->config['templates']['layout'],
    'items' => $pager, 'page' => $page, 'pages' => $pages, 'filters' => $form->createView(),
]);
```

After — one view model, labels as translation keys of the bundle domain, pagination in the single shape:

```php
use Nowo\UiKitBundle\View\{Action, Column, Confirm, EmptyState, Label, ListPage, PageHeader, Pagination};

$t = static fn (string $key): Label => new Label($key, 'NowoXxxBundle');

return $this->render('@NowoXxx/admin/index.html.twig', ['page' => new ListPage(
    new PageHeader($t('xxx.items.title'), null, [Action::route('create', 'nowo_xxx_new', label: $t('xxx.items.new'))]),
    [new Column('name', $t('xxx.items.name'), sortable: true), new Column('updatedAt', $t('xxx.items.updated'))],
    $pager->getCurrentPageResults(),
    $filters->createView(),
    fn (Item $item): array => [
        Action::edit($this->generateUrl('nowo_xxx_edit', ['id' => $item->getId()])),
        Action::delete($this->generateUrl('nowo_xxx_delete', ['id' => $item->getId()]), form: $this->deleteForm($item)->createView(), confirm: Confirm::delete()),
    ],
    Pagination::fromPagerfanta($pager, 'nowo_xxx_index', $request->query->all()),
    new EmptyState($t('xxx.items.empty')),
    $this->generateUrl('nowo_xxx_index'),
    $request->query->getString('sort') ?: null,
    $request->query->getString('direction', 'asc'),
    bundle: 'NowoXxxBundle',
    layout: $this->webUi['layout_template'],      // null (default) = UiKit layout
)]);
```

Rules: view models are plain data (no services, no request objects — worker safe); never pre-render HTML into
them; destructive actions are non-GET `Action`s with a `Confirm` (CSRF-only `FormView` preferred).

**POST actions backed by a form: name it.** Symfony treats a form as *submitted* only when its name is in the
request (`$form->isSubmitted()`). An **unnamed** form (`createNamedBuilder('')` / `createFormBuilder()` with no
name) whose only field is the CSRF token posts `_token` at the top level, but nothing tells the form it was submitted
— `isSubmitted()` stays false and the delete silently does nothing. Use a named form
(`createNamedBuilder('delete_item')`, the token posts as `delete_item[_token]`) or add a hidden marker field
(`->add('confirm', HiddenType::class, ['data' => '1'])`; since 2.2 hidden fields always print their `value`, even an
empty one), and assert `isSubmitted() && isValid()` in a functional test of the delete route.

## 3. Template → scaffold + data blocks

Before — the bundle owns the whole page:

```twig
{% extends layout %}
{% block body %}
  <div class="d-flex justify-content-between mb-3"><h1 class="h3">{{ 'items.title'|trans }}</h1>…</div>
  {% for type, messages in app.flashes %}<div class="alert alert-{{ type }}">…</div>{% endfor %}
  <table class="table table-hover">… <form onsubmit="return confirm('Sure?')">…</form> …</table>
  {% include '@NowoXxx/_pagination.html.twig' %}
{% endblock %}
```

After — only the data the scaffold cannot know:

```twig
{% extends '@NowoUiKit/page/list.html.twig' %}

{% block nowo_ui_list_row %}
    <td class="{{ nowo_ui_class('table_cell', page.bundle) }}" data-label="{{ 'items.name'|trans({}, 'NowoXxxBundle') }}"><a href="{{ path('nowo_xxx_show', {id: row.id}) }}">{{ row.name }}</a></td>
    <td class="{{ nowo_ui_class('table_cell', page.bundle) }}" data-label="{{ 'items.updated'|trans({}, 'NowoXxxBundle') }}" data-nowo-ui-priority="2">{{ row.updatedAt|date('Y-m-d H:i') }}</td>
{% endblock %}
```

- Overridden cells carry `data-label` (card mode of responsive lists) and, for optional columns,
  `data-nowo-ui-priority` matching `Column::priority`; the default cells do it for you.
- Test ids: put `attributes: ['data-testid' => 'xxx-list']` on the page model (scaffold root) and target the
  standard regions with `[data-nowo-ui-region="table|filters|pagination|…"]` instead of adding markup.
- Filters: per-field placeholder / width through `ListPage::filterFields` (`FilterField`), apply / clear / extra
  actions through `filtersSubmit`, `filtersClear`, `filterActions`; replace the whole region with the
  `nowo_ui_list_filters` block only when the form needs custom layout.
- Destructive actions: `Action::delete(…, confirm: Confirm::delete())`; type-to-confirm with
  `new Confirm(…, expectedValue: $item->getName())`. Form pages: secondary actions land in the page header.
- Row confirm dialogs: let UiKit derive the ids (they cover the target, token, Confirm text and row key); set
  `Confirm::$id` only when a test or script needs a fixed id, and then one id per distinct dialog (a shared id with
  different content throws). Tests that look up a row dialog use `$action->withConfirmScope($rowKey)->confirmId()`.
- Icon-only buttons: `display: 'icon'` on the `Action` (toolbar, header, card or row) or `ListPage::filtersDisplay`
  for the filter buttons — never a hand-written `<a><i></i></a>`; the label stays for screen readers. Primary list
  actions next to the filters: `headerActionsInFilters: true` (or the host's `theme.pages.list` option).
- Variants: leave `variant` null for plain actions (they resolve to `neutral`, slot `action_neutral`); set
  `secondary` only when the action is deliberately secondary. Actions column alignment: `ListPage::actionsAlign`.
- Pagination: pass a `Pagination`; filters and sort in the request query are kept across pages automatically
  (`Pagination::query` adds or overrides keys).

- Header, actions, breadcrumbs, flashes, filters, table frame, row actions, confirm dialogs, pagination and empty
  state now come from the scaffold. Delete the local partials.
- Data blocks per scaffold: [`PAGE_CONTRACT.md#scaffolds`](PAGE_CONTRACT.md#scaffolds). In `nowo_ui_form_body`
  render the custom rows first and finish with `{{ parent() }}` (REQ-TWIG-003 loop for the rest).
- Stack assets with `{% block nowo_ui_scripts %}{{ parent() }}…{% endblock %}`; never a CDN, never inline `on*=`.
- Unique pages extend `nowo_ui_layout.admin` (or `page.layout ?? nowo_ui_layout.admin`), set `nowo_ui_bundle`, fill
  `nowo_ui_document_title`, `nowo_ui_page_header` (2.3: `{{ nowo_ui_page_header(header) }}` — the scaffolds' header;
  do not copy the private scaffold macro) and `nowo_ui_content`.
- Do not add a layout Twig global of your own; `web_ui.layout_template` reaches the scaffold through the view
  model's `layout`.
- Form themes (2.1): pass the bundle's form theme(s) as `FormPage::formThemes` / `SettingsSection::formThemes`
  instead of overriding `nowo_ui_content` to call `form_theme`.
- Area navigation (2.1): a tab bar between the sections of the bundle area is `PageHeader::tabs` (`AreaNav`), not a
  local `_tabs` include in a data block; publish it through an `AreaNavProviderInterface` so DashboardMenu can reuse
  it. 1.x `<nav>` attributes (e.g. `data-turbo="false"`) go in `AreaNav::$attributes` / the `tabs` partial `attr`.
- Modals: prefer the framework-free `dialog` partial (`data-nowo-ui-dialog-open="<id>"`) over `modal_shell`; confirm
  actions can add a no-JS `Action::fallbackHref` to a `page/confirm` page.

## 4. Utility classes → theme slots

Every framework class in bundle markup becomes a slot (or a `nowo-ui-*` / bundle-prefixed semantic class):

| Markup today | After |
| --- | --- |
| `class="btn btn-primary"` | `class="{{ nowo_ui_class('btn_primary', page.bundle) }}"` |
| `class="table table-sm"` | `nowo_ui_class('table', …)` (density via `theme.bundles.<Bundle>.classes.table`) |
| `class="badge bg-success"` | `nowo_ui_class('badge_success', …)` |
| `class="card"` / `card-body` | `nowo_ui_class('card', …)` / `nowo_ui_class('card_body', …)` |
| `class="d-flex gap-2 mb-3"` around actions | `nowo_ui_class('toolbar', …)` |
| `class="alert alert-danger"` | `nowo_ui_class('flash_error', …)` (or the `flashes` partial) |
| `class="card-title"` / `card-subtitle` in a card heading | `nowo_ui_class('card_title', …)` / `nowo_ui_class('card_intro', …)` (or the `card` partial) |
| `<dl class="row">` with `col-sm-3` / `col-sm-9` | the `detail_list` partial (`detail_item` / `detail_term` / `detail_value` slots; stacked layout via `nowo-ui-detail-list--stacked`) |
| `btn btn-link` on a toolbar link button | `Action` `variant: 'link'` (slot `btn_link`) or `nowo_ui_class('btn_link', …)` |
| `text-muted` / `text-gray-500` helper text | `nowo_ui_class('muted', …)`; empty list cells use `table_cell_muted` |
| breadcrumb link / separator classes | `breadcrumb_link` / `breadcrumb_separator` slots (the `breadcrumbs` partial) |
| sortable header / sort link classes | `sortable_header` / `sort_link` slots (list scaffold) |
| `opacity-50 pointer-events-none` on disabled row actions | `row_action_disabled` slot |
| modal panel / header / body / footer classes | `modal_panel` / `modal_header` / `modal_body` / `modal_footer` (the `modal_shell` partial) |
| local Bootstrap modal markup (`modal fade` …) | the `dialog` partial (`dialog_*` slots; `modal_shell` stays for BC and its structure classes are allowed by the contract test) |
| `text-muted` on dialog body text | `dialog` partial `body_muted: true` (slot `dialog_body_muted`) or `nowo-ui-confirm__body--muted` |
| `w-100` / `mw-100` / `max-w-*` on wide form fields | `nowo_ui_class('input_wide', …)` / `nowo_ui_class('input_full', …)` |
| `<code>` / `<pre>` with `bg-light p-2 small` for payloads | `nowo_ui_class('code', …)` / `nowo_ui_class('code_block', …)` |
| local confirm modal markup | the `confirm` partial (`confirm_*` slots; title + message header: `Confirm::messageInHeader`) |
| full-page delete confirm template | `page/confirm` (root slot `confirm_page`, never the dialog slot `confirm`) |
| audit / meta `<dl>` under a show page | `ShowPage::meta` (slots `detail_list` + `detail_list_meta`) |
| per-framework template copies (`_x_bootstrap`, `_x_tailwind`) | one template with slots |

Inside scaffolds the page type is implicit (`nowo_ui_page_type`), so `theme.pages.<type>` variations reach your
data blocks too. Pass `page.bundle` (or `nowo_ui_bundle`) so per-bundle exceptions apply. The vocabulary and the defaults per
framework: [`THEME_SLOTS.md`](THEME_SLOTS.md). Missing slot? Propose it in UiKit (minor) instead of hard-coding.

## 5. Local chrome map → theme.bundles bridge

Bundles must not keep their own class / attribute maps (`AdminChrome`, `PanelChrome`, `DashboardUiConfig`…). For one
minor version, keep accepting the old config, mark it deprecated, and **prepend** it into UiKit's theme as the
bundle's exception so existing hosts render the same:

```php
// NowoXxxExtension implements PrependExtensionInterface
public function prepend(ContainerBuilder $container): void
{
    $config = $this->processConfiguration(new Configuration(), $container->getExtensionConfig($this->getAlias()));
    $classes = [];
    foreach (['button_primary' => 'btn_primary', 'table' => 'table', 'card' => 'card'] as $old => $slot) {
        if (null !== ($config['chrome'][$old] ?? null)) {
            $classes[$slot] = $config['chrome'][$old];
        }
    }
    if ([] !== $classes) {
        trigger_deprecation('nowo-tech/xxx-bundle', '1.9', 'nowo_xxx.chrome is deprecated, use nowo_ui_kit.theme.bundles.NowoXxxBundle.classes.');
        // prepended: a host's own nowo_ui_kit.theme.bundles.NowoXxxBundle config still wins
        $container->prependExtensionConfig('nowo_ui_kit', ['theme' => ['bundles' => ['NowoXxxBundle' => ['classes' => $classes]]]]);
    }
}
```

Map old keys to slots one by one (document the mapping table in UPGRADING). Old maps whose values were complete
framework classes (`btn btn-primary`) usually become **nothing**: the slot's framework default already provides
them; only the host-specific part goes into the bridge. Remove the chrome service and its Twig global in the next
major.

## 6. Canonical configuration keys (aliases)

Canonical keys (REQ-UI-003 "Configuration vocabulary"): `web_ui.enabled`, `web_ui.path_prefix`,
`web_ui.layout_template` (default `null` → UiKit layout), `web_ui.templates.<page>`, `security.access_roles` /
`access_checker` / `allow_unauthenticated`; CSS stack and row-action display are inherited from `nowo_ui_kit`. Keep
the old names for one minor version with `setDeprecated()` and map them before normalization:

```php
$root
    ->beforeNormalization()
        ->ifTrue(static fn (mixed $v): bool => \is_array($v) && isset($v['templates']['layout']))
        ->then(static function (array $v): array {
            $v['web_ui']['layout_template'] ??= $v['templates']['layout'];
            unset($v['templates']['layout']);

            return $v;
        })
    ->end()
    ->children()
        ->arrayNode('templates')
            ->children()
                ->scalarNode('layout')->setDeprecated('nowo-tech/xxx-bundle', '1.9', 'Use "web_ui.layout_template" (null = UiKit layout).')->end()
            ->end()
        ->end()
        ->arrayNode('web_ui')->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')->defaultTrue()->end()
                ->scalarNode('path_prefix')->defaultValue('/admin/xxx')->end()
                ->scalarNode('layout_template')->defaultNull()->end()
                ->arrayNode('templates')->useAttributeAsKey('page')->scalarPrototype()->end()->end()
            ->end()
        ->end()
    ->end();
```

Per-bundle `css_framework`, `row_actions_display`, `include_ui_kit_css` and hard-coded form themes
(`bootstrap_5_layout.html.twig`) go away: read `nowo_ui_kit` / the FormKit profile instead (keep the old keys as
deprecated overrides if hosts set them).

## 7. Contract test

```php
namespace Nowo\XxxBundle\Tests\Integration;

use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;

final class PageContractTest extends PageContractTestCase
{
    public static function pageFixtures(): iterable
    {
        yield 'index' => new PageFixture('@NowoXxx/admin/index.html.twig', ['page' => Fixtures::listPage()]);
        yield 'index (empty)' => new PageFixture('@NowoXxx/admin/index.html.twig', ['page' => Fixtures::emptyListPage()]);
        yield 'edit' => new PageFixture('@NowoXxx/admin/edit.html.twig', ['page' => Fixtures::formPage()]);
        yield 'board' => new PageFixture('@NowoXxx/board.html.twig', ['header' => Fixtures::header(), 'columns' => []]);
    }

    protected static function templatePaths(): array
    {
        return ['NowoXxx' => \dirname(__DIR__, 2).'/src/Resources/views'];
    }

    protected static function translationDirs(): array
    {
        return [\dirname(__DIR__, 2).'/src/Resources/translations'];
    }

    protected static function templatesDocument(): ?string
    {
        return \dirname(__DIR__, 2).'/docs/TEMPLATES.md';
    }

    protected function configureTwig(\Twig\Environment $twig): void
    {
        $twig->addExtension(new \Nowo\XxxBundle\Twig\XxxExtension(/* stubs */));
    }
}
```

One fixture per page (and per significant state: empty list, form with errors). The test renders each page against a
stub host layout and a stub theme and fails on: no scaffold / undeclared unique page, missing canonical blocks,
`<html>` / CDN / `on*=` / `confirm(`, framework classes outside slots (frameworks `none`, `bootstrap5`, `tailwind` by
default), stub theme not reflected, raw keys or any missing message (2.3: also text translated twice — pass already translated
text to `form_widget()` / `form_row()` with `translation_domain: false`, or the untranslated `Label` through
`|nowo_ui_translatable`; choice labels with `choice_translation_domain: false`). Details and hooks:
[`PAGE_CONTRACT.md#contract-test`](PAGE_CONTRACT.md#contract-test). A documented exception goes in
`allowedClasses()` with a note in `docs/TEMPLATES.md` (not for the `modal_shell` structure classes, allowed since 2.1).

**FormKit and translation keys.** With FormKit's automatic placeholder / help convention every field resolves keys
such as `<form>.<field>.placeholder` and `<form>.<field>.help`; the contract test flags each one that is missing from
the bundle catalogues (and any `*.label|placeholder|help|title` key printed raw). Either ship a translation for every
field the convention touches, set `convention_requires_translation: true` in the FormKit profile (the convention only
applies when a translation exists), or opt fields out explicitly (`help: false`, `placeholder: false`). Run the test
with the bundle's real FormKit theme (`formThemes()` hook) so it sees the same keys as production.

**Collections.** Pages with `CollectionType` fields (`prototype: true`) need UiKit 2.1+: earlier versions did not
reset `vars.prototype` between the contract test's renders, so the second render lost `data-prototype` or threw
"already rendered".

**Header actions with a `FormView`.** Since 2.1 the stub host's `page_header` override renders `actions` itself (like
most host overrides) and a seventh test checks that every rendered form of a `FormView`-backed `Action` keeps its
hidden fields (CSRF token) exactly once. Render actions through `nowo_ui_partial('action')`, never with your own
`form_start` / `form_end` on a FormView the scaffold may already have rendered.

## 8. docs/TEMPLATES.md

Every UI bundle ships `docs/TEMPLATES.md` with this shape (the contract test parses the backticked `*.twig` names
under the **Unique pages** heading, including its sub-headings, until the next heading of the same level):

```markdown
# Templates

## Pages

| Page | Template | Scaffold | View model | Data blocks filled |
| --- | --- | --- | --- | --- |
| Items list | `admin/index.html.twig` | `page/list` | `ListPage` (columns: name, updatedAt; rowActions: edit, delete) | `nowo_ui_list_row` |
| Item form | `admin/edit.html.twig` | `page/form` | `FormPage` (deleteAction on edit) | `nowo_ui_form_aside` |
| Sign in | `security/login.html.twig` | `page/auth` | `AuthPage` | `nowo_ui_auth_footer` |

## Unique pages

- `board.html.twig` — kanban board; extends `nowo_ui_layout.admin`, fills `nowo_ui_page_header` and `nowo_ui_content`.

## Per-page overrides

`nowo_xxx.web_ui.templates.<page>` (`index`, `edit`, `login`): a host template that extends `@!NowoXxx/…` and
overrides a data block.

## Allowed exceptions

- (none) — or the classes returned by `allowedClasses()` in the contract test and why.
```

## 9. Demo, changelog and checklist

- Demo: use the UiKit layouts (or a host bridge) and the stub-like host theme of UiKit's demo; refresh the WebP
  screenshots (REQ-DEMO-013).
- CHANGELOG + UPGRADING: a **"Template contract"** subsection listing pages → scaffolds, removed local partials,
  deprecated keys and chrome maps with their replacements; any later change to blocks, view-model fields or markup
  goes there too.
- Checklist (REQ-UI-003 cell): ✅ when every page extends a scaffold or is declared unique, the contract test
  passes, canonical keys (with aliases) are in place and no local pagination / flash / confirm / modal partials
  remain; ⚠️ while partially migrated.
