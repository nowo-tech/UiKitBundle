<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\Theme\ThemeSlots;
use PHPUnit\Framework\TestCase;

/**
 * Wide-form inputs (input_wide / input_full) and muted dialog text (dialog_body_muted, confirm body modifier),
 * framework-free (2.1).
 */
final class WideFormAndMutedSlotsTest extends TestCase
{
    public function testInputWidthSlotsAreCompleteClassLists(): void
    {
        self::assertSame('nowo-ui-input nowo-ui-input--wide form-control', ThemeSlots::semantic('input_wide').' '.ThemeSlots::defaults('input_wide', 'bootstrap5'));
        self::assertStringNotContainsString('max-w-xs', ThemeSlots::defaults('input_wide', 'tailwind'));
        self::assertStringNotContainsString('max-w-', ThemeSlots::defaults('input_full', 'tailwind'));

        $html = PageContractTwig::create(['t' => "<input class=\"{{ nowo_ui_class('input_wide') }}\"><input class=\"{{ nowo_ui_class('input_full') }}\">"], ['classes' => ['input_full' => 'app-full']], 'none')->render('t');
        self::assertSame('<input class="nowo-ui-input nowo-ui-input--wide"><input class="nowo-ui-input nowo-ui-input--full app-full">', $html);
    }

    public function testMutedDialogBodyUsesItsSlot(): void
    {
        $html = PageContractTwig::create([], ['classes' => ['dialog_body_muted' => 'app-muted']])->render('@NowoUiKitBundle/partials/_dialog.html.twig', ['id' => 'd', 'title' => 'T', 'body' => 'x', 'body_muted' => true]);

        self::assertStringContainsString('<div class="nowo-ui-dialog__body nowo-ui-dialog__body--muted app-muted">x</div>', $html);
    }

    public function testCssIsFrameworkFree(): void
    {
        $css = (string) file_get_contents(PageContractTwig::root().'/src/Resources/public/css/nowo-ui.unlayered.css');

        self::assertSame(1, preg_match('/\.nowo-ui-input--wide \{\s*width: 100%;\s*max-width: 36rem;\s*\}/', $css));
        self::assertSame(1, preg_match('/\.nowo-ui-input--full \{\s*width: 100%;\s*max-width: none;\s*\}/', $css));
        self::assertLessThan(strpos($css, '.nowo-ui-input--wide {'), strpos($css, '.nowo-ui-input {'), 'modifiers come after the base rule');
        self::assertSame(1, preg_match('/\.nowo-ui-dialog__body--muted,\s*\.nowo-ui-confirm__body--muted \{\s*color: var\(--nowo-ui-muted\);/', $css));
    }
}
