<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * UiKit demo (2.54): context menus. Rows and cards whose kebab / row actions open the same items on a right-click, the
 * ContextMenu key, Shift+F10 or a long press. The page forbids inline style attributes, inline scripts and inline handlers
 * (CSP), which the e2e checks. Stateless: the chosen item comes back in the query string or as a POST (FrankenPHP worker mode).
 */
final class KitContextMenuController extends AbstractController
{
    #[Route('/kit/context-menu', name: 'kit_context_menu', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $picked = $request->query->get('picked');
        $response = $this->render('kit/context_menu.html.twig', [
            'picked' => \is_string($picked) && 1 === preg_match('/^[a-z0-9-]{1,40}$/', $picked) ? $picked : null,
            'submitted' => $request->isMethod('POST'),
        ]);
        $response->headers->set('Content-Security-Policy', "script-src 'self'; style-src-attr 'none'; script-src-attr 'none'");

        return $response;
    }
}
