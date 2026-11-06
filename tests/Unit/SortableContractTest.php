<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit;

use Nowo\UiKitBundle\Theme\ThemeSlots;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `sortable` is behaviour only (no partial, no slot). It stays opt-in, ships as a built IIFE, and writes no HTML.
 * The behaviour itself is covered by nowo-ui-sortable.test.ts and the demo's e2e.
 */
final class SortableContractTest extends TestCase
{
    private const PUBLIC_DIR = __DIR__.'/../../src/Resources/public';

    public function testItIsNotInTheDefaultScripts(): void
    {
        self::assertNotContains('sortable', ThemeSlots::SCRIPTS);
    }

    public function testTheBuiltScriptExistsAndBuildsNoHtml(): void
    {
        $script = file_get_contents(self::PUBLIC_DIR.'/js/nowo-ui-sortable.js');

        self::assertIsString($script);
        self::assertStringContainsString('nowo-ui:sortable:move', $script);
        self::assertStringNotContainsString('innerHTML', $script);
        self::assertStringNotContainsString('insertAdjacentHTML', $script);
        self::assertStringNotContainsString('fetch(', $script);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function stylesheets(): iterable
    {
        yield 'unlayered' => ['nowo-ui.unlayered.css'];
        yield 'layered' => ['nowo-ui.css'];
    }

    #[DataProvider('stylesheets')]
    public function testADraggedItemLeavesTheFlowWhateverAHostRuleSays(string $file): void
    {
        $css = file_get_contents(self::PUBLIC_DIR.'/css/'.$file);

        self::assertIsString($css);
        self::assertMatchesRegularExpression('/\[data-nowo-ui-sortable-item\]\.is-dragging \{\s+position: absolute !important;/', $css);
        self::assertMatchesRegularExpression('/\[data-nowo-ui-sortable-handle\],\s+\.nowo-ui-sortable__handle \{[^}]*touch-action: none;/', $css);
    }
}
