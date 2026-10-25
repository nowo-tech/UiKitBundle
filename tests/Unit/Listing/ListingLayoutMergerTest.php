<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Listing;

use Nowo\UiKitBundle\Listing\ListingFieldState;
use Nowo\UiKitBundle\Listing\ListingLayoutMerger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ListingLayoutMergerTest extends TestCase
{
    /**
     * @param list<ListingFieldState> $states
     *
     * @return list<string>
     */
    private function summary(array $states): array
    {
        return array_map(static fn (ListingFieldState $s): string => $s->field->key.($s->visible ? '+' : '-').($s->merged ? '^' : ''), $states);
    }

    public function testNullYieldsDefinitionDefaults(): void
    {
        self::assertSame(['number+', 'customer+', 'total+', 'notes-'], $this->summary(ListingLayoutMerger::merge((new InvoiceListing())->columns(), null)));
    }

    public function testMergeIsLenient(): void
    {
        $stored = [
            ['key' => 'total', 'visible' => true, 'merged' => true],
            'garbage',
            ['visible' => true],
            ['key' => 7],
            ['key' => 'removed_in_new_release', 'visible' => true],
            ['key' => 'number', 'visible' => false],
            ['key' => 'total', 'visible' => false],
            ['key' => 'notes', 'visible' => 'yes', 'merged' => 1],
        ];

        // order kept, duplicate / unknown / malformed dropped, required forced visible, non-bool flags false,
        // missing field (customer) appended with its default
        self::assertSame(['total+^', 'number+', 'notes-', 'customer+'], $this->summary(ListingLayoutMerger::merge((new InvoiceListing())->columns(), $stored)));
    }

    public function testNormalizeAcceptsValidLists(): void
    {
        $columns = (new InvoiceListing())->columns();

        self::assertSame([
            ['key' => 'customer', 'visible' => false],
            ['key' => 'number', 'visible' => true],
            ['key' => 'total', 'visible' => true, 'merged' => true],
        ], ListingLayoutMerger::normalize($columns, [
            ['key' => 'customer', 'visible' => false, 'merged' => false],
            ['key' => 'number', 'visible' => false],
            ['key' => 'total', 'visible' => true, 'merged' => true],
        ]));
        self::assertSame([], ListingLayoutMerger::normalize($columns, []));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidSubmissions(): iterable
    {
        yield 'not an array' => ['x'];
        yield 'not a list' => [['a' => ['key' => 'number', 'visible' => true]]];
        yield 'entry not an array' => [['number']];
        yield 'missing key' => [[['visible' => true]]];
        yield 'visible not bool' => [[['key' => 'number', 'visible' => 1]]];
        yield 'merged not bool' => [[['key' => 'number', 'visible' => true, 'merged' => 'yes']]];
        yield 'unknown key' => [[['key' => 'nope', 'visible' => true]]];
        yield 'duplicate' => [[['key' => 'total', 'visible' => true], ['key' => 'total', 'visible' => false]]];
    }

    #[DataProvider('invalidSubmissions')]
    public function testNormalizeRejects(mixed $submitted): void
    {
        self::assertNull(ListingLayoutMerger::normalize((new InvoiceListing())->columns(), $submitted));
    }

    public function testNormalizeLayout(): void
    {
        $definition = new InvoiceListing();

        self::assertSame([
            'columns' => [['key' => 'total', 'visible' => true, 'merged' => true]],
            'filters' => [['key' => 'tag', 'visible' => true]],
        ], ListingLayoutMerger::normalizeLayout($definition, [
            'columns' => [['key' => 'total', 'visible' => true, 'merged' => true]],
            'filters' => [['key' => 'tag', 'visible' => true, 'merged' => true]],
        ]));
        self::assertSame(['columns' => [], 'filters' => []], ListingLayoutMerger::normalizeLayout($definition, []));
        self::assertNull(ListingLayoutMerger::normalizeLayout($definition, 'x'));
        self::assertNull(ListingLayoutMerger::normalizeLayout($definition, ['columns' => [['key' => 'q', 'visible' => true]]]));
        self::assertNull(ListingLayoutMerger::normalizeLayout($definition, ['filters' => 'x']));
    }
}
