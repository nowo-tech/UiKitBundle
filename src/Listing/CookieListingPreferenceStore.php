<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Listing;

use Symfony\Component\HttpFoundation\Request;

/**
 * Default {@see ListingPreferenceStoreInterface}: one cookie per listing, written by the browser (no database).
 *
 * Name: `nowo_ui_listing_<key>` with every character outside `[A-Za-z0-9_-]` replaced by `_` (`invoices.index` →
 * `nowo_ui_listing_invoices_index`). Value: URI-encoded JSON
 * `{"v": 1, "columns": [{"key": …, "visible": …, "merged": true}], "filters": [{"key": …, "visible": …}], "mode": "scroll"|"responsive"}`
 * — every member optional (the responsive table may store the mode alone). Written with path `/`, SameSite=Lax,
 * max-age one year, `Secure` on https.
 *
 * Parsing is lenient: a missing, oversized (> {@see self::MAX_LENGTH} bytes), non-JSON or wrongly typed value yields
 * null or drops the member, and the definition defaults apply.
 */
final readonly class CookieListingPreferenceStore implements ListingPreferenceStoreInterface
{
    public const PREFIX = 'nowo_ui_listing_';

    /** Largest cookie value read (browsers cap a cookie at about 4 KiB). */
    public const MAX_LENGTH = 4096;

    public function load(string $listingKey, Request $request): ?array
    {
        $raw = $request->cookies->all()[self::name($listingKey)] ?? null;
        if (!\is_string($raw) || '' === $raw || \strlen($raw) > self::MAX_LENGTH) {
            return null;
        }

        try {
            $data = json_decode($raw, true, 8, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
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
        return self::PREFIX.(string) preg_replace('/[^A-Za-z0-9_-]/', '_', $listingKey);
    }
}
