<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\DependencyInjection\Compiler;

use Nowo\UiKitBundle\View\ErrorIllustrationProviderInterface;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * 2.47: fails the container build (never an error page at runtime) when `nowo_ui_kit.error_pages.illustration_provider`
 * names a service that does not exist or whose class does not implement {@see ErrorIllustrationProviderInterface}. The
 * argument is a service closure (audit LK-L1) or, from a host that registered the extension itself, a plain reference.
 */
final class ErrorIllustrationProviderPass implements CompilerPassInterface
{
    public const EXTENSION = 'nowo_ui_kit.error_page_twig_extension';

    /** Index of the provider argument of the ErrorPageExtension definition. */
    private const ARGUMENT = 3;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::EXTENSION)) {
            return;
        }
        $arguments = $container->getDefinition(self::EXTENSION)->getArguments();
        $reference = $arguments[self::ARGUMENT] ?? null;
        if ($reference instanceof ServiceClosureArgument) {
            $reference = $reference->getValues()[0] ?? null;
        }
        if (!$reference instanceof Reference) {
            return;
        }
        $id = (string) $reference;
        if (!$container->has($id)) {
            throw new InvalidArgumentException(\sprintf('nowo_ui_kit.error_pages.illustration_provider: service "%s" does not exist.', $id));
        }
        $class = $container->getParameterBag()->resolveValue($container->findDefinition($id)->getClass());
        if (\is_string($class) && class_exists($class) && !is_subclass_of($class, ErrorIllustrationProviderInterface::class)) {
            throw new InvalidArgumentException(\sprintf('nowo_ui_kit.error_pages.illustration_provider: service "%s" (%s) must implement %s.', $id, $class, ErrorIllustrationProviderInterface::class));
        }
    }
}
