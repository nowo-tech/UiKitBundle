<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

use Symfony\Component\HttpFoundation\Request;

/**
 * Request side of the pagination modes (2.77): whether a request is a "Load more" fetch of the `list-pagination`
 * peer, which sends {@see self::HEADER}: `1` with every next-page fetch (2.23). A host checks it to skip what the peer
 * throws away — the row count (`COUNT(*)`) of the "Showing x–y of N" line — and builds the pagination with
 * {@see Pagination::progressiveFetch()} / {@see Pagination::withoutTotal()} instead:
 *
 *     $rows = $repository->page($filters, $cursor, $perPage + 1);
 *     $pagination = PaginationRequest::isLoadMore($request)
 *         ? Pagination::progressiveFetch($page, $perPage, \count($rows) > $perPage, query: $filters)
 *         : Pagination::fromRequest($request, $repository->count($filters))->withMode('infinite');
 *
 * The header is a hint for skipping work, never an authorisation: anyone can send it, so the response must be the
 * same page the user may read without it.
 */
final class PaginationRequest
{
    /** Header the `list-pagination` peer sends with each next-page fetch. */
    public const HEADER = 'X-Nowo-Ui-Pagination';

    /**
     * Whether `$request` is a "Load more" fetch of the `list-pagination` peer (header {@see self::HEADER} set to `1`).
     */
    public static function isLoadMore(Request $request): bool
    {
        return '1' === $request->headers->get(self::HEADER);
    }
}
