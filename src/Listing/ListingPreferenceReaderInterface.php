<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Listing;

/**
 * 2.49 (REQ-ARCH-001 rule 3): framework-free port of the listing preferences (columns, filters, table mode of one
 * viewer), the successor of {@see ListingPreferenceStoreInterface}. It takes a {@see ListingContext} instead of the
 * HttpFoundation `Request`.
 *
 * The default {@see CookieListingPreferenceStore} implements both ports. A host that keeps preferences server-side
 * aliases `Nowo\UiKitBundle\Listing\ListingPreferenceReaderInterface` to its own service; the
 * {@see ListingCustomizerViewBuilder} then uses it (it wins over a re-aliased legacy store). The viewer is
 * {@see ListingContext::$viewerId} (from {@see ListingViewerResolverInterface}) or the store's own identity port.
 *
 * Implementations must be stateless between requests (FrankenPHP worker, kernel reset false): read from `$context`
 * only.
 */
interface ListingPreferenceReaderInterface
{
    /**
     * Stored preferences of a listing for the viewer of `$context`, null when there are none. Values are raw: the
     * builder merges them leniently with the definition ({@see ListingLayoutMerger::merge()}).
     *
     * @return array{columns: array<mixed>|null, filters: array<mixed>|null, mode: string|null}|null
     */
    public function load(string $listingKey, ListingContext $context): ?array;

    /**
     * Name of the cookie the browser writes for this listing, null when the store is server-side (the page then needs
     * a `save_url`).
     */
    public function cookieName(string $listingKey): ?string;
}
