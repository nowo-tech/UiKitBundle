<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\DependencyInjection;

use Nowo\UiKitBundle\Appearance\AppearancePreset;
use Nowo\UiKitBundle\Appearance\AppearancePresetProviderInterface;
use Nowo\UiKitBundle\Appearance\AppearancePresetRegistry;
use Nowo\UiKitBundle\Appearance\AppearanceProviderInterface;
use Nowo\UiKitBundle\DependencyInjection\Configuration;
use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use Nowo\UiKitBundle\Tests\Unit\Appearance\AppearanceCssEmitterTest;
use Nowo\UiKitBundle\Twig\AppearanceExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class AppearanceConfigurationTest extends TestCase
{
    public function testDisabledByDefault(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[]]);
        self::assertFalse($config['appearance']['enabled']);
        self::assertSame('slate', $config['appearance']['defaults']['preset']);
        self::assertSame(['ocean', 'slate', 'sandstone', 'midnight', 'obsidian', 'aurora', 'ember'], $config['appearance']['builtin_presets']);
        self::assertSame(':root, [data-theme="light"]', $config['appearance']['selectors']['light']);

        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([[]], $container);
        self::assertFalse($container->getParameter('nowo_ui_kit.appearance.enabled'));
        self::assertFalse($container->hasDefinition('nowo_ui_kit.appearance.twig_extension'));
    }

    public function testRegistersServicesAndReproducesALegacyHostConfig(): void
    {
        $container = $this->compile([
            'enabled' => true,
            'defaults' => ['brand_name' => 'Brand', 'preset' => 'brand', 'preset_dark' => 'brand', 'corner_style' => 'soft', 'border_strength' => 'medium'],
            'presets' => [
                'brand' => [
                    'mode' => 'light',
                    'light' => ['accent' => '#1f6f54', 'accent_deep' => '#134736', 'danger' => '#b42318', 'warn' => '#b54708', 'paper' => '#f3f6f4', 'ink' => '#0f1c18', 'surface' => '#ffffff'],
                    'dark' => ['accent' => '#4aad7f', 'accent_deep' => '#6bc49a', 'danger' => '#f97066', 'warn' => '#fdb022', 'paper' => '#0c1210', 'ink' => '#e6eee9', 'surface' => '#151c19'],
                ],
            ],
            'kit_tokens' => false,
            'token_aliases' => AppearanceCssEmitterTest::LEGACY_ALIASES,
            'shadow_rgb_fallback' => '15 28 24',
        ]);

        $registry = $container->get('nowo_ui_kit.appearance.preset_registry');
        self::assertInstanceOf(AppearancePresetRegistry::class, $registry);
        self::assertSame(['brand', 'tagged', 'ocean', 'slate', 'sandstone', 'midnight', 'obsidian', 'aurora', 'ember'], $registry->ids());

        $provider = $container->get('nowo_ui_kit.appearance.provider');
        self::assertInstanceOf(AppearanceProviderInterface::class, $provider);
        $appearance = $provider->getAppearance();
        self::assertSame('Brand', $appearance->brandName);
        self::assertSame('#1f6f54', $appearance->light->accent);
        self::assertSame('#4aad7f', $appearance->dark->accent);

        $twig = new Environment(new ArrayLoader(['t' => '{{ nowo_ui_appearance_css() }}']));
        $extension = $container->get('nowo_ui_kit.appearance.twig_extension');
        self::assertInstanceOf(AppearanceExtension::class, $extension);
        $twig->addExtension($extension);
        $css = $twig->render('t');
        self::assertStringContainsString("  --beacon-radius-card: 0.75rem;\n", $css);
        self::assertStringContainsString("  --color-sand: color-mix(in srgb, #0f1c18 14%, #f3f6f4);\n", $css);
        self::assertStringContainsString("  --beacon-shadow: 15 28 24;\n", $css);
        self::assertStringNotContainsString('--nowo-ui-', $css);

        self::assertTrue($container->has('nowo_ui_kit.appearance.form_type'));
    }

    public function testCustomProviderIsAliased(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([['appearance' => ['enabled' => true, 'provider' => 'app.appearance_provider']]], $container);
        self::assertSame('app.appearance_provider', (string) $container->getAlias('nowo_ui_kit.appearance.provider'));
        self::assertSame('nowo_ui_kit.appearance.provider', (string) $container->getAlias(AppearanceProviderInterface::class));
        self::assertArrayHasKey(AppearancePresetProviderInterface::class, $container->getAutoconfiguredInstanceof());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidConfigs(): iterable
    {
        yield 'colour' => [['presets' => ['x' => ['light' => ['accent' => 'red;}']]]], 'Invalid CSS colour'];
        yield 'preset id' => [['presets' => ['Bad Id' => ['mode' => 'light']]], 'Preset ids must be'];
        yield 'builtin' => [['builtin_presets' => ['ocean', 'nope']], 'Unknown built-in preset'];
        yield 'alias token' => [['token_aliases' => ['--nowo-ui-nope' => ['--x']]], 'unknown kit token'];
        yield 'alias name' => [['token_aliases' => ['--nowo-ui-primary' => ['x;y']]], 'is not a custom property name'];
        yield 'alias type' => [['token_aliases' => ['--nowo-ui-primary' => [['x']]]], 'Invalid'];
        yield 'selector' => [['selectors' => ['dark' => '.x{}']], 'Appearance selectors'];
        yield 'shadow' => [['shadow_rgb_fallback' => '1,2,3'], 'shadow_rgb_fallback'];
        yield 'corner' => [['defaults' => ['corner_style' => 'square']], 'corner_style'];
    }

    /**
     * @param array<string, mixed> $appearance
     */
    #[DataProvider('invalidConfigs')]
    public function testRejectsInvalidConfig(array $appearance, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);
        (new Processor())->processConfiguration(new Configuration(), [['appearance' => $appearance]]);
    }

    public function testSingleAliasStringIsAccepted(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [['appearance' => ['token_aliases' => ['--nowo-ui-primary' => '--brand']]]]);
        self::assertSame(['--nowo-ui-primary' => ['--brand']], $config['appearance']['token_aliases']);
    }

    /**
     * @param array<string, mixed> $appearance
     */
    private function compile(array $appearance): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->register('request_stack', RequestStack::class);
        (new NowoUiKitExtension())->load([['appearance' => $appearance]], $container);
        $container->register('test.tagged_presets', TaggedPresetProvider::class)->setAutoconfigured(true);
        foreach (['nowo_ui_kit.appearance.preset_registry', 'nowo_ui_kit.appearance.twig_extension', 'nowo_ui_kit.appearance.form_type'] as $id) {
            $container->getDefinition($id)->setPublic(true);
        }
        $container->getAlias('nowo_ui_kit.appearance.provider')->setPublic(true);
        $container->compile();

        return $container;
    }
}

final class TaggedPresetProvider implements AppearancePresetProviderInterface
{
    public function getPresets(): iterable
    {
        yield AppearancePreset::fromArray('tagged', ['mode' => 'dark']);
    }
}
