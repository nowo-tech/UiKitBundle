<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\Theme\ThemeSlots;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Slots `code` (inline `<code>`) and `code_block` (`<pre>` payloads / JSON): semantic class, framework defaults,
 * host classes / attributes, and the kit CSS (monospace token, scrolling block).
 */
final class CodeSlotsTest extends TestCase
{
    private const TEMPLATE = "<code class=\"{{ nowo_ui_class('code', bundle) }}\"{{ nowo_ui_attrs('code', bundle) }}>id</code><pre class=\"{{ nowo_ui_class('code_block', bundle) }}\"{{ nowo_ui_attrs('code_block', bundle) }}><code>{{ payload|json_encode }}</code></pre>";

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function frameworks(): iterable
    {
        yield 'none' => ['none', 'nowo-ui-code', 'nowo-ui-code-block'];
        yield 'bootstrap5' => ['bootstrap5', 'nowo-ui-code', 'nowo-ui-code-block border p-3'];
        yield 'bootstrap4' => ['bootstrap4', 'nowo-ui-code', 'nowo-ui-code-block border p-3'];
        yield 'tailwind' => ['tailwind', 'nowo-ui-code font-mono text-sm', 'nowo-ui-code-block font-mono text-sm p-3 border'];
        yield 'foundation' => ['foundation', 'nowo-ui-code', 'nowo-ui-code-block'];
    }

    #[DataProvider('frameworks')]
    public function testFrameworkDefaults(string $framework, string $code, string $block): void
    {
        $html = PageContractTwig::create(['t' => self::TEMPLATE], [], $framework)->render('t', ['bundle' => null, 'payload' => ['a' => 1]]);

        self::assertStringContainsString('<code class="'.$code.'">id</code>', $html);
        self::assertStringContainsString('<pre class="'.$block.'"><code>{&quot;a&quot;:1}</code></pre>', $html);
    }

    public function testHostClassesAttributesAndBundleExceptions(): void
    {
        $theme = [
            'classes' => ['code' => 'app-code', 'code_block' => 'app-pre'],
            'attributes' => ['code_block' => ['data-lang' => 'json']],
            'bundles' => ['NowoXBundle' => ['classes' => ['code_block' => 'x-pre'], 'attributes' => [], 'partials' => []]],
        ];
        $twig = PageContractTwig::create(['t' => self::TEMPLATE], $theme);

        $host = $twig->render('t', ['bundle' => null, 'payload' => []]);
        self::assertStringContainsString('<code class="nowo-ui-code app-code">id</code>', $host);
        self::assertStringContainsString('<pre class="nowo-ui-code-block border p-3 app-pre" data-lang="json">', $host);

        $bundle = $twig->render('t', ['bundle' => 'NowoXBundle', 'payload' => []]);
        self::assertStringContainsString('<pre class="nowo-ui-code-block border p-3 x-pre" data-lang="json">', $bundle);
    }

    public function testSlotsAreRegistered(): void
    {
        self::assertSame('nowo-ui-code', ThemeSlots::semantic('code'));
        self::assertSame('nowo-ui-code-block', ThemeSlots::semantic('code_block'));
        self::assertSame('', ThemeSlots::defaults('code', 'custom'));
    }

    public function testCssUsesMonospaceTokenAndScrollsTheBlock(): void
    {
        $css = (string) file_get_contents(PageContractTwig::root().'/src/Resources/public/css/nowo-ui.unlayered.css');

        self::assertStringContainsString('font-family: var(--nowo-ui-font-mono, ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace);', $css);
        self::assertSame(1, preg_match('/\.nowo-ui-code-block \{[^}]*max-height: var\(--nowo-ui-code-block-max-height, 32rem\);[^}]*overflow: auto;[^}]*white-space: pre;/', $css));
        self::assertSame(1, preg_match('/\.nowo-ui-code-block--wrap \{[^}]*white-space: pre-wrap;/', $css));
        self::assertSame(1, preg_match('/\.nowo-ui-code-block > code \{[^}]*font: inherit;/', $css));
        self::assertStringContainsString('background: var(--nowo-ui-code-bg, color-mix(in srgb, var(--nowo-ui-text) 7%, var(--nowo-ui-surface)));', $css);
    }
}
