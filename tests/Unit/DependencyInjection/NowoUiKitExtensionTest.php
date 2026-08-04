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

    public function testAlias(): void
    {
        self::assertSame(Configuration::ALIAS, (new NowoUiKitExtension())->getAlias());
    }
}
