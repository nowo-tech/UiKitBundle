<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\DependencyInjection;

use Nowo\UiKitBundle\Enum\CssFramework;
use Nowo\UiKitBundle\Enum\IconSet;
use Nowo\UiKitBundle\Enum\RowActionsDisplay;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

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
                ->arrayNode('panel_path_rewrites')
                    ->info('Map of legacy path prefix → new prefix (e.g. /admin/blog: /panel/blog). Rewrites route paths (longest prefix first) and adds 301 redirects for legacy request paths. Empty = disabled.')
                    ->useAttributeAsKey('from', false)
                    ->normalizeKeys(false)
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                    ->validate()
                        ->always(static function (array $map): array {
                            $error = self::findInvalidRewrite($map);
                            if (null !== $error) {
                                throw new InvalidConfigurationException('Invalid nowo_ui_kit.panel_path_rewrites: '.$error);
                            }

                            return $map;
                        })
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    /**
     * Returns a description of the first invalid entry, or null when the map is valid.
     *
     * @param array<mixed> $map
     */
    public static function findInvalidRewrite(array $map): ?string
    {
        foreach ($map as $from => $to) {
            if (!\is_string($to) || !str_starts_with((string) $from, '/') || !str_starts_with($to, '/')) {
                return \sprintf('both prefixes must be strings starting with "/" ("%s" => "%s").', $from, \is_string($to) ? $to : get_debug_type($to));
            }

            $fromNorm = '/'.trim((string) $from, '/');
            $toNorm = '/'.trim($to, '/');
            if ($fromNorm === $toNorm || str_starts_with($toNorm, $fromNorm.'/')) {
                return \sprintf('target "%s" must differ from and not be nested under source "%s" (would cause redirect loops).', $to, $from);
            }
        }

        return null;
    }
}
