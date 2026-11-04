<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * UiKit 2.26 demo: partials/_avatar.html.twig with a same-origin picture, a broken URL (falls back to the initials),
 * URLs the policy rejects (http:, data:, //host: initials only), the four sizes, and the shell user menu with
 * avatar_src. Fixtures only.
 */
final class KitAvatarController extends AbstractController
{
    #[Route('/kit/avatars', name: 'kit_avatars')]
    public function __invoke(): Response
    {
        return $this->render('kit/avatars.html.twig', [
            'picture' => '/images/avatar-ada.svg',
            'people' => [
                ['testid' => 'avatar-picture', 'name' => 'Ada Lovelace', 'src' => '/images/avatar-ada.svg'],
                ['testid' => 'avatar-broken', 'name' => 'Grace Hopper', 'src' => '/does-not-exist.webp'],
                ['testid' => 'avatar-http', 'name' => 'Alan Turing', 'src' => 'http://example.com/turing.png'],
                ['testid' => 'avatar-data', 'name' => 'Katherine Johnson', 'src' => 'data:image/png;base64,iVBORw0KGgo='],
                ['testid' => 'avatar-relative', 'name' => 'Edsger Dijkstra', 'src' => '//example.com/dijkstra.png'],
                ['testid' => 'avatar-none', 'name' => 'Barbara Liskov', 'src' => null],
            ],
        ]);
    }
}
