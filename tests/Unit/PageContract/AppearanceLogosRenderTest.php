<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\Brand\Contract\BrandColors;
use Nowo\UiKitBundle\Appearance\Appearance;
use Nowo\UiKitBundle\Appearance\AppearanceCssEmitter;
use Nowo\UiKitBundle\Appearance\AppearanceLogos;
use Nowo\UiKitBundle\Appearance\AppearancePalette;
use Nowo\UiKitBundle\Appearance\AppearancePresetRegistry;
use Nowo\UiKitBundle\Appearance\AppearanceProviderInterface;
use Nowo\UiKitBundle\Appearance\AppearanceView;
use Nowo\UiKitBundle\Appearance\BrandAppearanceProvider;
use Nowo\UiKitBundle\Appearance\ConfigAppearanceProvider;
use Nowo\UiKitBundle\Tests\Unit\Appearance\BrandAppearanceTest;
use Nowo\UiKitBundle\Twig\AppearanceExtension;
use Nowo\UiKitBundle\View\AuthPage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

/**
 * 2.40 (spec 220 S1): appearance logos in the shell (header, full-height aside, icons-only rail), the auth panel, the
 * guest and error brand regions and the opt-in appearance head. Without logos every template renders the 2.39 bytes.
 */
final class AppearanceLogosRenderTest extends TestCase
{
    private const HALVES = <<<'TWIG'
{{ include(nowo_ui_partial('app_shell_open'), opts) }}<main id="main-content" tabindex="-1">BODY</main>{{ include(nowo_ui_partial('app_shell_close'), {}) }}
TWIG;

    private const GUEST = "{% extends '@NowoUiKit/layout/guest.html.twig' %}{% block nowo_ui_content %}<p>Guest</p>{% endblock %}";

    private const ERROR = "{% extends '@NowoUiKit/error/status.html.twig' %}";

    private const SHELL = ['kit_styles' => true, 'sidebar_cookie' => null, 'sidebar_hidden_value' => 'collapsed', 'sidebar_rail_value' => 'rail', 'sidebar_storage' => null, 'brand' => ['name' => 'Config', 'href' => '/home', 'icon_src' => '/config-icon.png']];

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function pages(): iterable
    {
        yield 'shell below-header' => ['t', ['opts' => []]];
        yield 'shell full-height + rail' => ['t', ['opts' => ['sidebar_layout' => 'full-height', 'rail_mode' => true]]];
        yield 'shell brand option' => ['t', ['opts' => ['sidebar_layout' => 'full-height', 'brand' => 'Acme']]];
        yield 'guest layout' => ['guest', []];
        yield 'error page' => ['error', ['status_code' => 500]];
        yield 'auth page' => ['@NowoUiKit/page/auth.html.twig', ['page' => new AuthPage('Sign in', brand: 'Acme')]];
    }

    /**
     * §3.4.1 / §3.4.2: appearance off, or on without logos (config provider, head_links off) = the same bytes.
     *
     * @param array<string, mixed> $vars
     */
    #[DataProvider('pages')]
    public function testWithoutLogosEveryPageIsUnchanged(string $template, array $vars): void
    {
        $off = $this->twig(new AppearanceView())->render($template, $vars);
        $config = new ConfigAppearanceProvider(new AppearancePresetRegistry(), ['brand_name' => 'From appearance']);
        self::assertSame($off, $this->twig(new AppearanceView($config))->render($template, $vars));
        self::assertStringNotContainsString('nowo-ui-brand__logo', $off);
        self::assertStringNotContainsString('data-nowo-ui-region="brand"', $off);
    }

    public function testShellPrintsTheLogoPairAndTheRailMark(): void
    {
        $html = $this->twig($this->view($this->appearance('Acme <Billing>', new AppearanceLogos('/l.png', '/d.png', '/m.png'))))
            ->render('t', ['opts' => ['sidebar_layout' => 'full-height', 'rail_mode' => true]]);

        $logos = '<img class="nowo-ui-brand__logo" src="/l.png" alt="Acme &lt;Billing&gt;" data-nowo-ui-theme-only="light" decoding="async"><img class="nowo-ui-brand__logo" src="/d.png" alt="Acme &lt;Billing&gt;" data-nowo-ui-theme-only="dark" decoding="async">';
        $inner = '<span class="nowo-ui-app-shell__brand-icon nowo-ui-aside-brand__icon nowo-ui-aside-brand__icon--rail" aria-hidden="true"><img src="/m.png" alt="" width="28" height="28"></span>'
            .'<span class="nowo-ui-app-shell__brand-name nowo-ui-aside-brand__name nowo-ui-aside-brand__logo">'.$logos.'</span>';
        self::assertStringContainsString('<a class="nowo-ui-app-shell__brand nowo-ui-header__brand nowo-ui-aside-brand" href="/home">'.$inner.'</a>', $html);
        self::assertStringContainsString('<a class="nowo-ui-app-shell__aside-brand nowo-ui-aside-brand" href="/home" data-nowo-ui-aside-brand>'.$inner.'</a>', $html);
        self::assertStringNotContainsString('/config-icon.png', $html, 'the appearance mark wins over shell.brand.icon_src');
    }

