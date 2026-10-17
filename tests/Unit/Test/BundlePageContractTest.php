<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Test;

use Nowo\UiKitBundle\Test\ContractStubExtension;
use Nowo\UiKitBundle\Test\FrameworkClasses;
use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;
use Nowo\UiKitBundle\Test\RenderedPage;
use Nowo\UiKitBundle\Test\StubHost;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\Crumb;
use Nowo\UiKitBundle\View\DashboardPage;
use Nowo\UiKitBundle\View\FormPage;
use Nowo\UiKitBundle\View\KpiTile;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\Pagination;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Forms;
use PHPUnit\Framework\Attributes\DataProvider;
use Twig\Environment;
use Twig\TwigFunction;

/**
 * The contract test as a UI bundle uses it: bundle views extending scaffolds with data blocks, a unique page
 * declared in TEMPLATES.md, bundle catalogues, a bundle Twig function, default css frameworks. Also proves each
 * assertion catches a violation (negative fixtures).
 */
final class BundlePageContractTest extends PageContractTestCase
{
    private const DOMAIN = 'NowoContractFixtureBundle';

    public static function pageFixtures(): iterable
    {
        $label = static fn (string $key): Label => new Label($key, self::DOMAIN);
        $fixtures = ScaffoldFixtures::all([
            'list' => '@NowoContractFixture/items/index.html.twig',
            'show' => '@NowoContractFixture/items/show.html.twig',
            'form' => '@NowoContractFixture/items/edit.html.twig',
            'settings' => '@NowoUiKit/page/settings.html.twig',
            'dashboard' => '@NowoContractFixture/dashboard.html.twig',
            'confirm' => '@NowoUiKit/page/confirm.html.twig',
            'wizard' => '@NowoUiKit/page/wizard.html.twig',
            'auth' => '@NowoContractFixture/login.html.twig',
        ], 'NowoContractFixtureBundle');
        unset($fixtures['list_empty']);
        $fixtures['list'] = new PageFixture('@NowoContractFixture/items/index.html.twig', ['page' => new ListPage(
            new PageHeader($label('fixture.items'), null, [], [new Crumb($label('fixture.items'))]),
            [new Column('name', 'Name'), new Column('active', 'Status')],
            [['name' => 'Alpha', 'active' => true], ['name' => 'Beta', 'active' => false]],
            pagination: new Pagination(1, 2, 2),
            bundle: 'NowoContractFixtureBundle',
        )]);
        $fixtures['board'] = new PageFixture('@NowoContractFixture/board.html.twig', [
            'header' => new PageHeader($label('fixture.board')),
            'columns' => ['To do', 'Doing', 'Done'],
        ]);

        return $fixtures;
    }

    public function testUniquePagesAreParsedFromTheTemplatesDocument(): void
    {
        self::assertSame(['board.html.twig', 'bad/unique_without_layout.html.twig', 'bad/no_slots.html.twig', 'bad/no_attrs.html.twig', 'bad/empty_content.html.twig'], self::uniquePages());
    }

    public function testCreateTwigAppliesTheBundleHooks(): void
    {
        $twig = $this->createTwig('tailwind');

        self::assertSame('ok', $twig->createTemplate('{{ fixture_status() }}')->render());
        self::assertSame('/x?a=1 http://localhost/x /assets/app.css csrf-del 1 /logout /p', trim($twig->createTemplate("{{ path('x', {a: 1}) }} {{ url('x') }} {{ asset('/app.css') }} {{ csrf_token('del') }} {{ is_granted('ROLE_ADMIN') }} {{ logout_path() }} {{ absolute_url('/p') }}")->render()));
        self::assertSame('tailwind', $twig->getGlobals()['nowo_ui_kit_css_framework']);
        self::assertSame('/r', ContractStubExtension::path('r'));
    }

    public function testFrameworkClassesDetection(): void
    {
        self::assertTrue(FrameworkClasses::isFrameworkClass('d-flex'));
        self::assertTrue(FrameworkClasses::isFrameworkClass('md:flex'));
        self::assertTrue(FrameworkClasses::isFrameworkClass('px-4'));
        self::assertTrue(FrameworkClasses::isFrameworkClass('callout'));
        self::assertFalse(FrameworkClasses::isFrameworkClass('nowo-ui-btn'));
        self::assertFalse(FrameworkClasses::isFrameworkClass('nowo-http-log-row'));
        self::assertFalse(FrameworkClasses::isFrameworkClass('pc-table'));
        self::assertContains('btn-primary', FrameworkClasses::allowed('bootstrap5'));
        self::assertNotContains('btn-primary', FrameworkClasses::allowed('tailwind'));
        self::assertSame([...FrameworkClasses::UIKIT, ...FrameworkClasses::MODAL_SHELL], FrameworkClasses::allowed('none'));
        self::assertSame([], FrameworkClasses::violations(FrameworkClasses::MODAL_SHELL, 'tailwind'));
        self::assertSame(['btn'], FrameworkClasses::violations(['btn', 'nowo-ui-x', 'host-ok'], 'none'));
        self::assertSame([], FrameworkClasses::violations(['btn'], 'none', ['btn']));
    }

