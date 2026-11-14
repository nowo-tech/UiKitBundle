<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Listing;

use JsonException;
use Nowo\UiKitBundle\Listing\Http\ListingContextFactory;
use Symfony\Component\HttpFoundation\Request;

/**
 * Default {@see ListingPreferenceReaderInterface} (and legacy {@see ListingPreferenceStoreInterface}): one cookie per listing, written by the browser (no database).
 *
 * Name: `nowo_ui_listing_<key>` with every character outside `[A-Za-z0-9_-]` replaced by `_` (`invoices.index` →
 * `nowo_ui_listing_invoices_index`). Value: URI-encoded JSON
 * `{"v": 1, "columns": [{"key": …, "visible": …, "merged": true}], "filters": [{"key": …, "visible": …}], "mode": "scroll"|"responsive"}`
 * — every member optional (the responsive table may store the mode alone). Written with path `/`, SameSite=Lax,
 * max-age one year, `Secure` on https.
 *
 * Parsing is lenient: a missing, oversized (> {@see self::MAX_LENGTH} bytes), non-JSON or wrongly typed value yields
 * null or drops the member, and the definition defaults apply.
 *
 * 2.49: {@see self::load()} takes a {@see ListingContext} (framework-free port) or, for the deprecated
 * {@see ListingPreferenceStoreInterface}, a `Request`; both read the same cookie with the same rules.
 */
final readonly class CookieListingPreferenceStore implements ListingPreferenceReaderInterface, ListingPreferenceStoreInterface
{
    public const PREFIX = 'nowo_ui_listing_';

    /** Largest cookie value read (browsers cap a cookie at about 4 KiB). */
    public const MAX_LENGTH = 4096;

    /**
     * A `Request` argument is deprecated since 2.49 (pass a {@see ListingContext}); it is removed in 3.0.
     */
    public function load(string $listingKey, ListingContext|Request $context): ?array
    {
        if ($context instanceof Request) {
            $context = ListingContextFactory::fromRequest($context);
        }
        $raw = $context->cookie(self::name($listingKey));
        if (null === $raw || '' === $raw || \strlen($raw) > self::MAX_LENGTH) {
            return null;
        }

        try {
            $data = json_decode($raw, true, 8, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (!\is_array($data)) {
            return null;
        }

        $columns = $data['columns'] ?? null;
        $filters = $data['filters'] ?? null;
        $mode = $data['mode'] ?? null;
        $result = [
            'columns' => \is_array($columns) && array_is_list($columns) ? $columns : null,
            'filters' => \is_array($filters) && array_is_list($filters) ? $filters : null,
            'mode' => \is_string($mode) && null !== ListingTableMode::tryFrom($mode) ? $mode : null,
        ];

        return [null, null, null] === array_values($result) ? null : $result;
    }

    public function cookieName(string $listingKey): string
    {
        return self::name($listingKey);
    }

    public static function name(string $listingKey): string
    {
        return self::PREFIX.preg_replace('/[^A-Za-z0-9_-]/', '_', $listingKey);
    }
}
