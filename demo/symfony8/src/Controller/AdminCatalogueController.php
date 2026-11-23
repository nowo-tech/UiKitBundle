<?php

declare(strict_types=1);

namespace App\Controller;

use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\Crumb;
use Nowo\UiKitBundle\View\EmptyState;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\Stat;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Routing\Attribute\Route;

/**
 * UiKit 2.61 admin components in the catalogue (demo platform shell): data tables (filter bar with a phone sheet,
 * bulk selection, sortable headers, density, columns, row actions, pagination with per-page), data display (stat
 * tiles, definition lists, timeline, avatars, chips, progress, code block, empty and loading states), form patterns
 * (error summary, fieldset, choice group, input group, switch, form grid, dropzone, read-only view, sticky footer,
 * loading button), feedback and overlays (toasts, tooltip, destructive confirm, detail drawer) and navigation
 * patterns (breadcrumbs, client-side tabs, steps).
 *
 * Stateless (FrankenPHP worker, kernel reset off): every value comes from the request (group, filters, sort, page,
 * per page) or from constants; forms are built per request; a POST only answers with a toast on the same page,
 * nothing is stored. The profiler toolbar is off, so the axe and layout checks only see the page.
 */
final class AdminCatalogueController extends AbstractController
{
    public const GROUPS = ['data-tables', 'data-display', 'form-patterns', 'overlays', 'navigation-patterns'];

    private const STATUSES = ['paid' => ['Paid', 'success'], 'due' => ['Due', 'warning'], 'overdue' => ['Overdue', 'danger'], 'draft' => ['Draft', 'neutral']];
    private const CUSTOMERS = ['Analytical Engines Ltd', 'Hopper Compilers', 'Turing Machines', 'Lovelace & Co', 'Babbage Works', 'Noether Algebra', 'Hamilton Flight Software'];
    private const SORTS = ['number', 'customer', 'issued', 'amount'];
    private const PER_PAGE = [10, 25, 50];
    private const INPUT = ['class' => 'nowo-ui-input'];

    public function __construct(
        #[Autowire(service: 'profiler')]
        private readonly ?Profiler $profiler = null,
    ) {
    }

