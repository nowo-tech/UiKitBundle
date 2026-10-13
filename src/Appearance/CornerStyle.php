<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Appearance;

/**
 * Corner style: cards get a larger radius, controls (inputs, buttons) a subtler one.
 */
enum CornerStyle: string
{
    case Sharp = 'sharp';
    case Soft = 'soft';
    case Rounded = 'rounded';

    /**
     * Unknown / empty values fall back to {@see self::Soft}.
     *
     * @return self Fluent interface
     */
    public static function fromLoose(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Soft;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * @return array{card: string, control: string}
     */
    public function radii(): array
    {
        return match ($this) {
            self::Sharp => ['card' => '0.125rem', 'control' => '0.0625rem'],
            self::Rounded => ['card' => '1.25rem', 'control' => '0.5rem'],
            self::Soft => ['card' => '0.75rem', 'control' => '0.375rem'],
        };
    }
}