    public function testStubHostMarkers(): void
    {
        self::assertSame('pc-page-header', StubHost::slotClass('page_header'));
        self::assertSame('data-pc-page-header', StubHost::slotAttribute('page_header'));
        self::assertSame([StubHost::PARTIAL => StubHost::PARTIAL_TEMPLATE], StubHost::theme()['partials']);
        $page = new RenderedPage('t', '<div data-nowo-ui-contract-block="b"><p title=" ">x</p><input type="submit" value="Go"></div>', 'none');
        self::assertNull($page->block('missing'));
        self::assertSame('', $page->blockHtml('missing'));
        self::assertSame(['x', 'Go'], $page->texts());
        $forms = new RenderedPage('t', '<form name="a"><input type="hidden" name="a[_token]" value="t"><input type="hidden" name="other" value="o"></form><form name="b"></form><form name="a"></form>', 'none', [], ['a' => ['a[_token]' => 't']]);
        self::assertSame([['a[_token]' => ['t']], ['a[_token]' => []]], $forms->hiddenFieldValues('a', ['a[_token]']));
        self::assertSame(['a' => ['a[_token]' => 't']], $forms->actionForms);
    }

    public function testCollectionPrototypesAreResetBetweenRenders(): void
    {
        $form = Forms::createFormFactoryBuilder()->getFormFactory()->createNamedBuilder('team', FormType::class, ['members' => ['Ann']])
            ->add('members', CollectionType::class, ['entry_type' => TextType::class, 'allow_add' => true, 'prototype' => true])
            ->getForm()->createView();
        $fixture = new PageFixture('@NowoUiKit/page/form.html.twig', ['page' => new FormPage(new PageHeader('Team'), $form)]);

        foreach (['none', 'bootstrap5'] as $framework) {
            $page = $this->renderPage($fixture, $framework);
            self::assertStringContainsString('data-prototype=', $page->html);
            self::assertStringContainsString('team[members][__name__]', $page->html);
        }
    }

