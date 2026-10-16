<?php

declare(strict_types=1);

namespace App\Controller;

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
use Nowo\UiKitBundle\View\Link;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\Pagination;
use Nowo\UiKitBundle\View\SettingsPage;
use Nowo\UiKitBundle\View\SettingsSection;
use Nowo\UiKitBundle\View\ShowPage;
use Nowo\UiKitBundle\View\WizardPage;
use Nowo\UiKitBundle\View\WizardStep;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * REQ-UI-003 host-theme demo: an "Acme Shop" back office rendered with the UiKit page scaffolds. Every page is
 * available with the host theme (config/packages/nowo_ui_kit.yaml: tokens, class map, page_header partial,
 * host layouts) under /page-contract/… and with UiKit's default theme under /page-contract/default/… (a per-page
 * layout without the host tokens + the DemoDefaultTheme per-bundle exception that resets the host classes and
 * partial).
 */
#[Route('/page-contract', requirements: ['theme' => 'default'])]
final class PageContractController extends AbstractController
{
    private const ORDERS = [
        ['id' => 1042, 'customer' => 'Lucía Fernández', 'email' => 'lucia@example.com', 'total' => '249.90', 'status' => 'paid', 'placed' => '2026-10-16 09:12'],
        ['id' => 1041, 'customer' => 'Marco Rossi', 'email' => 'marco@example.com', 'total' => '89.00', 'status' => 'pending', 'placed' => '2026-10-16 08:47'],
        ['id' => 1040, 'customer' => 'Emma Schulz', 'email' => 'emma@example.com', 'total' => '1,120.00', 'status' => 'shipped', 'placed' => '2026-10-15 18:03'],
        ['id' => 1039, 'customer' => 'Noah Martin', 'email' => 'noah@example.com', 'total' => '34.50', 'status' => 'refunded', 'placed' => '2026-10-15 16:20'],
        ['id' => 1038, 'customer' => 'Sofia Almeida', 'email' => 'sofia@example.com', 'total' => '412.75', 'status' => 'paid', 'placed' => '2026-10-15 11:58'],
    ];

    #[Route('', name: 'page_contract_index')]
    #[Route('/{theme}', name: 'page_contract_default_index')]
    public function index(?string $theme = null): Response
    {
        return $this->render('page_contract/index.html.twig', ['page' => new DashboardPage(
            $this->header($theme, 'Page contract', 'One host theme re-skins every UiKit scaffold: list, show, form, settings, dashboard, confirm, wizard and auth.', 'Overview', [
                Action::create($this->generateUrl($this->route('page_contract_orders', $theme), $this->params($theme)), 'Browse orders'),
            ]),
            [
                new KpiTile('Scaffolds', '8', 'page/*.html.twig'),
                new KpiTile('Theme slots', '70+', 'docs/THEME_SLOTS.md'),
                new KpiTile('Host overrides', '0', 'per bundle page', null, 'success'),
                new KpiTile('Partial overrides', '1', 'page_header', null, 'info'),
            ],
            $this->bundle($theme),
            $this->layout($theme),
        ), 'cards' => $this->scaffoldLinks($theme)]);
    }

    #[Route('/dashboard', name: 'page_contract_dashboard')]
    #[Route('/{theme}/dashboard', name: 'page_contract_default_dashboard')]
    public function dashboard(?string $theme = null): Response
    {
        return $this->render('page_contract/dashboard.html.twig', [
            'page' => new DashboardPage(
                $this->header($theme, 'Dashboard', 'Sales of the last 30 days.', 'Dashboard', [new Action('export', 'Export report', '#', icon: 'export')]),
                [
                    new KpiTile('Revenue', '48,210 €', '+12% vs previous 30 days', null, 'success'),
                    new KpiTile('Orders', '1,284', '42 today', $this->generateUrl($this->route('page_contract_orders', $theme), $this->params($theme))),
                    new KpiTile('Refunds', '17', '1.3% of orders', null, 'warning'),
                    new KpiTile('Failed payments', '5', 'needs review', null, 'danger'),
                ],
                $this->bundle($theme),
                $this->layout($theme),
            ),
            'recent' => \array_slice(self::ORDERS, 0, 4),
            'stock' => [['product' => 'Linen tote bag', 'left' => 3], ['product' => 'Ceramic mug (sand)', 'left' => 5], ['product' => 'Notebook A5 dotted', 'left' => 8]],
        ]);
    }

