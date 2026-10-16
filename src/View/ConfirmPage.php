<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * View model of `@NowoUiKit/page/confirm.html.twig`: full-page confirm (no-JS fallback of the confirm dialog).
 * `action` is the non-GET action submitted by the confirm button.
 */
final readonly class ConfirmPage
{
    /**
     * @param array<string, string|int|float|bool|null> $attributes extra attributes of the scaffold root (data-testid…; no on* handlers)
     */
    public function __construct(
        public PageHeader $header,
        public Confirm $confirm,
        public Action $action,
        public ?string $cancelHref = null,
        public ?string $bundle = null,
        public ?string $layout = null,
        public array $attributes = [],
    ) {
        Action::assertAttributes($attributes);
        if (!$action->isPost()) {
            throw new \InvalidArgumentException('ConfirmPage needs a non-GET action (it renders the POST form).');
        }
    }
}
