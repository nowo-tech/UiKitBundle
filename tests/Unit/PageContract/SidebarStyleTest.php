<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\Shell\SidebarStyle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

/**
 * 2.52 inset sidebar style of the app shell. Classic (the default, whether it comes from the config, the
 * `sidebar_style` option or the `_nowo_ui_sidebar_style` request attribute, and whatever invalid value is given) renders
 * byte-identical to UiKit 2.51, which printed the below-header shell exactly as the read-only tests/Fixtures/bc-2.26
 * fixtures. Inset adds only `is-sidebar-inset` and `data-nowo-ui-sidebar-style="inset"` to the shell root — no inline
 * style or script, the same landmarks in the same order — with either sidebar layout, every aside mode, both content
 * widths, `app_shell` (option and blocks) and kit styles off.
 */
final class SidebarStyleTest extends TestCase
{
    private const SHELL = ['kit_styles' => true, 'sidebar_cookie' => null, 'sidebar_hidden_value' => 'collapsed', 'sidebar_rail_value' => 'rail', 'sidebar_storage' => null];

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function classicScenarios(): iterable
    {
        foreach (array_keys(self::shellOptions()) as $set) {
            foreach (array_keys(self::classicVariants()) as $variant) {
                yield $set.' / '.$variant => [$set, $variant];
            }
        }
    }

    #[DataProvider('classicScenarios')]
    public function testClassicIsThe251Markup(string $set, string $variant): void
    {
        [$options, $attribute, $sidebarStyle] = self::classicVariants()[$variant];
        $with = self::shellOptions()[$set] + $options;
        $twig = $this->twig($attribute, $sidebarStyle);

        foreach ([
            'app_shell_open_'.$set => static fn (): string => $twig->render('halves', ['opts' => $with]),
            'app_shell_'.$set => static fn (): string => $twig->render('shell', ['opts' => $with]),
            'app_shell_blocks_'.$set => static fn (): string => $twig->render('shell_blocks', ['opts' => $with]),
        ] as $fixture => $render) {
            $file = \dirname(__DIR__, 2).'/Fixtures/bc-2.26/'.$fixture.'.html';
            self::assertFileExists($file);
            self::assertSame((string) file_get_contents($file), $render(), $fixture.' ('.$variant.') must render exactly as UiKit 2.51 printed it');
        }
    }

