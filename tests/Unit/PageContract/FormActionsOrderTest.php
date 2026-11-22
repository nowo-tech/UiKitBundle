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
 * 2.66 (UX-017) + 2.66.1 (owner decision 2026-11-22): theme.form_actions_order defaults to `primary_first` again (the
 * 2.65 order: the primary, then Cancel); `cancel_first` is the opt-in (Cancel first, then the primary on the right).
 * Dialog rows (Cancel closes the dialog), dialog footers, the full-page confirm and the wizard keep Cancel first. The
 * DOM order is the focus order.
 */
final class FormActionsOrderTest extends TestCase
{
    private const PARTIAL = "{{ include(nowo_ui_partial('form_actions', bundle|default(null)), vars, false) }}";

    private const CANCEL_FIRST = ['form_actions_order' => 'cancel_first'];

    public function testThemeOptionDefaultsToPrimaryFirst(): void
    {
        self::assertSame(['cancel_first', 'primary_first'], Theme::FORM_ACTIONS_ORDERS);
        self::assertSame('primary_first', (new Theme())->formActionsOrder());
        self::assertSame('primary_first', Theme::fromConfig('bootstrap5', [])->formActionsOrder());
        self::assertSame('primary_first', Theme::fromConfig('bootstrap5', ['form_actions_order' => null])->formActionsOrder());
        self::assertSame('cancel_first', Theme::fromConfig('bootstrap5', ['form_actions_order' => 'cancel_first'])->formActionsOrder());
        self::assertSame('cancel_first', (new Theme(formActionsOrder: 'cancel_first'))->formActionsOrder());
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

    public function testPartialPutsCancelFirstThenThePrimaryWithCancelFirst(): void
    {
        $c = $this->crawl(['cancel_href' => '/items'], self::CANCEL_FIRST);
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

    public function testPartialFollowsTheDefaultPrimaryFirst(): void
    {
        $c = $this->crawl(['cancel_href' => '/items']);
        $row = $c->filter('.nowo-ui-form__actions');
        self::assertSame('primary_first', $row->attr('data-nowo-ui-form-actions'));
        self::assertSame(['Save', 'Cancel'], $row->children()->each(static fn (Crawler $n): string => $n->text()));
        self::assertSame('/items', $row->filter('a[data-nowo-ui-form-cancel]')->attr('href'));
    }

    public function testDialogCancelIsAButtonThatClosesTheDialog(): void
    {
        // a dialog row keeps Cancel first whatever theme.form_actions_order says (2.66.1)
        $c = $this->crawl(['cancel_dialog' => true, 'submit_label' => new Label('Import'), 'submit_attrs' => ['data-testid' => 'x-import', 'class' => 'is-wide', 'type' => 'button'], 'cancel_label' => 'Close']);
        self::assertSame('cancel_first', $c->filter('.nowo-ui-form__actions')->attr('data-nowo-ui-form-actions'));
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
        // a Cancel link that closes the dialog makes it a dialog row: Cancel first
        self::assertSame(['Cancel', 'Save'], $c->filter('.nowo-ui-form__actions')->children()->each(static fn (Crawler $n): string => $n->text()));
    }

    public function testDialogRowsKeepCancelFirstUnlessTheOrderOptionSaysOtherwise(): void
    {
        foreach ([[], self::CANCEL_FIRST, ['form_actions_order' => 'primary_first']] as $theme) {
            self::assertSame(['Cancel', 'Save'], $this->crawl(['cancel_dialog' => true], $theme)->filter('.nowo-ui-form__actions')->children()->each(static fn (Crawler $n): string => $n->text()));
            self::assertSame(['Cancel', 'Save'], $this->crawl(['cancel_href' => '/c', 'cancel_attrs' => ['data-nowo-ui-dialog-close' => true]], $theme)->filter('.nowo-ui-form__actions')->children()->each(static fn (Crawler $n): string => $n->text()));
        }
        // a link with cancel_dialog is a page row (cancel_href wins) and follows the theme; data-nowo-ui-dialog-close false too
        self::assertSame(['Save', 'Cancel'], $this->crawl(['cancel_href' => '/c', 'cancel_dialog' => true])->filter('.nowo-ui-form__actions')->children()->each(static fn (Crawler $n): string => $n->text()));
        self::assertSame(['Save', 'Cancel'], $this->crawl(['cancel_href' => '/c', 'cancel_attrs' => ['data-nowo-ui-dialog-close' => false]])->filter('.nowo-ui-form__actions')->children()->each(static fn (Crawler $n): string => $n->text()));
        self::assertSame(['Save', 'Cancel'], $this->crawl(['cancel_dialog' => true, 'order' => 'primary_first'])->filter('.nowo-ui-form__actions')->children()->each(static fn (Crawler $n): string => $n->text()));
    }

    public function testExtraActionsSitBetweenCancelAndThePrimary(): void
    {
        $html = $this->render(['cancel_href' => '/c', 'extra_actions' => '<a class="x-extra" href="/x">Preview</a>'], self::CANCEL_FIRST);
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

    public function testFormScaffoldPutsCancelFirstWithCancelFirst(): void
    {
        $page = new FormPage(new PageHeader('Edit'), $this->form(), 'Store', '/items', 'Back');
        $c = new Crawler(PageContractTwig::create([], self::CANCEL_FIRST)->render('@NowoUiKit/page/form.html.twig', ['page' => $page]));
        $row = $c->filter('form .nowo-ui-form__actions');
        self::assertCount(1, $row);
        self::assertSame(['Back', 'Store'], $row->children()->each(static fn (Crawler $n): string => $n->text()));
        self::assertSame('/items', $row->filter('a[data-nowo-ui-form-cancel]')->attr('href'));

        $rows = (new RenderedPage('t', $c->html(), 'bootstrap5'))->formActionsRows();
        self::assertSame([['cancel', 'primary']], $rows);
    }

    public function testFormScaffoldKeepsThe265RowByDefault(): void
    {
        $page = new FormPage(new PageHeader('Edit'), $this->form(), cancelHref: '/items');
        foreach ([[], ['form_actions_order' => 'primary_first']] as $theme) {
            $c = new Crawler(PageContractTwig::create([], $theme)->render('@NowoUiKit/page/form.html.twig', ['page' => $page]));
            $row = $c->filter('form .nowo-ui-form__actions');
            self::assertSame(['Save', 'Cancel'], $row->children()->each(static fn (Crawler $n): string => $n->text()));
            // the 2.65 markup: no order marker on the row, no cancel marker on the link
            self::assertNull($row->attr('data-nowo-ui-form-actions'));
            self::assertCount(0, $row->filter('[data-nowo-ui-form-cancel]'));
            self::assertSame([['primary', 'cancel']], (new RenderedPage('t', $c->html(), 'bootstrap5'))->formActionsRows());
        }
    }

    public function testFormScaffoldRendersAnOverriddenPartialInEitherOrder(): void
    {
        $page = new FormPage(new PageHeader('Edit'), $this->form(), cancelHref: '/items');
        $theme = ['partials' => ['form_actions' => 'my_actions']];
        $html = PageContractTwig::create(['my_actions' => '<div class="x-my-actions">{{ cancel_href }}</div>'], $theme)->render('@NowoUiKit/page/form.html.twig', ['page' => $page]);
        self::assertStringContainsString('<div class="x-my-actions">/items</div>', $html);
        self::assertStringNotContainsString('nowo-ui-form__actions', $html);
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

    public function testRowAnatomies(): void
    {
        $row = '<div class="nowo-ui-form__actions"><a class="nowo-ui-btn nowo-ui-btn--secondary" href="/c">Cancel</a><button type="submit" class="nowo-ui-btn nowo-ui-btn--primary">Save</button></div>';
        $dialogRow = '<div class="nowo-ui-form__actions"><button type="button" class="nowo-ui-btn nowo-ui-btn--secondary" data-nowo-ui-dialog-close>Cancel</button><button type="submit" class="nowo-ui-btn nowo-ui-btn--primary">Save</button></div>';
        $html = '<section data-nowo-ui-page="form">'.$row.$dialogRow.'</section>'
            .'<dialog open><form method="post">'.$row.'</form></dialog>'
            .'<section data-nowo-ui-page="confirm">'.$row.'</section>'
            .'<div data-nowo-ui-page="wizard"><form>'.$row.'</form></div>';
        $page = new RenderedPage('t', $html, 'none');
        self::assertSame(['form', 'dialog', 'dialog', 'confirm', 'wizard'], $page->formActionsRowAnatomies());
        self::assertCount(5, $page->formActionsRows());
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
