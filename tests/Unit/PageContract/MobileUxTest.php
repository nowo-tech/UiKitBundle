<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use InvalidArgumentException;
use Nowo\UiKitBundle\Theme\ThemeSlots;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\Table;
use PHPUnit\Framework\TestCase;

/**
 * 2.41 phone mode (Beacon mobile UX audit 2026-11-09): MUX-40 select geometry, MUX-01 edge tabs launcher, MUX-20 / 22
 * phone cards (Column::phone, responsive_table phone_layout / overflow), MUX-04 touch token, MUX-161 font-size floor,
 * MUX-150 guest header. Every markup change is opt-in: without the new options the 2.40 markup.
 */
final class MobileUxTest extends TestCase
{
    public function testColumnPhoneIsValidated(): void
    {
        self::assertNull((new Column('name'))->phone);
        self::assertSame('primary', (new Column('name', phone: 'primary'))->phone);
        self::assertSame(['primary', 'meta', 'hide'], Column::PHONE);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Column phone "title" (primary|meta|hide).');
        new Column('name', phone: 'title');
    }

    public function testListPageCellsCarryThePhonePlaceOnlyWhenSet(): void
    {
        $twig = PageContractTwig::create();
        $plain = $twig->render('@NowoUiKit/page/list.html.twig', ['page' => new ListPage(new PageHeader('Users'), [new Column('name'), new Column('email')], [['name' => 'Ada', 'email' => 'a@x']])]);
        self::assertStringNotContainsString('data-nowo-ui-phone', $plain);

        $page = new ListPage(new PageHeader('Users'), [new Column('name', phone: 'primary'), new Column('email', phone: 'meta'), new Column('id', phone: 'hide'), new Column('role')], [['name' => 'Ada', 'email' => 'a@x', 'id' => 7, 'role' => 'admin']]);
        $html = $twig->render('@NowoUiKit/page/list.html.twig', ['page' => $page]);
        self::assertMatchesRegularExpression('#<th scope="col" class="[^"]*"[^>]* data-nowo-ui-phone="primary"#', $html);
        self::assertMatchesRegularExpression('#<td class="[^"]*" data-label="name" data-nowo-ui-phone="primary">Ada</td>#', $html);
        self::assertMatchesRegularExpression('#<td class="[^"]*" data-label="email" data-nowo-ui-phone="meta">a@x</td>#', $html);
        self::assertMatchesRegularExpression('#<td class="[^"]*" data-label="id" data-nowo-ui-phone="hide">7</td>#', $html);
        self::assertMatchesRegularExpression('#<td class="[^"]*" data-label="role">admin</td>#', $html);
    }

    public function testTablePartialCellsCarryThePhonePlace(): void
    {
        $table = new Table([new Column('name', phone: 'primary'), new Column('role')], [['name' => 'Ada', 'role' => 'admin']]);
        $html = PageContractTwig::create(['t' => "{{ include('@NowoUiKit/partials/_table.html.twig', {table: table}) }}"])->render('t', ['table' => $table]);

        self::assertMatchesRegularExpression('#<th scope="col" class="[^"]*"[^>]* data-nowo-ui-phone="primary"#', $html);
        self::assertStringContainsString('data-label="name" data-nowo-ui-phone="primary">Ada</td>', $html);
        self::assertStringContainsString('data-label="role">admin</td>', $html);
    }

