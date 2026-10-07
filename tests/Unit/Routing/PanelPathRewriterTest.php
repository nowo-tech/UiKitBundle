<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Routing;

use Nowo\UiKitBundle\Routing\PanelPathRewriter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class PanelPathRewriterTest extends TestCase
{
    public function testEmptyMapIsNoOp(): void
    {
        $rewriter = new PanelPathRewriter();
        $routes = new RouteCollection();
        $routes->add('a', new Route('/admin/blog'));

        $rewriter->rewriteCollection($routes);

        self::assertTrue($rewriter->isEmpty());
        self::assertSame('/admin/blog', $routes->get('a')?->getPath());
        self::assertNull($rewriter->legacyRedirectTarget('/admin/blog'));
    }

    public function testRewritesRoutesLongestPrefixFirst(): void
    {
        $rewriter = new PanelPathRewriter([
            '/admin' => '/panel',
            '/admin/seo' => '/panel/seo',
            '/settings/seo/' => '/panel/seo/settings/',
        ]);
        $routes = new RouteCollection();
        $routes->add('seo', new Route('/admin/seo/{id}'));
        $routes->add('other', new Route('/admin/users'));
        $routes->add('exact', new Route('/admin/seo'));
        $routes->add('placeholder', new Route('/admin/seo{_format}'));
        $routes->add('settings', new Route('/settings/seo/edit'));
        $routes->add('untouched', new Route('/public'));
        $routes->add('lookalike', new Route('/administrator'));

        $rewriter->rewriteCollection($routes);

        self::assertSame('/panel/seo/{id}', $routes->get('seo')?->getPath());
        self::assertSame('/panel/users', $routes->get('other')?->getPath());
        self::assertSame('/panel/seo', $routes->get('exact')?->getPath());
        self::assertSame('/panel/seo{_format}', $routes->get('placeholder')?->getPath());
        self::assertSame('/panel/seo/settings/edit', $routes->get('settings')?->getPath());
        self::assertSame('/public', $routes->get('untouched')?->getPath());
        self::assertSame('/administrator', $routes->get('lookalike')?->getPath());
    }

    public function testMapIsSortedAndNormalized(): void
    {
        $rewriter = new PanelPathRewriter(['/a/' => '/b', '/a/long' => '/c', '' => '/x', '/same' => '/same']);

        self::assertSame(['/a/long' => '/c', '/a' => '/b'], $rewriter->getMap());
    }

    public function testLegacyRedirectTarget(): void
    {
        $rewriter = new PanelPathRewriter(['/admin/blog' => '/panel/blog', '/settings/geo' => '/panel/geo']);

        self::assertSame('/panel/blog', $rewriter->legacyRedirectTarget('/admin/blog'));
        self::assertSame('/panel/blog/42/edit', $rewriter->legacyRedirectTarget('/admin/blog/42/edit'));
        self::assertSame('/panel/geo', $rewriter->legacyRedirectTarget('/settings/geo'));
        self::assertNull($rewriter->legacyRedirectTarget('/admin/blogger'));
        self::assertNull($rewriter->legacyRedirectTarget('/panel/blog'));
        self::assertNull($rewriter->legacyRedirectTarget('/'));
    }
}
