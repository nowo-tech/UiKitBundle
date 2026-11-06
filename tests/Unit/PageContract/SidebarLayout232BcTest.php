<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

/**
 * 2.32 full-height sidebar layout is opt-in: with the default layout (`below-header`, whether it comes from the config,
 * the `sidebar_layout` option or the `_nowo_ui_sidebar_layout` request attribute, and whatever invalid value is given)
 * the app shell renders byte-identical to UiKit 2.31 — which printed the shell exactly as 2.26 did, so the reference
 * is the read-only tests/Fixtures/bc-2.26 shell fixtures (no whitespace normalisation). The full-height-only options
 * (aside_brand_html, brand_name, brand_href, brand_icon_*, the shell brand config) and hover_expand: false change
 * nothing either.
 */
final class SidebarLayout232BcTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function scenarios(): iterable
    {
        foreach (self::shellOptions() as $set => $options) {
            foreach (array_keys(self::variants()) as $variant) {
                yield $set.' / '.$variant => [$set, $variant];
            }
        }
    }

    #[DataProvider('scenarios')]
    public function testBelowHeaderIsThe231Markup(string $set, string $variant): void
    {
        [$options, $request, $sidebarLayout, $shell] = self::variants()[$variant];
        $base = self::shellOptions()[$set];
        $with = $base + $options;
        $twig = $this->twig($request, $sidebarLayout, $shell);

        foreach ([
            'app_shell_open_'.$set => static fn (): string => $twig->render('halves', ['opts' => $with]),
            'app_shell_'.$set => static fn (): string => $twig->render('shell', ['opts' => $with]),
            'app_shell_blocks_'.$set => static fn (): string => $twig->render('shell_blocks', ['opts' => $with]),
        ] as $fixture => $render) {
            $file = \dirname(__DIR__, 2).'/Fixtures/bc-2.26/'.$fixture.'.html';
            self::assertFileExists($file);
            self::assertSame((string) file_get_contents($file), $render(), $fixture.' ('.$variant.') must render exactly as UiKit 2.31 printed it');
        }
    }

    /**
     * Variant => [extra options, request attribute value (null = none), nowo_ui_kit.shell.sidebar_layout, shell global extras].
     *
     * @return array<string, array{0: array<string, mixed>, 1: mixed, 2: string, 3: array<string, mixed>}>
     */
    private static function variants(): array
    {
        $parts = ['aside_brand_html' => '<a href="/">Acme</a>', 'brand_name' => 'Acme', 'brand_href' => '/', 'brand_icon_html' => '<svg></svg>', 'brand_icon_src' => '/logo.svg'];

        return [
            'config default' => [[], null, 'below-header', []],
            'option' => [['sidebar_layout' => 'below-header'], 'full-height', 'full-height', []],
            'request attribute' => [[], 'below-header', 'full-height', []],
            'invalid option and attribute' => [['sidebar_layout' => 'sideways'], 'diagonal', 'below-header', []],
            'full-height-only options' => [$parts, null, 'below-header', ['brand' => ['name' => 'Acme', 'href' => '/', 'icon_src' => '/logo.svg']]],
            'hover expand off' => [['hover_expand' => false], null, 'below-header', ['sidebar_layout' => 'below-header', 'hover_expand' => false]],
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

    /**
     * @param array<string, mixed> $shell
     */
    private function twig(mixed $layoutAttribute, string $sidebarLayout, array $shell): Environment
    {
        $request = Request::create('https://app.example/orders/7', 'GET');
        $request->attributes->set('_route', 'orders_show');
        $request->attributes->set('_route_params', []);
        if (null !== $layoutAttribute) {
            $request->attributes->set('_nowo_ui_sidebar_layout', $layoutAttribute);
        }

        return PageContractTwig::create([
            'halves' => "{{ include(nowo_ui_partial('app_shell_open'), opts) }}<main id=\"main-content\" tabindex=\"-1\">BODY</main>{{ include(nowo_ui_partial('app_shell_close'), {footer_copyright: '© Acme'}) }}",
            'shell' => "{{ include(nowo_ui_partial('app_shell'), opts|merge({main: '<main id=\"main-content\">BODY</main>'})) }}",
            'shell_blocks' => "{% embed '@NowoUiKitBundle/shell/_shell.html.twig' with opts %}{% block nowo_ui_shell_brand %}<a href=\"/\">Acme</a>{% endblock %}{% block nowo_ui_shell_header_actions %}<a href=\"/search\">Search</a>{% endblock %}{% block nowo_ui_shell_main %}<main id=\"main-content\">BODY</main>{% endblock %}{% endembed %}",
        ], request: $request, breadcrumbKit: false, shell: $shell + ['kit_styles' => true, 'sidebar_cookie' => null, 'sidebar_hidden_value' => 'collapsed', 'sidebar_rail_value' => 'rail', 'sidebar_storage' => null], sidebarLayout: $sidebarLayout);
    }
}