    public function testResponsiveTablePhoneLayoutAndOverflowAreOptIn(): void
    {
        $tpl = "{% embed '@NowoUiKit/partials/_responsive_table.html.twig' with opts only %}{% block table %}<table data-responsive-table-target=\"table\"></table>{% endblock %}{% endembed %}";
        $twig = PageContractTwig::create(['t' => $tpl]);

        $plain = $twig->render('t', ['opts' => ['dialog_id' => 'd']]);
        self::assertStringNotContainsString('phone-layout', $plain);
        self::assertStringNotContainsString('data-rt-overflow-mode', $plain);

        $html = $twig->render('t', ['opts' => ['dialog_id' => 'd', 'phone_layout' => 'cards', 'overflow' => 'break']]);
        self::assertStringContainsString(' data-responsive-table-phone-layout-value="cards"', $html);
        self::assertStringContainsString(' data-rt-overflow-mode="break"', $html);

        // Unknown values print nothing.
        $other = $twig->render('t', ['opts' => ['dialog_id' => 'd', 'phone_layout' => 'list" onclick="x', 'overflow' => 'clip']]);
        self::assertStringNotContainsString('phone-layout', $other);
        self::assertStringNotContainsString('overflow-mode', $other);
        self::assertStringNotContainsString('onclick', $other);
    }

    public function testEdgeTabsLauncherMarkup(): void
    {
        $twig = PageContractTwig::create(['t' => "{{ include('@NowoUiKit/partials/_edge_tabs_launcher.html.twig', opts) }}"], [], 'none');

        $html = $twig->render('t', ['opts' => []]);
        self::assertStringContainsString('<button type="button" class="nowo-ui-edge-tabs-launcher" data-nowo-ui-edge-tabs-launcher data-nowo-ui-edge-tabs-when="phone" aria-expanded="false" aria-label="Page tools" title="Page tools">', $html);
        self::assertStringNotContainsString('data-nowo-ui-edge-tabs-gutter', $html);
        self::assertStringNotContainsString('style=', $html);

        $html = $twig->render('t', ['opts' => ['when' => 'always', 'gutter' => true, 'label' => 'Tools', 'class' => 'mine', 'testid' => 'tools', 'attr' => ['data-a' => '1', 'onclick' => 'x', 'data-off' => false]]]);
        self::assertStringContainsString('class="nowo-ui-edge-tabs-launcher mine"', $html);
        self::assertStringContainsString('data-nowo-ui-edge-tabs-when="always" data-nowo-ui-edge-tabs-gutter aria-expanded="false" aria-label="Tools" title="Tools" data-testid="tools" data-a="1">', $html);
        self::assertStringNotContainsString('onclick', $html);
        self::assertStringNotContainsString('data-off', $html);

        self::assertStringContainsString('data-nowo-ui-edge-tabs-when="never"', $twig->render('t', ['opts' => ['when' => '" onclick="x']]));
        self::assertSame('@NowoUiKitBundle/partials/_edge_tabs_launcher.html.twig', ThemeSlots::PARTIALS['edge_tabs_launcher']);
        self::assertNotContains('edge-tabs', ThemeSlots::SCRIPTS, 'opt-in runtime');
        self::assertFileExists(\dirname(__DIR__, 3).'/src/Resources/public/js/nowo-ui-edge-tabs.js');
    }

    public function testGuestLayoutPrintsNoHeaderByDefault(): void
    {
        $child = '{% extends "@NowoUiKit/layout/guest.html.twig" %}{% block nowo_ui_content %}<p>CONTENT</p>{% endblock %}';
        $html = PageContractTwig::create(['child' => $child])->render('child');

        self::assertStringNotContainsString('nowo-ui-guest-header', $html);
        self::assertMatchesRegularExpression('#nowo-ui-page--guest">\s*<main class="nowo-ui-content#', $html);
    }