    public function testOptionsWinOverTheAppearanceAndTheConfigIsTheLastFallback(): void
    {
        $view = $this->view($this->appearance('', new AppearanceLogos('/l.png')));

        // brand_html / brand: the page brand, no appearance at all.
        $html = $this->twig($view)->render('t', ['opts' => ['brand_html' => '<a class="host" href="/">Host</a>']]);
        self::assertStringContainsString('<a class="host" href="/">Host</a>', $html);
        self::assertStringNotContainsString('nowo-ui-brand__logo', $html);
        self::assertStringNotContainsString('nowo-ui-brand__logo', $this->twig($view)->render('t', ['opts' => ['sidebar_layout' => 'full-height', 'brand' => 'Acme']]));

        // brand_icon_src: the option's icon and the name text, no lockup.
        $icon = $this->twig($view)->render('t', ['opts' => ['brand_icon_src' => '/opt.png', 'brand_name' => 'Option']]);
        self::assertStringContainsString('<span class="nowo-ui-app-shell__brand-icon nowo-ui-aside-brand__icon" aria-hidden="true"><img src="/opt.png" alt="" width="28" height="28"></span><span class="nowo-ui-app-shell__brand-name nowo-ui-aside-brand__name">Option</span>', $icon);
        self::assertStringNotContainsString('nowo-ui-brand__logo', $icon);

        // An empty appearance name: the config name; no mark: the config icon; one logo: one <img>, no theme-only.
        $config = $this->twig($view)->render('t', ['opts' => []]);
        self::assertStringContainsString('<img src="/config-icon.png" alt="" width="28" height="28">', $config);
        self::assertStringContainsString('<img class="nowo-ui-brand__logo" src="/l.png" alt="Config" decoding="async">', $config);
        self::assertStringNotContainsString('data-nowo-ui-theme-only', $config);

        // brand_name option: the alt of the lockup.
        self::assertStringContainsString('alt="Named"', $this->twig($view)->render('t', ['opts' => ['brand_name' => 'Named']]));

        // No mark and no config icon: the initial for the rail.
        $initial = $this->twig($view, array_replace(self::SHELL, ['brand' => ['name' => 'Zeta']]))->render('t', ['opts' => []]);
        self::assertStringContainsString('<span class="nowo-ui-app-shell__brand-icon nowo-ui-aside-brand__icon nowo-ui-aside-brand__icon--initial nowo-ui-aside-brand__icon--rail" aria-hidden="true">Z</span>', $initial);
        self::assertStringContainsString('<strong class="nowo-ui-app-shell__brand nowo-ui-header__brand nowo-ui-aside-brand">', $initial);

        // Logos without a lockup (mark only): the mark replaces the config icon, the name stays text.
        $mark = $this->twig($this->view($this->appearance('Acme', new AppearanceLogos(markSrc: '/m.png'))))->render('t', ['opts' => []]);
        self::assertStringContainsString('<img src="/m.png" alt="" width="28" height="28"></span><span class="nowo-ui-app-shell__brand-name nowo-ui-aside-brand__name">Acme</span>', $mark);
        self::assertStringNotContainsString('nowo-ui-brand__logo', $mark);
    }

    public function testAuthPanelGuestAndErrorRegions(): void
    {
        $view = $this->view($this->appearance('Acme', new AppearanceLogos('/l.png', '/d.png')));

        $auth = $this->twig($view)->render('@NowoUiKit/page/auth.html.twig', ['page' => new AuthPage('Sign in', brand: 'Acme Login')]);
        self::assertStringContainsString('<p class="nowo-ui-auth-panel__brand nowo-ui-auth-panel__brand--logo" data-nowo-ui-region="brand"><img class="nowo-ui-brand__logo" src="/l.png" alt="Acme Login" data-nowo-ui-theme-only="light" decoding="async"><img class="nowo-ui-brand__logo" src="/d.png" alt="Acme Login" data-nowo-ui-theme-only="dark" decoding="async"></p>', $auth);
        self::assertStringNotContainsString('nowo-ui-brand-region', $auth, 'the auth panel replaces the guest region');
        self::assertStringContainsString('alt="Acme"', $this->twig($view)->render('@NowoUiKit/page/auth.html.twig', ['page' => new AuthPage('Sign in')]));

        $guest = $this->twig($view)->render('guest');
        self::assertStringContainsString('<div class="nowo-ui-brand-region nowo-ui-brand-region--guest" data-nowo-ui-region="brand"><img class="nowo-ui-brand__logo" src="/l.png" alt="Acme"', $guest);

        $error = $this->twig($view)->render('error', ['status_code' => 404]);
        self::assertMatchesRegularExpression('#<main id="main-content" tabindex="-1" class="nowo-ui-error-page__main">\s*<div class="nowo-ui-brand-region nowo-ui-brand-region--error" data-nowo-ui-region="brand"><img#', $error);
    }

