<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The `multi-select` peer on its own page, for the panel's motion. The page forbids style attributes. Fixtures only.
 */
final class KitMultiSelectController extends AbstractController
{
    #[Route('/kit/multi-select', name: 'kit_multi_select')]
    public function __invoke(FormFactoryInterface $forms): Response
    {
        $form = $forms->createNamedBuilder('', FormType::class, null, ['method' => 'GET'])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'required' => false,
                'multiple' => true,
                'nowo_ui_multi_select' => true,
                'choices' => ['Active' => 'active', 'Cancelled' => 'cancelled', 'Pending' => 'pending'],
            ])
            ->getForm();
        $response = $this->render('kit/multi_select.html.twig', ['form' => $form->createView()]);
        $response->headers->set('Content-Security-Policy', "script-src 'self'; style-src-attr 'none'; script-src-attr 'none'");

        return $response;
    }
}
