<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Listing;

use Nowo\UiKitBundle\Listing\Http\ListingContextFactory;
use Nowo\UiKitBundle\Listing\ListingContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ListingContextTest extends TestCase
{
    public function testDefaultsAreEmpty(): void
    {
        $context = new ListingContext();

        self::assertSame([], $context->cookies);
        self::assertNull($context->viewerId);
        self::assertNull($context->route);
        self::assertSame([], $context->routeParameters);
        self::assertNull($context->locale);
        self::assertNull($context->cookie('any'));
    }

    public function testKeepsOnlyStringCookiesAndScalarRouteParameters(): void
    {
        $context = new ListingContext(
            ['a' => '1', 'b' => ['x'], 7 => 'int key', 'c' => ''],
            'user-7',
            'invoice_index',
            ['project' => 'p1', 'page' => 2, 'flag' => true, 'none' => null, 'list' => [1], 3 => 'x'],
            'es',
        );

        self::assertSame(['a' => '1', 'c' => ''], $context->cookies);
        self::assertSame('1', $context->cookie('a'));
        self::assertSame('', $context->cookie('c'));
        self::assertNull($context->cookie('b'));
        self::assertSame('user-7', $context->viewerId);
        self::assertSame('invoice_index', $context->route);
        self::assertSame(['project' => 'p1', 'page' => 2, 'flag' => true, 'none' => null], $context->routeParameters);
        self::assertSame('es', $context->locale);
    }

    public function testWithViewerIdReturnsACopy(): void
    {
        $context = new ListingContext(['a' => '1'], null, 'r', ['id' => 5], 'en');
        $copy = $context->withViewerId('u1');

        self::assertNotSame($context, $copy);
        self::assertNull($context->viewerId);
        self::assertSame('u1', $copy->viewerId);
        self::assertSame($context->cookies, $copy->cookies);
        self::assertSame($context->route, $copy->route);
        self::assertSame($context->routeParameters, $copy->routeParameters);
        self::assertSame($context->locale, $copy->locale);
    }

    public function testFactoryCopiesCookiesRouteAndLocale(): void
    {
        $request = new Request([], [], ['_route' => 'invoice_index', '_route_params' => ['project' => 'p1']], ['nowo_ui_listing_x' => '{"mode":"scroll"}']);
        $request->setLocale('fr');

        $context = ListingContextFactory::fromRequest($request, 'viewer');

        self::assertSame(['nowo_ui_listing_x' => '{"mode":"scroll"}'], $context->cookies);
        self::assertSame('viewer', $context->viewerId);
        self::assertSame('invoice_index', $context->route);
        self::assertSame(['project' => 'p1'], $context->routeParameters);
        self::assertSame('fr', $context->locale);
    }

    public function testFactoryWithoutRoute(): void
    {
        $context = ListingContextFactory::fromRequest(new Request([], [], ['_route' => 42, '_route_params' => 'x']));

        self::assertNull($context->route);
        self::assertSame([], $context->routeParameters);
        self::assertNull($context->viewerId);
        self::assertSame('en', $context->locale);
    }
}
