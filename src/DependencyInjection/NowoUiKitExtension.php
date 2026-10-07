<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\DependencyInjection;

use Nowo\UiKitBundle\Enum\CssFramework;
use Nowo\UiKitBundle\EventSubscriber\LegacyPanelPathRedirectSubscriber;
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

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');
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
