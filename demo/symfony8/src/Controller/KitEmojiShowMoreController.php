<?php

declare(strict_types=1);

namespace App\Controller;

use Nowo\UiKitBundle\Form\EmojiPickerType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * UiKit demo: the emoji picker field and the show more fold. The response forbids inline style attributes, inline scripts and
 * inline handlers (CSP), which the e2e checks. Fixtures only.
 */
final class KitEmojiShowMoreController extends AbstractController
{
    #[Route('/kit/emoji-show-more', name: 'kit_emoji_show_more', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $form = $this->createFormBuilder(['icon' => '🚀'])
            ->add('icon', EmojiPickerType::class, ['label' => 'Board icon', 'required' => false])
            ->add('save', SubmitType::class, ['label' => 'Save'])
            ->getForm();
        $form->handleRequest($request);
        $saved = $form->isSubmitted() && $form->isValid() ? (string) $form->get('icon')->getData() : null;

        $response = $this->render('kit/emoji_show_more.html.twig', ['form' => $form->createView(), 'saved' => $saved], new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200));
        $response->headers->set('Content-Security-Policy', "script-src 'self'; style-src-attr 'none'; script-src-attr 'none'");

        return $response;
    }
}
