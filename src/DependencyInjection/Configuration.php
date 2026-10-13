<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\DependencyInjection;

use Nowo\UiKitBundle\Appearance\AppearanceCssEmitter;
use Nowo\UiKitBundle\Appearance\AppearancePalette;
use Nowo\UiKitBundle\Appearance\AppearancePreset;
use Nowo\UiKitBundle\Appearance\BorderStrength;
use Nowo\UiKitBundle\Appearance\BuiltInAppearancePresets;
use Nowo\UiKitBundle\Appearance\CornerStyle;
use Nowo\UiKitBundle\Enum\CssFramework;
use Nowo\UiKitBundle\Enum\IconSet;
use Nowo\UiKitBundle\Enum\RowActionsDisplay;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
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
                ->arrayNode('release_check')
                    ->info('Optional GitHub release-version checker (footer dialog + JSON endpoint). Off by default.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                            ->info('Register the checker service + controller. Also import @NowoUiKitBundle/Resources/config/routes/release_check.yaml.')
                        ->end()
                        ->scalarNode('github_repo')
                            ->defaultValue('')
                            ->info('Public GitHub repository "owner/name" whose releases are compared (e.g. nowo-tech/my-app).')
                        ->end()
                        ->scalarNode('current_version')
                            ->defaultValue('')
                            ->info('Installed version label, clean semver x.y.z with optional "v" (e.g. "%app.version%"). Non-semver values skip the check.')
                        ->end()
                        ->integerNode('cache_ttl')
                            ->defaultValue(43200)
                            ->min(60)
                            ->info('Seconds to cache the GitHub answer (cache.app). Default 12h.')
                        ->end()
                        ->scalarNode('default_branch')
                            ->defaultValue('main')
                            ->info('Branch used as compare target when the installed version is already the latest.')
                        ->end()
                        ->scalarNode('user_agent')
                            ->defaultValue('nowo-ui-kit-release-check')
                            ->info('User-Agent sent to api.github.com.')
                        ->end()
                    ->end()
                ->end()
            ->end();

        $this->addAppearanceSection($root);

        return $treeBuilder;
    }

    private function addAppearanceSection(ArrayNodeDefinition $root): void
    {
        $palette = static function (string $name): ArrayNodeDefinition {
            $node = new ArrayNodeDefinition($name);
            $children = $node->children();
            foreach (AppearancePalette::KEYS as $key) {
                $children->scalarNode($key)
                    ->validate()
                        ->ifTrue(static fn (mixed $v): bool => !\is_string($v) || !AppearanceCssEmitter::isColor($v))
                        ->thenInvalid('Invalid CSS colour %s.')
                    ->end()
                ->end();
            }

            return $node;
        };

        $root
            ->children()
                ->arrayNode('appearance')
                    ->info('Optional site appearance SPI (presets, --nowo-ui-* token emitter, Twig functions). Off by default.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('provider')
                            ->defaultNull()
                            ->info('Service id implementing AppearanceProviderInterface (host storage). Null = the config defaults below.')
                        ->end()
                        ->arrayNode('defaults')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('brand_name')->defaultValue('')->end()
                                ->scalarNode('brand_eyebrow')->defaultValue('')->end()
                                ->scalarNode('preset')->defaultValue('slate')->info('Preset whose light colours are the default.')->end()
                                ->scalarNode('preset_dark')->defaultValue('midnight')->info('Preset whose dark colours are the default.')->end()
                                ->enumNode('corner_style')->values(CornerStyle::values())->defaultValue(CornerStyle::Soft->value)->end()
                                ->enumNode('border_strength')->values(BorderStrength::values())->defaultValue(BorderStrength::Medium->value)->end()
                                ->booleanNode('footer_fixed')->defaultFalse()->end()
                            ->end()
                        ->end()
                        ->arrayNode('presets')
                            ->info('Host presets by id: {mode: light|dark, light: {accent, accent_deep, danger, warn, paper, ink, surface}, dark: {…}}. Listed before the built-ins; a built-in id replaces it.')
                            ->useAttributeAsKey('id')
                            ->normalizeKeys(false)
                            ->arrayPrototype()
                                ->children()
                                    ->enumNode('mode')->values([AppearancePreset::MODE_LIGHT, AppearancePreset::MODE_DARK])->defaultValue(AppearancePreset::MODE_LIGHT)->end()
                                    ->append($palette('light'))
                                    ->append($palette('dark'))
                                ->end()
                            ->end()
                            ->validate()
                                ->ifTrue(static fn (array $presets): bool => [] !== array_filter(array_keys($presets), static fn (int|string $id): bool => 1 !== preg_match('/^[a-z0-9][a-z0-9_-]*$/', (string) $id)))
                                ->thenInvalid('Preset ids must be lowercase letters, digits, "-" or "_": %s')
                            ->end()
                        ->end()
                        ->arrayNode('builtin_presets')
                            ->info('Built-in presets to offer (default: all).')
                            ->scalarPrototype()->end()
                            ->defaultValue(BuiltInAppearancePresets::IDS)
                            ->validate()
                                ->ifTrue(static fn (array $ids): bool => [] !== array_diff($ids, BuiltInAppearancePresets::IDS))
                                ->thenInvalid('Unknown built-in preset in %s (available: '.implode(', ', BuiltInAppearancePresets::IDS).').')
                            ->end()
                        ->end()
                        ->booleanNode('kit_tokens')
                            ->defaultTrue()
                            ->info('Emit the --nowo-ui-* tokens. False = only the token_aliases are written.')
                        ->end()
                        ->arrayNode('token_aliases')
                            ->info('Kit token => extra custom property names that receive the same value (e.g. "--nowo-ui-primary": ["--brand-primary"]).')
                            ->useAttributeAsKey('token')
                            ->normalizeKeys(false)
                            ->arrayPrototype()
                                ->beforeNormalization()->castToArray()->end()
                                ->scalarPrototype()->end()
                            ->end()
                            ->validate()
                                ->always(static function (array $aliases): array {
                                    $known = array_merge(AppearanceCssEmitter::ROOT_TOKENS, AppearanceCssEmitter::MODE_TOKENS);
                                    foreach ($aliases as $token => $names) {
                                        if (!\in_array($token, $known, true)) {
                                            throw new InvalidConfigurationException(\sprintf('Invalid nowo_ui_kit.appearance.token_aliases: unknown kit token "%s" (known: %s).', $token, implode(', ', $known)));
                                        }
                                        foreach ($names as $name) {
                                            if (!\is_string($name) || 1 !== preg_match('/^--[A-Za-z0-9_-]+$/', $name)) {
                                                throw new InvalidConfigurationException(\sprintf('Invalid nowo_ui_kit.appearance.token_aliases: "%s" is not a custom property name (--name).', \is_string($name) ? $name : get_debug_type($name)));
                                            }
                                        }
                                    }

                                    return $aliases;
                                })
                            ->end()
                        ->end()
                        ->arrayNode('selectors')
                            ->info('Selectors of the three emitted blocks.')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('root')->defaultValue(AppearanceCssEmitter::DEFAULT_SELECTORS['root'])->end()
                                ->scalarNode('light')->defaultValue(AppearanceCssEmitter::DEFAULT_SELECTORS['light'])->end()
                                ->scalarNode('dark')->defaultValue(AppearanceCssEmitter::DEFAULT_SELECTORS['dark'])->end()
                            ->end()
                            ->validate()
                                ->ifTrue(static fn (array $selectors): bool => [] !== array_filter($selectors, static fn (mixed $s): bool => !\is_string($s) || '' === trim($s) || 1 === preg_match('/[{};<>]/', $s)))
                                ->thenInvalid('Appearance selectors must be non-empty and contain no "{", "}", ";", "<" or ">": %s')
                            ->end()
                        ->end()
                        ->scalarNode('shadow_rgb_fallback')
                            ->defaultValue('15 23 42')
                            ->info('--nowo-ui-shadow-rgb when the light ink colour is not hex ("r g b").')
                            ->validate()
                                ->ifTrue(static fn (mixed $v): bool => !\is_string($v) || 1 !== preg_match('/^\d{1,3} \d{1,3} \d{1,3}$/', $v))
                                ->thenInvalid('shadow_rgb_fallback must be three channels "r g b": %s')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
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

            if (str_starts_with((string) $from, '//') || str_starts_with($to, '//') || str_contains($from.$to, '\\')) {
                return \sprintf('prefixes must be same-origin paths: no leading "//" and no backslashes ("%s" => "%s").', $from, $to);
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
