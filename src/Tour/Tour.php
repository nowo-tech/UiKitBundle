<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tour;

/**
 * A guided product tour of one screen (2.13): a stable `key` (the "seen" state is stored per key) and its ordered
 * {@see TourStep}s. Pass it to the `tour` partial (`nowo_ui_partial('tour')`); build it with {@see TourBuilder}. The
 * host decides which steps a viewer gets (permissions, feature flags); UiKit only renders and runs them.
 */
final readonly class Tour
{
    /**
     * @param list<TourStep> $steps
     * @param bool           $autoStart start once on the first visit (the key is not in the browser's seen map yet)
     */
    public function __construct(
        public string $key,
        public array $steps = [],
        public bool $autoStart = true,
    ) {
        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]*$/', $key)) {
            throw new \InvalidArgumentException(\sprintf('Invalid Tour key "%s" (letters, digits, "_", ".", ":" and "-").', $key));
        }
    }

    public function isEmpty(): bool
    {
        return [] === $this->steps;
    }
}
