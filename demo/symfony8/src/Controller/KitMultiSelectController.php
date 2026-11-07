<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The `multi-select` peer on its own page, for the panel's motion. The page forbids style attributes. Fixtures only.
 */
final class KitMultiSelectController extends AbstractController
{
    #[Route('/kit/multi-select', name: 'kit_multi_select')]
    public function __invoke(): Response
    {
        $response = $this->render('kit/multi_select.html.twig');
        $response->headers->set('Content-Security-Policy', "script-src 'self'; style-src-attr 'none'; script-src-attr 'none'");

        return $response;
    }
}
