<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

/**
 * 2.60 demo platform shell (@NowoUiKit/demo_platform/layout.html.twig): the one layout of every bundle demo. Top bar
 * (platform brand, bundle name, locale pills, theme toggle, sign-in / user menu), aside nav from the
 * nowo_ui_demo_platform variable (current item, sections, test ids), content landmark, footer, assets, the
 * accessibility contract (skip link, landmarks and their names, aria-current) and request isolation (one Twig
 * environment, several requests: nothing carried over, as in a FrankenPHP worker with the kernel kept alive).
 */
final class DemoPlatformTest extends TestCase
{
    private const BASE = <<<'TWIG'
{% extends '@NowoUiKit/demo_platform/layout.html.twig' %}
{% set nowo_ui_demo_platform = platform %}
{% block nowo_ui_page_header %}{{ include(nowo_ui_partial('page_header'), {title: 'Contact us', intro: 'A public form.'}) }}{% endblock %}
{% block body %}<p data-testid="page-body">BODY</p>{% endblock %}
TWIG;

    private const PLATFORM = [
        'bundle' => 'Contact Form',
        'home_route' => 'demo_home',
        'repository' => 'https://github.com/nowo-tech/ContactFormBundle',
        'translation_domain' => 'messages',
        'locales' => ['en', 'es'],
        'nav' => [
            ['label' => 'demo.home', 'route' => 'demo_home'],
            ['label' => 'demo.contact', 'route' => 'form_show', 'params' => ['slug' => 'contact'], 'section' => 'demo.public'],
            ['label' => 'demo.job', 'route' => 'form_show', 'params' => ['slug' => 'job'], 'section' => 'demo.public'],
            ['label' => 'demo.admin', 'route' => 'admin_index', 'active' => ['admin_*'], 'section' => 'demo.admin_section', 'testid' => 'nav-admin'],
        ],
    ];

    private const MESSAGES = ['messages' => [
        'demo.home' => 'Home',
        'demo.contact' => 'Contact us',
        'demo.job' => 'Job application',
        'demo.admin' => 'Forms',
        'demo.public' => 'Public pages',
        'demo.admin_section' => 'Admin',
    ]];

    public function testShellRegionsAssetsAndNav(): void
    {
        $html = $this->render($this->twig($this->request('form_show', ['slug' => 'contact', '_locale' => 'en'])));

        // Assets: the base theme is linked even with nowo_ui_kit.base_stylesheet off, before the kit and the platform CSS.
        $base = strpos($html, 'css/nowo-ui-base.css');
        $kit = strpos($html, 'css/nowo-ui.css');
        $platform = strpos($html, 'css/nowo-ui-demo-platform.css');
        self::assertNotFalse($base);
        self::assertNotFalse($kit);
        self::assertNotFalse($platform);
        self::assertLessThan($kit, $base);
        self::assertLessThan($platform, $kit);
        self::assertSame(1, substr_count($html, 'css/nowo-ui-base.css'));
        self::assertStringContainsString('<script src="/bundles/nowouikit/js/nowo-ui-shell.js" defer></script>', $html);
        self::assertStringContainsString('js/nowo-ui-theme.js', $html);
        self::assertStringNotContainsString('cdn', strtolower($html));

        // Top bar: platform brand, bundle name linking home, theme toggle.
        self::assertMatchesRegularExpression('#<div class="nowo-ui-app-shell nowo-ui-shell is-content-width nowo-ui-demo-platform is-main-content" data-nowo-ui-shell data-testid="demo-platform">#', $html);
        self::assertStringContainsString('<a class="nowo-ui-demo-platform__platform" href="https://github.com/nowo-tech" data-testid="demo-platform-brand">', $html);
        self::assertStringContainsString('<span class="nowo-ui-demo-platform__platform-name">nowo · bundles</span>', $html);
        self::assertStringContainsString('<a class="nowo-ui-demo-platform__bundle" href="/demo_home" data-testid="demo-platform-bundle">Contact Form</a>', $html);
        self::assertStringContainsString('data-nowo-ui-theme-toggle', $html);
        self::assertStringNotContainsString('data-nowo-ui-user-menu', $html);
        self::assertStringNotContainsString('demo-platform-sign-in', $html);

        // Locale pills: current route with _locale swapped, the current one marked.
        self::assertStringContainsString('<nav class="nowo-ui-demo-platform__locales" aria-label="Language" data-testid="demo-platform-locales">', $html);
        self::assertStringContainsString('href="/form_show?slug=contact&amp;_locale=en" hreflang="en" lang="en" aria-current="true"', $html);
        self::assertStringContainsString('href="/form_show?slug=contact&amp;_locale=es" hreflang="es" lang="es">', $html);

        // Aside: unnamed first group, then one group per section, current item by route + params.
        self::assertStringContainsString('<aside id="nowo-ui-demo-aside" class="nowo-ui-app-shell__aside nowo-ui-aside" data-nowo-ui-aside aria-label="Demo navigation">', $html);
        self::assertStringContainsString('<nav class="nowo-ui-aside__nav" aria-label="Demo navigation">', $html);
        self::assertStringContainsString('<p class="nowo-ui-aside__label" id="nowo-ui-demo-nav-2">Public pages</p>', $html);
        self::assertStringContainsString('<nav class="nowo-ui-aside__nav" aria-labelledby="nowo-ui-demo-nav-2">', $html);
        self::assertStringContainsString('<p class="nowo-ui-aside__label" id="nowo-ui-demo-nav-3">Admin</p>', $html);
        self::assertSame(1, substr_count($html, 'aria-current="page"'));
        self::assertMatchesRegularExpression('#class="nowo-ui-aside__link is-current"\s+href="/form_show\?slug=contact"\s+aria-current="page"\s+data-testid="demo-nav-form_show"\s*>Contact us</a>#', $html);
        self::assertMatchesRegularExpression('#class="nowo-ui-aside__link"\s+href="/form_show\?slug=job"\s+data-testid="demo-nav-form_show"\s*>Job application</a>#', $html);
        self::assertMatchesRegularExpression('#href="/admin_index"\s+data-testid="nav-admin"\s*>Forms</a>#', $html);

        // Content and footer.
        self::assertMatchesRegularExpression('#<main class="nowo-ui-content nowo-ui-demo-platform__content" id="nowo-ui-content" tabindex="-1">\s*<div class="nowo-ui-page-header#', $html);
        self::assertStringContainsString('<p data-testid="page-body">BODY</p>', $html);
        self::assertStringContainsString('<footer class="nowo-ui-footer" role="contentinfo">', $html);
        self::assertStringContainsString('<p class="nowo-ui-footer__meta">nowo · bundles · Contact Form demo</p>', $html);
        self::assertStringContainsString('href="https://github.com/nowo-tech/ContactFormBundle"', $html);
        self::assertStringContainsString('>Source code</a>', $html);
    }

