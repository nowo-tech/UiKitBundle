<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use DOMDocument;
use DOMXPath;
use Nowo\UiKitBundle\Test\FrameworkClasses;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Keyset (cursor) pager partial (2.56): Newer / Older links, optional per-page links, accessible nav, escaped values.
 */
final class KeysetPagerTest extends TestCase
{
    private const TEMPLATE = '@NowoUiKitBundle/partials/_keyset_pager.html.twig';

    /**
     * @param array<string, mixed> $vars
     */
    private function render(array $vars, string $framework = 'bootstrap5'): string
    {
        return PageContractTwig::create([], [], $framework)->render(self::TEMPLATE, $vars + ['route' => 'api_usage']);
    }

    public function testBothCursors(): void
    {
        $html = $this->render(['next_cursor' => 'n1', 'prev_cursor' => 'p1', 'testid' => 'calls']);

        self::assertStringContainsString('<nav class="nowo-ui-keyset-pager d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3" aria-label="Pagination (newer and older)" data-nowo-ui-keyset-pager data-testid="calls">', $html);
        self::assertStringContainsString('href="/api_usage?cursor=p1" rel="prev" data-testid="calls-newer"', $html);
        self::assertStringContainsString('href="/api_usage?cursor=n1" rel="next" data-testid="calls-older"', $html);
        self::assertStringContainsString('<span>Newer</span>', $html);
        self::assertStringContainsString('<span>Older</span>', $html);
        self::assertSame(2, substr_count($html, '<span aria-hidden="true">'), 'one decorative icon per link');
        self::assertLessThan(strpos($html, 'rel="next"'), strpos($html, 'rel="prev"'));
        self::assertStringNotContainsString('keyset_pager.', $html);
        self::assertStringNotContainsString('per-page', $html);
    }

    public function testOneCursorOrNone(): void
    {
        $older = $this->render(['next_cursor' => 'n1']);
        self::assertStringContainsString('rel="next"', $older);
        self::assertStringNotContainsString('rel="prev"', $older);
        self::assertStringNotContainsString('data-testid', $older);

        $newer = $this->render(['prev_cursor' => 'p1', 'next_cursor' => null]);
        self::assertStringContainsString('rel="prev"', $newer);
        self::assertStringNotContainsString('rel="next"', $newer);

        self::assertSame('', trim($this->render([])));
        self::assertSame('', trim($this->render(['next_cursor' => '', 'prev_cursor' => null])));
        self::assertStringContainsString('cursor=0', $this->render(['next_cursor' => 0]), 'a zero cursor is a cursor');
    }

    public function testParamsAreMergedAndTheCursorIsReplaced(): void
    {
        $html = $this->render([
            'next_cursor' => 'n2',
            'route' => 'orgs_calls',
            'route_params' => ['id' => 7],
            'query' => ['status' => 'error', 'cursor' => 'old', 'tag' => ['a', 'b']],
        ]);

        self::assertStringContainsString('href="/orgs_calls?id=7&amp;status=error&amp;tag%5B0%5D=a&amp;tag%5B1%5D=b&amp;cursor=n2"', $html);
        self::assertStringNotContainsString('cursor=old', $html);
    }

    public function testDefaultsToTheCurrentRoute(): void
    {
        $request = new Request([], [], ['_route' => 'current_route', '_route_params' => ['slug' => 'x']]);
        $html = PageContractTwig::create(request: $request)->render(self::TEMPLATE, ['next_cursor' => 'n']);

        self::assertStringContainsString('href="/current_route?slug=x&amp;cursor=n"', $html);
    }

    public function testCustomCursorParam(): void
    {
        $html = $this->render(['next_cursor' => 'n', 'prev_cursor' => 'p', 'cursor_param' => 'after', 'query' => ['after' => 'stale', 'cursor' => 'kept']]);

        self::assertStringContainsString('href="/api_usage?cursor=kept&amp;after=n"', $html);
        self::assertStringContainsString('href="/api_usage?cursor=kept&amp;after=p"', $html);
        self::assertStringNotContainsString('stale', $html);
    }

