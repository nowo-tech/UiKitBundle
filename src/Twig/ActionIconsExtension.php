<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Twig;

use Nowo\UiKitBundle\View\Action;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Twig globals `nowo_ui_kit_action_icons` and `nowo_ui_kit_action_variants`: the opt-in `nowo_ui_kit.action_icons` and
 * `nowo_ui_kit.action_variants` maps read by `components/_icon.html.twig`, `macros/ui.html.twig` and the action partials.
 *
 * A service rather than a `twig.globals` entry: it gets the processed config from this bundle's own load(), so the
 * values do not depend on whether TwigBundle is registered before or after this bundle.
 */
final class ActionIconsExtension extends AbstractExtension implements GlobalsInterface
{
    /** The `web_ui.icons.*` grammar of the other kits (YopassBundle `TAB_ICON_PATTERN`): `[prefix:]name`. */
    public const NAME_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*(?::[a-z0-9]+(?:-[a-z0-9]+)*)?$/';

    public const VARIANTS = ['primary', 'secondary', 'danger'];

    /** The canonical kinds with a default in every icon set of `components/_icon.html.twig`. */
    public const ICON_KINDS = [
        'edit', 'delete', 'view', 'copy', 'create', 'save', 'cancel', 'restore', 'share', 'config', 'export', 'filter',
        'prev', 'next', 'close', 'filter_off', 'scroll_horizontal', 'rows', 'chevron_up', 'chevron_down', 'chevron_right',
        'book', 'sliders', 'grip', 'stack', 'tour', 'sort', 'sort_asc', 'sort_desc', 'more', 'home', 'grid', 'chevron_left',
        'extend', 'revoke', 'refresh', 'archive', 'pause', 'resume', 'download', 'import', 'send', 'sign_in', 'sign_out',
        'reset', 'mark_done', 'mark_all_done', 'move', 'enable', 'disable', 'hide', 'run', 'retry', 'stop',
    ];

    /**
     * @param array<string, string> $icons    kind => `[prefix:]name`
     * @param array<string, string> $variants kind => primary|secondary|danger
     */
    public function __construct(private readonly array $icons = [], private readonly array $variants = [])
    {
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getGlobals(): array
    {
        return ['nowo_ui_kit_action_icons' => $this->icons, 'nowo_ui_kit_action_variants' => $this->variants];
    }

    /**
     * The keys the two maps accept: every icon kind and every {@see Action} kind.
     *
     * @return list<string>
     */
    public static function kinds(): array
    {
        return array_values(array_unique([...self::ICON_KINDS, ...Action::KINDS]));
    }
}
