<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Closure;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\FilterField;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;

/**
 * 2.30 list-filter host options (`theme.pages.list.filters`: fold toggle, default display, button classes / icons,
 * grid track mode; `ListPage::filtersFold` / `filtersGrid`) are opt-in: list pages rendered without them print
 * byte-identical to UiKit 2.29. The fixtures under tests/Fixtures/bc-2.29 were rendered by UiKit 2.29.1 (origin/main
 * 93beca6) from exactly these scenarios; no whitespace normalisation.
 */
final class ListFilters230BcTest extends TestCase
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
    public function testListFiltersWithoutTheNewOptionsRenderAsIn229(string $name): void
    {
        $file = \dirname(__DIR__, 2).'/Fixtures/bc-2.29/'.$name.'.html';
        self::assertFileExists($file);
        self::assertSame(file_get_contents($file), self::renders()[$name](), $name.' must render exactly as UiKit 2.29 printed it');
    }

    /**
     * Scenario name => render callback (2.29 API only).
     *
     * @return array<string, Closure(): string>
     */
    public static function renders(): array
    {
        $renders = [];
        foreach (['bootstrap5', 'tailwind', 'custom'] as $fw) {
            $renders['list_filters_plain_'.$fw] = static fn (): string => self::render($fw, new ListPage(new PageHeader('Orders'), [new Column('name')], [['name' => 'A']], self::form(), filtersClearHref: '/orders'));
            $renders['list_filters_options_'.$fw] = static fn (): string => self::render($fw, new ListPage(
                new PageHeader('Orders', actions: [new Action('create', 'New', '/new', icon: 'create')]),
                [new Column('name')],
                [['name' => 'A']],
                self::form(),
                filterFields: [new FilterField('q', new Label('Search orders', 'admin'), 'lg', 'app-search', 'app-row', ['data-x' => '1']), new FilterField('status', width: 'sm')],
                filtersSubmit: new Action('filter', 'Go', icon: 'filter', attributes: ['data-testid' => 'apply']),
                filtersClear: new Action('close', 'Reset', '/orders', icon: 'close'),
                filterActions: [new Action('export', 'Export', '/orders.csv', icon: 'export')],
                headerActionsInFilters: true,
            ));
        }
        foreach (['icon', 'text', 'icon_text'] as $display) {
            $renders['list_filters_display_'.$display] = static fn (): string => self::render('bootstrap5', new ListPage(new PageHeader('Orders'), [new Column('name')], [['name' => 'A']], self::form(), filtersClear: new Action('close', 'Reset', '/orders'), filtersDisplay: $display));
        }
        $renders['list_filters_partial_host_classes'] = static fn (): string => PageContractTwig::create([], ['classes' => ['filters' => 'app-filters', 'filters_field' => 'app-field', 'filters_actions' => 'app-actions', 'btn_primary' => 'app-btn']], 'tailwind', request: self::request())
            ->render('@NowoUiKitBundle/page/_list_filters.html.twig', ['page' => new ListPage(new PageHeader('Orders'), filters: self::form(), filtersClearHref: '/orders')]);

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

    private static function render(string $framework, ListPage $page): string
    {
        return PageContractTwig::create(framework: $framework, request: self::request(), breadcrumbKit: false)->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);
    }

    private static function request(): Request
    {
        $request = Request::create('https://app.example/orders?filters[q]=abc', 'GET');
        $request->attributes->set('_route', 'orders');
        $request->attributes->set('_route_params', []);

        return $request;
    }

    private static function form(): FormView
    {
        return PageContractTwig::forms()->createNamedBuilder('filters', FormType::class, ['q' => 'abc'], ['method' => 'GET'])
            ->add('q', SearchType::class, ['required' => false])
            ->add('status', ChoiceType::class, ['required' => false, 'choices' => ['Open' => 'open']])
            ->add('mine', CheckboxType::class, ['required' => false])
            ->getForm()->createView();
    }
}
