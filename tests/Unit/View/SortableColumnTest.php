<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\View;

use Nowo\UiKitBundle\Listing\ListingField;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 2.20 sort options of Column / ListingField and the 1.x `sort` / `direction` arguments of ListPage.
 */
final class SortableColumnTest extends TestCase
{
    public function testSortField(): void
    {
        self::assertFalse((new Column('name'))->isSortable());
        self::assertSame('name', (new Column('name', sortable: true))->sortField());
        $column = new Column('placed', sortKey: 'created', sortFirst: 'desc', sortType: 'date', sortValueKey: 'ts');
        self::assertTrue($column->isSortable());
        self::assertSame('created', $column->sortField());
        self::assertSame('desc', $column->sortFirst);
        self::assertSame('date', $column->sortType);
        self::assertSame('ts', $column->sortValueKey);
    }

    public function testListPageKeepsTheLegacySortArguments(): void
    {
        $page = new ListPage(new PageHeader('x'), [new Column('name', sortable: true)], sort: 'name', direction: 'desc');

        self::assertSame('name', $page->sort);
        self::assertSame('desc', $page->direction);
        self::assertSame('direction', $page->table->sorting->directionParam);
        self::assertSame('asc', $page->nextDirection('name'));
        self::assertSame('asc', $page->nextDirection($page->columns[0]));
        self::assertFalse($page->clientSort);
    }

    /**
     * @return iterable<string, array{\Closure(): mixed, string}>
     */
    public static function invalid(): iterable
    {
        yield 'empty sortKey' => [static fn (): Column => new Column('a', sortKey: ' '), 'The sortKey of the Column "a" must be non-empty'];
        yield 'sortFirst' => [static fn (): Column => new Column('a', sortFirst: 'up'), 'Invalid Column sortFirst "up" (asc|desc).'];
        yield 'sortType' => [static fn (): Column => new Column('a', sortType: 'money'), 'Invalid Column sortType "money" (number|date|text).'];
        yield 'listing sortKey' => [static fn (): ListingField => new ListingField('a', 'A', sortKey: ''), 'The sortKey of the ListingField "a" must be non-empty'];
        yield 'listing sortFirst' => [static fn (): ListingField => new ListingField('a', 'A', sortFirst: 'up'), 'Invalid ListingField sortFirst "up" (asc|desc).'];
    }

    /**
     * @param \Closure(): mixed $build
     */
    #[DataProvider('invalid')]
    public function testRejectsInvalidSortOptions(\Closure $build, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        $build();
    }
}
