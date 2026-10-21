<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\Test\FrameworkClasses;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Kit error pages (2.6): @NowoUiKit/error/status.html.twig on @NowoUiKit/error/layout.html.twig — the union of the
 * Beacon / sms-bridge templates/error/layout.html.twig behaviour (REQ-ERROR-001 item 10, REQ-A11Y-002, CSP).
 */
final class ErrorPagesTest extends TestCase
{
    private const HOST = "{% extends '@NowoUiKit/error/status.html.twig' %}";

    public function testServerErrorOnGetRetriesAndReloadsItself(): void
    {
        $request = Request::create('https://app.example/orders?page=2', 'GET');
        $request->attributes->set('csp_nonce', 'n0nce');
        $html = PageContractTwig::create(['error.html.twig' => self::HOST], request: $request)->render('error.html.twig', ['status_code' => 500, 'status_text' => 'Internal Server Error']);

        self::assertStringStartsWith('<!DOCTYPE html>', ltrim($html));
        self::assertStringContainsString('<html lang="en" data-nowo-ui-theme-storage="nowo-ui-theme">', $html);
        self::assertStringContainsString('<title>500 — Server error</title>', $html);
        self::assertStringContainsString('<meta name="robots" content="noindex">', $html);
        self::assertMatchesRegularExpression('#<script nonce="n0nce" data-nowo-ui-theme-boot>#', $html);
        self::assertStringContainsString('<link rel="stylesheet" href="/bundles/nowouikit/css/nowo-ui.css">', $html);
        self::assertStringContainsString('<body class="nowo-ui-error-page" data-error-status="500">', $html);
        self::assertStringContainsString('<a class="nowo-ui-skip-link" href="#main-content">Skip to main content</a>', $html);
        self::assertStringContainsString('<div class="nowo-ui-error-page__top">', $html);
        self::assertStringContainsString('data-nowo-ui-theme-toggle', $html);
        self::assertStringContainsString('<main id="main-content" tabindex="-1" class="nowo-ui-error-page__main">', $html);
        self::assertStringContainsString('<p class="nowo-ui-error-page__code" aria-hidden="true">500</p>', $html);
        self::assertStringContainsString('<h1 class="nowo-ui-error-page__title">Server error</h1>', $html);
        self::assertStringContainsString('<p class="nowo-ui-error-page__lead">Something failed on our side, not yours. Try again in a moment.</p>', $html);
        self::assertStringNotContainsString('<p class="nowo-ui-error-page__hint">', $html, 'UiKit ships no hint');
        self::assertMatchesRegularExpression('#<p class="nowo-ui-error-page__hint" data-testid="error-auto-retry" data-nowo-ui-error-retry data-nowo-ui-error-retry-url="/orders\?page=2" data-nowo-ui-error-retry-seconds="600">\s*This page reloads automatically in <span data-nowo-ui-error-retry-remaining>10:00</span>#', $html);
        self::assertStringContainsString('<a class="nowo-ui-btn nowo-ui-btn--primary btn btn-primary" href="/" data-testid="error-home">Back to home</a>', $html);
        self::assertStringContainsString('<a class="nowo-ui-btn nowo-ui-btn--secondary btn btn-outline-secondary" href="/orders?page=2" data-testid="error-retry">Try again</a>', $html);
        self::assertStringNotContainsString('error-back', $html);
        self::assertStringContainsString('<script src="/bundles/nowouikit/js/nowo-ui-theme.js" defer nonce="n0nce"></script>', $html);
        self::assertStringContainsString('<script src="/bundles/nowouikit/js/nowo-ui-error-retry.js" defer nonce="n0nce"></script>', $html);
        self::assertSame(0, preg_match('/\son[a-z]+=/i', $html), 'no inline handlers');
        self::assertSame(0, preg_match('/<script(?![^>]*nonce=)[^>]*>/', $html), 'every script carries the nonce');
        self::assertSame(0, preg_match('/<style(?![^>]*nonce=)[^>]*>/', $html), 'every style carries the nonce');
    }

