<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\DependencyInjection;

use Nowo\UiKitBundle\Enum\CssFramework;
use Nowo\UiKitBundle\EventSubscriber\LegacyPanelPathRedirectSubscriber;
use Nowo\UiKitBundle\Release\ReleaseStatusController;
use Nowo\UiKitBundle\Release\ReleaseUpdateChecker;
use Nowo\UiKitBundle\Routing\PanelPathRewriter;
use Nowo\UiKitBundle\Routing\PanelPathRewritingLoader;
use Symfony\Component\Asset\Package;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class NowoUiKitExtension extends Extension implements PrependExtensionInterface
{
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

        $translationsPath = __DIR__.'/../Resources/translations';
        if (is_dir($translationsPath) && $container->hasExtension('framework')) {
            $container->prependExtensionConfig('framework', [
                'translator' => [
                    'paths' => [$translationsPath],
                    'fallbacks' => ['en'],
                ],
            ]);
        }

        if (!$container->hasExtension('twig')) {
            return;
        }

        $config = $this->processConfiguration(new Configuration(), $container->getExtensionConfig($this->getAlias()));
        $fw = CssFramework::from($config['css_framework'])->normalized()->value;

        $container->prependExtensionConfig('twig', [
            'globals' => [
                'nowo_ui_kit_css_framework' => $fw,
                'nowo_ui_kit_icon_set' => $config['icon_set'],
                'nowo_ui_kit_row_actions_display' => $config['row_actions_display'],
                'nowo_ui_kit_release_check' => [
                    'enabled' => $config['release_check']['enabled'],
                    'current_version' => $config['release_check']['current_version'],
                ],
            ],
        ]);
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

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');
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

        if (!interface_exists(HttpClientInterface::class) || !class_exists(HttpClient::class)) {
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
