<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * One link of an {@see AreaNav}: label, target and whether it is the current section (`aria-current="page"`).
 */
final readonly class AreaNavItem
{
    /**
     * @param array<string, string|int|float|bool|null> $attributes extra HTML attributes of the link (no on* handlers)
     */
    public function __construct(
        public Label|string $label,
        public string $href,
        public bool $active = false,
        public array $attributes = [],
    ) {
        Action::assertAttributes($attributes);
    }

    /**
     * Copy with another active state.
     *
     * @return self Fluent interface
     */
    public function withActive(bool $active): self
    {
        return new self($this->label, $this->href, $active, $this->attributes);
    }
}