    public function testLabelsIconsAndNavLabel(): void
    {
        $html = $this->render(['next_cursor' => 'n', 'prev_cursor' => 'p', 'next_label' => 'Older <calls>', 'prev_label' => 'keyset_pager.newer', 'nav_label' => 'Call pages', 'next_icon' => 'chevron_right', 'prev_icon' => 'chevron_left']);

        self::assertStringContainsString('<span>Older &lt;calls&gt;</span>', $html);
        self::assertStringContainsString('<span>Newer</span>', $html);
        self::assertStringContainsString('aria-label="Call pages"', $html);
        self::assertStringContainsString('bi-chevron-right', $html);
        self::assertStringContainsString('bi-chevron-left', $html);

        $es = PageContractTwig::create(locale: 'es')->render(self::TEMPLATE, ['route' => 'r', 'next_cursor' => 'n', 'prev_cursor' => 'p']);
        self::assertStringContainsString('<span>Más antiguos</span>', $es);
        self::assertStringContainsString('<span>Más recientes</span>', $es);

        $custom = PageContractTwig::create(messages: ['app' => ['calls.older' => 'Earlier calls']])->render(self::TEMPLATE, ['route' => 'r', 'next_cursor' => 'n', 'next_label' => 'calls.older', 'trans_domain' => 'app', 'nav_label' => 'x']);
        self::assertStringContainsString('<span>Earlier calls</span>', $custom);
    }

