<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\View;

use Nowo\UiKitBundle\View\PaginationRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class PaginationRequestTest extends TestCase
{
    public function testDetectsTheLoadMoreHeaderOfThePeer(): void
    {
        self::assertSame('X-Nowo-Ui-Pagination', PaginationRequest::HEADER);
        self::assertFalse(PaginationRequest::isLoadMore(Request::create('/orders')));

        $request = Request::create('/orders');
        $request->headers->set('X-Nowo-Ui-Pagination', '1');
        self::assertTrue(PaginationRequest::isLoadMore($request));

        foreach (['0', '', 'true', ' 1'] as $value) {
            $request->headers->set('X-Nowo-Ui-Pagination', $value);
            self::assertFalse(PaginationRequest::isLoadMore($request), var_export($value, true));
        }
    }
}
