<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class LayoutRenderTest extends TestCase
{
    private const CHILD = '{% extends layout %}{% block nowo_ui_document_title %}Doc{% endblock %}{% block nowo_ui_head %}<meta name="x" content="head">{% endblock %}{% block nowo_ui_styles %}{{ parent() }}<link rel="stylesheet" href="/app.css">{% endblock %}{% block nowo_ui_scripts %}{{ parent() }}<script src="/app.js" defer></script>{% endblock %}{% block nowo_ui_page_header %}<h1>HEADER</h1>{% endblock %}{% block nowo_ui_content %}<p>CONTENT</p>{% endblock %}{% block nowo_ui_modals %}<dialog id="m"></dialog>{% endblock %}';

    public function testAdminLayoutRendersCanonicalBlocksAndStacksAssets(): void
    {
        $html = PageContractTwig::create(['child' => self::CHILD.'{% block nowo_ui_navigation %}<nav>NAV</nav>{% endblock %}'])->render('child', ['layout' => '@NowoUiKit/layout/admin.html.twig']);

        self::assertStringStartsWith('<!DOCTYPE html>', $html);
        self::assertStringContainsString('<html lang="en">', $html);
        self::assertStringContainsString('<title>Doc</title>', $html);
        self::assertStringContainsString('<meta name="x" content="head">', $html);
        self::assertMatchesRegularExpression('#<link rel="stylesheet" href="/bundles/nowouikit/css/nowo-ui.css">\s*<link rel="stylesheet" href="/app.css">#', $html);
        self::assertMatchesRegularExpression('#nowo-ui-theme.js" defer></script>\s*<script src="/app.js" defer></script>#', $html);
        self::assertStringContainsString('<script src="/bundles/nowouikit/js/nowo-ui-theme-boot.js" data-nowo-ui-theme-boot></script>', $html);
        self::assertStringContainsString('class="nowo-ui-page nowo-ui-page--admin"', $html);
        self::assertStringContainsString('<nav>NAV</nav>', $html);
        self::assertMatchesRegularExpression('#<h1>HEADER</h1>\s*<div class="nowo-ui-flash nowo-ui-flash--success alert alert-success" role="alert">Saved.</div>\s*<p>CONTENT</p>\s*</main>\s*<dialog id="m"></dialog>#', $html);
        self::assertStringNotContainsString('data-nowo-ui-host-tokens', $html);
        self::assertStringNotContainsString('<style', $html);
        $this->assertNoCdnNoInlineHandlers($html);
    }

    public function testGuestLayoutHasNoNavigationRegion(): void
    {
        $html = PageContractTwig::create(['child' => self::CHILD.'{% block nowo_ui_navigation %}<nav>NAV</nav>{% endblock %}'])->render('child', ['layout' => '@NowoUiKit/layout/guest.html.twig']);

        self::assertStringContainsString('class="nowo-ui-page nowo-ui-page--guest"', $html);
        self::assertStringNotContainsString('NAV', $html);
        self::assertStringContainsString('<p>CONTENT</p>', $html);
        $this->assertNoCdnNoInlineHandlers($html);
    }

    public function testThemeTokensNonceAttributesAndInlineThemeBoot(): void
    {
        $request = new Request([], [], ['_route' => 'x', 'csp_nonce' => 'abc']);
        $request->setLocale('es');
        $twig = PageContractTwig::create(['child' => self::CHILD], [
            'classes' => ['body' => 'app-body', 'page' => 'app-page'],
            'attributes' => ['document' => ['data-app' => 'beacon'], 'content' => ['data-turbo' => 'false']],
            'tokens' => ['primary' => 'var(--moss)'],
            'bundles' => ['NowoXBundle' => ['classes' => ['content' => 'x-content']]],
        ], request: $request);

        $html = $twig->render('child', ['layout' => '@NowoUiKit/layout/admin.html.twig', 'nowo_ui_bundle' => 'NowoXBundle']);

        self::assertStringContainsString('<html lang="es" data-nowo-ui-host-tokens data-app="beacon">', $html);
        self::assertStringContainsString('<script nonce="abc" data-nowo-ui-theme-boot>', $html);
        self::assertStringContainsString("<style nonce=\"abc\" data-nowo-ui-theme-tokens>\n:root {\n  --nowo-ui-primary: var(--moss);\n}\n</style>", $html);
        self::assertStringContainsString('<body class="nowo-ui-body app-body">', $html);
        self::assertStringContainsString('class="nowo-ui-page app-page nowo-ui-page--admin"', $html);
        self::assertStringContainsString('<main class="nowo-ui-content x-content" id="nowo-ui-content" data-nowo-ui-bundle="NowoXBundle" data-turbo="false">', $html);
        self::assertStringContainsString('<script src="/bundles/nowouikit/js/nowo-ui-confirm.js" defer nonce="abc"></script>', $html);
    }

    public function testAppearanceIsPrintedAfterTheThemeTokens(): void
    {
        $request = new Request([], [], ['_route' => 'x', 'csp_nonce' => 'abc']);
        $twig = PageContractTwig::create(['child' => self::CHILD], ['tokens' => ['primary' => 'var(--moss)']], request: $request);
        $twig->addFunction(new \Twig\TwigFunction('nowo_ui_appearance_style', static fn (?string $nonce = null): string => '<style nonce="'.$nonce.'" data-nowo-ui-appearance>:root { --nowo-ui-primary: #c00; }</style>', ['is_safe' => ['html']]));

        $html = $twig->render('child', ['layout' => '@NowoUiKit/layout/admin.html.twig']);

        self::assertMatchesRegularExpression('#<link rel="stylesheet" href="/bundles/nowouikit/css/nowo-ui.css">\s*<style nonce="abc" data-nowo-ui-theme-tokens>.*?</style>\s*<style nonce="abc" data-nowo-ui-appearance>#s', $html);
    }

    public function testThemeBootCanBeDisabledAndDocumentTitleVariable(): void
    {
        $html = PageContractTwig::create(['child' => "{% extends '@NowoUiKit/layout/document.html.twig' %}{% block nowo_ui_body %}<p>B</p>{% endblock %}"])
            ->render('child', ['nowo_ui_theme_boot' => false, 'nowo_ui_document_title' => 'T']);

        self::assertStringNotContainsString('theme-boot', $html);
        self::assertStringContainsString('<title>T</title>', $html);
        self::assertStringContainsString('<p>B</p>', $html);
    }

    private function assertNoCdnNoInlineHandlers(string $html): void
    {
        self::assertSame(0, preg_match('#(?:src|href)="(?:https?:)?//#', $html), 'CDN / absolute asset URL');
        self::assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html), 'inline event handler');
        self::assertStringNotContainsString('confirm(', $html);
    }
}
