<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Listing\Http;

use Nowo\UiKitBundle\Listing\CookieListingPreferenceStore;
use Nowo\UiKitBundle\Listing\ListingContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * 2.49: maps an HttpFoundation request to the framework-free {@see ListingContext} (HTTP adapter of the listing
 * ports). Only the listing cookies (`nowo_ui_listing_*`, {@see CookieListingPreferenceStore::PREFIX}), the route name,
 * its scalar parameters and the locale are copied: session, remember-me and other host cookies never reach a reader
 * (audit MR-U-L1).
 *
 * @internal
 */
final class ListingContextFactory
{
    public static function fromRequest(Request $request, ?string $viewerId = null): ListingContext
    {
        $route = $request->attributes->get('_route');
        $params = $request->attributes->get('_route_params');

        $cookies = [];
        foreach ($request->cookies->all() as $name => $value) {
            if (str_starts_with((string) $name, CookieListingPreferenceStore::PREFIX)) {
                $cookies[$name] = $value;
            }
        }

        return new ListingContext(
            $cookies,
            $viewerId,
            \is_string($route) ? $route : null,
            \is_array($params) ? $params : [],
            $request->getLocale(),
        );
    }
}
