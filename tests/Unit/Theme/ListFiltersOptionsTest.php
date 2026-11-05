<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Theme;

use Closure;
use InvalidArgumentException;
use Nowo\UiKitBundle\Theme\ListFiltersOptions;
use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ListFiltersOptionsTest extends TestCase
{
    public function testGridModesAndClasses(): void
    {
        $fit = new ListFiltersOptions();
        self::assertNull($fit->gridColumns());
        self::assertSame('', $fit->gridClass());
        self::assertSame('', $fit->gridClass('auto-fit'));
        self::assertSame('nowo-ui-filters--grid-auto-fill', $fit->gridClass('auto-fill'));

        $fill = new ListFiltersOptions(grid: 'auto-fill');
        self::assertNull($fill->gridColumns());
        self::assertSame('nowo-ui-filters--grid-auto-fill', $fill->gridClass());
        self::assertSame('', $fill->gridClass('auto-fit'));

        $custom = new ListFiltersOptions(grid: ' repeat(auto-fill, minmax(min(100%, 13rem), 1fr)) ');
        self::assertSame('repeat(auto-fill, minmax(min(100%, 13rem), 1fr))', $custom->gridColumns());
        self::assertSame('', $custom->gridClass());
        self::assertSame('nowo-ui-filters--grid-auto-fit', $custom->gridClass('auto-fit'));
        self::assertSame('nowo-ui-filters--grid-auto-fill', $custom->gridClass('auto-fill'));

        foreach (['repeat(3, 1fr)', 'minmax(0, 2fr) 1fr', 'repeat(auto-fill, minmax(calc(10rem + 2%), 1fr))', 'var(--app-tracks)', '10rem 1fr'] as $ok) {
            self::assertNull(ListFiltersOptions::gridError($ok), $ok);
        }
        foreach (['', ' ', 'a;b', 'repeat(2, 1fr))(', ')(', 'url(x)', 'URL (x)', 'x{y}', 'a"b', "a'b", 'a\\b', str_repeat('1fr ', 60)] as $bad) {
            self::assertNotNull(ListFiltersOptions::gridError($bad), $bad);
        }
    }

    public function testFromConfigAndErrors(): void
    {
        self::assertEquals(new ListFiltersOptions(), ListFiltersOptions::fromConfig([]));
        self::assertEquals(new ListFiltersOptions(display: 'icon_text', foldEnabled: true), ListFiltersOptions::fromConfig(['display' => 'icon_text', 'fold' => ['enabled' => true]]));
        self::assertNull(ListFiltersOptions::error([]));
        self::assertSame('a map is expected.', ListFiltersOptions::error('x'));
        self::assertSame('display "big" must be null, text, icon, icon_text.', ListFiltersOptions::error(['display' => 'big']));
        self::assertStringContainsString('unknown key(s) "nope"', (string) ListFiltersOptions::error(['nope' => 1]));
        self::assertStringContainsString('unknown fold key(s) "key"', (string) ListFiltersOptions::error(['fold' => ['key' => 'x']]));
        self::assertSame('fold must be a map.', ListFiltersOptions::error(['fold' => 'yes']));
        self::assertSame('fold.icons must be a map {expanded, collapsed}.', ListFiltersOptions::error(['fold' => ['icons' => 'x']]));
        self::assertSame('fold.enabled must be a boolean, string given.', ListFiltersOptions::error(['fold' => ['enabled' => 'yes']]));
        self::assertSame('submit_class must be a string or null, int given.', ListFiltersOptions::error(['submit_class' => 3]));
    }

    public function testThemePrintsTheCustomTrackListAsAToken(): void
    {
        $theme = new Theme(pages: ['list' => ['filters' => ['grid' => 'repeat(auto-fill, minmax(min(100%, 13rem), 1fr))']]]);
        self::assertTrue($theme->hasTokens());
        self::assertSame(":root {\n  --nowo-ui-filters-grid-columns: repeat(auto-fill, minmax(min(100%, 13rem), 1fr));\n}", $theme->tokensCss());

        // an explicit token wins (with or without the prefix)
        $theme = new Theme(tokens: ['filters-grid-columns' => '1fr 1fr'], pages: ['list' => ['filters' => ['grid' => 'repeat(3, 1fr)']]]);
        self::assertSame(":root {\n  --nowo-ui-filters-grid-columns: 1fr 1fr;\n}", $theme->tokensCss());
        $theme = new Theme(tokens: ['--nowo-ui-filters-grid-columns' => '1fr'], pages: ['list' => ['filters' => ['grid' => 'repeat(3, 1fr)']]]);
        self::assertSame(":root {\n  --nowo-ui-filters-grid-columns: 1fr;\n}", $theme->tokensCss());

        // auto-fit / auto-fill are classes, not tokens
        self::assertFalse((new Theme(pages: ['list' => ['filters' => ['grid' => 'auto-fill']]]))->hasTokens());
        self::assertSame('', (new Theme())->tokensCss());
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function invalid(): iterable
    {
        yield 'filters on a non-list page' => [static fn (): Theme => new Theme(pages: ['show' => ['filters' => []]]), 'Invalid UiKit theme pages.show.filters: on the list page type only.'];
        yield 'filters not a map' => [static fn (): Theme => new Theme(pages: ['list' => ['filters' => 'x']]), 'Invalid UiKit theme pages.list.filters: a map is expected.'];
        yield 'filters invalid' => [static fn (): Theme => new Theme(pages: ['list' => ['filters' => ['grid' => 'a;b']]]), 'Invalid UiKit theme pages.list.filters: grid "a;b" must be'];
        yield 'ListPage filtersGrid' => [static fn (): ListPage => new ListPage(new PageHeader('x'), filtersGrid: 'repeat(2, 1fr)'), 'Invalid ListPage filtersGrid "repeat(2, 1fr)" (auto-fit|auto-fill).'];
        yield 'ListPage filtersFoldKey' => [static fn (): ListPage => new ListPage(new PageHeader('x'), filtersFoldKey: 'a,b'), 'Invalid ListPage filtersFoldKey "a,b"'];
    }

    /**
     * @param Closure(): mixed $build
     */
    #[DataProvider('invalid')]
    public function testRejectsInvalidOptions(Closure $build, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        $build();
    }

    public function testListPagePerCallOptions(): void
    {
        $page = new ListPage(new PageHeader('x'), filtersFold: true, filtersFoldKey: 'orders.index', filtersGrid: 'auto-fill');
        self::assertTrue($page->filtersFold);
        self::assertSame('orders.index', $page->filtersFoldKey);
        self::assertSame('auto-fill', $page->filtersGrid);
        $page = new ListPage(new PageHeader('x'));
        self::assertNull($page->filtersFold);
        self::assertNull($page->filtersFoldKey);
        self::assertNull($page->filtersGrid);
    }
}
