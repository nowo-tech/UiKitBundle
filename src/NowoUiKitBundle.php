<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle;

use Nowo\UiKitBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class NowoUiKitBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new TwigPathsPass());
    }

    public function getContainerExtension(): ExtensionInterface
    {
        return new NowoUiKitExtension();
    }
}