    #[Route('/catalogue/admin/{group}', name: 'catalogue_admin', requirements: ['group' => 'data-tables|data-display|form-patterns|overlays|navigation-patterns'], defaults: ['group' => 'data-tables'], methods: ['GET', 'POST'])]
    public function __invoke(string $group, Request $request, FormFactoryInterface $forms): Response
    {
        $this->profiler?->disable();
        $context = ['group' => $group, 'notice' => null];

        if ('data-tables' === $group) {
            $context += $this->dataTables($request, $forms, $context['notice']);
        } elseif ('data-display' === $group) {
            $context['stats'] = [
                new Stat('Revenue', '48,210 €', 'vs. last month', delta: '+12.4 %', trend: 'up', icon: 'export'),
                new Stat('Open invoices', 14, 'due in 30 days', delta: '−3', trend: 'down', deltaTone: 'positive', icon: 'view'),
                new Stat('Overdue', 3, 'over 30 days', tone: 'danger', delta: '+1', trend: 'up', deltaTone: 'negative', icon: 'archive'),
                new Stat('Customers', 128, 'active this year', delta: '0 %', trend: 'flat', icon: 'config'),
            ];
            // 2.69 one-time secrets: fresh demo values on every request, as a host shows a new token only once.
            $context['secrets'] = [
                'token' => 'demo_tok_'.bin2hex(random_bytes(12)),
                'recovery' => 'demo_rec_'.bin2hex(random_bytes(8)),
                'dialog' => 'demo_dlg_'.bin2hex(random_bytes(12)),
            ];
            $context['empty_state'] = new EmptyState('No credit notes yet', 'Credit notes you issue appear here, with their invoice and refund status.', Action::create('#', 'Create a credit note'), icon: 'create');
        } elseif ('form-patterns' === $group) {
            // The demo has no validator: the invalid example gets its errors by hand, as a host's validation would add them.
            $invalid = $this->customerForm($forms, 'invalid_customer')->submit(['name' => '', 'email' => 'ada@', 'vat' => 'ES-?']);
            $invalid->get('name')->addError(new FormError('Enter the company name.'));
            $invalid->get('email')->addError(new FormError('Enter an email address like name@example.com.'));
            $invalid->get('plan')->addError(new FormError('Choose a plan.'));
            $invalid->get('vat')->addError(new FormError('Enter a VAT number like ES12345678.'));
            $context['invalid_form'] = $invalid->createView();
            $patterns = $this->customerForm($forms, 'customer');
            $patterns->handleRequest($request);
            if ($patterns->isSubmitted()) {
                if ('' === trim((string) $patterns->get('name')->getData())) {
                    $patterns->get('name')->addError(new FormError('Enter the company name.'));
                } else {
                    $context['notice'] = 'Customer saved (demo: nothing is stored).';
                }
            }
            $context['patterns_form'] = $patterns->createView();
            $context['option_cards_form'] = $this->optionCardsForm($forms)->createView();
        } elseif ('overlays' === $group) {
            if ($request->isMethod('POST')) {
                $context['notice'] = 'Invoice INV-1044 deleted (demo: nothing is stored).';
            }
            $context['delete_action'] = Action::delete(
                $this->generateUrl('catalogue_admin', ['group' => 'overlays']),
                confirm: new Confirm('Delete invoice INV-1044?', 'The invoice and its 3 payments are deleted. This cannot be undone.', 'Delete invoice', 'danger', 'Cancel', 'catalogue-delete-confirm'),
                label: 'Delete invoice',
            );
        } elseif ('navigation-patterns' === $group) {
            $context['header'] = new PageHeader(
                'Breadcrumbs, tabs and steps',
                'The trail sits in the page header; tabs switch panels in the page; steps show where a task stands.',
                breadcrumbs: [new Crumb('Catalogue', $this->generateUrl('catalogue')), new Crumb('Invoices', $this->generateUrl('catalogue_admin')), new Crumb('INV-1042')],
                breadcrumbsMode: 'own',
            );
        }

        $response = $this->render('catalogue/admin/'.str_replace('-', '_', $group).'.html.twig', $context);
        if ('data-display' === $group) {
            // One-time secrets on the page: never stored by the browser, so back / forward cannot replay them.
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    /**
     * 2.69 (audit ZAE-M1): a one-time secret served as a remote dialog fragment, like a host's "token created" answer.
     * A fresh demo value per request; never cached (Cache-Control: no-store).
     */
    #[Route('/catalogue/admin/secret-fragment', name: 'catalogue_admin_secret_fragment', methods: ['GET'])]
    public function secretFragment(): Response
    {
        $this->profiler?->disable();
        $response = $this->render('catalogue/admin/_secret_fragment.html.twig', ['secret' => 'demo_frg_'.bin2hex(random_bytes(12))]);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function dataTables(Request $request, FormFactoryInterface $forms, ?string &$notice): array
    {
        $filters = $forms->createNamedBuilder('', FormType::class, null, ['method' => 'GET'])
            ->add('q', SearchType::class, ['label' => 'Search', 'required' => false, 'attr' => self::INPUT + ['placeholder' => 'Number or customer']])
            ->add('status', ChoiceType::class, ['label' => 'Status', 'required' => false, 'placeholder' => 'Any status', 'choices' => array_flip(array_map(static fn (array $s): string => $s[0], self::STATUSES)), 'attr' => self::INPUT])
            ->getForm();
        $filters->handleRequest($request);
        $q = mb_strtolower(trim((string) $filters->get('q')->getData()));
        $status = (string) $filters->get('status')->getData();

        $rows = array_values(array_filter(self::invoices(), static fn (array $r): bool => ('' === $q || str_contains(mb_strtolower($r['number'].' '.$r['customer']), $q)) && ('' === $status || $r['status'] === $status)));
        $sort = \in_array($request->query->get('sort'), self::SORTS, true) ? (string) $request->query->get('sort') : 'number';
        $dir = 'desc' === $request->query->get('dir') ? 'desc' : 'asc';
        usort($rows, static fn (array $a, array $b): int => ('asc' === $dir ? 1 : -1) * ($a[$sort] <=> $b[$sort]));

        $perPage = \in_array($request->query->getInt('per_page'), self::PER_PAGE, true) ? $request->query->getInt('per_page') : 10;
        $total = \count($rows);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, $request->query->getInt('page', 1)));
        $pageRows = \array_slice($rows, ($page - 1) * $perPage, $perPage);

        $ids = array_column($pageRows, 'number');
        $makeBulk = static fn (): FormInterface => $forms->createNamedBuilder('bulk', FormType::class, null, ['attr' => ['id' => 'catalogue-bulk']])
            ->add('ids', ChoiceType::class, [
                'choices' => array_combine($ids, $ids),
                'expanded' => true,
                'multiple' => true,
                'label' => false,
                'choice_name' => static fn (string $id): string => $id,
                'choice_attr' => static fn (string $id): array => ['data-nowo-ui-bulk-item' => true, 'aria-label' => 'Select '.$id],
            ])
            ->add('all', HiddenType::class, ['mapped' => false, 'attr' => ['autocomplete' => 'off']])
            ->getForm();
        $bulk = $makeBulk();
        $bulk->handleRequest($request);
        if ($bulk->isSubmitted()) {
            $count = '1' === $bulk->get('all')->getData() ? $total : \count((array) $bulk->get('ids')->getData());
            $action = 'delete' === $request->request->get('bulk_action') ? 'deleted' : 'archived';
            $notice = 0 === $count ? 'Select at least one invoice first.' : \sprintf('%d invoice%s %s (demo: nothing is stored).', $count, 1 === $count ? '' : 's', $action);
            // The answer page starts with a fresh, unbound form: "every page" (all) is never printed as 1 (audit XJ-1).
            $bulk = $makeBulk();
        }

        $query = array_filter(['q' => $q, 'status' => $status, 'sort' => $sort, 'dir' => $dir, 'per_page' => $perPage], static fn ($v): bool => '' !== $v && null !== $v);

        return [
            'filter_form' => $filters->createView(),
            'bulk_form' => $bulk->createView(),
            'rows' => $pageRows,
            'statuses' => self::STATUSES,
            'sort' => $sort,
            'dir' => $dir,
            'query' => $query,
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => $pages],
            'per_page_choices' => self::PER_PAGE,
            'notice' => $notice,
        ];
    }

