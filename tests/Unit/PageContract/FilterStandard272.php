<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

/**
 * The only deliberate 2.72 changes of the list_filters output without the new options (Beacon filter standard): the
 * default apply / clear labels ("Apply" / "Clear" → "Filter" / "Clear filters") and the icon-only class of those two
 * buttons (`btn_icon` slot → `nowo-ui-filter-bar__action--icon-only`). Byte-identical fixtures of older releases are
 * compared after this upgrade, so any other drift still fails.
 */
final class FilterStandard272
{
    /**
     * @param bool $defaultSubmit whether the fixture's apply button used the default label (no ListPage::filtersSubmit)
     */
    public static function upgrade(string $html, bool $defaultSubmit = true): string
    {
        $html = (string) preg_replace('/(nowo-ui-filters__submit) nowo-ui-btn--icon/', '$1 nowo-ui-filter-bar__action--icon-only', $html);
        $html = (string) preg_replace('/ nowo-ui-btn--icon(" href="[^"]*") title="Clear" aria-label="Clear">/', ' nowo-ui-filter-bar__action--icon-only$1 title="Clear filters" aria-label="Clear filters">', $html);
        $html = str_replace(['>Clear</a>', '"visually-hidden">Clear</span>', '"nowo-ui-action__label">Clear</span>'], ['>Clear filters</a>', '"visually-hidden">Clear filters</span>', '"nowo-ui-action__label">Clear filters</span>'], $html);
        if (!$defaultSubmit) {
            return $html;
        }

        return (string) preg_replace_callback('/<button type="submit" class="[^"]*nowo-ui-filters__submit[^"]*"[^>]*>.*?<\/button>/s', static fn (array $m): string => str_replace(['"Apply"', '>Apply<'], ['"Filter"', '>Filter<'], $m[0]), $html);
    }
}
