<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use PHPUnit\Framework\TestCase;

/**
 * 2.55.1: Twig's `default` filter treats an empty array as unset, so a legitimate empty list (`script_names: []`,
 * `static_icons: []`) was replaced by the default. The partials test `is defined and is not null`.
 */
final class EmptyListParameterTest extends TestCase
{
    private const SCRIPTS = "{{ include(nowo_ui_partial('scripts'), opts) }}";

    private const RUNTIME = '/bundles/nowouikit/js/nowo-ui-';

    public function testEmptyScriptNamesPrintsNoDefaultRuntime(): void
    {
        $html = PageContractTwig::create(['t' => self::SCRIPTS])->render('t', ['opts' => ['script_names' => []]]);

        self::assertSame(0, substr_count($html, '<script'), 'no default runtime is printed');
        self::assertStringNotContainsString(self::RUNTIME.'modal.js', $html);
    }

    public function testThemeAppendedRuntimesStayAloneAndOnceWithAnEmptyList(): void
    {
        $html = PageContractTwig::create(['t' => self::SCRIPTS], ['context_menus' => true])->render('t', ['opts' => ['script_names' => []]]);

        self::assertSame(1, substr_count($html, '<script'));
        self::assertSame(1, substr_count($html, self::RUNTIME.'context-menu.js'));
        self::assertStringNotContainsString(self::RUNTIME.'modal.js', $html);
    }

    public function testUndefinedAndNullScriptNamesPrintTheDefaults(): void
    {
        $twig = PageContractTwig::create(['t' => self::SCRIPTS, 'n' => "{{ include(nowo_ui_partial('scripts'), {script_names: null}) }}"]);

        foreach (['undefined' => $twig->render('t', ['opts' => []]), 'null' => $twig->render('n')] as $case => $html) {
            self::assertSame(1, substr_count($html, self::RUNTIME.'modal.js'), $case);
            self::assertSame(1, substr_count($html, self::RUNTIME.'theme.js'), $case);
        }
    }

    public function testAnExplicitScriptListIsPrintedAsGiven(): void
    {
        $html = PageContractTwig::create(['t' => self::SCRIPTS])->render('t', ['opts' => ['script_names' => ['modal']]]);

        self::assertSame(1, substr_count($html, '<script'));
        self::assertSame(1, substr_count($html, self::RUNTIME.'modal.js'));
    }

    public function testEmptyStaticIconsPrintNoStaticBurgerIcon(): void
    {
        $tpl = "{{ include(nowo_ui_partial('app_shell_open'), opts) }}";
        $base = ['burger_icon' => 'morph'];
        $twig = PageContractTwig::create(['t' => $tpl]);

        $none = $twig->render('t', ['opts' => $base + ['burger_static_icons' => []]]);
        self::assertStringNotContainsString('nowo-ui-burger__icon--open', $none);
        self::assertStringNotContainsString('nowo-ui-burger__icon--close', $none);
        self::assertStringContainsString('data-sidebar-morph', $none);

        foreach (['undefined' => $base, 'null' => $base + ['burger_static_icons' => null]] as $case => $opts) {
            $html = $twig->render('t', ['opts' => $opts]);
            self::assertStringContainsString('nowo-ui-burger__icon--open', $html, $case);
            self::assertStringContainsString('nowo-ui-burger__icon--close', $html, $case);
        }

        $direct = PageContractTwig::create(['b' => "{% include '@NowoUiKitBundle/shell/_burger.html.twig' with {controls: 'aside', icon: 'morph', static_icons: []} only %}"]);
        self::assertStringNotContainsString('nowo-ui-burger__icon--open', $direct->render('b'));
    }
}
