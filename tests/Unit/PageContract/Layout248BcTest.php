<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Closure;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\AreaNav;
use Nowo\UiKitBundle\View\AreaNavItem;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\PageStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

/**
 * 2.48 options are opt-in (`drawer_below`, `persist` on the side panel and its container, `tabs_inline` on the page
 * header): without them, and with their "off" values, the output is byte-identical to UiKit 2.47.0. The fixtures under
 * tests/Fixtures/bc-2.47 were rendered by the 2.47.0 templates (tag v2.47.0, 1d0adb8) from exactly these scenarios
 * (`UIKIT_BC_2_47_WRITE=1` over a checkout of those templates); no whitespace normalisation.
 */
final class Layout248BcTest extends TestCase
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
    public function testOutputWithoutTheNewOptionsIsAsIn247(string $name): void
    {
        $file = \dirname(__DIR__, 2).'/Fixtures/bc-2.47/'.$name.'.html';
        $html = self::renders()[$name]([]);
        if ('1' === getenv('UIKIT_BC_2_47_WRITE')) {
            if (!is_dir(\dirname($file))) {
                mkdir(\dirname($file), 0o777, true);
            }
            file_put_contents($file, $html);
        }
        self::assertFileExists($file);
        $expected = (string) file_get_contents($file);
        self::assertSame($expected, $html, $name.' must render exactly as UiKit 2.47.0 printed it');
        foreach (self::offValues()[$name] ?? [] as $label => $extra) {
            self::assertSame($expected, self::renders()[$name]($extra), $name.' ('.$label.') must render exactly as UiKit 2.47.0 printed it');
        }
    }

    /**
     * The "off" value of each new option (2.47 output expected).
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    private static function offValues(): array
    {
        $panel = ['drawer md' => ['drawer_below' => 'md'], 'drawer invalid' => ['drawer_below' => 'huge'], 'persist null' => ['persist' => null], 'persist invalid' => ['persist' => 'a b']];
        $header = ['tabs_inline false' => ['tabs_inline' => false], 'tabs_inline string' => ['tabs_inline' => 'true']];

        return [
            'side_panel' => $panel,
            'side_panel_open_overlay' => $panel,
            'side_panel_container' => ['drawer md' => ['drawer_below' => 'md'], 'drawer null' => ['drawer_below' => null]],
            'page_header_tabs' => $header,
            'page_header_tabs_split' => $header,
            'page_header_contract' => $header,
            'page_header_compact' => $header,
            'page_header_no_tabs' => ['tabs_inline true without tabs' => ['tabs_inline' => true]],
        ];
    }

    /**
     * @return array<string, Closure(array<string, mixed>): string>
     */
    private static function renders(): array
    {
        $p = '@NowoUiKitBundle/partials/';
        $twig = static fn (): Environment => PageContractTwig::create([
            'container' => "{% embed '@NowoUiKitBundle/partials/_side_panel_container.html.twig' with opts only %}{% block nowo_ui_side_panel_main %}<p>main</p>{% endblock %}{% block nowo_ui_side_panel_panel %}<aside>panel</aside>{% endblock %}{% endembed %}",
        ]);

        return [
            'side_panel' => static fn (array $x): string => $twig()->render($p.'_side_panel.html.twig', ['id' => 'comments', 'title' => 'Comments', 'close_href' => '?comments=0'] + $x),
            'side_panel_open_overlay' => static fn (array $x): string => $twig()->render($p.'_side_panel.html.twig', ['id' => 'nav', 'title' => 'Nav', 'open' => true, 'side' => 'start', 'layout' => 'overlay', 'heading_level' => 3, 'backdrop_close' => false, 'attr' => ['data-testid' => 'p', 'class' => 'x']] + $x),
            'side_panel_container' => static fn (array $x): string => $twig()->render('container', ['opts' => ['attr' => ['data-testid' => 'layout']] + $x]),
            'side_panel_toggle' => static fn (array $x): string => $twig()->render($p.'_side_panel_toggle.html.twig', ['target' => 'comments', 'label' => 'Comments', 'href' => '?comments=1', 'count' => 2] + $x),
            'page_header_tabs' => static fn (array $x): string => $twig()->render($p.'_page_header.html.twig', ['header' => self::header()] + $x),
            'page_header_tabs_split' => static fn (array $x): string => $twig()->render($p.'_page_header.html.twig', ['header' => self::header()->withSplitActions()->withStatus(new PageStatus('Draft'))] + $x),
            'page_header_contract' => static fn (array $x): string => $twig()->render('@NowoUiKit/page/_page_header.html.twig', ['header' => self::header(), 'extra_actions' => [Action::view('/p/1/print', 'Print')]] + $x),
            'page_header_compact' => static fn (array $x): string => $twig()->render($p.'_page_header.html.twig', ['header' => self::header(), 'presentation' => 'compact'] + $x),
            'page_header_no_tabs' => static fn (array $x): string => $twig()->render($p.'_page_header.html.twig', ['header' => self::header()->withTabs(null), 'toolbar' => '<em>T</em>'] + $x),
            'page_header_function' => static fn (array $x): string => PageContractTwig::create(['f' => '{{ nowo_ui_page_header(header, {presentation: "standard"}) }}'])->render('f', ['header' => self::header()] + $x),
        ];
    }

    private static function header(): PageHeader
    {
        return new PageHeader(
            'Getting started',
            'Intro',
            actions: [Action::edit('/pages/1/edit', 'Edit'), Action::view('/pages/1/raw', 'Raw')],
            tabs: new AreaNav([new AreaNavItem('Page', '/pages/1', true), new AreaNavItem('History', '/pages/1/history', icon: 'book')], 'Page sections'),
        );
    }
}
