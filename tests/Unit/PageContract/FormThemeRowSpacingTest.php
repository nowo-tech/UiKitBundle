<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\Theme\ThemeSlots;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\PercentType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;

/**
 * 2.15.4: on Bootstrap the UiKit form theme spaces text and checkbox rows alike (`form_row` / `form_row_check`
 * default `mb-3`, never twice), and file inputs get the `input` slot (`form-control`).
 */
final class FormThemeRowSpacingTest extends TestCase
{
    private const THEME = '@NowoUiKit/form/nowo_ui_layout.html.twig';

    public function testRowSlotDefaultsPerFramework(): void
    {
        foreach (['bootstrap5', 'bootstrap', 'tabler', 'bootstrap4'] as $framework) {
            self::assertSame('mb-3', ThemeSlots::defaults('form_row', $framework), $framework);
            self::assertSame('mb-3', ThemeSlots::defaults('form_row_check', $framework), $framework);
        }
        // Tailwind / Foundation / none: the form flex gap (nowo-ui.css) spaces the rows, as before.
        foreach (['tailwind', 'foundation', 'none', 'custom'] as $framework) {
            self::assertSame('', ThemeSlots::defaults('form_row', $framework), $framework);
            self::assertSame('', ThemeSlots::defaults('form_row_check', $framework), $framework);
        }
    }

    public function testBootstrapRowsAreSpacedOnce(): void
    {
        $form = PageContractTwig::forms()->createNamedBuilder('item', FormType::class)
            ->add('name', TextType::class, ['required' => false])
            ->add('agree', CheckboxType::class, ['required' => false])
            ->add('again', CheckboxType::class, ['required' => false, 'row_attr' => ['class' => 'mb-3']])
            ->add('dup', TextType::class, ['required' => false, 'row_attr' => ['class' => 'mb-3 wide']])
            ->getForm();
        $template = ['t' => "{% form_theme form '".self::THEME."' %}{{ form_row(form.name) }}{{ form_row(form.agree) }}{{ form_row(form.again) }}{{ form_row(form.dup) }}"];
        $html = PageContractTwig::create($template, [], 'bootstrap5')->render('t', ['form' => $form->createView()]);

        self::assertStringContainsString('<div class="nowo-ui-form-row mb-3"><label for="item_name">', $html);
        self::assertMatchesRegularExpression('#<div class="nowo-ui-form-row nowo-ui-form-row--check mb-3">\s*<label class="nowo-ui-check form-check"><input type="checkbox" id="item_agree"#', $html);
        self::assertMatchesRegularExpression('#<div class="mb-3 nowo-ui-form-row nowo-ui-form-row--check">\s*<label class="nowo-ui-check form-check"><input type="checkbox" id="item_again"#', $html);
        self::assertStringContainsString('<div class="mb-3 wide nowo-ui-form-row"><label for="item_dup">', $html);
        self::assertSame(0, substr_count($html, 'mb-3 mb-3'));

        // custom / none: no slot class, only the kit row class (2.66.2)
        $html = PageContractTwig::create($template, [], 'none')->render('t', ['form' => $form->createView()]);
        self::assertStringContainsString('<div class="nowo-ui-form-row"><label for="item_name">', $html);
        self::assertMatchesRegularExpression('#<div class="nowo-ui-form-row nowo-ui-form-row--check">\s*<label class="nowo-ui-check">#', $html);
    }

    public function testFileInputsGetTheInputSlot(): void
    {
        $form = PageContractTwig::forms()->createNamedBuilder('item', FormType::class)
            ->add('upload', FileType::class, ['required' => false])
            ->add('bad', FileType::class, ['required' => false])
            ->getForm();
        $form->get('bad')->addError(new FormError('Too big'));
        $template = ['t' => "{% form_theme form '".self::THEME."' %}{{ form_widget(form.upload) }}{{ form_widget(form.bad) }}"];

        $html = PageContractTwig::create($template, [], 'bootstrap5')->render('t', ['form' => $form->createView()]);
        self::assertStringContainsString('<input type="file" id="item_upload" name="item[upload]" class="nowo-ui-input form-control" />', $html);
        self::assertStringContainsString('<input type="file" id="item_bad" name="item[bad]" class="nowo-ui-input form-control nowo-ui-input--invalid is-invalid" aria-invalid="true" />', $html);

        $html = PageContractTwig::create($template, [], 'tailwind')->render('t', ['form' => $form->createView()]);
        self::assertStringContainsString('<input type="file" id="item_upload" name="item[upload]" class="nowo-ui-input block w-full max-w-xs border px-3 py-1.5 text-sm" />', $html);
    }

