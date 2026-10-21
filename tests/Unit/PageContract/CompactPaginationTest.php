<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\Test\FrameworkClasses;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\Pagination;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Compact pagination (2.6): View\Pagination variant `compact` → previous / "Page n of m" / next + a per-page select
 * that auto-submits through nowo-ui-autosubmit.js (no inline JS), keeping the filters and going back to page 1.
 */
final class CompactPaginationTest extends TestCase
{
    public function testCompactVariantFromTheModel(): void
    {
        $pagination = Pagination::fromTotal(95, 2, 10, route: 'items_index', query: ['q' => 'red', 'status' => ['open', 'late'], 'archived' => false, 'flag' => true, 'none' => null, 'page' => 9], variant: 'compact');
        $html = PageContractTwig::create()->render('@NowoUiKit/partials/_pagination.html.twig', ['pagination' => $pagination, 'region' => 'pagination']);

        self::assertStringContainsString('<div class="nowo-ui-pagination nowo-ui-pagination--compact d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3" data-nowo-ui-pagination="compact" data-nowo-ui-region="pagination">', $html);
        self::assertStringContainsString('<a class="nowo-ui-pagination__link page-link" href="/items_index?q=red&amp;status%5B0%5D=open&amp;status%5B1%5D=late&amp;archived=0&amp;flag=1&amp;per_page=10&amp;page=1" rel="prev" aria-label="Previous">«</a>', $html);
        self::assertStringContainsString('<span class="nowo-ui-pagination__page" aria-current="page">Page 2 of 10</span>', $html);
        self::assertStringContainsString('rel="next" aria-label="Next">»</a>', $html);
        self::assertStringContainsString('<form class="nowo-ui-pagination__per-page-form" method="get" action="/items_index" data-nowo-ui-region="per-page">', $html);
        self::assertStringContainsString('<input type="hidden" name="q" value="red"><input type="hidden" name="status[0]" value="open"><input type="hidden" name="status[1]" value="late"><input type="hidden" name="flag" value="1">', $html);
        self::assertStringNotContainsString('name="page"', $html);
        self::assertStringNotContainsString('name="archived"', $html);
        self::assertStringContainsString('<label class="nowo-ui-pagination__info" for="nowo-ui-pagination-per-page">Per page</label>', $html);
        self::assertStringContainsString('<select id="nowo-ui-pagination-per-page" name="per_page" class="nowo-ui-pagination__select form-select form-select-sm" data-nowo-ui-autosubmit>', $html);
        self::assertStringContainsString('<option value="10" selected>10</option>', $html);
        self::assertStringContainsString('<option value="100">100</option>', $html);
        self::assertStringContainsString('<noscript><button type="submit" class="nowo-ui-btn nowo-ui-btn--secondary btn btn-outline-secondary nowo-ui-btn--sm btn-sm">Apply</button></noscript>', $html);
        self::assertSame(0, preg_match('/\son[a-z]+=/i', $html));
        self::assertStringNotContainsString('pagination.', $html);
    }

    public function testEdgesHashInputAndOptions(): void
    {
        $twig = PageContractTwig::create([], [], 'tailwind');
        $first = $twig->render('@NowoUiKit/partials/_pagination_compact.html.twig', ['pagination' => ['page' => 1, 'per_page' => 25, 'total' => 60, 'total_pages' => 3], 'route' => 'r', 'per_page_choices' => [25, 50], 'id' => 'orders']);
        self::assertStringContainsString('<span class="nowo-ui-pagination__link is-disabled" aria-disabled="true" aria-label="Previous">«</span>', $first);
        self::assertStringContainsString('for="orders-per-page"', $first);
        preg_match_all('/class="([^"]*)"/', $first, $m);
        self::assertSame([], FrameworkClasses::violations(preg_split('/\s+/', implode(' ', $m[1])) ?: [], 'tailwind'));

        $last = $twig->render('@NowoUiKit/partials/_pagination.html.twig', ['pagination' => ['page' => 3, 'per_page' => 25, 'total' => 60, 'total_pages' => 3, 'variant' => 'compact'], 'route' => 'r']);
        self::assertStringContainsString('aria-disabled="true" aria-label="Next">»</span>', $last);
        self::assertStringNotContainsString('<select', $last, 'no choices: no select');

        // One page: no nav; the select only when the rows exceed the smallest choice.
        $one = new Pagination(1, 10, 8, allowedPerPage: [10, 25], variant: 'compact');
        self::assertSame('', trim($twig->render('@NowoUiKit/partials/_pagination.html.twig', ['pagination' => $one])));
        $select = new Pagination(1, 25, 20, allowedPerPage: [10, 25], variant: 'compact');
        $html = $twig->render('@NowoUiKit/partials/_pagination.html.twig', ['pagination' => $select]);
        self::assertStringNotContainsString('<nav', $html);
        self::assertStringContainsString('<select', $html);
        self::assertStringContainsString('action="/items_index"', $html, 'current route');
        self::assertStringNotContainsString('<select', $twig->render('@NowoUiKit/partials/_pagination.html.twig', ['pagination' => $select, 'per_page_selector' => false]));

        self::assertSame('', trim($twig->render('@NowoUiKit/partials/_pagination_compact.html.twig', ['pagination' => new Pagination(1, 10, 0)])));
        // `variant` on the partial wins over the model's
        self::assertStringContainsString('data-nowo-ui-pagination="compact"', $twig->render('@NowoUiKit/partials/_pagination.html.twig', ['pagination' => new Pagination(2, 10, 30), 'variant' => 'compact']));
        self::assertStringNotContainsString('compact', $twig->render('@NowoUiKit/partials/_pagination.html.twig', ['pagination' => $pagination = new Pagination(2, 10, 30, variant: 'compact'), 'variant' => 'full']));
        self::assertSame('compact', $pagination['variant']);
    }

    public function testListScaffoldRendersTheCompactVariant(): void
    {
        $request = new Request(['status' => 'open', 'page' => '2'], [], ['_route' => 'items_index', '_route_params' => []]);
        $page = new ListPage(new PageHeader('Items'), [new Column('name', 'Name')], [['name' => 'A']], pagination: Pagination::fromRequest($request, 40, 10, [10, 20], variant: 'compact'));
        $html = PageContractTwig::create(request: $request)->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);

        self::assertStringContainsString('data-nowo-ui-pagination="compact" data-nowo-ui-region="pagination"', $html);
        self::assertStringContainsString('Page 2 of 4', $html);
        self::assertStringContainsString('<input type="hidden" name="status" value="open">', $html);
    }
}
