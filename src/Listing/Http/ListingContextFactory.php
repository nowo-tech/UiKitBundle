<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Listing\Http;

use Nowo\UiKitBundle\Listing\ListingContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * 2.49: maps an HttpFoundation request to the framework-free {@see ListingContext} (HTTP adapter of the listing
 * ports). Only cookies, the route name, its scalar parameters and the locale are copied.
 *
 * @internal
 */
final class ListingContextFactory
{
    public static function fromRequest(Request $request, ?string $viewerId = null): ListingContext
    {
        $route = $request->attributes->get('_route');
        $params = $request->attributes->get('_route_params');

        return new ListingContext(
            $request->cookies->all(),
            $viewerId,
            \is_string($route) ? $route : null,
            \is_array($params) ? $params : [],
            $request->getLocale(),
        );
    }
}
