<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\DependencyInjection\Compiler;

use Nowo\UiKitBundle\Dialog\RemoteDialogPageResolverInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * 2.74: fails the container build (never a silent "no redirect" at runtime) when a
 * `nowo_ui_kit.remote_dialog.direct_get.routes.<route>.page_resolver` names a service that does not exist or whose
 * class does not implement {@see RemoteDialogPageResolverInterface}. The extension lists the ids in a hidden
 * parameter, removed here.
 */
final class RemoteDialogPageResolverPass implements CompilerPassInterface
{
    public const PARAMETER = '.nowo_ui_kit.remote_dialog.page_resolvers';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(self::PARAMETER)) {
            return;
        }
        $ids = $container->getParameter(self::PARAMETER);
        $container->getParameterBag()->remove(self::PARAMETER);
        foreach (\is_array($ids) ? $ids : [] as $id) {
            $id = (string) $id;
            if (!$container->has($id)) {
                throw new InvalidArgumentException(\sprintf('nowo_ui_kit.remote_dialog.direct_get page_resolver: service "%s" does not exist.', $id));
            }
            $target = $id;
            while ($container->hasAlias($target)) {
                $target = (string) $container->getAlias($target);
            }
            $class = $container->getParameterBag()->resolveValue($container->getDefinition($target)->getClass() ?? $target);
            if (!\is_string($class) || !is_subclass_of($class, RemoteDialogPageResolverInterface::class)) {
                throw new InvalidArgumentException(\sprintf('nowo_ui_kit.remote_dialog.direct_get page_resolver: service "%s" (%s) must implement %s.', $id, \is_string($class) ? $class : get_debug_type($class), RemoteDialogPageResolverInterface::class));
            }
        }
    }
}
