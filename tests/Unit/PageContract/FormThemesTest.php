<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\View\FormPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\SettingsPage;
use Nowo\UiKitBundle\View\SettingsSection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormView;

/**
 * Per-page form themes (2.1): FormPage::formThemes and SettingsSection::formThemes are applied with form_theme
 * before the scaffold renders the form, so bundles keep their form theme without overriding nowo_ui_content.
 */
final class FormThemesTest extends TestCase
{
    private const THEME = '{% block text_widget %}<input data-bundle-theme name="{{ full_name }}">{% endblock %}';

    public function testFormPageAppliesItsFormThemes(): void
    {
        $twig = PageContractTwig::create(['bundle/form_theme.html.twig' => self::THEME]);

        $themed = $twig->render('@NowoUiKit/page/form.html.twig', ['page' => new FormPage(new PageHeader('Edit'), $this->form('item'), formThemes: ['bundle/form_theme.html.twig'])]);
        self::assertStringContainsString('<input data-bundle-theme name="item[name]">', $themed);

        // themes are keyed by the form's cache key (name + type) in the renderer: another form stays unthemed
        $plain = $twig->render('@NowoUiKit/page/form.html.twig', ['page' => new FormPage(new PageHeader('Edit'), $this->form('other'))]);
        self::assertStringNotContainsString('data-bundle-theme', $plain);
        self::assertStringContainsString('name="other[name]"', $plain);
    }

    public function testSettingsSectionsApplyTheirOwnFormThemes(): void
    {
        $twig = PageContractTwig::create(['bundle/form_theme.html.twig' => self::THEME]);
        $page = new SettingsPage(new PageHeader('Settings'), [
            new SettingsSection('general', 'General', form: $this->form('general'), formThemes: ['bundle/form_theme.html.twig']),
            new SettingsSection('mail', 'Mail', form: $this->form('mail')),
        ]);

        $html = $twig->render('@NowoUiKit/page/settings.html.twig', ['page' => $page]);

        self::assertStringContainsString('<input data-bundle-theme name="general[name]">', $html);
        self::assertStringNotContainsString('<input data-bundle-theme name="mail[name]">', $html);
        self::assertStringContainsString('name="mail[name]"', $html);
    }

    public function testThemesAreValidated(): void
    {
        self::assertSame(['a.html.twig'], (new SettingsSection('s', 'S', formThemes: new \ArrayIterator(['a.html.twig'])))->formThemes);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('FormPage formThemes: expected template names (non-empty strings), got string.');
        new FormPage(new PageHeader('X'), $this->form('x'), formThemes: [' ']);
    }

    public function testNonStringThemesAreRefused(): void
    {
        $this->expectExceptionMessage('SettingsSection formThemes: expected template names (non-empty strings), got int.');
        new SettingsSection('s', 'S', formThemes: [1]); // @phpstan-ignore argument.type
    }

    private function form(string $name): FormView
    {
        return PageContractTwig::forms()->createNamedBuilder($name, FormType::class)->add('name', TextType::class, ['required' => false])->getForm()->createView();
    }
}
