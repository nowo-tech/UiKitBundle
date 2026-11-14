<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Listing;

use Nowo\UiKitBundle\Listing\ListingContext;
use Nowo\UiKitBundle\Listing\ListingPreferenceReaderInterface;

/**
 * Host reader stub (2.49 port, server-side: no cookie).
 */
final class ServerListingReader implements ListingPreferenceReaderInterface
{
    public function load(string $listingKey, ListingContext $context): ?array
    {
        return null;
    }

    public function cookieName(string $listingKey): ?string
    {
        return null;
    }
}
