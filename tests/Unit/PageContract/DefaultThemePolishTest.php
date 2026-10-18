<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\AreaNav;
use Nowo\UiKitBundle\View\AreaNavItem;
use Nowo\UiKitBundle\View\AuthPage;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\ConfirmPage;
use Nowo\UiKitBundle\View\Crumb;
use Nowo\UiKitBundle\View\DetailItem;
use Nowo\UiKitBundle\View\DetailSection;
use Nowo\UiKitBundle\View\FormPage;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\SettingsPage;
use Nowo\UiKitBundle\View\SettingsSection;
use Nowo\UiKitBundle\View\ShowPage;
use Nowo\UiKitBundle\View\Table;
use Nowo\UiKitBundle\View\WizardPage;
use Nowo\UiKitBundle\View\WizardStep;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Twig\Environment;
use Twig\TwigFunction;

/**
 * UiKit 2.2: default-theme polish and the phase-1 backlog (confirm close / size / solid danger button, dialog rich
 * title and attributes, Action::dialogId, section tabs level, settings section actions, list caption / actions
 * column, breadcrumbs modes, flashes input, auth footer and JS-free pages, scripts partial, hidden values).
 */
final class DefaultThemePolishTest extends TestCase
{
    public function testConfirmDialogCloseSizeAndSolidDangerButton(): void
    {
        $twig = PageContractTwig::create([], ['classes' => ['confirm_close' => 'app-close'], 'attributes' => ['confirm_close' => ['data-x' => '1']]]);
        $html = $twig->render('@NowoUiKitBundle/partials/_confirm.html.twig', ['confirm' => Confirm::delete('Order #7 will be deleted.', 'Delete order #7?'), 'action' => Action::delete('/o/7', 'tok')]);

        self::assertMatchesRegularExpression('#<button type="button" class="nowo-ui-confirm__close app-close" data-nowo-confirm-close aria-label="Close" title="Close" data-x="1"><span aria-hidden="true">&times;</span></button>\s*</header>#', $html);
        self::assertStringContainsString('>Delete order #7?</h2>', $html);
        self::assertStringContainsString('class="nowo-ui-confirm"', $html);
        self::assertStringContainsString('nowo-ui-btn nowo-ui-btn--danger nowo-ui-btn--solid btn btn-danger nowo-ui-confirm__submit', $html);

        $lg = $twig->render('@NowoUiKitBundle/partials/_confirm.html.twig', ['confirm' => new Confirm('Sure?', variant: 'primary', size: 'lg'), 'id' => 'c2', 'dismissible' => false, 'action' => Action::delete('/o/7')]);
        self::assertStringContainsString('class="nowo-ui-confirm nowo-ui-confirm--lg"', $lg);
        self::assertStringNotContainsString('nowo-ui-confirm__close', $lg);
        self::assertStringContainsString('nowo-ui-btn nowo-ui-btn--primary btn btn-primary nowo-ui-confirm__submit', $lg);

        // 1.x inputs: no close button unless asked; size option; bare Twig (no ThemeExtension) keeps a solid modifier
        $legacy = $twig->render('@NowoUiKitBundle/partials/_confirm.html.twig', ['id' => 'c3', 'title' => 'T', 'size' => 'sm', 'dismissible' => true, 'close_label' => 'Dismiss']);
        self::assertStringContainsString('class="nowo-ui-confirm nowo-ui-confirm--sm"', $legacy);
        self::assertStringContainsString('aria-label="Dismiss"', $legacy);
        self::assertStringNotContainsString('nowo-ui-confirm__close', $twig->render('@NowoUiKitBundle/partials/_confirm.html.twig', ['id' => 'c4', 'title' => 'T']));

        $bare = new Environment(new \Twig\Loader\FilesystemLoader(), ['autoescape' => 'html']);
        $bare->getLoader()->addPath(PageContractTwig::root().'/src/Resources/views', 'NowoUiKitBundle');
        $bare->addFunction(new TwigFunction('path', static fn (string $name): string => '/'.$name));
        $bare->addFilter(new \Twig\TwigFilter('trans', static fn (string $key): string => $key));
        $bare->addFilter(new \Twig\TwigFilter('nowo_ui_trans', static fn (mixed $v): string => \is_object($v) ? $v->key : (string) $v));
        $bareHtml = $bare->render('@NowoUiKitBundle/partials/_confirm.html.twig', ['confirm' => new Confirm('Sure?'), 'id' => 'c5', 'action' => Action::delete('/x'), 'framework' => 'custom']);
        self::assertStringContainsString('class="nowo-ui-btn nowo-ui-btn--danger nowo-ui-btn--solid nowo-ui-confirm__submit"', $bareHtml);
        self::assertStringContainsString('class="nowo-ui-confirm__close"', $bareHtml);
    }

