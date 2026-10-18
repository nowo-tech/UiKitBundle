<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Test;

use Nowo\UiKitBundle\Test\PageFixture;
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
use Nowo\UiKitBundle\View\Pagination;
use Nowo\UiKitBundle\View\SettingsPage;
use Nowo\UiKitBundle\View\SettingsSection;
use Nowo\UiKitBundle\View\ShowPage;
use Nowo\UiKitBundle\View\WizardPage;
use Nowo\UiKitBundle\View\WizardStep;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;

/**
 * View models for every scaffold, shared by the contract self-tests. `$label` turns plain strings into labels of
 * a translation domain (the bundle-like fixture uses catalogue keys).
 */
final class ScaffoldFixtures
{
    /**
     * @param array<string, string> $templates scaffold => template name
     *
     * @return array<string, PageFixture>
     */
    public static function all(array $templates, ?string $bundle = null): array
    {
        $header = static fn (string $title, array $actions = []): PageHeader => new PageHeader($title, 'Every item of the catalogue', $actions, [new Crumb('Home', '/'), new Crumb($title)]);

        return [
            'list' => new PageFixture($templates['list'], ['page' => new ListPage(
                $header('Items', [Action::create('/items/new')]),
                [new Column('name', 'Name', true), new Column('qty', 'Quantity', false, 'end'), new Column('owner', 'Owner')],
                [['name' => 'Alpha', 'qty' => 3, 'owner' => 'Ann'], ['name' => 'Beta', 'qty' => 1, 'owner' => 'Bob']],
                self::form('filters', ['q' => SearchType::class], 'GET'),
                static fn (array|object $row, int|string $key): array => [Action::edit('/items/'.$key.'/edit'), Action::delete('/items/'.$key, 'tok')],
                new Pagination(1, 2, 5, route: 'items_index'),
                filtersClearHref: '/items',
                sort: 'name',
                bundle: $bundle,
            )]),
            'list_empty' => new PageFixture($templates['list'], ['page' => new ListPage(
                $header('Items'),
                [new Column('name', 'Name')],
                [],
                empty: new EmptyState('No items yet', 'Create the first one.', Action::create('/items/new')),
                bundle: $bundle,
            )]),
            'show' => new PageFixture($templates['show'], ['page' => new ShowPage(
                $header('Item Alpha', [Action::edit('/items/1/edit'), new Action('restore', 'Restore', '/items/1/restore', method: 'POST', form: self::csrf('restore_item')), Action::delete('/items/1', confirm: Confirm::delete(), form: self::csrf('delete_item'))]),
                [new DetailSection('General', [new DetailItem('Name', 'Alpha'), new DetailItem('Quantity', 3), new DetailItem('Active', true)], 'general')],
                [new DetailItem('Created', '2026-10-16')],
                $bundle,
            )]),
            'form' => new PageFixture($templates['form'], ['page' => new FormPage(
                $header('Edit item'),
                self::form('item', ['name' => TextType::class, 'active' => CheckboxType::class]),
                cancelHref: '/items',
                secondaryActions: [Action::view('/items/1'), new Action('export', 'Export', '/items/1/export', icon: 'export', variant: 'outline'), new Action('copy', 'Duplicate', '/items/1/copy', variant: 'link'), new Action('import', 'Import', modalId: 'import-modal')],
                deleteAction: Action::delete('/items/1', 'tok'),
                bundle: $bundle,
            )]),
            'settings' => new PageFixture($templates['settings'], ['page' => new SettingsPage(
                $header('Settings'),
                [new SettingsSection('general', 'General', '/settings/general'), new SettingsSection('mail', 'Mail', '/settings/mail', self::form('mail', ['sender' => TextType::class]), 'Outgoing mail')],
                'mail',
                $bundle,
            )]),
            'dashboard' => new PageFixture($templates['dashboard'], ['page' => new DashboardPage(
                $header('Dashboard'),
                [new KpiTile('Items', '42', 'last 30 days', '/items'), new KpiTile('Errors', '3', null, null, 'danger')],
                $bundle,
            )]),
            'confirm' => new PageFixture($templates['confirm'], ['page' => new ConfirmPage(
                $header('Delete item'),
                Confirm::delete('Alpha will be removed.'),
                Action::delete('/items/1', 'tok'),
                '/items',
                $bundle,
            )]),
            'wizard' => new PageFixture($templates['wizard'], ['page' => new WizardPage(
                $header('Setup'),
                [new WizardStep('account', 'Account', '/setup/account'), new WizardStep('storage', 'Storage'), new WizardStep('done', 'Done')],
                'storage',
                self::form('storage', ['path' => TextType::class]),
                '/setup/account',
                bundle: $bundle,
            )]),
            'auth' => new PageFixture($templates['auth'], ['page' => new AuthPage(
                'Sign in',
                self::form('login', ['username' => TextType::class, 'password' => PasswordType::class]),
                'Use your account.',
                'Acme',
                new Label('empty.default', 'NowoUiKitBundle'),
                links: [new Link('Forgot password?', '/reset')],
                bundle: $bundle,
            )]),
        ];
    }

    /**
     * CSRF-only FormView (one hidden `_token`) backing an action.
     */
    public static function csrf(string $name): FormView
    {
        return Forms::createFormFactoryBuilder()->getFormFactory()->createNamedBuilder($name, FormType::class)->add('_token', HiddenType::class, ['data' => 'tok-'.$name])->getForm()->createView();
    }

    /**
     * @param array<string, class-string> $fields
     */
    public static function form(string $name, array $fields, string $method = 'POST'): FormView
    {
        // Literal (humanized) labels: not catalogue messages, so the strict missing-message rule (2.3) ignores them
        $builder = Forms::createFormFactoryBuilder()->getFormFactory()->createNamedBuilder($name, FormType::class, null, ['method' => $method, 'translation_domain' => false]);
        foreach ($fields as $field => $type) {
            $builder->add($field, $type, ['required' => false]);
        }

        return $builder->getForm()->createView();
    }
}
