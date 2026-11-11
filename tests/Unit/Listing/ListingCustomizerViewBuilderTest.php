<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Listing;

use LogicException;
use Nowo\UiKitBundle\Listing\CookieListingPreferenceStore;
use Nowo\UiKitBundle\Listing\ListingContext;
use Nowo\UiKitBundle\Listing\ListingCustomizerViewBuilder;
use Nowo\UiKitBundle\Listing\ListingDefinitionInterface;
use Nowo\UiKitBundle\Listing\ListingField;
use Nowo\UiKitBundle\Listing\ListingPreferenceReaderInterface;
use Nowo\UiKitBundle\Listing\ListingPreferenceStoreInterface;
use Nowo\UiKitBundle\Listing\ListingRegistry;
use Nowo\UiKitBundle\Listing\ListingTableMode;
use Nowo\UiKitBundle\Listing\ListingViewerResolverInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

final class ListingCustomizerViewBuilderTest extends TestCase
{
    private function builder(?ListingPreferenceStoreInterface $store = null, bool $translator = true): ListingCustomizerViewBuilder
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

    private function request(?string $cookie = null, array $query = []): Request
    {
        return new Request($query, [], [], null === $cookie ? [] : ['nowo_ui_listing_invoices_index' => $cookie]);
    }

    public function testDefaultsWithoutCookie(): void
    {
        $view = $this->builder()->build(InvoiceListing::KEY, $this->request());

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
        $request = $this->request($cookie, ['status' => 'paid', 'q' => 'acme']);

        ['view' => $view, 'filteredRequest' => $filtered] = $this->builder(null, false)->forRequest(InvoiceListing::KEY, $request);

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

        $same = $this->builder()->forRequest(InvoiceListing::KEY, $plain = $this->request(null, ['q' => 'x']), 'order', 'direction');
        self::assertSame($plain, $same['filteredRequest']);
    }

