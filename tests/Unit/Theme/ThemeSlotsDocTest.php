<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Theme;

use Nowo\UiKitBundle\Theme\ThemeSlots;
use PHPUnit\Framework\TestCase;

/**
 * docs/THEME_SLOTS.md lists every slot with its semantic class and framework defaults, and every partial name.
 */
final class ThemeSlotsDocTest extends TestCase
{
    /**
     * One table row per slot, as documented.
     */
    public static function row(string $slot): string
    {
        $cell = static fn (string $classes): string => '' === $classes ? '—' : '`'.$classes.'`';

        return \sprintf(
            '| `%s` | `%s` | %s | %s | %s | %s |',
            $slot,
            ThemeSlots::semantic($slot),
            $cell(ThemeSlots::defaults($slot, 'bootstrap5')),
            $cell(ThemeSlots::defaults($slot, 'bootstrap4')),
            $cell(ThemeSlots::defaults($slot, 'tailwind')),
            $cell(ThemeSlots::defaults($slot, 'foundation')),
        );
    }

    public function testEverySlotAndPartialIsDocumented(): void
    {
        $doc = (string) file_get_contents(__DIR__.'/../../../docs/THEME_SLOTS.md');
        $table = substr($doc, (int) strpos($doc, '## Slots'), (int) strpos($doc, '## Attributes') - (int) strpos($doc, '## Slots'));
        $rows = array_values(array_filter(explode("\n", $table), static fn (string $l): bool => str_starts_with($l, '| `')));

        self::assertSame(array_map(self::row(...), ThemeSlots::names()), $rows, 'Regenerate the slot table of docs/THEME_SLOTS.md from ThemeSlots::SLOTS.');
        foreach (ThemeSlots::partialNames() as $partial) {
            self::assertStringContainsString('`'.$partial.'`', substr($doc, (int) strpos($doc, '## Partials')), $partial);
        }
    }
}
