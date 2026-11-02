<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use InvalidArgumentException;
use Nowo\UiKitBundle\Form\VisibleWhen;
use Nowo\UiKitBundle\Twig\ErrorPageExtension;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\DetailItem;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\PageStatus;
use Nowo\UiKitBundle\View\Pagination;
use Nowo\UiKitBundle\View\Stat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * 2.23 opt-in features: per-item attributes (Stat, PageStatus, DetailItem), pagination modes, the visible-when
 * contract and the path-only error-page retry URI (audit CK O1). The defaults render as 2.22 (OptInFeatures223BcTest).
 */
final class OptInFeatures223Test extends TestCase
{
    public function testStatPageStatusAndDetailItemAttributes(): void
    {
        $twig = PageContractTwig::create();
        $stat = new Stat('Uptime 24h', '99.98 %', attributes: ['data-uptime-24h' => '99.98', 'data-live' => true, 'data-off' => false], valueAttributes: ['data-value' => '"x"<y>']);
        $html = $twig->render('@NowoUiKitBundle/partials/_stat_row.html.twig', ['items' => [$stat]]);
        self::assertStringContainsString('nowo-ui-stat--neutral" data-uptime-24h="99.98" data-live>', $html);
        self::assertStringContainsString('data-value="&quot;x&quot;&lt;y&gt;">99.98 %</dd>', $html);
        self::assertStringNotContainsString('data-off', $html);

        $html = $twig->render('@NowoUiKitBundle/partials/_page_status.html.twig', ['status' => new PageStatus('Up', 'success', attributes: ['data-detail-status' => 'up'])]);
        self::assertStringContainsString('data-nowo-ui-page-status="success" data-detail-status="up">Up</span>', $html);

        $item = new DetailItem('Interval', '60 s', attributes: ['data-fact' => 'interval'], valueAttributes: ['data-interval' => '60']);
        $html = $twig->render('@NowoUiKitBundle/page/_detail_list.html.twig', ['items' => [$item], 'modifier' => 'facts']);
        self::assertStringContainsString('<div class="nowo-ui-detail-list__item" data-fact="interval">', $html);
        self::assertStringContainsString('<dd class="nowo-ui-detail-list__value" data-interval="60">60 s</dd>', $html);
    }

    /**
     * @return iterable<string, array{0: callable(): object}>
     */
    public static function unsafeAttributes(): iterable
    {
        yield 'stat onclick' => [static fn (): object => new Stat('a', 1, attributes: ['onclick' => 'x()'])];
        yield 'stat value onmouseover' => [static fn (): object => new Stat('a', 1, valueAttributes: ['OnMouseOver' => 'x()'])];
        yield 'status bad name' => [static fn (): object => new PageStatus('Up', attributes: ['a b' => '1'])];
        yield 'item array value' => [static fn (): object => new DetailItem('a', 'b', attributes: ['data-x' => ['no']])];
        yield 'item value onfocus' => [static fn (): object => new DetailItem('a', 'b', valueAttributes: ['onfocus' => 'x()'])];
    }

    /**
     * @param callable(): object $build
     */
    #[DataProvider('unsafeAttributes')]
    public function testUnsafeAttributesAreRejected(callable $build): void
    {
        $this->expectException(InvalidArgumentException::class);
        $build();
    }

    public function testInfiniteModeKeepsThePagerLinksAndAddsThePeerContract(): void
    {
        $request = new Request(['status' => 'up', 'page' => '2'], [], ['_route' => 'monitors', '_route_params' => []]);
        $twig = PageContractTwig::create(request: $request);
        $pager = Pagination::fromTotal(9, 2, 2, [2, 10])->withMode(Pagination::MODE_INFINITE);
        self::assertTrue($pager->isProgressive());
        $page = new ListPage(new PageHeader('Monitors'), [new Column('name')], [['name' => 'a'], ['name' => 'b']], pagination: $pager);
        $html = $twig->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);

