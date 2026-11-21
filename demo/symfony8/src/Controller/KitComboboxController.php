<?php

declare(strict_types=1);

namespace App\Controller;

use Nowo\UiKitBundle\Form\ComboboxType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * UiKit demo (2.53): the async grouped combobox. The page forbids inline style attributes, inline scripts and inline handlers (CSP),
 * which the e2e checks. The search endpoint answers from fixtures only and keeps nothing between requests (FrankenPHP worker mode).
 */
final class KitComboboxController extends AbstractController
{
    /** Fixture pages: [id, title, path, wiki]. One title is markup, to show that it stays text. */
    private const PAGES = [
        ['1', 'Getting started', '/handbook/getting-started', 'This wiki'],
        ['2', 'Install the kit', '/handbook/install', 'This wiki'],
        ['3', 'Release notes', '/handbook/release-notes', 'This wiki'],
        ['4', 'Start here', '/ops/start-here', 'Other wikis'],
        ['5', 'Status page', '/ops/status', 'Other wikis'],
        ['6', '<img src=x onerror="document.title=\'pwned\'">', '/ops/markup', 'Other wikis'],
    ];

    #[Route('/kit/combobox', name: 'kit_combobox', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $search = $this->generateUrl('kit_combobox_search');
        $form = $this->createFormBuilder()
            ->add('page', ComboboxType::class, ['label' => 'Wiki page', 'required' => false, 'url' => $search, 'help' => 'Type at least two letters, e.g. "st".'])
            ->add('link', TextType::class, ['label' => 'Link to page', 'required' => false])
            ->add('link_id', HiddenType::class)
            ->add('save', SubmitType::class, ['label' => 'Save', 'attr' => ['class' => 'nowo-ui-btn nowo-ui-btn--primary']])
            ->getForm();
        $form->handleRequest($request);
        $saved = $form->isSubmitted() && $form->isValid() ? [
            'page' => (string) $form->get('page')->getData(),
            'link' => (string) $form->get('link')->getData(),
            'link_id' => (string) $form->get('link_id')->getData(),
        ] : null;

        $response = $this->render('kit/combobox.html.twig', ['form' => $form->createView(), 'saved' => $saved, 'search' => $search]);
        $response->headers->set('Content-Security-Policy', "script-src 'self'; style-src-attr 'none'; script-src-attr 'none'");

        return $response;
    }

    /** The host endpoint: `?q=` matched against the titles, answered in the combobox's grouped shape. */
    #[Route('/kit/combobox/search', name: 'kit_combobox_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $term = mb_strtolower(trim($request->query->getString('q')));
        $groups = [];
        if (mb_strlen($term) >= 2) {
            foreach (self::PAGES as [$id, $title, $path, $wiki]) {
                if (str_contains(mb_strtolower($title), $term)) {
                    $groups[$wiki][] = ['value' => $id, 'label' => $title, 'description' => $path];
                }
            }
        }
        $answer = [];
        foreach ($groups as $label => $options) {
            $answer[] = ['label' => $label, 'options' => $options];
        }

        return new JsonResponse(['groups' => $answer], 200, ['Cache-Control' => 'no-store']);
    }
}