    public function testAccessibilityContract(): void
    {
        $html = $this->render($this->twig($this->request('admin_forms_edit', ['id' => 3])));

        // Skip link first in the body, to the focusable main landmark.
        self::assertMatchesRegularExpression('#<body[^>]*>\s*<a class="nowo-ui-skip-link" href="\#nowo-ui-content">Skip to main content</a>#', $html);
        self::assertSame(1, substr_count($html, 'id="nowo-ui-content"'));
        self::assertSame(1, substr_count($html, '<main'));
        // One banner, one complementary (named), one contentinfo.
        self::assertSame(1, substr_count($html, '<header class="nowo-ui-app-shell__header'));
        self::assertSame(1, substr_count($html, '<aside '));
        self::assertSame(1, substr_count($html, 'role="contentinfo"'));
        // Every nav landmark has a name; the burger controls the aside and names its action.
        self::assertSame(0, preg_match('#<nav(?![^>]*aria-label)(?![^>]*aria-labelledby)[^>]*>#', $html));
        self::assertStringContainsString('aria-controls="nowo-ui-demo-aside" aria-expanded="true"', $html);
        // Prefix match (admin_*) marks the admin item current on a deeper admin page.
        self::assertMatchesRegularExpression('#href="/admin_index"\s+aria-current="page"\s+data-testid="nav-admin"#', $html);
        // Locale pills: visible code hidden from assistive tech, the full name read instead.
        self::assertStringContainsString('<span aria-hidden="true">ES</span><span class="visually-hidden">', $html);
        // No inline handlers or inline styles (CSP friendly).
        self::assertSame(0, preg_match('/\son[a-z]+=/i', $html));
        self::assertSame(0, preg_match('/\sstyle=/i', $html));
        self::assertMatchesRegularExpression('#<html lang="en"#', $html);
    }

    public function testAuthSignInAndUserMenu(): void
    {
        $platform = self::PLATFORM + ['auth' => ['login_route' => 'app_login', 'logout_route' => 'app_logout']];
        $twig = $this->twig($this->request('demo_home'));

        $anonymous = $this->render($twig, $platform);
        self::assertStringContainsString('<a class="nowo-ui-btn nowo-ui-btn--primary nowo-ui-btn--sm" href="/app_login" data-testid="demo-platform-sign-in">Sign in</a>', $anonymous);
        self::assertStringNotContainsString('data-nowo-ui-user-menu', $anonymous);

        $user = new class {
            public function getUserIdentifier(): string
            {
                return 'ada@example.com';
            }
        };
        $request = $this->request('demo_home');
        $signedIn = $this->render($this->twig($request), $platform, ['app' => new class($request, $user) {
            public function __construct(public readonly Request $request, public readonly object $user)
            {
            }

            /** @return array<string, list<mixed>> */
            public function getFlashes(): array
            {
                return [];
            }
        }]);
        self::assertStringContainsString('data-nowo-ui-user-menu', $signedIn);
        self::assertStringContainsString('ada@example.com', $signedIn);
        self::assertStringContainsString('href="/app_logout"', $signedIn);
        self::assertStringNotContainsString('demo-platform-sign-in', $signedIn);
    }

