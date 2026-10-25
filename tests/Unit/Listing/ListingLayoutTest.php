<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Listing;

use Nowo\UiKitBundle\Listing\ListingField;
use Nowo\UiKitBundle\Listing\ListingLayout;
use Nowo\UiKitBundle\Listing\ListingLayoutMerger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ListingLayoutTest extends TestCase
{
    /**
     * @param list<mixed>|null $columns
     * @param list<mixed>|null $filters
     */
    private function layout(?array $columns = null, ?array $filters = null): ListingLayout
    {
        $definition = new InvoiceListing();

        return new ListingLayout($definition->key(), ListingLayoutMerger::merge($definition->columns(), $columns), ListingLayoutMerger::merge($definition->filters(), $filters), null !== $columns || null !== $filters);
    }

    public function testDefaults(): void
    {
        $layout = $this->layout();

        self::assertFalse($layout->stored);
        self::assertSame([['number'], ['customer'], ['total']], array_map(static fn (array $g): array => array_map(static fn (ListingField $f): string => $f->key, $g), $layout->visibleColumnGroups()));
        self::assertSame(['number', 'customer', 'total'], $layout->visibleColumnKeys());
        self::assertSame(['number', 'customer', 'total'], array_map(static fn (ListingField $f): string => $f->key, $layout->visibleColumns()));
        self::assertSame(['q', 'status'], $layout->visibleFilterKeys());
        self::assertSame(['q', 'status'], array_map(static fn (ListingField $f): string => $f->key, $layout->visibleFilters()));
        self::assertSame(['tag'], $layout->hiddenFilterKeys());
        self::assertTrue($layout->isColumnVisible('total'));
        self::assertFalse($layout->isColumnVisible('notes'));
        self::assertTrue($layout->isFilterVisible('q'));
        self::assertFalse($layout->isFilterVisible('tag'));
        self::assertTrue($layout->hasColumn('notes'));
        self::assertFalse($layout->hasColumn('q'));
    }

    public function testStackedGroups(): void
    {
        $layout = $this->layout([
            ['key' => 'customer', 'visible' => true, 'merged' => true],
            ['key' => 'number', 'visible' => true],
            ['key' => 'notes', 'visible' => false, 'merged' => true],
            ['key' => 'total', 'visible' => true, 'merged' => true],
        ]);

        // a stacked first column starts its own cell; a hidden column never joins a cell; "total" joins "number"
        self::assertSame([['customer'], ['number', 'total']], array_map(static fn (array $g): array => array_map(static fn (ListingField $f): string => $f->key, $g), $layout->visibleColumnGroups()));
        self::assertTrue($layout->stored);
    }

    public function testFilterRequestStripsHiddenFiltersAndHiddenSort(): void
    {
        $layout = $this->layout([['key' => 'total', 'visible' => false]], [['key' => 'status', 'visible' => false], ['key' => 'tag', 'visible' => true]]);
        $request = Request::create('/invoices', 'GET', ['q' => 'acme', 'status' => 'paid', 'tag' => 'x', 'sort' => 'total', 'dir' => 'asc', 'page' => '2']);

        $filtered = $layout->filterRequest($request);
        self::assertNotSame($request, $filtered);
        self::assertSame(['q' => 'acme', 'tag' => 'x', 'page' => '2'], $filtered->query->all());
        self::assertSame('paid', $request->query->get('status'), 'the original request is untouched');
        self::assertSame(['status'], array_map(static fn (ListingField $f): string => $f->key, $layout->ignoredFilters($request)));

        $custom = $layout->filterRequest(Request::create('/invoices', 'GET', ['order' => 'total', 'direction' => 'desc']), 'order', 'direction');
        self::assertSame([], $custom->query->all());
    }

    public function testFilterRequestKeepsVisibleAndUnknownSorts(): void
    {
        $layout = $this->layout();
        $request = Request::create('/invoices', 'GET', ['q' => 'acme', 'sort' => 'total', 'dir' => 'asc']);
        self::assertSame($request, $layout->filterRequest($request));

        $unknown = Request::create('/invoices', 'GET', ['sort' => 'created_at']);
        self::assertSame($unknown, $layout->filterRequest($unknown), 'a sort outside the definition is the host\'s business');

        $tagged = Request::create('/invoices', 'GET', ['tag' => 'x', 'sort' => ['a']]);
        self::assertSame(['sort' => ['a']], $layout->filterRequest($tagged)->query->all());
    }

    public function testIgnoredFiltersOnlyCountValues(): void
    {
        $layout = $this->layout(null, [['key' => 'q', 'visible' => false], ['key' => 'status', 'visible' => false], ['key' => 'tag', 'visible' => false]]);

        self::assertSame([], $layout->ignoredFilters(Request::create('/', 'GET', ['q' => '', 'status' => ['', ['']]])));
        self::assertSame(['status', 'tag'], array_map(static fn (ListingField $f): string => $f->key, $layout->ignoredFilters(Request::create('/', 'GET', ['status' => ['', 'paid'], 'tag' => '0']))));
    }

    public function testWithoutColumns(): void
    {
        $layout = $this->layout([['key' => 'total', 'visible' => true, 'merged' => true]]);
        $copy = $layout->withoutColumns(['total', 'customer']);

        self::assertSame(['number'], $copy->visibleColumnKeys());
        self::assertTrue($copy->columns[0]->merged, 'merged flag kept');
        self::assertTrue($copy->stored);
        self::assertSame($layout->filters, $copy->filters);
        self::assertSame(['total', 'number', 'customer'], $layout->visibleColumnKeys());
    }
}
