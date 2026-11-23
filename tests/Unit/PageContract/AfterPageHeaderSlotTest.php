<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `nowo_ui_after_page_header`: an empty slot right after `nowo_ui_page_header` (before the flashes and
 * `nowo_ui_content`) in every layout that prints that sequence. Empty by default: the output is byte-identical
 * to the same layout without the block; filled, its markup lands between the page header and the content.
 */
final class AfterPageHeaderSlotTest extends TestCase
{
    private const SLOT = '{% block nowo_ui_after_page_header %}{% endblock %}';

    private const BODY = '{% block nowo_ui_page_header %}<h1>HEADER</h1>{% endblock %}{% block nowo_ui_content %}<p>CONTENT</p>{% endblock %}';

    /**
     * @return iterable<string, array{string, string}> layout file, child template source (`%s` = the layout)
     */
    public static function layouts(): iterable
    {
        $extends = '{% extends "%s" %}'.self::BODY;
        $bridge = '{% extends "base" %}{% use "%s" %}{% block body %}{{ block("nowo_ui_bridge_body") }}{% endblock %}'.self::BODY;

        yield 'admin' => ['layout/admin.html.twig', $extends];
        yield 'guest' => ['layout/guest.html.twig', $extends];
        yield 'status' => ['layout/status.html.twig', $extends];
        yield 'demo platform' => ['demo_platform/layout.html.twig', $extends];
        yield 'bridge' => ['layout/_bridge.html.twig', $bridge];
    }

    #[DataProvider('layouts')]
    public function testEmptyByDefaultIsByteIdentical(string $layout, string $child): void
    {
        $source = file_get_contents(PageContractTwig::root().'/src/Resources/views/'.$layout);
        self::assertIsString($source);
        self::assertSame(1, substr_count($source, self::SLOT), $layout.' declares the slot exactly once.');

        $now = $this->render($source, $child);
        $before = $this->render(str_replace(self::SLOT, '', $source), $child);

        self::assertNotSame('', $now);
        self::assertStringContainsString('<h1>HEADER</h1>', $now);
        self::assertSame($before, $now, $layout.': the empty slot changes the rendered HTML.');
    }

    #[DataProvider('layouts')]
    public function testFilledSlotRendersBetweenHeaderAndContent(string $layout, string $child): void
    {
        $source = file_get_contents(PageContractTwig::root().'/src/Resources/views/'.$layout);
        self::assertIsString($source);

        $html = $this->render($source, $child.'{% block nowo_ui_after_page_header %}<aside data-after-header>BAR</aside>{% endblock %}');

        self::assertMatchesRegularExpression('#<h1>HEADER</h1>\s*<aside data-after-header>BAR</aside>.*<p>CONTENT</p>#s', $html, $layout);
        self::assertSame(1, substr_count($html, 'data-after-header'));
    }

    private function render(string $layoutSource, string $child): string
    {
        $twig = PageContractTwig::create([
            'layout_under_test' => $layoutSource,
            'base' => '<html><body>{% block body %}{% endblock %}</body></html>',
            'child' => str_replace('%s', 'layout_under_test', $child),
        ]);

        return $twig->render('child', ['nowo_ui_document_title' => 'Doc']);
    }
}