    #[Route('/orders', name: 'page_contract_orders')]
    #[Route('/{theme}/orders', name: 'page_contract_default_orders')]
    public function list(Request $request, ?string $theme = null): Response
    {
        $query = (string) $request->query->get('q', '');
        $rows = array_values(array_filter(self::ORDERS, static fn (array $o): bool => '' === $query || false !== stripos($o['customer'], $query)));
        $filters = $this->container->get('form.factory')->createNamedBuilder('filters', options: ['method' => 'GET'])
            ->add('q', SearchType::class, ['required' => false, 'label' => 'Customer', 'attr' => ['placeholder' => 'Search customers…']])
            ->add('status', ChoiceType::class, ['required' => false, 'label' => 'Status', 'placeholder' => 'Any status', 'choices' => ['Paid' => 'paid', 'Pending' => 'pending', 'Shipped' => 'shipped', 'Refunded' => 'refunded']])
            ->getForm();
        $filters->handleRequest($request);

        return $this->render('page_contract/orders/index.html.twig', ['page' => new ListPage(
            $this->header($theme, 'Orders', 'Every order placed in the shop, newest first.', 'Orders', [
                Action::create('#', 'New order'),
                new Action('export', 'Export CSV', '#', icon: 'export'),
            ]),
            [new Column('id', '#', true), new Column('customer', 'Customer', true), new Column('placed', 'Placed'), new Column('status', 'Status'), new Column('total', 'Total', true, 'end')],
            $rows,
            $filters->createView(),
            fn (array $row): array => [
                Action::view($this->generateUrl($this->route('page_contract_order', $theme), $this->params($theme, ['id' => $row['id']]))),
                Action::edit($this->generateUrl($this->route('page_contract_order_edit', $theme), $this->params($theme, ['id' => $row['id']]))),
                Action::delete('#', 'demo-token', Confirm::delete(\sprintf('Order #%d of %s will be deleted.', $row['id'], $row['customer']))),
            ],
            new Pagination(1, 5, 42, route: $this->route('page_contract_orders', $theme), routeParams: $this->params($theme)),
            new EmptyState('No orders match', 'Try another customer name.'),
            $this->generateUrl($this->route('page_contract_orders', $theme), $this->params($theme)),
            'id',
            'desc',
            bundle: $this->bundle($theme),
            layout: $this->layout($theme),
        )]);
    }

    #[Route('/orders/{id}', name: 'page_contract_order', requirements: ['id' => '\d+'])]
    #[Route('/{theme}/orders/{id}', name: 'page_contract_default_order', requirements: ['id' => '\d+'])]
    public function show(int $id, ?string $theme = null): Response
    {
        $order = $this->order($id);

        return $this->render('page_contract/orders/show.html.twig', ['order' => $order, 'page' => new ShowPage(
            $this->header($theme, \sprintf('Order #%d', $order['id']), \sprintf('Placed by %s on %s.', $order['customer'], $order['placed']), 'Order', [
                Action::edit($this->generateUrl($this->route('page_contract_order_edit', $theme), $this->params($theme, ['id' => $id]))),
                Action::delete('#', 'demo-token', Confirm::delete('The order and its invoice will be deleted.')),
            ]),
            [
                new DetailSection('Customer', [new DetailItem('Name', $order['customer']), new DetailItem('Email', $order['email'], 'mailto:'.$order['email']), new DetailItem('Newsletter', true)], 'customer'),
                new DetailSection('Payment', [new DetailItem('Total', $order['total'].' €'), new DetailItem('Status', ucfirst($order['status'])), new DetailItem('Gift wrap', false)], 'payment'),
            ],
            [new DetailItem('Created', $order['placed']), new DetailItem('Updated', '2026-10-16 10:30')],
            $this->bundle($theme),
            $this->layout($theme),
        )]);
    }

    #[Route('/orders/{id}/edit', name: 'page_contract_order_edit', requirements: ['id' => '\d+'])]
    #[Route('/{theme}/orders/{id}/edit', name: 'page_contract_default_order_edit', requirements: ['id' => '\d+'])]
    public function form(int $id, ?string $theme = null): Response
    {
        $order = $this->order($id);
        $form = $this->container->get('form.factory')->createNamedBuilder('order', data: $order)
            ->add('customer', TextType::class, ['label' => 'Customer'])
            ->add('email', EmailType::class, ['label' => 'Email', 'help' => 'Receipts and shipping updates go here.'])
            ->add('total', MoneyType::class, ['label' => 'Total', 'currency' => 'EUR'])
            ->add('status', ChoiceType::class, ['label' => 'Status', 'choices' => ['Paid' => 'paid', 'Pending' => 'pending', 'Shipped' => 'shipped', 'Refunded' => 'refunded']])
            ->add('notes', TextareaType::class, ['label' => 'Internal notes', 'required' => false])
            ->getForm();

        return $this->render('page_contract/orders/edit.html.twig', ['page' => new FormPage(
            $this->header($theme, \sprintf('Edit order #%d', $id), 'Changes are logged in the order history.', 'Edit'),
            $form->createView(),
            'Save order',
            $this->generateUrl($this->route('page_contract_order', $theme), $this->params($theme, ['id' => $id])),
            secondaryActions: [new Action('copy', 'Duplicate', '#', variant: 'secondary')],
            deleteAction: Action::delete('#', 'demo-token', Confirm::delete('The order and its invoice will be deleted.')),
            bundle: $this->bundle($theme),
            layout: $this->layout($theme),
        )]);
    }

