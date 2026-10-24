<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Listing;

use Symfony\Component\HttpFoundation\Request;

/**
 * Port of the listing preferences (columns, filters, table mode of one viewer): where the
 * {@see ListingCustomizerViewBuilder} reads them.
 *
 * The default {@see CookieListingPreferenceStore} reads a cookie the `listing-customizer` / `responsive-table`
 * peers write in the browser (no database). A host that keeps preferences server-side (per user, per project…)
 * aliases `Nowo\UiKitBundle\Listing\ListingPreferenceStoreInterface` to its own service, returns null from
 * {@see self::cookieName()}, and passes `save_url` (+ CSRF) to the `listing_customizer` partial and the responsive
 * table: the peers then send the layout there (validate it with {@see ListingLayoutMerger::normalizeLayout()}).
 *
 * Implementations must be stateless between requests (FrankenPHP worker): read from `$request` only.
 */
interface ListingPreferenceStoreInterface
{
    /**
     * Stored preferences of a listing for the viewer of `$request`, null when there are none. Values are raw: the
     * builder merges them leniently with the definition ({@see ListingLayoutMerger::merge()}).
     *
     * @return array{columns: array<mixed>|null, filters: array<mixed>|null, mode: string|null}|null
     */
    public function load(string $listingKey, Request $request): ?array;

    /**
     * Name of the cookie the browser writes for this listing, null when the store is server-side (the page then needs
     * a `save_url`).
     */
    public function cookieName(string $listingKey): ?string;
}
