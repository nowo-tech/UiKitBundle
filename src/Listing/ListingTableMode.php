<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Listing;

/**
 * How the listing table fits its container (`partials/_responsive_table.html.twig`): every visible column with
 * horizontal scroll, or only the columns that fit with a row detail dialog for the rest.
 */
enum ListingTableMode: string
{
    case Scroll = 'scroll';
    case Responsive = 'responsive';
}