    public function testGuestLayoutHeaderLinksHomeAndSignIn(): void
    {
        $child = '{% extends "@NowoUiKit/layout/guest.html.twig" %}{% block nowo_ui_content %}<p>CONTENT</p>{% endblock %}';
        $twig = PageContractTwig::create(['child' => $child]);

        $html = $twig->render('child', ['nowo_ui_guest_header' => ['home_href' => '/', 'sign_in_href' => '/login', 'brand' => 'Beacon', 'testid' => 'gh']]);
        self::assertStringContainsString('<header class="nowo-ui-guest-header" data-nowo-ui-region="guest-header" data-testid="gh">', $html);
        self::assertStringContainsString('<a class="nowo-ui-guest-header__brand" href="/" aria-label="Beacon — Home">Beacon</a>', $html);
        self::assertMatchesRegularExpression('#<nav class="nowo-ui-guest-header__nav" aria-label="Beacon"><a class="nowo-ui-btn nowo-ui-btn--secondary[^"]*nowo-ui-guest-header__sign-in" href="/login">Sign in</a></nav>#', $html);
        self::assertMatchesRegularExpression('#</header>\s*<main class="nowo-ui-content#', $html);

        // Unsafe schemes print no link; show_sign_in false hides the sign-in link; labels override.
        $html = $twig->render('child', ['nowo_ui_guest_header' => ['home_href' => 'javascript:alert(1)', 'sign_in_href' => '/login', 'show_sign_in' => false, 'labels' => ['home' => 'Inicio']]]);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringContainsString('<span class="nowo-ui-guest-header__brand">Inicio</span>', $html);
        self::assertStringNotContainsString('/login', $html);

        self::assertSame('@NowoUiKitBundle/partials/_guest_header.html.twig', ThemeSlots::PARTIALS['guest_header']);
    }

    public function testCssSelectGeometry(): void
    {
        foreach (['nowo-ui.unlayered.css', 'nowo-ui.css'] as $file) {
            $css = $this->css($file);
            self::assertMatchesRegularExpression('/:is\(\.nowo-ui-select, select\.nowo-ui-input:not\(\.form-select\), select\.nowo-ui-pagination__select\):not\(\[multiple\], \[size\]\) \{[^}]*-webkit-appearance: none;[^}]*appearance: none;[^}]*line-height: 1\.25;[^}]*padding-inline-end: var\(--nowo-ui-select-chevron-gutter, 2rem\);[^}]*currentColor/s', $css, $file);
            self::assertStringContainsString('@supports (-webkit-touch-callout: none) {', $css);
            self::assertMatchesRegularExpression('/:is\(\.nowo-ui-select, \.nowo-ui-input, select\.nowo-ui-pagination__select\) \{\s*font-size: max\(1rem, var\(--nowo-ui-input-font-size, 1rem\)\);/', $css, $file);
        }
    }

    public function testCssTouchTokenAndFontFloorOnlyOnTouchOrNarrowScreens(): void
    {
        $css = $this->css('nowo-ui.unlayered.css');
        self::assertMatchesRegularExpression('/@media \(pointer: coarse\) \{\s*:root \{\s*--nowo-ui-control-size-touch: 2\.75rem;\s*\}\s*:is\(\.nowo-ui-btn--icon, \.nowo-ui-tabs__item, \.nowo-ui-pagination__link, \.nowo-ui-sort, [^)]*\) \{\s*min-height: var\(--nowo-ui-control-size-touch\);/', $css);
        self::assertMatchesRegularExpression('/@media \(max-width: 40rem\), \(pointer: coarse\) \{\s*\.nowo-ui-badge \{\s*font-size: max\(var\(--nowo-ui-font-size-min, 0\.75rem\), var\(--nowo-ui-badge-font-size, 0\.75rem\)\);/', $css);
        // The desktop badge rule is unchanged.
        self::assertMatchesRegularExpression('/\n\.nowo-ui-badge \{[^}]*font-size: var\(--nowo-ui-badge-font-size, 0\.75rem\);/', $css);
    }

