<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Closure;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Confirm;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

/**
 * 2.28 (remote form dialog, overlay registry, `menu_semantics`) is opt-in in the markup: with the new options left
 * out, the `menu` partial (details and popover), the `dialog` partial, the side panel partials and the `_scripts`
 * output render byte-identical to UiKit 2.27. The fixtures under tests/Fixtures/bc-2.27 were rendered by UiKit 2.27.2
 * (origin/main c38d215) from exactly these scenarios; no whitespace normalisation.
 *
 * Variants render the same call with the 2.28 option at its default (`menu_semantics: null`, side panel
 * `backdrop_close: true`), which must change nothing either.
 */
final class RemoteDialogOverlay228BcTest extends TestCase
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
    public function testOutputWithoutTheNewOptionsIsAsIn227(string $name): void
    {
        $file = \dirname(__DIR__, 2).'/Fixtures/bc-2.27/'.$name.'.html';
        self::assertFileExists($file);
        $expected = (string) file_get_contents($file);
        self::assertSame($expected, self::renders()[$name](), $name.' must render exactly as UiKit 2.27 printed it');
        foreach (self::variants()[$name] ?? [] as $variant => $render) {
            self::assertSame($expected, $render(), $name.' ('.$variant.') must render exactly as UiKit 2.27 printed it');
        }
    }

    /**
     * Scenario name => render callback (2.27 API only).
     *
     * @return array<string, Closure(): string>
     */
    public static function renders(): array
    {
        $renders = [];
        foreach (self::menuCalls() as $name => $options) {
            $renders['menu_'.$name] = static fn (): string => self::twig()->render('@NowoUiKitBundle/partials/_menu.html.twig', $options);
        }
        $renders['page_actions'] = static fn (): string => self::twig()->render('@NowoUiKitBundle/partials/_page_actions.html.twig', ['actions' => self::actions()]);
        $renders['dialog_plain'] = static fn (): string => self::twig()->render('@NowoUiKitBundle/partials/_dialog.html.twig', ['id' => 'help', 'title' => 'Help', 'body' => '<p>Body</p>']);
        $renders['dialog_options'] = static fn (): string => self::twig()->render('@NowoUiKitBundle/partials/_dialog.html.twig', [
            'id' => 'monitor', 'title' => 'Edit monitor', 'size' => 'xl', 'backdrop_close' => false,
            'attr' => ['data-uptime-monitor-form-modal' => true], 'title_attr' => ['data-monitor-form-title' => true],
            'body_attr' => ['data-monitor-form-body' => true], 'footer' => '<button type="button">OK</button>',
        ]);
        foreach (self::panelCalls() as $name => $options) {
            $renders['side_panel_'.$name] = static fn (): string => self::twig()->render('@NowoUiKitBundle/partials/_side_panel.html.twig', $options);
        }
        $renders['side_panel_container'] = static fn (): string => self::twig()->render('container');
        $renders['scripts'] = static fn (): string => self::twig()->render('@NowoUiKitBundle/partials/_scripts.html.twig');

        return $renders;
    }

    /**
     * 2.28 renders whose output must equal the 2.27 fixture of the same scenario.
     *
     * @return array<string, array<string, Closure(): string>>
     */
    public static function variants(): array
    {
        if (!self::supports228()) {
            return [];
        }
        $variants = [];
        foreach (self::menuCalls() as $name => $options) {
            $variants['menu_'.$name]['menu_semantics null'] = static fn (): string => self::twig()->render('@NowoUiKitBundle/partials/_menu.html.twig', $options + ['menu_semantics' => null]);
        }
        foreach (self::panelCalls() as $name => $options) {
            $variants['side_panel_'.$name]['backdrop_close true'] = static fn (): string => self::twig()->render('@NowoUiKitBundle/partials/_side_panel.html.twig', $options + ['backdrop_close' => true]);
        }

        return $variants;
    }

    /**
     * Writes the fixtures (run once against the reference revision, never in CI).
     */
    public static function dumpFixtures(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0o777, true);
        }
        foreach (self::renders() as $name => $render) {
            file_put_contents($dir.'/'.$name.'.html', $render());
        }
    }

    /**
     * Whether the tree under test is 2.28 or later (the variants use 2.28 options); false while dumping on 2.27.
     */
    private static function supports228(): bool
    {
        return str_contains((string) file_get_contents(PageContractTwig::root().'/src/Resources/views/partials/_menu.html.twig'), 'menu_semantics');
    }

    private static function twig(): Environment
    {
        $request = Request::create('https://app.example/orders/7', 'GET');
        $request->attributes->set('_route', 'orders_show');
        $request->attributes->set('_route_params', []);

        return PageContractTwig::create([
            'container' => "{% embed '@NowoUiKitBundle/partials/_side_panel_container.html.twig' %}{% block nowo_ui_side_panel_main %}<p>MAIN</p>{% endblock %}{% block nowo_ui_side_panel_panel %}{{ include('@NowoUiKitBundle/partials/_side_panel.html.twig', {id: 'notes', title: 'Notes', content: '<p>N</p>'}) }}{% endblock %}{% endembed %}",
        ], request: $request, breadcrumbKit: false);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function menuCalls(): array
    {
        $items = [
            ['label' => 'Profile', 'href' => '/profile', 'current' => true],
            ['label' => 'Billing', 'href' => '/billing', 'attr' => ['data-testid' => 'billing']],
            ['label' => 'Refresh', 'icon' => 'refresh', 'attr' => ['data-action' => 'list#refresh']],
            ['label' => 'Delete account', 'href' => '/delete', 'danger' => true],
        ];

        return [
            'details' => ['items' => $items, 'id' => 'account'],
            'popover' => ['items' => $items, 'id' => 'account-pop', 'popover' => true, 'icon' => 'more', 'display' => 'icon', 'square' => true],
            'popover_actions' => ['items' => self::actions(), 'id' => 'row', 'popover' => true, 'menu_items' => true],
            'content' => ['content' => '<p>Free</p>', 'id' => 'free', 'popover' => true, 'label' => 'Info'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function panelCalls(): array
    {
        return [
            'inline' => ['id' => 'notes', 'title' => 'Notes', 'content' => '<p>N</p>'],
            'overlay_open' => ['id' => 'help', 'title' => 'Help', 'open' => true, 'layout' => 'overlay', 'side' => 'start', 'close_href' => '/page', 'attr' => ['data-testid' => 'help']],
        ];
    }

    /**
     * @return list<Action>
     */
    private static function actions(): array
    {
        return [
            Action::create('/quotes/new', 'New quote'),
            new Action('copy', 'Duplicate', '/quotes/7/copy', method: 'POST', csrfToken: 'tok-copy'),
            Action::delete('/quotes/7', 'tok-del', new Confirm('Delete Q-1042?', 'This cannot be undone.')),
        ];
    }
}
