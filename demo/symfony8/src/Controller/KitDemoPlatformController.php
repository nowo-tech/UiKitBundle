<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Routing\Attribute\Route;

/**
 * UiKit 2.60 demo: the shared demo platform shell (@NowoUiKit/demo_platform/layout.html.twig) that every nowo-tech
 * bundle demo extends. A fake "Acme Inbox" bundle demo declares its nav (two sections, a prefix-matched admin item),
 * its locales and its repository in one Twig variable. The profiler toolbar is off for these pages, so the axe checks
 * of e2e/tests/demo-platform.spec.ts only see the page. Stateless: the page id comes from the route.
 */
final class KitDemoPlatformController extends AbstractController
{
    private const PAGES = ['overview', 'messages', 'settings'];

    public function __construct(
        #[Autowire(service: 'profiler')]
        private readonly ?Profiler $profiler = null,
    ) {
    }

    #[Route('/kit/demo-platform/{page}', name: 'kit_demo_platform', requirements: ['page' => 'overview|messages|settings'], defaults: ['page' => 'overview'])]
    public function __invoke(string $page): Response
    {
        $this->profiler?->disable();

        return $this->render('kit/demo_platform.html.twig', ['page' => \in_array($page, self::PAGES, true) ? $page : 'overview']);
    }
}
