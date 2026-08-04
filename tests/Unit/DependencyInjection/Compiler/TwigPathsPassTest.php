<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\UiKitBundle\DependencyInjection\Compiler\TwigPathsPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Twig\Loader\FilesystemLoader;

final class TwigPathsPassTest extends TestCase
{
    public function testSkipsWhenTwigLoaderMissing(): void
    {
        $container = new ContainerBuilder();

        (new TwigPathsPass())->process($container);

        self::assertFalse($container->hasDefinition('twig.loader.native'));
    }

    public function testAddsBundleViewsPathToNativeLoader(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        $definition = new Definition(FilesystemLoader::class);
        $container->setDefinition('twig.loader.native', $definition);

        (new TwigPathsPass())->process($container);

        $calls = $definition->getMethodCalls();
        self::assertNotEmpty($calls);
        self::assertSame('addPath', $calls[array_key_last($calls)][0]);
        self::assertSame(TwigPathsPass::TWIG_NAMESPACE, $calls[array_key_last($calls)][1][1]);
    }

    public function testPrependsOverridePathWhenDirectoryExists(): void
    {
        $projectDir = sys_get_temp_dir().'/ui_kit_twig_'.uniqid('', true);
        $override = $projectDir.'/templates/bundles/'.TwigPathsPass::TWIG_NAMESPACE;
        mkdir($override, 0777, true);

        try {
            $container = new ContainerBuilder();
            $container->setParameter('kernel.project_dir', $projectDir);

            $definition = new Definition(FilesystemLoader::class);
            $container->setDefinition('twig.loader.native_filesystem', $definition);

            (new TwigPathsPass())->process($container);

            $calls = $definition->getMethodCalls();
            self::assertSame('prependPath', $calls[0][0]);
            self::assertSame($override, $calls[0][1][0]);
        } finally {
            @rmdir($override);
            @rmdir(\dirname($override));
            @rmdir(\dirname($override, 2));
            @rmdir($projectDir);
        }
    }

    public function testResolvesTwigLoaderAlias(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        $definition = new Definition(FilesystemLoader::class);
        $container->setDefinition('twig.loader.native_filesystem', $definition);
        $container->setAlias('twig.loader.native', 'twig.loader.native_filesystem');

        (new TwigPathsPass())->process($container);

        self::assertNotEmpty($definition->getMethodCalls());
    }

    public function testResolvesNestedTwigLoaderAliases(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        $definition = new Definition(FilesystemLoader::class);
        $container->setDefinition('twig.loader.native_filesystem', $definition);
        $container->setAlias('twig.loader.native.inner', 'twig.loader.native_filesystem');
        $container->setAlias('twig.loader.native', 'twig.loader.native.inner');

        (new TwigPathsPass())->process($container);

        self::assertNotEmpty($definition->getMethodCalls());
    }
}
