<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Closure;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\Crumb;
use Nowo\UiKitBundle\View\DetailItem;
use Nowo\UiKitBundle\View\DetailSection;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\PageStatus;
use Nowo\UiKitBundle\View\Pagination;
use Nowo\UiKitBundle\View\ShowPage;
use Nowo\UiKitBundle\View\Stat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

/**
 * 2.23 features are opt-in (per-item `attributes` / `valueAttributes` on Stat, PageStatus and DetailItem; pagination
 * modes `infinite` / `responsive`; the `visible-when` peer contract; the path-only error-page retry URI): pages that
 * use none of them render byte-identical to UiKit 2.22. The fixtures under tests/Fixtures/bc-2.22 were rendered by
 * UiKit 2.22.0 (origin/main 6fc281a, release f9aca2d) from exactly these scenarios; no whitespace normalisation.
 */
final class OptInFeatures223BcTest extends TestCase
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
    public function testPagesWithoutTheNewOptionsRenderAsIn222(string $name): void
    {
        $file = \dirname(__DIR__, 2).'/Fixtures/bc-2.22/'.$name.'.html';
        self::assertFileExists($file);
        // 2.26 adds js/nowo-ui-avatar.js to the default scripts (UPGRADING): the only line that may differ.
        self::assertSame(file_get_contents($file), (string) preg_replace('#^[ \t]*<script src="[^"]*/js/nowo-ui-avatar\.js"[^>]*></script>\n#m', '', self::renders()[$name]()), $name.' must render exactly as UiKit 2.22 printed it');
    }

    /**
     * Scenario name => render callback (2.22 API only).
     *
     * @return array<string, Closure(): string>
     */
    public static function renders(): array
    {
        $renders = [];
        foreach (['bootstrap5', 'tailwind', 'custom'] as $fw) {
            $renders['show_stats_status_'.$fw] = static fn (): string => self::twig($fw)->render('@NowoUiKit/page/show.html.twig', ['page' => self::showPage()]);
            $renders['list_paginated_'.$fw] = static fn (): string => self::twig($fw)->render('@NowoUiKit/page/list.html.twig', ['page' => self::listPage(new Pagination(2, 2, 9))]);
        }
        $renders['stat_row_partial'] = static fn (): string => self::twig('bootstrap5')->render('@NowoUiKitBundle/partials/_stat_row.html.twig', ['items' => self::stats(), 'label' => 'Key figures', 'attr' => ['data-testid' => 'stats']]);
        $renders['page_status_partial'] = static fn (): string => self::twig('bootstrap5')->render('@NowoUiKitBundle/partials/_page_status.html.twig', ['status' => new PageStatus('Up', 'success', 'Checked 1 min ago')])
            .self::twig('tailwind')->render('@NowoUiKitBundle/partials/_page_status.html.twig', ['status' => new PageStatus('Paused')]);
        $renders['detail_list_layouts'] = static function (): string {
            $html = '';
            foreach ([null, 'facts', 'meta', 'stacked'] as $modifier) {
                $html .= self::twig('bootstrap5')->render('@NowoUiKitBundle/page/_detail_list.html.twig', ['items' => self::items(), 'modifier' => $modifier]);
            }

            return $html;
        };
        $renders['pagination_partial'] = static function (): string {
            $twig = self::twig('bootstrap5');

            return $twig->render('@NowoUiKitBundle/partials/_pagination.html.twig', ['pagination' => new Pagination(3, 10, 95, route: 'orders', query: ['q' => 'x'])])
                .$twig->render('@NowoUiKitBundle/partials/_pagination.html.twig', ['pagination' => Pagination::fromTotal(95, 1, 25), 'per_page_selector' => true])
                .$twig->render('@NowoUiKitBundle/partials/_pagination.html.twig', ['pagination' => ['page' => 2, 'per_page' => 10, 'total' => 35, 'total_pages' => 4], 'item_count' => 10, 'route' => 'orders'])
                .$twig->render('@NowoUiKitBundle/partials/_pagination.html.twig', ['pagination' => new Pagination(1, 10, 0)])
                .$twig->render('@NowoUiKitBundle/partials/_pagination.html.twig', ['pagination' => Pagination::fromTotal(95, 2, 10, variant: Pagination::VARIANT_COMPACT)]);
        };
        $renders['table_partial'] = static fn (): string => self::twig('bootstrap5')->render('@NowoUiKitBundle/partials/_table.html.twig', ['table' => self::listPage(null)->table, 'label' => 'Monitors']);
        $renders['error_page_retry'] = static function (): string {
            $request = Request::create('https://app.example/orders?page=2', 'GET');

            return PageContractTwig::create(['e' => "{% extends '@NowoUiKit/error/status.html.twig' %}"], request: $request)->render('e', ['status_code' => 503]);
        };

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

    private static function showPage(): ShowPage
    {
        $header = (new PageHeader('api.example.com', null, [], [new Crumb('Monitors', '/monitors'), new Crumb('api.example.com')]))
            ->withStatus(new PageStatus('Up', 'success', 'Checked 1 min ago'));

        return new ShowPage(
            $header,
            [new DetailSection('Check', self::items(), 'check', 'facts'), new DetailSection('Notes', [new DetailItem('Owner', 'Ops')])],
            [new DetailItem('Created', '2026-10-16 09:12')],
            attributes: ['data-testid' => 'monitor'],
            stats: self::stats(),
        );
    }

    /**
     * @return list<Stat>
     */
    private static function stats(): array
    {
        return [new Stat('Uptime 24h', '99.98 %', 'last 24 h', 'success'), new Stat('Response', 182, null, 'neutral', '/monitors/1/checks'), new Stat('Incidents', 0, tone: 'warning')];
    }

    /**
     * @return list<DetailItem>
     */
    private static function items(): array
    {
        return [new DetailItem('URL', 'https://api.example.com', 'https://api.example.com'), new DetailItem('Status', 'Up', badge: 'success'), new DetailItem('Interval', '60 s'), new DetailItem('Paused', false)];
    }

    private static function listPage(?Pagination $pagination): ListPage
    {
        return new ListPage(
            new PageHeader('Monitors'),
            [new Column('name', 'Name'), new Column('status', 'Status', priority: 2)],
            [['name' => 'api', 'status' => 'up'], ['name' => 'web', 'status' => 'down']],
            pagination: $pagination,
        );
    }
}
