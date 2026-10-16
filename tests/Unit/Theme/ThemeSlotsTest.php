<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Theme;

use Nowo\UiKitBundle\Theme\ThemeSlots;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

final class ThemeSlotsTest extends TestCase
{
    /** Slot => equivalent 1.x macro call (scaffold markup and macros paint the same classes). */
    private const MACRO_PARITY = [
        'page_header' => 'ui.page_header()',
        'toolbar' => 'ui.toolbar()',
        'btn_primary' => "ui.btn('primary')",
        'btn_secondary' => "ui.btn('secondary')",
        'btn_danger' => "ui.btn('danger')",
        'burger' => 'ui.burger()',
        'table_wrap' => 'ui.table_wrap()',
        'table' => 'ui.table()',
        'row_actions' => 'ui.row_actions()',
        'badge' => 'ui.badge()',
        'badge_success' => "ui.badge('success')",
        'badge_warning' => "ui.badge('warning')",
        'badge_danger' => "ui.badge('danger')",
        'badge_info' => "ui.badge('info')",
        'badge_neutral' => "ui.badge('neutral')",
        'filters' => 'ui.filters()',
        'input' => 'ui.input()',
        'pagination' => 'ui.pagination_wrap()',
        'empty' => 'ui.empty()',
        'flash_success' => "ui.flash('success')",
        'flash_error' => "ui.flash('error')",
        'flash_warning' => "ui.flash('warning')",
        'flash_info' => "ui.flash('info')",
        'card' => 'ui.card()',
        'card_header' => 'ui.card_header()',
        'card_body' => 'ui.card_body()',
        'tabs' => 'ui.tab_list()',
        'tab' => 'ui.tab_item(false)',
        'tab_active' => 'ui.tab_item(true)',
        'shell' => 'ui.shell()',
    ];

    /**
     * @return iterable<string, array{string}>
     */
    public static function frameworks(): iterable
    {
        foreach (['bootstrap5', 'bootstrap', 'bootstrap4', 'tabler', 'tailwind', 'foundation', 'custom', 'none'] as $fw) {
            yield $fw => [$fw];
        }
    }

    #[DataProvider('frameworks')]
    public function testSlotDefaultsMatchTheMacros(string $framework): void
    {
        foreach (self::MACRO_PARITY as $slot => $macro) {
            $expected = $this->tokens($this->renderMacro($macro, $framework));
            $actual = $this->tokens(ThemeSlots::semantic($slot).' '.ThemeSlots::defaults($slot, $framework));
            sort($expected);
            sort($actual);
            self::assertSame($expected, $actual, $slot.' / '.$framework);
        }
    }

    public function testRequiredVocabularyIsDefined(): void
    {
        foreach (['page_header', 'page_title', 'page_intro', 'toolbar', 'btn', 'btn_primary', 'btn_secondary', 'btn_danger', 'btn_sm', 'table_wrap', 'table', 'table_head', 'table_row', 'table_cell', 'row_actions', 'action', 'badge', 'badge_success', 'filters', 'filters_actions', 'input', 'pagination', 'pagination_link', 'pagination_active', 'pagination_info', 'empty', 'card', 'tabs', 'tab', 'modal', 'confirm', 'flash', 'flash_success', 'flash_error', 'form', 'form_actions', 'section', 'detail_list', 'kpi', 'wizard_steps', 'auth_panel', 'toasts'] as $slot) {
            self::assertTrue(ThemeSlots::has($slot), $slot);
            self::assertStringStartsWith('nowo-ui-', ThemeSlots::semantic($slot), $slot);
        }
        self::assertSame(array_keys(ThemeSlots::SLOTS), ThemeSlots::names());
        self::assertFalse(ThemeSlots::has('nope'));
    }

    public function testFamiliesAndBootstrap4Fallback(): void
    {
        self::assertSame('bootstrap', ThemeSlots::family('tabler'));
        self::assertSame('bootstrap4', ThemeSlots::family('bootstrap4'));
        self::assertNull(ThemeSlots::family('custom'));
        self::assertSame('', ThemeSlots::defaults('table', 'none'));
        self::assertSame('table-responsive', ThemeSlots::defaults('table_wrap', 'bootstrap4'));
        self::assertSame('badge badge-success ml-2', ThemeSlots::defaults('badge_success', 'bootstrap4'));
        self::assertSame('', ThemeSlots::defaults('table_head', 'bootstrap4'));
    }

    /**
     * Contrast: a Bootstrap badge variant sets text and background together (`text-bg-*`, 5.2+), never a bare
     * `bg-*` whose text colour would come from the semantic nowo-ui-badge--* palette (dark text on a solid fill).
     */
    public function testBootstrapBadgeVariantsPairTextAndBackground(): void
    {
        foreach (['badge', 'badge_success', 'badge_warning', 'badge_danger', 'badge_info', 'badge_neutral'] as $slot) {
            $defaults = ThemeSlots::defaults($slot, 'bootstrap5');
            self::assertMatchesRegularExpression('/(^| )text-bg-(success|warning|danger|info|light)( |$)/', $defaults, $slot);
            self::assertDoesNotMatchRegularExpression('/(^| )bg-/', $defaults, $slot);
        }
        self::assertSame('badge text-bg-success ms-2', ThemeSlots::defaults('badge_success', 'tabler'));
    }

    public function testUnknownSlotThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown UiKit theme slot "btn_moss"');
        ThemeSlots::semantic('btn_moss');
    }

    public function testPartialsPointAtExistingTemplates(): void
    {
        $views = \dirname(__DIR__, 3).'/src/Resources/views/';
        foreach (ThemeSlots::PARTIALS as $name => $template) {
            self::assertFileExists($views.substr($template, \strlen('@NowoUiKitBundle/')), $name);
        }
        self::assertSame(array_keys(ThemeSlots::PARTIALS), ThemeSlots::partialNames());
    }

    public function testTailwindDefaultsCarryNoPalette(): void
    {
        foreach (ThemeSlots::names() as $slot) {
            self::assertSame(0, preg_match('/(?<![\w-])(?:bg|text|border|ring)-(?:slate|gray|red|blue|green|white|black)|rounded/', ThemeSlots::defaults($slot, 'tailwind')), $slot);
        }
    }

    /**
     * @return list<string>
     */
    private function tokens(string $classes): array
    {
        return array_values(array_unique(preg_split('/\s+/', trim($classes)) ?: []));
    }

    private function renderMacro(string $expression, string $framework): string
    {
        $fs = new FilesystemLoader();
        $fs->addPath(\dirname(__DIR__, 3).'/src/Resources/views', 'NowoUiKitBundle');
        $twig = new Environment(new ChainLoader([new ArrayLoader(['t.twig' => "{% import '@NowoUiKitBundle/macros/ui.html.twig' as ui %}{{ {$expression} }}"]), $fs]));
        $twig->addGlobal('nowo_ui_kit_css_framework', $framework);

        return $twig->render('t.twig');
    }
}
