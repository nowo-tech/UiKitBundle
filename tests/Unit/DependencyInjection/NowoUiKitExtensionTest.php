<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\DependencyInjection;

use LogicException;
use Nowo\UiKitBundle\DependencyInjection\Configuration;
use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use Nowo\UiKitBundle\Dialog\RemoteDialogResponder;
use Nowo\UiKitBundle\Release\ReleaseStatusController;
use Nowo\UiKitBundle\Release\ReleaseUpdateChecker;
use Nowo\UiKitBundle\Routing\PanelPathRewritingLoader;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Yaml\Yaml;

final class NowoUiKitExtensionTest extends TestCase
{
    public function testLoadSetsParametersAndNormalizesBootstrapAlias(): void
    {
        $container = new ContainerBuilder();
        $extension = new NowoUiKitExtension();

        $extension->load([['css_framework' => 'bootstrap', 'icon_set' => 'none']], $container);

        self::assertSame('bootstrap5', $container->getParameter('nowo_ui_kit.css_framework'));
        self::assertSame('none', $container->getParameter('nowo_ui_kit.icon_set'));
        self::assertSame('icon', $container->getParameter('nowo_ui_kit.row_actions_display'));
    }

    public function testLoadSetsRowActionsDisplay(): void
    {
        $container = new ContainerBuilder();
        $extension = new NowoUiKitExtension();

        $extension->load([['row_actions_display' => 'icon_text']], $container);

        self::assertSame('icon_text', $container->getParameter('nowo_ui_kit.row_actions_display'));
    }

    public function testRemoteDialogResponderIsPublicAndTheRedirectListenerOptIn(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([[]], $container);
        self::assertTrue($container->getDefinition('nowo_ui_kit.remote_dialog_responder')->isPublic());
        self::assertTrue($container->hasAlias(RemoteDialogResponder::class));
        self::assertFalse($container->hasDefinition('nowo_ui_kit.remote_dialog_redirect_listener'));
        self::assertNull($container->getParameter('nowo_ui_kit.theme')['menu_semantics']);

        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([['remote_dialog' => ['convert_redirects' => true], 'theme' => ['menu_semantics' => 'menu']]], $container);
        $listener = $container->getDefinition('nowo_ui_kit.remote_dialog_redirect_listener');
        self::assertSame([['event' => 'kernel.response', 'priority' => -10]], $listener->getTag('kernel.event_listener'));
        self::assertSame('menu', $container->getParameter('nowo_ui_kit.theme')['menu_semantics']);
    }

    public function testEmptyPanelPathRewritesRegistersNoRoutingServices(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([[]], $container);

        self::assertSame([], $container->getParameter('nowo_ui_kit.panel_path_rewrites'));
        self::assertFalse($container->hasDefinition('nowo_ui_kit.panel_path_rewriter'));
        self::assertFalse($container->hasDefinition('nowo_ui_kit.panel_path_rewriting_loader'));
        self::assertFalse($container->hasDefinition('nowo_ui_kit.legacy_panel_path_redirect_subscriber'));
    }

    public function testPanelPathRewritesRegistersLoaderDecoratorAndSubscriber(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([['panel_path_rewrites' => ['/admin/blog' => '/panel/blog']]], $container);

        self::assertSame(['/admin/blog' => '/panel/blog'], $container->getParameter('nowo_ui_kit.panel_path_rewrites'));
        self::assertTrue($container->hasDefinition('nowo_ui_kit.panel_path_rewriter'));

        $loader = $container->getDefinition('nowo_ui_kit.panel_path_rewriting_loader');
        self::assertSame('routing.loader', $loader->getDecoratedService()[0] ?? null);

        $subscriber = $container->getDefinition('nowo_ui_kit.legacy_panel_path_redirect_subscriber');
        self::assertTrue($subscriber->hasTag('kernel.event_subscriber'));
    }

    public function testDecoratorWrapsRoutingLoaderWhenCompiled(): void
    {
        $container = new ContainerBuilder();
        $container->register('routing.loader', DelegatingLoader::class)
            ->setArguments([new LoaderResolver()])
            ->setPublic(true);
        (new NowoUiKitExtension())->load([['panel_path_rewrites' => ['/admin/blog' => '/panel/blog']]], $container);
        $container->compile();

        self::assertInstanceOf(
            PanelPathRewritingLoader::class,
            $container->get('routing.loader'),
        );
    }

