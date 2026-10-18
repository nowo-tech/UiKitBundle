<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\View;

use Nowo\UiKitBundle\View\Pagination;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

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

    /**
     * Same results as the apps' PagePagination::fromTotal() (symfony-beacon and six other apps) with the defaults.
     */
    public function testFromTotalClampsLikePagePagination(): void
    {
        $p = Pagination::fromTotal(55, 99, 7);
        self::assertSame([3, 25, 55, 3, 50, 25], [$p->page, $p->perPage, $p->total, $p->totalPages, $p->offset(), $p->limit()]);
        self::assertSame(50, $p['offset']);
        self::assertSame(25, $p['limit']);
        self::assertSame(25, $p['per_page']);
        self::assertSame([10, 25, 50, 100], $p->allowedPerPage);

        $empty = Pagination::fromTotal(-4, 0, 25);
        self::assertSame([1, 25, 0, 1, 0], [$empty->page, $empty->perPage, $empty->total, $empty->totalPages, $empty->offset()]);

        $second = Pagination::fromTotal(25, 2, 10, route: 'items', query: ['q' => 'x'], routeParams: ['id' => 1], pageParam: 'p', perPageParam: 'n');
        self::assertSame([2, 10, 3, 10], [$second->page, $second->perPage, $second->totalPages, $second->offset()]);
        self::assertSame(['items', ['q' => 'x'], ['id' => 1], 'p', 'n'], [$second->route, $second->query, $second->routeParams, $second->pageParam, $second->perPageParam]);
        self::assertSame([0, 10], [(new Pagination(1, 10, 5))->offset(), (new Pagination(1, 10, 5))->limit()]);
        self::assertNull((new Pagination(1, 10, 5))[3]);
    }

    public function testFromTotalFallsBackToTheDefaultOrTheFirstAllowedValue(): void
    {
        self::assertSame(40, Pagination::fromTotal(100, 1, 7, [20, 40], 40)->perPage);
        self::assertSame(20, Pagination::fromTotal(100, 1, 7, [20, 40])->perPage, 'default 25 not allowed: first allowed value');
        self::assertSame([20, 40], Pagination::fromTotal(100, 1, 40, [20, 20, 40, 0, -5])->allowedPerPage);
        self::assertSame(40, Pagination::fromTotal(100, 1, 40, [20, 40])->perPage);

        $this->expectException(\InvalidArgumentException::class);
        Pagination::fromTotal(10, 1, 10, [0]);
    }

    public function testFromRequestReadsTheQueryAndKeepsTheOtherParameters(): void
    {
        $request = Request::create('/list', 'GET', ['page' => '2', 'per_page' => '10', 'q' => 'abc']);
        $p = Pagination::fromRequest($request, 25);
        self::assertSame([2, 10, 10, 3, ['q' => 'abc']], [$p->page, $p->perPage, $p->offset(), $p->totalPages, $p->query]);

        $custom = Pagination::fromRequest(Request::create('/list', 'GET', ['p' => '9', 'n' => '7']), 30, 10, [10, 20], 'items', 'p', 'n');
        self::assertSame([3, 10, 'items', 'p', 'n', []], [$custom->page, $custom->perPage, $custom->route, $custom->pageParam, $custom->perPageParam, $custom->query]);

        $defaults = Pagination::fromRequest(Request::create('/list'), 0);
        self::assertSame([1, 25, 0], [$defaults->page, $defaults->perPage, $defaults->offset()]);
    }
}
