<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\HostChromeFilterType;
use App\Form\KitchenSinkSwatchType;
use App\Form\LocaleTabsDemoType;
use Nowo\UiKitBundle\Listing\SortRequest;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\Table;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class KitchenSinkController extends AbstractController
{
    /** Rows of the sortable table examples (2.20). */
    private const FILES = [
        ['name' => 'report-2026.pdf', 'owner' => 'Ada Lovelace', 'size' => '2.4 MB', 'bytes' => 2516582, 'updated' => '2026-10-16 09:12'],
        ['name' => 'Budget.xlsx', 'owner' => 'Grace Hopper', 'size' => '840 kB', 'bytes' => 860160, 'updated' => '2026-10-12 17:40'],
        ['name' => 'notes.txt', 'owner' => 'Alan Turing', 'size' => '12 kB', 'bytes' => 12288, 'updated' => '2026-10-15 08:03'],
        ['name' => 'archive-10.zip', 'owner' => 'Ada Lovelace', 'size' => '96 MB', 'bytes' => 100663296, 'updated' => '2026-09-30 18:22'],
    ];

    #[Route('/', name: 'nowo_ui_kit_kitchen_sink')]
    public function __invoke(Request $request, FormFactoryInterface $forms): Response
    {
        // 2.20 sortable headers: server-side (?sort= / ?dir= links read by a SortRequest) and client-side (Stimulus peer table-sort)
        $sort = SortRequest::fromRequest($request, ['name' => 'asc', 'owner' => 'asc', 'size' => 'desc', 'updated' => 'desc'], 'updated');
        $columns = [
            new Column('name', 'Name', true),
            new Column('owner', 'Owner', true),
            new Column('size', 'Size', true, 'end', sortFirst: 'desc', sortType: 'number', sortValueKey: 'bytes'),
            new Column('updated', 'Updated', true, sortFirst: 'desc', sortType: 'date'),
        ];

        return $this->render('@NowoUiKitBundle/demo/kitchen_sink.html.twig', [
            'kitchen_tables' => [
                new Table($columns, $sort->sortRows(self::FILES, ['size' => 'bytes']), caption: 'Sortable headers — server side (links)', responsive: 'scroll', sorting: $sort),
                new Table($columns, self::FILES, caption: 'Sortable headers — client side (Stimulus peer table-sort)', responsive: 'scroll', clientSort: true),
            ],
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
