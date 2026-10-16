<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * View model of `@NowoUiKit/page/confirm.html.twig`: full-page confirm (no-JS fallback of the confirm dialog).
 * `action` is the non-GET action submitted by the confirm button.
 */
final readonly class ConfirmPage
{
    public function __construct(
        public PageHeader $header,
        public Confirm $confirm,
        public Action $action,
        public ?string $cancelHref = null,
        public ?string $bundle = null,
        public ?string $layout = null,
    ) {
        if (!$action->isPost()) {
            throw new \InvalidArgumentException('ConfirmPage needs a non-GET action (it renders the POST form).');
        }
    }
}