    public function testPostNotFoundTooManyAndUnknownCodes(): void
    {
        $post = Request::create('https://app.example/orders', 'POST');
        $html = PageContractTwig::create(['e' => self::HOST], request: $post)->render('e', ['status_code' => 503]);
        self::assertStringNotContainsString('data-testid="error-retry"', $html);
        self::assertStringNotContainsString('error-auto-retry', $html);
        self::assertStringNotContainsString('data-nowo-ui-error-retry ', $html);

        $get = Request::create('https://app.example/missing', 'GET', server: ['HTTP_REFERER' => 'https://app.example/orders']);
        $twig = PageContractTwig::create(['e' => self::HOST], request: $get);
        $notFound = $twig->render('e', ['status_code' => 404]);
        self::assertStringContainsString('<h1 class="nowo-ui-error-page__title">Page not found</h1>', $notFound);
        self::assertStringContainsString('href="https://app.example/orders" data-testid="error-back">Go back</a>', $notFound);
        self::assertStringNotContainsString('<figure', $notFound);

        $tooMany = $twig->render('e', ['status_code' => 429, 'exception' => ['Retry-After' => '30']]);
        self::assertStringContainsString('<p class="nowo-ui-error-page__hint" data-testid="error-retry-after">Try again in 30 seconds.</p>', $tooMany);
        self::assertStringNotContainsString('error-back', $tooMany);

        $timeout = $twig->render('e', ['status_code' => 408]);
        self::assertStringContainsString('data-testid="error-retry">Try again</a>', $timeout);
        self::assertStringNotContainsString('error-auto-retry', $timeout);

        $unknown = $twig->render('e', ['status_code' => 418]);
        self::assertStringContainsString('<title>418 — Something went wrong</title>', $unknown);
        self::assertStringContainsString('<h1 class="nowo-ui-error-page__title">Something went wrong</h1>', $unknown);

        $noCode = $twig->render('e', []);
        self::assertStringContainsString('<title>Something went wrong</title>', $noCode);
        self::assertStringNotContainsString('nowo-ui-error-page__code', $noCode);
        self::assertStringContainsString('data-error-status=""', $noCode);
    }

    public function testIllustrationHostTextsThemeAndConfig(): void
    {
        $request = Request::create('https://app.example/x', 'GET');
        $twig = PageContractTwig::create(['e' => self::HOST], [
            'classes' => ['error_card' => 'error-page__card', 'error_title' => 'error-page__title', 'error_home' => ['value' => 'btn-primary', 'framework_defaults' => false]],
            'attributes' => ['error_card' => ['data-host' => 'card']],
        ], request: $request, errorPages: [
            'translation_domain' => 'messages',
            'illustrations' => [404 => ['path' => 'illustrations/error-404', 'width' => 896, 'height' => 597]],
            'theme_storage' => 'beacon-theme',
            'theme_toggle' => false,
            'html_attributes' => ['data-csp-strict-nonce' => true],
            'home_path' => '/dashboard',
        ], messages: ['messages' => [
            'error.404.title' => 'Off the map',
            'error.404.hint' => 'Even frogs take a wrong turn & get lost.',
            'error.404.image_alt' => 'Frog with a map',
            'error.skip_to_content' => 'Skip ahead',
        ]]);

        $html = $twig->render('e', ['status_code' => 404]);
        self::assertStringContainsString('<html lang="en" data-nowo-ui-theme-storage="beacon-theme" data-csp-strict-nonce>', $html);
        self::assertStringNotContainsString('data-nowo-ui-theme-toggle', $html);
        self::assertStringContainsString('>Skip ahead</a>', $html);
        self::assertStringContainsString('<div class="nowo-ui-error-page__card error-page__card" data-host="card" data-nowo-ui-region="error">', $html);
        self::assertMatchesRegularExpression('#<figure class="nowo-ui-error-page__figure">\s*<picture>\s*<source type="image/webp" srcset="/bundles/nowouikit/illustrations/error-404.webp">\s*<img class="nowo-ui-error-page__img" src="/bundles/nowouikit/illustrations/error-404.png" width="896" height="597" alt="Frog with a map" decoding="async" fetchpriority="high">\s*</picture>\s*</figure>#', $html);
        self::assertStringContainsString('<h1 class="nowo-ui-error-page__title error-page__title">Off the map</h1>', $html);
        self::assertStringContainsString('<p class="nowo-ui-error-page__lead">The page you are looking for does not exist or has moved. Check the address.</p>', $html, 'UiKit text where the host has none');
        self::assertStringContainsString('<p class="nowo-ui-error-page__hint">Even frogs take a wrong turn &amp; get lost.</p>', $html, 'escaped once');
        self::assertStringContainsString('<a class="nowo-ui-btn nowo-ui-btn--primary btn-primary" href="/dashboard" data-testid="error-home">', $html);

        foreach (['none', 'tailwind', 'bootstrap5'] as $framework) {
            $page = PageContractTwig::create(['e' => self::HOST], [], $framework, request: $request)->render('e', ['status_code' => 500]);
            preg_match_all('/class="([^"]*)"/', $page, $m);
            self::assertSame([], FrameworkClasses::violations(preg_split('/\s+/', implode(' ', $m[1])) ?: [], $framework), $framework);
        }
    }

