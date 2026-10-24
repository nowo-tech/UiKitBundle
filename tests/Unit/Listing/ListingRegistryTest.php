<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Listing;

use Nowo\UiKitBundle\Listing\ListingRegistry;
use PHPUnit\Framework\TestCase;

final class ListingRegistryTest extends TestCase
{
    public function testLookup(): void
    {
        $definition = new InvoiceListing();
        $registry = new ListingRegistry(new \ArrayIterator([$definition]));

        self::assertTrue($registry->has(InvoiceListing::KEY));
        self::assertFalse($registry->has('other'));
        self::assertSame($definition, $registry->get(InvoiceListing::KEY));
        self::assertSame([InvoiceListing::KEY], $registry->keys());
    }

    public function testUnknownKeyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown listing "other" (known: invoices.index).');
        (new ListingRegistry([new InvoiceListing()]))->get('other');
    }

    public function testUnknownKeyOnEmptyRegistry(): void
    {
        $this->expectExceptionMessage('Unknown listing "x" (known: none).');
        (new ListingRegistry())->get('x');
    }

    public function testDuplicateKeysThrow(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Two listing definitions use the key "invoices.index"');
        new ListingRegistry([new InvoiceListing(), new InvoiceListing()]);
    }
}
