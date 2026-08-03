<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit;

use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use Nowo\UiKitBundle\NowoUiKitBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class NowoUiKitBundleTest extends TestCase
{
    public function testBuildRegistersTwigPathsPass(): void
    {
        $bundle = new NowoUiKitBundle();
        $container = new ContainerBuilder();

        $bundle->build($container);

        $passes = $container->getCompilerPassConfig()->getPasses();
        $found = false;
        foreach ($passes as $pass) {
            if ($pass instanceof \Nowo\UiKitBundle\DependencyInjection\Compiler\TwigPathsPass) {
                $found = true;
                break;
            }
        }

        self::assertTrue($found);
    }

    public function testGetContainerExtensionReturnsNowoUiKitExtension(): void
    {
        $bundle = new NowoUiKitBundle();

        self::assertInstanceOf(NowoUiKitExtension::class, $bundle->getContainerExtension());
    }
}