    private function customerForm(FormFactoryInterface $forms, string $name): FormInterface
    {
        return $forms->createNamedBuilder($name, FormType::class, null, ['attr' => ['id' => $name.'-form', 'class' => 'nowo-ui-form', 'data-nowo-ui-busy-on-submit' => true]])
            ->add('name', TextType::class, ['label' => 'Company name', 'attr' => self::INPUT])
            ->add('email', EmailType::class, ['label' => 'Billing email', 'attr' => self::INPUT])
            ->add('website', UrlType::class, ['label' => 'Website', 'required' => false, 'default_protocol' => 'https', 'attr' => self::INPUT + ['placeholder' => 'example.com']])
            ->add('credit', MoneyType::class, ['label' => 'Credit limit', 'required' => false, 'currency' => false, 'help' => 'Leave empty for no limit.', 'attr' => self::INPUT])
            ->add('plan', ChoiceType::class, ['label' => 'Plan', 'expanded' => true, 'choices' => ['Starter' => 'starter', 'Business' => 'business', 'Enterprise' => 'enterprise'], 'help' => 'You can change it at any time.'])
            ->add('vat', TextType::class, ['label' => 'VAT number', 'required' => false, 'attr' => self::INPUT])
            ->add('reminders', CheckboxType::class, ['label' => 'Send payment reminders', 'required' => false, 'row_attr' => ['class' => 'nowo-ui-form-row--switch'], 'attr' => ['role' => 'switch']])
            ->add('contract', FileType::class, ['label' => 'Signed contract', 'required' => false, 'multiple' => true, 'attr' => ['accept' => '.pdf,image/*']])
            ->getForm();
    }

    /**
     * The option cards example: four expanded choices (radio and checkbox) of a small unmapped form.
     */
    private function optionCardsForm(FormFactoryInterface $forms): FormInterface
    {
        return $forms->createNamedBuilder('option_cards', FormType::class, ['plan' => 'starter', 'addons' => ['backup'], 'device' => 'laptop', 'channels' => ['email']], ['attr' => ['id' => 'option-cards-form']])
            ->add('plan', ChoiceType::class, ['label' => 'Plan (radio, basic)', 'expanded' => true, 'choices' => ['Starter' => 'starter', 'Team' => 'team', 'Enterprise' => 'enterprise'], 'choice_attr' => static fn (string $choice, string $key, string $value): array => 'enterprise' === $value ? ['disabled' => true] : []])
            ->add('addons', ChoiceType::class, ['label' => 'Add-ons (checkbox, basic)', 'expanded' => true, 'multiple' => true, 'choices' => ['Daily backup' => 'backup', 'Single sign-on' => 'sso']])
            ->add('device', ChoiceType::class, ['label' => 'Device (radio, icon)', 'expanded' => true, 'choices' => ['Laptop' => 'laptop', 'Tablet' => 'tablet', 'Phone' => 'phone'], 'choice_attr' => static fn (string $choice, string $key, string $value): array => 'phone' === $value ? ['disabled' => true] : []])
            ->add('channels', ChoiceType::class, ['label' => 'Channels (checkbox, icon)', 'expanded' => true, 'multiple' => true, 'choices' => ['Email' => 'email', 'SMS' => 'sms', 'Push' => 'push'], 'choice_attr' => static fn (string $choice, string $key, string $value): array => 'push' === $value ? ['disabled' => true] : []])
            ->getForm();
    }

    /**
     * 42 invoices, the same on every request.
     *
     * @return list<array{number: string, customer: string, issued: string, amount: float, status: string}>
     */
    private static function invoices(): array
    {
        $rows = [];
        $statuses = array_keys(self::STATUSES);
        for ($i = 0; $i < 42; ++$i) {
            $rows[] = [
                'number' => \sprintf('INV-%04d', 1001 + $i),
                'customer' => self::CUSTOMERS[($i * 3) % \count(self::CUSTOMERS)],
                'issued' => \sprintf('2026-%02d-%02d', 1 + ($i % 11), 1 + (($i * 7) % 28)),
                'amount' => round(120 + (($i * 977) % 4800) + ($i % 4) * 0.25, 2),
                'status' => $statuses[($i * 5) % \count($statuses)],
            ];
        }

        return $rows;
    }
}
