<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Test;

use Nowo\UiKitBundle\Theme\ThemeSlots;

/**
 * The stub host of {@see PageContractTestCase}: host layouts that render every canonical block inside a marker
 * element (no `<html>`), and a stub theme with a distinct marker class (`pc-<slot>`) and attribute
 * (`data-pc-<slot>`) for every slot, one token and one partial override (`page_header`). The override renders the
 * 2.0 `actions` input itself through the `action` partial (like a typical host override), after the scaffold has
 * pre-rendered the 1.x `toolbar`: FormView-backed header actions render twice and must keep their hidden fields.
 */
final class StubHost
{
    public const NAMESPACE = 'NowoUiKitContract';

    public const LAYOUT_ADMIN = '@NowoUiKitContract/layout/admin.html.twig';

    public const LAYOUT_GUEST = '@NowoUiKitContract/layout/guest.html.twig';

    /** Partial replaced by the stub theme; scaffolds must render the page header through nowo_ui_partial(). */
    public const PARTIAL = 'page_header';

    public const PARTIAL_TEMPLATE = '@NowoUiKitContract/partials/_page_header.html.twig';

    /** Canonical blocks of the admin layout, in render order (the guest layout has no navigation). */
    public const ADMIN_BLOCKS = ['nowo_ui_document_title', 'nowo_ui_head', 'nowo_ui_styles', 'nowo_ui_navigation', 'nowo_ui_page_header', 'nowo_ui_flashes', 'nowo_ui_content', 'nowo_ui_modals', 'nowo_ui_scripts'];

    public const GUEST_BLOCKS = ['nowo_ui_document_title', 'nowo_ui_head', 'nowo_ui_styles', 'nowo_ui_page_header', 'nowo_ui_flashes', 'nowo_ui_content', 'nowo_ui_modals', 'nowo_ui_scripts'];

    /**
     * Marker class of a slot in the stub theme.
     */
    public static function slotClass(string $slot): string
    {
        return 'pc-'.str_replace('_', '-', $slot);
    }

    /**
     * Marker attribute of a slot in the stub theme.
     */
    public static function slotAttribute(string $slot): string
    {
        return 'data-pc-'.str_replace('_', '-', $slot);
    }

    /**
     * Processed `nowo_ui_kit.theme` config of the stub theme.
     *
     * @return array{classes: array<string, string>, attributes: array<string, array<string, string>>, partials: array<string, string>, tokens: array<string, string>}
     */
    public static function theme(): array
    {
        $classes = [];
        $attributes = [];
        foreach (ThemeSlots::names() as $slot) {
            $classes[$slot] = self::slotClass($slot);
            $attributes[$slot] = [self::slotAttribute($slot) => '1'];
        }

        return [
            'classes' => $classes,
            'attributes' => $attributes,
            'partials' => [self::PARTIAL => self::PARTIAL_TEMPLATE],
            'tokens' => ['primary' => '#5b3cc4'],
        ];
    }

    /**
     * In-memory templates of the stub host (layouts + partial override).
     *
     * @return array<string, string>
     */
    public static function templates(): array
    {
        return [
            self::LAYOUT_ADMIN => self::layout('admin', self::ADMIN_BLOCKS),
            self::LAYOUT_GUEST => self::layout('guest', self::GUEST_BLOCKS),
            self::PARTIAL_TEMPLATE => <<<'TWIG'
                {% set _bundle = bundle|default(null) %}
                <header class="pc-partial-page-header" data-nowo-ui-contract-partial="page_header">
                    <h1>{{ header is defined and header ? header.title|nowo_ui_trans : title|default('') }}</h1>
                    {% for action in actions|default(header is defined and header ? header.actions : []) %}
                        {{ include(nowo_ui_partial('action', _bundle), {action: action, context: 'toolbar', bundle: _bundle, nowo_ui_page_type: nowo_ui_page_type|default(null)}, false) }}
                    {% endfor %}
                </header>
                TWIG,
        ];
    }

    /**
     * @param list<string> $blocks
     */
    private static function layout(string $kind, array $blocks): string
    {
        $defaults = [
            'nowo_ui_styles' => '{{ nowo_ui_tokens() }}',
            'nowo_ui_flashes' => '{{ include(nowo_ui_partial(\'flashes\', nowo_ui_bundle|default(null)), {bundle: nowo_ui_bundle|default(null)}) }}',
        ];
        $twig = '<div data-nowo-ui-contract-layout="'.$kind.'"{% if nowo_ui_bundle|default(null) %} data-nowo-ui-bundle="{{ nowo_ui_bundle }}"{% endif %}>'."\n";
        foreach ($blocks as $block) {
            $twig .= \sprintf('<div data-nowo-ui-contract-block="%1$s">{%% block %1$s %%}%2$s{%% endblock %%}</div>', $block, $defaults[$block] ?? '')."\n";
        }

        return $twig.'</div>'."\n";
    }
}
