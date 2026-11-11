<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Listing;

/**
 * 2.49, optional SPI: the opaque id of the current viewer, copied into {@see ListingContext::$viewerId} when the
 * {@see ListingCustomizerViewBuilder} builds the context from a request. The kit ships no implementation (it does not
 * depend on symfony/security); a host aliases `Nowo\UiKitBundle\Listing\ListingViewerResolverInterface` to an adapter
 * over its own identity port. Without one, `viewerId` is null.
 *
 * Must resolve per call (FrankenPHP worker, kernel reset false): no cached identity in a property.
 */
interface ListingViewerResolverInterface
{
    public function viewerId(): ?string;
}
