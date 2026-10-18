<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\View\AuthPage;
use Nowo\UiKitBundle\View\FormPage;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\PageHeader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormView;

/**
 * Auth scaffold 2.3: form themes, form attributes, document title, root form errors (form_errors slot), the
 * nowo_ui_auth_body / nowo_ui_auth_after_form blocks; FormPage::formAttributes.
 */
final class AuthScaffoldTest extends TestCase
{
    public function testFormThemesAttributesTitleAndRootErrors(): void
    {
        $twig = PageContractTwig::create(['bundle/auth_theme.html.twig' => '{% block password_widget %}<input data-auth-theme type="password" name="{{ full_name }}">{% endblock %}'], ['classes' => ['form_errors' => 'host-errors']], 'none');
        $page = new AuthPage(
            new Label('action.submit', 'NowoUiKitBundle'),
            $this->form('login', true),
            formThemes: ['bundle/auth_theme.html.twig'],
            formAttributes: ['class' => 'app-login', 'novalidate' => true, 'data-turbo' => 'false', 'data-null' => null],
            documentTitle: 'Sign in · Acme',
        );

        $html = $twig->render('@NowoUiKit/page/auth.html.twig', ['page' => $page]);

        self::assertStringContainsString('<title>Sign in · Acme</title>', $html);
        self::assertStringContainsString('<form name="login" method="post" class="nowo-ui-form app-login" data-nowo-ui-region="form" novalidate="novalidate" data-turbo="false">', $html);
        self::assertStringNotContainsString('data-null', $html);
        self::assertStringContainsString('<input data-auth-theme type="password" name="login[password]">', $html);
        self::assertMatchesRegularExpression('#<form[^>]*>\s*<div class="nowo-ui-form-errors host-errors" role="alert" data-nowo-ui-region="form-errors"><ul><li[^>]*>The CSRF token is invalid.</li></ul></div>\s*<div>#', $html);
        self::assertSame(1, substr_count($html, 'The CSRF token is invalid.'));

        // No root errors: no box
        $clean = $twig->render('@NowoUiKit/page/auth.html.twig', ['page' => new AuthPage('Sign in', $this->form('login2'))]);
        self::assertStringNotContainsString('nowo-ui-form-errors', $clean);
        self::assertStringContainsString('<title>Sign in</title>', $clean);
        self::assertStringContainsString('<form name="login2" method="post" class="nowo-ui-form" data-nowo-ui-region="form">', $clean);
    }

    public function testRootErrorsCanBeSkipped(): void
    {
        // A bundle base template that prints the root errors itself (AuthKit 1.27.0)
        $own = '{% block nowo_ui_auth_fields %}<div data-own>{{ form_errors(form) }}</div>{{ parent() }}{% endblock %}';
        $twig = PageContractTwig::create([
            'auth/own.html.twig' => "{% extends '@NowoUiKit/page/auth.html.twig' %}".$own,
            'auth/own_var.html.twig' => "{% extends '@NowoUiKit/page/auth.html.twig' %}{% set nowo_ui_auth_root_errors = false %}".$own,
        ], [], 'none');

        // 2.3 default: printed by the scaffold and by the bundle — twice
        self::assertSame(2, substr_count($twig->render('auth/own.html.twig', ['page' => new AuthPage('Sign in', $this->form('a', true))]), 'The CSRF token is invalid.'));

        // AuthPage::rootErrors = false
        $flag = $twig->render('auth/own.html.twig', ['page' => new AuthPage('Sign in', $this->form('b', true), rootErrors: false)]);
        self::assertSame(1, substr_count($flag, 'The CSRF token is invalid.'));
        self::assertStringNotContainsString('nowo-ui-form-errors', $flag);
        self::assertStringContainsString('<div data-own><ul><li', $flag);

        // nowo_ui_auth_root_errors = false in the extending template (no view-model change)
        $var = $twig->render('auth/own_var.html.twig', ['page' => new AuthPage('Sign in', $this->form('c', true))]);
        self::assertSame(1, substr_count($var, 'The CSRF token is invalid.'));
        self::assertStringNotContainsString('nowo-ui-form-errors', $var);
    }

    public function testBodyAndAfterFormBlocks(): void
    {
        $twig = PageContractTwig::create([
            'auth/after.html.twig' => "{% extends '@NowoUiKit/page/auth.html.twig' %}{% block nowo_ui_auth_after_form %}<p data-after>or use a passkey</p>{% endblock %}",
            'auth/body.html.twig' => "{% extends '@NowoUiKit/page/auth.html.twig' %}{% block nowo_ui_auth_body %}<p data-body>Check your inbox.</p>{% endblock %}",
        ], [], 'none');

        $after = $twig->render('auth/after.html.twig', ['page' => new AuthPage('Sign in', $this->form('login'))]);
        self::assertMatchesRegularExpression('#</form>\s*<p data-after>or use a passkey</p>\s*</div>#', $after);

        $body = $twig->render('auth/body.html.twig', ['page' => new AuthPage('Sign in', $this->form('login'))]);
        self::assertStringContainsString('<p data-body>Check your inbox.</p>', $body);
        self::assertStringNotContainsString('<form', $body);
    }

    public function testAttributesAreValidated(): void
    {
        self::assertSame(['a.html.twig'], (new AuthPage('T', formThemes: new \ArrayIterator(['a.html.twig'])))->formThemes);
        $this->expectExceptionMessage('Invalid or unsafe attribute "onsubmit"');
        new AuthPage('T', formAttributes: ['onsubmit' => 'x']);
    }

    public function testFormPageAttributesAreValidated(): void
    {
        $this->expectExceptionMessage('Invalid or unsafe attribute "onsubmit"');
        new FormPage(new PageHeader('T'), null, formAttributes: ['onsubmit' => 'x']);
    }

    public function testFormPageFormAttributes(): void
    {
        $twig = PageContractTwig::create([], [], 'bootstrap5');
        $html = $twig->render('@NowoUiKit/page/form.html.twig', ['page' => new FormPage(new PageHeader('Edit'), $this->form('item'), formAttributes: ['class' => 'app-form', 'novalidate' => true])]);

        self::assertStringContainsString('<form name="item" method="post" class="nowo-ui-form app-form" data-nowo-ui-region="form" novalidate="novalidate">', $html);
    }

    private function form(string $name, bool $errors = false): FormView
    {
        $form = PageContractTwig::forms()->createNamedBuilder($name, FormType::class)
            ->add('username', TextType::class, ['required' => false])
            ->add('password', PasswordType::class, ['required' => false])
            ->getForm();
        if ($errors) {
            $form->addError(new FormError('The CSRF token is invalid.'));
        }

        return $form->createView();
    }
}
