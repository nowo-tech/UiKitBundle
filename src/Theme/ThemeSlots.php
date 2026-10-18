<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Theme;

/**
 * Single source of the theme slot vocabulary (REQ-UI-003): semantic `nowo-ui-*` class per slot plus the UiKit
 * framework defaults per `css_framework` family. Adding a slot is a minor change; renaming or removing one is
 * breaking ("Template contract" in UPGRADING).
 *
 * Framework families: `bootstrap` (bootstrap5, bootstrap, tabler), `bootstrap4` (falls back to `bootstrap`),
 * `tailwind`, `foundation`; `custom` and `none` get the semantic class only. Defaults mirror the
 * `macros/ui.html.twig` helpers for the slots that have one, so scaffolds and macros paint the same markup.
 * The tailwind family only carries layout / spacing / type-scale utilities (no palette, no radius).
 */
final class ThemeSlots
{
    /**
     * slot => [semantic, framework family => default classes].
     *
     * @var array<string, array{0: string, 1: array<string, string>}>
     */
    public const SLOTS = [
        // Document / layout
        'document' => ['nowo-ui-document', []],
        'body' => ['nowo-ui-body', []],
        'page' => ['nowo-ui-page', []],
        'navigation' => ['nowo-ui-navigation', []],
        'content' => ['nowo-ui-content', []],
        'shell' => ['nowo-ui-shell', []],
        'burger' => ['nowo-ui-burger nowo-ui-btn nowo-ui-btn--secondary nowo-ui-btn--sm', [
            'bootstrap' => 'btn btn-outline-secondary btn-sm',
            'tailwind' => 'inline-flex items-center gap-1 font-medium px-2 py-1 text-sm border',
            'foundation' => 'button secondary tiny',
        ]],
        'breadcrumbs' => ['nowo-ui-breadcrumbs', []],
        // Plain breadcrumb trail (_breadcrumbs without BreadcrumbKit): crumb links and the separators between items
        'breadcrumb_link' => ['nowo-ui-breadcrumbs__link', []],
        'breadcrumb_separator' => ['nowo-ui-breadcrumbs__separator', []],
        // Page header
        'page_header' => ['nowo-ui-page-header', [
            'bootstrap' => 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3',
            'tailwind' => 'flex flex-wrap items-start justify-between gap-2 mb-3',
            'foundation' => 'grid-x grid-margin-x align-middle mb-3',
        ]],
        'page_title' => ['nowo-ui-page-header__title', [
            'bootstrap' => 'h3 mb-1',
            'tailwind' => 'text-xl font-semibold mb-1',
        ]],
        'page_intro' => ['nowo-ui-page-header__intro', [
            'bootstrap' => 'mb-0',
            'tailwind' => 'text-sm',
        ]],
        'toolbar' => ['nowo-ui-toolbar', [
            'bootstrap' => 'd-flex flex-wrap align-items-center gap-1 mb-2',
            'tailwind' => 'flex flex-wrap items-center gap-1 mb-2',
            'foundation' => 'grid-x grid-margin-x align-middle mb-2',
        ]],
        // Buttons (variants are complete button classes; btn_sm is a size modifier)
        'btn' => ['nowo-ui-btn', [
            'bootstrap' => 'btn',
            'tailwind' => 'inline-flex items-center gap-1 font-medium px-3 py-1.5 text-sm',
            'foundation' => 'button',
        ]],
        'btn_primary' => ['nowo-ui-btn nowo-ui-btn--primary', [
            'bootstrap' => 'btn btn-primary',
            'tailwind' => 'inline-flex items-center gap-1 font-medium px-3 py-1.5 text-sm',
            'foundation' => 'button primary',
        ]],
        'btn_secondary' => ['nowo-ui-btn nowo-ui-btn--secondary', [
            'bootstrap' => 'btn btn-outline-secondary',
            'tailwind' => 'inline-flex items-center gap-1 font-medium px-3 py-1.5 text-sm border',
            'foundation' => 'button secondary',
        ]],
        'btn_danger' => ['nowo-ui-btn nowo-ui-btn--danger', [
            'bootstrap' => 'btn btn-outline-danger',
            'tailwind' => 'inline-flex items-center gap-1 font-medium px-3 py-1.5 text-sm border',
            'foundation' => 'button alert',
        ]],
        // Link-styled button (Action variant `link`, toolbar context)
        'btn_link' => ['nowo-ui-btn nowo-ui-btn--link', [
            'bootstrap' => 'btn btn-link',
            'tailwind' => 'inline-flex items-center gap-1 font-medium px-3 py-1.5 text-sm underline-offset-2 px-0',
            'foundation' => 'button secondary',
        ]],
        // Solid destructive button (2.2): the confirm button of a danger Confirm (dialog and full page)
        'btn_danger_solid' => ['nowo-ui-btn nowo-ui-btn--danger nowo-ui-btn--solid', [
            'bootstrap' => 'btn btn-danger',
            'tailwind' => 'inline-flex items-center gap-1 font-medium px-3 py-1.5 text-sm',
            'foundation' => 'button alert',
        ]],
        'btn_sm' => ['nowo-ui-btn--sm', [
            'bootstrap' => 'btn-sm',
            'foundation' => 'tiny',
        ]],
        // Icon-only toolbar button modifier (Action::display = icon; label visually hidden + aria-label)
        'btn_icon' => ['nowo-ui-btn--icon', []],
        // Tables / lists
        'table_wrap' => ['nowo-ui-table-wrap', [
            'bootstrap' => 'table-responsive',
            'tailwind' => 'overflow-x-auto',
        ]],
        'table' => ['nowo-ui-table nowo-ui-table--striped', [
            'bootstrap' => 'table table-striped table-hover align-middle',
            'tailwind' => 'min-w-full text-sm text-left',
            'foundation' => 'stack table',
        ]],
        // <caption> of a list table (ListPage::caption; visually hidden with ListPage::captionVisible = false)
        'table_caption' => ['nowo-ui-table__caption', []],
        'table_head' => ['nowo-ui-table__head', []],
        'table_head_row' => ['nowo-ui-table__head-row', []],
        'table_head_cell' => ['nowo-ui-table__head-cell', []],
        // Header cell of a sortable column (added to table_head_cell) and its sort link (+ nowo-ui-sort--asc|desc)
        'sortable_header' => ['nowo-ui-table__head-cell--sortable', []],
        'sort_link' => ['nowo-ui-sort', []],
        'table_actions_head' => ['nowo-ui-table__actions', []],
        // Row-actions body cell (alignment: ListPage::actionsAlign → nowo-ui-cell--<align> on head and cell)
        'table_actions_cell' => ['nowo-ui-table__actions', []],
        'table_row' => ['nowo-ui-table__row', []],
        'table_cell' => ['nowo-ui-table__cell', []],
        // Default list cell without a value (null / ''): added to table_cell, the cell prints a muted "—"
        'table_cell_muted' => ['nowo-ui-table__cell--muted', [
            'bootstrap' => 'text-muted',
            'foundation' => 'text-secondary',
        ]],
        'row_actions' => ['nowo-ui-row-actions', [
            'bootstrap' => 'd-flex gap-1',
            'tailwind' => 'flex gap-1',
            'foundation' => 'button-group',
        ]],
        'action' => ['nowo-ui-action', []],
        // Disabled row action (Action::disabled / 1.x hash `disabled`): added to the action classes in row context
        'row_action_disabled' => ['nowo-ui-action--disabled', []],
        // Row actions per variant (resolved like Action::resolvedVariant(): delete = danger, create / save = primary,
        // else neutral; explicit variants as given): host classes added on top of the `action` slot in row context;
        // no framework defaults (the row action keeps the `ui.action` classes, governed by the `action` slot's
        // framework_defaults). Neutral kinds paint like secondary (same macro classes) but read their own slot.
        'action_primary' => ['nowo-ui-btn--primary', []],
        'action_secondary' => ['nowo-ui-btn--secondary', []],
        'action_danger' => ['nowo-ui-btn--danger', []],
        'action_neutral' => ['nowo-ui-btn--secondary', []],
        // Badges (2.2): one subtle set (tinted background, emphasis text, tinted border) on every stack — Bootstrap
        // 5.3 `bg-*-subtle text-*-emphasis border-*-subtle` (on 5.0–5.2 the semantic nowo-ui-badge--* palette paints
        // it), Bootstrap 4 its badge colours; no margin utilities (badges sit flush in cells and lists). `info` and
        // `primary` follow the primary colour. Hosts wanting solid badges set the slots (docs/THEME_SLOTS.md#badges).
        'badge' => ['nowo-ui-badge', [
            'bootstrap' => 'badge bg-body-tertiary text-body-emphasis border',
            'bootstrap4' => 'badge badge-light border',
            'tailwind' => 'inline-flex border px-2 py-0.5 text-xs',
            'foundation' => 'badge secondary',
        ]],
        'badge_primary' => ['nowo-ui-badge nowo-ui-badge--primary', [
            'bootstrap' => 'badge bg-primary-subtle text-primary-emphasis border border-primary-subtle',
            'bootstrap4' => 'badge badge-primary',
            'tailwind' => 'inline-flex border px-2 py-0.5 text-xs',
            'foundation' => 'badge primary',
        ]],
        'badge_success' => ['nowo-ui-badge nowo-ui-badge--success', [
            'bootstrap' => 'badge bg-success-subtle text-success-emphasis border border-success-subtle',
            'bootstrap4' => 'badge badge-success',
            'tailwind' => 'inline-flex border px-2 py-0.5 text-xs',
            'foundation' => 'badge success',
        ]],
        'badge_warning' => ['nowo-ui-badge nowo-ui-badge--warning', [
            'bootstrap' => 'badge bg-warning-subtle text-warning-emphasis border border-warning-subtle',
            'bootstrap4' => 'badge badge-warning',
            'tailwind' => 'inline-flex border px-2 py-0.5 text-xs',
            'foundation' => 'badge warning',
        ]],
        'badge_danger' => ['nowo-ui-badge nowo-ui-badge--danger', [
            'bootstrap' => 'badge bg-danger-subtle text-danger-emphasis border border-danger-subtle',
            'bootstrap4' => 'badge badge-danger',
            'tailwind' => 'inline-flex border px-2 py-0.5 text-xs',
            'foundation' => 'badge alert',
        ]],
        'badge_info' => ['nowo-ui-badge nowo-ui-badge--info', [
            'bootstrap' => 'badge bg-primary-subtle text-primary-emphasis border border-primary-subtle',
            'bootstrap4' => 'badge badge-info',
            'tailwind' => 'inline-flex border px-2 py-0.5 text-xs',
            'foundation' => 'badge primary',
        ]],
        'badge_neutral' => ['nowo-ui-badge nowo-ui-badge--neutral', [
            'bootstrap' => 'badge bg-body-tertiary text-body-secondary border',
            'bootstrap4' => 'badge badge-light border',
            'tailwind' => 'inline-flex border px-2 py-0.5 text-xs',
            'foundation' => 'badge secondary',
        ]],
        // Filters / inputs
        // List filters form (2.2: a responsive grid painted by nowo-ui.css — fields auto-fit, actions bottom-aligned)
        'filters' => ['nowo-ui-filters nowo-ui-search', [
            'bootstrap' => 'd-grid gap-3 mb-3',
            'tailwind' => 'grid gap-3 mb-4',
        ]],
        'filters_actions' => ['nowo-ui-filters__actions', [
            'bootstrap' => 'd-flex flex-wrap align-items-end gap-2',
            'tailwind' => 'flex flex-wrap items-end gap-2',
        ]],
        'filters_field' => ['nowo-ui-filters__field', []],
        'input' => ['nowo-ui-input', [
            'bootstrap' => 'form-control',
            'tailwind' => 'block w-full max-w-xs border px-3 py-1.5 text-sm',
        ]],
        // Wide / full-width inputs (2.1): complete input class lists (use instead of `input`, like the btn variants)
        // Checkbox / radio (2.2): the <label> wrapping box + text (form theme @NowoUiKit/form/nowo_ui_layout.html.twig)
        // and the <input> itself
        'check' => ['nowo-ui-check', [
            'bootstrap' => 'form-check',
            'tailwind' => 'inline-flex items-center gap-2',
        ]],
        'check_input' => ['nowo-ui-check__input', [
            'bootstrap' => 'form-check-input',
        ]],
        // Invalid field (2.3, UiKit form theme): added to the widget classes of a field with errors (+ aria-invalid)
        'input_invalid' => ['nowo-ui-input--invalid', [
            'bootstrap' => 'is-invalid',
        ]],
        // Field error messages (2.3, UiKit form theme `form_errors` of a field) and the root form errors box (CSRF,
        // global errors: auth scaffold, above the fields)
        'form_error' => ['nowo-ui-form-error', [
            'bootstrap' => 'invalid-feedback d-block',
            'tailwind' => 'text-sm mt-1',
        ]],
        'form_errors' => ['nowo-ui-form-errors', []],
        'input_wide' => ['nowo-ui-input nowo-ui-input--wide', [
            'bootstrap' => 'form-control',
            'tailwind' => 'block w-full max-w-xl border px-3 py-1.5 text-sm',
        ]],
        'input_full' => ['nowo-ui-input nowo-ui-input--full', [
            'bootstrap' => 'form-control',
            'tailwind' => 'block w-full border px-3 py-1.5 text-sm',
        ]],
        // Pagination
        'pagination' => ['nowo-ui-pagination', [
            'bootstrap' => 'd-flex flex-wrap align-items-center justify-content-between gap-2 mt-3',
            'tailwind' => 'flex flex-wrap items-center justify-between gap-2 mt-3',
        ]],
        'pagination_link' => ['nowo-ui-pagination__link', ['bootstrap' => 'page-link']],
        'pagination_active' => ['nowo-ui-pagination__link is-active', ['bootstrap' => 'page-link']],
        'pagination_info' => ['nowo-ui-pagination__info', []],
        // Code: inline <code> (identifiers, short values) and <pre> blocks (payloads, JSON, logs). Monospace,
        // muted surface, scrollable block (max height) — tokens --nowo-ui-font-mono, --nowo-ui-code-*
        // Bootstrap 5 paints <code> pink through --bs-code-color, which nowo-ui.css points at --nowo-ui-code-text
        // (2.3); Bootstrap 4 has a literal colour: `text-body` (2.3)
        'code' => ['nowo-ui-code', [
            'bootstrap4' => 'text-body',
            'tailwind' => 'font-mono text-sm',
        ]],
        'code_block' => ['nowo-ui-code-block', [
            'bootstrap' => 'border p-3',
            'tailwind' => 'font-mono text-sm p-3 border',
        ]],
        // Feedback
        // Secondary text (ui.muted()): the "—" placeholder of empty detail-list values
        'muted' => ['nowo-ui-muted', [
            'bootstrap' => 'text-muted',
            'foundation' => 'text-secondary',
        ]],
        'empty' => ['nowo-ui-empty nowo-ui-muted', [
            'bootstrap' => 'text-muted',
            'foundation' => 'text-secondary',
        ]],
        'flashes' => ['nowo-ui-flashes', []],
        'flash' => ['nowo-ui-flash', []],
        'flash_success' => ['nowo-ui-flash nowo-ui-flash--success', [
            'bootstrap' => 'alert alert-success',
            'tailwind' => 'px-4 py-3 text-sm mb-2 border',
            'foundation' => 'callout success',
        ]],
        'flash_error' => ['nowo-ui-flash nowo-ui-flash--error', [
            'bootstrap' => 'alert alert-danger',
            'tailwind' => 'px-4 py-3 text-sm mb-2 border',
            'foundation' => 'callout alert',
        ]],
        'flash_warning' => ['nowo-ui-flash nowo-ui-flash--warning', [
            'bootstrap' => 'alert alert-warning',
            'tailwind' => 'px-4 py-3 text-sm mb-2 border',
            'foundation' => 'callout warning',
        ]],
        'flash_info' => ['nowo-ui-flash nowo-ui-flash--info', [
            'bootstrap' => 'alert alert-info',
            'tailwind' => 'px-4 py-3 text-sm mb-2 border',
            'foundation' => 'callout alert',
        ]],
        'toasts' => ['nowo-ui-toast-stack', []],
        // Progress (2.2, partial `progress`): wrapper (label + value) and the native <progress> meter
        'progress' => ['nowo-ui-progress-field', []],
        'progress_meter' => ['nowo-ui-progress-meter', []],
        // Surfaces
        'card' => ['nowo-ui-card', [
            'bootstrap' => 'card',
            'tailwind' => 'border',
            'foundation' => 'callout',
        ]],
        'card_header' => ['nowo-ui-card__header', [
            'bootstrap' => 'card-header d-flex flex-wrap align-items-start justify-content-between gap-2',
            'tailwind' => 'flex flex-wrap items-start justify-between gap-2 border-b px-4 py-3',
        ]],
        'card_body' => ['nowo-ui-card__body', [
            'bootstrap' => 'card-body',
            'tailwind' => 'px-4 py-3',
        ]],
        'card_footer' => ['nowo-ui-card__footer', [
            'bootstrap' => 'card-footer',
            'tailwind' => 'border-t px-4 py-3',
        ]],
        'card_title' => ['nowo-ui-card__title', []],
        'card_intro' => ['nowo-ui-card__intro', []],
        'cards' => ['nowo-ui-cards', []],
        'section' => ['nowo-ui-section', [
            'bootstrap' => 'mb-4',
            'tailwind' => 'mb-6',
        ]],
        'detail_list' => ['nowo-ui-detail-list', []],
        // Detail list rows (term + value); add `nowo-ui-detail-list--stacked` to detail_list for label over value
        'detail_item' => ['nowo-ui-detail-list__item', []],
        'detail_term' => ['nowo-ui-detail-list__term', []],
        'detail_value' => ['nowo-ui-detail-list__value', []],
        // Show scaffold audit meta list (ShowPage::meta): added to the detail_list classes of that list
        'detail_list_meta' => ['nowo-ui-detail-list--meta', []],
        'kpis' => ['nowo-ui-kpis', []],
        'kpi' => ['nowo-ui-kpi', [
            'bootstrap' => 'card',
            'tailwind' => 'border',
            'foundation' => 'callout',
        ]],
        'kpi_label' => ['nowo-ui-kpi__label', []],
        'kpi_value' => ['nowo-ui-kpi__value', []],
        'kpi_hint' => ['nowo-ui-kpi__hint', []],
        // Whole-tile KPI link (KpiTile::linkTile): classes on the tile <a> only
        'kpi_link' => ['nowo-ui-kpi--link', []],
        'tabs' => ['nowo-ui-tabs', [
            'bootstrap' => 'nav nav-tabs mb-3',
            'tailwind' => 'flex flex-wrap gap-2 border-b mb-3',
            'foundation' => 'tabs',
        ]],
        'tab' => ['nowo-ui-tabs__item', [
            'bootstrap' => 'nav-link',
            'tailwind' => 'px-3 py-2 text-sm no-underline',
            'foundation' => 'tabs-title',
        ]],
        'tab_active' => ['nowo-ui-tabs__item nowo-ui-tabs__item--active', [
            'bootstrap' => 'nav-link active',
            'tailwind' => 'px-3 py-2 text-sm no-underline border-b-2',
            'foundation' => 'tabs-title is-active',
        ]],
        // Second tab level (2.2): section tabs under an area nav (PageHeader::tabs), e.g. the settings sections —
        // pills / sub-nav instead of underlined tabs. The `tabs` partial uses it with `level: 'secondary'`.
        'tabs_secondary' => ['nowo-ui-tabs nowo-ui-tabs--secondary', [
            'bootstrap' => 'nav nav-pills mb-3',
            'tailwind' => 'flex flex-wrap gap-2 mb-3',
            'foundation' => 'menu',
        ]],
        // Floating widgets (2.3, partial `menu`): native <details> disclosure root, its <summary> toggle, the item
        // list panel and one item (link / button); `popover` is the panel of a menu with free content
        'menu' => ['nowo-ui-menu', []],
        'menu_toggle' => ['nowo-ui-menu__toggle', [
            'bootstrap' => 'btn btn-outline-secondary btn-sm',
            'tailwind' => 'inline-flex items-center gap-1 font-medium px-2 py-1 text-sm border',
            'foundation' => 'button secondary tiny',
        ]],
        'menu_panel' => ['nowo-ui-menu__panel', []],
        'menu_item' => ['nowo-ui-menu__item', []],
        'popover' => ['nowo-ui-popover', []],
        'modal' => ['nowo-ui-modal', []],
        // _modal_shell internals (1.x markup: the Bootstrap structure classes modal-content / modal-header /
        // modal-body / modal-footer stay; the theme adds host classes and attributes)
        'modal_panel' => ['nowo-ui-modal__panel', []],
        'modal_header' => ['nowo-ui-modal__header', []],
        'modal_body' => ['nowo-ui-modal__body', []],
        'modal_footer' => ['nowo-ui-modal__footer', []],
        // Generic native <dialog> (partial `dialog`, 2.1): framework-free alternative to modal_shell
        'dialog' => ['nowo-ui-dialog', []],
        'dialog_header' => ['nowo-ui-dialog__header', []],
        'dialog_title' => ['nowo-ui-dialog__title', []],
        'dialog_body' => ['nowo-ui-dialog__body', []],
        // Muted dialog body text (dialog partial `body_muted`), added to dialog_body; framework-free
        'dialog_body_muted' => ['nowo-ui-dialog__body--muted', []],
        'dialog_footer' => ['nowo-ui-dialog__footer', []],
        // Confirm <dialog> root (dialog partial only: never on the full-page confirm, so host dialog CSS such as
        // `:not([open]) { display: none }` cannot hide the page)
        'confirm' => ['nowo-ui-confirm', []],
        // Full-page confirm root (page/confirm.html.twig; next to the `card` slot)
        'confirm_page' => ['nowo-ui-confirm-page', []],
        // Confirm internals (dialog partial and full-page confirm scaffold; on the page only the host classes are added
        // to its own wrappers, the dialog box styles of the semantic classes stay dialog-only)
        'confirm_panel' => ['nowo-ui-confirm__panel', []],
        'confirm_header' => ['nowo-ui-confirm__header', []],
        'confirm_title' => ['nowo-ui-confirm__title', []],
        // Close button (✕) of a 2.0 confirm dialog header (2.2; `dismissible`, default on for View\Confirm input)
        'confirm_close' => ['nowo-ui-confirm__close', []],
        'confirm_body' => ['nowo-ui-confirm__body', []],
        'confirm_message' => ['nowo-ui-confirm__message', []],
        // Type-to-confirm wrapper (label + input; Confirm::expectedValue)
        'confirm_expect' => ['nowo-ui-confirm__expect', []],
        'confirm_expect_label' => ['nowo-ui-confirm__expect-label', []],
        'confirm_footer' => ['nowo-ui-confirm__footer', []],
        // Forms
        'form' => ['nowo-ui-form', []],
        'form_panel' => ['nowo-ui-form-panel', []],
        'form_actions' => ['nowo-ui-form__actions', [
            'bootstrap' => 'd-flex flex-wrap gap-2 mt-3',
            'tailwind' => 'flex flex-wrap gap-2 mt-4',
        ]],
        'danger_zone' => ['nowo-ui-danger-zone', [
            'bootstrap' => 'mt-4',
            'tailwind' => 'mt-6',
        ]],
        'danger_zone_title' => ['nowo-ui-danger-zone__title', []],
        'danger_zone_intro' => ['nowo-ui-danger-zone__intro', []],
        // Wizard / auth
        'wizard_steps' => ['nowo-ui-wizard-steps', []],
        'wizard_step' => ['nowo-ui-wizard-steps__item', []],
        'wizard_step_active' => ['nowo-ui-wizard-steps__item nowo-ui-wizard-steps__item--active', []],
        'auth_panel' => ['nowo-ui-auth-panel', [
            'bootstrap' => 'card',
            'tailwind' => 'border',
            'foundation' => 'callout',
        ]],
        'auth_footer' => ['nowo-ui-auth-panel__footer', []],
        'auth_links' => ['nowo-ui-auth-panel__links', []],
    ];