    public function testListFilterGridDropsTheRowMargin(): void
    {
        foreach (['nowo-ui.unlayered.css', 'nowo-ui.css'] as $file) {
            $css = (string) file_get_contents(\dirname(__DIR__, 3).'/src/Resources/public/css/'.$file);
            self::assertStringContainsString("form.nowo-ui-filters[data-nowo-ui-region] > .nowo-ui-filters__field.mb-3 {\n  margin-bottom: 0 !important;\n}", $css, $file);
        }
    }

    /**
     * 2.66.2 (regression of 2.62.0, #132): without a CSS framework every row stacks its label above its control. The
     * row carries `nowo-ui-form-row` once (never twice when the bundle passes it in row_attr) and nowo-ui.css makes
     * the label of a text row a block; checkbox / radio rows keep the box beside its text.
     */
    public function testTextRowsCarryTheKitRowClassAndTheCssStacksTheirLabel(): void
    {
        $form = PageContractTwig::forms()->createNamedBuilder('item', FormType::class)
            ->add('name', TextType::class, ['required' => false, 'row_attr' => ['class' => 'nowo-ui-form-row']])
            ->getForm();
        $template = ['t' => "{% form_theme form '".self::THEME."' %}{{ form_row(form.name) }}"];
        foreach (['none', 'custom', 'tailwind', 'bootstrap5'] as $framework) {
            $html = PageContractTwig::create($template, [], $framework)->render('t', ['form' => $form->createView()]);
            self::assertSame(1, substr_count($html, 'nowo-ui-form-row'), $framework);
        }

        foreach (['nowo-ui.unlayered.css', 'nowo-ui.css'] as $file) {
            $css = (string) file_get_contents(\dirname(__DIR__, 3).'/src/Resources/public/css/'.$file);
            self::assertStringContainsString(":where(.nowo-ui-form-row:not(.nowo-ui-form-row--check)) > :where(label) {\n  display: block;\n  inline-size: fit-content;\n  margin-block-end: var(--nowo-ui-form-label-gap, 0.5rem);\n}", $css, $file);
        }
    }

    /** 2.66.2: money and percent symbols sit in a kit input group, not as bare text between the label and the input. */
    public function testMoneyAndPercentWidgetsUseTheInputGroup(): void
    {
        $form = PageContractTwig::forms()->createNamedBuilder('item', FormType::class)
            ->add('total', MoneyType::class, ['required' => false, 'currency' => 'EUR'])
            ->add('rate', PercentType::class, ['required' => false])
            ->add('plain', PercentType::class, ['required' => false, 'symbol' => false])
            ->getForm();
        $template = ['t' => "{% form_theme form '".self::THEME."' %}{{ form_widget(form.total) }}|{{ form_widget(form.rate) }}|{{ form_widget(form.plain) }}"];
        $html = PageContractTwig::create($template, [], 'custom')->render('t', ['form' => $form->createView()]);
        [$total, $rate, $plain] = explode('|', $html);

        self::assertMatchesRegularExpression('#^<div class="nowo-ui-input-group">(<span class="nowo-ui-input-group__addon">€</span>)?<input type="text" id="item_total"[^>]*class="nowo-ui-input"[^>]*/>(<span class="nowo-ui-input-group__addon">€</span>)?</div>$#', trim($total));
        self::assertSame(1, substr_count($total, 'nowo-ui-input-group__addon'));
        self::assertMatchesRegularExpression('#^<div class="nowo-ui-input-group"><input type="text" id="item_rate"[^>]*/><span class="nowo-ui-input-group__addon">%</span>\s*</div>$#', trim($rate));
        self::assertStringNotContainsString('nowo-ui-input-group', $plain);
        self::assertStringContainsString('type="text" id="item_plain"', $plain);
    }
}
