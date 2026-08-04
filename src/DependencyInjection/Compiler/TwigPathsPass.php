<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * REQ-TWIG-001 / REQ-TWIG-002: register @NowoUiKitBundle; app overrides win.
 */
final class TwigPathsPass implements CompilerPassInterface
{
    public const TWIG_NAMESPACE = 'NowoUiKitBundle';

    public function process(ContainerBuilder $container): void
    {
        $loaderId = $this->getNativeLoaderServiceId($container);
        if (null === $loaderId) {
            return;
        }

        $viewsPath = \dirname(__DIR__, 2).'/Resources/views';
        $definition = $container->getDefinition($loaderId);

        if ($container->hasParameter('kernel.project_dir')) {
            $projectDirParam = $container->getParameter('kernel.project_dir');
            if (\is_string($projectDirParam)) {
                $projectDir = rtrim($projectDirParam, '/\\');
                $overridePath = $projectDir.'/templates/bundles/'.self::TWIG_NAMESPACE;
                if (is_dir($overridePath)) {
                    $definition->addMethodCall('prependPath', [$overridePath, self::TWIG_NAMESPACE]);
                }
            }
        }

        $definition->addMethodCall('addPath', [$viewsPath, self::TWIG_NAMESPACE]);
    }

    private function getNativeLoaderServiceId(ContainerBuilder $container): ?string
    {
        if ($container->hasAlias('twig.loader.native')) {
            $resolved = $this->resolveDefinitionId($container, (string) $container->getAlias('twig.loader.native'));
            if (null !== $resolved) {
                return $resolved;
            }
        }

        if ($container->hasDefinition('twig.loader.native')) {
            return 'twig.loader.native';
        }

        if ($container->hasDefinition('twig.loader.native_filesystem')) {
            return 'twig.loader.native_filesystem';
        }

        return null;
    }

    private function resolveDefinitionId(ContainerBuilder $container, string $id): ?string
    {
        for ($i = 0; $i < 32 && $container->hasAlias($id); ++$i) {
            $id = (string) $container->getAlias($id);
        }

        return $container->hasDefinition($id) ? $id : null;
    }
}
