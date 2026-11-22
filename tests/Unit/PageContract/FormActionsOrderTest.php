<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use InvalidArgumentException;
use Nowo\UiKitBundle\Test\RenderedPage;
use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Theme\ThemeSlots;
use Nowo\UiKitBundle\View\FormPage;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\PageHeader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormView;

/**
 * 2.66 (UX-017): one form actions order — Cancel (secondary) first, then the primary on the right, as in the dialog
 * footers and the full-page confirm. The `form_actions` partial renders the row (form scaffold and bundle forms);
 * theme.form_actions_order `primary_first` keeps the 2.65 order; the DOM order is the focus order.
 */
final class FormActionsOrderTest extends TestCase
{
    private const PARTIAL = "{{ include(nowo_ui_partial('form_actions', bundle|default(null)), vars, false) }}";

    public function testThemeOptionDefaultsToCancelFirst(): void
    {
        self::assertSame(['cancel_first', 'primary_first'], Theme::FORM_ACTIONS_ORDERS);
        self::assertSame('cancel_first', (new Theme())->formActionsOrder());
        self::assertSame('cancel_first', Theme::fromConfig('bootstrap5', [])->formActionsOrder());
        self::assertSame('cancel_first', Theme::fromConfig('bootstrap5', ['form_actions_order' => null])->formActionsOrder());
        self::assertSame('primary_first', Theme::fromConfig('bootstrap5', ['form_actions_order' => 'primary_first'])->formActionsOrder());
        self::assertSame('primary_first', (new Theme(formActionsOrder: 'primary_first'))->formActionsOrder());
        self::assertSame('@NowoUiKitBundle/partials/_form_actions.html.twig', ThemeSlots::PARTIALS['form_actions']);
    }

    public function testThemeRejectsAnUnknownOrder(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid UiKit theme form_actions_order "reversed": use "cancel_first" or "primary_first".');
        new Theme(formActionsOrder: 'reversed');
    }

    public function testConfigRejectsANonStringOrder(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid UiKit theme form_actions_order "int"');
        Theme::fromConfig('bootstrap5', ['form_actions_order' => 3]);
    }

    public function testPartialPutsCancelFirstThenThePrimary(): void
    {
        $c = $this->crawl(['cancel_href' => '/items']);
        $row = $c->filter('.nowo-ui-form__actions');
        self::assertCount(1, $row);
        self::assertSame('cancel_first', $row->attr('data-nowo-ui-form-actions'));
        self::assertSame('nowo-ui-form__actions d-flex flex-wrap gap-2', $row->attr('class'));
        $controls = $row->children();
        self::assertCount(2, $controls);
        self::assertSame('a', $controls->eq(0)->nodeName());
        self::assertSame('/items', $controls->eq(0)->attr('href'));
        self::assertSame('', $controls->eq(0)->attr('data-nowo-ui-form-cancel'));
        self::assertSame('Cancel', $controls->eq(0)->text());
        self::assertSame('nowo-ui-btn nowo-ui-btn--secondary btn btn-outline-secondary', $controls->eq(0)->attr('class'));
        self::assertSame('button', $controls->eq(1)->nodeName());
        self::assertSame('submit', $controls->eq(1)->attr('type'));
        self::assertSame('Save', $controls->eq(1)->text());
        self::assertSame('nowo-ui-btn nowo-ui-btn--primary btn btn-primary', $controls->eq(1)->attr('class'));
    }

    public function testDialogCancelIsAButtonThatClosesTheDialog(): void
    {
        $c = $this->crawl(['cancel_dialog' => true, 'submit_label' => new Label('Import'), 'submit_attrs' => ['data-testid' => 'x-import', 'class' => 'is-wide', 'type' => 'button'], 'cancel_label' => 'Close']);
        $controls = $c->filter('.nowo-ui-form__actions')->children();
        self::assertSame('button', $controls->eq(0)->nodeName());
        self::assertSame('button', $controls->eq(0)->attr('type'));
        self::assertSame('', $controls->eq(0)->attr('data-nowo-ui-dialog-close'));
        self::assertSame('Close', $controls->eq(0)->text());
        // the partial owns the submit type; the class is appended to the btn_primary slot
        self::assertSame('submit', $controls->eq(1)->attr('type'));
        self::assertSame('x-import', $controls->eq(1)->attr('data-testid'));
        self::assertSame('nowo-ui-btn nowo-ui-btn--primary btn btn-primary is-wide', $controls->eq(1)->attr('class'));
        self::assertSame('Import', $controls->eq(1)->text());
    }

