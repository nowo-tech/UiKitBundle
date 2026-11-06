<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\CsrfOnlyType;
use App\Form\DemoDestinationType;
use Nowo\UiKitBundle\Dialog\RemoteDialog;
use Nowo\UiKitBundle\Dialog\RemoteDialogResponder;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\Pagination;
use Nowo\UiKitBundle\View\Table;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormView;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * UiKit 2.6 kit templates demo: the error pages (@NowoUiKit/error/status.html.twig, previewed per status code with a
 * 200 response), the signed-in app shell (@NowoUiKit/shell/*), the confirm dialog wrapper and the compact pagination.
 * The real error pages of this demo use the one-line templates/bundles/TwigBundle/Exception/error.html.twig.
 */
#[Route('/kit')]
final class KitTemplatesController extends AbstractController
{
    private const ROWS = ['Invoice #1042', 'Invoice #1041', 'Invoice #1040', 'Refund #77', 'Invoice #1039', 'Invoice #1038', 'Credit note #12', 'Invoice #1037', 'Invoice #1036', 'Invoice #1035', 'Invoice #1034', 'Refund #76'];

    #[Route('/errors/{code}', name: 'kit_error_preview', requirements: ['code' => '4\d\d|5\d\d'])]
    public function error(int $code): Response
    {
        return $this->render('@NowoUiKit/error/status.html.twig', [
            'status_code' => $code,
            'exception' => 429 === $code ? ['Retry-After' => '30'] : null,
        ]);
    }

    #[Route('/shell', name: 'kit_shell')]
    public function shell(Request $request): Response
    {
        $pagination = Pagination::fromRequest($request, \count(self::ROWS), 5, [5, 10, 25], variant: Pagination::VARIANT_COMPACT);

        return $this->render('kit/shell.html.twig', [
            'pagination' => $pagination,
            'rows' => \array_slice(self::ROWS, $pagination->offset(), $pagination->limit()),
            // REQ-TWIG-005: the confirm dialog's POST is a Form (form_start / form_end), not a hand-written form
            'void_form' => $this->createForm(CsrfOnlyType::class, null, ['action' => $this->generateUrl('kit_shell')])->createView(),
        ]);
    }

    /**
     * UiKit 2.25: the side panel / drawer. `?panel=1` renders the comments panel open (the no-JavaScript state the toggle
     * and the close link point to). The response forbids inline style attributes and handlers (CSP), which the e2e
     * checks while it opens and closes the panels.
     */
    #[Route('/side-panel', name: 'kit_side_panel')]
    public function sidePanel(Request $request): Response
    {
        $response = $this->render('kit/side_panel.html.twig', ['open' => '1' === $request->query->get('panel')]);
        $response->headers->set('Content-Security-Policy', "script-src 'self'; style-src-attr 'none'; script-src-attr 'none'");

        return $response;
    }

    /**
     * UiKit 2.34: the small options — table row attributes and foot, tree hooks, dialog hooks and back arrow, the remote
     * form dialog as a side panel, progress with an absolute amount. The response forbids inline style attributes and
     * handlers (CSP); window.nowoUiAskConfirm is exercised by e2e/tests/small-options.spec.ts on this page.
     */
    #[Route('/small-options', name: 'kit_small_options')]
    public function smallOptions(): Response
    {
        $table = new Table(
            [new Column('name', 'Task'), new Column('hours', 'Hours', align: 'end')],
            [['id' => 1, 'name' => 'Draft', 'hours' => 3], ['id' => 2, 'name' => 'Review', 'hours' => 5]],
            label: 'Tasks',
            rowAttributes: static fn (array $row): array => ['class' => 'demo-row', 'data-testid' => 'demo-row', 'data-task-id' => $row['id']],
        );
        $response = $this->render('kit/small_options.html.twig', ['table' => $table]);
        $response->headers->set('Content-Security-Policy', "script-src 'self'; style-src-attr 'none'; script-src-attr 'none'");

        return $response;
    }

    /**
     * UiKit 2.28: the remote form dialog (IIFE driver) and the overlay registry. The list page opens the form page
     * (/kit/remote-form/new) in a dialog; without JavaScript, or opened directly, it is a normal page. The response
     * forbids inline style attributes and handlers (CSP).
     */
    #[Route('/remote-form', name: 'kit_remote_form')]
    public function remoteForm(Request $request): Response
    {
        $response = $this->render('kit/remote_form.html.twig', ['saved' => $request->query->getString('saved')]);
        $response->headers->set('Content-Security-Policy', "script-src 'self'; style-src-attr 'none'; script-src-attr 'none'");

        return $response;
    }

    /**
     * The form page of the remote form dialog: the fragment for `X-Nowo-Dialog: 1`, 422 when invalid (a required
     * label), 204 + X-Nowo-Redirect once saved (through RemoteDialogResponder), a redirect without the header.
     */
    #[Route('/remote-form/new', name: 'kit_remote_form_new', methods: ['GET', 'POST'])]
    public function remoteFormNew(Request $request, RemoteDialogResponder $dialog): Response
    {
        $form = $this->createForm(DemoDestinationType::class, null, ['action' => $this->generateUrl('kit_remote_form_new')]);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $label = trim((string) $form->get('label')->getData());
            if ('' === $label) {
                $form->get('label')->addError(new FormError('Enter a label.'));
            } elseif ($form->isValid()) {
                // The demo has no session (no flash bag): the saved label travels in the query of the redirect target.
                return $dialog->redirectToRoute('kit_remote_form', ['saved' => $label]);
            }
        }

        $response = $this->render($dialog->template('kit/remote_form_new.html.twig', 'kit/_remote_form_fields.html.twig'), ['form' => $form->createView()]);
        $response->headers->set('Content-Security-Policy', "script-src 'self'; style-src-attr 'none'; script-src-attr 'none'");

        return RemoteDialog::vary($response);
    }

    /**
     * UiKit 2.33 (UI-1): a two-step flow in the same remote form dialog. Step 1 answers `204` +
     * `X-Nowo-Dialog-Location` (RemoteDialogResponder::stepToRoute()), so the dialog stays open and loads step 2; step 2
     * answers `204` + `X-Nowo-Redirect` and the dialog closes. Without the dialog header both are plain redirects.
     */
    #[Route('/remote-form/wizard', name: 'kit_remote_form_wizard', methods: ['GET', 'POST'])]
    public function remoteFormWizard(Request $request, RemoteDialogResponder $dialog): Response
    {
        $form = $this->container->get('form.factory')->createNamedBuilder('wizard', FormType::class, null, ['action' => $this->generateUrl('kit_remote_form_wizard')])
            ->add('label', TextType::class, ['required' => false, 'label' => 'Label'])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $label = trim((string) $form->get('label')->getData());
            if ('' === $label) {
                $form->get('label')->addError(new FormError('Enter a label.'));
            } elseif ($form->isValid()) {
                return $dialog->stepToRoute('kit_remote_form_wizard_channel', ['label' => $label]);
            }
        }

        return $this->wizardStep($dialog, $form->createView(), 1, '');
    }

    #[Route('/remote-form/wizard/channel', name: 'kit_remote_form_wizard_channel', methods: ['GET', 'POST'])]
    public function remoteFormWizardChannel(Request $request, RemoteDialogResponder $dialog): Response
    {
        $label = $request->query->getString('label');
        // No `action`: in the dialog an action-less form posts to the step URL the dialog loaded (query included).
        $form = $this->container->get('form.factory')->createNamedBuilder('wizard_channel', FormType::class)
            ->add('channel', ChoiceType::class, ['choices' => ['Email' => 'email', 'Slack' => 'slack'], 'label' => 'Channel'])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            return $dialog->redirectToRoute('kit_remote_form', ['saved' => \sprintf('%s (%s)', $label, (string) $form->get('channel')->getData())]);
        }

        return $this->wizardStep($dialog, $form->createView(), 2, $label);
    }

    private function wizardStep(RemoteDialogResponder $dialog, FormView $form, int $step, string $label): Response
    {
        $response = $this->render($dialog->template('kit/remote_form_wizard.html.twig', 'kit/_remote_form_wizard_step.html.twig'), ['form' => $form, 'step' => $step, 'label' => $label]);
        $response->headers->set('Content-Security-Policy', "script-src 'self'; style-src-attr 'none'; script-src-attr 'none'");

        return $dialog->fragment($response);
    }

    /**
     * UiKit 2.29: the two dialog sizes and the dialog anatomy — a medium form, a wide table, a plain yes / no
     * confirmation (natural height) and a long form whose submit sits in the footer (`form="…"`), outside the scrolling
     * body.
     */
    #[Route('/dialogs', name: 'kit_dialogs', methods: ['GET', 'POST'])]
    public function dialogs(Request $request): Response
    {
        $short = $this->container->get('form.factory')->createNamedBuilder('medium', FormType::class, null, ['action' => $this->generateUrl('kit_dialogs'), 'attr' => ['id' => 'medium-form']])
            ->add('label', TextType::class, ['required' => false])
            ->add('channel', ChoiceType::class, ['choices' => ['Email' => 'email', 'Slack' => 'slack', 'Webhook' => 'webhook']])
            ->getForm();
        $long = $this->container->get('form.factory')->createNamedBuilder('long', FormType::class, null, ['action' => $this->generateUrl('kit_dialogs'), 'attr' => ['id' => 'long-form']]);
        foreach (['Name', 'Owner', 'Team', 'Project', 'Environment', 'Region', 'Endpoint', 'Method', 'Expected status', 'Keyword', 'Interval', 'Timeout', 'Retries', 'Escalation', 'Maintenance window', 'Runbook', 'Contact', 'Tags'] as $field) {
            $long->add(str_replace(' ', '_', strtolower($field)), TextType::class, ['required' => false, 'label' => $field]);
        }
        $long->add('notes', TextareaType::class, ['required' => false]);
        $longForm = $long->getForm();

        // 2.29.1 (audit FK-I5): the forms are handled (CSRF and validation) and a valid POST redirects (PRG); nothing is
        // stored, the demo only shows "Submitted.".
        foreach ([$short, $longForm] as $form) {
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                return $this->redirectToRoute('kit_dialogs', ['submitted' => 1]);
            }
        }

        return $this->render('kit/dialogs.html.twig', [
            'medium_form' => $short->createView(),
            'long_form' => $longForm->createView(),
            'rows' => self::ROWS,
            'submitted' => $request->query->getBoolean('submitted'),
            // 2.31 (dialog rule v2): View\Confirm dialogs (partial `confirm`) on the dialog anatomy. Demo only, do not
            // copy: a literal CSRF token and a POST target that does nothing; a real action uses a CSRF-only form
            // (Action::form / nowo_ui_action_form()) and its own route.
            'archive_confirm' => new Confirm('Archive this monitor?', 'It stops checking and leaves the dashboard. You can restore it later.', 'Archive', variant: 'primary', id: 'archive-monitor'),
            'delete_confirm' => new Confirm('Delete monitor?', 'The monitor "API health" and its 90 days of history will be deleted. This cannot be undone.', 'Delete monitor', id: 'delete-monitor', expectedValue: 'api-health'),
            'confirm_action' => Action::delete($this->generateUrl('kit_dialogs'), 'demo-token'),
        ]);
    }

    /**
     * UiKit 2.22: a 1.x row-action POST hash with confirm_mode native on a page without Stimulus, i.e. without the
     * confirm-submit peer: no inline handler, js/nowo-ui-confirm.js warns once in the console and (2.23) falls back to
     * window.confirm() so the form never submits unconfirmed (docs/CONFIGURATION.md#row-actions-confirm-222).
     */
    #[Route('/row-actions-confirm', name: 'kit_row_actions_confirm', methods: ['GET', 'POST'])]
    public function rowActionsConfirm(Request $request): Response
    {
        return $this->render('kit/row_actions_confirm.html.twig', ['submitted' => $request->isMethod('POST')]);
    }
}
