<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Test;

use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\DashboardPage;
use Nowo\UiKitBundle\View\FilterField;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;

/**
 * 2.3: every text of the list filters is translated exactly once (FilterField placeholder, the "Any" option), with a
 * translating form domain and with `translation_domain: false`, and the strict missing-message rule of the contract
 * test catches double translations (with its opt-out hooks).
 */
final class FilterTranslationContractTest extends PageContractTestCase
{
    private const DOMAIN = 'NowoContractFixtureBundle';

    /** @var list<string> */
    private static array $ignored = [];

    private static bool $strict = true;

    /** @var array<string, string> */
    private static array $extraTemplates = [];

    public static function pageFixtures(): iterable
    {
        yield 'domain' => self::fixture(self::DOMAIN, new Label('fixture.filter.search_placeholder', self::DOMAIN));
        yield 'kit label in another domain' => self::fixture(self::DOMAIN, Label::kit('filters.search_placeholder'));
        yield 'literal placeholder' => self::fixture(self::DOMAIN, 'Name…');
        yield 'pre-translated form' => self::fixture(false, new Label('fixture.filter.search_placeholder', self::DOMAIN));
    }

    public function testPlaceholderAndAnyAreTranslatedOnce(): void
    {
        $html = $this->renderPage(self::fixture(self::DOMAIN, new Label('fixture.filter.search_placeholder', self::DOMAIN)))->html;
        self::assertStringContainsString('placeholder="Name or e-mail"', $html);
        self::assertStringContainsString('<option value="">Any</option>', $html);
        self::assertStringContainsString('>Search</label>', $html);
        self::assertStringContainsString('>Active</option>', $html);

        $literal = $this->renderPage(self::fixture(self::DOMAIN, 'Name…'));
        self::assertStringContainsString('placeholder="Name…"', $literal->html);
        self::assertSame([], $literal->missingTranslations);

        $false = $this->renderPage(self::fixture(false, Label::kit('filters.search_placeholder')))->html;
        self::assertStringContainsString('placeholder="Search…"', $false);
        self::assertStringContainsString('<option value="">Any</option>', $false);
    }

    public function testDoubleTranslationIsCaught(): void
    {
        $page = $this->renderPage($this->doubleTranslated());
        self::assertSame([self::DOMAIN.':Name or e-mail'], $page->missingTranslations);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('translation keys missing from the catalogues: '.self::DOMAIN.':Name or e-mail');
        $this->assertNoRawTranslationKeys($page);
    }

    public function testIgnoredAndNonStrictMissingTranslations(): void
    {
        try {
            foreach ([[self::DOMAIN.':Name or e-mail'], ['Name or e-mail'], ['/^NowoContract\w+:Name /']] as $rules) {
                self::$ignored = $rules;
                self::assertSame([], $this->renderPage($this->doubleTranslated())->missingTranslations);
            }
            self::$ignored = ['/'];
            self::assertNotSame([], $this->renderPage($this->doubleTranslated())->missingTranslations);
            self::$ignored = [];
            self::$strict = false;
            self::assertSame([], $this->renderPage($this->doubleTranslated())->missingTranslations);
        } finally {
            self::$ignored = [];
            self::$strict = true;
        }
    }

    protected static function templatePaths(): array
    {
        return ['NowoContractFixture' => \dirname(__DIR__, 2).'/Fixtures/page-contract/views'];
    }

    protected static function inlineTemplates(): array
    {
        return self::$extraTemplates;
    }

    protected static function translationDirs(): array
    {
        return [\dirname(__DIR__, 2).'/Fixtures/page-contract/translations'];
    }

    protected static function ignoredMissingTranslations(): array
    {
        return self::$ignored;
    }

    protected static function strictMissingTranslations(): bool
    {
        return self::$strict;
    }

    private function doubleTranslated(): PageFixture
    {
        // The 2.2 bug: an already translated text handed to a form theme that translates it again
        self::$extraTemplates = ['@NowoContractFixture/bad/double.html.twig' => "{% extends '@NowoUiKit/page/dashboard.html.twig' %}{% block nowo_ui_dashboard_cards %}{{ form_widget(form.q, {attr: {placeholder: 'fixture.filter.search_placeholder'|trans({}, '".self::DOMAIN."')}}) }}{% endblock %}"];

        return new PageFixture('@NowoContractFixture/bad/double.html.twig', ['page' => new DashboardPage(new PageHeader('Bad')), 'form' => self::form(self::DOMAIN)]);
    }

    private static function fixture(string|false $domain, Label|string $placeholder): PageFixture
    {
        return new PageFixture('@NowoUiKit/page/list.html.twig', ['page' => new ListPage(
            new PageHeader(new Label('fixture.items', self::DOMAIN)),
            [new Column('name', new Label('fixture.item', self::DOMAIN))],
            [['name' => 'Alpha']],
            self::form($domain),
            filterFields: [new FilterField('q', $placeholder, 'lg')],
            filtersSubmit: new Action('filter', new Label('fixture.filter.apply', self::DOMAIN)),
        )]);
    }

    private static function form(string|false $domain): FormView
    {
        return Forms::createFormFactoryBuilder()->getFormFactory()
            ->createNamedBuilder('filters', FormType::class, null, ['method' => 'GET', 'translation_domain' => $domain])
            ->add('q', SearchType::class, ['required' => false, 'label' => false === $domain ? 'Search' : 'fixture.filter.search'])
            ->add('status', ChoiceType::class, ['required' => false, 'label' => false === $domain ? 'Status' : 'fixture.filter.status', 'choices' => false === $domain ? ['Active' => 'a'] : ['fixture.status.active' => 'a', 'fixture.status.inactive' => 'i']])
            ->getForm()->createView();
    }
}