    public function testPrependRegistersAssetPackage(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'framework';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });

        $extension = new NowoUiKitExtension();
        $extension->prepend($container);

        $frameworkConfigs = $container->getExtensionConfig('framework');
        $found = false;
        foreach ($frameworkConfigs as $config) {
            if (isset($config['assets']['packages'][Configuration::ALIAS]['base_path'])) {
                self::assertSame('/bundles/nowouikit', $config['assets']['packages'][Configuration::ALIAS]['base_path']);
                $found = true;
            }
        }
        self::assertTrue($found, 'Expected nowo_ui_kit asset package in framework prepend config');
    }

    public function testPrependRegistersTwigGlobalsWhenTwigPresent(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'framework';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });
        $container->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'twig';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });

        $extension = new NowoUiKitExtension();
        $container->prependExtensionConfig(Configuration::ALIAS, [
            'css_framework' => 'tailwind',
            'icon_set' => 'svg_inline',
            'row_actions_display' => 'text',
            'row_actions_confirm' => 'dialog',
        ]);
        $extension->prepend($container);

        $twigConfigs = $container->getExtensionConfig('twig');
        $globals = null;
        foreach ($twigConfigs as $config) {
            if (isset($config['globals']['nowo_ui_kit_css_framework'])) {
                $globals = $config['globals'];
            }
        }
        self::assertNotNull($globals);
        self::assertSame('tailwind', $globals['nowo_ui_kit_css_framework']);
        self::assertSame('svg_inline', $globals['nowo_ui_kit_icon_set']);
        self::assertSame('text', $globals['nowo_ui_kit_row_actions_display']);
        self::assertSame('dialog', $globals['nowo_ui_kit_row_actions_confirm']);
        self::assertSame(['brand' => 'auto'], $globals['nowo_ui_kit_auth_panel'], '2.44: auth_panel.brand defaults to auto');
    }

    public function testReleaseCheckDisabledRegistersControllerOnly(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([[]], $container);

        self::assertFalse($container->getParameter('nowo_ui_kit.release_check.enabled'));
        self::assertTrue($container->hasDefinition('nowo_ui_kit.release_status_controller'));
        self::assertSame([], $container->getDefinition('nowo_ui_kit.release_status_controller')->getArguments());
        self::assertTrue($container->getDefinition('nowo_ui_kit.release_status_controller')->hasTag('controller.service_arguments'));
        self::assertFalse($container->hasDefinition('nowo_ui_kit.release_update_checker'));
    }

    public function testReleaseCheckEnabledRequiresTheHttpClient(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires symfony/http-client');
        (new NowoUiKitExtension(static fn (): bool => false))->load([['release_check' => ['enabled' => true]]], new ContainerBuilder());
    }

    public function testReleaseCheckEnabledRegistersCheckerAndWiresController(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([[
            'release_check' => [
                'enabled' => true,
                'github_repo' => 'acme/app',
                'current_version' => 'v1.2.3',
                'cache_ttl' => 120,
                'default_branch' => 'develop',
                'user_agent' => 'acme-app',
            ],
        ]], $container);

        self::assertTrue($container->getParameter('nowo_ui_kit.release_check.enabled'));
        self::assertSame('acme/app', $container->getParameter('nowo_ui_kit.release_check.github_repo'));
        self::assertSame('v1.2.3', $container->getParameter('nowo_ui_kit.release_check.current_version'));

        $checker = $container->getDefinition('nowo_ui_kit.release_update_checker');
        self::assertSame(ReleaseUpdateChecker::class, $checker->getClass());
        $args = $checker->getArguments();
        self::assertSame('http_client', (string) $args[0]);
        self::assertSame('cache.app', (string) $args[1]);
        self::assertSame(['v1.2.3', 'acme/app', true, 120, 'acme-app', 'develop'], \array_slice($args, 2, 6));
        self::assertSame([5.0, 8.0], \array_slice($args, 9, 2));
        self::assertTrue($container->hasAlias(ReleaseUpdateChecker::class));

        $controllerArgs = $container->getDefinition('nowo_ui_kit.release_status_controller')->getArguments();
        self::assertSame('nowo_ui_kit.release_update_checker', (string) $controllerArgs[0]);
    }

    public function testReleaseCheckTimeoutsAreWiredIntoTheChecker(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([[
            'release_check' => ['enabled' => true, 'github_repo' => 'acme/app', 'timeout' => 1.5, 'releases_timeout' => 2.0],
        ]], $container);

        $args = $container->getDefinition('nowo_ui_kit.release_update_checker')->getArguments();
        self::assertSame([1.5, 2.0], \array_slice($args, 9, 2));
    }

    public function testPrependExposesReleaseCheckTwigGlobal(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'twig';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });
        $container->prependExtensionConfig(Configuration::ALIAS, [
            'release_check' => ['enabled' => true, 'github_repo' => 'acme/app', 'current_version' => 'v9.9.9'],
        ]);
        (new NowoUiKitExtension())->prepend($container);

        $globals = $container->getExtensionConfig('twig')[0]['globals'] ?? [];
        self::assertSame(['enabled' => true, 'current_version' => 'v9.9.9'], $globals['nowo_ui_kit_release_check'] ?? null);
    }

    public function testReleaseCheckAcceptsParametersAndEnvPlaceholders(): void
    {
        // 2.33 (UI-5): prepend() runs before parameters resolve; the raw references reach the Twig global.
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'twig';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });
        $container->prependExtensionConfig(Configuration::ALIAS, [
            'release_check' => ['enabled' => '%env(bool:APP_UPDATE_CHECK)%', 'github_repo' => 'acme/app', 'current_version' => '%app.version%'],
        ]);
        (new NowoUiKitExtension(static fn (): bool => true))->prepend($container);
        $globals = $container->getExtensionConfig('twig')[0]['globals'] ?? [];
        self::assertSame(['enabled' => '%env(bool:APP_UPDATE_CHECK)%', 'current_version' => '%app.version%'], $globals['nowo_ui_kit_release_check'] ?? null);

        // load() gets resolved parameters: a false parameter registers no checker.
        $container = new ContainerBuilder();
        (new NowoUiKitExtension(static fn (): bool => true))->load([['release_check' => ['enabled' => false, 'current_version' => '1.0.0']]], $container);
        self::assertFalse($container->hasDefinition('nowo_ui_kit.release_update_checker'));

        // Through the real merge pass: a parameter resolves before load(), an env placeholder stays a runtime value.
        foreach (['%app.update_check%' => false, '%env(bool:APP_UPDATE_CHECK)%' => null] as $enabled => $expected) {
            $container = new ContainerBuilder(new EnvPlaceholderParameterBag(['app.update_check' => false, 'app.version' => '1.2.3']));
            $container->registerExtension(new NowoUiKitExtension(static fn (): bool => true));
            $container->loadFromExtension(Configuration::ALIAS, ['release_check' => ['enabled' => $enabled, 'github_repo' => 'acme/app', 'current_version' => '%app.version%']]);
            (new MergeExtensionConfigurationPass())->process($container);
            self::assertSame('1.2.3', $container->getParameter('nowo_ui_kit.release_check.current_version'));
            if (false === $expected) {
                self::assertFalse($container->hasDefinition('nowo_ui_kit.release_update_checker'));
                continue;
            }
            $argument = $container->getDefinition('nowo_ui_kit.release_update_checker')->getArgument(4);
            self::assertIsString($argument);
            self::assertStringContainsString('APP_UPDATE_CHECK', (string) $container->resolveEnvPlaceholders($argument, '%%env(%s)%%'));
        }
    }

    public function testRouteFileIsAThinAttributeImportOfTheStatusController(): void
    {
        $routes = Yaml::parseFile(\dirname(__DIR__, 3).'/src/Resources/config/routes/release_check.yaml');
        self::assertIsArray($routes);
        self::assertSame([
            'nowo_ui_kit_release_status_attribute' => ['resource' => ReleaseStatusController::class, 'type' => 'attribute'],
        ], $routes);

        $attributes = (new ReflectionClass(ReleaseStatusController::class))->getAttributes(Route::class);
        self::assertCount(1, $attributes);
        $route = $attributes[0]->newInstance();
        self::assertSame('nowo_ui_kit_release_status', $route->name);
        self::assertSame('/_nowo-ui/release/status', $route->path);
        self::assertSame(['GET'], $route->methods);
    }

    public function testStatusControllerIsExplicitPublicAndNotAutoconfigured(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([[]], $container);

        $definition = $container->getDefinition('nowo_ui_kit.release_status_controller');
        self::assertTrue($definition->isPublic());
        self::assertFalse($definition->isAutoconfigured());
        self::assertTrue($definition->hasTag('controller.service_arguments'));
        self::assertSame('nowo_ui_kit.release_status_controller', (string) $container->getAlias(ReleaseStatusController::class));
        self::assertTrue($container->getAlias(ReleaseStatusController::class)->isPublic());
    }

    public function testAlias(): void
    {
        self::assertSame(Configuration::ALIAS, (new NowoUiKitExtension())->getAlias());
    }
}
