<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\DependencyInjection;

use Closure;
use LogicException;
use Nowo\Brand\Contract\BrandProviderInterface;
use Nowo\UiKitBundle\Appearance\AppearanceCssEmitter;
use Nowo\UiKitBundle\Appearance\AppearancePreset;
use Nowo\UiKitBundle\Appearance\AppearancePresetProviderInterface;
use Nowo\UiKitBundle\Appearance\AppearancePresetRegistry;
use Nowo\UiKitBundle\Appearance\AppearanceProviderInterface;
use Nowo\UiKitBundle\Appearance\AppearanceView;
use Nowo\UiKitBundle\Appearance\BrandAppearanceProvider;
use Nowo\UiKitBundle\Appearance\ConfigAppearanceProvider;
use Nowo\UiKitBundle\DependencyInjection\Compiler\BrandAppearanceProviderPass;
use Nowo\UiKitBundle\Dialog\RemoteDialogHeaders;
use Nowo\UiKitBundle\Dialog\RemoteDialogRedirectListener;
use Nowo\UiKitBundle\Dialog\RemoteDialogResponder;
use Nowo\UiKitBundle\Enum\CssFramework;
use Nowo\UiKitBundle\EventSubscriber\LegacyPanelPathRedirectSubscriber;
use Nowo\UiKitBundle\Form\AppearancePaletteType;
use Nowo\UiKitBundle\Form\AppearanceType;
use Nowo\UiKitBundle\Form\DateRangeRequestHandler;
use Nowo\UiKitBundle\Form\DateRangeSubmitExtension;
use Nowo\UiKitBundle\Form\DateRangeType;
use Nowo\UiKitBundle\Form\EmojiPickerType;
use Nowo\UiKitBundle\Form\ImageCropTypeExtension;
use Nowo\UiKitBundle\Form\ImageInputTypeExtension;
use Nowo\UiKitBundle\Form\MultiSelectTypeExtension;
use Nowo\UiKitBundle\Listing\CookieListingPreferenceStore;
use Nowo\UiKitBundle\Listing\ListingCustomizerViewBuilder;
use Nowo\UiKitBundle\Listing\ListingDefinitionInterface;
use Nowo\UiKitBundle\Listing\ListingPreferenceReaderInterface;
use Nowo\UiKitBundle\Listing\ListingPreferenceStoreInterface;
use Nowo\UiKitBundle\Listing\ListingRegistry;
use Nowo\UiKitBundle\Listing\ListingViewerResolverInterface;
use Nowo\UiKitBundle\Navigation\AreaNavProviderInterface;
use Nowo\UiKitBundle\Navigation\AreaNavRegistry;
use Nowo\UiKitBundle\Release\ReleaseStatusController;
use Nowo\UiKitBundle\Release\ReleaseUpdateChecker;
use Nowo\UiKitBundle\Routing\PanelPathRewriter;
use Nowo\UiKitBundle\Routing\PanelPathRewritingLoader;
use Nowo\UiKitBundle\Theme\FontPreload;
use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Twig\ActionIconsExtension;
use Nowo\UiKitBundle\Twig\AppearanceExtension;
use Nowo\UiKitBundle\Twig\AreaNavExtension;
use Nowo\UiKitBundle\Twig\AvatarExtension;
use Nowo\UiKitBundle\Twig\ErrorPageExtension;
use Nowo\UiKitBundle\Twig\RouteLinkExtension;
use Nowo\UiKitBundle\Twig\ThemeExtension;
use Symfony\Component\Asset\Package;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class NowoUiKitExtension extends Extension implements PrependExtensionInterface
{
    /** Form theme with the date-range field's blocks (2.15), prepended to `twig.form_themes`. */
    public const DATE_RANGE_FORM_THEME = '@NowoUiKit/form/nowo_ui_date_range.html.twig';

    /** Form theme with the emoji picker field's block, prepended to `twig.form_themes`. */
    public const EMOJI_PICKER_FORM_THEME = '@NowoUiKit/form/nowo_ui_emoji_picker.html.twig';

    /** Form theme with the image input's block (2.47, FileType option `nowo_ui_image_input`), prepended to `twig.form_themes`. */
    public const IMAGE_INPUT_FORM_THEME = '@NowoUiKit/form/nowo_ui_image_input.html.twig';

    /** @var Closure(): bool */
    private readonly Closure $httpClientAvailable;

    /**
     * @param (Closure(): bool)|null $httpClientAvailable whether symfony/http-client is installed (overridable in tests)
     */
    public function __construct(?Closure $httpClientAvailable = null)
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

        $configs = $container->getExtensionConfig($this->getAlias());
        // 2.33 (UI-5): release_check.enabled / current_version may be container parameters or env placeholders
        // ("%app.update_check%", "%env(bool:X)%"), which are not resolved yet here: they are kept raw for the Twig
        // global (TwigBundle resolves them) and left out of this early validation (load() validates them resolved).
        $releaseCheck = $this->rawReleaseCheck($configs);
        $config = $this->processConfiguration(new Configuration(), $this->withoutRuntimeReleaseCheck($configs));
        $fw = CssFramework::from($config['css_framework'])->normalized()->value;

        $twig = [
            'globals' => [
                'nowo_ui_kit_css_framework' => $fw,
                'nowo_ui_kit_icon_set' => $config['icon_set'],
                'nowo_ui_kit_row_actions_display' => $config['row_actions_display'],
                'nowo_ui_kit_row_actions_confirm' => $config['row_actions_confirm'],
                'nowo_ui_kit_release_check' => $releaseCheck,
                'nowo_ui_kit_shell' => $config['shell'],
                'nowo_ui_kit_auth_panel' => $config['auth_panel'],
            ],
        ];
        // 2.15: the date-range field's blocks (only `nowo_ui_date_range_*`) in every form, whatever the host theme.
        if (class_exists(AbstractType::class)) {
            $twig['form_themes'] = [self::DATE_RANGE_FORM_THEME, self::EMOJI_PICKER_FORM_THEME, self::IMAGE_INPUT_FORM_THEME];
        }
        $container->prependExtensionConfig('twig', $twig);
    }

    /**
     * The `release_check.enabled` / `current_version` of the raw configs (the last one set wins, as when merged), with
     * the defaults `false` / `''`. Values stay as written: a bool, a version, or a `%parameter%` / `%env()%` string.
     *
     * @param array<int, array<string, mixed>> $configs
     *
     * @return array{enabled: mixed, current_version: mixed}
     */
    private function rawReleaseCheck(array $configs): array
    {
        $values = ['enabled' => false, 'current_version' => ''];
        foreach ($configs as $config) {
            $releaseCheck = $config['release_check'] ?? null;
            if (!\is_array($releaseCheck)) {
                continue;
            }
            foreach (['enabled', 'current_version'] as $key) {
                if (\array_key_exists($key, $releaseCheck)) {
                    $values[$key] = $releaseCheck[$key];
                }
            }
        }

        return $values;
    }

    /**
     * The raw configs without `release_check.enabled` / `current_version` written as `%…%` strings (validated in load(),
     * once parameters are resolved).
     *
     * @param array<int, array<string, mixed>> $configs
     *
     * @return array<int, array<string, mixed>>
     */
    private function withoutRuntimeReleaseCheck(array $configs): array
    {
        foreach ($configs as $index => $config) {
            if (!isset($config['release_check']) || !\is_array($config['release_check'])) {
                continue;
            }
            foreach (['enabled', 'current_version'] as $key) {
                $value = $config['release_check'][$key] ?? null;
                if (\is_string($value) && str_contains($value, '%')) {
                    unset($configs[$index]['release_check'][$key]);
                }
            }
        }

        return $configs;
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
        $container->setParameter('nowo_ui_kit.auth_panel.brand', $config['auth_panel']['brand']);

        /** @var array<string, string> $rewrites */
        $rewrites = $config['panel_path_rewrites'];
        $container->setParameter('nowo_ui_kit.panel_path_rewrites', $rewrites);
        $this->registerPanelPathRewrites($container, $rewrites);

        /** @var array{enabled: bool|string, github_repo: string, current_version: string, cache_ttl: int, default_branch: string, user_agent: string, timeout: float, releases_timeout: float} $releaseCheck */
        $releaseCheck = $config['release_check'];
        $container->setParameter('nowo_ui_kit.release_check.enabled', $releaseCheck['enabled']);
        $container->setParameter('nowo_ui_kit.release_check.github_repo', $releaseCheck['github_repo']);
        $container->setParameter('nowo_ui_kit.release_check.current_version', $releaseCheck['current_version']);
        $this->registerReleaseCheck($container, $releaseCheck);

        /** @var array{enabled: bool, provider: ?string, brand: string, head_links: bool, defaults: array{brand_name: string, brand_eyebrow: string, preset: string, preset_dark: string, corner_style: string, border_strength: string, footer_fixed: bool}, presets: array<string, array<string, mixed>>, builtin_presets: list<string>, kit_tokens: bool, token_aliases: array<string, list<string>>, selectors: array{root: string, light: string, dark: string}, shadow_rgb_fallback: string} $appearance */
        $appearance = $config['appearance'];
        $container->setParameter('nowo_ui_kit.appearance.enabled', $appearance['enabled']);
        // 2.40 (spec 220): lets a bridge (InstanceSettings) take the provider over only when the host set none.
        $container->setParameter('nowo_ui_kit.appearance.provider_is_default', null === $appearance['provider']);
        $this->registerAppearance($container, $appearance);
        // Form types / extensions below exist only with symfony/form (optional dependency).
        if (class_exists(AbstractType::class)) {
            $this->registerMultiSelect($container);
            $this->registerDateRange($container);
        }

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

        // 2.26: nowo_ui_avatar_url (the avatar URL policy of partials/_avatar.html.twig).
        $container->register('nowo_ui_kit.avatar_twig_extension', AvatarExtension::class)
            ->addTag('twig.extension');

        // A service, not a Twig config global: it sees the processed config whatever the bundle order.
        $container->setParameter('nowo_ui_kit.action_icons', $config['action_icons']);
        $container->setParameter('nowo_ui_kit.action_variants', $config['action_variants']);
        $container->register('nowo_ui_kit.action_icons_twig_extension', ActionIconsExtension::class)
            ->setArguments([$config['action_icons'], $config['action_variants']])
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
                // 2.47: optional host provider, as a service closure resolved inside the extension's guard (a provider whose
                // constructor throws never breaks Twig; audit LK-L1). ErrorIllustrationProviderPass checks its class.
                \is_string($errorPages['illustration_provider'] ?? null) && '' !== $errorPages['illustration_provider']
                    ? new ServiceClosureArgument(new Reference($errorPages['illustration_provider']))
                    : null,
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('twig.extension');

        // 2.18: standalone factory (core types only) of the CSRF-only forms of POST actions with a csrfToken.
        $actionForms = null;
        if (class_exists(Forms::class)) {
            $container->register('nowo_ui_kit.action_form_factory', FormFactoryInterface::class)
                ->setFactory([Forms::class, 'createFormFactory']);
            $actionForms = new Reference('nowo_ui_kit.action_form_factory');
        }
        // 2.40 (spec 220 S1): the appearance as the kit templates read it (never throws; null provider while disabled).
        /** @var array{head_links: bool} $appearanceConfig */
        $appearanceConfig = $config['appearance'];
        $container->register('nowo_ui_kit.appearance.view', AppearanceView::class)
            ->setArguments([
                new Reference('nowo_ui_kit.appearance.provider', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                $appearanceConfig['head_links'],
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('kernel.reset', ['method' => 'reset']);
        $container->register('nowo_ui_kit.theme_twig_extension', ThemeExtension::class)
            ->setArguments([
                new Reference('nowo_ui_kit.theme'),
                $layouts,
                $breadcrumbs,
                new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setArgument('$actionForms', $actionForms)
            ->setArgument('$fontPreload', $this->registerFontPreload($container, $config))
            ->setArgument('$header', $config['header'])
            ->setArgument('$sidebarLayout', $config['shell']['sidebar_layout'])
            ->setArgument('$remoteDialogHeaders', new Reference('nowo_ui_kit.remote_dialog_headers'))
            ->setArgument('$appearanceView', new Reference('nowo_ui_kit.appearance.view'))
            ->setArgument('$baseStylesheet', $config['base_stylesheet'])
            ->addTag('twig.extension');

        $this->registerRemoteDialog($container, $config);
    }

    /**
     * 2.28 remote form dialog: the public `RemoteDialogResponder` service (autowirable) and, with
     * `remote_dialog.convert_redirects`, the `kernel.response` listener that turns redirects of dialog requests into
     * `204` + `X-Nowo-Redirect`.
     *
     * @param array<string, mixed> $config
     */
    private function registerRemoteDialog(ContainerBuilder $container, array $config): void
    {
        /** @var array{convert_redirects: bool, step_routes: list<string>, request_header: string, redirect_header: string, location_header: string} $remoteDialog */
        $remoteDialog = $config['remote_dialog'];
        $container->register('nowo_ui_kit.remote_dialog_headers', RemoteDialogHeaders::class)
            ->setArguments([$remoteDialog['request_header'], $remoteDialog['redirect_header'], $remoteDialog['location_header']]);
        $container->setAlias(RemoteDialogHeaders::class, 'nowo_ui_kit.remote_dialog_headers');
        $headers = new Reference('nowo_ui_kit.remote_dialog_headers');

        $container->register('nowo_ui_kit.remote_dialog_responder', RemoteDialogResponder::class)
            ->setArguments([
                new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('router', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                $headers,
            ])
            ->setPublic(true);
        $container->setAlias(RemoteDialogResponder::class, 'nowo_ui_kit.remote_dialog_responder')->setPublic(true);

        // 2.33: also for step routes alone (convert_redirects off), with the RemoteDialogRedirectEvent hook.
        if ($remoteDialog['convert_redirects'] || [] !== $remoteDialog['step_routes']) {
            $container->register('nowo_ui_kit.remote_dialog_redirect_listener', RemoteDialogRedirectListener::class)
                ->setArguments([
                    $headers,
                    $remoteDialog['convert_redirects'],
                    $remoteDialog['step_routes'],
                    new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ])
                ->addTag('kernel.event_listener', ['event' => 'kernel.response', 'priority' => -10]);
        }
    }

    /**
     * 2.21 font preload tags (`nowo_ui_kit.font_preload`): the {@see FontPreload} service, null when nothing is
     * configured (the layouts then print nothing).
     *
     * @param array<string, mixed> $config
     */
    private function registerFontPreload(ContainerBuilder $container, array $config): ?Reference
    {
        /** @var array{fonts: list<string>, manifest: array{path: ?string, entry: string, prefixes: list<string>, base: string}} $fonts */
        $fonts = $config['font_preload'];
        if ([] === $fonts['fonts'] && (null === $fonts['manifest']['path'] || [] === $fonts['manifest']['prefixes'])) {
            return null;
        }
        $container->register('nowo_ui_kit.font_preload', FontPreload::class)
            ->setArguments([
                $fonts['fonts'],
                $fonts['manifest']['path'],
                $fonts['manifest']['entry'],
                $fonts['manifest']['prefixes'],
                $fonts['manifest']['base'],
            ]);

        return new Reference('nowo_ui_kit.font_preload');
    }

    /**
     * ChoiceType option `nowo_ui_multi_select` (2.14, markup of the `multi-select` Stimulus peer) and FileType option
     * `nowo_ui_image_crop` (2.43) and `nowo_ui_image_input` (2.47); only with
     * symfony/form (guarded by the caller).
     */
    private function registerMultiSelect(ContainerBuilder $container): void
    {
        $container->register('nowo_ui_kit.form.multi_select_extension', MultiSelectTypeExtension::class)
            ->setArguments([new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->addTag('form.type_extension');
        // 2.43: FileType option `nowo_ui_image_crop` (attributes of js/nowo-ui-image-crop.js).
        $container->register('nowo_ui_kit.form.image_crop_extension', ImageCropTypeExtension::class)
            ->setArguments([new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->addTag('form.type_extension');
        // 2.47: FileType option `nowo_ui_image_input` (the image input box, js/nowo-ui-image-input.js).
        $container->register('nowo_ui_kit.form.image_input_extension', ImageInputTypeExtension::class)
            ->setArguments([
                new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('form.type_extension');
    }

    /**
     * Date-range filter field (2.15): `DateRangeType` (form type, translated short labels), the submit extension
     * that maps its two host-named parameters onto it and the request-handler decorator that lets those parameters
     * submit a nameless form (2.15.1); only with symfony/form (guarded by the caller).
     */
    private function registerDateRange(ContainerBuilder $container): void
    {
        $container->register('nowo_ui_kit.form.emoji_picker_type', EmojiPickerType::class)
            ->setArguments([new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->addTag('form.type');
        $container->register('nowo_ui_kit.form.date_range_type', DateRangeType::class)
            ->setArguments([new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->addTag('form.type');
        $container->register('nowo_ui_kit.form.date_range_submit_extension', DateRangeSubmitExtension::class)
            ->addTag('form.type_extension');
        // A nameless form counts a range's from / to parameter as a present child (2.15.1); no-op without the
        // FrameworkBundle's HttpFoundation request handler.
        $container->register('nowo_ui_kit.form.date_range_request_handler', DateRangeRequestHandler::class)
            ->setArguments([new Reference('nowo_ui_kit.form.date_range_request_handler.inner')])
            ->setDecoratedService('form.type_extension.form.request_handler', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE);
    }

    /**
     * Listing customizer: autoconfigured definitions (tag `nowo_ui_kit.listing_definition`), their registry, the
     * default cookie preference store behind the {@see ListingPreferenceReaderInterface} port (2.49) and the deprecated
     * {@see ListingPreferenceStoreInterface} (a host re-aliases either interface to its own store; the reader wins) and
     * the view builder (optional {@see ListingViewerResolverInterface}).
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
        $container->setAlias(ListingPreferenceReaderInterface::class, 'nowo_ui_kit.listing.cookie_store');

        $container->register('nowo_ui_kit.listing.view_builder', ListingCustomizerViewBuilder::class)
            ->setArguments([
                new Reference('nowo_ui_kit.listing.registry'),
                new Reference(ListingPreferenceStoreInterface::class),
                new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference(ListingPreferenceReaderInterface::class),
                new Reference(ListingViewerResolverInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                $container->hasParameter('kernel.debug') ? '%kernel.debug%' : false,
            ]);
        $container->setAlias(ListingCustomizerViewBuilder::class, 'nowo_ui_kit.listing.view_builder');
    }

    /**
     * Appearance SPI services (only when enabled): preset registry, config provider, CSS emitter, Twig
     * extension, optional form types (symfony/form).
     *
     * @param array{enabled: bool, provider: ?string, brand: string, head_links: bool, defaults: array{brand_name: string, brand_eyebrow: string, preset: string, preset_dark: string, corner_style: string, border_strength: string, footer_fixed: bool}, presets: array<string, array<string, mixed>>, builtin_presets: list<string>, kit_tokens: bool, token_aliases: array<string, list<string>>, selectors: array{root: string, light: string, dark: string}, shadow_rgb_fallback: string} $appearance
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
        // 2.40 (audit IS-L1): the kit's own fallback, private and never decorated (hosts may decorate config_provider).
        $container->register('nowo_ui_kit.appearance.defaults_provider', ConfigAppearanceProvider::class)
            ->setArguments([new Reference('nowo_ui_kit.appearance.preset_registry'), $appearance['defaults']])
            ->setPublic(false);

        $providerId = $appearance['provider'] ?? 'nowo_ui_kit.appearance.config_provider';
        $container->setAlias('nowo_ui_kit.appearance.provider', $providerId);
        $container->setAlias(AppearanceProviderInterface::class, 'nowo_ui_kit.appearance.provider');

        // 2.40 (spec 220 S1): candidate brand-driven default provider; BrandAppearanceProviderPass aliases it when a
        // BrandProviderInterface service exists, else removes it.
        if (null === $appearance['provider'] && 'auto' === $appearance['brand'] && interface_exists(BrandProviderInterface::class)) {
            $container->register(BrandAppearanceProviderPass::CANDIDATE, BrandAppearanceProvider::class)
                ->setArguments([
                    new Reference('nowo_ui_kit.appearance.defaults_provider'),
                    new Reference(BrandProviderInterface::class),
                    new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ]);
        }

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
                new Reference('nowo_ui_kit.appearance.defaults_provider'),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('nowo_ui_kit.appearance.view'),
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
     * @param array{enabled: bool|string, github_repo: string, current_version: string, cache_ttl: int, default_branch: string, user_agent: string, timeout: float, releases_timeout: float} $releaseCheck
     */
    private function registerReleaseCheck(ContainerBuilder $container, array $releaseCheck): void
    {
        $controller = $container->register('nowo_ui_kit.release_status_controller', ReleaseStatusController::class)
            ->setPublic(true)
            ->addTag('controller.service_arguments');
        // The attribute route's _controller is the class name: resolve it to the explicit service (REQ-SF-004).
        $container->setAlias(ReleaseStatusController::class, 'nowo_ui_kit.release_status_controller')->setPublic(true);

        // A literal false registers nothing; an env placeholder registers the checker, which reads it at runtime.
        if (false === $releaseCheck['enabled']) {
            return;
        }

        if (!($this->httpClientAvailable)()) {
            throw new LogicException('nowo_ui_kit.release_check.enabled requires symfony/http-client (composer require symfony/http-client).');
        }

        $container->register('nowo_ui_kit.release_update_checker', ReleaseUpdateChecker::class)
            ->setArguments([
                new Reference('http_client'),
                new Reference('cache.app'),
                $releaseCheck['current_version'],
                $releaseCheck['github_repo'],
                // 2.33 (UI-5): a parameter / env placeholder stays a runtime value (false = the check is skipped).
                $releaseCheck['enabled'],
                $releaseCheck['cache_ttl'],
                $releaseCheck['user_agent'],
                $releaseCheck['default_branch'],
                new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
                (float) $releaseCheck['timeout'],
                (float) $releaseCheck['releases_timeout'],
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
