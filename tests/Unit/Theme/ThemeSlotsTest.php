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
        'btn_link' => "ui.btn('link')",
        'muted' => 'ui.muted()',
        'burger' => 'ui.burger()',
        'table_wrap' => 'ui.table_wrap()',
        'row_actions' => 'ui.row_actions()',
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
        'card_footer' => 'ui.card_footer()',
        'tabs' => 'ui.tab_list()',
        'tab' => 'ui.tab_item(false)',
        'tab_active' => 'ui.tab_item(true)',
        'shell' => 'ui.shell()',
        // 2.2: table (striped on Bootstrap), badge* (one subtle set, no margin utilities) and filters (grid) slot
        // defaults diverge on purpose from the 1.x macros, which keep their 1.x output
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
        self::assertSame('badge badge-success', ThemeSlots::defaults('badge_success', 'bootstrap4'));
        self::assertSame('', ThemeSlots::defaults('table_head', 'bootstrap4'));
    }

    /**
     * 2.2 badges: one subtle set on Bootstrap — every variant pairs a subtle background with its emphasis text and a
     * subtle border (never a bare solid `bg-*` / `text-bg-*` mixed with the semantic palette), no margin utilities;
     * info and primary follow the primary colour.
     */
    public function testBootstrapBadgesAreOneSubtleSet(): void
    {
        foreach (['badge_primary' => 'primary', 'badge_success' => 'success', 'badge_warning' => 'warning', 'badge_danger' => 'danger', 'badge_info' => 'primary'] as $slot => $tone) {
            self::assertSame('badge bg-'.$tone.'-subtle text-'.$tone.'-emphasis border border-'.$tone.'-subtle', ThemeSlots::defaults($slot, 'bootstrap5'), $slot);
        }
        foreach (['badge', 'badge_primary', 'badge_success', 'badge_warning', 'badge_danger', 'badge_info', 'badge_neutral'] as $slot) {
            foreach (['bootstrap5', 'bootstrap4', 'tailwind'] as $fw) {
                self::assertDoesNotMatchRegularExpression('/(^| )(ms|ml)-2( |$)|text-bg-/', ThemeSlots::defaults($slot, $fw), $slot.' / '.$fw);
            }
        }
        self::assertSame('badge bg-success-subtle text-success-emphasis border border-success-subtle', ThemeSlots::defaults('badge_success', 'tabler'));
        self::assertSame('nowo-ui-card__footer card-footer', ThemeSlots::semantic('card_footer').' '.ThemeSlots::defaults('card_footer', 'bootstrap4'));
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