    #[Route('/settings/{section}', name: 'page_contract_settings', requirements: ['section' => 'general|notifications|billing'], defaults: ['section' => 'notifications'])]
    #[Route('/{theme}/settings/{section}', name: 'page_contract_default_settings', requirements: ['section' => 'general|notifications|billing'], defaults: ['section' => 'notifications'])]
    public function settings(string $section, ?string $theme = null): Response
    {
        $sections = [];
        foreach (['general' => 'General', 'notifications' => 'Notifications', 'billing' => 'Billing'] as $id => $label) {
            $sections[] = new SettingsSection(
                $id,
                $label,
                $this->generateUrl($this->route('page_contract_settings', $theme), $this->params($theme, ['section' => $id])),
                $id === $section ? $this->notificationsForm() : null,
                $id === $section ? 'Who hears about new orders, refunds and low stock.' : null,
                'Save notifications',
            );
        }

        return $this->render('@NowoUiKit/page/settings.html.twig', ['page' => new SettingsPage(
            $this->header($theme, 'Shop settings', 'Configuration shared by every storefront.', 'Settings'),
            $sections,
            $section,
            $this->bundle($theme),
            $this->layout($theme),
        )]);
    }

    #[Route('/orders/{id}/delete', name: 'page_contract_confirm', requirements: ['id' => '\d+'])]
    #[Route('/{theme}/orders/{id}/delete', name: 'page_contract_default_confirm', requirements: ['id' => '\d+'])]
    public function confirm(int $id, ?string $theme = null): Response
    {
        $order = $this->order($id);

        return $this->render('@NowoUiKit/page/confirm.html.twig', ['page' => new ConfirmPage(
            $this->header($theme, \sprintf('Delete order #%d', $id), null, 'Delete'),
            new Confirm('Delete this order?', \sprintf('Order #%d of %s (%s €) and its invoice will be deleted. This cannot be undone.', $id, $order['customer'], $order['total']), 'Delete order'),
            Action::delete('#', 'demo-token'),
            $this->generateUrl($this->route('page_contract_order', $theme), $this->params($theme, ['id' => $id])),
            $this->bundle($theme),
            $this->layout($theme),
        )]);
    }

    #[Route('/onboarding', name: 'page_contract_wizard')]
    #[Route('/{theme}/onboarding', name: 'page_contract_default_wizard')]
    public function wizard(?string $theme = null): Response
    {
        $form = $this->container->get('form.factory')->createNamedBuilder('payments')
            ->add('provider', ChoiceType::class, ['label' => 'Payment provider', 'choices' => ['Stripe' => 'stripe', 'Adyen' => 'adyen', 'Bank transfer' => 'bank'], 'expanded' => true, 'data' => 'stripe'])
            ->add('currency', ChoiceType::class, ['label' => 'Shop currency', 'choices' => ['Euro (€)' => 'EUR', 'US dollar ($)' => 'USD']])
            ->add('test_mode', CheckboxType::class, ['label' => 'Start in test mode', 'required' => false, 'data' => true])
            ->getForm();

        return $this->render('@NowoUiKit/page/wizard.html.twig', ['page' => new WizardPage(
            $this->header($theme, 'Set up your shop', 'Four short steps; you can change everything later.', 'Onboarding'),
            [new WizardStep('account', 'Account', '#'), new WizardStep('store', 'Store details', '#'), new WizardStep('payments', 'Payments'), new WizardStep('launch', 'Launch')],
            'payments',
            $form->createView(),
            '#',
            'Continue',
            bundle: $this->bundle($theme),
            layout: $this->layout($theme),
        )]);
    }

    #[Route('/sign-in', name: 'page_contract_auth')]
    #[Route('/{theme}/sign-in', name: 'page_contract_default_auth')]
    public function auth(?string $theme = null): Response
    {
        $form = $this->container->get('form.factory')->createNamedBuilder('login')
            ->add('email', EmailType::class, ['label' => 'Email', 'attr' => ['placeholder' => 'you@acme.test']])
            ->add('password', PasswordType::class, ['label' => 'Password'])
            ->add('remember', CheckboxType::class, ['label' => 'Keep me signed in', 'required' => false])
            ->getForm();

        return $this->render('@NowoUiKit/page/auth.html.twig', ['page' => new AuthPage(
            'Sign in to Acme Admin',
            $form->createView(),
            'Use your work account.',
            'Acme Shop',
            null,
            'Sign in',
            [new Link('Forgot your password?', '#'), new Link('Request an account', '#')],
            $this->bundle($theme),
            null !== $theme ? 'page_contract/default_guest_layout.html.twig' : null,
        )]);
    }