    /**
     * Inset differs from the classic markup only by the root class and attribute, for every shell scenario and every
     * way of choosing it.
     *
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function insetScenarios(): iterable
    {
        foreach (array_keys(self::shellOptions()) as $set) {
            foreach (array_keys(self::insetVariants()) as $variant) {
                yield $set.' / '.$variant => [$set, $variant];
            }
        }
    }

    #[DataProvider('insetScenarios')]
    public function testInsetAddsOnlyTheRootHooks(string $set, string $variant): void
    {
        [$options, $attribute, $sidebarStyle] = self::insetVariants()[$variant];
        $with = self::shellOptions()[$set] + $options;
        $twig = $this->twig($attribute, $sidebarStyle);

        foreach (['halves' => 'app_shell_open_', 'shell' => 'app_shell_', 'shell_blocks' => 'app_shell_blocks_'] as $template => $fixture) {
            $classic = (string) file_get_contents(\dirname(__DIR__, 2).'/Fixtures/bc-2.26/'.$fixture.$set.'.html');
            $html = $twig->render($template, ['opts' => $with]);

            self::assertSame(1, substr_count($html, ' is-sidebar-inset'), $fixture.$set.' ('.$variant.')');
            self::assertSame(1, substr_count($html, ' data-nowo-ui-sidebar-style="inset"'));
            $stripped = str_replace([' is-sidebar-inset', ' data-nowo-ui-sidebar-style="inset"'], '', $html);
            self::assertSame($classic, $stripped, $fixture.$set.' ('.$variant.'): nothing but the root hooks changes');
            self::assertDoesNotMatchRegularExpression('/\sstyle=/', $html, 'no inline style (CSP)');
            self::assertStringNotContainsString('<script', $html, 'no inline script (CSP)');
        }
    }

    public function testInsetRootWithFullHeightRailAndHoverExpand(): void
    {
        $html = $this->twig(null, 'inset', 'full-height')->render('halves', ['opts' => [
            'brand_name' => 'Acme', 'brand_href' => '/', 'rail_mode' => true, 'rail' => true, 'hover_expand' => true,
            'aside_items' => [['label' => 'Invoices', 'href' => '/invoices', 'current' => true]],
        ]]);

        self::assertStringContainsString('<div class="nowo-ui-app-shell nowo-ui-shell is-content-width is-sidebar-rail is-aside-rail is-sidebar-full-height is-sidebar-inset" data-nowo-ui-shell data-nowo-ui-sidebar-style="inset" data-nowo-ui-sidebar-layout="full-height" data-nowo-ui-sidebar-rail data-nowo-ui-sidebar-mode="rail" data-nowo-ui-sidebar-hover-expand>', $html);
        self::assertStringContainsString('data-nowo-ui-aside-head', $html);
        self::assertSame(2, substr_count($html, 'data-nowo-ui-burger '));
    }

    public function testInsetWithCollapsedAsideAndFullWidth(): void
    {
        $html = $this->twig(null, 'inset')->render('halves', ['opts' => ['brand' => 'Acme', 'collapsed' => true, 'content_width' => 'full', 'rail_mode' => true]]);

        self::assertStringContainsString('<div class="nowo-ui-app-shell nowo-ui-shell is-full-width is-sidebar-collapsed is-aside-collapsed is-sidebar-inset" data-nowo-ui-shell data-nowo-ui-sidebar-style="inset" data-nowo-ui-sidebar-rail data-nowo-ui-sidebar-mode="hidden">', $html);
    }

    /**
     * The landmarks and their order (focus order follows the DOM) are the same in both styles.
     */
    public function testLandmarksAndOrderAreTheSameInBothStyles(): void
    {
        $opts = ['brand' => 'Acme', 'name' => 'Ada', 'initials' => 'A', 'aside_items' => [['label' => 'Orders', 'href' => '/orders', 'current' => true]], 'rail_mode' => true];
        $landmarks = static function (string $html): array {
            preg_match_all('/<(header|aside|main|footer|nav|button|a)\b[^>]*>/', $html, $m);

            return array_map(static fn (string $tag): string => (string) preg_replace('/ (class|data-nowo-ui-sidebar-style)="[^"]*"/', '', $tag), $m[0]);
        };
        $classic = $this->twig(null, 'classic')->render('halves', ['opts' => $opts]);
        $inset = $this->twig(null, 'inset')->render('halves', ['opts' => $opts]);

        self::assertNotSame($classic, $inset);
        self::assertSame($landmarks($classic), $landmarks($inset));
        self::assertStringContainsString('<aside id="nowo-ui-aside"', $inset);
        self::assertStringContainsString('role="contentinfo"', $inset);
    }

    public function testKitStylesOffKeepsTheHooksForHostCss(): void
    {
        $html = PageContractTwig::create(['t' => "{{ include(nowo_ui_partial('app_shell_open'), {brand: 'Acme'}) }}"], request: new Request(attributes: ['_route' => 'home']), shell: ['kit_styles' => false] + self::SHELL, sidebarStyle: 'inset')
            ->render('t');

        self::assertStringContainsString('<div class="nowo-ui-app-shell is-content-width is-sidebar-inset" data-nowo-ui-shell data-nowo-ui-sidebar-style="inset">', $html);
        self::assertStringNotContainsString(' nowo-ui-shell ', $html);
        self::assertStringNotContainsString('nowo-ui-main', $html);
    }

    public function testTwigFunctionResolvesPerRequest(): void
    {
        $twig = $this->twig('inset', 'classic');
        self::assertSame('inset|classic|inset', PageContractTwig::create(['t' => "{{ nowo_ui_sidebar_style() }}|{{ nowo_ui_sidebar_style('classic') }}|{{ nowo_ui_sidebar_style('nope') }}"], request: new Request(attributes: ['_route' => 'home', '_nowo_ui_sidebar_style' => 'inset']))->render('t'));
        self::assertStringContainsString('is-sidebar-inset', $twig->render('halves', ['opts' => ['brand' => 'Acme']]));
    }

