<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Listing;

/**
 * The {@see ListingDefinitionInterface} services (tag `nowo_ui_kit.listing_definition`) by listing key.
 */
final readonly class ListingRegistry
{
    /** @var array<string, ListingDefinitionInterface> */
    private array $definitions;

    /**
     * @param iterable<ListingDefinitionInterface> $definitions
     */
    public function __construct(iterable $definitions = [])
    {
        $byKey = [];
        foreach ($definitions as $definition) {
            $key = $definition->key();
            if (isset($byKey[$key])) {
                throw new \LogicException(\sprintf('Two listing definitions use the key "%s" (%s, %s).', $key, $byKey[$key]::class, $definition::class));
            }
            $byKey[$key] = $definition;
        }
        $this->definitions = $byKey;
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    public function get(string $key): ListingDefinitionInterface
    {
        return $this->definitions[$key] ?? throw new \InvalidArgumentException(\sprintf('Unknown listing "%s" (known: %s).', $key, implode(', ', $this->keys()) ?: 'none'));
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->definitions);
    }
}
