<?php

declare(strict_types=1);

namespace App\Controller;

use Nowo\UiKitBundle\Shell\HeaderPresentation;
use Nowo\UiKitBundle\Shell\SidebarLayout;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Routing\Attribute\Route;

/**
 * UiKit 2.32 demo: the app shell's sidebar layout. `?layout=below-header|full-height` (default full-height) sets the
 * request attribute `_nowo_ui_sidebar_layout`, as a host listener would from the user's preference; `?mode=expanded|
 * rail|hidden` is the server-rendered aside mode; `?hover=1` turns on the hover-expanded rail; `?footer=fixed` pins the
 * footer (it follows the aside through --nowo-ui-shell-inset-start); `?header=compact` fills the compact header slot.
 * The profiler toolbar is off for this page, so the e2e geometry and axe checks only see the shell.
 */
final class KitSidebarLayoutController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'profiler')]
        private readonly ?Profiler $profiler = null,
    ) {
    }

    private const ROWS = ['Invoice #1042', 'Invoice #1041', 'Invoice #1040', 'Refund #77', 'Invoice #1039', 'Invoice #1038', 'Credit note #12', 'Invoice #1037', 'Invoice #1036', 'Invoice #1035', 'Invoice #1034', 'Refund #76', 'Invoice #1033', 'Invoice #1032', 'Invoice #1031', 'Invoice #1030'];

    #[Route('/demo/sidebar-layout', name: 'kit_sidebar_layout')]
    public function __invoke(Request $request): Response
    {
        $this->profiler?->disable();
        $layout = SidebarLayout::fromValue($request->query->get('layout')) ?? SidebarLayout::FullHeight;
        $mode = \in_array($request->query->get('mode'), ['rail', 'hidden'], true) ? (string) $request->query->get('mode') : 'expanded';
        $compact = 'compact' === $request->query->get('header');
        $request->attributes->set(SidebarLayout::ATTRIBUTE, $layout->value);
        $request->attributes->set(HeaderPresentation::ATTRIBUTE, $compact ? HeaderPresentation::Compact->value : HeaderPresentation::Standard->value);
        $query = array_filter([
            'layout' => $layout->value,
            'mode' => 'expanded' === $mode ? null : $mode,
            'hover' => $request->query->getBoolean('hover') ? '1' : null,
            'footer' => 'fixed' === $request->query->get('footer') ? 'fixed' : null,
            'header' => $compact ? 'compact' : null,
        ]);

        return $this->render('kit/sidebar_layout.html.twig', [
            'query' => $query,
            'mode' => $mode,
            'hover' => isset($query['hover']),
            'footer_fixed' => isset($query['footer']),
            'compact' => $compact,
            'rows' => self::ROWS,
        ]);
    }
}
