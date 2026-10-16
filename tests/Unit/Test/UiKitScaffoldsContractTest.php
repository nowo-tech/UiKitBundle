<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Test;

use Nowo\UiKitBundle\Test\PageContractTestCase;

/**
 * Runs the shared contract test against UiKit's own scaffolds (every scaffold is its own page) with the default
 * hooks, for every css_framework: proves the allow-lists cover what UiKit emits.
 */
final class UiKitScaffoldsContractTest extends PageContractTestCase
{
    public static function pageFixtures(): iterable
    {
        $templates = [];
        foreach (self::SCAFFOLDS as $scaffold) {
            $templates[$scaffold] = '@NowoUiKit/page/'.$scaffold.'.html.twig';
        }

        return ScaffoldFixtures::all($templates);
    }

    protected static function cssFrameworks(): array
    {
        return ['none', 'custom', 'bootstrap', 'bootstrap5', 'bootstrap4', 'tabler', 'tailwind', 'foundation'];
    }
}
