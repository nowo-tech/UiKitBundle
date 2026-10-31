<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\HostChromeFilterType;
use App\Form\KitchenSinkSwatchType;
use App\Form\LocaleTabsDemoType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class KitchenSinkController extends AbstractController
{
    #[Route('/', name: 'nowo_ui_kit_kitchen_sink')]
    public function __invoke(FormFactoryInterface $forms): Response
    {
        return $this->render('@NowoUiKitBundle/demo/kitchen_sink.html.twig', [
            // REQ-TWIG-005: the kitchen sink's form controls are Symfony Forms (form_start / form_widget / form_end)
            'kitchen_forms' => [
                'swatch' => $forms->createNamed('swatch', KitchenSinkSwatchType::class)->createView(),
                'swatch_themed' => $forms->createNamed('swatch_themed', KitchenSinkSwatchType::class)->createView(),
                'locale_tabs' => $forms->createNamed('', LocaleTabsDemoType::class, null, [
                    'entries' => [
                        ['locale' => 'es', 'title' => 'Bienvenida', 'body' => 'Hola y bienvenido.'],
                        ['locale' => 'en', 'title' => '', 'body' => 'Hello and welcome.'],
                        ['locale' => 'ar', 'title' => 'مرحبا', 'body' => ''],
                    ],
                    'required_locales' => ['es', 'en'],
                ])->createView(),
                'host_chrome' => $forms->createNamed('', HostChromeFilterType::class)->createView(),
            ],
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