    public function testHostLayoutAndBlocks(): void
    {
        $layout = <<<'TWIG'
{% extends '@NowoUiKit/error/layout.html.twig' %}
{% block nowo_ui_error_head %}<link rel="stylesheet" href="/build/app.css">{% endblock %}
{% block nowo_ui_error_body_end %}<div id="chat-dock"></div>{% endblock %}
{% block nowo_ui_error_scripts %}{{ parent() }}<script src="/build/app.js" defer></script>{% endblock %}
TWIG;
        $page = <<<'TWIG'
{% extends '@NowoUiKit/error/status.html.twig' %}
{% block error_extra_actions %}<a href="#" data-nowo-chat-open="support">Report this problem</a>{% endblock %}
{% block error_extra %}<p class="support">Ref {{ error.statusCode }}</p>{% endblock %}
{% block error_hint %}{% endblock %}
TWIG;
        $request = Request::create('https://app.example/x', 'GET');
        $html = PageContractTwig::create(['host/error_layout.html.twig' => $layout, 'e' => $page], request: $request, errorPages: ['layout_template' => 'host/error_layout.html.twig'])
            ->render('e', ['status_code' => 502]);

        self::assertStringContainsString('<link rel="stylesheet" href="/build/app.css">', $html);
        self::assertStringContainsString('<div id="chat-dock"></div>', $html);
        self::assertStringContainsString('<script src="/build/app.js" defer></script>', $html);
        self::assertStringContainsString('nowo-ui-error-retry.js', $html);
        self::assertMatchesRegularExpression('#data-testid="error-retry">Try again</a>\s*<a href="\#" data-nowo-chat-open="support">Report this problem</a>\s*</div>\s*<p class="support">Ref 502</p>#', $html);
        self::assertStringContainsString('<h1 class="nowo-ui-error-page__title">Bad gateway</h1>', $html);
    }

    public function testEveryShippedCodeHasItsTextsInEveryLocale(): void
    {
        foreach (glob(PageContractTwig::root().'/src/Resources/translations/NowoUiKitBundle.*.yaml') ?: [] as $file) {
            $catalogue = \Symfony\Component\Yaml\Yaml::parseFile($file);
            self::assertIsArray($catalogue);
            foreach (['skip_to_content', 'home', 'retry', 'back', 'retry_after', 'auto_retry', 'generic.title', 'generic.lead', 'generic.image_alt'] as $key) {
                self::assertArrayHasKey('error.'.$key, $catalogue, basename($file));
            }
            foreach ([400, 401, 403, 404, 405, 408, 410, 413, 422, 429, 500, 502, 503, 504] as $code) {
                self::assertArrayHasKey('error.'.$code.'.title', $catalogue, basename($file));
                self::assertArrayHasKey('error.'.$code.'.lead', $catalogue, basename($file));
            }
            self::assertStringContainsString('%seconds%', (string) $catalogue['error.retry_after'], basename($file));
            self::assertStringContainsString('%code%', (string) $catalogue['error.generic.image_alt'], basename($file));
        }
    }
}
