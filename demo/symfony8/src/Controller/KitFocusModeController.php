<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Routing\Attribute\Route;

/**
 * UiKit 2.54 demo: the shell focus mode (`nowo_ui_kit.theme.focus_mode`, enabled in config/packages/nowo_ui_kit.yaml
 * with `keep: [breadcrumb_min, search, timer_chip]`). A wiki-like page in the kit shell: the page toolbar has the
 * `focus_mode_toggle` partial (area `wiki`) and a fullscreen toggle; the header keeps a search field (Ctrl+K focuses
 * it, js/focus-mode-demo.js) and a timer chip; a side panel, the footer and a messages dock (a host element marked
 * data-nowo-ui-focus-region="dock") disappear in focus mode. The response forbids inline style attributes, inline
 * handlers and inline scripts (CSP), which the e2e checks while it toggles the mode. The profiler toolbar is off for this
 * page, so the CSP and axe checks only see the page.
 */
final class KitFocusModeController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'profiler')]
        private readonly ?Profiler $profiler = null,
    ) {
    }

    #[Route('/kit/focus-mode', name: 'kit_focus_mode')]
    public function __invoke(): Response
    {
        $this->profiler?->disable();
        $response = $this->render('kit/focus_mode.html.twig');
        $response->headers->set('Content-Security-Policy', "script-src 'self'; style-src-attr 'none'; script-src-attr 'none'");

        return $response;
    }
}