        self::assertStringContainsString('<tbody data-nowo-ui-list-rows>', $html);
        self::assertStringContainsString('data-controller="list-pagination" data-nowo-ui-pagination-mode="infinite" data-nowo-ui-pagination-narrow="infinite" data-nowo-ui-pagination-wide="infinite" data-nowo-ui-pagination-query="(max-width: 900px)" data-nowo-ui-pagination-auto="3"', $html);
        self::assertStringContainsString('data-nowo-ui-pagination-next="'.htmlspecialchars('/monitors?'.http_build_query(['status' => 'up', 'per_page' => 2, 'page' => 3])).'"', $html);
        self::assertStringContainsString('data-nowo-ui-pagination-loaded-text="%count% more rows loaded" data-nowo-ui-pagination-error-text="Could not load more rows. Try again."', $html);
        // the pager links stay (no-JS fallback)
        self::assertStringContainsString('<nav class="nowo-ui-pagination__nav"', $html);
        self::assertStringContainsString('<div class="nowo-ui-pagination__more" data-nowo-ui-pagination-more-region hidden>', $html);
        self::assertStringContainsString('data-nowo-ui-pagination-more>Load more</button>', $html);
        self::assertStringContainsString('<p class="visually-hidden" role="status" aria-live="polite" data-nowo-ui-pagination-status></p>', $html);
    }

    public function testResponsiveModeLastPageCompactAndArrayInputs(): void
    {
        $twig = PageContractTwig::create(locale: 'es');
        $pager = new Pagination(5, 2, 9, route: 'monitors', mode: Pagination::MODE_RESPONSIVE, modeNarrow: 'pager', modeWide: 'infinite', breakpoint: '(max-width: 40rem)', autoLoads: 0);
        $html = $twig->render('@NowoUiKitBundle/partials/_pagination.html.twig', ['pagination' => $pager]);
        self::assertStringContainsString('data-nowo-ui-pagination-mode="responsive" data-nowo-ui-pagination-narrow="pager" data-nowo-ui-pagination-wide="infinite" data-nowo-ui-pagination-query="(max-width: 40rem)" data-nowo-ui-pagination-auto="0" data-nowo-ui-pagination-loaded-text', $html);
        self::assertStringNotContainsString('data-nowo-ui-pagination-next', $html);
        self::assertStringContainsString('>Cargar más</button>', $html);

        $html = $twig->render('@NowoUiKitBundle/partials/_pagination.html.twig', ['pagination' => $pager->withVariant(Pagination::VARIANT_COMPACT)->withMode('infinite')]);
        self::assertStringContainsString('data-nowo-ui-pagination="compact"', $html);
        self::assertStringContainsString('data-controller="list-pagination" data-nowo-ui-pagination-mode="infinite"', $html);
        self::assertStringContainsString('data-nowo-ui-pagination-more-region', $html);

        $html = $twig->render('@NowoUiKitBundle/partials/_pagination.html.twig', ['pagination' => ['page' => 1, 'per_page' => 2, 'total' => 9, 'total_pages' => 5], 'item_count' => 2, 'route' => 'monitors', 'pagination_mode' => 'responsive', 'auto_loads' => 2]);
        self::assertStringContainsString('data-nowo-ui-pagination-mode="responsive" data-nowo-ui-pagination-narrow="infinite" data-nowo-ui-pagination-wide="pager"', $html);
        self::assertStringContainsString('data-nowo-ui-pagination-auto="2" data-nowo-ui-pagination-next="/monitors?per_page=2&amp;page=2"', $html);

        // an unknown mode variable is ignored (pager)
        $html = $twig->render('@NowoUiKitBundle/partials/_pagination.html.twig', ['pagination' => new Pagination(1, 2, 9, route: 'monitors'), 'pagination_mode' => 'edit']);
        self::assertStringNotContainsString('list-pagination', $html);
    }

    public function testPaginationModeModel(): void
    {
        $pager = Pagination::fromArray(['page' => 2, 'per_page' => 10, 'total' => 50, 'mode' => 'responsive', 'mode_narrow' => 'infinite', 'mode_wide' => 'infinite', 'breakpoint' => '(max-width: 700px)', 'auto_loads' => 5]);
        self::assertSame(['responsive', 'infinite', 'infinite', '(max-width: 700px)', 5], [$pager['mode'], $pager['mode_narrow'], $pager['mode_wide'], $pager['breakpoint'], $pager['auto_loads']]);
        self::assertSame('responsive', $pager->withVariant('compact')->mode);
        $plain = new Pagination(1, 10, 50);
        self::assertFalse($plain->isProgressive());
        self::assertSame(['pager', 'infinite', 'pager', Pagination::DEFAULT_BREAKPOINT, 3], [$plain->mode, $plain->modeNarrow, $plain->modeWide, $plain->breakpoint, $plain->autoLoads]);
        self::assertArrayNotHasKey('mode', $plain->toArray());
        $kept = $pager->withMode('infinite');
        self::assertSame(['infinite', '(max-width: 700px)', 5], [$kept->mode, $kept->breakpoint, $kept->autoLoads]);
    }

    /**
     * @return iterable<string, array{0: callable(): Pagination}>
     */
    public static function invalidModes(): iterable
    {
        yield 'mode' => [static fn (): Pagination => new Pagination(1, 10, 5, mode: 'scroll')];
        yield 'narrow' => [static fn (): Pagination => new Pagination(1, 10, 5, modeNarrow: 'responsive')];
        yield 'wide' => [static fn (): Pagination => new Pagination(1, 10, 5, modeWide: 'x')];
        yield 'breakpoint empty' => [static fn (): Pagination => new Pagination(1, 10, 5, breakpoint: ' ')];
        yield 'breakpoint markup' => [static fn (): Pagination => new Pagination(1, 10, 5, breakpoint: '"><script>')];
        yield 'breakpoint long' => [static fn (): Pagination => new Pagination(1, 10, 5, breakpoint: str_repeat('a', 201))];
        yield 'auto loads' => [static fn (): Pagination => new Pagination(1, 10, 5, autoLoads: -1)];
    }

    /**
     * @param callable(): Pagination $build
     */
    #[DataProvider('invalidModes')]
    public function testInvalidModesAreRejected(callable $build): void
    {
        $this->expectException(InvalidArgumentException::class);
        $build();
    }

    public function testVisibleWhenContract(): void
    {
        self::assertSame(['data-nowo-ui-visible-when-field' => 'settings[provider]', 'data-nowo-ui-visible-when-in' => '["smtp","ses/v2","1",""]'], VisibleWhen::attributes('settings[provider]', ['smtp', 'ses/v2', true, false, 'smtp']));
        self::assertSame([], VisibleWhen::fromOption(null));
        self::assertSame(VisibleWhen::attributes('provider', ['smtp']), VisibleWhen::fromOption(['field' => 'provider', 'in' => ['smtp']]));
        self::assertSame(['class' => 'wide', 'data-nowo-ui-visible-when-field' => 'p', 'data-nowo-ui-visible-when-in' => '["2"]'], VisibleWhen::rowAttr(['class' => 'wide'], 'p', [2]));
        self::assertSame(['data-controller' => 'visible-when'], VisibleWhen::containerAttributes());

        // Twig helper + the UiKit form theme renders it as row_attr; hidden fields are never disabled
        $forms = PageContractTwig::forms();
        $form = $forms->createNamedBuilder('settings', FormType::class, ['provider' => 'ses', 'smtp_host' => 'mail.example.com'], ['attr' => VisibleWhen::containerAttributes()])
            ->add('provider', ChoiceType::class, ['choices' => ['SMTP' => 'smtp', 'SES' => 'ses']])
            ->add('smtp_host', TextType::class, ['row_attr' => VisibleWhen::attributes('settings[provider]', ['smtp'])])
            ->getForm();
        $twig = PageContractTwig::create(['f.html.twig' => "{% form_theme form '@NowoUiKitBundle/form/nowo_ui_layout.html.twig' %}{{ form(form) }}|{{ nowo_ui_html_attrs(nowo_ui_visible_when('settings[provider]', ['ses'])) }}"]);
        $html = $twig->render('f.html.twig', ['form' => $form->createView()]);
        self::assertStringContainsString('data-controller="visible-when"', $html);
        self::assertStringContainsString('data-nowo-ui-visible-when-field="settings[provider]" data-nowo-ui-visible-when-in="[&quot;smtp&quot;]"', $html);
        self::assertStringContainsString('value="mail.example.com"', $html);
        self::assertStringNotContainsString('disabled', $html);
        self::assertStringEndsWith('| data-nowo-ui-visible-when-field="settings[provider]" data-nowo-ui-visible-when-in="[&quot;ses&quot;]"', $html);
    }

    /**
     * @return iterable<string, array{0: callable(): mixed}>
     */
    public static function invalidVisibleWhen(): iterable
    {
        yield 'empty field' => [static fn (): array => VisibleWhen::attributes(' ', ['a'])];
        yield 'quote in field' => [static fn (): array => VisibleWhen::attributes('a"b', ['a'])];
        yield 'control char' => [static fn (): array => VisibleWhen::attributes("a\nb", ['a'])];
        yield 'array value' => [static fn (): array => VisibleWhen::attributes('a', [['x']])];
        yield 'bad option' => [static fn (): array => VisibleWhen::fromOption(['field' => 'a'])];
    }

    /**
     * @param callable(): mixed $build
     */
    #[DataProvider('invalidVisibleWhen')]
    public function testInvalidVisibleWhenIsRejected(callable $build): void
    {
        $this->expectException(InvalidArgumentException::class);
        $build();
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function retryTargets(): iterable
    {
        yield 'ordinary' => ['/orders?page=2', '/orders?page=2'];
        yield 'protocol relative' => ['//evil.example/x', '/evil.example/x'];
        yield 'slash backslash' => ['/\\evil.example/x', '/evil.example/x'];
        yield 'backslashes' => ['\\\\evil.example', '/evil.example'];
        yield 'tab' => ["/\t/evil.example", '/evil.example'];
        yield 'absolute form' => ['https://evil.example//x?a=1', '/x?a=1'];
        yield 'absolute no path' => ['http://evil.example', '/'];
        yield 'relative' => ['orders', '/orders'];
    }

    #[DataProvider('retryTargets')]
    public function testErrorPageRetryHrefIsPathOnly(string $uri, string $expected): void
    {
        self::assertSame($expected, ErrorPageExtension::pathOnly($uri));
    }

    // 2.23 (audit CK O1): a //host request-target on a 5xx GET gives a same-site "Try again" / auto-retry target
    public function testProtocolRelativeRequestTargetStaysOnSite(): void
    {
        $request = Request::create('https://app.example/x');
        $request->server->set('REQUEST_URI', '//evil.example/x');
        $stack = new RequestStack();
        $stack->push($request);
        $page = (new ErrorPageExtension([], null, $stack))->page(503);
        self::assertSame('/evil.example/x', $page->retryHref);

        $html = PageContractTwig::create(['e' => "{% extends '@NowoUiKit/error/status.html.twig' %}"], request: $request)->render('e', ['status_code' => 503]);
        self::assertStringNotContainsString('"//evil.example', $html);
        self::assertStringContainsString('href="/evil.example/x"', $html);
    }
}
