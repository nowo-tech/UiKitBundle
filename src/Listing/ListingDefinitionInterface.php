<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Listing;

/**
 * Declares the customizable columns and filters of one listing (listing customizer): what the viewer may show,
 * hide, reorder and stack in the `listing_customizer` partial.
 *
 * Declaration order is the default order, `ListingField::$defaultVisible` the default visibility: the defaults must
 * match the listing as shipped. Column keys name the `cell_<key>` blocks of the host cells template (and the sort
 * field, see {@see ListingLayout::filterRequest()}); filter keys name the fields of the host filter form.
 *
 * Autoconfigured with the tag `nowo_ui_kit.listing_definition` (or tag it yourself) and looked up by key in the
 * {@see ListingRegistry}. Implementations must be stateless (FrankenPHP worker).
 */
interface ListingDefinitionInterface
{
    public const TAG = 'nowo_ui_kit.listing_definition';

    /**
     * Stable id of the listing (e.g. `invoices.index`): stored with the preferences and part of the cookie name.
     */
    public function key(): string;

    /**
     * @return list<ListingField>
     */
    public function columns(): array;

    /**
     * @return list<ListingField>
     */
    public function filters(): array;
}
