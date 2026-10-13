<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Appearance;

/**
 * How strongly borders read against the paper colour: the share of ink mixed into the border colour
 * (light / dark) and the border width.
 */
enum BorderStrength: string
{
    case Subtle = 'subtle';
    case Medium = 'medium';
    case Strong = 'strong';

    /**
     * Unknown / empty values fall back to {@see self::Medium}.
     *
     * @return self Fluent interface
     */
    public static function fromLoose(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Medium;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * @return array{mixLight: int, mixDark: int, width: string}
     */
    public function tokens(): array
    {
        return match ($this) {
            self::Subtle => ['mixLight' => 7, 'mixDark' => 9, 'width' => '1px'],
            self::Strong => ['mixLight' => 26, 'mixDark' => 30, 'width' => '1.5px'],
            self::Medium => ['mixLight' => 14, 'mixDark' => 16, 'width' => '1px'],
        };
    }
}
