<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\View;

use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\AuthPage;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\ConfirmPage;
use Nowo\UiKitBundle\View\Crumb;
use Nowo\UiKitBundle\View\DashboardPage;
use Nowo\UiKitBundle\View\DetailItem;
use Nowo\UiKitBundle\View\DetailSection;
use Nowo\UiKitBundle\View\EmptyState;
use Nowo\UiKitBundle\View\FormPage;
use Nowo\UiKitBundle\View\KpiTile;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\Link;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\SettingsPage;
use Nowo\UiKitBundle\View\SettingsSection;
use Nowo\UiKitBundle\View\ShowPage;
use Nowo\UiKitBundle\View\WizardPage;
use Nowo\UiKitBundle\View\WizardStep;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormView;

final class ViewModelsTest extends TestCase
{
    public function testLabel(): void
    {
        $label = new Label('items.title', 'admin', ['%n%' => 1]);
        self::assertSame(['items.title', 'admin', ['%n%' => 1]], [$label->key, $label->domain, $label->params]);
        self::assertSame(['%n%' => 2, '%m%' => 3], $label->withParams(['%n%' => 2, '%m%' => 3])->params);
        self::assertSame('NowoUiKitBundle', Label::kit('action.edit')->domain);
        self::assertSame('messages', (new Label('x'))->domain);

        $this->expectException(\InvalidArgumentException::class);
        new Label(' ');
    }

    public function testActionDefaultsFactoriesAndHash(): void
    {
        $create = Action::create('/items/new');
        self::assertSame(['create', '/items/new', 'GET', 'primary'], [$create->kind, $create->href, $create->method, $create->resolvedVariant()]);
        self::assertFalse($create->isPost());
        self::assertNull($create->confirmId());
        self::assertSame('secondary', Action::edit('/e', 'Edit it')->resolvedVariant());
        self::assertSame('view', Action::view('/v')->kind);
        self::assertSame('primary', (new Action('save'))->resolvedVariant());
        self::assertSame('link', (new Action('custom', 'X', variant: 'link'))->resolvedVariant());

        $route = Action::route('export', 'items_export', ['format' => 'csv'], new Label('export.csv', 'admin'));
        self::assertSame(['items_export', ['format' => 'csv'], null], [$route->route, $route->params, $route->href]);

        $delete = Action::delete('/items/1', 'tok');
        self::assertTrue($delete->isPost());
        self::assertSame('danger', $delete->resolvedVariant());
        self::assertSame('confirm.delete_title', $delete->confirm?->title instanceof Label ? $delete->confirm->title->key : null);
        self::assertMatchesRegularExpression('/^nowo-ui-confirm-[0-9a-f]{12}$/', (string) $delete->confirmId());
        self::assertSame($delete->confirmId(), Action::delete('/items/1')->confirmId());
        self::assertNotSame($delete->confirmId(), Action::delete('/items/2')->confirmId());
        self::assertSame('my-dialog', Action::delete('/x', confirm: new Confirm('Sure?', id: 'my-dialog'))->confirmId());

        $hash = (new Action('delete', 'Del', '/x', method: 'delete', csrfToken: 't', confirm: new Confirm('Sure?', id: 'c1'), icon: 'trash', variant: 'danger', attributes: ['data-x' => '1'], csrfField: '_csrf', modalId: 'm', disabled: true, visible: false))->toArray();
        self::assertSame([
            'kind' => 'delete', 'label' => 'Del', 'href' => '/x', 'route' => null, 'params' => [], 'method' => 'DELETE',
            'csrf_token' => 't', 'csrf_field' => '_csrf', 'confirm_id' => 'c1', 'icon' => 'trash', 'variant' => 'danger',
            'attr' => ['data-x' => '1'], 'modal_id' => 'm', 'disabled' => true, 'visible' => false,
        ], $hash);
    }

