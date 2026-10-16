<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

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
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

/**
 * Render tests of every scaffold against the real Twig env (translator, Form extension, ThemeExtension).
 */
final class ScaffoldRenderTest extends TestCase
{
    /** Host theme used by most tests: proves scaffolds read slots. */
    private const THEME = [
        'classes' => [
            'page_header' => 'app-header',
            'table' => 'data-table',
            'table_row' => 'row-hover',
            'btn_primary' => 'btn-moss',
            'form' => 'app-form',
            'kpi' => 'app-kpi',
            'auth_panel' => 'app-auth',
            'wizard_step_active' => 'app-step-on',
            'tab_active' => 'app-tab-on',
            'detail_list' => 'app-dl',
        ],
        'attributes' => ['table' => ['data-controller' => 'table']],
        'bundles' => ['NowoXBundle' => ['classes' => ['table' => 'data-table data-table--dense']]],
    ];

    public function testListScaffold(): void
    {
        $page = new ListPage(
            new PageHeader(new Label('items.title', 'admin'), 'Every item', [Action::create('/items/new')], [new Crumb('Home', '/'), new Crumb('Items')]),
            [new Column('name', 'Name', true), new Column('qty', new Label('Qty', 'admin'), false, 'end', 'w-1', true), new Column('owner')],
            [['name' => 'Alpha', 'qty' => 3, 'owner' => 'Ann'], (object) ['name' => 'Beta <b>', 'qty' => 1, 'owner' => 'Bob']],
            $this->filtersForm(),
            static fn (array|object $row, int|string $key): array => [Action::edit('/items/'.$key.'/edit'), Action::delete('/items/'.$key, 'tok')],
            new Pagination(1, 2, 5, route: 'items_index'),
            filtersClearHref: '/items',
            sort: 'name',
            direction: 'asc',
            caption: 'All items',
            bundle: 'NowoXBundle',
        );

        $html = $this->twig()->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);

