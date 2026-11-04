<?php

declare(strict_types=1);

namespace App\Controller;

use Nowo\UiKitBundle\Shell\HeaderPresentation;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\Crumb;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\PageStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Routing\Attribute\Route;

/**
 * UiKit 2.27 demo: the compact header. `?presentation=standard|compact` (default compact) sets the request attribute
 * `_nowo_ui_header_presentation`, as a host listener would from the user's preference; `?dir=rtl` mirrors the page.
 * A six-level trail (Home › Sales › Customers › ACME › Quotes › Q-1042), a brand, a page-actions menu with a confirm
 * action and three right-hand controls. The response carries a strict CSP (nonces for the theme boot and the token
 * style, no inline style attributes or handlers); the e2e asserts zero violations. The dev tools that inject inline
 * code are off for this page (the profiler toolbar here, hot reload in config/packages/dev), so every violation the
 * e2e could see would be UiKit's.
 */
final class KitCompactHeaderController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'profiler')]
        private readonly ?Profiler $profiler = null,
    ) {
    }

    #[Route('/demo/compact-header', name: 'kit_compact_header', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $query = array_filter([
            'presentation' => 'standard' === $request->query->get('presentation') ? 'standard' : null,
            'dir' => 'rtl' === $request->query->get('dir') ? 'rtl' : null,
        ]);
        if ($request->isMethod('POST')) {
            return $this->redirectToRoute('kit_compact_header', $query);
        }
        $this->profiler?->disable();
        $request->attributes->set(HeaderPresentation::ATTRIBUTE, $query['presentation'] ?? HeaderPresentation::Compact->value);
        $nonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        $request->attributes->set('csp_nonce', $nonce);

        $at = fn (string $level): string => $this->generateUrl('kit_compact_header', $query + ['at' => $level]);
        $self = $this->generateUrl('kit_compact_header', $query);
        $header = new PageHeader(
            'Q-1042 · Annual maintenance renewal for ACME Corporation',
            'Quote sent on 2 November; the customer has 30 days to accept it.',
            [
                Action::edit($at('edit'))->asIcon(),
                new Action('export', 'Export PDF', $at('pdf')),
                new Action('copy', 'Duplicate', $self, method: 'POST', csrfToken: 'demo'),
                Action::delete($self, 'demo', new Confirm('Delete Q-1042?', 'The quote and its lines are removed. This cannot be undone.')),
            ],
            [
                new Crumb('Home', $this->generateUrl('nowo_ui_kit_kitchen_sink')),
                new Crumb('Sales', $at('sales')),
                new Crumb('Customers of the northern region and partners', $at('customers')),
                new Crumb('ACME Corporation', $at('acme')),
                new Crumb('Quotes', $at('quotes')),
                new Crumb('Q-1042 · Annual maintenance renewal for ACME Corporation'),
            ],
            breadcrumbsMode: 'own',
            status: new PageStatus('Sent', 'info'),
        );

        $policy = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-".$nonce."'",
            "style-src 'self' 'nonce-".$nonce."'",
            "style-src-attr 'none'",
            "script-src-attr 'none'",
            "img-src 'self' data:",
            "font-src 'self'",
            "object-src 'none'",
            "base-uri 'none'",
        ]);
        $response = $this->render('kit/compact_header.html.twig', [
            'header' => $header->withSplitActions(),
            'query' => $query,
            'dir' => $query['dir'] ?? 'ltr',
            'at' => (string) $request->query->get('at', ''),
            // Also delivered as <meta http-equiv>: in the full demo e2e run (FrankenPHP worker, dev tools on) the header
            // is sometimes missing from this response, so the e2e asserts the policy from the <meta>.
            'csp' => $policy,
        ]);
        $response->headers->set('Content-Security-Policy', $policy);

        return $response;
    }
}
