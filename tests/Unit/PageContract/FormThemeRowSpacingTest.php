<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\Theme\ThemeSlots;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
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

        self::assertStringContainsString('<div class="mb-3"><label for="item_name">', $html);
        self::assertMatchesRegularExpression('#<div class="nowo-ui-form-row nowo-ui-form-row--check mb-3">\s*<label class="nowo-ui-check form-check"><input type="checkbox" id="item_agree"#', $html);
        self::assertMatchesRegularExpression('#<div class="mb-3 nowo-ui-form-row nowo-ui-form-row--check">\s*<label class="nowo-ui-check form-check"><input type="checkbox" id="item_again"#', $html);
        self::assertStringContainsString('<div class="mb-3 wide"><label for="item_dup">', $html);
        self::assertSame(0, substr_count($html, 'mb-3 mb-3'));

        // custom / none: unchanged (no row class)
        $html = PageContractTwig::create($template, [], 'none')->render('t', ['form' => $form->createView()]);
        self::assertStringContainsString('<div><label for="item_name">', $html);
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
}
