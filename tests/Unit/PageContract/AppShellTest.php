<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Signed-in app shell (2.6): shell/_open + _close halves (the apps' layouts/shell/*), shell/_shell with blocks,
 * shell/_burger (svg / morph glyphs, two- and three-mode labels), cookie-rendered aside state, kit styles on / off.
 */
final class AppShellTest extends TestCase
{
    private const HALVES = <<<'TWIG'
{{ include(nowo_ui_partial('app_shell_open'), opts) }}<main id="main-content" tabindex="-1">BODY</main>{{ include(nowo_ui_partial('app_shell_close'), close|default({})) }}
TWIG;

    public function testKitStyledShellWithItems(): void
    {
        $html = PageContractTwig::create(['t' => self::HALVES])->render('t', ['opts' => [
            'brand' => 'Acme',
            'aside_items' => [['label' => 'Orders', 'href' => '/orders', 'current' => true]],
            'aside_label' => 'Back office',
            'header_end' => '<a href="/search">Search</a>',
            'name' => 'Ada Lovelace',
            'initials' => 'AL',
        ]]);

        self::assertStringContainsString('<div class="nowo-ui-app-shell nowo-ui-shell is-content-width" data-nowo-ui-shell>', $html);
        self::assertStringContainsString('<header class="nowo-ui-app-shell__header nowo-ui-header">', $html);
        self::assertStringContainsString('<div class="nowo-ui-app-shell__header-start nowo-ui-header__start">', $html);
        self::assertStringContainsString('<button type="button" class="nowo-ui-app-shell__burger nowo-ui-burger nowo-ui-btn nowo-ui-btn--secondary nowo-ui-btn--sm" data-nowo-ui-burger aria-controls="nowo-ui-aside" aria-expanded="true" data-nowo-ui-burger-glyph="close" aria-label="Hide navigation" title="Hide navigation" data-label-expand="Expand navigation" data-label-hide="Hide navigation">', $html);
        self::assertStringContainsString('<svg class="nowo-ui-burger__icon" viewBox="0 0 24 24"', $html);
        self::assertStringContainsString('<strong class="nowo-ui-app-shell__brand nowo-ui-header__brand">Acme</strong>', $html);
        self::assertStringContainsString('<a href="/search">Search</a>', $html);
        self::assertStringContainsString('data-nowo-ui-user-menu', $html);
        self::assertStringContainsString('<aside id="nowo-ui-aside" class="nowo-ui-app-shell__aside nowo-ui-aside" data-nowo-ui-aside aria-label="Back office">', $html);
        self::assertStringContainsString('<p class="nowo-ui-app-shell__aside-label nowo-ui-aside__label">Back office</p>', $html);
        self::assertStringContainsString('<nav class="nowo-ui-app-shell__aside-nav nowo-ui-aside__nav">', $html);
        self::assertStringContainsString('href="/orders"', $html);
        self::assertMatchesRegularExpression('#<div class="nowo-ui-app-shell__main nowo-ui-main">\s*<main id="main-content" tabindex="-1">BODY</main>\s*</div>#', $html);
        self::assertStringContainsString('role="contentinfo"', $html);
        self::assertMatchesRegularExpression('#<div class="nowo-ui-app-shell__backdrop nowo-ui-aside-backdrop" data-nowo-ui-aside-backdrop></div>\s*</div>#', $html);
        self::assertSame(0, preg_match('/\son[a-z]+=/i', $html));
    }

    public function testHostShellCookieStateAndMorphBurger(): void
    {
        $theme = [
            'classes' => ['app_shell' => 'app-shell', 'app_aside' => 'app-sidebar', 'app_burger' => 'app-sidebar-toggle', 'app_main' => 'app-main', 'app_backdrop' => 'app-sidebar-backdrop'],
            'attributes' => ['app_shell' => ['data-app-shell' => true], 'app_aside' => ['data-tour' => 'sidebar'], 'app_burger' => ['data-sidebar-toggle' => true], 'app_backdrop' => ['data-sidebar-backdrop' => true]],
        ];
        $shell = ['kit_styles' => false, 'sidebar_cookie' => 'sms_bridge_sidebar', 'sidebar_hidden_value' => 'collapsed', 'sidebar_rail_value' => 'rail', 'sidebar_storage' => null];
        $request = new Request(attributes: ['_route' => 'home'], cookies: ['sms_bridge_sidebar' => 'collapsed']);
        $twig = PageContractTwig::create(['t' => self::HALVES], $theme, 'tailwind', request: $request, shell: $shell);

        $html = $twig->render('t', ['opts' => [
            'aside_id' => 'app-sidebar',
            'brand_html' => '<a class="brand" href="/">SMS Bridge</a>',
            'aside_html' => '<div class="app-sidebar__inner">NAV</div>',
            'aside_label' => 'Admin',
            'show_user_menu' => false,
            'content_width' => 'full',
            'shell_attr' => ['data-content-width' => 'full'],
            'burger_icon' => 'morph',
            'burger_label_attr_names' => [],
        ], 'close' => ['footer_html' => '<footer>Legal</footer>']]);

        self::assertStringContainsString('<div class="nowo-ui-app-shell app-shell is-full-width is-sidebar-collapsed" data-nowo-ui-shell data-app-shell data-content-width="full">', $html);
        self::assertStringContainsString('aria-controls="app-sidebar" aria-expanded="false" data-nowo-ui-burger-glyph="open" aria-label="Expand navigation" title="Expand navigation" data-label-expand="Expand navigation" data-label-hide="Hide navigation" data-sidebar-toggle>', $html);
        self::assertStringContainsString('class="nowo-ui-app-shell__burger app-sidebar-toggle"', $html);
        self::assertStringContainsString('<svg class="nowo-ui-burger__icon nowo-ui-burger__icon--open"', $html);
        self::assertStringContainsString('<svg class="nowo-ui-burger__icon nowo-ui-burger__icon--close"', $html);
        self::assertStringContainsString('<morph-icon class="nowo-ui-burger__morph" data-sidebar-morph size="18" stroke-width="2" aria-hidden="true"></morph-icon>', $html);
        self::assertStringContainsString('<a class="brand" href="/">SMS Bridge</a>', $html);
        self::assertStringContainsString('<aside id="app-sidebar" class="nowo-ui-app-shell__aside app-sidebar" data-nowo-ui-aside aria-label="Admin" data-tour="sidebar">', $html);
        self::assertStringContainsString('<div class="app-sidebar__inner">NAV</div>', $html);
        self::assertStringNotContainsString(' nowo-ui-shell', $html);
        self::assertStringNotContainsString('data-nowo-ui-user-menu', $html);
        self::assertStringContainsString('<footer>Legal</footer>', $html);
        self::assertStringNotContainsString('role="contentinfo"', $html);
        self::assertStringContainsString('<div class="nowo-ui-app-shell__backdrop app-sidebar-backdrop" data-nowo-ui-aside-backdrop data-sidebar-backdrop></div>', $html);

        // Explicit state wins over the cookie; no footer with footer_html: false.
        $open = $twig->render('t', ['opts' => ['collapsed' => false, 'burger_icon' => 'none'], 'close' => ['footer_html' => false]]);
        self::assertStringContainsString('aria-expanded="true"', $open);
        self::assertStringNotContainsString('<svg class="nowo-ui-burger__icon', $open);
        self::assertStringNotContainsString('<footer', $open);
    }

    public function testThreeModeRailWithKitStylesAndCookie(): void
    {
        $shell = ['kit_styles' => true, 'sidebar_cookie' => 'beacon_sidebar', 'sidebar_hidden_value' => 'collapsed', 'sidebar_rail_value' => 'rail', 'sidebar_storage' => 'beacon-sidebar'];
        $request = new Request(attributes: ['_route' => 'home'], cookies: ['beacon_sidebar' => 'rail']);
        $html = PageContractTwig::create(['t' => self::HALVES], [], 'bootstrap5', request: $request, shell: $shell)->render('t', ['opts' => [
            'rail_mode' => true,
            'burger_icon' => 'morph',
            'burger_static_icons' => ['open'],
            'burger_label_attr_names' => ['hide' => 'data-label-collapse'],
        ]]);

        self::assertStringContainsString('<div class="nowo-ui-app-shell nowo-ui-shell is-content-width is-sidebar-rail is-aside-rail" data-nowo-ui-shell data-nowo-ui-sidebar-rail data-nowo-ui-sidebar-storage="beacon-sidebar" data-nowo-ui-sidebar-cookie="beacon_sidebar" data-nowo-ui-sidebar-hidden-value="collapsed" data-nowo-ui-sidebar-mode="rail">', $html);
        self::assertStringContainsString('aria-expanded="true" data-nowo-ui-burger-glyph="open" aria-label="Hide navigation" title="Hide navigation" data-label-expand="Expand navigation" data-label-rail="Show icons only" data-label-collapse="Hide navigation">', $html, 'icons rail: the menu glyph, not the X (2.6.1)');
        self::assertStringContainsString('nowo-ui-burger__icon--open', $html);
        self::assertStringNotContainsString('nowo-ui-burger__icon--close', $html);

        $expanded = PageContractTwig::create(['t' => self::HALVES], [], 'bootstrap5', shell: $shell)->render('t', ['opts' => ['rail_mode' => true, 'collapsed' => true]]);
        self::assertStringContainsString('is-sidebar-collapsed is-aside-collapsed" data-nowo-ui-shell data-nowo-ui-sidebar-rail data-nowo-ui-sidebar-storage="beacon-sidebar" data-nowo-ui-sidebar-cookie="beacon_sidebar" data-nowo-ui-sidebar-hidden-value="collapsed" data-nowo-ui-sidebar-mode="hidden">', $expanded);

        $plain = PageContractTwig::create(['t' => self::HALVES], [], 'bootstrap5', shell: $shell)->render('t', ['opts' => ['rail_mode' => true]]);
        self::assertStringContainsString('data-nowo-ui-sidebar-mode="expanded"', $plain);
        self::assertStringContainsString('aria-label="Show icons only"', $plain, 'three-mode: the next action of an expanded aside is the rail');
    }

    public function testShellWithBlocks(): void
    {
        $embed = <<<'TWIG'
{% embed nowo_ui_partial('app_shell') with {aside_label: 'Admin', show_user_menu: false} %}
    {% block nowo_ui_shell_brand %}<a href="/">Brand</a>{% endblock %}
    {% block nowo_ui_shell_header_actions %}<button type="button">Search</button>{% endblock %}
    {% block nowo_ui_shell_aside %}<nav>Aside</nav>{% endblock %}
    {% block nowo_ui_shell_main %}<main id="main-content">Main</main>{% endblock %}
    {% block nowo_ui_shell_footer %}<footer>Foot</footer>{% endblock %}
{% endembed %}
TWIG;
        $html = PageContractTwig::create(['t' => $embed])->render('t');
        self::assertStringContainsString('<a href="/">Brand</a>', $html);
        self::assertStringContainsString('<button type="button">Search</button>', $html);
        self::assertStringContainsString('aria-label="Admin"><nav>Aside</nav>', preg_replace('/>\s+</', '><', $html) ?? '');
        self::assertMatchesRegularExpression('#<div class="nowo-ui-app-shell__main nowo-ui-main">\s*<main id="main-content">Main</main>#', $html);
        self::assertStringContainsString('<footer>Foot</footer>', $html);

        $included = PageContractTwig::create(['t' => "{{ include(nowo_ui_partial('app_shell'), {main: '<p>M</p>', brand: 'B', show_user_menu: false}) }}"])->render('t');
        self::assertStringContainsString('<p>M</p>', $included);
        self::assertStringContainsString('>B</strong>', $included);
        self::assertStringContainsString('role="contentinfo"', $included);
    }
}
