<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\DependencyInjection;

use Nowo\Brand\Contract\Brand;
use Nowo\Brand\Contract\BrandColors;
use Nowo\Brand\Contract\BrandProviderInterface;
use Nowo\UiKitBundle\Appearance\AppearanceProviderInterface;
use Nowo\UiKitBundle\Appearance\AppearanceView;
use Nowo\UiKitBundle\Appearance\BrandAppearanceProvider;
use Nowo\UiKitBundle\Appearance\ConfigAppearanceProvider;
use Nowo\UiKitBundle\DependencyInjection\Compiler\BrandAppearanceProviderPass;
use Nowo\UiKitBundle\DependencyInjection\Configuration;
use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Nowo\UiKitBundle\Tests\Unit\Appearance\BrandAppearanceTest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * 2.40 (spec 220 S1): `provider_is_default`, the `brand` / `head_links` options, the brand provider candidate and its
 * compiler pass (aliased only with a BrandProviderInterface service and an untouched provider alias).
 */
final class BrandAppearanceWiringTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[]]);
        self::assertSame('auto', $config['appearance']['brand']);
        self::assertFalse($config['appearance']['head_links']);

        $this->expectException(InvalidConfigurationException::class);
        (new Processor())->processConfiguration(new Configuration(), [['appearance' => ['brand' => 'on']]]);
    }

    public function testProviderIsDefaultParameter(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([[]], $container);
        self::assertTrue($container->getParameter('nowo_ui_kit.appearance.provider_is_default'));
        self::assertFalse($container->hasDefinition(BrandAppearanceProviderPass::CANDIDATE), 'appearance disabled: no candidate');
        self::assertTrue($container->hasDefinition('nowo_ui_kit.appearance.view'));

        $custom = new ContainerBuilder();
        (new NowoUiKitExtension())->load([['appearance' => ['enabled' => true, 'provider' => 'app.provider']]], $custom);
        self::assertFalse($custom->getParameter('nowo_ui_kit.appearance.provider_is_default'));
        self::assertFalse($custom->hasDefinition(BrandAppearanceProviderPass::CANDIDATE), 'host provider: no candidate');

        $off = new ContainerBuilder();
        (new NowoUiKitExtension())->load([['appearance' => ['enabled' => true, 'brand' => 'off']]], $off);
        self::assertTrue($off->getParameter('nowo_ui_kit.appearance.provider_is_default'));
        self::assertFalse($off->hasDefinition(BrandAppearanceProviderPass::CANDIDATE), 'brand: off');
    }

    public function testWithoutABrandServiceTheContainerKeepsTheConfigProvider(): void
    {
        $container = $this->compile(['enabled' => true, 'defaults' => ['brand_name' => 'Config']]);
        self::assertFalse($container->hasDefinition(BrandAppearanceProviderPass::CANDIDATE));
        self::assertInstanceOf(ConfigAppearanceProvider::class, $container->get('nowo_ui_kit.appearance.provider'));
        $view = $container->get('nowo_ui_kit.appearance.view');
        self::assertInstanceOf(AppearanceView::class, $view);
        self::assertSame('Config', $view->current()?->brandName);
        self::assertNull($view->logos());
    }

    public function testABrandServiceDrivesTheDefaultProvider(): void
    {
        $container = $this->compile(['enabled' => true, 'head_links' => true], static function (ContainerBuilder $c): void {
            $c->register('app.brand', FixedBrandProvider::class);
            $c->setAlias(BrandProviderInterface::class, 'app.brand');
        });
        $provider = $container->get('nowo_ui_kit.appearance.provider');
        self::assertInstanceOf(BrandAppearanceProvider::class, $provider);
        self::assertSame('Acme', $provider->getAppearance()->brandName);
        $view = $container->get('nowo_ui_kit.appearance.view');
        self::assertInstanceOf(AppearanceView::class, $view);
        self::assertTrue($view->headLinks());
        self::assertSame('Acme', $view->current()?->brandName);
    }

    public function testABrandServiceDefinitionUnderTheInterfaceIdAlsoCounts(): void
    {
        $container = $this->compile(['enabled' => true], static function (ContainerBuilder $c): void {
            $c->register(BrandProviderInterface::class, FixedBrandProvider::class);
        });
        self::assertInstanceOf(BrandAppearanceProvider::class, $container->get('nowo_ui_kit.appearance.provider'));
    }

    public function testABridgeThatReAliasedTheProviderWinsInEitherOrder(): void
    {
        $bridge = static function (ContainerBuilder $c): void {
            $c->register('app.brand', FixedBrandProvider::class);
            $c->setAlias(BrandProviderInterface::class, 'app.brand');
            $c->register('app.bridge', ConfigAppearanceProvider::class)->setArguments([new Reference('nowo_ui_kit.appearance.preset_registry'), ['brand_name' => 'Bridge']]);
        };
        $before = $this->compile(['enabled' => true], $bridge, new BridgePass(), true);
        self::assertSame('Bridge', $this->appearanceName($before));

        $after = $this->compile(['enabled' => true], $bridge, new BridgePass(), false);
        self::assertSame('Bridge', $this->appearanceName($after), 'UiKit already aliased its candidate; the bridge pass re-aliases on provider_is_default');
    }

    public function testBundleRegistersThePass(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitBundle())->build($container);
        $passes = array_map(get_class(...), $container->getCompilerPassConfig()->getBeforeOptimizationPasses());
        self::assertContains(BrandAppearanceProviderPass::class, $passes);
    }

    private function appearanceName(ContainerBuilder $container): string
    {
        $provider = $container->get('nowo_ui_kit.appearance.provider');
        self::assertInstanceOf(AppearanceProviderInterface::class, $provider);

        return $provider->getAppearance()->brandName;
    }

    /**
     * @param array<string, mixed> $appearance
     */
    private function compile(array $appearance, ?callable $host = null, ?CompilerPassInterface $extraPass = null, bool $extraFirst = true): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->register('request_stack', RequestStack::class);
        (new NowoUiKitExtension())->load([['appearance' => $appearance]], $container);
        if (null !== $host) {
            $host($container);
        }
        if ($extraPass instanceof CompilerPassInterface && $extraFirst) {
            $container->addCompilerPass($extraPass);
        }
        $container->addCompilerPass(new BrandAppearanceProviderPass());
        if ($extraPass instanceof CompilerPassInterface && !$extraFirst) {
            $container->addCompilerPass($extraPass);
        }
        $container->getAlias('nowo_ui_kit.appearance.provider')->setPublic(true);
        $container->getDefinition('nowo_ui_kit.appearance.view')->setPublic(true);
        $container->compile();

        return $container;
    }
}

/**
 * What InstanceSettings' bridge pass does (spec 220 §2.2): re-alias the provider when provider_is_default.
 */
final class BridgePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (true === $container->getParameter('nowo_ui_kit.appearance.provider_is_default')) {
            $container->setAlias('nowo_ui_kit.appearance.provider', 'app.bridge')->setPublic(true);
        }
    }
}

final class FixedBrandProvider implements BrandProviderInterface
{
    public function getBrand(): Brand
    {
        return new Brand('Acme', BrandColors::fromArray(BrandAppearanceTest::BEACON_LIGHT), BrandColors::fromArray(BrandAppearanceTest::BEACON_DARK));
    }
}
