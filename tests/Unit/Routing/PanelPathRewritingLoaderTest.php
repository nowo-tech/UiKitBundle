<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Routing;

use Nowo\UiKitBundle\Routing\PanelPathRewriter;
use Nowo\UiKitBundle\Routing\PanelPathRewritingLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class PanelPathRewritingLoaderTest extends TestCase
{
    public function testLoadRewritesRouteCollection(): void
    {
        $routes = new RouteCollection();
        $routes->add('blog', new Route('/admin/blog/{id}'));

        $inner = $this->createMock(LoaderInterface::class);
        $inner->expects(self::once())->method('load')->with('res', 'attribute')->willReturn($routes);

        $loader = new PanelPathRewritingLoader($inner, new PanelPathRewriter(['/admin/blog' => '/panel/blog']));

        self::assertSame($routes, $loader->load('res', 'attribute'));
        self::assertSame('/panel/blog/{id}', $routes->get('blog')?->getPath());
    }

    public function testLoadPassesThroughNonCollections(): void
    {
        $inner = $this->createMock(LoaderInterface::class);
        $inner->method('load')->willReturn('not-a-collection');

        $loader = new PanelPathRewritingLoader($inner, new PanelPathRewriter(['/a' => '/b']));

        self::assertSame('not-a-collection', $loader->load('x'));
    }

    public function testDelegatesSupportsAndResolver(): void
    {
        $resolver = $this->createMock(LoaderResolverInterface::class);
        $inner = $this->createMock(LoaderInterface::class);
        $inner->method('supports')->with('r', 't')->willReturn(true);
        $inner->method('getResolver')->willReturn($resolver);
        $inner->expects(self::once())->method('setResolver')->with($resolver);

        $loader = new PanelPathRewritingLoader($inner, new PanelPathRewriter());

        self::assertTrue($loader->supports('r', 't'));
        self::assertSame($resolver, $loader->getResolver());
        $loader->setResolver($resolver);
    }
}
