<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use PHPUnit\Framework\TestCase;

/**
 * 2.63 — three dialog additions, all opt-in (the 2.62 markup and layout stay): `phone: 'sheet'` (full-screen phone
 * sheet of the confirm_dialog anatomy), `header_steps` (steps indicator inside the anatomy header) and the default
 * divider between `.nowo-ui-section` blocks of a dialog body.
 */
final class DialogSheetStepsSections263Test extends TestCase
{
    private const ANATOMY = <<<'TWIG'
{% embed nowo_ui_partial('confirm_dialog') with opts %}
    {% block body %}<p>Body</p>{% endblock %}
    {% block footer %}<button type="button" data-confirm-dialog-close>Cancel</button>{% endblock %}
{% endembed %}
TWIG;

    private const STEPS = [
        ['label' => 'File', 'status' => 'done'],
        ['label' => 'Columns', 'status' => 'current'],
        ['label' => 'Result'],
    ];

    /**
     * @param array<string, mixed> $opts
     */
    private function render(array $opts, string $template = self::ANATOMY): string
    {
        return PageContractTwig::create(['t' => $template])->render('t', ['opts' => $opts + ['anatomy' => true, 'title' => 'Import', 'dialog_id' => 'imp']]);
    }

    public function testSheetIsAnExplicitOptInOfTheAnatomy(): void
    {
        self::assertStringContainsString('nowo-ui-confirm-dialog nowo-ui-confirm-dialog--sheet nowo-ui-confirm-dialog--anatomy', $this->render(['phone' => 'sheet']));
        foreach ([[], ['phone' => 'inset'], ['phone' => 'full'], ['phone' => true], ['phone' => 'SHEET']] as $opts) {
            self::assertStringNotContainsString('--sheet', $this->render($opts), json_encode($opts, \JSON_THROW_ON_ERROR));
        }
        // Without the anatomy the option is ignored (a free panel has no header / footer to keep).
        $free = PageContractTwig::create(['t' => "{% embed nowo_ui_partial('confirm_dialog') with opts %}{% block form %}<p>x</p>{% endblock %}{% endembed %}"])->render('t', ['opts' => ['phone' => 'sheet']]);
        self::assertStringNotContainsString('--sheet', $free);
        // A `phone` in an inherited context cannot switch it on either: an explicit anatomy: true is required.
        self::assertStringNotContainsString('--sheet', $free);
    }

    public function testSheetCombinesWithWideAndModifier(): void
    {
        $html = $this->render(['phone' => 'sheet', 'size' => 'wide', 'dialog_modifier' => 'x-own']);
        self::assertStringContainsString('nowo-ui-confirm-dialog--wide nowo-ui-confirm-dialog--sheet nowo-ui-confirm-dialog--anatomy x-own', $html);
    }

    public function testHeaderStepsOptionRendersTheStepsPartialInTheHeader(): void
    {
        $html = $this->render(['header_steps' => self::STEPS, 'header_steps_label' => 'Import steps']);
        self::assertMatchesRegularExpression('#<header[^>]*>.*<div class="nowo-ui-dialog__steps"><ol class="nowo-ui-steps nowo-ui-steps--compact" aria-label="Import steps">.*</ol></div>\s*</header>#s', $html);
        self::assertStringContainsString('aria-current="step"', $html);
        // After the close button in the DOM: title, close, then the steps.
        self::assertLessThan(strpos($html, 'nowo-ui-dialog__steps'), strpos($html, 'nowo-ui-dialog__close'));
        self::assertSame(1, substr_count($html, '<ol '));
    }

    public function testHeaderStepsBlockOverridesTheOption(): void
    {
        $html = $this->render([], "{% embed nowo_ui_partial('confirm_dialog') with opts %}{% block header_steps %}<p class=\"own-steps\">Step 2 of 4</p>{% endblock %}{% block body %}b{% endblock %}{% endembed %}");
        self::assertStringContainsString('<div class="nowo-ui-dialog__steps"><p class="own-steps">Step 2 of 4</p></div>', $html);
    }

    public function testNoStepsKeepsTheHeaderMarkupOfTheLastRelease(): void
    {
        foreach ([[], ['header_steps' => []], ['header_steps' => 'nope'], ['header_steps' => null]] as $opts) {
            $html = $this->render($opts);
            self::assertStringNotContainsString('nowo-ui-dialog__steps', $html);
            self::assertMatchesRegularExpression('#</button>\s*</header>#', $html);
        }
        // Inherited context only: no anatomy, no steps.
        $free = PageContractTwig::create(['t' => "{% embed nowo_ui_partial('confirm_dialog') with opts %}{% block form %}<p>x</p>{% endblock %}{% endembed %}"])->render('t', ['opts' => ['header_steps' => self::STEPS]]);
        self::assertStringNotContainsString('<ol', $free);
    }

    public function testStepsStayAnUnsafeHrefFreeList(): void
    {
        $html = $this->render(['header_steps' => [['label' => 'File', 'status' => 'done', 'href' => 'javascript:alert(1)'], ['label' => 'Next', 'status' => 'current']]]);
        self::assertStringNotContainsString('javascript:', $html);
    }

    public function testStylesheetsHaveTheThreeAdditions(): void
    {
        foreach (['nowo-ui.unlayered.css', 'nowo-ui.css'] as $file) {
            $css = (string) file_get_contents(\dirname(__DIR__, 3).'/src/Resources/public/css/'.$file);
            // Sheet: only at phone width, the whole screen, safe areas on the header, the body and the footer.
            self::assertMatchesRegularExpression('/@media \(max-width: 39\.99rem\) \{\s*\.nowo-ui-confirm-dialog\.nowo-ui-confirm-dialog--sheet\.nowo-ui-confirm-dialog--anatomy\[open\] \{[^}]*height: 100dvh;[^}]*margin: 0;[^}]*border-radius: 0;/', $css, $file);
            foreach (['inset-top', 'inset-bottom', 'inset-left', 'inset-right'] as $edge) {
                self::assertStringContainsString('env(safe-area-inset-'.str_replace('inset-', '', $edge).', 0px)', $css, $file.' '.$edge);
            }
            self::assertMatchesRegularExpression('/\.nowo-ui-confirm-dialog--sheet > \.nowo-ui-dialog__footer \{[^}]*padding-bottom: calc\(1rem \+ env\(safe-area-inset-bottom, 0px\)\);/', $css, $file);
            // Steps in the header: own row, the list is compact on phones.
            self::assertMatchesRegularExpression('/\.nowo-ui-dialog__header:has\(> \.nowo-ui-dialog__steps\) \{\s*flex-wrap: wrap;/', $css, $file);
            self::assertMatchesRegularExpression('/\.nowo-ui-dialog__steps \{\s*flex: 0 0 100%;/', $css, $file);
            // Sections: a divider and the row gap between two non-card sections of a dialog body.
            self::assertMatchesRegularExpression('/\.nowo-ui-dialog__body \.nowo-ui-section:not\(\.nowo-ui-card\) \+ \.nowo-ui-section:not\(\.nowo-ui-card\) \{[^}]*padding-top: var\(--nowo-ui-form-row-gap, 1rem\);[^}]*border-top: 1px solid var\(--nowo-ui-border\);/', $css, $file);
            self::assertDoesNotMatchRegularExpression('/^[^\/\n]*(url\(http|@import|<style)/m', $css, $file.': no CDN');
        }
    }
}
