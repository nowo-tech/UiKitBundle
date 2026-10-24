<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Listing;

use Nowo\UiKitBundle\Listing\CookieListingPreferenceStore;
use Nowo\UiKitBundle\Listing\ListingCustomizerViewBuilder;
use Nowo\UiKitBundle\Listing\ListingDefinitionInterface;
use Nowo\UiKitBundle\Listing\ListingField;
use Nowo\UiKitBundle\Listing\ListingPreferenceStoreInterface;
use Nowo\UiKitBundle\Listing\ListingRegistry;
use Nowo\UiKitBundle\Listing\ListingTableMode;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

final class ListingCustomizerViewBuilderTest extends TestCase
{
    private static function builder(?ListingPreferenceStoreInterface $store = null, bool $translator = true): ListingCustomizerViewBuilder
    {
        $trans = null;
        if ($translator) {
            $trans = new Translator('en');
            $trans->addLoader('array', new ArrayLoader());
            $trans->addResource('array', ['invoice.number' => 'Number'], 'en');
            $trans->addResource('array', ['filter.q' => 'Search'], 'en', 'admin');
        }

        return new ListingCustomizerViewBuilder(new ListingRegistry([new InvoiceListing()]), $store ?? new CookieListingPreferenceStore(), $trans);
    }

    private static function request(?string $cookie = null, array $query = []): Request
    {
        return new Request($query, [], [], null === $cookie ? [] : ['nowo_ui_listing_invoices_index' => $cookie]);
    }

    public function testDefaultsWithoutCookie(): void
    {
        $view = self::builder()->build(InvoiceListing::KEY, self::request());

        self::assertSame(InvoiceListing::KEY, $view->listingKey);
        self::assertFalse($view->layout->stored);
        self::assertNull($view->tableMode);
        self::assertSame('', $view->tableModeValue());
        self::assertSame('nowo_ui_listing_invoices_index', $view->cookieName);
        self::assertSame(['table_mode' => '', 'save_cookie' => 'nowo_ui_listing_invoices_index'], $view->tableOptions());
        self::assertSame('nowo-ui-listing-invoices-index', $view->dialogId());
        self::assertTrue($view->hasColumns());
        self::assertTrue($view->hasFilters());
        self::assertSame([], $view->ignoredFilters);
        self::assertSame([
            'listingKey' => InvoiceListing::KEY,
            'stored' => false,
            'mode' => null,
            'columns' => [
                ['key' => 'number', 'label' => 'Number', 'required' => true, 'visible' => true, 'merged' => false],
                ['key' => 'customer', 'label' => 'Customer', 'required' => false, 'visible' => true, 'merged' => false],
                ['key' => 'total', 'label' => 'Total', 'required' => false, 'visible' => true, 'merged' => false],
                ['key' => 'notes', 'label' => 'Notes', 'required' => false, 'visible' => false, 'merged' => false],
            ],
            'filters' => [
                ['key' => 'q', 'label' => 'Search', 'required' => false, 'visible' => true],
                ['key' => 'status', 'label' => 'Status', 'required' => false, 'visible' => true],
                ['key' => 'tag', 'label' => 'Tag', 'required' => false, 'visible' => false],
            ],
        ], $view->clientState());
    }

    public function testStoredCookieAndForRequest(): void
    {
        $cookie = '{"v":1,"columns":[{"key":"total","visible":true},{"key":"customer","visible":true,"merged":true}],"filters":[{"key":"status","visible":false}],"mode":"responsive"}';
        $request = self::request($cookie, ['status' => 'paid', 'q' => 'acme']);

        ['view' => $view, 'filteredRequest' => $filtered] = self::builder(null, false)->forRequest(InvoiceListing::KEY, $request);

        self::assertTrue($view->layout->stored);
        self::assertSame(ListingTableMode::Responsive, $view->tableMode);
        self::assertSame(['table_mode' => 'responsive', 'save_cookie' => 'nowo_ui_listing_invoices_index'], $view->tableOptions());
        self::assertSame(['total', 'customer', 'number'], $view->layout->visibleColumnKeys());
        self::assertSame(['status'], array_map(static fn (ListingField $f): string => $f->key, $view->ignoredFilters));
        self::assertSame(['q' => 'acme'], $filtered->query->all());
        self::assertSame('invoice.number', $view->clientState()['columns'][2]['label'], 'no translator: the key');
        self::assertTrue($view->clientState()['columns'][1]['merged']);
        self::assertTrue($view->clientState()['stored']);
        self::assertSame('responsive', $view->clientState()['mode']);

        $narrow = $view->withLayout($view->layout->withoutColumns(['customer']));
        self::assertSame(['total', 'number'], $narrow->layout->visibleColumnKeys());
        self::assertSame($view->clientState(), $narrow->clientState());
        self::assertSame($view->ignoredFilters, $narrow->ignoredFilters);

        $same = self::builder()->forRequest(InvoiceListing::KEY, $plain = self::request(null, ['q' => 'x']), 'order', 'direction');
        self::assertSame($plain, $same['filteredRequest']);
    }

    public function testServerSideStore(): void
    {
        $store = new class implements ListingPreferenceStoreInterface {
            public function load(string $listingKey, Request $request): ?array
            {
                return ['columns' => null, 'filters' => [['key' => 'tag', 'visible' => true]], 'mode' => 'bogus'];
            }

            public function cookieName(string $listingKey): ?string
            {
                return null;
            }
        };

        $view = self::builder($store)->build(InvoiceListing::KEY, self::request());

        self::assertNull($view->cookieName);
        self::assertNull($view->tableMode);
        self::assertSame(['table_mode' => ''], $view->tableOptions());
        self::assertTrue($view->layout->stored);
        self::assertSame(['tag', 'q', 'status'], $view->layout->visibleFilterKeys());
    }

    public function testListingWithoutFilters(): void
    {
        $definition = new class implements ListingDefinitionInterface {
            public function key(): string
            {
                return 'audit log';
            }

            public function columns(): array
            {
                return [new ListingField('when', 'When', required: true)];
            }

            public function filters(): array
            {
                return [];
            }
        };
        $view = (new ListingCustomizerViewBuilder(new ListingRegistry([$definition]), new CookieListingPreferenceStore()))->build('audit log', new Request());

        self::assertTrue($view->hasColumns());
        self::assertFalse($view->hasFilters());
        self::assertSame('nowo-ui-listing-audit-log', $view->dialogId());
        self::assertSame('nowo_ui_listing_audit_log', $view->cookieName);
    }
}
