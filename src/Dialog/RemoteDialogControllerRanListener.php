<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Dialog;

use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * 2.74 (audit ZBD-L1; registered only when a `remote_dialog.direct_get` entry has a `page_resolver`): marks a request
 * whose controller is about to run, after every `kernel.controller_arguments` access listener (`#[IsGranted]` runs at
 * 20, this at -1024), and removes the mark when the request ends in an exception. {@see RemoteDialogReopen} calls a
 * page resolver only for a marked request, so a `200` that the controller never produced (an authentication entry
 * point or an interstitial answering `200`, an exception page rendered as `200`) never reaches a resolver.
 *
 * The mark is a request attribute (per request, nothing kept in the service): FrankenPHP worker safe.
 *
 * @internal wiring of `remote_dialog.direct_get`; hosts and kits configure it, they do not call it (no public port: REQ-ARCH-001 rule 3)
 */
final readonly class RemoteDialogControllerRanListener
{
    public const ATTRIBUTE = '_nowo_ui_dialog_controller_ran';

    public function onControllerArguments(ControllerArgumentsEvent $event): void
    {
        $event->getRequest()->attributes->set(self::ATTRIBUTE, true);
    }

    public function onException(ExceptionEvent $event): void
    {
        $event->getRequest()->attributes->remove(self::ATTRIBUTE);
    }
}
