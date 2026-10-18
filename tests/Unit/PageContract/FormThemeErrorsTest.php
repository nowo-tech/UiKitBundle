<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Forms;

/**
 * UiKit form theme 2.3: `input_invalid` on the widget of a field with errors (+ aria-invalid), messages in the
 * `form_error` slot, root errors as a plain list; the PasswordToggle bridge (`input` slot on the toggle's input).
 */
final class FormThemeErrorsTest extends TestCase
{
    private const THEME = '@NowoUiKit/form/nowo_ui_layout.html.twig';

    public function testInvalidFieldsAndErrorMessages(): void
    {
        $form = PageContractTwig::forms()->createNamedBuilder('item', FormType::class)
            ->add('name', TextType::class, ['required' => false])
            ->add('notes', TextareaType::class, ['required' => false])
            ->add('kind', ChoiceType::class, ['required' => false, 'choices' => ['A' => 'a']])
            ->add('ok', TextType::class, ['required' => false])
            ->getForm();
        foreach (['name', 'notes', 'kind'] as $field) {
            $form->get($field)->addError(new FormError('Bad '.$field));
        }
        $form->addError(new FormError('Global problem'));

        foreach (['bootstrap5' => ['nowo-ui-input form-control nowo-ui-input--invalid is-invalid', 'nowo-ui-form-error invalid-feedback d-block'], 'none' => ['nowo-ui-input nowo-ui-input--invalid', 'nowo-ui-form-error']] as $framework => [$input, $error]) {
            $twig = PageContractTwig::create(['t' => "{% form_theme form '".self::THEME."' %}{{ form_errors(form) }}{{ form_widget(form) }}"], ['attributes' => ['form_error' => ['data-x' => '1']]], $framework);
            $html = $twig->render('t', ['form' => $form->createView()]);

            self::assertStringContainsString('<input type="text" id="item_name" name="item[name]" aria-describedby="item_name_error1" aria-invalid="true" class="'.$input.'" />', $html, $framework);
            self::assertStringContainsString('<ul class="'.$error.'" data-x="1"><li id="item_name_error1">Bad name</li></ul>', $html, $framework);
            self::assertMatchesRegularExpression('#<textarea id="item_notes" name="item\[notes\]" aria-describedby="item_notes_error1" aria-invalid="true" class="nowo-ui-input nowo-ui-input--full[^"]* nowo-ui-input--invalid[^"]*">#', $html, $framework);
            self::assertStringContainsString('<select id="item_kind" name="item[kind]" aria-describedby="item_kind_error1" aria-invalid="true" class="'.$input.'">', $html, $framework);
            self::assertStringContainsString('<ul class="nowo-ui-form-errors__list"><li id="item_error1">Global problem</li></ul>', $html, $framework);
            self::assertStringContainsString('<input type="text" id="item_ok" name="item[ok]" class="nowo-ui-input'.('none' === $framework ? '' : ' form-control').'" />', $html, $framework);
        }
    }

    public function testPasswordToggleBridge(): void
    {
        $toggle = new class extends AbstractType {
            public function getParent(): string
            {
                return PasswordType::class;
            }

            public function getBlockPrefix(): string
            {
                return 'toggle_password';
            }
        };
        $factory = Forms::createFormFactoryBuilder()->addType($toggle)->getFormFactory();
        $form = $factory->createNamedBuilder('login', FormType::class)->add('password', $toggle::class, ['required' => false])->add('name', TextType::class, ['required' => false])->getForm();
        $form->get('password')->addError(new FormError('Wrong'));
        // Stand-in for @NowoPasswordToggleBundle/Form/toggle_password_widget.html.twig
        $templates = [
            'toggle.html.twig' => '{% block toggle_password_widget %}<x-toggle><input type="password" {{ block(\'widget_attributes\') }}/></x-toggle>{% endblock %}',
            'bridged' => "{% form_theme form 'toggle.html.twig' '".self::THEME."' %}{{ form_widget(form) }}",
            'unbridged' => "{% form_theme form 'toggle.html.twig' %}{{ form_widget(form) }}",
        ];

        $html = PageContractTwig::create($templates, [], 'bootstrap5')->render('bridged', ['form' => $form->createView()]);
        self::assertStringContainsString('<x-toggle><input type="password" id="login_password" name="login[password]" aria-describedby="login_password_error1" aria-invalid="true" class="nowo-ui-input form-control nowo-ui-input--invalid is-invalid"/></x-toggle>', $html);
        self::assertStringContainsString('<input type="text" id="login_name" name="login[name]" class="nowo-ui-input form-control" />', $html);

        // Without the UiKit theme the toggle input keeps the plain form_div_layout attributes
        $html = PageContractTwig::create($templates, [], 'bootstrap5')->render('unbridged', ['form' => $form->createView()]);
        self::assertStringContainsString('<x-toggle><input type="password" id="login_password" name="login[password]" aria-describedby="login_password_error1" aria-invalid="true"/></x-toggle>', $html);
    }
}
