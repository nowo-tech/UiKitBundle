<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class KitchenSinkController extends AbstractController
{
    #[Route('/', name: 'nowo_ui_kit_kitchen_sink')]
    public function __invoke(): Response
    {
        return $this->render('@NowoUiKitBundle/demo/kitchen_sink.html.twig', [
            'layout_template' => 'base.html.twig',
            'pagination'      => [
                'page'        => 2,
                'per_page'    => 10,
                'total'       => 42,
                'total_pages' => 5,
            ],
            'item_count'   => 10,
            'route'        => 'nowo_ui_kit_kitchen_sink',
            'route_params' => [],
        ]);
    }
}