    /**
     * Page types (one per scaffold): keys of `theme.pages`, values of the `nowo_ui_page_type` Twig variable the
     * scaffolds set for slot resolution.
     *
     * @var list<string>
     */
    public const PAGE_TYPES = ['list', 'show', 'form', 'settings', 'dashboard', 'confirm', 'wizard', 'auth'];

    /**
     * UiKit runtime scripts printed by the `scripts` partial (`js/nowo-ui-<name>.js`, 2.2): what the document layout
     * loads, and what a host layout with its own script list must load ({{ include(nowo_ui_partial('scripts')) }}).
     *
     * @var list<string>
     */
    public const SCRIPTS = ['modal', 'confirm', 'dialog', 'toast', 'tabs', 'theme'];

    /**
     * Partial name => default template (replaceable through `theme.partials`).
     *
     * @var array<string, string>
     */
    public const PARTIALS = [
        'action' => '@NowoUiKitBundle/partials/_action.html.twig',
        'breadcrumbs' => '@NowoUiKitBundle/partials/_breadcrumbs.html.twig',
        'burger' => '@NowoUiKitBundle/partials/_burger.html.twig',
        'card' => '@NowoUiKitBundle/partials/_card.html.twig',
        'confirm' => '@NowoUiKitBundle/partials/_confirm.html.twig',
        'detail_list' => '@NowoUiKitBundle/page/_detail_list.html.twig',
        'dialog' => '@NowoUiKitBundle/partials/_dialog.html.twig',
        'empty' => '@NowoUiKitBundle/partials/_empty.html.twig',
        'filters' => '@NowoUiKitBundle/partials/_filters.html.twig',
        'flashes' => '@NowoUiKitBundle/partials/_flashes.html.twig',
        'list_filters' => '@NowoUiKitBundle/page/_list_filters.html.twig',
        'menu' => '@NowoUiKitBundle/partials/_menu.html.twig',
        'modal_shell' => '@NowoUiKitBundle/partials/_modal_shell.html.twig',
        'page_header' => '@NowoUiKitBundle/partials/_page_header.html.twig',
        'pagination' => '@NowoUiKitBundle/partials/_pagination.html.twig',
        'progress' => '@NowoUiKitBundle/partials/_progress.html.twig',
        'row_actions' => '@NowoUiKitBundle/partials/_row_actions.html.twig',
        'scripts' => '@NowoUiKitBundle/partials/_scripts.html.twig',
        'section_form' => '@NowoUiKitBundle/page/_section_form.html.twig',
        'shell' => '@NowoUiKitBundle/partials/_shell.html.twig',
        'table' => '@NowoUiKitBundle/partials/_table.html.twig',
        'tabs' => '@NowoUiKitBundle/partials/_tabs.html.twig',
        'toasts' => '@NowoUiKitBundle/partials/_toasts.html.twig',
        'wizard_form' => '@NowoUiKitBundle/page/_wizard_form.html.twig',
    ];

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::SLOTS);
    }

    public static function has(string $slot): bool
    {
        return isset(self::SLOTS[$slot]);
    }

    /**
     * The semantic `nowo-ui-*` class(es) of a slot.
     */
    public static function semantic(string $slot): string
    {
        return self::slot($slot)[0];
    }

    /**
     * UiKit framework defaults of a slot for a `css_framework` value ('' for custom / none / no default).
     */
    public static function defaults(string $slot, string $framework): string
    {
        $defaults = self::slot($slot)[1];
        $family = self::family($framework);
        if (null === $family) {
            return '';
        }

        return $defaults[$family] ?? ('bootstrap4' === $family ? ($defaults['bootstrap'] ?? '') : '');
    }

    /**
     * Framework family used to look up defaults, null when the stack gets semantic classes only.
     */
    public static function family(string $framework): ?string
    {
        return match ($framework) {
            'bootstrap', 'bootstrap5', 'tabler' => 'bootstrap',
            'bootstrap4' => 'bootstrap4',
            'tailwind' => 'tailwind',
            'foundation' => 'foundation',
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function partialNames(): array
    {
        return array_keys(self::PARTIALS);
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private static function slot(string $slot): array
    {
        if (!isset(self::SLOTS[$slot])) {
            throw new \InvalidArgumentException(\sprintf('Unknown UiKit theme slot "%s" (known: %s).', $slot, implode(', ', self::names())));
        }

        return self::SLOTS[$slot];
    }
}
