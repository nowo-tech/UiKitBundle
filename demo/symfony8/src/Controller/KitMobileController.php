<?php

declare(strict_types=1);

namespace App\Controller;

use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\Table;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * UiKit demo (2.41 phone mode, Beacon mobile UX audit 2026-11-09): page-edge tabs folded behind one launcher, selects
 * WebKit no longer cuts, phone cards of a responsive table and of a `table` with Column::phone, the guest header.
 * The response forbids inline style attributes, inline scripts and inline handlers (CSP). Fixtures only.
 */
final class KitMobileController extends AbstractController
{
    private const USERS = [
        ['name' => 'Demo Admin', 'email' => 'admin@example.test', 'role' => 'Administrator', 'status' => 'Active', 'id' => 1],
        ['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'role' => 'Manager', 'status' => 'Invited', 'id' => 2],
        ['name' => 'Grace Hopper', 'email' => 'grace@example.test', 'role' => 'Member', 'status' => 'Active', 'id' => 3],
    ];

    #[Route('/kit/mobile', name: 'kit_mobile', methods: ['GET'])]
    public function index(): Response
    {
        $table = new Table([
            new Column('name', 'User', phone: 'primary'),
            new Column('email', 'Email', phone: 'meta'),
            new Column('role', 'Role'),
            new Column('status', 'Status'),
            new Column('id', 'Id', phone: 'hide'),
        ], self::USERS, label: 'Users (Column::phone)');

        return $this->csp($this->render('kit/mobile.html.twig', ['users' => self::USERS, 'users_table' => $table]));
    }

    #[Route('/kit/mobile/guest', name: 'kit_mobile_guest', methods: ['GET'])]
    public function guest(): Response
    {
        return $this->csp($this->render('kit/mobile_guest.html.twig'));
    }

    private function csp(Response $response): Response
    {
        $response->headers->set('Content-Security-Policy', "script-src 'self'; style-src-attr 'none'; script-src-attr 'none'");

        return $response;
    }
}