    /**
     * Worker mode at the Twig level (2.52.1, audit RK-L2): one Environment (compiled templates, extensions, the resolver)
     * renders the shell for alternating requests; a request without a preference right after an inset one prints the
     * 2.51 classic markup byte for byte, and the inset render differs from it by the two root hooks only.
     */
    public function testOneEnvironmentRendersEachRequestsOwnStyle(): void
    {
        $request = Request::create('https://app.example/orders/7', 'GET');
        $request->attributes->set('_route', 'orders_show');
        $request->attributes->set('_route_params', []);
        $twig = $this->twig(null, 'classic', request: $request);
        $opts = ['opts' => self::shellOptions()['plain']];
        $classic = (string) file_get_contents(\dirname(__DIR__, 2).'/Fixtures/bc-2.26/app_shell_open_plain.html');

        for ($i = 0; $i < 50; ++$i) {
            $request->attributes->set(SidebarStyle::ATTRIBUTE, 'inset');
            $inset = $twig->render('halves', $opts);
            self::assertStringContainsString(' data-nowo-ui-sidebar-style="inset"', $inset, 'render '.$i.': inset');
            self::assertSame($classic, str_replace([' is-sidebar-inset', ' data-nowo-ui-sidebar-style="inset"'], '', $inset));

            $request->attributes->remove(SidebarStyle::ATTRIBUTE);
            self::assertSame($classic, $twig->render('halves', $opts), 'render '.$i.': no preference after an inset request');

            $request->attributes->set(SidebarStyle::ATTRIBUTE, 'classic');
            self::assertSame($classic, $twig->render('halves', $opts), 'render '.$i.': classic preference');
        }
    }

    /**
     * Variant => [extra options, request attribute value (null = none), nowo_ui_kit.shell.sidebar_style].
     *
     * @return array<string, array{0: array<string, mixed>, 1: mixed, 2: string}>
     */
    private static function classicVariants(): array
    {
        return [
            'config default' => [[], null, 'classic'],
            'option' => [['sidebar_style' => 'classic'], 'inset', 'inset'],
            'request attribute' => [[], 'classic', 'inset'],
            'invalid option and attribute' => [['sidebar_style' => 'floating'], 'Inset', 'classic'],
        ];
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: mixed, 2: string}>
     */
    private static function insetVariants(): array
    {
        return [
            'config' => [[], null, 'inset'],
            'option' => [['sidebar_style' => 'inset'], 'classic', 'classic'],
            'request attribute (user preference)' => [[], 'inset', 'classic'],
            'invalid option, config' => [['sidebar_style' => 'floating'], null, 'inset'],
        ];
    }

    /**
     * The 2.26 shell scenarios of CompactHeader227BcTest (same options, same fixtures).
     *
     * @return array<string, array<string, mixed>>
     */
    private static function shellOptions(): array
    {
        return [
            'plain' => ['brand' => 'Acme', 'aside_items' => [['label' => 'Orders', 'href' => '/orders', 'current' => true]], 'aside_label' => 'Back office'],
            'rail' => ['brand_html' => '<a class="brand" href="/">Acme</a>', 'rail_mode' => true, 'rail' => true, 'header_end' => '<a href="/search">Search</a>', 'aside_items' => [['label' => 'Orders', 'href' => '/orders']]],
            'collapsed' => ['brand' => 'Acme', 'collapsed' => true, 'content_width' => 'full', 'aside_html' => '<nav>NAV</nav>', 'shell_attr' => ['data-testid' => 'shell']],
            'user_menu' => ['brand' => 'Acme', 'name' => 'Ada Lovelace', 'initials' => 'AL', 'email' => 'ada@example.com', 'avatar_src' => '/images/ada.svg', 'user_links' => [['label' => 'Profile', 'href' => '/profile']], 'logout_href' => '/logout', 'header_end' => '<a href="/help">Help</a>'],
        ];
    }

    private function twig(mixed $styleAttribute, string $sidebarStyle, string $sidebarLayout = 'below-header', ?Request $request = null): Environment
    {
        if (null === $request) {
            $request = Request::create('https://app.example/orders/7', 'GET');
            $request->attributes->set('_route', 'orders_show');
            $request->attributes->set('_route_params', []);
        }
        if (null !== $styleAttribute) {
            $request->attributes->set('_nowo_ui_sidebar_style', $styleAttribute);
        }

        return PageContractTwig::create([
            'halves' => "{{ include(nowo_ui_partial('app_shell_open'), opts) }}<main id=\"main-content\" tabindex=\"-1\">BODY</main>{{ include(nowo_ui_partial('app_shell_close'), {footer_copyright: '© Acme'}) }}",
            'shell' => "{{ include(nowo_ui_partial('app_shell'), opts|merge({main: '<main id=\"main-content\">BODY</main>'})) }}",
            'shell_blocks' => "{% embed '@NowoUiKitBundle/shell/_shell.html.twig' with opts %}{% block nowo_ui_shell_brand %}<a href=\"/\">Acme</a>{% endblock %}{% block nowo_ui_shell_header_actions %}<a href=\"/search\">Search</a>{% endblock %}{% block nowo_ui_shell_main %}<main id=\"main-content\">BODY</main>{% endblock %}{% endembed %}",
        ], request: $request, breadcrumbKit: false, shell: self::SHELL, sidebarLayout: $sidebarLayout, sidebarStyle: $sidebarStyle);
    }
}
