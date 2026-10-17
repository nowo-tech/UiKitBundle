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
            'bootstrap' => 'table table-hover align-middle',
            'tailwind' => 'min-w-full text-sm text-left',
            'foundation' => 'stack table',
        ]],
        'table_head' => ['nowo-ui-table__head', []],
        'table_head_row' => ['nowo-ui-table__head-row', []],
        'table_head_cell' => ['nowo-ui-table__head-cell', []],
        'table_actions_head' => ['nowo-ui-table__actions', []],
        'table_row' => ['nowo-ui-table__row', []],
        'table_cell' => ['nowo-ui-table__cell', []],
        'row_actions' => ['nowo-ui-row-actions', [
            'bootstrap' => 'd-flex gap-1',
            'tailwind' => 'flex gap-1',
            'foundation' => 'button-group',
        ]],
        'action' => ['nowo-ui-action', []],
        // Row actions per variant (resolved like Action::resolvedVariant(): delete = danger, create / save = primary,
        // else secondary): host classes added on top of the `action` slot in row context; no framework defaults
        // (the row action keeps the `ui.action` classes, governed by the `action` slot's framework_defaults)
        'action_primary' => ['nowo-ui-btn--primary', []],
        'action_secondary' => ['nowo-ui-btn--secondary', []],
        'action_danger' => ['nowo-ui-btn--danger', []],
        // Badges (Bootstrap: `text-bg-*` (5.2+) sets text and background together, so the colour pair never mixes
        // with the semantic nowo-ui-badge--* palette; on 5.0 / 5.1 the semantic palette paints the badge)
        'badge' => ['nowo-ui-badge', [
            'bootstrap' => 'badge text-bg-light border ms-2',
            'bootstrap4' => 'badge badge-light border ml-2',
            'tailwind' => 'ml-2 inline-flex border px-2 py-0.5 text-xs',
            'foundation' => 'badge secondary',
        ]],
        'badge_success' => ['nowo-ui-badge nowo-ui-badge--success', [
            'bootstrap' => 'badge text-bg-success ms-2',
            'bootstrap4' => 'badge badge-success ml-2',
            'tailwind' => 'ml-2 inline-flex border px-2 py-0.5 text-xs',
            'foundation' => 'badge success',
        ]],
        'badge_warning' => ['nowo-ui-badge nowo-ui-badge--warning', [
            'bootstrap' => 'badge text-bg-warning ms-2',
            'bootstrap4' => 'badge badge-warning ml-2',
            'tailwind' => 'ml-2 inline-flex border px-2 py-0.5 text-xs',
            'foundation' => 'badge warning',
        ]],
        'badge_danger' => ['nowo-ui-badge nowo-ui-badge--danger', [
            'bootstrap' => 'badge text-bg-danger ms-2',
            'bootstrap4' => 'badge badge-danger ml-2',
            'tailwind' => 'ml-2 inline-flex border px-2 py-0.5 text-xs',
            'foundation' => 'badge alert',
        ]],
        'badge_info' => ['nowo-ui-badge nowo-ui-badge--info', [
            'bootstrap' => 'badge text-bg-info ms-2',
            'bootstrap4' => 'badge badge-info ml-2',
            'tailwind' => 'ml-2 inline-flex border px-2 py-0.5 text-xs',
            'foundation' => 'badge secondary',
        ]],
        'badge_neutral' => ['nowo-ui-badge nowo-ui-badge--neutral', [
            'bootstrap' => 'badge text-bg-light border ms-2',
            'bootstrap4' => 'badge badge-light border ml-2',
            'tailwind' => 'ml-2 inline-flex border px-2 py-0.5 text-xs',
            'foundation' => 'badge secondary',
        ]],
        // Filters / inputs
        'filters' => ['nowo-ui-filters nowo-ui-search', [
            'bootstrap' => 'd-flex flex-wrap gap-2 mb-3',
            'tailwind' => 'flex flex-wrap gap-2 mb-4',
            'foundation' => 'grid-x grid-margin-x mb-3',
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
        // Pagination
        'pagination' => ['nowo-ui-pagination', [
            'bootstrap' => 'd-flex flex-wrap align-items-center justify-content-between gap-2 mt-3',
            'tailwind' => 'flex flex-wrap items-center justify-between gap-2 mt-3',
        ]],
        'pagination_link' => ['nowo-ui-pagination__link', ['bootstrap' => 'page-link']],
        'pagination_active' => ['nowo-ui-pagination__link is-active', ['bootstrap' => 'page-link']],
        'pagination_info' => ['nowo-ui-pagination__info', []],
        // Feedback
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
        'modal' => ['nowo-ui-modal', []],
        'confirm' => ['nowo-ui-confirm', []],
        // Confirm internals (dialog partial and full-page confirm scaffold; on the page only the host classes are added
        // to its own wrappers, the dialog box styles of the semantic classes stay dialog-only)
        'confirm_panel' => ['nowo-ui-confirm__panel', []],
        'confirm_header' => ['nowo-ui-confirm__header', []],
        'confirm_title' => ['nowo-ui-confirm__title', []],
        'confirm_body' => ['nowo-ui-confirm__body', []],
        'confirm_message' => ['nowo-ui-confirm__message', []],
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
        'empty' => '@NowoUiKitBundle/partials/_empty.html.twig',
        'filters' => '@NowoUiKitBundle/partials/_filters.html.twig',
        'flashes' => '@NowoUiKitBundle/partials/_flashes.html.twig',
        'list_filters' => '@NowoUiKitBundle/page/_list_filters.html.twig',
        'modal_shell' => '@NowoUiKitBundle/partials/_modal_shell.html.twig',
        'page_header' => '@NowoUiKitBundle/partials/_page_header.html.twig',
        'pagination' => '@NowoUiKitBundle/partials/_pagination.html.twig',
        'row_actions' => '@NowoUiKitBundle/partials/_row_actions.html.twig',
        'section_form' => '@NowoUiKitBundle/page/_section_form.html.twig',
        'shell' => '@NowoUiKitBundle/partials/_shell.html.twig',
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