        self::assertStringContainsString('<title>items.title</title>', $html);
        self::assertStringContainsString('class="nowo-ui-page-header d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3 app-header"', $html);
        self::assertStringContainsString('<a class="nowo-ui-breadcrumbs__link" href="/">Home</a>', $html);
        self::assertStringContainsString('<a class="nowo-ui-btn nowo-ui-btn--primary btn btn-primary btn-moss nowo-ui-action--create" href="/items/new">', $html);
        self::assertStringContainsString('class="nowo-ui-filters nowo-ui-search d-flex flex-wrap gap-2 mb-3" role="search"', $html);
        self::assertStringContainsString('<a class="nowo-ui-btn nowo-ui-btn--secondary btn btn-outline-secondary" href="/items">Clear</a>', $html);
        // bundle exception replaces the host table class; attributes from the theme
        self::assertStringContainsString('<table class="nowo-ui-table nowo-ui-table--striped table table-hover align-middle data-table data-table--dense" data-controller="table">', $html);
        self::assertStringContainsString('<caption class="nowo-ui-table__caption">All items</caption>', $html);
        self::assertStringContainsString('aria-sort="ascending"', $html);
        self::assertStringContainsString('<a class="nowo-ui-sort nowo-ui-sort--asc" href="/items_index?sort=name&amp;direction=desc">Name</a>', $html);
        self::assertStringContainsString('data-required="1"', $html);
        self::assertStringContainsString('<tr class="nowo-ui-table__row row-hover">', $html);
        self::assertStringContainsString('>Beta &lt;b&gt;</td>', $html);
        self::assertStringContainsString('<span class="visually-hidden">Actions</span>', $html);
        self::assertStringContainsString('href="/items/1/edit"', $html);
        self::assertStringContainsString('data-nowo-confirm-open data-nowo-confirm-target="nowo-ui-confirm-', $html);
        self::assertStringContainsString('<p class="nowo-ui-confirm__message">This action cannot be undone.</p>', $html);
        self::assertStringContainsString('<input type="hidden" name="_token" value="tok">', $html);
        self::assertStringContainsString('Showing 1–2 of 5', $html);
        self::assertStringContainsString('href="/items_index?per_page=2&amp;page=2"', $html);
        $this->assertContract($html);
    }

    public function testListDataBlocksAndEmptyState(): void
    {
        $child = <<<'TWIG'
            {% extends '@NowoUiKit/page/list.html.twig' %}
            {% block nowo_ui_list_head %}<th>CUSTOM HEAD</th>{% endblock %}
            {% block nowo_ui_list_row %}<td>ROW {{ row_key }} {{ row.name }} {{ loop.index }}</td>{% endblock %}
            {% block nowo_ui_list_after %}<p>AFTER</p>{% endblock %}
            TWIG;
        $page = new ListPage(new PageHeader('Items'), [new Column('name')], [['name' => 'A']], rowActions: [Action::view('/v')]);
        $html = $this->twig(['child' => $child])->render('child', ['page' => $page]);

        self::assertStringContainsString('<th>CUSTOM HEAD</th>', $html);
        self::assertStringContainsString('<td>ROW 0 A 1</td>', $html);
        self::assertStringContainsString('<p>AFTER</p>', $html);
        self::assertStringNotContainsString('nowo-ui-pagination', $html);

        $empty = new ListPage(new PageHeader('Items'), [new Column('name')], [], empty: new EmptyState('No items', new Label('empty.default', 'NowoUiKitBundle'), Action::create('/new')));
        $html = $this->twig(['child' => $child])->render('child', ['page' => $empty]);
        self::assertStringContainsString('<p class="nowo-ui-empty__title">No items</p>', $html);
        self::assertStringContainsString('<p class="nowo-ui-empty__message">No items found.</p>', $html);
        self::assertStringContainsString('href="/new"', $html);
        self::assertStringNotContainsString('<table', $html);
        self::assertStringContainsString('<p>AFTER</p>', $html);

        $bare = $this->twig()->render('@NowoUiKit/page/list.html.twig', ['page' => new ListPage(new PageHeader('Items'))]);
        self::assertStringContainsString('<p class="nowo-ui-empty nowo-ui-muted text-muted" data-nowo-ui-region="empty">No items found.</p>', $bare);
    }

    public function testListUsesTheRequestRouteForSortingWithoutPagination(): void
    {
        $request = new Request(['q' => 'x', 'page' => 2], [], ['_route' => 'things', '_route_params' => ['org' => 7]]);
        $page = new ListPage(new PageHeader('Things'), [new Column('name', 'Name', true)], [['name' => 'A']], sort: 'name', direction: 'desc');
        $html = $this->twig(request: $request)->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);

        self::assertStringContainsString('href="/things?org=7&amp;q=x&amp;page=2&amp;sort=name&amp;direction=asc"', $html);
        self::assertStringContainsString('nowo-ui-sort--desc', $html);
    }

    public function testShowScaffold(): void
    {
        $page = new ShowPage(
            new PageHeader('Item #1', actions: [Action::edit('/items/1/edit'), Action::delete('/items/1', 'tok')]),
            [new DetailSection('General', [new DetailItem('Name', 'Alpha'), new DetailItem('Site', 'example.org', '/site'), new DetailItem('Active', true), new DetailItem('Archived', false), new DetailItem('Notes', null), new DetailItem(new Label('Qty', 'admin'), 3)], 'general')],
            [new DetailItem('Created', '2026-10-16')],
        );
        $html = $this->twig()->render('@NowoUiKit/page/show.html.twig', ['page' => $page]);

        self::assertStringContainsString('<section class="nowo-ui-section mb-4 nowo-ui-card card" id="general" data-nowo-ui-region="section">', $html);
        self::assertStringContainsString('<h2 class="nowo-ui-card__title">General</h2>', $html);
        self::assertStringContainsString('<dl class="nowo-ui-detail-list app-dl">', $html);
        self::assertStringContainsString('<dd class="nowo-ui-detail-list__value"><a href="/site">example.org</a></dd>', $html);
        self::assertStringContainsString('<dd class="nowo-ui-detail-list__value">✓</dd>', $html);
        self::assertSame(2, substr_count($html, '<dd class="nowo-ui-detail-list__value">—</dd>'));
        self::assertStringContainsString('nowo-ui-detail-list--meta', $html);
        self::assertStringContainsString('class="nowo-ui-btn nowo-ui-btn--danger btn btn-outline-danger nowo-ui-action--delete"', $html);
        $this->assertContract($html);

        $child = "{% extends '@NowoUiKit/page/show.html.twig' %}{% block nowo_ui_show_sections %}<div>SECTIONS</div>{% endblock %}{% block nowo_ui_show_after %}<table>RELATED</table>{% endblock %}";
        $html = $this->twig(['child' => $child])->render('child', ['page' => new ShowPage(new PageHeader('X'), [new DetailSection(null, [new DetailItem('a', 'b')])])]);
        self::assertStringContainsString('<div>SECTIONS</div>', $html);
        self::assertStringContainsString('<table>RELATED</table>', $html);
        self::assertStringNotContainsString('nowo-ui-detail-list', $html);
    }

    public function testFormScaffold(): void
    {
        $form = $this->entityForm();
        $page = new FormPage(new PageHeader('Edit item'), $form, cancelHref: '/items', secondaryActions: [Action::view('/items/1'), new Action('custom', 'Duplicate', '/dup', variant: 'link')], deleteAction: Action::delete('/items/1', form: $this->csrfForm()), bundle: 'NowoXBundle');
        $html = $this->twig()->render('@NowoUiKit/page/form.html.twig', ['page' => $page]);

        self::assertMatchesRegularExpression('#<form name="item" method="post" class="nowo-ui-form app-form" data-nowo-ui-region="form">#', $html);
        self::assertStringContainsString('<input type="text" id="item_name" name="item[name]"', $html);
        self::assertStringContainsString('<input type="checkbox" id="item_active" name="item[active]"', $html);
        self::assertStringContainsString('<button type="submit" class="nowo-ui-btn nowo-ui-btn--primary btn btn-primary btn-moss">Save</button>', $html);
        self::assertStringContainsString('<a class="nowo-ui-btn nowo-ui-btn--secondary btn btn-outline-secondary" href="/items">Cancel</a>', $html);
        self::assertStringContainsString('class="nowo-ui-btn nowo-ui-btn--link btn btn-link nowo-ui-action--custom" href="/dup"', $html);
        self::assertStringContainsString('<h2 class="nowo-ui-danger-zone__title">Danger zone</h2>', $html);
        self::assertMatchesRegularExpression('#<form name="csrf" method="post" action="/items/1" class="nowo-ui-action__form">\s*<button type="submit" class="nowo-ui-btn nowo-ui-btn--danger btn btn-outline-danger nowo-ui-confirm__submit">Delete</button>#', $html);
        $this->assertContract($html);

        $child = "{% extends '@NowoUiKit/page/form.html.twig' %}{% block nowo_ui_form_body %}{{ form_row(form.name) }}<p>CUSTOM</p>{{ parent() }}{% endblock %}{% block nowo_ui_form_aside %}<aside>ASIDE</aside>{% endblock %}";
        $html = $this->twig(['child' => $child])->render('child', ['page' => new FormPage(new PageHeader('X'), $this->entityForm(), 'Store', '/x', 'Back')]);
        self::assertStringContainsString('<p>CUSTOM</p>', $html);
        self::assertSame(1, substr_count($html, 'id="item_name"'));
        self::assertStringContainsString('id="item_active"', $html);
        self::assertStringContainsString('<aside>ASIDE</aside>', $html);
        self::assertStringContainsString('>Store</button>', $html);
        self::assertStringContainsString('>Back</a>', $html);
    }

    public function testSettingsScaffold(): void
    {
        $page = new SettingsPage(new PageHeader('Settings'), [
            new SettingsSection('general', 'General', '/settings/general'),
            new SettingsSection('mail', new Label('Mail', 'admin'), '/settings/mail', $this->entityForm(), 'Outgoing mail', 'Save mail'),
        ], 'mail');
        $html = $this->twig()->render('@NowoUiKit/page/settings.html.twig', ['page' => $page]);

        self::assertStringContainsString('<nav class="nowo-ui-tabs nav nav-tabs mb-3" aria-label="Sections" data-nowo-ui-region="tabs">', $html);
        // tabs through the `tabs` theme partial (1.x markup: one attribute per line)
        self::assertMatchesRegularExpression('#<a\s+class="nowo-ui-tabs__item nav-link"\s+href="/settings/general"\s*>General</a>#', $html);
        self::assertMatchesRegularExpression('#<a\s+class="nowo-ui-tabs__item nowo-ui-tabs__item--active nav-link active app-tab-on"\s+href="/settings/mail"\s+aria-current="page"\s*>Mail</a>#', $html);
        self::assertStringContainsString('id="nowo-ui-settings-mail"', $html);
        self::assertStringNotContainsString('id="nowo-ui-settings-general"', $html);
        self::assertStringContainsString('<p class="nowo-ui-card__intro">Outgoing mail</p>', $html);
        self::assertStringContainsString('>Save mail</button>', $html);
        $this->assertContract($html);

        $child = "{% extends '@NowoUiKit/page/settings.html.twig' %}{% block nowo_ui_settings_section %}<p>SECTION {{ section.id }}</p>{% endblock %}";
        $html = $this->twig(['child' => $child])->render('child', ['page' => new SettingsPage(new PageHeader('S'), [new SettingsSection('a', 'A'), new SettingsSection('b', 'B')])]);
        self::assertStringContainsString('<p>SECTION a</p>', $html);
        self::assertStringContainsString('<p>SECTION b</p>', $html);
        self::assertStringNotContainsString('<nav class="nowo-ui-tabs', $html);

        $html = $this->twig()->render('@NowoUiKit/page/settings.html.twig', ['page' => new SettingsPage(new PageHeader('S'), [new SettingsSection('a', 'A', form: $this->entityForm())])]);
        self::assertStringContainsString('>Save</button>', $html);
    }

    public function testDashboardScaffold(): void
    {
        $page = new DashboardPage(new PageHeader('Dashboard'), [new KpiTile('Users', 42, 'last 7 days', '/users', 'success'), new KpiTile(new Label('Errors', 'admin'), 0)]);
        $child = "{% extends '@NowoUiKit/page/dashboard.html.twig' %}{% block nowo_ui_dashboard_cards %}<section class=\"{{ nowo_ui_class('card') }}\">CARD</section>{% endblock %}";
        $html = $this->twig(['child' => $child])->render('child', ['page' => $page]);

        self::assertStringContainsString('<div class="nowo-ui-kpi card app-kpi nowo-ui-kpi--success">', $html);
        self::assertStringContainsString('<p class="nowo-ui-kpi__value"><a href="/users">42</a></p>', $html);
        self::assertStringContainsString('<p class="nowo-ui-kpi__hint">last 7 days</p>', $html);
        self::assertStringContainsString('<p class="nowo-ui-kpi__value">0</p>', $html);
        self::assertMatchesRegularExpression('#<div class="nowo-ui-cards" data-nowo-ui-region="cards">\s*<section class="nowo-ui-card card">CARD</section>\s*</div>#', $html);
        $this->assertContract($html);

        $html = $this->twig()->render('@NowoUiKit/page/dashboard.html.twig', ['page' => new DashboardPage(new PageHeader('D'))]);
        self::assertStringNotContainsString('nowo-ui-kpis', $html);
    }

    public function testConfirmScaffold(): void
    {
        $page = new ConfirmPage(new PageHeader('Delete item'), new Confirm('Delete "Alpha"?', 'It cannot be undone.', 'Yes, delete', 'danger', 'Keep it'), new Action('delete', href: '/items/1', method: 'DELETE', csrfToken: 'tok'), '/items');
        $html = $this->twig()->render('@NowoUiKit/page/confirm.html.twig', ['page' => $page]);

        self::assertStringContainsString('<h2 class="nowo-ui-confirm__title">Delete &quot;Alpha&quot;?</h2>', $html);
        self::assertStringContainsString('<a class="nowo-ui-btn nowo-ui-btn--secondary btn btn-outline-secondary" href="/items">Keep it</a>', $html);
        self::assertStringContainsString('<input type="hidden" name="_method" value="DELETE">', $html);
        self::assertStringContainsString('class="nowo-ui-btn nowo-ui-btn--danger btn btn-outline-danger nowo-ui-confirm__submit">Yes, delete</button>', $html);
        $this->assertContract($html);

        $route = new ConfirmPage(new PageHeader('Purge'), new Confirm('Purge?', variant: 'primary'), new Action('custom', 'Purge', route: 'purge', params: ['id' => 3], method: 'POST'));
        $html = $this->twig()->render('@NowoUiKit/page/confirm.html.twig', ['page' => $route]);
        self::assertStringContainsString('action="/purge?id=3"', $html);
        self::assertStringContainsString('>Confirm</button>', $html);
        self::assertStringNotContainsString('nowo-ui-confirm__message', $html);
    }

    public function testWizardScaffold(): void
    {
        $page = new WizardPage(new PageHeader('Setup'), [new WizardStep('db', 'Database', '/setup/db'), new WizardStep('admin', 'Admin user'), new WizardStep('done', 'Done', '/setup/done')], 'admin', $this->entityForm(), '/setup/db');
        $html = $this->twig()->render('@NowoUiKit/page/wizard.html.twig', ['page' => $page]);

        self::assertStringContainsString('<li class="nowo-ui-wizard-steps__item nowo-ui-wizard-steps__item--done"><a href="/setup/db">Database</a></li>', $html);
        self::assertStringContainsString('<li class="nowo-ui-wizard-steps__item nowo-ui-wizard-steps__item--active app-step-on nowo-ui-wizard-steps__item--current" aria-current="step"><span>Admin user</span></li>', $html);
        self::assertStringContainsString('<li class="nowo-ui-wizard-steps__item nowo-ui-wizard-steps__item--upcoming"><span>Done</span></li>', $html);
        self::assertStringContainsString('Step 2 of 3', $html);
        self::assertStringContainsString('href="/setup/db">Back</a>', $html);
        self::assertStringContainsString('>Next</button>', $html);
        $this->assertContract($html);

        $child = "{% extends '@NowoUiKit/page/wizard.html.twig' %}{% block nowo_ui_wizard_step %}<p>STEP {{ step.id }}</p>{% endblock %}";
        $html = $this->twig(['child' => $child])->render('child', ['page' => new WizardPage(new PageHeader('S'), [new WizardStep('a', 'A')], 'a', nextLabel: 'Go', backLabel: 'Prev')]);
        self::assertStringContainsString('<p>STEP a</p>', $html);

        $html = $this->twig()->render('@NowoUiKit/page/wizard.html.twig', ['page' => new WizardPage(new PageHeader('S'), [new WizardStep('a', 'A')], 'a', $this->entityForm(), '/back', 'Go', 'Prev')]);
        self::assertStringContainsString('>Go</button>', $html);
        self::assertStringContainsString('>Prev</a>', $html);
    }

    public function testAuthScaffoldUsesTheGuestLayout(): void
    {
        $form = PageContractTwig::forms()->createNamedBuilder('login', FormType::class)->add('email', TextType::class)->add('password', PasswordType::class)->getForm()->createView();
        $page = new AuthPage('Sign in', $form, 'Welcome back', 'Acme', 'Invalid credentials.', null, [new Link('Forgot password?', '/reset', ['data-turbo' => 'false', 'data-flag' => true, 'data-off' => false])], 'NowoAuthKitBundle');
        $html = $this->twig()->render('@NowoUiKit/page/auth.html.twig', ['page' => $page]);

        self::assertStringContainsString('nowo-ui-page--guest', $html);
        self::assertStringContainsString('<section class="nowo-ui-auth-panel card app-auth" data-nowo-ui-page="auth" data-nowo-ui-region="panel">', $html);
        self::assertStringContainsString('<p class="nowo-ui-auth-panel__brand">Acme</p>', $html);
        self::assertStringContainsString('<h1 class="nowo-ui-page-header__title h3 mb-1">Sign in</h1>', $html);
        self::assertStringContainsString('<div class="nowo-ui-flash nowo-ui-flash--error alert alert-danger" role="alert">Invalid credentials.</div>', $html);
        self::assertStringContainsString('id="login_password"', $html);
        self::assertStringContainsString('>Submit</button>', $html);
        self::assertStringContainsString('<a href="/reset" data-turbo="false" data-flag>Forgot password?</a>', $html);
        $this->assertContract($html);

        $child = "{% extends '@NowoUiKit/page/auth.html.twig' %}{% block nowo_ui_auth_fields %}{{ form_row(form.password) }}{{ form_row(form.email) }}{% endblock %}{% block nowo_ui_auth_actions %}<button>GO</button>{% endblock %}{% block nowo_ui_auth_footer %}<p>FOOT</p>{% endblock %}";
        $form = PageContractTwig::forms()->createNamedBuilder('login', FormType::class)->add('email', TextType::class)->add('password', PasswordType::class)->getForm()->createView();
        $html = $this->twig(['child' => $child])->render('child', ['page' => new AuthPage(new Label('Login', 'admin'), $form, submitLabel: 'Enter')]);
        self::assertLessThan(strpos($html, 'id="login_email"'), strpos($html, 'id="login_password"'));
        self::assertStringContainsString('<button>GO</button>', $html);
        self::assertStringContainsString('<p>FOOT</p>', $html);

        $html = $this->twig()->render('@NowoUiKit/page/auth.html.twig', ['page' => new AuthPage('Maintenance', submitLabel: 'x')]);
        self::assertStringNotContainsString('<form', $html);
    }

    public function testCustomLayoutsFromConfigAndPerPageOverride(): void
    {
        $templates = [
            'layouts/admin.html.twig' => '<main data-host="admin">{% block nowo_ui_page_header %}{% endblock %}{% block nowo_ui_flashes %}{% endblock %}{% block nowo_ui_content %}{% endblock %}{% block nowo_ui_modals %}{% endblock %}</main>',
            'bundle/layout.html.twig' => '<main data-host="bundle">{% block nowo_ui_content %}{% endblock %}</main>',
        ];
        $twig = PageContractTwig::create($templates, layouts: ['admin' => 'layouts/admin.html.twig']);
        $html = $twig->render('@NowoUiKit/page/dashboard.html.twig', ['page' => new DashboardPage(new PageHeader('D'))]);
        self::assertStringStartsWith('<main data-host="admin">', $html);
        self::assertStringContainsString('nowo-ui-dashboard-page', $html);

        $html = $twig->render('@NowoUiKit/page/dashboard.html.twig', ['page' => new DashboardPage(new PageHeader('D'), layout: 'bundle/layout.html.twig')]);
        self::assertStringStartsWith('<main data-host="bundle">', $html);
    }

    public function testPartialOverridesFromTheTheme(): void
    {
        $twig = PageContractTwig::create(['kit/_page_header.html.twig' => '<header data-kit>{{ header.title|nowo_ui_trans }}</header>', 'x/_empty.html.twig' => '<p data-x-empty></p>'], [
            'partials' => ['page_header' => 'kit/_page_header.html.twig'],
            'bundles' => ['NowoXBundle' => ['partials' => ['empty' => 'x/_empty.html.twig']]],
        ]);
        $html = $twig->render('@NowoUiKit/page/list.html.twig', ['page' => new ListPage(new PageHeader('Items'), bundle: 'NowoXBundle')]);

        self::assertStringContainsString('<header data-kit>Items</header>', $html);
        self::assertStringContainsString('<p data-x-empty></p>', $html);
    }

    public function testTemplatesCarryNoPaletteCdnOrInlineHandlers(): void
    {
        $views = PageContractTwig::root().'/src/Resources/views';
        $files = array_merge(glob($views.'/layout/*.twig') ?: [], glob($views.'/page/*.twig') ?: [], glob($views.'/partials/_action*.twig') ?: [], [$views.'/partials/_breadcrumbs.html.twig']);
        self::assertCount(21, $files);
        foreach ($files as $file) {
            $code = (string) preg_replace('/\{#.*?#\}/s', '', (string) file_get_contents($file));
            self::assertSame(0, preg_match('/(?:bg|text|border)-(?:slate|gray|zinc|red|blue|green|white|black)|rounded|\bon[a-z]+=|confirm\(|https?:\/\//', $code, $m), basename($file).': '.($m[0] ?? ''));
        }
    }

    private function assertContract(string $html): void
    {
        self::assertSame(0, preg_match('#(?:src|href)="(?:https?:)?//#', $html), 'CDN / absolute asset URL');
        self::assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html), 'inline event handler');
        self::assertStringNotContainsString('confirm(', $html);
        self::assertSame(1, substr_count($html, '<html'));
        self::assertStringContainsString('data-nowo-ui-page=', $html);
    }

    /**
     * @param array<string, string> $templates
     */
    private function twig(array $templates = [], ?Request $request = null): Environment
    {
        return PageContractTwig::create($templates, self::THEME, request: $request);
    }

    private function filtersForm(): FormView
    {
        return PageContractTwig::forms()->createNamedBuilder('filters', FormType::class, null, ['method' => 'GET'])->add('q', SearchType::class, ['required' => false])->getForm()->createView();
    }

    private function entityForm(): FormView
    {
        return PageContractTwig::forms()->createNamedBuilder('item', FormType::class)->add('name', TextType::class)->add('active', CheckboxType::class, ['required' => false])->getForm()->createView();
    }

    private function csrfForm(): FormView
    {
        return PageContractTwig::forms()->createNamedBuilder('csrf', FormType::class)->getForm()->createView();
    }
}
