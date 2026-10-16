<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * Step of a {@see WizardPage}. Status (done / current / upcoming) comes from the page's current step.
 */
final readonly class WizardStep
{
    public function __construct(
        public string $id,
        public Label|string $label,
        public ?string $href = null,
    ) {
        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $id)) {
            throw new \InvalidArgumentException(\sprintf('Invalid WizardStep id "%s".', $id));
        }
    }
}
