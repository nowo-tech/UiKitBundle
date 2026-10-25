<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Listing;

use Nowo\UiKitBundle\Listing\CookieListingPreferenceStore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class CookieListingPreferenceStoreTest extends TestCase
{
    private function request(mixed $value, string $name = 'nowo_ui_listing_invoices_index'): Request
    {
        return new Request([], [], [], [$name => $value]);
    }

    public function testCookieName(): void
    {
        $store = new CookieListingPreferenceStore();

        self::assertSame('nowo_ui_listing_invoices_index', $store->cookieName('invoices.index'));
        self::assertSame('nowo_ui_listing_a-b_c_d_', CookieListingPreferenceStore::name('a-b_c d;'));
    }

    public function testValidCookie(): void
    {
        $json = json_encode(['v' => 1, 'columns' => [['key' => 'total', 'visible' => true]], 'filters' => [], 'mode' => 'responsive'], \JSON_THROW_ON_ERROR);

        self::assertSame(
            ['columns' => [['key' => 'total', 'visible' => true]], 'filters' => [], 'mode' => 'responsive'],
            (new CookieListingPreferenceStore())->load('invoices.index', $this->request($json)),
        );
    }

    public function testPartialCookieDropsWrongMembers(): void
    {
        $store = new CookieListingPreferenceStore();

        self::assertSame(['columns' => null, 'filters' => null, 'mode' => 'scroll'], $store->load('invoices.index', $this->request('{"mode":"scroll","columns":{"a":1},"filters":"x"}')));
        self::assertSame(['columns' => [1], 'filters' => null, 'mode' => null], $store->load('invoices.index', $this->request('{"columns":[1],"mode":"cards"}')));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function unusable(): iterable
    {
        yield 'missing' => [null];
        yield 'empty' => [''];
        yield 'array value' => [['x']];
        yield 'not json' => ['{columns'];
        yield 'json scalar' => ['42'];
        yield 'too deep' => [str_repeat('[', 20).str_repeat(']', 20)];
        yield 'nothing usable' => ['{"v":1,"mode":7}'];
        yield 'oversized' => ['{"columns":["'.str_repeat('x', CookieListingPreferenceStore::MAX_LENGTH).'"]}'];
    }

    #[DataProvider('unusable')]
    public function testUnusableCookieYieldsNull(mixed $value): void
    {
        self::assertNull((new CookieListingPreferenceStore())->load('invoices.index', $this->request($value)));
    }

    public function testOtherListingCookieIsIgnored(): void
    {
        self::assertNull((new CookieListingPreferenceStore())->load('invoices.index', $this->request('{"mode":"scroll"}', 'nowo_ui_listing_other')));
    }
}