    public function testCancelLinkWinsOverTheDialogButtonAndKeepsItsAttributes(): void
    {
        $c = $this->crawl(['cancel_href' => '/back', 'cancel_dialog' => true, 'cancel_attrs' => ['data-nowo-ui-dialog-close' => true, 'data-testid' => 'cancel', 'href' => '/evil', 'class' => 'x-cancel']]);
        $cancel = $c->filter('[data-nowo-ui-form-cancel]');
        self::assertCount(1, $cancel);
        self::assertSame('a', $cancel->nodeName());
        self::assertSame('/back', $cancel->attr('href'));
        self::assertSame('cancel', $cancel->attr('data-testid'));
        self::assertSame('', $cancel->attr('data-nowo-ui-dialog-close'));
        self::assertStringEndsWith(' x-cancel', (string) $cancel->attr('class'));
        self::assertSame(1, substr_count($cancel->outerHtml(), 'href='));
    }

    public function testExtraActionsSitBetweenCancelAndThePrimary(): void
    {
        $html = $this->render(['cancel_href' => '/c', 'extra_actions' => '<a class="x-extra" href="/x">Preview</a>']);
        self::assertSame(['Cancel', 'Preview', 'Save'], (new Crawler($html))->filter('.nowo-ui-form__actions')->children()->each(static fn (Crawler $n): string => $n->text()));

        $html = $this->render(['cancel_href' => '/c', 'extra_actions' => '<a class="x-extra" href="/x">Preview</a>', 'order' => 'primary_first']);
        self::assertSame(['Save', 'Preview', 'Cancel'], (new Crawler($html))->filter('.nowo-ui-form__actions')->children()->each(static fn (Crawler $n): string => $n->text()));
    }

    public function testThemePrimaryFirstAndTheOrderOptionWins(): void
    {
        $theme = ['form_actions_order' => 'primary_first'];
        $c = $this->crawl(['cancel_href' => '/c'], $theme);
        self::assertSame('primary_first', $c->filter('.nowo-ui-form__actions')->attr('data-nowo-ui-form-actions'));
        self::assertSame(['Save', 'Cancel'], $c->filter('.nowo-ui-form__actions')->children()->each(static fn (Crawler $n): string => $n->text()));

        $c = $this->crawl(['cancel_href' => '/c', 'order' => 'cancel_first'], $theme);
        self::assertSame(['Cancel', 'Save'], $c->filter('.nowo-ui-form__actions')->children()->each(static fn (Crawler $n): string => $n->text()));
    }

    public function testRowWithoutCancelOrWithoutSubmit(): void
    {
        $c = $this->crawl([]);
        self::assertSame(['Save'], $c->filter('.nowo-ui-form__actions')->children()->each(static fn (Crawler $n): string => $n->text()));
        self::assertCount(0, $c->filter('[data-nowo-ui-form-cancel]'));

        $c = $this->crawl(['submit' => false, 'cancel_href' => '/c']);
        self::assertSame(['Cancel'], $c->filter('.nowo-ui-form__actions')->children()->each(static fn (Crawler $n): string => $n->text()));
    }