    public function testErrorPageRendersWithAThrowingProvider(): void
    {
        $throwing = new class implements AppearanceProviderInterface {
            public function getAppearance(): Appearance
            {
                throw new RuntimeException('SQLSTATE[HY000] [2002] Connection refused');
            }
        };
        $view = new AppearanceView($throwing, true);
        $html = $this->twig($view)->render('error', ['status_code' => 500]);
        self::assertSame($this->twig(new AppearanceView())->render('error', ['status_code' => 500]), $html, 'no 500 inside a 500: the config chrome');
        self::assertSame($this->twig(new AppearanceView())->render('t', ['opts' => []]), $this->twig($view)->render('t', ['opts' => []]));
    }

    public function testTokensAndAppearanceFunctionsSurviveAThrowingProvider(): void
    {
        $registry = new AppearancePresetRegistry();
        $config = new ConfigAppearanceProvider($registry, ['brand_name' => 'Config', 'preset' => 'ocean']);
        $throwing = new class implements AppearanceProviderInterface {
            public function getAppearance(): Appearance
            {
                throw new RuntimeException('SQLSTATE[HY000] [2002] Connection refused');
            }
        };
        $logger = new class extends AbstractLogger {
            public int $count = 0;

            public function log($level, string|Stringable $message, array $context = []): void
            {
                ++$this->count;
            }
        };
        $render = function (AppearanceProviderInterface $provider, string $template, array $vars) use ($registry, $config, $logger): string {
            $twig = $this->twig(new AppearanceView($provider, false, $logger));
            $twig->addExtension(new AppearanceExtension($provider, new AppearanceCssEmitter(), $registry, null, $config, $logger));

            return $twig->render($template, $vars);
        };

        foreach ([['error', ['status_code' => 500]], ['guest', []], ['t', ['opts' => []]]] as [$template, $vars]) {
            $html = $render($throwing, $template, $vars);
            self::assertSame($render($config, $template, $vars), $html, $template.': the config chrome');
        }
        self::assertStringContainsString('data-nowo-ui-appearance>', $render($throwing, 'error', ['status_code' => 500]));
        self::assertStringContainsString('--nowo-ui-primary: #0e7490;', $render($throwing, 'guest', []), 'tokens of the configured preset');
        self::assertGreaterThan(0, $logger->count);
    }

    public function testAppearanceHeadIsOptIn(): void
    {
        $appearance = $this->appearance('Acme', new AppearanceLogos(faviconSrc: '/favicon.png', appleTouchSrc: '/touch.png'));
        $head = "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n    <link rel=\"icon\" href=\"/favicon.png\">\n    <link rel=\"apple-touch-icon\" href=\"/touch.png\">\n    <meta name=\"theme-color\" content=\"#f3f6f4\" media=\"(prefers-color-scheme: light)\">\n    <meta name=\"theme-color\" content=\"#0c1210\" media=\"(prefers-color-scheme: dark)\">\n";

        self::assertStringContainsString($head, $this->twig(new AppearanceView(BrandAppearanceTest::fixed($appearance), true))->render('guest'));
        self::assertStringContainsString('viewport-fit=cover">'."\n    <link rel=\"icon\" href=\"/favicon.png\">", $this->twig(new AppearanceView(BrandAppearanceTest::fixed($appearance), true))->render('error', ['status_code' => 500]));

        $off = $this->twig(new AppearanceView(BrandAppearanceTest::fixed($appearance)))->render('guest');
        self::assertStringNotContainsString('rel="icon"', $off);
        self::assertStringNotContainsString('theme-color', $off);
    }

    private function appearance(string $name, AppearanceLogos $logos): Appearance
    {
        $beacon = static fn (array $c): AppearancePalette => BrandAppearanceProvider::palette(BrandColors::fromArray($c));

        return new Appearance($name, '', $beacon(BrandAppearanceTest::BEACON_LIGHT), $beacon(BrandAppearanceTest::BEACON_DARK), logos: $logos);
    }

    private function view(Appearance $appearance): AppearanceView
    {
        return new AppearanceView(BrandAppearanceTest::fixed($appearance));
    }

    /**
     * @param array<string, mixed> $shell
     */
    private function twig(AppearanceView $view, array $shell = self::SHELL): Environment
    {
        return PageContractTwig::create(
            ['t' => self::HALVES, 'guest' => self::GUEST, 'error' => self::ERROR],
            request: Request::create('https://app.example/x'),
            shell: $shell,
            appearanceView: $view,
        );
    }
}
