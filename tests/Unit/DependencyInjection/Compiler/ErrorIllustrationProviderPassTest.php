<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\UiKitBundle\DependencyInjection\Compiler\ErrorIllustrationProviderPass;
use Nowo\UiKitBundle\Twig\ErrorPageExtension;
use Nowo\UiKitBundle\View\ErrorIllustration;
use Nowo\UiKitBundle\View\ErrorIllustrationProviderInterface;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * 2.47: a misconfigured `error_pages.illustration_provider` fails the container build, never an error page.
 */
final class ErrorIllustrationProviderPassTest extends TestCase
{
    public function testWithoutExtensionOrProviderNothingHappens(): void
    {
        $container = new ContainerBuilder();
        (new ErrorIllustrationProviderPass())->process($container);

        $container->register(ErrorIllustrationProviderPass::EXTENSION, ErrorPageExtension::class)->setArguments([[], null, null, null, null]);
        (new ErrorIllustrationProviderPass())->process($container);

        self::assertTrue($container->hasDefinition(ErrorIllustrationProviderPass::EXTENSION));
    }

    public function testProviderImplementingTheInterfacePasses(): void
    {
        $container = $this->container('app.illustrations');
        $container->setParameter('app.provider_class', PassTestProvider::class);
        $container->register('app.illustrations', '%app.provider_class%');
        $container->setAlias('app.illustrations_alias', 'app.illustrations');
        (new ErrorIllustrationProviderPass())->process($container);

        $aliased = $this->container('app.illustrations_alias');
        $aliased->register('app.illustrations', PassTestProvider::class);
        $aliased->setAlias('app.illustrations_alias', 'app.illustrations');
        (new ErrorIllustrationProviderPass())->process($aliased);

        $unknownClass = $this->container('app.factory');
        $unknownClass->register('app.factory');
        (new ErrorIllustrationProviderPass())->process($unknownClass);

        self::assertTrue($container->hasDefinition('app.illustrations'));
    }

    public function testMissingServiceFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('service "app.missing" does not exist');
        (new ErrorIllustrationProviderPass())->process($this->container('app.missing'));
    }

    public function testServiceClosureArgumentIsUnwrapped(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.wrong', stdClass::class);
        $container->register(ErrorIllustrationProviderPass::EXTENSION, ErrorPageExtension::class)
            ->setArguments([[], null, null, new ServiceClosureArgument(new Reference('app.wrong')), null]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('service "app.wrong"');
        (new ErrorIllustrationProviderPass())->process($container);
    }

    public function testWrongClassFails(): void
    {
        $container = $this->container('app.wrong');
        $container->register('app.wrong', stdClass::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement '.ErrorIllustrationProviderInterface::class);
        (new ErrorIllustrationProviderPass())->process($container);
    }

    private function container(string $provider): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(ErrorIllustrationProviderPass::EXTENSION, ErrorPageExtension::class)
            ->setArguments([[], null, null, new Reference($provider), null]);

        return $container;
    }
}

/**
 * @internal
 */
final class PassTestProvider implements ErrorIllustrationProviderInterface
{
    public function getIllustration(int $statusCode): ?ErrorIllustration
    {
        return null;
    }
}
