<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\View;

use Nowo\UiKitBundle\View\Pagination;
use PHPUnit\Framework\TestCase;

final class PaginationTest extends TestCase
{
    public function testNormalisesAndComputesTotals(): void
    {
        $p = new Pagination(2, 10, 45, route: 'items', query: ['q' => 'x']);
        self::assertSame([2, 10, 45, 5], [$p->page, $p->perPage, $p->total, $p->totalPages]);
        self::assertSame([11, 20, 15], [$p->from(), $p->to(), $p->to(5)]);
        self::assertTrue($p->hasPrevious());
        self::assertTrue($p->hasNext());
        self::assertSame([
            'page' => 2, 'per_page' => 10, 'total' => 45, 'total_pages' => 5, 'route' => 'items', 'query' => ['q' => 'x'],
            'route_params' => null, 'page_param' => 'page', 'per_page_param' => 'per_page',
        ], $p->toArray());

        $last = new Pagination(5, 10, 45);
        self::assertSame(45, $last->to());
        self::assertFalse($last->hasNext());

        $empty = new Pagination(0, 0, -3);
        self::assertSame([1, 1, 0, 1, 0, 0], [$empty->page, $empty->perPage, $empty->total, $empty->totalPages, $empty->from(), $empty->to()]);
        self::assertFalse($empty->hasPrevious());
    }

    public function testFromArrayAcceptsBundleShapes(): void
    {
        $legacy = Pagination::fromArray(['page' => 3, 'per_page' => 20, 'total' => 100, 'total_pages' => 5]);
        self::assertSame([3, 20, 100, 5, null, [], null], [$legacy->page, $legacy->perPage, $legacy->total, $legacy->totalPages, $legacy->route, $legacy->query, $legacy->routeParams]);

        $other = Pagination::fromArray(['current_page' => '2', 'page_size' => 10, 'total_items' => 31, 'route' => 'r', 'query' => ['a' => 1], 'route_params' => ['id' => 4]]);
        self::assertSame([2, 10, 31, 4, 'r', ['a' => 1], ['id' => 4]], [$other->page, $other->perPage, $other->total, $other->totalPages, $other->route, $other->query, $other->routeParams]);

        $camel = Pagination::fromArray(['currentPage' => 1, 'limit' => 5, 'count' => 6, 'last_page' => 2, 'route' => '', 'query' => 'x', 'route_params' => 'y']);
        self::assertSame([1, 5, 6, 2, null, [], null], [$camel->page, $camel->perPage, $camel->total, $camel->totalPages, $camel->route, $camel->query, $camel->routeParams]);

        $defaults = Pagination::fromArray(['page' => 'abc']);
        self::assertSame([1, 25, 0, 1], [$defaults->page, $defaults->perPage, $defaults->total, $defaults->totalPages]);
    }

    public function testFromPagerfanta(): void
    {
        $pager = new class {
            public function getCurrentPage(): int
            {
                return 2;
            }

            public function getMaxPerPage(): int
            {
                return 10;
            }

            public function getNbResults(): int
            {
                return 25;
            }

            public function getNbPages(): int
            {
                return 3;
            }
        };
        $p = Pagination::fromPagerfanta($pager, 'items', ['q' => 'x']);
        self::assertSame([2, 10, 25, 3, 'items', ['q' => 'x']], [$p->page, $p->perPage, $p->total, $p->totalPages, $p->route, $p->query]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not look like a Pagerfanta pager: method getCurrentPage() is missing');
        Pagination::fromPagerfanta(new \stdClass());
    }

    public function testFromKnp(): void
    {
        $knp = new class {
            public function getCurrentPageNumber(): int
            {
                return 3;
            }

            public function getItemNumberPerPage(): int
            {
                return 5;
            }

            public function getTotalItemCount(): int
            {
                return 12;
            }

            public function getRoute(): string
            {
                return 'knp_items';
            }

            /**
             * @return array<string, mixed>
             */
            public function getParams(): array
            {
                return ['page' => 3, 'q' => 'y'];
            }
        };
        $p = Pagination::fromKnp($knp);
        self::assertSame([3, 5, 12, 3, 'knp_items', ['q' => 'y']], [$p->page, $p->perPage, $p->total, $p->totalPages, $p->route, $p->query]);

        $explicit = Pagination::fromKnp($knp, 'mine', ['z' => 1]);
        self::assertSame(['mine', ['z' => 1]], [$explicit->route, $explicit->query]);

        $bare = new class {
            public function getCurrentPageNumber(): int
            {
                return 1;
            }

            public function getItemNumberPerPage(): int
            {
                return 5;
            }

            public function getTotalItemCount(): int
            {
                return 0;
            }

            public function getRoute(): ?string
            {
                return null;
            }

            public function getParams(): ?array
            {
                return null;
            }
        };
        $p = Pagination::fromKnp($bare);
        self::assertSame([null, []], [$p->route, $p->query]);

        $this->expectException(\InvalidArgumentException::class);
        Pagination::fromKnp(new \stdClass());
    }
}