    /** 2.41.1 (JC-I12): a launcher with `hidden` does not fold the tabs (they would be unreachable). */
    public function testCssEdgeTabsFoldNeedsTheRuntimeAndTheLauncher(): void
    {
        $css = $this->css('nowo-ui.unlayered.css');
        self::assertMatchesRegularExpression('/\n\.nowo-ui-edge-tabs-launcher \{[^}]*position: fixed;[^}]*display: none;/', $css);
        foreach (['phone', 'always'] as $when) {
            $ctx = ":root[data-nowo-ui-edge-tabs-ready]:not([data-nowo-ui-edge-tabs-open]):has(.nowo-ui-edge-tabs-launcher[data-nowo-ui-edge-tabs-when='".$when."']:not([hidden])) .nowo-ui-edge-tab__link:not([aria-expanded='true'], [data-nowo-ui-edge-tab-pinned], [data-nowo-ui-edge-tabs-folding]) {";
            self::assertStringContainsString($ctx, $css, $when);
        }
        self::assertMatchesRegularExpression("/@media \\(max-width: 40rem\\), \\(pointer: coarse\\) and \\(max-width: 64rem\\) \\{\\s*:root\\[data-nowo-ui-edge-tabs-ready\\]:has\\(\\.nowo-ui-edge-tabs-launcher\\[data-nowo-ui-edge-tabs-when='phone'\\]:not\\(\\[hidden\\]\\)\\) \\.nowo-ui-edge-tabs-launcher \\{\\s*display: inline-flex;/", $css);
        self::assertStringContainsString("[data-nowo-ui-edge-tabs-when='phone']:not([hidden])[data-nowo-ui-edge-tabs-gutter]) .nowo-ui-main {", $css);
    }

    /**
     * 2.42 (MUX-01): a tab whose panel is open (aria-expanded="true") or pinned never folds, and folded it keeps its own
     * `top` (only the open launcher stacks the tabs under it); no other rule hides an edge tab link.
     */
    public function testCssExpandedOrPinnedEdgeTabNeverFolds(): void
    {
        $css = $this->css('nowo-ui.unlayered.css');
        preg_match_all('/^\s*([^{}\n]*\.nowo-ui-edge-tab__link[^{}\n]*) \{([^}]*)\}/m', $css, $rules, \PREG_SET_ORDER);
        $folding = 0;
        foreach ($rules as [, $selector, $body]) {
            if (str_contains($body, 'display: none') && !str_starts_with(trim($selector), '.nowo-ui-edge-tab__link')) {
                self::assertStringEndsWith(".nowo-ui-edge-tab__link:not([aria-expanded='true'], [data-nowo-ui-edge-tab-pinned], [data-nowo-ui-edge-tabs-folding])", trim($selector));
                ++$folding;
            }
            if (str_contains($body, 'top:') && str_contains($selector, ':root')) {
                self::assertStringContainsString('[data-nowo-ui-edge-tabs-open]', $selector, 'only the open launcher moves the tabs');
            }
        }
        self::assertSame(2, $folding, 'phone and always');
        self::assertStringContainsString("@layer nowo-ui {\n", $this->css('nowo-ui.css'));
        self::assertStringContainsString(":not([aria-expanded='true'], [data-nowo-ui-edge-tab-pinned], [data-nowo-ui-edge-tabs-folding]) {\n    display: none !important;", $this->css('nowo-ui.css'));
    }

    public function testCssPhoneCards(): void
    {
        $css = $this->css('nowo-ui.unlayered.css');
        self::assertStringContainsString(".nowo-ui-responsive-table[data-rt-cards] tbody > tr > td[data-rt-label]::before {\n  content: attr(data-rt-label);", $css);
        self::assertStringContainsString(".nowo-ui-responsive-table[data-rt-cards] tbody > tr > td[data-nowo-ui-phone='primary'] {\n  order: -1;", $css);
        self::assertStringContainsString(".nowo-ui-responsive-table[data-rt-cards] tbody > tr > :is(td[data-nowo-ui-phone='hide'], [data-responsive-detail]) {\n  display: none;", $css);
        self::assertStringContainsString(".nowo-ui-table--cards > tbody > tr:has(> td[data-nowo-ui-phone]) {\n    display: flex;", $css);
        self::assertStringContainsString(".nowo-ui-table--cards > tbody > tr > td[data-nowo-ui-phone='hide'] {\n    display: none !important;", $css);
    }

    private function css(string $file): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 3).'/src/Resources/public/css/'.$file);
    }
}
