<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\DependencyInjection;

use Nowo\UiKitBundle\DependencyInjection\Configuration;
use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

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
        $container->register('routing.loader', \Symfony\Component\Config\Loader\DelegatingLoader::class)
            ->setArguments([new \Symfony\Component\Config\Loader\LoaderResolver()])
            ->setPublic(true);
        (new NowoUiKitExtension())->load([['panel_path_rewrites' => ['/admin/blog' => '/panel/blog']]], $container);
        $container->compile();

        self::assertInstanceOf(
            \Nowo\UiKitBundle\Routing\PanelPathRewritingLoader::class,
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
        self::assertSame(\Nowo\UiKitBundle\Release\ReleaseUpdateChecker::class, $checker->getClass());
        $args = $checker->getArguments();
        self::assertSame('http_client', (string) $args[0]);
        self::assertSame('cache.app', (string) $args[1]);
        self::assertSame(['v1.2.3', 'acme/app', true, 120, 'acme-app', 'develop'], \array_slice($args, 2, 6));
        self::assertTrue($container->hasAlias(\Nowo\UiKitBundle\Release\ReleaseUpdateChecker::class));

        $controllerArgs = $container->getDefinition('nowo_ui_kit.release_status_controller')->getArguments();
        self::assertSame('nowo_ui_kit.release_update_checker', (string) $controllerArgs[0]);
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

    public function testRouteFileDeclaresStatusEndpoint(): void
    {
        $routes = \Symfony\Component\Yaml\Yaml::parseFile(\dirname(__DIR__, 3).'/src/Resources/config/routes/release_check.yaml');
        self::assertIsArray($routes);
        self::assertSame('/_nowo-ui/release/status', $routes['nowo_ui_kit_release_status']['path']);
        self::assertSame('nowo_ui_kit.release_status_controller', $routes['nowo_ui_kit_release_status']['controller']);
        self::assertSame(['GET'], $routes['nowo_ui_kit_release_status']['methods']);
    }

    public function testAlias(): void
    {
        self::assertSame(Configuration::ALIAS, (new NowoUiKitExtension())->getAlias());
    }
}
