<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit;

use Nowo\UiKitBundle\Theme\ThemeSlots;
use PHPUnit\Framework\TestCase;

/**
 * 2.5: one spacing system for form rows — the form is a flex column with the row gap (no sibling margins that add to a
 * host gap), the actions row and labels have their own tokens, side-by-side fields a column gap.
 */
final class FormSpacingCssTest extends TestCase
{
    public function testFormRowsUseAFlexGapInBothStylesheets(): void
    {
        foreach (['nowo-ui.unlayered.css', 'nowo-ui.css'] as $file) {
            $css = self::css($file);
            self::assertStringContainsString(":where(form.nowo-ui-form, .nowo-ui-dialog__body form) {\n  display: flex;\n  flex-direction: column;\n  gap: var(--nowo-ui-form-row-gap, 1rem);\n}", $css, $file);
            self::assertStringNotContainsString(':where(form.nowo-ui-form, .nowo-ui-dialog__body form, .nowo-ui-dialog__body) > :where(div, fieldset) + :where(div, fieldset)', $css, $file.': no sibling margin on forms');
            self::assertStringContainsString('margin-top: calc(var(--nowo-ui-form-actions-gap, 1rem) - var(--nowo-ui-form-row-gap, 1rem));', $css, $file);
            self::assertStringContainsString(":where(form.nowo-ui-form, .nowo-ui-dialog__body form):has(> .mb-3) {
  gap: max(0px, calc(var(--nowo-ui-form-row-gap, 1rem) - 1rem));
}", $css, $file.': Bootstrap form-theme rows (mb-3) do not add to the gap');
            self::assertStringContainsString(":where(* + .nowo-ui-form__actions) {\n  margin-top: var(--nowo-ui-form-actions-gap, 1rem);", $css, $file);
            self::assertStringContainsString(':has(> .mb-3:nth-last-child(1 of :not([type="hidden"]))) {'."\n  margin-bottom: -1rem;", $css, $file);
            self::assertStringContainsString(':where(form.nowo-ui-form, .nowo-ui-dialog__body form) > :where(:not(fieldset)) > :where(:last-child) > :where(:last-child) {'."\n  margin-bottom: 0;", $css, $file);
            self::assertStringContainsString('margin-bottom: var(--nowo-ui-form-label-gap, 0.5rem);', $css, $file);
            self::assertStringContainsString('gap: var(--nowo-ui-form-row-gap, 1rem) var(--nowo-ui-form-column-gap, 1rem);', $css, $file);
        }
        self::assertStringContainsString('@layer nowo-ui {', self::css('nowo-ui.css'));
    }

    public function testActionsSlotHasNoMarginUtility(): void
    {
        foreach (['bootstrap5', 'bootstrap4', 'tailwind', 'foundation'] as $framework) {
            self::assertDoesNotMatchRegularExpression('/\bm[tby]?-\d/', ThemeSlots::defaults('form_actions', $framework), $framework);
        }
        self::assertSame('nowo-ui-form__columns', ThemeSlots::semantic('form_columns'));
    }

    private static function css(string $file): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2).'/src/Resources/public/css/'.$file);
    }
}
