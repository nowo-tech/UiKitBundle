<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\DependencyInjection;

use Nowo\UiKitBundle\Appearance\AppearanceCssEmitter;
use Nowo\UiKitBundle\Appearance\AppearancePreset;
use Nowo\UiKitBundle\Appearance\AppearancePresetProviderInterface;
use Nowo\UiKitBundle\Appearance\AppearancePresetRegistry;
use Nowo\UiKitBundle\Appearance\AppearanceProviderInterface;
use Nowo\UiKitBundle\Appearance\ConfigAppearanceProvider;
use Nowo\UiKitBundle\Enum\CssFramework;
use Nowo\UiKitBundle\EventSubscriber\LegacyPanelPathRedirectSubscriber;
use Nowo\UiKitBundle\Form\AppearancePaletteType;
use Nowo\UiKitBundle\Form\AppearanceType;
use Nowo\UiKitBundle\Form\DateRangeSubmitExtension;
use Nowo\UiKitBundle\Form\DateRangeType;
use Nowo\UiKitBundle\Form\MultiSelectTypeExtension;
use Nowo\UiKitBundle\Listing\CookieListingPreferenceStore;
use Nowo\UiKitBundle\Listing\ListingCustomizerViewBuilder;
use Nowo\UiKitBundle\Listing\ListingDefinitionInterface;
use Nowo\UiKitBundle\Listing\ListingPreferenceStoreInterface;
use Nowo\UiKitBundle\Listing\ListingRegistry;
use Nowo\UiKitBundle\Navigation\AreaNavProviderInterface;
use Nowo\UiKitBundle\Navigation\AreaNavRegistry;
use Nowo\UiKitBundle\Release\ReleaseStatusController;
use Nowo\UiKitBundle\Release\ReleaseUpdateChecker;
use Nowo\UiKitBundle\Routing\PanelPathRewriter;
use Nowo\UiKitBundle\Routing\PanelPathRewritingLoader;
use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Twig\AppearanceExtension;
use Nowo\UiKitBundle\Twig\AreaNavExtension;
use Nowo\UiKitBundle\Twig\ErrorPageExtension;
use Nowo\UiKitBundle\Twig\RouteLinkExtension;
use Nowo\UiKitBundle\Twig\ThemeExtension;
use Symfony\Component\Asset\Package;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class NowoUiKitExtension extends Extension implements PrependExtensionInterface
{
    /** Form theme with the date-range field's blocks (2.15), prepended to `twig.form_themes`. */
    public const DATE_RANGE_FORM_THEME = '@NowoUiKit/form/nowo_ui_date_range.html.twig';

    /** @var \Closure(): bool */
    private readonly \Closure $httpClientAvailable;

    /**
     * @param (\Closure(): bool)|null $httpClientAvailable whether symfony/http-client is installed (overridable in tests)
     */
    public function __construct(?\Closure $httpClientAvailable = null)
    {
        $this->httpClientAvailable = $httpClientAvailable ?? static fn (): bool => interface_exists(HttpClientInterface::class) && class_exists(HttpClient::class);
    }

    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }

    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('framework') && class_exists(Package::class)) {
            $container->prependExtensionConfig('framework', [
                'assets' => [
                    'packages' => [
                        Configuration::ALIAS => [
                            'base_path' => '/bundles/nowouikit',
                        ],
                    ],
                ],
            ]);
        }

        // No framework.translator prepend: FrameworkBundle already discovers this bundle's
        // Resources/translations (NowoUiKitBundle domain), and prepending would force-enable
        // the translator and push `en` in front of the host's fallbacks.

        if (!$container->hasExtension('twig')) {
            return;
        }

        $config = $this->processConfiguration(new Configuration(), $container->getExtensionConfig($this->getAlias()));
        $fw = CssFramework::from($config['css_framework'])->normalized()->value;

        $twig = [
            'globals' => [
                'nowo_ui_kit_css_framework' => $fw,
                'nowo_ui_kit_icon_set' => $config['icon_set'],
                'nowo_ui_kit_row_actions_display' => $config['row_actions_display'],
                'nowo_ui_kit_release_check' => [
                    'enabled' => $config['release_check']['enabled'],
                    'current_version' => $config['release_check']['current_version'],
                ],
                'nowo_ui_kit_shell' => $config['shell'],
            ],
        ];
        // 2.15: the date-range field's blocks (only `nowo_ui_date_range_*`) in every form, whatever the host theme.
        if (class_exists(AbstractType::class)) {
            $twig['form_themes'] = [self::DATE_RANGE_FORM_THEME];
        }
        $container->prependExtensionConfig('twig', $twig);
    }

    /**
     * @param array<int, array<string, mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $fw = CssFramework::from($config['css_framework'])->normalized()->value;

        $container->setParameter('nowo_ui_kit.css_framework', $fw);
        $container->setParameter('nowo_ui_kit.icon_set', $config['icon_set']);
        $container->setParameter('nowo_ui_kit.row_actions_display', $config['row_actions_display']);

        /** @var array<string, string> $rewrites */
        $rewrites = $config['panel_path_rewrites'];
        $container->setParameter('nowo_ui_kit.panel_path_rewrites', $rewrites);
        $this->registerPanelPathRewrites($container, $rewrites);

        /** @var array{enabled: bool, github_repo: string, current_version: string, cache_ttl: int, default_branch: string, user_agent: string} $releaseCheck */
        $releaseCheck = $config['release_check'];
        $container->setParameter('nowo_ui_kit.release_check.enabled', $releaseCheck['enabled']);
        $container->setParameter('nowo_ui_kit.release_check.github_repo', $releaseCheck['github_repo']);
        $container->setParameter('nowo_ui_kit.release_check.current_version', $releaseCheck['current_version']);
        $this->registerReleaseCheck($container, $releaseCheck);

        /** @var array{enabled: bool, provider: ?string, defaults: array{brand_name: string, brand_eyebrow: string, preset: string, preset_dark: string, corner_style: string, border_strength: string, footer_fixed: bool}, presets: array<string, array<string, mixed>>, builtin_presets: list<string>, kit_tokens: bool, token_aliases: array<string, list<string>>, selectors: array{root: string, light: string, dark: string}, shadow_rgb_fallback: string} $appearance */
        $appearance = $config['appearance'];
        $container->setParameter('nowo_ui_kit.appearance.enabled', $appearance['enabled']);
        $this->registerAppearance($container, $appearance);
        $this->registerMultiSelect($container);
        $this->registerDateRange($container);

        /** @var array{base_url: string, default_testid: ?string, routes: array<string, array{path: string, testid: ?string}>} $routeLinks */
        $routeLinks = $config['route_links'];
        $container->setParameter('nowo_ui_kit.route_links', $routeLinks);
        $container->register('nowo_ui_kit.route_link_twig_extension', RouteLinkExtension::class)
            ->setArguments([
                $routeLinks['routes'],
                $routeLinks['base_url'],
                $routeLinks['default_testid'],
                new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('twig.extension');

        $this->registerPageContract($container, $fw, $config);
        $this->registerListingCustomizer($container);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');
    }

    /**
     * REQ-UI-003 page contract: theme service (slot classes / attributes / partials / tokens) and its Twig
     * extension (functions + `nowo_ui_layout` / `nowo_ui_theme` / `nowo_ui_breadcrumbs` globals).
     *
     * @param array<string, mixed> $config
     */
    private function registerPageContract(ContainerBuilder $container, string $framework, array $config): void
    {
        /** @var array{admin: string, guest: string} $layouts */
        $layouts = $config['layouts'];
        /** @var array{breadcrumb_kit: bool, collection: string|null, enabled: bool} $breadcrumbs */
        $breadcrumbs = $config['breadcrumbs'];
        /** @var array<string, mixed> $theme */
        $theme = $config['theme'];

        $container->setParameter('nowo_ui_kit.layouts', $layouts);
        $container->setParameter('nowo_ui_kit.theme', $theme);

        $container->register('nowo_ui_kit.theme', Theme::class)
            ->setFactory([Theme::class, 'fromConfig'])
            ->setArguments([$framework, $theme]);
        $container->setAlias(Theme::class, 'nowo_ui_kit.theme');

        $container->registerForAutoconfiguration(AreaNavProviderInterface::class)
            ->addTag(AreaNavProviderInterface::TAG);
        $container->register('nowo_ui_kit.area_nav_registry', AreaNavRegistry::class)
            ->setArguments([new TaggedIteratorArgument(AreaNavProviderInterface::TAG)]);
        $container->setAlias(AreaNavRegistry::class, 'nowo_ui_kit.area_nav_registry');
        $container->register('nowo_ui_kit.area_nav_twig_extension', AreaNavExtension::class)
            ->setArguments([new Reference('nowo_ui_kit.area_nav_registry')])
            ->addTag('twig.extension');

        /** @var array<string, mixed> $errorPages */
        $errorPages = $config['error_pages'];
        $container->setParameter('nowo_ui_kit.error_pages', $errorPages);
        $container->register('nowo_ui_kit.error_page_twig_extension', ErrorPageExtension::class)
            ->setArguments([
                $errorPages,
                new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('twig.extension');

        $container->register('nowo_ui_kit.theme_twig_extension', ThemeExtension::class)
            ->setArguments([
                new Reference('nowo_ui_kit.theme'),
                $layouts,
                $breadcrumbs,
                new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('twig.extension');
    }

    /**
     * ChoiceType option `nowo_ui_multi_select` (2.14, markup of the `multi-select` Stimulus peer); only with
     * symfony/form.
     */
    private function registerMultiSelect(ContainerBuilder $container): void
    {
        if (!class_exists(AbstractType::class)) {
            return;
        }
        $container->register('nowo_ui_kit.form.multi_select_extension', MultiSelectTypeExtension::class)
            ->setArguments([new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->addTag('form.type_extension');
    }

    /**
     * Date-range filter field (2.15): `DateRangeType` (form type, translated short labels) and the submit extension
     * that maps its two host-named parameters onto it; only with symfony/form.
     */
    private function registerDateRange(ContainerBuilder $container): void
    {
        if (!class_exists(AbstractType::class)) {
            return;
        }
        $container->register('nowo_ui_kit.form.date_range_type', DateRangeType::class)
            ->setArguments([new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->addTag('form.type');
        $container->register('nowo_ui_kit.form.date_range_submit_extension', DateRangeSubmitExtension::class)
            ->addTag('form.type_extension');
    }

    /**
     * Listing customizer: autoconfigured definitions (tag `nowo_ui_kit.listing_definition`), their registry, the
     * default cookie preference store behind the {@see ListingPreferenceStoreInterface} port (a host re-aliases the
     * interface to its own store) and the view builder.
     */
    private function registerListingCustomizer(ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(ListingDefinitionInterface::class)
            ->addTag(ListingDefinitionInterface::TAG);
        $container->register('nowo_ui_kit.listing.registry', ListingRegistry::class)
            ->setArguments([new TaggedIteratorArgument(ListingDefinitionInterface::TAG)]);
        $container->setAlias(ListingRegistry::class, 'nowo_ui_kit.listing.registry');

        $container->register('nowo_ui_kit.listing.cookie_store', CookieListingPreferenceStore::class);
        $container->setAlias(CookieListingPreferenceStore::class, 'nowo_ui_kit.listing.cookie_store');
        $container->setAlias(ListingPreferenceStoreInterface::class, 'nowo_ui_kit.listing.cookie_store');

        $container->register('nowo_ui_kit.listing.view_builder', ListingCustomizerViewBuilder::class)
            ->setArguments([
                new Reference('nowo_ui_kit.listing.registry'),
                new Reference(ListingPreferenceStoreInterface::class),
                new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ]);
        $container->setAlias(ListingCustomizerViewBuilder::class, 'nowo_ui_kit.listing.view_builder');
    }

    /**
     * Appearance SPI services (only when enabled): preset registry, config provider, CSS emitter, Twig
     * extension, optional form types (symfony/form).
     *
     * @param array{enabled: bool, provider: ?string, defaults: array{brand_name: string, brand_eyebrow: string, preset: string, preset_dark: string, corner_style: string, border_strength: string, footer_fixed: bool}, presets: array<string, array<string, mixed>>, builtin_presets: list<string>, kit_tokens: bool, token_aliases: array<string, list<string>>, selectors: array{root: string, light: string, dark: string}, shadow_rgb_fallback: string} $appearance
     */
    private function registerAppearance(ContainerBuilder $container, array $appearance): void
    {
        if (!$appearance['enabled']) {
            return;
        }

        $container->registerForAutoconfiguration(AppearancePresetProviderInterface::class)
            ->addTag(AppearancePresetProviderInterface::TAG);

        $presets = [];
        foreach ($appearance['presets'] as $id => $data) {
            $presets[] = (new Definition(AppearancePreset::class))
                ->setFactory([AppearancePreset::class, 'fromArray'])
                ->setArguments([(string) $id, $data]);
        }

        $container->register('nowo_ui_kit.appearance.preset_registry', AppearancePresetRegistry::class)
            ->setArguments([$presets, new TaggedIteratorArgument(AppearancePresetProviderInterface::TAG), $appearance['builtin_presets']]);
        $container->setAlias(AppearancePresetRegistry::class, 'nowo_ui_kit.appearance.preset_registry');

        $container->register('nowo_ui_kit.appearance.config_provider', ConfigAppearanceProvider::class)
            ->setArguments([new Reference('nowo_ui_kit.appearance.preset_registry'), $appearance['defaults']]);
        $container->setAlias(ConfigAppearanceProvider::class, 'nowo_ui_kit.appearance.config_provider');

        $providerId = $appearance['provider'] ?? 'nowo_ui_kit.appearance.config_provider';
        $container->setAlias('nowo_ui_kit.appearance.provider', $providerId);
        $container->setAlias(AppearanceProviderInterface::class, 'nowo_ui_kit.appearance.provider');

        $container->register('nowo_ui_kit.appearance.css_emitter', AppearanceCssEmitter::class)
            ->setArguments([
                $appearance['token_aliases'],
                $appearance['kit_tokens'],
                $appearance['selectors'],
                $appearance['shadow_rgb_fallback'],
            ]);
        $container->setAlias(AppearanceCssEmitter::class, 'nowo_ui_kit.appearance.css_emitter');

        $container->register('nowo_ui_kit.appearance.twig_extension', AppearanceExtension::class)
            ->setArguments([
                new Reference('nowo_ui_kit.appearance.provider'),
                new Reference('nowo_ui_kit.appearance.css_emitter'),
                new Reference('nowo_ui_kit.appearance.preset_registry'),
                new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('twig.extension');

        if (class_exists(AbstractType::class)) {
            $container->register('nowo_ui_kit.appearance.form_type', AppearanceType::class)
                ->setArguments([new Reference('nowo_ui_kit.appearance.preset_registry')])
                ->addTag('form.type');
            $container->register('nowo_ui_kit.appearance.palette_form_type', AppearancePaletteType::class)
                ->addTag('form.type');
        }
    }

    /**
     * The controller is always registered (404 while disabled) so an imported route never breaks;
     * the checker (HTTP client + cache) only when enabled.
     *
     * @param array{enabled: bool, github_repo: string, current_version: string, cache_ttl: int, default_branch: string, user_agent: string} $releaseCheck
     */
    private function registerReleaseCheck(ContainerBuilder $container, array $releaseCheck): void
    {
        $controller = $container->register('nowo_ui_kit.release_status_controller', ReleaseStatusController::class)
            ->setPublic(true)
            ->addTag('controller.service_arguments');

        if (!$releaseCheck['enabled']) {
            return;
        }

        if (!($this->httpClientAvailable)()) {
            throw new \LogicException('nowo_ui_kit.release_check.enabled requires symfony/http-client (composer require symfony/http-client).');
        }

        $container->register('nowo_ui_kit.release_update_checker', ReleaseUpdateChecker::class)
            ->setArguments([
                new Reference('http_client'),
                new Reference('cache.app'),
                $releaseCheck['current_version'],
                $releaseCheck['github_repo'],
                true,
                $releaseCheck['cache_ttl'],
                $releaseCheck['user_agent'],
                $releaseCheck['default_branch'],
                new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
            ])
            ->setPublic(false);
        $container->setAlias(ReleaseUpdateChecker::class, 'nowo_ui_kit.release_update_checker')->setPublic(false);

        $controller->setArguments([new Reference('nowo_ui_kit.release_update_checker')]);
    }

    /**
     * Registers the routing loader decorator and legacy redirect subscriber (only when the map is non-empty).
     *
     * @param array<string, string> $rewrites
     */
    private function registerPanelPathRewrites(ContainerBuilder $container, array $rewrites): void
    {
        if ([] === $rewrites) {
            return;
        }

        $container->register('nowo_ui_kit.panel_path_rewriter', PanelPathRewriter::class)
            ->setArguments([$rewrites])
            ->setPublic(false);

        $container->register('nowo_ui_kit.panel_path_rewriting_loader', PanelPathRewritingLoader::class)
            ->setArguments([new Reference('nowo_ui_kit.panel_path_rewriting_loader.inner'), new Reference('nowo_ui_kit.panel_path_rewriter')])
            ->setDecoratedService('routing.loader', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
            ->setPublic(false);

        $container->register('nowo_ui_kit.legacy_panel_path_redirect_subscriber', LegacyPanelPathRedirectSubscriber::class)
            ->setArguments([new Reference('nowo_ui_kit.panel_path_rewriter')])
            ->addTag('kernel.event_subscriber')
            ->setPublic(false);
    }
}
