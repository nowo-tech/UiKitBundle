<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Listing;

use Nowo\UiKitBundle\Listing\ListingField;
use Nowo\UiKitBundle\Listing\ListingFieldState;
use Nowo\UiKitBundle\Listing\ListingTableMode;
use PHPUnit\Framework\TestCase;

final class ListingFieldTest extends TestCase
{
    public function testDefaultsAndVisibility(): void
    {
        $field = new ListingField('a', 'A');

        self::assertSame('a', $field->key);
        self::assertFalse($field->required);
        self::assertTrue($field->defaultVisible);
        self::assertSame(0, $field->priority);
        self::assertSame('', $field->headClass);
        self::assertSame('', $field->cellClass);
        self::assertTrue($field->isVisibleByDefault());
        self::assertFalse((new ListingField('b', 'B', defaultVisible: false))->isVisibleByDefault());
        self::assertTrue((new ListingField('c', 'C', required: true, defaultVisible: false))->isVisibleByDefault());

        $state = new ListingFieldState($field, false);
        self::assertFalse($state->merged);
        self::assertSame(['scroll', 'responsive'], array_map(static fn (ListingTableMode $m): string => $m->value, ListingTableMode::cases()));
    }

    public function testEmptyKeyIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ListingField(' ', 'A');
    }

    public function testQueryKeysDefaultToTheKey(): void
    {
        self::assertSame(['a'], (new ListingField('a', 'A'))->queryKeys());
        self::assertSame(['created_from', 'created_to'], (new ListingField('created', 'Created', queryKeys: ['created_from', 'created_to']))->queryKeys());
    }

    public function testEmptyQueryKeyIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ListingField('created', 'Created', queryKeys: ['created_from', '']);
    }
}
