<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Closure;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\Pagination;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

/**
 * 2.77 keyset options are opt-in (`next_query`, `cursor_params`, a pagination without a total): paginations that use
 * none of them render byte-identical to UiKit 2.76. The fixtures under tests/Fixtures/bc-2.76 were rendered by UiKit
 * 2.76.0 (tag v2.76.0, 44aa828) from exactly these scenarios; no whitespace normalisation.
 */
final class KeysetPagination277BcTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function scenarios(): iterable
    {
        foreach (array_keys(self::renders()) as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('scenarios')]
    public function testPaginationsWithoutTheNewOptionsRenderAsIn276(string $name): void
    {
        $file = \dirname(__DIR__, 2).'/Fixtures/bc-2.76/'.$name.'.html';
        self::assertFileExists($file);
        self::assertSame((string) file_get_contents($file), self::renders()[$name](), $name.' must render exactly as UiKit 2.76 printed it');
    }

    /**
     * Scenario name => render callback (2.76 API only).
     *
     * @return array<string, Closure(): string>
     */
    public static function renders(): array
    {
        $partial = '@NowoUiKitBundle/partials/_pagination.html.twig';
        $renders = [];
        foreach (['bootstrap5', 'tailwind', 'custom'] as $fw) {
            $renders['pagination_pager_'.$fw] = static fn (): string => self::twig($fw)->render($partial, ['pagination' => new Pagination(3, 10, 95, route: 'orders', query: ['q' => 'x'])])
                .self::twig($fw)->render($partial, ['pagination' => Pagination::fromTotal(95, 1, 25), 'per_page_selector' => true])
                .self::twig($fw)->render($partial, ['pagination' => new Pagination(1, 10, 0)]);
            $renders['pagination_infinite_'.$fw] = static fn (): string => self::twig($fw)->render($partial, ['pagination' => Pagination::fromTotal(9, 2, 2, [2, 10])->withMode(Pagination::MODE_INFINITE)])
                .self::twig($fw)->render($partial, ['pagination' => new Pagination(5, 2, 9, route: 'monitors', mode: Pagination::MODE_RESPONSIVE, modeNarrow: 'pager', modeWide: 'infinite', autoLoads: 0)])
                .self::twig($fw)->render($partial, ['pagination' => ['page' => 1, 'per_page' => 2, 'total' => 9, 'total_pages' => 5], 'item_count' => 2, 'route' => 'monitors', 'pagination_mode' => 'responsive', 'auto_loads' => 2]);
            $renders['pagination_compact_'.$fw] = static fn (): string => self::twig($fw)->render($partial, ['pagination' => Pagination::fromTotal(95, 2, 10, variant: Pagination::VARIANT_COMPACT)])
                .self::twig($fw)->render($partial, ['pagination' => Pagination::fromTotal(95, 2, 10, [10, 25], variant: Pagination::VARIANT_COMPACT)->withMode(Pagination::MODE_INFINITE)])
                .self::twig($fw)->render($partial, ['pagination' => Pagination::fromTotal(95, 10, 10, variant: Pagination::VARIANT_COMPACT)->withMode(Pagination::MODE_INFINITE)]);
            $renders['list_infinite_'.$fw] = static fn (): string => self::twig($fw)->render('@NowoUiKit/page/list.html.twig', ['page' => new ListPage(new PageHeader('Monitors'), [new Column('name')], [['name' => 'a'], ['name' => 'b']], pagination: Pagination::fromTotal(9, 2, 2, [2, 10])->withMode(Pagination::MODE_INFINITE))]);
        }

        return $renders;
    }

    /**
     * Writes the fixtures (run once against the reference revision, never in CI).
     */
    public static function dumpFixtures(string $dir): void
    {
        foreach (self::renders() as $name => $render) {
            file_put_contents($dir.'/'.$name.'.html', $render());
        }
    }

    private static function twig(string $framework): Environment
    {
        $request = Request::create('https://app.example/monitors?status=up&page=2&per_page=2', 'GET');
        $request->attributes->set('_route', 'monitors');
        $request->attributes->set('_route_params', []);

        return PageContractTwig::create(framework: $framework, request: $request, breadcrumbKit: false);
    }
}
