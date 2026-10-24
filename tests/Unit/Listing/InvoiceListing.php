<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Listing;

use Nowo\UiKitBundle\Listing\ListingDefinitionInterface;
use Nowo\UiKitBundle\Listing\ListingField;
use Nowo\UiKitBundle\View\Label;

/**
 * Test definition: four columns (one required, one hidden by default) and three filters (one hidden by default).
 */
final class InvoiceListing implements ListingDefinitionInterface
{
    public const KEY = 'invoices.index';

    public function key(): string
    {
        return self::KEY;
    }

    public function columns(): array
    {
        return [
            new ListingField('number', new Label('invoice.number'), required: true, priority: 1, cellClass: 'num'),
            new ListingField('customer', 'Customer', priority: 2, headClass: 'h-customer'),
            new ListingField('total', 'Total', priority: 3, headClass: 'h-total', cellClass: 'c-total'),
            new ListingField('notes', 'Notes', defaultVisible: false),
        ];
    }

    public function filters(): array
    {
        return [
            new ListingField('q', new Label('filter.q', 'admin')),
            new ListingField('status', 'Status'),
            new ListingField('tag', 'Tag', defaultVisible: false),
        ];
    }
}