    /**
     * @return iterable<string, array{\Closure(): object, string}>
     */
    public static function invalidModels(): iterable
    {
        yield 'action kind' => [static fn (): object => new Action('explode'), 'Invalid Action kind'];
        yield 'action variant' => [static fn (): object => new Action('edit', variant: 'moss'), 'Invalid Action variant'];
        yield 'action method' => [static fn (): object => new Action('edit', method: 'HEAD'), 'Invalid Action method'];
        yield 'action href+route' => [static fn (): object => new Action('edit', href: '/x', route: 'x'), 'either href or route'];
        yield 'action on* attr' => [static fn (): object => new Action('edit', attributes: ['onclick' => 'x()']), 'Invalid or unsafe attribute "onclick"'];
        yield 'action non-scalar attr' => [static fn (): object => new Action('edit', attributes: ['data-x' => ['a']]), 'must be a scalar'];
        yield 'link attr' => [static fn (): object => new Link('x', '/x', ['onmouseover' => 'y']), 'Invalid or unsafe attribute'];
        yield 'confirm variant' => [static fn (): object => new Confirm('x', variant: 'moss'), 'Invalid Confirm variant'];
        yield 'confirm id' => [static fn (): object => new Confirm('x', id: '1 2'), 'Invalid Confirm id'];
        yield 'column key' => [static fn (): object => new Column(''), 'non-empty key'];
        yield 'column align' => [static fn (): object => new Column('a', align: 'left'), 'Invalid Column align'];
        yield 'kpi variant' => [static fn (): object => new KpiTile('a', 1, variant: 'moss'), 'Invalid KpiTile variant'];
        yield 'settings section id' => [static fn (): object => new SettingsSection('a b', 'A'), 'Invalid SettingsSection id'];
        yield 'wizard step id' => [static fn (): object => new WizardStep('-', 'A'), 'Invalid WizardStep id'];
        yield 'header actions type' => [static fn (): object => new PageHeader('T', actions: ['x']), 'PageHeader actions: expected'];
        yield 'list direction' => [static fn (): object => new ListPage(new PageHeader('T'), direction: 'up'), 'Invalid ListPage direction'];
        yield 'list static actions' => [static fn (): object => new ListPage(new PageHeader('T'), rowActions: [new Crumb('x')]), 'ListPage rowActions: expected'];
        yield 'settings current' => [static fn (): object => new SettingsPage(new PageHeader('T'), [new SettingsSection('a', 'A')], 'b'), 'current section "b"'];
        yield 'wizard current' => [static fn (): object => new WizardPage(new PageHeader('T'), [new WizardStep('a', 'A')], 'b'), 'current step "b"'];
        yield 'confirm page GET' => [static fn (): object => new ConfirmPage(new PageHeader('T'), new Confirm('x'), Action::edit('/x')), 'non-GET action'];
    }

    /**
     * @param \Closure(): object $factory
     */
    #[DataProvider('invalidModels')]
    public function testRejectsInvalidModels(\Closure $factory, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        $factory();
    }

    public function testSmallValueObjects(): void
    {
        $confirm = Confirm::delete('Really?');
        self::assertSame(['Really?', 'danger'], [$confirm->message, $confirm->variant]);
        self::assertInstanceOf(Label::class, $confirm->confirmLabel);
        self::assertSame('/', (new Crumb('Home', '/'))->href);
        self::assertSame(['data-turbo' => false], (new Link('Back', '/', ['data-turbo' => false]))->attributes);
        $column = new Column('qty', 'Qty', true, 'end', 'w-1', true);
        self::assertSame(['qty', true, 'end', 'w-1', true], [$column->key, $column->sortable, $column->align, $column->class, $column->required]);
        $empty = new EmptyState('Nothing', 'Add one', Action::create('/new'));
        self::assertSame('create', $empty->action?->kind);
        $section = new DetailSection('General', [new DetailItem('Name', 'A'), new DetailItem('Site', 'x', '/x')], 'general');
        self::assertCount(2, $section->items);
        self::assertSame('/x', $section->items[1]->href);
        $kpi = new KpiTile('Users', 42, 'last 7 days', '/users', 'success');
        self::assertSame([42, 'success'], [$kpi->value, $kpi->variant]);
    }

    public function testPageHeaderAcceptsIterables(): void
    {
        $header = new PageHeader('T', 'I', new \ArrayIterator([Action::create('/n')]), (static function () {
            yield new Crumb('Home', '/');
        })(), 'admin');
        self::assertCount(1, $header->actions);
        self::assertCount(1, $header->breadcrumbs);
        self::assertSame('admin', $header->breadcrumbCollection);
    }

