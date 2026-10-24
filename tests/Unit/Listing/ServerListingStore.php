<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Listing;

use Nowo\UiKitBundle\Listing\ListingPreferenceStoreInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Host store stub (server-side: no cookie).
 */
final class ServerListingStore implements ListingPreferenceStoreInterface
{
    public function load(string $listingKey, Request $request): ?array
    {
        return null;
    }

    public function cookieName(string $listingKey): ?string
    {
        return null;
    }
}