    public function testModalShellStructureIsAStructuralException(): void
    {
        $fixture = new PageFixture('@NowoContractFixture/bad/modal_shell.html.twig', ['page' => new DashboardPage(new PageHeader('Modal'), [new KpiTile('Items', '1')])]);
        self::$extraTemplates = [$fixture->template => "{% extends '@NowoUiKit/page/dashboard.html.twig' %}{% block nowo_ui_modals %}{{ include(nowo_ui_partial('modal_shell'), {id: 'm1', title: 'Import', body: '<p>x</p>', footer: '<span>ok</span>'}) }}{% endblock %}"];
        try {
            foreach (['bootstrap5', 'tailwind', 'none'] as $framework) {
                $page = $this->renderPage($fixture, $framework);
                self::assertStringContainsString('modal-content', $page->html);
                $this->assertNoFrameworkUtilityClasses($page);
            }
        } finally {
            self::$extraTemplates = [];
        }
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string, 3: array<string, mixed>}>
     */
    public static function violations(): iterable
    {
        $header = ['page' => new DashboardPage(new PageHeader('Bad'), [new KpiTile('Items', '1')])];
        $dashboard = static fn (string $cards): string => "{% extends '@NowoUiKit/page/dashboard.html.twig' %}{% block nowo_ui_dashboard_cards %}".$cards.'{% endblock %}';

        yield 'not a scaffold, not unique' => ['is not listed under "Unique pages"', 'assertExtendsScaffoldOrUnique', '{% extends nowo_ui_layout.admin %}{% block nowo_ui_content %}x{% endblock %}', []];
        yield 'unique without layout' => ['must extend the UiKit layout', 'assertExtendsScaffoldOrUnique', '<p>standalone</p>', []];
        yield 'own layout' => ['exactly once; got 0', 'assertCanonicalBlocksFilled', '<main>no host layout</main>', []];
        yield 'empty content' => ['"nowo_ui_content" is empty', 'assertCanonicalBlocksFilled', '{% extends nowo_ui_layout.admin %}{% block nowo_ui_document_title %}T{% endblock %}', []];
        yield 'html skeleton' => ['document skeleton', 'assertNoForbiddenMarkup', $dashboard('<!DOCTYPE html><html><body></body></html>'), $header];
        yield 'cdn script' => ['CDN <script>', 'assertNoForbiddenMarkup', $dashboard('<script src="https://cdn.example.com/x.js"></script>'), $header];
        yield 'cdn stylesheet' => ['CDN <link>', 'assertNoForbiddenMarkup', $dashboard('<link rel="stylesheet" href="//cdn.example.com/x.css">'), $header];
        yield 'inline handler' => ['inline on* event handler', 'assertNoForbiddenMarkup', $dashboard('<button onclick="go()">Go</button>'), $header];
        yield 'confirm()' => ['confirm() call', 'assertNoForbiddenMarkup', $dashboard('<form data-x="return confirm(\'Sure?\')"></form>'), $header];
        yield 'javascript url' => ['javascript: URL', 'assertNoForbiddenMarkup', $dashboard('<a href="javascript:void(0)">x</a>'), $header];
        yield 'utility class' => ['outside the theme slots: d-flex, gap-2', 'assertNoFrameworkUtilityClasses', $dashboard('<div class="d-flex gap-2">x</div>'), $header];
        yield 'no slot classes' => ['no theme slot class', 'assertStubThemeReflected', '{% extends nowo_ui_layout.admin %}{% block nowo_ui_document_title %}T{% endblock %}{% block nowo_ui_content %}<p>plain</p>{% endblock %}', []];
        yield 'no slot attributes' => ['no theme slot attributes', 'assertStubThemeReflected', "{% extends nowo_ui_layout.admin %}{% block nowo_ui_document_title %}T{% endblock %}{% block nowo_ui_content %}<p class=\"{{ nowo_ui_class('card') }}\">x</p>{% endblock %}", []];
        yield 'page header bypasses the partial' => ['ignores the theme partial override', 'assertStubThemeReflected', "{% extends '@NowoUiKit/page/dashboard.html.twig' %}{% block nowo_ui_page_header %}<h1 class=\"{{ nowo_ui_class('page_title') }}\">Bad</h1>{% endblock %}", $header];
        yield 'missing translation' => ['NowoContractFixtureBundle:fixture.missing', 'assertNoRawTranslationKeys', $dashboard("{{ 'fixture.missing'|trans({}, 'NowoContractFixtureBundle') }}"), $header];
        yield 'action form without its token' => ['rendered form "bad_action" #1 of a FormView-backed action must carry its hidden field "bad_action[_token]" exactly once (got 0)', 'assertActionFormsKeepHiddenFields', $dashboard('{{ form_start(action.form) }}<button>Go</button>{{ form_end(action.form, {render_rest: false}) }}'), $header + ['action' => new Action('custom', 'Go', '/go', method: 'POST', form: ScaffoldFixtures::csrf('bad_action'))]];
        yield 'raw key' => ['raw translation keys: item.name.placeholder', 'assertNoRawTranslationKeys', $dashboard('<input placeholder="item.name.placeholder">'), $header];
    }

    /**
     * @param array<string, mixed> $context
     */
    #[DataProvider('violations')]
    public function testAssertionsCatchViolations(string $message, string $assertion, string $source, array $context): void
    {
        $names = ['unique without layout' => 'unique_without_layout', 'no slot classes' => 'no_slots', 'no slot attributes' => 'no_attrs', 'empty content' => 'empty_content'];
        $fixture = new PageFixture('@NowoContractFixture/bad/'.($names[$this->dataName()] ?? md5($source)).'.html.twig', $context);
        self::$extraTemplates = [$fixture->template => $source];

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage($message);
        try {
            if ('assertExtendsScaffoldOrUnique' === $assertion) {
                $this->assertExtendsScaffoldOrUnique($fixture);
            } else {
                $this->{$assertion}($this->renderPage($fixture, 'none'));
            }
        } finally {
            self::$extraTemplates = [];
        }
    }

    /** @var array<string, string> */
    private static array $extraTemplates = [];

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

    protected static function templatesDocument(): string
    {
        return \dirname(__DIR__, 2).'/Fixtures/page-contract/TEMPLATES.md';
    }

    protected static function allowedClasses(): array
    {
        return ['host-provided'];
    }

    protected function configureTwig(Environment $twig): void
    {
        $twig->addFunction(new TwigFunction('fixture_status', static fn (): string => 'ok'));
    }
}
