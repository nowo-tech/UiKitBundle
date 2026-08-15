<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\DependencyInjection;

use Nowo\UiKitBundle\Enum\CssFramework;
use Symfony\Component\Asset\Package;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

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

        $translationsPath = \dirname(__DIR__).'/Resources/translations';
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
     *
     * @return void
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $fw = CssFramework::from($config['css_framework'])->normalized()->value;

        $container->setParameter('nowo_ui_kit.css_framework', $fw);
        $container->setParameter('nowo_ui_kit.icon_set', $config['icon_set']);
        $container->setParameter('nowo_ui_kit.row_actions_display', $config['row_actions_display']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');
    }
}
