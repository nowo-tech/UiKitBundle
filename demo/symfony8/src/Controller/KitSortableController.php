<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * UiKit demo: js/nowo-ui-sortable.js on a small board (cards and columns on one page) and a horizontal list.
 * Fixtures only: the "write" is simulated in the page script. The response forbids inline style attributes, inline
 * scripts and inline handlers (CSP), which the e2e checks while it drags.
 */
final class KitSortableController extends AbstractController
{
    #[Route('/kit/sortable', name: 'kit_sortable')]
    public function index(): Response
    {
        $response = $this->render('kit/sortable.html.twig', [
            'columns' => [
                ['id' => 'todo', 'title' => 'To do', 'cards' => array_map(static fn (int $n): array => ['id' => 't'.$n, 'title' => 'Task '.$n], range(1, 12))],
                ['id' => 'doing', 'title' => 'Doing', 'cards' => [
                    ['id' => 'd1', 'title' => 'Refused card', 'refuse' => true],
                    ['id' => 'd2', 'title' => 'Failing card', 'fail' => true],
                ]],
                ['id' => 'done', 'title' => 'Done', 'cards' => []],
            ],
            'steps' => ['Draft', 'Review', 'Publish'],
        ]);
        $response->headers->set('Content-Security-Policy', "script-src 'self'; style-src-attr 'none'; script-src-attr 'none'");

        return $response;
    }
}