    public function testConfirmSizeValidationAndStableDerivedIds(): void
    {
        $md = Action::delete('/x', 't', new Confirm('Sure?'));
        self::assertSame($md->confirmId(), Action::delete('/x', 't', new Confirm('Sure?', size: 'md'))->confirmId());
        self::assertNotSame($md->confirmId(), Action::delete('/x', 't', new Confirm('Sure?', size: 'lg'))->confirmId());
        self::assertArrayNotHasKey('size', (new Confirm('Sure?'))->jsonSerialize());
        self::assertSame('lg', (new Confirm('Sure?', size: 'lg'))->jsonSerialize()['size']);
        self::assertSame('Delete it?', Confirm::delete(title: 'Delete it?')->title);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Confirm size "xl"');
        new Confirm('Sure?', size: 'xl');
    }

    public function testFullPageConfirmUsesTheSolidDangerButton(): void
    {
        $page = new ConfirmPage(new PageHeader('Delete'), new Confirm('Delete?'), new Action('delete', href: '/x', method: 'POST'));
        $html = PageContractTwig::create()->render('@NowoUiKit/page/confirm.html.twig', ['page' => $page]);
        self::assertStringContainsString('nowo-ui-btn nowo-ui-btn--danger nowo-ui-btn--solid btn btn-danger nowo-ui-confirm__submit', $html);
    }

    public function testDialogRichTitleAndAttributes(): void
    {
        $twig = PageContractTwig::create(['t' => "{{ include(nowo_ui_partial('dialog'), {id: 'd1', title_html: '<code>approve</code> transition', title_attr: {'data-t': '1'}, body_attr: {'data-b': true, 'data-off': false}, body: '<p>x</p>'}, false) }}"]);
        $html = $twig->render('t');

        self::assertStringContainsString('<h2 class="nowo-ui-dialog__title" id="d1-title" data-t="1"><code>approve</code> transition</h2>', $html);
        self::assertStringContainsString('<div class="nowo-ui-dialog__body" data-b><p>x</p></div>', $html);

        $escaped = PageContractTwig::create(['t' => "{{ include(nowo_ui_partial('dialog'), {id: 'd2', title: '<b>T</b>'}, false) }}"])->render('t');
        self::assertStringContainsString('>&lt;b&gt;T&lt;/b&gt;</h2>', $escaped);
    }

    public function testActionDialogOpener(): void
    {
        $action = new Action('custom', 'Edit transition', attributes: ['data-nowo-ui-dialog-replace' => true], dialogId: 'transition-dialog');
        $html = PageContractTwig::create()->render('@NowoUiKitBundle/partials/_action.html.twig', ['action' => $action]);
        self::assertStringContainsString('<button type="button" class="nowo-ui-btn nowo-ui-btn--secondary btn btn-outline-secondary nowo-ui-action--custom" data-nowo-ui-dialog-replace data-nowo-ui-dialog-open="transition-dialog" aria-haspopup="dialog" aria-controls="transition-dialog">', $html);
        self::assertSame('transition-dialog', $action->toArray()['dialog_id']);
        self::assertSame('transition-dialog', $action->withConfirmScope(3)->dialogId);

        $row = PageContractTwig::create()->render('@NowoUiKitBundle/partials/_action.html.twig', ['action' => new Action('config', disabled: true, dialogId: 'd9'), 'context' => 'row']);
        self::assertMatchesRegularExpression('#<button type="button" class="[^"]*" title="Settings" aria-label="Settings" disabled data-nowo-ui-dialog-open="d9" aria-haspopup="dialog" aria-controls="d9">#', $row);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Action dialogId "1x"');
        new Action(dialogId: '1x');
    }

