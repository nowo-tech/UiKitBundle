<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * @internal typed list normalisation for the view models
 */
final class ViewList
{
    /**
     * @template T of object
     *
     * @param iterable<mixed> $items
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    public static function of(iterable $items, string $class, string $what): array
    {
        $list = [];
        foreach ($items as $item) {
            if (!$item instanceof $class) {
                throw new \InvalidArgumentException(\sprintf('%s: expected %s, got %s.', $what, $class, get_debug_type($item)));
            }
            $list[] = $item;
        }

        return $list;
    }
}
