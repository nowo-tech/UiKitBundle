<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Listing;

use Nowo\UiKitBundle\Listing\ListingField;
use Nowo\UiKitBundle\Listing\SortRequest;
use Nowo\UiKitBundle\View\Column;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class SortRequestTest extends TestCase
{
    private const FIELDS = ['name' => 'asc', 'created' => 'desc'];

    public function testReadsAWhitelistedKeyAndDirection(): void
    {
        $sort = SortRequest::fromRequest(new Request(['sort' => 'name', 'dir' => 'DESC']), self::FIELDS, 'created');

        self::assertSame('name', $sort->field);
        self::assertSame('desc', $sort->direction);
        self::assertSame(self::FIELDS, $sort->fields);
        self::assertTrue($sort->isActive('name'));
        self::assertFalse($sort->isActive('created'));
        self::assertSame(['sort' => 'name', 'dir' => 'desc'], $sort->query());
    }

    /**
     * @return iterable<string, array{mixed, mixed, string|null, string|null, string|null, string}>
     */
    public static function fallbacks(): iterable
    {
        yield 'unknown key → default and its first-click direction' => ['secret', 'asc', 'created', null, 'created', 'desc'];
        yield 'array key (tampered query) → default' => [['name'], 'asc', 'created', null, 'created', 'desc'];
        yield 'explicit default direction' => [null, null, 'created', 'asc', 'created', 'asc'];
        yield 'no default → the list order' => ['nope', null, null, null, null, 'asc'];
        yield 'unknown direction → the key\'s first-click one' => ['created', 'sideways', null, null, 'created', 'desc'];
        yield 'array direction → first-click one' => ['name', ['x'], null, null, 'name', 'asc'];
    }

    #[DataProvider('fallbacks')]
    public function testFallsBackWithoutErrors(mixed $sort, mixed $dir, ?string $default, ?string $defaultDirection, ?string $field, string $direction): void
    {
        $request = SortRequest::fromQuery($sort, $dir, self::FIELDS, $default, $defaultDirection);

        self::assertSame($field, $request->field);
        self::assertSame($direction, $request->direction);
    }

    public function testCustomParameterNamesAndAListOfKeys(): void
    {
        $request = new Request(['order' => 'b', 'way' => 'desc', 'sort' => 'a']);
        $sort = SortRequest::fromRequest($request, ['a', 'b'], sortParam: 'order', directionParam: 'way', pageParam: 'p');

        self::assertSame(['a' => 'asc', 'b' => 'asc'], $sort->fields);
        self::assertSame(['order' => 'b', 'way' => 'desc'], $sort->query());
        self::assertSame(['q' => 'x', 'order' => 'b', 'way' => 'asc'], $sort->linkQuery('b', ['q' => 'x', 'p' => 4]));
        self::assertSame(['p' => 4, 'order' => 'a', 'way' => 'asc'], $sort->withPageParam(null)->linkQuery('a', ['p' => 4]));
        self::assertSame($sort, $sort->withPageParam('p'));
        self::assertSame([], (new SortRequest())->query());
    }

    public function testNextDirection(): void
    {
        $sort = new SortRequest('created', 'desc', self::FIELDS);

        self::assertSame('asc', $sort->nextDirection('created'), 'the sorted key toggles');
        self::assertSame('asc', $sort->nextDirection('name'));
        self::assertSame('asc', $sort->nextDirection('unknown'));
        self::assertSame('desc', (new SortRequest('created', 'asc', self::FIELDS))->nextDirection('created'));
        self::assertSame('desc', (new SortRequest(null, 'asc', self::FIELDS))->nextDirection('created'));
        self::assertFalse((new SortRequest())->isActive('name'));
    }

    public function testFieldsOfColumnsAndListingFields(): void
    {
        self::assertSame(['name' => 'asc', 'created' => 'desc'], SortRequest::fieldsOf([
            new Column('name', sortable: true),
            new Column('placed', sortKey: 'created', sortFirst: 'desc'),
            new Column('notes'),
        ]));
        self::assertSame(['number' => 'desc'], SortRequest::fieldsOf([new ListingField('number', 'N', sortKey: 'number', sortFirst: 'desc'), new ListingField('notes', 'Notes')]));
        self::assertSame([], SortRequest::fieldsOf(new InvoiceListing()));
    }

    public function testSortRows(): void
    {
        $rows = [
            ['name' => 'beta', 'n' => 10],
            ['name' => 'Alpha', 'n' => 9],
            ['name' => null, 'n' => 100],
            ['name' => 'alpha 2', 'n' => 9],
            ['name' => '', 'n' => 1],
        ];
        $names = static fn (array $sorted): array => array_column($sorted, 'name');

        self::assertSame(['Alpha', 'alpha 2', 'beta', null, ''], $names((new SortRequest('name'))->sortRows($rows)));
        self::assertSame(['beta', 'alpha 2', 'Alpha', null, ''], $names((new SortRequest('name', 'desc'))->sortRows($rows)));
        self::assertSame(['', 'Alpha', 'alpha 2', 'beta', null], $names((new SortRequest('count'))->sortRows($rows, ['count' => 'n'])), 'equal values keep their order');
        self::assertSame([null, 'beta', 'Alpha', 'alpha 2', ''], $names((new SortRequest('count', 'desc'))->sortRows(new \ArrayIterator($rows), ['count' => static fn (array $row): int => $row['n']])));
        self::assertSame($rows, (new SortRequest())->sortRows($rows));

        $objects = [(object) ['n' => 2], (object) ['n' => 1], 'scalar', new \ArrayObject(['n' => 3])];
        $sorted = (new SortRequest('n'))->sortRows($objects);
        self::assertSame([$objects[1], $objects[0], $objects[3], 'scalar'], $sorted);
    }

    /**
     * @return iterable<string, array{\Closure(): mixed, string}>
     */
    public static function invalid(): iterable
    {
        yield 'direction' => [static fn (): SortRequest => new SortRequest('a', 'up'), 'Invalid sort direction "up" (asc|desc).'];
        yield 'same parameters' => [static fn (): SortRequest => new SortRequest(sortParam: 'sort', directionParam: 'sort'), 'two distinct, non-empty query parameter names'];
        yield 'empty parameter' => [static fn (): SortRequest => new SortRequest(sortParam: ' '), 'two distinct, non-empty query parameter names'];
        yield 'empty key' => [static fn (): SortRequest => new SortRequest(fields: ['']), 'Sort keys must be non-empty strings.'];
        yield 'first-click direction' => [static fn (): SortRequest => new SortRequest(fields: ['a' => 'up']), 'Invalid first-click direction "up" of the sort key "a" (asc|desc).'];
        yield 'default not whitelisted' => [static fn (): SortRequest => SortRequest::fromQuery(null, null, ['a'], 'b'), 'The default sort "b" is not one of the sortable keys (a).'];
        yield 'default direction' => [static fn (): SortRequest => SortRequest::fromQuery(null, null, ['a'], 'a', 'up'), 'Invalid sort direction "up" (asc|desc).'];
    }

    /**
     * @param \Closure(): mixed $build
     */
    #[DataProvider('invalid')]
    public function testRejectsInvalidDeclarations(\Closure $build, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        $build();
    }
}