    public function testHiddenFieldsAlwaysCarryAValue(): void
    {
        $form = PageContractTwig::forms()->createNamedBuilder('delete_item', FormType::class)
            ->add('confirm', HiddenType::class, ['data' => ''])
            ->add('_token', HiddenType::class, ['data' => 'tok'])
            ->getForm()->createView();
        $html = PageContractTwig::create()->render('@NowoUiKitBundle/partials/_action.html.twig', ['action' => new Action('delete', href: '/x', method: 'POST', form: $form)]);

        self::assertStringContainsString('<input type="hidden" name="delete_item[confirm]" value="">', $html);
        self::assertStringContainsString('<input type="hidden" name="delete_item[_token]" value="tok">', $html);
    }

    public function testSectionTabsLevelAndSectionActions(): void
    {
        $form = PageContractTwig::forms()->createNamedBuilder('mail', FormType::class)->add('host', TextType::class)->getForm()->createView();
        $sections = [
            new SettingsSection('mail', 'Mail', '/s/mail', $form, actions: [new Action('custom', 'Send test', '/s/test'), new Action('delete', 'Reset', '/s/reset', method: 'POST', confirm: new Confirm('Reset?'))]),
            new SettingsSection('other', 'Other', '/s/other'),
        ];
        $nav = new AreaNav([new AreaNavItem('Settings', '/s', true), new AreaNavItem('Logs', '/l')]);
        $html = PageContractTwig::create()->render('@NowoUiKit/page/settings.html.twig', ['page' => new SettingsPage((new PageHeader('Settings'))->withTabs($nav), $sections, 'mail')]);

        self::assertStringContainsString('<nav class="nowo-ui-tabs nowo-ui-tabs--secondary nav nav-pills mb-3" aria-label="Sections" data-nowo-ui-region="tabs">', $html);
        self::assertStringContainsString('<nav class="nowo-ui-tabs nav nav-tabs mb-3" aria-label="Sections" data-nowo-ui-region="area-nav">', $html);
        self::assertStringContainsString('<form name="mail" method="post" class="nowo-ui-form" data-nowo-ui-region="form" id="mail-form">', $html);
        self::assertMatchesRegularExpression('#</form>\s*<div class="nowo-ui-form__actions d-flex flex-wrap gap-2 mt-3">\s*<button type="submit" class="nowo-ui-btn nowo-ui-btn--primary btn btn-primary" form="mail-form">Save</button>\s*<a class="[^"]*nowo-ui-action--custom" href="/s/test">#', $html);
        self::assertStringContainsString('data-nowo-confirm-open', $html);

        // without an area nav the section tabs stay primary; without actions the row stays inside the form
        $plain = PageContractTwig::create()->render('@NowoUiKit/page/settings.html.twig', ['page' => new SettingsPage(new PageHeader('Settings'), [new SettingsSection('a', 'A', '/a', PageContractTwig::forms()->createNamedBuilder('a', FormType::class)->add('x', TextType::class)->getForm()->createView()), new SettingsSection('b', 'B', '/b')], 'a')]);
        self::assertStringContainsString('<nav class="nowo-ui-tabs nav nav-tabs mb-3" aria-label="Sections" data-nowo-ui-region="tabs">', $plain);
        self::assertMatchesRegularExpression('#<button type="submit" class="nowo-ui-btn nowo-ui-btn--primary btn btn-primary">Save</button>\s*</div>\s*</form>#', $plain);

        // partial inputs: form attr.id wins, an unnamed form falls back, extra_actions is printed; bare tabs secondary
        $twig = PageContractTwig::create(['t' => "{{ include('@NowoUiKitBundle/page/_section_form.html.twig', {form: form, actions: actions, extra_actions: '<i>x</i>'}, false) }}", 'tabs' => "{{ include('@NowoUiKitBundle/partials/_tabs.html.twig', {items: [], level: 'secondary'}, false) }}"]);
        $unnamed = PageContractTwig::forms()->createNamedBuilder('', FormType::class)->add('x', TextType::class)->getForm()->createView();
        self::assertStringContainsString('form="nowo-ui-section-form"', $twig->render('t', ['form' => $unnamed, 'actions' => [new Action('custom', 'Go', '/go')]]));
        $withId = PageContractTwig::forms()->createNamedBuilder('n', FormType::class, null, ['attr' => ['id' => 'own']])->add('x', TextType::class)->getForm()->createView();
        $own = $twig->render('t', ['form' => $withId, 'actions' => [new Action('custom', 'Go', '/go')]]);
        self::assertStringContainsString('form="own"', $own);
        self::assertStringContainsString('<i>x</i>', $own);
        $bare = new Environment(new \Twig\Loader\ArrayLoader([]));
        $bare->addFilter(new \Twig\TwigFilter('trans', static fn (string $key): string => $key));
        $fs = new \Twig\Loader\FilesystemLoader();
        $fs->addPath(PageContractTwig::root().'/src/Resources/views', 'NowoUiKitBundle');
        $bare->setLoader(new \Twig\Loader\ChainLoader([new \Twig\Loader\ArrayLoader(['t' => "{{ include('@NowoUiKitBundle/partials/_tabs.html.twig', {items: [], level: 'secondary', aria_label: 'S'}) }}"]), $fs]));
        self::assertStringContainsString('<nav class="nowo-ui-tabs nowo-ui-tabs--secondary" aria-label="S">', $bare->render('t'));
    }

