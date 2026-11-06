<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\DependencyInjection;

use Nowo\UiKitBundle\Appearance\AppearanceCssEmitter;
use Nowo\UiKitBundle\Appearance\AppearancePalette;
use Nowo\UiKitBundle\Appearance\AppearancePreset;
use Nowo\UiKitBundle\Appearance\BorderStrength;
use Nowo\UiKitBundle\Appearance\BuiltInAppearancePresets;
use Nowo\UiKitBundle\Appearance\CornerStyle;
use Nowo\UiKitBundle\Dialog\RemoteDialogHeaders;
use Nowo\UiKitBundle\Enum\CssFramework;
use Nowo\UiKitBundle\Enum\IconSet;
use Nowo\UiKitBundle\Enum\RowActionsDisplay;
use Nowo\UiKitBundle\Shell\HeaderPresentation;
use Nowo\UiKitBundle\Shell\SafeHref;
use Nowo\UiKitBundle\Shell\SidebarLayout;
use Nowo\UiKitBundle\Theme\ListFiltersOptions;
use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Theme\ThemeSlots;
use Nowo\UiKitBundle\Twig\ActionIconsExtension;
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
                ->enumNode('row_actions_confirm')
                    ->values(['inline', 'native', 'dialog'])
                    ->defaultValue('inline')
                    ->info('confirm_message of 1.x row_actions POST hashes (2.22, REQ-UI-003): inline = onsubmit="return confirm(…)" (default, unchanged markup); native | dialog = no inline handler, the confirm-submit Stimulus peer asks (window.confirm or the kit <dialog>). native / dialog need the confirm-submit peer registered.')
                ->end()
                ->arrayNode('action_icons')
                    ->info('Opt-in: canonical kind → icon name "[prefix:]name" (e.g. delete: tabler:trash). Every use of the kind follows it, chrome included; an explicit icon on an action wins. ux_icon draws it as written; tabler-icons / bootstrap-icons need their own prefix or none (else the kit default); svg_inline and none keep the kit default. Also shows the kind icon on toolbar actions and menu items. See docs/ROW_ACTIONS.md.')
                    ->useAttributeAsKey('kind', false)
                    ->normalizeKeys(false)
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                    ->validate()
                        ->always(static function (array $map): array {
                            $error = self::findInvalidActionIcon($map);
                            if (null !== $error) {
                                throw new InvalidConfigurationException('Invalid nowo_ui_kit.action_icons: '.$error);
                            }

                            return $map;
                        })
                    ->end()
                ->end()
                ->arrayNode('action_variants')
                    ->info('Opt-in: canonical kind → button variant (primary|secondary|danger) for actions without an explicit variant (e.g. create: secondary keeps a filled primary out of table rows, filter: secondary for the filter submit). Action::create() sets primary explicitly and keeps it. See docs/ROW_ACTIONS.md.')
                    ->useAttributeAsKey('kind', false)
                    ->normalizeKeys(false)
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                    ->validate()
                        ->always(static function (array $map): array {
                            $error = self::findInvalidActionVariant($map);
                            if (null !== $error) {
                                throw new InvalidConfigurationException('Invalid nowo_ui_kit.action_variants: '.$error);
                            }

                            return $map;
                        })
                    ->end()
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
                            ->info('Register the checker service + controller. Also import @NowoUiKitBundle/Resources/config/routes/release_check.yaml. Since 2.33 also a container parameter or "%env(bool:…)%" (false at runtime = the check is skipped and the endpoint answers 404).')
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
                        ->floatNode('timeout')
                            ->defaultValue(5.0)
                            ->min(0.1)
                            ->max(60.0)
                            ->info('Idle timeout in seconds of the GitHub "releases/latest" request (HttpClient "timeout"). Keep it below PHP max_execution_time (REQ-RUNTIME-001).')
                        ->end()
                        ->floatNode('releases_timeout')
                            ->defaultValue(8.0)
                            ->min(0.1)
                            ->max(60.0)
                            ->info('Idle timeout in seconds of the GitHub "releases" listing used to count versions behind. Keep it below PHP max_execution_time (REQ-RUNTIME-001).')
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
                ->arrayNode('font_preload')
                    ->info('2.21: <link rel="preload" as="font" crossorigin> tags printed in the <head> of the document and error layouts (Twig nowo_ui_font_preload()) for the fonts painted above the fold. Empty = no tags.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('fonts')
                            ->info('Font URLs as served (e.g. "/fonts/inter-400.woff2"); the type comes from the extension.')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                            ->defaultValue([])
                        ->end()
                        ->arrayNode('manifest')
                            ->info('Hashed builds: pick the fonts from a Vite manifest (.vite/manifest.json). A missing manifest (dev server) prints nothing.')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('path')
                                    ->defaultNull()
                                    ->info('Absolute path of the manifest, e.g. "%kernel.project_dir%/public/build/.vite/manifest.json"; null = off.')
                                ->end()
                                ->scalarNode('entry')
                                    ->defaultValue('assets/app.js')
                                    ->info('Manifest entry whose "assets" list holds the fonts.')
                                ->end()
                                ->arrayNode('prefixes')
                                    ->info('Font file basename prefixes, one tag each (e.g. "inter-latin-400-normal").')
                                    ->scalarPrototype()->cannotBeEmpty()->end()
                                    ->defaultValue([])
                                ->end()
                                ->scalarNode('base')
                                    ->defaultValue('/build/')
                                    ->info('Public URL prefix of the manifest assets.')
                                ->end()
                            ->end()
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
                        ->booleanNode('enabled')->defaultTrue()->info('2.3.1: false drops the breadcrumb trail (BreadcrumbKit and PageHeader::breadcrumbs) from every page header, e.g. when the host layout renders its own.')->end()
                    ->end()
                ->end()
                ->arrayNode('header')
                    ->info('2.27 compact header: the default page-header presentation (a request attribute _nowo_ui_header_presentation overrides it per request) and where PageHeader::actions go in the compact presentation.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('presentation')
                            ->values(HeaderPresentation::values())
                            ->defaultValue(HeaderPresentation::Standard->value)
                            ->info('standard (default) | compact: in compact the page header keeps a visually hidden h1 and leaves its intro, breadcrumbs and back link to the top bar (header_context).')
                        ->end()
                        ->enumNode('compact_actions')
                            ->values(['page', 'bar'])
                            ->defaultValue('page')
                            ->info('page (default): header.actions stay in the page header; bar: in the compact presentation the page header leaves them out (the host shows them in the top bar through the page_actions partial). extra_actions always stay.')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('remote_dialog')
                    ->info('2.28 remote form dialog (X-Nowo-Dialog requests).')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('convert_redirects')
                            ->defaultFalse()
                            ->info('Turn a RedirectResponse answered to a dialog request (X-Nowo-Dialog: 1, main request) into 204 + X-Nowo-Redirect when its Location is a same-origin path, so redirectToRoute() controllers work in the dialog without code changes.')
                        ->end()
                        ->arrayNode('step_routes')
                            ->info('2.33: route names (`*` = any characters, e.g. "*_board_connection*") whose redirects, answered to a dialog request, become a step (204 + X-Nowo-Dialog-Location: the dialog stays open and loads the target, same-origin paths only). Every answer of these routes varies on the request header. Works with convert_redirects on or off; a Nowo\\UiKitBundle\\Dialog\\RemoteDialogRedirectEvent listener can change the answer per redirect.')
                            ->scalarPrototype()
                                ->validate()
                                    ->ifTrue(static fn (mixed $route): bool => !\is_string($route) || 1 !== preg_match('/^[A-Za-z0-9_.*-]+$/', $route))
                                    ->thenInvalid('A remote_dialog.step_routes entry is a route name of letters, digits, "_", ".", "-" and "*" (any characters), %s given.')
                                ->end()
                            ->end()
                            ->defaultValue([])
                        ->end()
                        ->scalarNode('request_header')
                            ->defaultValue(RemoteDialogHeaders::DEFAULT_REQUEST)
                            ->info('2.33: request header the dialog sends with "1" (both drivers, RemoteDialogResponder, the redirect listener, nowo_ui_dialog_request()).')
                        ->end()
                        ->scalarNode('redirect_header')
                            ->defaultValue(RemoteDialogHeaders::DEFAULT_REDIRECT)
                            ->info('2.33: response header of the success answer (204 + <path>: the dialog closes and the page goes there).')
                        ->end()
                        ->scalarNode('location_header')
                            ->defaultValue(RemoteDialogHeaders::DEFAULT_LOCATION)
                            ->info('2.33: response header of the step answer (204 + <path>: the dialog stays open and loads the next step).')
                        ->end()
                    ->end()
                    ->validate()
                        ->always(static function (array $dialog): array {
                            $error = RemoteDialogHeaders::invalid((string) $dialog['request_header'], (string) $dialog['redirect_header'], (string) $dialog['location_header']);
                            if (null !== $error) {
                                throw new InvalidConfigurationException('Invalid nowo_ui_kit.remote_dialog: '.$error);
                            }

                            return $dialog;
                        })
                    ->end()
                ->end()
                ->append($this->themeNode())
                ->append($this->errorPagesNode())
                ->append($this->shellNode())
            ->end();
    }

    /**
     * Kit error pages (2.6): `@NowoUiKit/error/status.html.twig` on `layout_template`, texts from a host domain / key
     * prefix with UiKit's catalogue as fallback, per-code illustrations, retry / back / Retry-After / self-reload.
     */
    private function errorPagesNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('error_pages');
        $node
            ->info('Kit error pages (2.6): templates/bundles/TwigBundle/Exception/error.html.twig = {% extends \'@NowoUiKit/error/status.html.twig\' %}.')
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('layout_template')
                    ->defaultValue('@NowoUiKit/error/layout.html.twig')
                    ->cannotBeEmpty()
                    ->info('Document the status template extends: the kit layout, or a host template extending it (own <head> assets, brand, chat dock…).')
                ->end()
                ->scalarNode('translation_domain')
                    ->defaultValue('NowoUiKitBundle')
                    ->cannotBeEmpty()
                    ->info('Domain of the page texts (e.g. messages). Keys missing there fall back to UiKit\'s own NowoUiKitBundle error.* texts.')
                ->end()
                ->scalarNode('translation_prefix')
                    ->defaultValue('error')
                    ->info('Key prefix: <prefix>.<code>.title|lead|hint|image_alt, <prefix>.generic.*, <prefix>.home|retry|back|retry_after|auto_retry|skip_to_content.')
                ->end()
                ->scalarNode('home_path')
                    ->defaultValue('/')
                    ->cannotBeEmpty()
                    ->info('Href of the "Back to home" button.')
                ->end()
                ->arrayNode('illustrations')
                    ->info('Status code (or "default") => asset path without extension (e.g. illustrations/error-404 → <picture> with error-404.webp + error-404.png), or {path, width, height, webp, extension}.')
                    ->useAttributeAsKey('code')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->beforeNormalization()
                            ->ifString()
                            ->then(static fn (string $path): array => ['path' => $path])
                        ->end()
                        ->children()
                            ->scalarNode('path')->isRequired()->cannotBeEmpty()->end()
                            ->integerNode('width')->defaultNull()->min(1)->end()
                            ->integerNode('height')->defaultNull()->min(1)->end()
                            ->booleanNode('webp')->defaultNull()->end()
                            ->scalarNode('extension')->defaultNull()->end()
                        ->end()
                    ->end()
                    ->defaultValue([])
                    ->validate()
                        ->always(static function (array $map): array {
                            foreach (array_keys($map) as $code) {
                                $key = (string) $code;
                                if ('default' !== $key && !(ctype_digit($key) && (int) $key >= 400 && (int) $key <= 599)) {
                                    throw new InvalidConfigurationException(\sprintf('Invalid nowo_ui_kit.error_pages.illustrations key "%s": an HTTP error status code (400-599) or "default".', $code));
                                }
                            }

                            return $map;
                        })
                    ->end()
                ->end()
                ->integerNode('illustration_width')->defaultValue(1024)->min(1)->info('Default intrinsic width of the illustrations.')->end()
                ->integerNode('illustration_height')->defaultValue(683)->min(1)->info('Default intrinsic height of the illustrations.')->end()
                ->booleanNode('webp')->defaultTrue()->info('Illustrations ship a .webp next to the fallback image (<source type="image/webp">).')->end()
                ->arrayNode('retry_statuses')
                    ->info('Statuses besides every 5xx that show "Try again" on GET.')
                    ->integerPrototype()->end()
                    ->defaultValue([408])
                ->end()
                ->arrayNode('back_link_statuses')
                    ->info('Statuses that show "Go back" when the referer is same-site.')
                    ->integerPrototype()->end()
                    ->defaultValue([404])
                ->end()
                ->integerNode('auto_retry_seconds')
                    ->defaultValue(600)
                    ->min(0)
                    ->info('A 5xx on GET reloads itself after this many seconds (REQ-ERROR-001 item 10; 0 = never). Never on POST.')
                ->end()
                ->scalarNode('theme_storage')
                    ->defaultValue('nowo-ui-theme')
                    ->cannotBeEmpty()
                    ->info('localStorage key of the theme (data-nowo-ui-theme-storage on <html> and the toggle), e.g. the host\'s beacon-theme.')
                ->end()
                ->booleanNode('theme_toggle')->defaultTrue()->info('Render the light / dark toggle in the top controls.')->end()
                ->arrayNode('html_attributes')
                    ->info('Extra <html> attributes (e.g. data-csp-strict-nonce: true); no on* handlers.')
                    ->useAttributeAsKey('attribute')
                    ->normalizeKeys(false)
                    ->variablePrototype()->end()
                    ->defaultValue([])
                    ->validate()
                        ->always(static function (array $attributes): array {
                            self::assertAttributeMap('error_pages.html_attributes', $attributes);

                            return $attributes;
                        })
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    /**
     * Signed-in app shell (2.6, `@NowoUiKit/shell/*`): server-rendered aside state and the kit look.
     */
    private function shellNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('shell');
        $node
            ->info('Signed-in app shell (2.6, @NowoUiKit/shell/_open|_close|_shell|_burger.html.twig; Twig global nowo_ui_kit_shell).')
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('kit_styles')
                    ->defaultTrue()
                    ->info('Add the kit shell classes (nowo-ui-shell, nowo-ui-header, nowo-ui-aside…) painted by nowo-ui.css and driven by nowo-ui-shell.js. false: only the app_* slot classes (host shell CSS / JS).')
                ->end()
                ->scalarNode('sidebar_cookie')
                    ->defaultNull()
                    ->info('Cookie holding the desktop aside mode (e.g. beacon_sidebar), read server-side so the first paint is already collapsed / rail; null = expanded unless the template passes collapsed.')
                ->end()
                ->scalarNode('sidebar_hidden_value')->defaultValue('collapsed')->cannotBeEmpty()->info('Cookie value of the hidden aside.')->end()
                ->scalarNode('sidebar_rail_value')->defaultValue('rail')->cannotBeEmpty()->info('Cookie value of the icons-only rail.')->end()
                ->scalarNode('sidebar_storage')->defaultNull()->info('localStorage key of the aside mode (data-nowo-ui-sidebar-storage on the shell root; kit_styles + rail only).')->end()
                ->enumNode('sidebar_layout')
                    ->values(SidebarLayout::values())
                    ->defaultValue(SidebarLayout::BelowHeader->value)
                    ->info('2.32: below-header (default, the 2.31 markup byte for byte) | full-height: on desktop the aside runs from the top edge with the burger and the brand in its head (aside_brand_html / brand_icon_html + brand_name / brand_html), the header and the footer start at its right edge. The app_shell_open option sidebar_layout, then the request attribute _nowo_ui_sidebar_layout, win over it.')
                ->end()
                ->arrayNode('brand')
                    ->info('2.32: the shell brand from config (no template override): the aside head of the full-height layout, and the header brand when a page passes neither brand_html nor brand. The app_shell_open options brand_name / brand_href / brand_icon_src / brand_icon_html / aside_brand_html win.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('name')->defaultNull()->info('Brand name (text); the icons-only rail hides it and keeps it as the link name.')->end()
                        ->scalarNode('href')
                            ->defaultNull()
                            ->info('Brand link: relative, http, https or mailto (validated when the container compiles; javascript:, data: and other schemes are refused).')
                            ->validate()
                                ->ifTrue(static fn (mixed $v): bool => null !== $v && (!\is_string($v) || !SafeHref::isSafe($v)))
                                ->thenInvalid('Invalid nowo_ui_kit.shell.brand.href %s: use a relative URL or an http, https or mailto one.')
                            ->end()
                        ->end()
                        ->scalarNode('icon_src')
                            ->defaultNull()
                            ->info('Brand icon image URL (e.g. /images/logo.svg; prefer same-origin or https), rendered as <img> in every aside mode; without it the name\'s initial. Relative, http or https only (validated when the container compiles).')
                            ->validate()
                                ->ifTrue(static fn (mixed $v): bool => null !== $v && (!\is_string($v) || !SafeHref::isSafeImage($v)))
                                ->thenInvalid('Invalid nowo_ui_kit.shell.brand.icon_src %s: use a relative URL or an http or https one.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->booleanNode('hover_expand')
                    ->defaultFalse()
                    ->info('2.32: with kit_styles and rail_mode, the icons-only rail widens over the page while the pointer or the keyboard focus is on it (data-nowo-ui-sidebar-hover-expand on the shell root, is-aside-peek set by nowo-ui-shell.js). The app_shell_open option hover_expand wins.')
                ->end()
            ->end();

        return $node;
    }

    /**
     * @param array<mixed> $attributes
     */
    private static function assertAttributeMap(string $path, array $attributes): void
    {
        foreach ($attributes as $attr => $value) {
            $error = Theme::attributeNameError((string) $attr);
            if (null !== $error) {
                throw new InvalidConfigurationException(\sprintf('Invalid nowo_ui_kit.%s: %s', $path, $error));
            }
            if (null !== $value && !\is_scalar($value)) {
                throw new InvalidConfigurationException(\sprintf('Invalid nowo_ui_kit.%s.%s: values are strings, numbers, booleans or null.', $path, $attr));
            }
        }
    }

    private function themeNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('theme');
        $node
            ->info('Global theme (REQ-UI-003): semantic slot classes / attributes, partial overrides and --nowo-ui-* tokens, configured once for every bundle.')
            ->addDefaultsIfNotSet();
        $children = $node->children();
        $this->addThemeChildren($children, 'theme', false);
        $children
            ->booleanNode('confirm_message_in_header')
                ->defaultFalse()
                ->info('Confirm dialogs and the full-page confirm render the message inside the header, under the title (hosts whose dialog header is title + message). View\\Confirm::messageInHeader wins when set.')
            ->end()
            ->booleanNode('popover_menus')
                ->defaultFalse()
                ->info('2.4: menu / kebab / row-action menus render their panel as a native [popover] (browser top layer: never clipped by table scrollers or cards; nowo-ui-popover.js places it). The partial option `popover` wins.')
            ->end()
            ->booleanNode('form_errors_after_widget')
                ->defaultFalse()
                ->info('2.10: the UiKit form theme (form/nowo_ui_layout.html.twig) prints a field\'s errors after its widget (and before the help), instead of Symfony\'s order (label, errors, widget, help).')
            ->end()
            ->enumNode('menu_semantics')
                ->values([null, 'menu', 'disclosure'])
                ->defaultNull()
                ->info('2.28: ARIA of popover menus with items. null (default) = as 2.27; menu = APG menu button (role="menu" / menuitem, arrow keys in nowo-ui-popover.js); disclosure = no aria-haspopup. The menu partial option `menu_semantics` wins.')
            ->end()
            ->arrayNode('dialog')
                ->info('2.31: kit dialog options (docs/THEMING.md#dialog-sizes-229).')
                ->addDefaultsIfNotSet()
                ->children()
                    ->enumNode('height')
                        ->values(Theme::DIALOG_HEIGHTS)
                        ->defaultValue('content')
                        ->info('2.31: content (default, dialog rule v2) = a dialog is as tall as its content, capped at min(the size\'s height token, 100dvh - 2rem), only the body scrolls; size = every dialog with a field, list, table or fold has the size\'s height (2.29 / 2.30), printed as --nowo-ui-dialog-fill: 1 by nowo_ui_theme_css().')
                    ->end()
                ->end()
            ->end();
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
        $bundlePages = $bundles
            ->children()
                ->arrayNode('pages')
                    ->info('Optional per-page-type variation of this bundle (2.1), keyed by scaffold type ('.implode(', ', ThemeSlots::PAGE_TYPES).'): classes replace every other class entry of the slot on that bundle page type, attributes merge last, framework_defaults overrides the bundle setting. Precedence: bundle page slot > bundle slot > bundle page > bundle > page-type slot > page type > host slot > host.')
                    ->useAttributeAsKey('page')
                    ->normalizeKeys(false)
                    ->validate()
                        ->always(static function (array $pages): array {
                            foreach (array_keys($pages) as $page) {
                                $error = Theme::pageTypeError((string) $page);
                                if (null !== $error) {
                                    throw new InvalidConfigurationException('Invalid nowo_ui_kit.theme.bundles.<bundle>.pages key: '.$error);
                                }
                            }

                            return $pages;
                        })
                    ->end()
                    ->arrayPrototype();
        $this->addThemeChildren($bundlePages->children(), 'theme.bundles.<bundle>.pages.<page>', true, false);
        $pages = $children
            ->arrayNode('pages')
                ->info('Optional per-page-type variation keyed by scaffold type ('.implode(', ', ThemeSlots::PAGE_TYPES).'): classes replace the host classes of a slot on that page type, attributes merge, framework_defaults overrides the host setting. Precedence: bundle page slot > bundle slot > bundle page > bundle > page-type slot > page type > host slot > host.')
                ->useAttributeAsKey('page')
                ->normalizeKeys(false)
                ->validate()
                    ->always(static function (array $pages): array {
                        foreach ($pages as $page => $data) {
                            $error = Theme::pageTypeError((string) $page);
                            if (null !== $error) {
                                throw new InvalidConfigurationException('Invalid nowo_ui_kit.theme.pages key: '.$error);
                            }
                            if ('list' !== (string) $page && isset($data['header_actions_in_filters'])) {
                                throw new InvalidConfigurationException(\sprintf('Invalid nowo_ui_kit.theme.pages.%s.header_actions_in_filters: only the list page type takes it.', $page));
                            }
                            if ('list' !== (string) $page && isset($data['filters'])) {
                                throw new InvalidConfigurationException(\sprintf('Invalid nowo_ui_kit.theme.pages.%s.filters: only the list page type takes it.', $page));
                            }
                            if (isset($data['filters'])) {
                                $error = ListFiltersOptions::error($data['filters']);
                                if (null !== $error) {
                                    throw new InvalidConfigurationException('Invalid nowo_ui_kit.theme.pages.list.filters: '.$error);
                                }
                            }
                        }

                        return $pages;
                    })
                ->end()
                ->arrayPrototype();
        $this->addThemeChildren($pages->children(), 'theme.pages.<page>', true, false);
        $pages
            ->children()
                ->booleanNode('header_actions_in_filters')
                    ->info('list only: render the page-header actions at the end of the filter row instead of the page header (ListPage::headerActionsInFilters wins when set; unset = false).')
                ->end()
                ->arrayNode('filters')
                    ->info('list only (2.30): host options of the list scaffold filters (page/_list_filters.html.twig). Unset = the 2.29 output.')
                    ->children()
                        ->enumNode('display')
                            ->values([null, 'text', 'icon', 'icon_text'])
                            ->defaultNull()
                            ->info('Default display of the apply / clear / extra filter buttons (ListPage::filtersDisplay and Action::display win; set, the plain clear link follows it too). null = the 2.29 behaviour.')
                        ->end()
                        ->scalarNode('submit_icon')
                            ->defaultNull()
                            ->info('Icon of the apply button when it shows one (components/_icon.html.twig name; null = filter). ListPage::filtersSubmit->icon wins.')
                        ->end()
                        ->scalarNode('clear_icon')
                            ->defaultNull()
                            ->info('Icon of the plain clear link (ListPage::filtersClearHref) when it shows one (null = filter_off).')
                        ->end()
                        ->scalarNode('submit_class')
                            ->defaultNull()
                            ->info('Host classes of the apply button, replacing its btn_<variant> slot classes (nowo-ui-filters__submit and, icon-only, the btn_icon slot stay). null = the slot classes.')
                        ->end()
                        ->scalarNode('clear_class')
                            ->defaultNull()
                            ->info('Host classes of the clear button (plain link or ListPage::filtersClear Action), replacing its btn_<variant> slot classes. null = the slot classes.')
                        ->end()
                        ->scalarNode('grid')
                            ->defaultValue(ListFiltersOptions::GRID_AUTO_FIT)
                            ->info('Grid tracks of the filter fields: auto-fit (default: a lone filter stretches across the card), auto-fill (empty tracks keep their width) or a grid-template-columns track list such as "repeat(auto-fill, minmax(min(100%, 13rem), 1fr))" (printed as the --nowo-ui-filters-grid-columns token by nowo_ui_tokens(); no inline style).')
                        ->end()
                        ->arrayNode('fold')
                            ->info('Fold toggle of the filters card (partials/_filters_collapse.html.twig, Stimulus peer filters-collapse). ListPage::filtersFold wins.')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')->defaultFalse()->info('Render the fold toggle (the card renders open unless the cookie remembers it folded).')->end()
                                ->scalarNode('cookie')->defaultValue(ListFiltersOptions::FOLD_COOKIE)->info('Cookie remembering the folded pages (comma-separated page keys; SameSite=Lax, no secrets).')->end()
                                ->scalarNode('cookie_path')->defaultValue('/')->info('Path of that cookie.')->end()
                                ->arrayNode('icons')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('expanded')->defaultValue('chevron_up')->info('Icon while the card is open.')->end()
                                        ->scalarNode('collapsed')->defaultValue('chevron_down')->info('Icon while the card is folded.')->end()
                                    ->end()
                                ->end()
                                ->booleanNode('testids')->defaultFalse()->info('data-testid filters-collapse-toggle / filters-collapse-summary.')->end()
                                ->booleanNode('morph')->defaultFalse()->info('Add the <morph-icon> of assets/morphicons to the toggle.')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    private function addThemeChildren(NodeBuilder $children, string $name, bool $scoped, bool $assets = true): void
    {
        $defaults = $children->booleanNode('framework_defaults');
        if ($scoped) {
            $defaults->info(\sprintf('Override of theme.framework_defaults for this %s (unset = inherit).', $assets ? 'bundle' : 'page type'));
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
            ->end();
        if (!$assets) {
            return;
        }
        $children
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

        foreach (['tokens' => 'Token => value, emitted as --nowo-ui-<token> on :root by nowo_ui_theme_tokens() (e.g. primary: "var(--color-moss)"). When set, the UiKit palette no longer re-asserts itself.', 'tokens_dark' => 'Dark-mode tokens, emitted on [data-theme="dark"].'] as $key => $info) {
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
     * @param array<array-key, mixed> $map
     */
    private static function findInvalidActionIcon(array $map): ?string
    {
        foreach ($map as $kind => $icon) {
            if (!\in_array($kind, ActionIconsExtension::kinds(), true)) {
                return \sprintf('unknown kind "%s" (%s).', $kind, implode('|', ActionIconsExtension::kinds()));
            }
            if (!\is_string($icon) || 1 !== preg_match(ActionIconsExtension::NAME_PATTERN, $icon)) {
                return \sprintf('kind "%s": expected an icon name "[prefix:]name" with lowercase letters, digits and single hyphens (e.g. "tabler:trash", "bi:pencil"), got %s.', $kind, json_encode($icon));
            }
        }

        return null;
    }

    /**
     * @param array<array-key, mixed> $map
     */
    private static function findInvalidActionVariant(array $map): ?string
    {
        foreach ($map as $kind => $variant) {
            if (!\in_array($kind, ActionIconsExtension::kinds(), true)) {
                return \sprintf('unknown kind "%s" (%s).', $kind, implode('|', ActionIconsExtension::kinds()));
            }
            if (!\in_array($variant, ActionIconsExtension::VARIANTS, true)) {
                return \sprintf('kind "%s": expected %s, got %s.', $kind, implode('|', ActionIconsExtension::VARIANTS), json_encode($variant));
            }
        }

        return null;
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