    public function testListPage(): void
    {
        $header = new PageHeader('Items');
        $rows = (static function () {
            yield ['id' => 1];
            yield ['id' => 2];
        })();
        $page = new ListPage($header, [new Column('id')], $rows, rowActions: static fn (array $row, int|string $key): array => [Action::edit('/items/'.$row['id'])], sort: 'id', direction: 'desc', bundle: 'NowoXBundle', layout: 'x.html.twig', caption: 'All');
        self::assertSame([['id' => 1], ['id' => 2]], $page->rows);
        self::assertTrue($page->hasRowActions());
        self::assertSame('/items/2', $page->rowActions(['id' => 2], 1)[0]->href);
        self::assertSame('asc', $page->nextDirection('id'));
        self::assertSame('asc', $page->nextDirection('name'));
        self::assertSame(['NowoXBundle', 'x.html.twig', 'All'], [$page->bundle, $page->layout, $page->caption]);

        $static = new ListPage($header, rows: [['id' => 1]], rowActions: [Action::view('/v')], sort: 'id');
        self::assertSame('desc', $static->nextDirection('id'));
        self::assertSame('view', $static->rowActions(['id' => 1])[0]->kind);
        self::assertFalse((new ListPage($header))->hasRowActions());
        self::assertSame([], (new ListPage($header))->rowActions([]));

        $bad = new ListPage($header, rowActions: static fn (): string => 'nope');
        self::assertSame([], $bad->rowActions([]));
    }

    public function testShowFormDashboardConfirmAuthPages(): void
    {
        $header = new PageHeader('T');
        $show = new ShowPage($header, [new DetailSection('S')], [new DetailItem('Created', '2026-10-16')], 'NowoXBundle');
        self::assertCount(1, $show->sections);
        self::assertCount(1, $show->meta);

        $form = new FormView();
        $formPage = new FormPage($header, $form, 'Save', '/back', 'Back', [Action::view('/v')], Action::delete('/d', 't'), 'NowoXBundle', 'l.html.twig');
        self::assertSame($form, $formPage->form);
        self::assertCount(1, $formPage->secondaryActions);
        self::assertSame('delete', $formPage->deleteAction?->kind);

        $dash = new DashboardPage($header, [new KpiTile('Users', 3)]);
        self::assertCount(1, $dash->kpis);

        $confirm = new ConfirmPage($header, new Confirm('Sure?'), Action::delete('/d', 't'), '/back');
        self::assertSame('/back', $confirm->cancelHref);

        $auth = new AuthPage('Sign in', $form, 'Welcome', 'Acme', 'Bad credentials', 'Go', [new Link('Forgot?', '/reset')], 'NowoAuthKitBundle');
        self::assertCount(1, $auth->links);
        self::assertNull($auth->layout);
    }

    public function testSettingsPage(): void
    {
        $header = new PageHeader('Settings');
        $tabs = new SettingsPage($header, [new SettingsSection('general', 'General', '/s/general'), new SettingsSection('mail', 'Mail', '/s/mail', new FormView(), 'Intro', 'Save mail')], 'mail');
        self::assertTrue($tabs->hasTabs());
        self::assertSame(['mail'], array_map(static fn (SettingsSection $s): string => $s->id, $tabs->visibleSections()));

        $stacked = new SettingsPage($header, [new SettingsSection('a', 'A'), new SettingsSection('b', 'B')]);
        self::assertFalse($stacked->hasTabs());
        self::assertCount(2, $stacked->visibleSections());
        self::assertFalse((new SettingsPage($header, [new SettingsSection('a', 'A', '/a')]))->hasTabs());
    }

    public function testWizardPage(): void
    {
        $steps = [new WizardStep('db', 'Database', '/w/db'), new WizardStep('admin', 'Admin'), new WizardStep('done', 'Done')];
        $page = new WizardPage(new PageHeader('Setup'), $steps, 'admin', new FormView(), '/w/db', 'Next', 'Back');
        self::assertSame(1, $page->currentIndex());
        self::assertSame(['done', 'current', 'upcoming'], array_map($page->status(...), $page->steps));
        self::assertSame('upcoming', $page->status(new WizardStep('db', 'Database')));
    }
}