    /**
     * Cards of the overview: one link per scaffold.
     *
     * @return list<array{title: string, scaffold: string, text: string, href: string}>
     */
    private function scaffoldLinks(?string $theme): array
    {
        $link = fn (string $route, array $params = []): string => $this->generateUrl($this->route($route, $theme), $this->params($theme, $params));

        return [
            ['title' => 'List', 'scaffold' => 'page/list', 'text' => 'Header actions, filters, sortable table, row actions with confirm dialogs, pagination.', 'href' => $link('page_contract_orders')],
            ['title' => 'Show', 'scaffold' => 'page/show', 'text' => 'Detail sections as definition lists plus audit meta.', 'href' => $link('page_contract_order', ['id' => 1042])],
            ['title' => 'Form', 'scaffold' => 'page/form', 'text' => 'Form with save / cancel, secondary actions and a danger zone.', 'href' => $link('page_contract_order_edit', ['id' => 1042])],
            ['title' => 'Dashboard', 'scaffold' => 'page/dashboard', 'text' => 'KPI tiles and a cards grid filled by the page.', 'href' => $link('page_contract_dashboard')],
            ['title' => 'Settings', 'scaffold' => 'page/settings', 'text' => 'Section tabs with one form per section.', 'href' => $link('page_contract_settings')],
            ['title' => 'Confirm', 'scaffold' => 'page/confirm', 'text' => 'Full-page confirm: the no-JS fallback of the confirm dialog.', 'href' => $link('page_contract_confirm', ['id' => 1041])],
            ['title' => 'Wizard', 'scaffold' => 'page/wizard', 'text' => 'Steps indicator, step form, back / next.', 'href' => $link('page_contract_wizard')],
            ['title' => 'Auth', 'scaffold' => 'page/auth', 'text' => 'Guest layout: brand, panel, form, footer links.', 'href' => $link('page_contract_auth')],
            ['title' => null !== $theme ? 'Host theme' : 'Default theme', 'scaffold' => null !== $theme ? '/page-contract' : '/page-contract/default', 'text' => null !== $theme ? 'The same pages with the Acme host theme.' : 'The same pages with UiKit\'s default theme.', 'href' => null !== $theme ? $this->generateUrl('page_contract_index') : $this->generateUrl('page_contract_default_index', ['theme' => 'default'])],
        ];
    }

    private function notificationsForm(): FormView
    {
        return $this->container->get('form.factory')->createNamedBuilder('notifications', data: ['recipients' => 'orders@acme.test', 'new_order' => true, 'refund' => true, 'low_stock' => false])
            ->add('recipients', TextType::class, ['label' => 'Recipients', 'help' => 'Comma-separated email addresses.'])
            ->add('new_order', CheckboxType::class, ['label' => 'New order', 'required' => false])
            ->add('refund', CheckboxType::class, ['label' => 'Refund requested', 'required' => false])
            ->add('low_stock', CheckboxType::class, ['label' => 'Low stock', 'required' => false])
            ->getForm()
            ->createView();
    }

    /**
     * @param list<Action> $actions
     */
    private function header(?string $theme, string $title, ?string $intro, string $crumb, array $actions = []): PageHeader
    {
        $home = null !== $theme ? $this->generateUrl('page_contract_default_index', ['theme' => $theme]) : $this->generateUrl('page_contract_index');

        return new PageHeader($title, $intro, $actions, 'Overview' === $crumb ? [new Crumb('Acme Admin', $home), new Crumb('Overview')] : [new Crumb('Acme Admin', $home), new Crumb($crumb)]);
    }

    /**
     * @return array{id: int, customer: string, email: string, total: string, status: string, placed: string}
     */
    private function order(int $id): array
    {
        foreach (self::ORDERS as $order) {
            if ($order['id'] === $id) {
                return $order;
            }
        }

        throw $this->createNotFoundException(\sprintf('Order %d not found.', $id));
    }

    private function route(string $route, ?string $theme): string
    {
        return null !== $theme ? str_replace('page_contract_', 'page_contract_default_', $route) : $route;
    }

    /**
     * @param array<string, int|string> $params
     *
     * @return array<string, int|string>
     */
    private function params(?string $theme, array $params = []): array
    {
        return null !== $theme ? ['theme' => $theme] + $params : $params;
    }

    /** The default-theme view: per-bundle exception that resets the host classes and partial. */
    private function bundle(?string $theme): ?string
    {
        return null !== $theme ? 'DemoDefaultTheme' : null;
    }

    /** The default-theme view: UiKit layout without the host tokens (per-page web_ui.layout_template). */
    private function layout(?string $theme): ?string
    {
        return null !== $theme ? 'page_contract/default_layout.html.twig' : null;
    }
}