    public function testListCaptionAndActionsColumnPosition(): void
    {
        $page = new ListPage(new PageHeader('Orders'), [new Column('id'), new Column('total', 'Total', align: 'end')], [['id' => 1, 'total' => '9.00']], rowActions: [Action::view('/v')], caption: '1 order', actionsPosition: 'start', captionVisible: false);
        $html = PageContractTwig::create([], ['classes' => ['table_caption' => 'app-caption']])->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);

        self::assertStringContainsString('<caption class="nowo-ui-table__caption app-caption visually-hidden">1 order</caption>', $html);
        self::assertMatchesRegularExpression('#<tr class="nowo-ui-table__head-row">\s*<th scope="col" class="nowo-ui-table__head-cell nowo-ui-table__actions nowo-ui-cell--end">Actions</th>#', $html);
        self::assertMatchesRegularExpression('#<tr class="nowo-ui-table__row">\s*<td class="nowo-ui-table__cell nowo-ui-table__actions nowo-ui-cell--end">#', $html);
        self::assertStringContainsString('<th scope="col" class="nowo-ui-table__head-cell nowo-ui-cell--end">Total</th>', $html);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ListPage actionsPosition "middle"');
        new ListPage(new PageHeader('Orders'), actionsPosition: 'middle');
    }

    public function testBreadcrumbsModes(): void
    {
        $tpl = ['t' => "{% include '@NowoUiKitBundle/partials/_breadcrumbs.html.twig' with {header: header} only %}"];
        $empty = PageContractTwig::create($tpl);
        $empty->addFunction(new TwigFunction('breadcrumb_render', static fn (Environment $env, ?string $c = null): string => '  ', ['is_safe' => ['html'], 'needs_environment' => true]));
        $kit = PageContractTwig::create($tpl);
        $kit->addFunction(new TwigFunction('breadcrumb_render', static fn (Environment $env, ?string $c = null): string => '<nav class="kit"></nav>', ['is_safe' => ['html'], 'needs_environment' => true]));
        $crumbs = [new Crumb('Home', '/'), new Crumb('Here')];

        // auto: an empty kit trail falls back to the page crumbs
        self::assertStringContainsString('<span class="nowo-ui-breadcrumbs__current" aria-current="page">Here</span>', $empty->render('t', ['header' => new PageHeader('T', breadcrumbs: $crumbs)]));
        // kit: never the page crumbs
        self::assertSame('', trim($empty->render('t', ['header' => new PageHeader('T', breadcrumbs: $crumbs, breadcrumbsMode: 'kit')])));
        // own: the page crumbs even when the kit renders a trail
        $own = $kit->render('t', ['header' => new PageHeader('T', breadcrumbs: $crumbs, breadcrumbsMode: 'own')]);
        self::assertStringContainsString('aria-current="page">Here</span>', $own);
        self::assertStringNotContainsString('class="kit"', $own);
        // the kit wrapper carries no slot host classes
        $themed = PageContractTwig::create($tpl, ['classes' => ['breadcrumbs' => 'app-crumbs']]);
        $themed->addFunction(new TwigFunction('breadcrumb_render', static fn (Environment $env, ?string $c = null): string => '<nav class="kit"></nav>', ['is_safe' => ['html'], 'needs_environment' => true]));
        self::assertSame('<div class="nowo-ui-breadcrumbs" data-nowo-ui-breadcrumbs="kit"><nav class="kit"></nav></div>', trim($themed->render('t', ['header' => new PageHeader('T')])));

        $header = (new PageHeader('T', breadcrumbsMode: 'own'))->withActions([])->withTabs(null);
        self::assertSame('own', $header->breadcrumbsMode);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid PageHeader breadcrumbsMode "none"');
        new PageHeader('T', breadcrumbsMode: 'none');
    }

    public function testFlashesInputIsNamespacedInsideScaffolds(): void
    {
        $twig = PageContractTwig::create(['t' => "{{ include('@NowoUiKitBundle/partials/_flashes.html.twig') }}"]);

        self::assertStringContainsString('>Saved.</div>', $twig->render('t'));
        self::assertStringContainsString('>Direct</div>', $twig->render('t', ['flashes' => ['info' => ['Direct']]]));
        self::assertStringContainsString('>Override</div>', $twig->render('t', ['flash_messages' => ['info' => ['Override']], 'flashes' => ['info' => ['Ignored']]]));
        // a page scaffold (nowo_ui_page_type) with a 1.x `flashes` variable still consumes the session flashes
        $html = $twig->render('t', ['flashes' => ['info' => ['Leaked']], 'nowo_ui_page_type' => 'list']);
        self::assertStringContainsString('>Saved.</div>', $html);
        self::assertStringNotContainsString('Leaked', $html);
    }

    public function testAuthFooterOnlyWithContentAndJsFreePages(): void
    {
        $twig = PageContractTwig::create();
        $html = $twig->render('@NowoUiKit/page/auth.html.twig', ['page' => new AuthPage('Down for maintenance', scripts: false)]);
        self::assertStringNotContainsString('<footer', $html);
        self::assertStringNotContainsString('<script', $html);

        $scripts = $twig->render('@NowoUiKit/page/auth.html.twig', ['page' => new AuthPage('Sign in')]);
        self::assertStringContainsString('<script src="/bundles/nowouikit/js/nowo-ui-theme-boot.js" data-nowo-ui-theme-boot></script>', $scripts);
        foreach (['modal', 'confirm', 'dialog', 'toast', 'tabs', 'theme'] as $name) {
            self::assertStringContainsString('<script src="/bundles/nowouikit/js/nowo-ui-'.$name.'.js" defer></script>', $scripts);
        }

        $child = "{% extends '@NowoUiKit/page/auth.html.twig' %}{% block nowo_ui_auth_footer %}<p>Own footer</p>{% endblock %}";
        $own = PageContractTwig::create(['child' => $child])->render('child', ['page' => new AuthPage('Sign in')]);
        self::assertMatchesRegularExpression('#<footer class="nowo-ui-auth-panel__footer">\s*<p>Own footer</p>\s*</footer>#', $own);
    }

    public function testScriptsPartialForHostLayouts(): void
    {
        $request = new \Symfony\Component\HttpFoundation\Request([], [], ['csp_nonce' => 'n0']);
        $twig = PageContractTwig::create(['t' => "{{ include(nowo_ui_partial('scripts')) }}", 'only' => "{{ include(nowo_ui_partial('scripts'), {script_names: ['dialog'], nonce: 'x'}) }}"], request: $request);

        self::assertSame(6, substr_count($twig->render('t'), 'nonce="n0"'));
        self::assertSame('<script src="/bundles/nowouikit/js/nowo-ui-dialog.js" defer nonce="x"></script>', trim($twig->render('only')));
    }

    public function testTablePartialStandaloneAndEmbedded(): void
    {
        $table = new Table([new Column('sku', 'SKU'), new Column('qty', 'Qty', align: 'end')], [['sku' => 'A-1', 'qty' => 3]], [Action::delete('/l/1', 'tok')], caption: 'Lines', responsive: 'scroll', attributes: ['data-testid' => 'lines']);
        $twig = PageContractTwig::create([
            't' => "{{ include(nowo_ui_partial('table'), {table: table}, false) }}",
            'e' => "{% embed '@NowoUiKitBundle/partials/_table.html.twig' with {table: table} only %}{% block nowo_ui_table_row %}<td data-own>{{ row.sku }}</td>{% endblock %}{% endembed %}",
        ]);
        $html = $twig->render('t', ['table' => $table]);
        self::assertStringContainsString('<table class="nowo-ui-table nowo-ui-table--striped table table-striped table-hover align-middle" data-testid="lines">', $html);
        self::assertStringContainsString('<caption class="nowo-ui-table__caption">Lines</caption>', $html);
        self::assertStringContainsString('data-label="Qty">3</td>', $html);
        self::assertStringContainsString('data-nowo-ui-region="confirms"', $html);
        self::assertStringContainsString('<td data-own>A-1</td>', $twig->render('e', ['table' => $table]));

        self::assertSame('desc', (new Table(sort: 'qty'))->nextDirection('qty'));
        self::assertSame([], (new Table(rowActions: static fn (): array => []))->rowActions([]));
        foreach ([['actionsAlign' => 'left'], ['actionsPosition' => 'mid'], ['responsive' => 'grid'], ['direction' => 'up']] as $bad) {
            try {
                new Table(...$bad);
                self::fail('expected an exception for '.key($bad));
            } catch (\InvalidArgumentException $e) {
                self::assertStringStartsWith('Invalid Table '.key($bad), $e->getMessage());
            }
        }
    }

    public function testFormPageBodyOnlyMode(): void
    {
        $child = "{% extends '@NowoUiKit/page/form.html.twig' %}{% block nowo_ui_form_body %}<div data-live-component></div>{% endblock %}";
        $twig = PageContractTwig::create(['child' => $child]);
        $html = $twig->render('child', ['page' => new FormPage(new PageHeader('Edit'), null)]);
        self::assertStringContainsString('<div data-live-component></div>', $html);
        self::assertStringNotContainsString('<form', $html);
        self::assertStringNotContainsString('nowo-ui-form__actions', $html);

        $form = PageContractTwig::forms()->createNamedBuilder('item', FormType::class)->add('name', TextType::class)->getForm()->createView();
        $noTag = PageContractTwig::create()->render('@NowoUiKit/page/form.html.twig', ['page' => new FormPage(new PageHeader('Edit'), $form, formThemes: ['form_div_layout.html.twig'], formTag: false)]);
        self::assertStringContainsString('name="item[name]"', $noTag);
        self::assertStringNotContainsString('<form', $noTag);
    }

    public function testWizardGuestLayoutAndProgressPartial(): void
    {
        $page = new WizardPage(new PageHeader('Restore'), [new WizardStep('a', 'Upload'), new WizardStep('b', 'Restore')], 'b', guest: true);
        $html = PageContractTwig::create()->render('@NowoUiKit/page/wizard.html.twig', ['page' => $page]);
        self::assertStringContainsString('nowo-ui-page--guest', $html);
        self::assertStringContainsString('nowo-ui-page--admin', PageContractTwig::create()->render('@NowoUiKit/page/wizard.html.twig', ['page' => new WizardPage(new PageHeader('Setup'), [new WizardStep('a', 'A')], 'a')]));

        $twig = PageContractTwig::create([
            'p' => "{{ include(nowo_ui_partial('progress'), {percent: 140, label: 'Restoring', id: 'r'}, false) }}",
            'i' => "{{ include(nowo_ui_partial('progress'), {attr: {'data-x': '1'}}, false) }}",
        ], ['classes' => ['progress_meter' => 'app-meter']]);
        $p = $twig->render('p');
        self::assertStringContainsString('<label class="nowo-ui-progress-field__label" for="r">Restoring</label>', $p);
        self::assertStringContainsString('<progress id="r" class="nowo-ui-progress-meter app-meter" max="100" value="100">100 %</progress>', $p);
        self::assertStringContainsString('<span class="nowo-ui-progress-field__value" aria-hidden="true">100 %</span>', $p);
        $i = $twig->render('i');
        self::assertStringContainsString('<div class="nowo-ui-progress-field nowo-ui-progress-field--indeterminate" data-x="1">', $i);
        self::assertStringContainsString('<progress id="nowo-ui-progress" class="nowo-ui-progress-meter app-meter" max="100">Progress</progress>', $i);
    }

    public function testSelfContainedStatusLayout(): void
    {
        $request = new \Symfony\Component\HttpFoundation\Request([], [], ['csp_nonce' => 'abc']);
        $page = new AuthPage('Back soon', intro: 'A restore is in progress.', layout: '@NowoUiKit/layout/status.html.twig', scripts: false);
        $html = PageContractTwig::create([], ['tokens' => ['primary' => '#123456']], request: $request)->render('@NowoUiKit/page/auth.html.twig', ['page' => $page]);

        self::assertStringContainsString('<style nonce="abc" data-nowo-ui-status-css>', $html);
        self::assertStringContainsString('--nowo-ui-primary: #123456;', $html);
        self::assertStringContainsString('<main class="nowo-ui-status" id="nowo-ui-content">', $html);
        self::assertStringContainsString('A restore is in progress.', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('<link', $html);
        self::assertStringNotContainsString('Saved.', $html);
    }

    public function testActionTitleRemoteDialogAndIconSetNone(): void
    {
        $twig = PageContractTwig::create();
        $html = $twig->render('@NowoUiKitBundle/partials/_action.html.twig', ['action' => new Action('delete', 'Delete', '/x', disabled: true, title: 'Used by 3 orders')]);
        self::assertMatchesRegularExpression('#^<span class="nowo-ui-action__tip" title="Used by 3 orders">\s*<a class="[^"]*is-disabled" href="/x" title="Used by 3 orders" aria-disabled="true" tabindex="-1">#', trim($html));
        $row = $twig->render('@NowoUiKitBundle/partials/_action.html.twig', ['action' => new Action('edit', 'Edit', '/e', title: 'Edit the order'), 'context' => 'row']);
        self::assertStringContainsString('title="Edit the order" aria-label="Edit"', $row);

        $remote = $twig->render('@NowoUiKitBundle/partials/_action.html.twig', ['action' => new Action('edit', 'Edit', '/items/7/edit', dialogId: 'edit-dialog')]);
        self::assertStringContainsString('href="/items/7/edit" data-nowo-ui-dialog-open="edit-dialog" data-nowo-ui-dialog-src="/items/7/edit" aria-haspopup="dialog" aria-controls="edit-dialog">', $remote);

        $none = PageContractTwig::create();
        $none->addGlobal('nowo_ui_kit_icon_set', 'none');
        $text = $none->render('@NowoUiKitBundle/partials/_action.html.twig', ['action' => new Action('export', 'Export', '/x', icon: 'export')]);
        self::assertSame(1, substr_count($text, 'Export'));
        $inner = $none->render('@NowoUiKitBundle/partials/_action_inner.html.twig', ['display' => 'icon_text', 'icon_name' => 'edit', 'label' => 'Edit']);
        self::assertSame(1, substr_count($inner, 'Edit'));
        $filters = PageContractTwig::forms()->createNamedBuilder('f', FormType::class, null, ['method' => 'GET'])->add('q', TextType::class, ['required' => false])->getForm()->createView();
        $list = $none->render('@NowoUiKit/page/list.html.twig', ['page' => new ListPage(new PageHeader('L'), filters: $filters, filtersSubmit: new Action('filter', 'Go', icon: 'filter'))]);
        self::assertMatchesRegularExpression('#nowo-ui-filters__submit">Go</button>#', $list);
    }

    public function testDetailItemBadge(): void
    {
        $page = new ShowPage(new PageHeader('Order'), [new DetailSection('Payment', [new DetailItem('Status', 'Paid', badge: 'success'), new DetailItem('Gift', null, badge: 'info')])]);
        $html = PageContractTwig::create()->render('@NowoUiKit/page/show.html.twig', ['page' => $page]);
        self::assertStringContainsString('<span class="nowo-ui-badge nowo-ui-badge--success badge bg-success-subtle text-success-emphasis border border-success-subtle">Paid</span>', $html);
        self::assertStringContainsString('<span class="nowo-ui-muted text-muted">—</span>', $html);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DetailItem badge "green"');
        new DetailItem('Status', 'Paid', badge: 'green');
    }

    public function testTableAccessibleName(): void
    {
        $html = PageContractTwig::create()->render('@NowoUiKit/page/list.html.twig', ['page' => new ListPage(new PageHeader('Orders'), [new Column('id')], [['id' => 1]])]);
        self::assertMatchesRegularExpression('#<table class="[^"]*" aria-label="Orders">#', $html);
        $captioned = PageContractTwig::create()->render('@NowoUiKit/page/list.html.twig', ['page' => new ListPage(new PageHeader('Orders'), [new Column('id')], [['id' => 1]], caption: 'All orders')]);
        self::assertStringNotContainsString('aria-label="Orders"', $captioned);
        $own = PageContractTwig::create(['t' => "{{ include(nowo_ui_partial('table'), {table: table}, false) }}"])->render('t', ['table' => new Table([new Column('id')], [['id' => 1]], label: 'Lines')]);
        self::assertStringContainsString('aria-label="Lines"', $own);
    }

    public function testIconSetNoneRendersTextOnlyActions(): void
    {
        $none = PageContractTwig::create();
        $none->addGlobal('nowo_ui_kit_icon_set', 'none');
        $row = $none->render('@NowoUiKitBundle/partials/_action.html.twig', ['action' => Action::edit('/e'), 'context' => 'row']);
        self::assertStringContainsString('<span class="nowo-ui-action__label">Edit</span>', $row);
        self::assertStringNotContainsString('visually-hidden', $row);
        self::assertStringNotContainsString('nowo-ui-icon--text', $row);
        $iconOnly = $none->render('@NowoUiKitBundle/partials/_action.html.twig', ['action' => new Action('export', 'Export', '/x', display: 'icon')]);
        self::assertSame(1, substr_count($iconOnly, 'Export</span>'));
        self::assertStringNotContainsString('nowo-ui-btn--icon', $iconOnly);
    }

    public function testFormThemeChecksAndInputs(): void
    {
        $form = PageContractTwig::forms()->createNamedBuilder('prefs', FormType::class)
            ->add('name', TextType::class, ['help' => 'Shown publicly.'])
            ->add('bio', TextareaType::class, ['required' => false])
            ->add('plan', ChoiceType::class, ['choices' => ['Free' => 'free', 'Pro' => 'pro']])
            ->add('mode', ChoiceType::class, ['choices' => ['Light' => 'l', 'Dark' => 'd'], 'expanded' => true])
            ->add('news', CheckboxType::class, ['required' => false, 'help' => 'Monthly.'])
            ->add('quiet', CheckboxType::class, ['required' => false, 'label' => false])
            ->add('raw', CheckboxType::class, ['required' => false, 'label' => 'Raw', 'translation_domain' => false])
            ->getForm()->createView();
        $twig = PageContractTwig::create(['t' => "{% form_theme form '@NowoUiKit/form/nowo_ui_layout.html.twig' %}{{ form_widget(form) }}"], ['classes' => ['check' => 'app-check']], 'none');
        $html = $twig->render('t', ['form' => $form]);

        self::assertStringContainsString('class="nowo-ui-input"', $html);
        self::assertStringContainsString('<textarea id="prefs_bio" name="prefs[bio]" class="nowo-ui-input nowo-ui-input--full">', $html);
        self::assertStringContainsString('<select id="prefs_plan" name="prefs[plan]" class="nowo-ui-input">', $html);
        self::assertMatchesRegularExpression('#<label class="nowo-ui-check app-check"><input type="checkbox" id="prefs_news" name="prefs\[news\]" aria-describedby="prefs_news_help" class="nowo-ui-check__input" value="1" /><span class="nowo-ui-check__label">News</span>\s*</label>#', $html);
        self::assertMatchesRegularExpression('#<label class="nowo-ui-check app-check"><input type="radio" id="prefs_mode_0" name="prefs\[mode\]" required="required" class="nowo-ui-check__input" value="l" /><span class="nowo-ui-check__label">Light</span>#', $html);
        self::assertStringContainsString('<span class="nowo-ui-check__label"></span>', $html);
        self::assertStringContainsString('<span class="nowo-ui-check__label">Raw</span>', $html);
        self::assertStringContainsString('id="prefs_news_help" class="help-text"', $html);
        self::assertSame('form-check-input', \Nowo\UiKitBundle\Theme\ThemeSlots::defaults('check_input', 'bootstrap5'));
    }

    public function testCssAlignsHeaderCellsAndPaintsCode(): void
    {
        $css = (string) file_get_contents(PageContractTwig::root().'/src/Resources/public/css/nowo-ui.unlayered.css');
        // the alignment helpers must beat `.nowo-ui-table th, .nowo-ui-table td { text-align: left }` (0,1,1)
        self::assertMatchesRegularExpression('/\.nowo-ui-table :is\(th, td\)\.nowo-ui-cell--end \{\s*text-align: end;/', $css);
        self::assertMatchesRegularExpression('/\.nowo-ui-table :is\(th, td\)\.nowo-ui-cell--center \{\s*text-align: center;/', $css);
        self::assertStringContainsString('background: var(--nowo-ui-code-bg, color-mix(in srgb, var(--nowo-ui-text) 7%, var(--nowo-ui-surface)));', $css);
    }
}
