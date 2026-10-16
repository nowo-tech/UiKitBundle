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
use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Theme\ThemeSlots;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
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
                ->arrayNode('route_links')
                    ->info('Route (or route prefix) → link map read by nowo_ui_route_link(), e.g. for the page-edge "manual" tab (partials/_edge_tab.html.twig). A prefix matches the route itself or any route starting with "<prefix>_"; the longest prefix wins.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('base_url')
                            ->defaultValue('')
                            ->info('Prepended to every relative path (paths starting with "/", "//", "http://" or "https://" are used as is).')
                        ->end()
                        ->scalarNode('default_testid')
                            ->defaultNull()
                            ->info('testid returned for entries without their own.')
                        ->end()
                        ->arrayNode('routes')
                            ->info('Route or route prefix → path (string) or {path, testid}.')
                            ->useAttributeAsKey('route', false)
                            ->normalizeKeys(false)
                            ->arrayPrototype()
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(static fn (string $path): array => ['path' => $path])
                                ->end()
                                ->children()
                                    ->scalarNode('path')->isRequired()->cannotBeEmpty()->end()
                                    ->scalarNode('testid')->defaultNull()->end()
                                ->end()
                            ->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
            ->end();

        $this->addAppearanceSection($root);
        $this->addPageContractSection($root);

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
     * REQ-UI-003 page contract: canonical layouts, global theme (slot classes / attributes / partials / tokens,
     * per-bundle exceptions) and the breadcrumb renderer used by page headers.
     */
    private function addPageContractSection(ArrayNodeDefinition $root): void
    {
        $root
            ->children()
                ->arrayNode('layouts')
                    ->info('Layouts the page scaffolds extend (Twig global nowo_ui_layout). Host layouts or one-file bridges must render the canonical nowo_ui_* blocks.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('admin')->defaultValue('@NowoUiKit/layout/admin.html.twig')->cannotBeEmpty()->end()
                        ->scalarNode('guest')->defaultValue('@NowoUiKit/layout/guest.html.twig')->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->arrayNode('breadcrumbs')
                    ->info('Page header breadcrumbs: BreadcrumbKit (breadcrumb_render) when installed and enabled here, else the plain trail from PageHeader::breadcrumbs.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('breadcrumb_kit')->defaultTrue()->info('Use BreadcrumbKit when its breadcrumb_render() Twig function exists.')->end()
                        ->scalarNode('collection')->defaultNull()->info('BreadcrumbKit collection code (null = its default collection). PageHeader::breadcrumbCollection overrides it.')->end()
                    ->end()
                ->end()
                ->append($this->themeNode())
            ->end();
    }

    private function themeNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('theme');
        $node
            ->info('Global theme (REQ-UI-003): semantic slot classes / attributes, partial overrides and --nowo-ui-* tokens, configured once for every bundle.')
            ->addDefaultsIfNotSet();
        $children = $node->children();
        $this->addThemeChildren($children, 'theme', false);
        $bundles = $children
            ->arrayNode('bundles')
                ->info('Optional per-bundle exceptions keyed by bundle name (e.g. NowoHttpLogBundle): a bundle slot class replaces the host class of that slot; attributes merge; partials replace; tokens are scoped to [data-nowo-ui-bundle="<Bundle>"].')
                ->useAttributeAsKey('bundle')
                ->normalizeKeys(false)
                ->validate()
                    ->always(static function (array $bundles): array {
                        foreach (array_keys($bundles) as $bundle) {
                            if (1 !== preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', (string) $bundle)) {
                                throw new InvalidConfigurationException(\sprintf('Invalid nowo_ui_kit.theme.bundles key "%s": use the bundle class short name (e.g. NowoHttpLogBundle).', $bundle));
                            }
                        }

                        return $bundles;
                    })
                ->end()
                ->arrayPrototype();
        $this->addThemeChildren($bundles->children(), 'theme.bundles.<bundle>', true);

        return $node;
    }

    private function addThemeChildren(NodeBuilder $children, string $name, bool $scoped): void
    {
        $defaults = $children->booleanNode('framework_defaults');
        if ($scoped) {
            $defaults->info('Override of theme.framework_defaults for this bundle (unset = inherit).');
        } else {
            $defaults->defaultTrue()->info('false = slots print the semantic nowo-ui-* class and the host classes only (no UiKit framework defaults such as btn / badge / card). Per slot: classes.<slot>: {value: …, framework_defaults: false}.');
        }
        $defaults->end();
        $children
            ->arrayNode('classes')
                ->info('Slot => host classes, merged after the UiKit framework defaults (vocabulary: Theme\ThemeSlots, docs/THEME_SLOTS.md): a string, a list, or {value: classes, framework_defaults: false} to drop the framework defaults of that slot.')
                ->useAttributeAsKey('slot')
                ->normalizeKeys(false)
                ->variablePrototype()->end()
                ->defaultValue([])
                ->validate()
                    ->always(static function (array $classes) use ($name): array {
                        foreach ($classes as $slot => $value) {
                            self::assertSlot($name.'.classes', (string) $slot);
                            $error = Theme::classEntryError($value);
                            if (null !== $error) {
                                throw new InvalidConfigurationException(\sprintf('Invalid nowo_ui_kit.%s.classes.%s: %s', $name, $slot, $error));
                            }
                            [$value, $defaults] = Theme::normalizeClassEntry($value);
                            $classes[$slot] = null === $defaults ? $value : ['value' => $value, 'framework_defaults' => $defaults];
                        }

                        return $classes;
                    })
                ->end()
            ->end()
            ->arrayNode('attributes')
                ->info('Slot => {attribute: value} rendered on the slot root (true = bare attribute, false/null = omitted; no on* handlers).')
                ->useAttributeAsKey('slot')
                ->normalizeKeys(false)
                ->arrayPrototype()
                    ->useAttributeAsKey('attribute')
                    ->normalizeKeys(false)
                    ->variablePrototype()->end()
                ->end()
                ->defaultValue([])
                ->validate()
                    ->always(static function (array $attributes) use ($name): array {
                        foreach ($attributes as $slot => $attrs) {
                            self::assertSlot($name.'.attributes', (string) $slot);
                            foreach ($attrs as $attr => $value) {
                                $error = Theme::attributeNameError((string) $attr);
                                if (null !== $error) {
                                    throw new InvalidConfigurationException(\sprintf('Invalid nowo_ui_kit.%s.attributes.%s: %s', $name, $slot, $error));
                                }
                                if (null !== $value && !\is_scalar($value)) {
                                    throw new InvalidConfigurationException(\sprintf('Invalid nowo_ui_kit.%s.attributes.%s.%s: values are strings, numbers, booleans or null.', $name, $slot, $attr));
                                }
                            }
                        }

                        return $attributes;
                    })
                ->end()
            ->end()
            ->arrayNode('partials')
                ->info('Partial name => template replacing the UiKit partial for every bundle (known: '.implode(', ', ThemeSlots::partialNames()).').')
                ->useAttributeAsKey('partial')
                ->normalizeKeys(false)
                ->scalarPrototype()->cannotBeEmpty()->end()
                ->defaultValue([])
                ->validate()
                    ->always(static function (array $partials) use ($name): array {
                        foreach (array_keys($partials) as $partial) {
                            if (!isset(ThemeSlots::PARTIALS[$partial])) {
                                throw new InvalidConfigurationException(\sprintf('Unknown nowo_ui_kit.%s.partials key "%s" (known: %s).', $name, $partial, implode(', ', ThemeSlots::partialNames())));
                            }
                        }

                        return $partials;
                    })
                ->end()
            ->end();

        foreach (['tokens' => 'Token => value, emitted as --nowo-ui-<token> on :root by nowo_ui_theme_tokens() (e.g. primary: "var(--color-moss)"). When set, the UiKit palette no longer re-asserts itself.', 'tokens_dark' => 'Dark-mode tokens, emitted on :root[data-theme="dark"].'] as $key => $info) {
            $children
                ->arrayNode($key)
                    ->info($scoped ? $info.' Scoped to [data-nowo-ui-bundle="<Bundle>"].' : $info)
                    ->useAttributeAsKey('token')
                    ->normalizeKeys(false)
                    ->variablePrototype()->end()
                    ->defaultValue([])
                    ->validate()
                        ->always(static function (array $tokens) use ($name, $key): array {
                            foreach ($tokens as $token => $value) {
                                $error = Theme::tokenError((string) $token, $value);
                                if (null !== $error) {
                                    throw new InvalidConfigurationException(\sprintf('Invalid nowo_ui_kit.%s.%s: %s', $name, $key, $error));
                                }
                            }

                            return array_map(static fn (mixed $v): string => trim(\is_scalar($v) ? (string) $v : ''), $tokens);
                        })
                    ->end()
                ->end();
        }
    }

    private static function assertSlot(string $path, string $slot): void
    {
        if (!ThemeSlots::has($slot)) {
            throw new InvalidConfigurationException(\sprintf('Unknown nowo_ui_kit.%s slot "%s" (known: %s).', $path, $slot, implode(', ', ThemeSlots::names())));
        }
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
