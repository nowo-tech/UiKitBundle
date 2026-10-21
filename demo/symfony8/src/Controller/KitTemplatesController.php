<?php

declare(strict_types=1);

namespace App\Controller;

use Nowo\UiKitBundle\View\Pagination;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * UiKit 2.6 kit templates demo: the error pages (@NowoUiKit/error/status.html.twig, previewed per status code with a
 * 200 response), the signed-in app shell (@NowoUiKit/shell/*), the confirm dialog wrapper and the compact pagination.
 * The real error pages of this demo use the one-line templates/bundles/TwigBundle/Exception/error.html.twig.
 */
#[Route('/kit')]
final class KitTemplatesController extends AbstractController
{
    private const ROWS = ['Invoice #1042', 'Invoice #1041', 'Invoice #1040', 'Refund #77', 'Invoice #1039', 'Invoice #1038', 'Credit note #12', 'Invoice #1037', 'Invoice #1036', 'Invoice #1035', 'Invoice #1034', 'Refund #76'];

    #[Route('/errors/{code}', name: 'kit_error_preview', requirements: ['code' => '4\d\d|5\d\d'])]
    public function error(int $code): Response
    {
        return $this->render('@NowoUiKit/error/status.html.twig', [
            'status_code' => $code,
            'exception' => 429 === $code ? ['Retry-After' => '30'] : null,
        ]);
    }

    #[Route('/shell', name: 'kit_shell')]
    public function shell(Request $request): Response
    {
        $pagination = Pagination::fromRequest($request, \count(self::ROWS), 5, [5, 10, 25], variant: Pagination::VARIANT_COMPACT);

        return $this->render('kit/shell.html.twig', [
            'pagination' => $pagination,
            'rows' => \array_slice(self::ROWS, $pagination->offset(), $pagination->limit()),
        ]);
    }
}