    public function testServerSideStore(): void
    {
        $store = new class implements ListingPreferenceStoreInterface {
            public function load(string $listingKey, Request $request): array
            {
                return ['columns' => null, 'filters' => [['key' => 'tag', 'visible' => true]], 'mode' => 'bogus'];
            }

            public function cookieName(string $listingKey): ?string
            {
                return null;
            }
        };

        $deprecations = [];
        set_error_handler(static function (int $type, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED);
        try {
            $builder = $this->builder($store);
        } finally {
            restore_error_handler();
        }
        $view = $builder->build(InvoiceListing::KEY, $this->request());

        self::assertCount(1, $deprecations, 'a legacy-only store still works, with a deprecation notice');
        self::assertStringContainsString('Since nowo-tech/ui-kit-bundle 2.49', $deprecations[0]);
        self::assertStringContainsString(ListingPreferenceReaderInterface::class, $deprecations[0]);
        self::assertNull($view->cookieName);
        self::assertNull($view->tableMode);
        self::assertSame(['table_mode' => ''], $view->tableOptions());
        self::assertTrue($view->layout->stored);
        self::assertSame(['tag', 'q', 'status'], $view->layout->visibleFilterKeys());

        $this->expectException(LogicException::class);
        $builder->buildForContext(InvoiceListing::KEY, new ListingContext());
    }

    /**
     * 2.49: the same stored layout through the reader port (with a viewer) gives the same view as through the legacy
     * Request port.
     */
    public function testReaderPortGivesTheSameViewAsTheLegacyPort(): void
    {
        $stored = ['columns' => [['key' => 'total', 'visible' => true, 'merged' => true], ['key' => 'customer', 'visible' => false]], 'filters' => [['key' => 'tag', 'visible' => true]], 'mode' => 'responsive'];
        $reader = new class($stored) implements ListingPreferenceReaderInterface {
            /** @var list<ListingContext> */
            public array $contexts = [];

            /**
             * @param array{columns: array<mixed>|null, filters: array<mixed>|null, mode: string|null} $stored
             */
            public function __construct(private readonly array $stored)
            {
            }

            public function load(string $listingKey, ListingContext $context): array
            {
                $this->contexts[] = $context;

                return $this->stored;
            }

            public function cookieName(string $listingKey): ?string
            {
                return null;
            }
        };
        $legacy = new class($stored) implements ListingPreferenceStoreInterface {
            /**
             * @param array{columns: array<mixed>|null, filters: array<mixed>|null, mode: string|null} $stored
             */
            public function __construct(private readonly array $stored)
            {
            }

            public function load(string $listingKey, Request $request): array
            {
                return $this->stored;
            }

            public function cookieName(string $listingKey): ?string
            {
                return null;
            }
        };
        $viewer = new class implements ListingViewerResolverInterface {
            public function viewerId(): string
            {
                return 'user-42';
            }
        };
        $registry = new ListingRegistry([new InvoiceListing()]);

        // the host reader wins over the default cookie store, even when the legacy alias is still the cookie store
        $builder = new ListingCustomizerViewBuilder($registry, new CookieListingPreferenceStore(), null, $reader, $viewer);
        $request = new Request([], [], ['_route' => 'invoice_index', '_route_params' => ['project' => 'p1']], ['nowo_ui_listing_invoices_index' => '{"mode":"scroll"}']);
        $view = $builder->build(InvoiceListing::KEY, $request);
        set_error_handler(static fn (): bool => true, \E_USER_DEPRECATED);
        try {
            $legacyView = (new ListingCustomizerViewBuilder($registry, $legacy))->build(InvoiceListing::KEY, $request);
        } finally {
            restore_error_handler();
        }

        self::assertEquals($legacyView, $view);
        self::assertSame(ListingTableMode::Responsive, $view->tableMode);
        self::assertCount(1, $reader->contexts);
        self::assertSame('user-42', $reader->contexts[0]->viewerId);
        self::assertSame('invoice_index', $reader->contexts[0]->route);
        self::assertSame(['project' => 'p1'], $reader->contexts[0]->routeParameters);

        $contextView = $builder->buildForContext(InvoiceListing::KEY, new ListingContext([], 'other'));
        self::assertEquals($view, $contextView);
        self::assertSame('other', $reader->contexts[1]->viewerId, 'buildForContext keeps the host context as given');
    }

    public function testBuildForContextWithTheCookieStoreMatchesBuild(): void
    {
        $cookie = '{"v":1,"columns":[{"key":"notes","visible":true}],"filters":[{"key":"q","visible":false}],"mode":"scroll"}';
        $builder = $this->builder();

        self::assertEquals(
            $builder->build(InvoiceListing::KEY, $this->request($cookie)),
            $builder->buildForContext(InvoiceListing::KEY, new ListingContext(['nowo_ui_listing_invoices_index' => $cookie])),
        );
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

    /**
     * @return iterable<string, array{bool, bool, bool, int}>
     */
    public static function viewerWarningCases(): iterable
    {
        // [debug, server-side reader, viewer resolver, expected warnings]
        yield 'debug, server-side, no resolver' => [true, true, false, 1];
        yield 'debug, server-side, resolver' => [true, true, true, 0];
        yield 'no debug' => [false, true, false, 0];
        yield 'cookie store' => [true, false, false, 0];
    }

    /**
     * Audit MR-U-L2: in debug, a server-side reader without a viewer resolver is reported.
     */
    #[DataProvider('viewerWarningCases')]
    public function testWarnsAboutAServerSideReaderWithoutViewer(bool $debug, bool $serverSide, bool $resolver, int $expected): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $warnings = [];

            /**
             * @param array<mixed> $context
             */
            public function log($level, string|Stringable $message, array $context = []): void
            {
                if ('warning' === $level) {
                    $this->warnings[] = (string) $message;
                }
            }
        };
        $viewer = $resolver ? new class implements ListingViewerResolverInterface {
            public function viewerId(): string
            {
                return 'u1';
            }
        } : null;
        $reader = $serverSide ? new ServerListingReader() : null;
        $builder = new ListingCustomizerViewBuilder(new ListingRegistry([new InvoiceListing()]), new CookieListingPreferenceStore(), null, $reader, $viewer, $logger, $debug);

        $builder->build(InvoiceListing::KEY, $this->request());

        self::assertCount($expected, $logger->warnings);
        if (1 === $expected) {
            self::assertStringContainsString('fail closed', $logger->warnings[0]);
        }
    }
}
