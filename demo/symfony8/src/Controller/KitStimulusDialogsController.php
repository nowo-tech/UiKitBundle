<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\DemoDestinationType;
use Nowo\UiKitBundle\Dialog\RemoteDialog;
use Nowo\UiKitBundle\Dialog\RemoteDialogResponder;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Confirm;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * UiKit 2.70 demo: the on-demand dialogs driven by the Stimulus peers. `/kit/stimulus-dialogs/stimulus` loads NO
 * js/nowo-ui-dialog.js / js/nowo-ui-confirm.js (a Beacon product page): row kebab menus share one confirm dialog
 * (`confirm_group`, the `confirm-shared` peer) and the rows' "Edit" openers share one remote form shell (`runtime:
 * 'stimulus'`, the `remote-form-dialog` peer in delegated mode). `/iife` is the same page on the IIFE runtimes (default
 * markup); `/both` prints the Stimulus markup and loads both runtimes (the peers win, nothing is handled twice).
 * Each row's hidden POST form carries its own token (an HMAC of the row; the demo has no session): the remove action
 * checks it, so the e2e proves the shared dialog submitted the right row. Stateless (FrankenPHP worker mode).
 */
#[Route('/kit/stimulus-dialogs/{runtime}', requirements: ['runtime' => 'stimulus|iife|both'])]
final class KitStimulusDialogsController extends AbstractController
{
    private const MEMBERS = [1 => 'Ann', 2 => 'Bob', 3 => 'Cy'];

    private const CSP = "script-src 'self'; style-src-attr 'none'; script-src-attr 'none'";

    public function __construct(#[Autowire('%kernel.secret%')] private readonly string $secret)
    {
    }

    #[Route('', name: 'kit_stimulus_dialogs', methods: ['GET'])]
    public function index(Request $request, string $runtime): Response
    {
        $members = [];
        foreach (self::MEMBERS as $id => $name) {
            $members[] = [
                'id' => $id,
                'name' => $name,
                'actions' => [
                    Action::delete(
                        $this->generateUrl('kit_stimulus_dialogs_remove', ['runtime' => $runtime, 'id' => $id]),
                        $this->token($id),
                        new Confirm(\sprintf('Remove %s?', $name), \sprintf('%s loses access to the project.', $name), 'Remove'),
                    ),
                ],
            ];
        }
        $removed = $request->query->getInt('removed');
        $saved = $request->query->getString('saved');
        $response = $this->render('kit/stimulus_dialogs.html.twig', [
            'runtime' => $runtime,
            'markup_runtime' => 'iife' === $runtime ? 'iife' : 'stimulus',
            'members' => $members,
            'removed' => self::MEMBERS[$removed] ?? null,
            'saved' => 1 === preg_match('/^[\pL\pN .()-]{1,60}$/u', $saved) ? $saved : null,
            'reset' => $request->query->getBoolean('reset'),
            // The confirm-submit demo (2.70): a nameless GET form whose one hidden field is `reset=1` (REQ-TWIG-005).
            'reset_form' => $this->container->get('form.factory')->createNamedBuilder('', FormType::class, ['reset' => '1'], [
                'method' => 'GET',
                'action' => $this->generateUrl('kit_stimulus_dialogs', ['runtime' => $runtime]),
            ])->add('reset', HiddenType::class)->getForm()->createView(),
        ]);
        $response->headers->set('Content-Security-Policy', self::CSP);

        return $response;
    }

    /** The remove action of one row: its own token or 403, then back to the page. */
    #[Route('/members/{id}/remove', name: 'kit_stimulus_dialogs_remove', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function remove(Request $request, string $runtime, int $id): Response
    {
        if (!isset(self::MEMBERS[$id]) || !hash_equals($this->token($id), $request->request->getString('_token'))) {
            throw new AccessDeniedHttpException('Invalid row token.');
        }

        return $this->redirectToRoute('kit_stimulus_dialogs', ['runtime' => $runtime, 'removed' => $id], Response::HTTP_SEE_OTHER);
    }

    /** The edit form of one row, loaded into the shared remote form shell (422 when invalid, 204 + X-Nowo-Redirect once saved). */
    #[Route('/members/{id}/edit', name: 'kit_stimulus_dialogs_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, RemoteDialogResponder $dialog, string $runtime, int $id): Response
    {
        $form = $this->createForm(DemoDestinationType::class, null, ['action' => $this->generateUrl('kit_stimulus_dialogs_edit', ['runtime' => $runtime, 'id' => $id])]);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $label = trim((string) $form->get('label')->getData());
            if ('' === $label) {
                $form->get('label')->addError(new FormError('Enter a label.'));
            } elseif ($form->isValid()) {
                return $dialog->redirectToRoute('kit_stimulus_dialogs', ['runtime' => $runtime, 'saved' => \sprintf('%s (%s)', $label, self::MEMBERS[$id] ?? '?')]);
            }
        }
        $response = $this->render($dialog->template('kit/remote_form_new.html.twig', 'kit/_remote_form_fields.html.twig'), ['form' => $form->createView()]);
        $response->headers->set('Content-Security-Policy', self::CSP);

        return RemoteDialog::vary($response);
    }

    private function token(int $id): string
    {
        return substr(hash_hmac('sha256', 'remove-member-'.$id, $this->secret), 0, 32);
    }
}