    public function testBundleAndPageScopedSlotClasses(): void
    {
        $theme = ['bundles' => ['NowoXBundle' => ['classes' => ['btn_secondary' => 'btn-ghost'], 'pages' => ['form' => ['classes' => ['form_actions' => 'x-form-actions']]]]]];
        $html = PageContractTwig::create(['t' => self::PARTIAL], $theme, 'none')->render('t', ['bundle' => 'NowoXBundle', 'vars' => ['cancel_href' => '/c', 'bundle' => 'NowoXBundle', 'nowo_ui_page_type' => 'form']]);
        $c = new Crawler($html);
        self::assertSame('nowo-ui-form__actions x-form-actions', $c->filter('[data-nowo-ui-form-actions]')->attr('class'));
        self::assertSame('nowo-ui-btn nowo-ui-btn--secondary btn-ghost', $c->filter('[data-nowo-ui-form-cancel]')->attr('class'));
    }

    public function testFormScaffoldPutsCancelFirstByDefault(): void
    {
        $page = new FormPage(new PageHeader('Edit'), $this->form(), 'Store', '/items', 'Back');
        $c = new Crawler(PageContractTwig::create()->render('@NowoUiKit/page/form.html.twig', ['page' => $page]));
        $row = $c->filter('form .nowo-ui-form__actions');
        self::assertCount(1, $row);
        self::assertSame(['Back', 'Store'], $row->children()->each(static fn (Crawler $n): string => $n->text()));
        self::assertSame('/items', $row->filter('a[data-nowo-ui-form-cancel]')->attr('href'));

        $rows = (new RenderedPage('t', $c->html(), 'bootstrap5'))->formActionsRows();
        self::assertSame([['cancel', 'primary']], $rows);
    }

    public function testFormScaffoldKeepsThe265OrderWithPrimaryFirst(): void
    {
        $page = new FormPage(new PageHeader('Edit'), $this->form(), cancelHref: '/items');
        $html = PageContractTwig::create([], ['form_actions_order' => 'primary_first'])->render('@NowoUiKit/page/form.html.twig', ['page' => $page]);
        self::assertSame(['Save', 'Cancel'], (new Crawler($html))->filter('form .nowo-ui-form__actions')->children()->each(static fn (Crawler $n): string => $n->text()));
    }

    public function testFormScaffoldWithoutCancelHref(): void
    {
        $page = new FormPage(new PageHeader('Edit'), $this->form());
        $html = PageContractTwig::create()->render('@NowoUiKit/page/form.html.twig', ['page' => $page]);
        self::assertSame(['Save'], (new Crawler($html))->filter('form .nowo-ui-form__actions')->children()->each(static fn (Crawler $n): string => $n->text()));
    }

    public function testRowsClassifyControls(): void
    {
        $html = '<div class="nowo-ui-form__actions"><button class="nowo-ui-btn nowo-ui-btn--primary">Go</button><a class="nowo-ui-btn nowo-ui-btn--secondary" href="/c">Cancel</a><button type="submit" name="skip" class="nowo-ui-btn nowo-ui-btn--secondary">Skip</button><input type="submit" class="nowo-ui-btn nowo-ui-btn--primary" value="Also"></div>'
            .'<p class="nowo-ui-form__actions-hint"><button>Not a row</button></p>'
            .'<div class="x nowo-ui-form__actions"><button type="button" data-nowo-ui-form-cancel class="plain">Close</button><button type="button" class="nowo-ui-btn nowo-ui-btn--secondary">Back</button></div>';
        self::assertSame([['primary', 'cancel', 'other', 'primary'], ['cancel', 'cancel']], (new RenderedPage('t', $html, 'none'))->formActionsRows());
    }

    /**
     * @param array<string, mixed> $vars
     * @param array<string, mixed> $theme
     */
    private function render(array $vars, array $theme = []): string
    {
        return PageContractTwig::create(['t' => self::PARTIAL], $theme)->render('t', ['vars' => $vars]);
    }

    /**
     * @param array<string, mixed> $vars
     * @param array<string, mixed> $theme
     */
    private function crawl(array $vars, array $theme = []): Crawler
    {
        return new Crawler($this->render($vars, $theme));
    }

    private function form(): FormView
    {
        return PageContractTwig::forms()->createNamedBuilder('item', FormType::class)->add('name', TextType::class)->getForm()->createView();
    }
}
