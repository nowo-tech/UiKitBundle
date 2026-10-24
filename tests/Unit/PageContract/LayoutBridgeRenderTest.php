<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use PHPUnit\Framework\TestCase;

/**
 * 2.11 layout/_bridge.html.twig (re-audit OVR-09): a host layout extending its own shell `use`s the bridge blocks;
 * pages still override the canonical blocks, the wrapper is the `host_page` slot, the width the `page_width` slot.
 */
final class LayoutBridgeRenderTest extends TestCase
{
    private const SHELL = '<title>{% block title %}{% endblock %}</title><head>{% block stylesheets %}<link href="/shell.css">{% endblock %}</head>'
        .'<body><div class="{% block width %}w-full{% endblock %}">{% block app_flashes %}{% endblock %}{% block body %}{% endblock %}</div>{% block body_end %}{% endblock %}</body>';

    private const BRIDGE = "{% extends 'shell' %}{% use '@NowoUiKit/layout/_bridge.html.twig' %}"
        ."{% set nowo_ui_document_title_suffix = suffix|default('') %}"
        ."{% block title %}{{ block('nowo_ui_bridge_title') }}{% endblock %}"
        ."{% block stylesheets %}{{ parent() }}{{ block('nowo_ui_bridge_assets') }}{% endblock %}"
        ."{% block width %}{{ nowo_ui_class('page_width') }}{% endblock %}"
        ."{% block body %}{{ block('nowo_ui_bridge_body') }}{% endblock %}"
        ."{% block body_end %}{{ block('nowo_ui_bridge_end') }}{% endblock %}";

    private const PAGE = "{% extends 'bridge' %}{% block nowo_ui_document_title %}Doc{% endblock %}"
        .'{% block nowo_ui_head %}<meta name="h">{% endblock %}{% block nowo_ui_styles %}<link href="/page.css">{% endblock %}'
        .'{% block nowo_ui_navigation %}<nav>NAV</nav>{% endblock %}{% block nowo_ui_page_header %}<h1>HEAD</h1>{% endblock %}'
        .'{% block nowo_ui_content %}<p>CONTENT</p>{% endblock %}{% block nowo_ui_modals %}<dialog></dialog>{% endblock %}'
        .'{% block nowo_ui_scripts %}<script src="/p.js"></script>{% endblock %}';

    public function testTheBridgeMapsTheCanonicalBlocksOntoTheHostShell(): void
    {
        $twig = PageContractTwig::create(['shell' => self::SHELL, 'bridge' => self::BRIDGE, 'page' => self::PAGE], [
            'classes' => ['host_page' => 'app-page', 'page_width' => 'max-w-4xl'],
            'pages' => ['auth' => ['classes' => ['host_page' => 'app-auth', 'page_width' => 'max-w-md']]],
            'bundles' => ['NowoXBundle' => ['classes' => ['host_page' => 'x-page']]],
        ]);

        $html = $twig->render('page', ['suffix' => 'Beacon']);
        self::assertStringContainsString('<title>Doc — Beacon</title>', $html);
        self::assertMatchesRegularExpression('#<link href="/shell.css">\s*<meta name="h">\s*<link href="/page.css">#', $html);
        self::assertStringContainsString('<div class="nowo-ui-page-width max-w-4xl">', $html);
        self::assertMatchesRegularExpression('#<div class="nowo-ui-host-page app-page nowo-ui-host-page--page">\s*<nav>NAV</nav>\s*<h1>HEAD</h1>\s*<div class="nowo-ui-flash[^"]*"[^>]*>Saved.</div>\s*<p>CONTENT</p>\s*</div>#', $html);
        self::assertMatchesRegularExpression('#</div>\s*<dialog></dialog>\s*<script src="/p.js"></script>\s*</body>#', $html);

        $auth = $twig->render('page', ['nowo_ui_page_type' => 'auth', 'nowo_ui_bundle' => 'NowoXBundle', 'nowo_ui_document_title_separator' => '|', 'suffix' => 'B', 'nowo_ui_host_page_class' => 'panel']);
        self::assertStringContainsString('<title>Doc | B</title>', $auth);
        self::assertStringContainsString('<div class="nowo-ui-page-width max-w-md">', $auth);
        self::assertStringContainsString('<div class="nowo-ui-host-page x-page nowo-ui-host-page--auth panel" data-nowo-ui-bundle="NowoXBundle">', $auth);
    }

    public function testFlashesCanLiveInTheShellAndTheTitleHasNoSuffixByDefault(): void
    {
        $bridge = str_replace('{% block body %}', "{% block app_flashes %}<aside>{{ block('nowo_ui_bridge_flashes') }}</aside>{% endblock %}{% block nowo_ui_flashes %}<p>TOASTS</p>{% endblock %}{% block body %}", self::BRIDGE);
        $html = PageContractTwig::create(['shell' => self::SHELL, 'bridge' => $bridge, 'page' => self::PAGE])
            ->render('page', ['nowo_ui_bridge_flashes_inside' => false]);

        self::assertStringContainsString('<title>Doc</title>', $html);
        self::assertMatchesRegularExpression('#<aside>\s*<p>TOASTS</p>\s*</aside>#', $html);
        self::assertSame(1, substr_count($html, 'TOASTS'));
        self::assertStringNotContainsString('Saved.', $html);
    }
}