    public function testNoIconsWithTheNoneIconSet(): void
    {
        $twig = PageContractTwig::create();
        $twig->addGlobal('nowo_ui_kit_icon_set', 'none');
        $html = $twig->render(self::TEMPLATE, ['route' => 'r', 'next_cursor' => 'n', 'prev_cursor' => 'p']);

        self::assertStringNotContainsString('aria-hidden', $html);
        self::assertStringNotContainsString('nowo-ui-icon', $html);
        self::assertStringContainsString('<span>Older</span>', $html);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function hostileValues(): iterable
    {
        yield 'script' => ['<script>alert(1)</script>'];
        yield 'double quote' => ['x" onmouseover="alert(1)'];
        yield 'single quote' => ["x' onfocus='alert(1)"];
        yield 'ampersand' => ['a&b=c&amp;d'];
        yield 'mixed' => ['"><img src=x onerror=alert(1)>&\'<'];
    }

    #[DataProvider('hostileValues')]
    public function testHostileValuesNeverBreakOutOfTheAttributes(string $hostile): void
    {
        $html = $this->render([
            'next_cursor' => $hostile,
            'prev_cursor' => $hostile,
            'query' => ['q' => $hostile, $hostile => 'v'],
            'route_params' => ['id' => $hostile],
            'testid' => $hostile,
            'nav_label' => $hostile,
            'next_label' => $hostile,
            'per_page_options' => [10, $hostile],
            'per_page' => $hostile,
        ]);

        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('" onmouseover=', $html);
        self::assertStringNotContainsString("' onfocus=", $html);
        $doc = new DOMDocument();
        $doc->loadHTML('<?xml encoding="utf-8"?>'.$html, \LIBXML_NOERROR);
        $anchors = $doc->getElementsByTagName('a');
        self::assertGreaterThanOrEqual(3, $anchors->length);
        foreach ((new DOMXPath($doc))->query('//*') ?: [] as $element) {
            foreach ($element->attributes ?? [] as $attr) {
                self::assertStringStartsNotWith('on', $attr->name, 'no event handler attribute was injected');
            }
        }
        foreach ($anchors as $a) {
            foreach ($a->attributes ?? [] as $attr) {
                self::assertContains($attr->name, ['class', 'href', 'rel', 'data-testid', 'aria-current'], 'only the template attributes');
            }
            self::assertStringStartsWith('/api_usage?', $a->getAttribute('href'));
        }
    }

    public function testRawQueryStringIsNeverEchoed(): void
    {
        $request = new Request(['evil' => '<b>x</b>', 'cursor' => 'abc'], [], ['_route' => 'r', '_route_params' => []]);
        $html = PageContractTwig::create(request: $request)->render(self::TEMPLATE, ['next_cursor' => 'n']);

        self::assertStringNotContainsString('evil', $html);
        self::assertStringNotContainsString('abc', $html);
    }

    public function testPerPageSelector(): void
    {
        $html = $this->render(['next_cursor' => 'n', 'prev_cursor' => 'p', 'per_page_options' => [25, 50, 100], 'per_page' => 50, 'query' => ['status' => 'ok', 'cursor' => 'c'], 'testid' => 'calls']);

        self::assertStringContainsString('<div class="nowo-ui-keyset-pager__per-page d-flex align-items-center gap-2" data-testid="calls-per-page">', $html);
        self::assertStringContainsString('<span>Per page</span>', $html);
        self::assertStringContainsString('href="/api_usage?status=ok&amp;per_page=25">25</a>', $html);
        self::assertStringContainsString('href="/api_usage?status=ok&amp;per_page=50" aria-current="true">50</a>', $html);
        self::assertStringContainsString('href="/api_usage?status=ok&amp;per_page=100">100</a>', $html);
        self::assertSame(1, substr_count($html, 'aria-current'));
        // the per-page size travels on Newer / Older; the options return to the first page (no cursor)
        self::assertStringContainsString('href="/api_usage?status=ok&amp;per_page=50&amp;cursor=n" rel="next"', $html);
        self::assertStringContainsString('href="/api_usage?status=ok&amp;per_page=50&amp;cursor=p" rel="prev"', $html);
        self::assertStringNotContainsString('per_page=25&amp;cursor', $html);

        $custom = $this->render(['per_page_options' => [10], 'per_page_param' => 'limit', 'per_page' => 10]);
        self::assertStringContainsString('href="/api_usage?limit=10" aria-current="true">10</a>', $custom);
        self::assertStringNotContainsString('rel=', $custom, 'options alone: no cursor links');
        self::assertStringNotContainsString('data-testid', $custom);
    }

    public function testPerPageFromTheQueryTravelsWithoutTheSetting(): void
    {
        $html = $this->render(['next_cursor' => 'n', 'query' => ['per_page' => 25]]);

        self::assertStringContainsString('href="/api_usage?per_page=25&amp;cursor=n"', $html);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function frameworks(): iterable
    {
        yield 'bootstrap5' => ['bootstrap5', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mt-3', 'btn btn-outline-secondary btn-sm', 'btn btn-link btn-sm'];
        yield 'bootstrap4' => ['bootstrap4', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mt-3', 'btn btn-outline-secondary btn-sm', 'btn btn-link btn-sm'];
        yield 'tailwind' => ['tailwind', 'flex flex-wrap items-center justify-between gap-2 mt-3', 'inline-flex items-center gap-1 font-medium px-2 py-1 text-sm border', 'px-2 py-1 text-sm underline'];
        yield 'foundation' => ['foundation', 'grid-x align-justify align-middle mt-3', 'button secondary tiny', ''];
        yield 'custom' => ['custom', '', '', ''];
    }

    #[DataProvider('frameworks')]
    public function testFrameworkVariants(string $framework, string $nav, string $link, string $option): void
    {
        $html = $this->render(['next_cursor' => 'n', 'prev_cursor' => 'p', 'per_page_options' => [10, 20], 'per_page' => 10], $framework);

        self::assertStringContainsString('<nav class="'.trim('nowo-ui-keyset-pager '.$nav).'"', $html);
        self::assertSame(2, substr_count($html, 'class="'.trim('nowo-ui-keyset-pager__link '.$link).'"'));
        self::assertSame(2, substr_count($html, 'class="'.trim('nowo-ui-keyset-pager__option '.$option).'"'));
        preg_match_all('/class="([^"]*)"/', $html, $m);
        self::assertSame([], FrameworkClasses::violations(preg_split('/\s+/', implode(' ', $m[1])) ?: [], $framework));
    }

    public function testThemeClassOverrideAndBundleException(): void
    {
        $twig = PageContractTwig::create([], ['classes' => ['keyset_pager_link' => 'app-pager-link']]);
        $html = $twig->render(self::TEMPLATE, ['route' => 'r', 'next_cursor' => 'n', 'bundle' => 'NowoXBundle']);

        self::assertStringContainsString('app-pager-link"', $html);
    }
}