    public function testBlocksFooterOffAndPlainLabels(): void
    {
        $page = <<<'TWIG'
{% extends 'base' %}
{% block nowo_ui_demo_platform_header_end %}<a class="nowo-ui-btn" href="/edit" data-testid="extra-action">Edit menus</a>{% endblock %}
{% block nowo_ui_demo_platform_aside_end %}<nav aria-label="Rendered sidebar" data-testid="product-menu">MENU</nav>{% endblock %}
TWIG;
        $twig = PageContractTwig::create(['base' => self::BASE, 'page' => $page], [], 'custom', request: $this->request('demo_home'), messages: self::MESSAGES);
        $html = $twig->render('page', ['platform' => [
            'bundle' => 'Dashboard Menu',
            'version' => '2.24',
            'translation_domain' => false,
            'footer' => false,
            'locales' => [['code' => 'en', 'label' => 'English', 'href' => '/switch/en'], ['code' => 'fr', 'label' => 'Français', 'href' => '/switch/fr']],
            'nav' => [['label' => 'Overview', 'route' => 'demo_home'], ['label' => 'Docs', 'href' => 'https://example.com/docs']],
        ]]);

        self::assertStringContainsString('<span class="nowo-ui-demo-platform__bundle" data-testid="demo-platform-bundle">Dashboard Menu</span>', $html);
        self::assertStringContainsString('<span class="nowo-ui-badge nowo-ui-demo-platform__version">2.24</span>', $html);
        self::assertStringContainsString('data-testid="extra-action"', $html);
        self::assertLessThan(strpos($html, 'data-testid="demo-platform-locales"'), strpos($html, 'data-testid="extra-action"'));
        self::assertStringContainsString('<nav aria-label="Rendered sidebar" data-testid="product-menu">MENU</nav>', $html);
        self::assertStringContainsString('href="/switch/fr" hreflang="fr" lang="fr">', $html);
        self::assertStringContainsString('<span class="visually-hidden">Français</span>', $html);
        self::assertMatchesRegularExpression('#aria-current="page"\s+data-testid="demo-nav-demo_home"\s*>Overview</a>#', $html);
        self::assertStringContainsString('href="https://example.com/docs"', $html);
        self::assertStringNotContainsString('<footer', $html);
        self::assertStringNotContainsString('role="contentinfo"', $html);
    }

    public function testFullWidthInsetAndNoLocalesWithoutRoute(): void
    {
        $twig = $this->twig(new Request());
        $html = $this->render($twig, ['bundle' => 'X', 'content_width' => 'full', 'sidebar_style' => 'inset', 'locales' => ['en', 'es'], 'nav' => [['label' => 'Home', 'route' => 'demo_home']]]);

        self::assertStringContainsString('is-full-width', $html);
        self::assertStringNotContainsString('is-main-content', $html);
        self::assertStringContainsString('data-nowo-ui-sidebar-style="inset"', $html);
        self::assertStringNotContainsString('demo-platform-locales', $html, 'no current route, no href: no switcher');
        self::assertStringNotContainsString('aria-current="page"', $html);
    }

    public function testRequestIsolationInOneEnvironment(): void
    {
        // One Environment (one worker), two requests in a row: the second render only reflects its own request.
        $first = $this->request('form_show', ['slug' => 'job', '_locale' => 'es']);
        $twig = $this->twig($first);
        $a = $this->render($twig);
        self::assertMatchesRegularExpression('#href="/form_show\?slug=job"\s+aria-current="page"#', $a);
        self::assertStringContainsString('lang="es" aria-current="true"', $a);

        $second = $this->request('demo_home', ['_locale' => 'en']);
        $b = $this->render($twig, self::PLATFORM, ['app' => new class($second) {
            public function __construct(public readonly Request $request)
            {
            }

            /** @return array<string, list<mixed>> */
            public function getFlashes(): array
            {
                return [];
            }
        }]);
        self::assertMatchesRegularExpression('#href="/demo_home"\s+aria-current="page"#', $b);
        self::assertDoesNotMatchRegularExpression('#href="/form_show\?slug=job"\s+aria-current="page"#', $b);
        self::assertStringContainsString('lang="en" aria-current="true"', $b);
        self::assertStringNotContainsString('lang="es" aria-current="true"', $b);
        self::assertSame(1, substr_count($b, 'aria-current="page"'));
    }

    /**
     * @param array<string, mixed> $params
     */
    private function request(string $route, array $params = []): Request
    {
        $request = new Request([], [], ['_route' => $route, '_route_params' => $params]);
        $request->setLocale((string) ($params['_locale'] ?? 'en'));

        return $request;
    }

    private function twig(Request $request): Environment
    {
        return PageContractTwig::create(['base' => self::BASE], [], 'custom', request: $request, messages: self::MESSAGES);
    }

    /**
     * @param array<string, mixed>|null $platform
     * @param array<string, mixed>      $context
     */
    private function render(Environment $twig, ?array $platform = null, array $context = []): string
    {
        return $twig->render('base', ['platform' => $platform ?? self::PLATFORM] + $context);
    }
}
