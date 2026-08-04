<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\DependencyInjection;

use Nowo\UiKitBundle\Enum\CssFramework;
use Nowo\UiKitBundle\Enum\IconSet;
use Nowo\UiKitBundle\Enum\RowActionsDisplay;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public const ALIAS = 'nowo_ui_kit';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ALIAS);
        $root = $treeBuilder->getRootNode();

        $root
            ->children()
                ->enumNode('css_framework')
                    ->values(CssFramework::values())
                    ->defaultValue(CssFramework::Bootstrap5->value)
                    ->info('Host CSS stack: bootstrap5|bootstrap4|tailwind|foundation|custom|none|tabler (bootstrap alias → bootstrap5).')
                ->end()
                ->enumNode('icon_set')
                    ->values(IconSet::values())
                    ->defaultValue(IconSet::BootstrapIcons->value)
                    ->info('Icon rendering: bootstrap-icons|tabler-icons|ux_icon|svg_inline|none.')
                ->end()
                ->enumNode('row_actions_display')
                    ->values(RowActionsDisplay::values())
                    ->defaultValue(RowActionsDisplay::Icon->value)
                    ->info('Table/list row actions: icon (glyph only) | text (label only) | icon_text (both).')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
