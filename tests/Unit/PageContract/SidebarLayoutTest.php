<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Markup;

/**
 * 2.32 full-height sidebar layout of the app shell: root hooks, the aside head (second burger, same partial and
 * options; the aside brand from aside_brand_html, the brand parts, the shell brand config, brand_html or brand), the
 * per-request override, the `app_shell` block, host slot classes with kit_styles off, and the hover expand attribute.
 */
final class SidebarLayoutTest extends TestCase
{
    private const HALVES = <<<'TWIG'
{{ include(nowo_ui_partial('app_shell_open'), opts) }}<main id="main-content" tabindex="-1">BODY</main>{{ include(nowo_ui_partial('app_shell_close'), {}) }}
TWIG;

    private const SHELL = ['kit_styles' => true, 'sidebar_cookie' => null, 'sidebar_hidden_value' => 'collapsed', 'sidebar_rail_value' => 'rail', 'sidebar_storage' => null];

    /**
     * @param array<string, mixed> $shell
     */
    private function twig(string $sidebarLayout = 'below-header', ?Request $request = null, array $shell = self::SHELL, array $theme = []): Environment
    {
        return PageContractTwig::create(['t' => self::HALVES, 'embed' => <<<'TWIG'
{% embed nowo_ui_partial('app_shell') with {sidebar_layout: 'full-height', brand: 'Acme'} %}
    {% block nowo_ui_shell_aside_brand %}<a class="host-brand" href="/"><img src="/logo.svg" alt=""><span class="nowo-ui-aside-brand__name">Acme</span></a>{% endblock %}
    {% block nowo_ui_shell_main %}<main id="main-content">BODY</main>{% endblock %}
{% endembed %}
TWIG], $theme, request: $request, shell: $shell, sidebarLayout: $sidebarLayout);
    }

    /**
     * @param array<string, mixed> $opts
     */
    private function render(array $opts, string $sidebarLayout = 'below-header', ?Request $request = null, array $shell = self::SHELL): string
    {
        return $this->twig($sidebarLayout, $request, $shell)->render('t', ['opts' => $opts]);
    }

    public function testFullHeightFromConfigWithBrandParts(): void
    {
        $html = $this->render([
            'brand_html' => '<a class="nowo-ui-header__brand" href="/">Acme Billing</a>',
            'brand_icon_html' => '<svg viewBox="0 0 24 24"></svg>',
            'brand_name' => 'Acme Billing',
            'brand_href' => '/home',
            'aside_items' => [['label' => 'Invoices', 'href' => '/invoices', 'current' => true]],
            'rail_mode' => true,
        ], 'full-height');

        self::assertStringContainsString('<div class="nowo-ui-app-shell nowo-ui-shell is-content-width is-sidebar-full-height" data-nowo-ui-shell data-nowo-ui-sidebar-layout="full-height" data-nowo-ui-sidebar-rail data-nowo-ui-sidebar-mode="expanded">', $html);
        self::assertSame(2, substr_count($html, 'data-nowo-ui-burger '), 'header burger + aside head burger');
        self::assertSame(2, substr_count($html, 'aria-controls="nowo-ui-aside" aria-expanded="true"'));
        self::assertMatchesRegularExpression('#<aside id="nowo-ui-aside" class="nowo-ui-app-shell__aside nowo-ui-aside" data-nowo-ui-aside aria-label="Navigation">\s*<div class="nowo-ui-app-shell__aside-head nowo-ui-aside-head" data-nowo-ui-aside-head>\s*<button type="button" class="nowo-ui-app-shell__burger nowo-ui-burger#', $html);
        self::assertStringContainsString('<a class="nowo-ui-app-shell__aside-brand nowo-ui-aside-brand" href="/home" data-nowo-ui-aside-brand><span class="nowo-ui-app-shell__brand-icon nowo-ui-aside-brand__icon" aria-hidden="true"><svg viewBox="0 0 24 24"></svg></span><span class="nowo-ui-app-shell__brand-name nowo-ui-aside-brand__name">Acme Billing</span></a>', $html);
        // The header keeps its own brand (phones, hidden aside).
        self::assertStringContainsString('<a class="nowo-ui-header__brand" href="/">Acme Billing</a>', $html);
        self::assertStringNotContainsString('data-nowo-ui-sidebar-hover-expand', $html);
    }

    public function testRequestAttributeAndOptionOverrideTheConfig(): void
    {
        $request = new Request(attributes: ['_route' => 'home', '_nowo_ui_sidebar_layout' => 'full-height']);
        $html = $this->render(['brand' => 'Acme'], 'below-header', $request);
        self::assertStringContainsString('data-nowo-ui-sidebar-layout="full-height"', $html);
        // brand text: its initial is the icon, the name follows.
        self::assertStringContainsString('<strong class="nowo-ui-app-shell__aside-brand nowo-ui-aside-brand" data-nowo-ui-aside-brand><span class="nowo-ui-app-shell__brand-icon nowo-ui-aside-brand__icon nowo-ui-aside-brand__icon--initial" aria-hidden="true">A</span><span class="nowo-ui-app-shell__brand-name nowo-ui-aside-brand__name">Acme</span></strong>', $html);
        self::assertStringContainsString('<strong class="nowo-ui-app-shell__brand nowo-ui-header__brand">Acme</strong>', $html);

        $below = $this->render(['brand' => 'Acme', 'sidebar_layout' => 'below-header'], 'full-height', $request);
        self::assertStringNotContainsString('data-nowo-ui-sidebar-layout', $below);
        self::assertStringNotContainsString('data-nowo-ui-aside-head', $below);
        self::assertStringNotContainsString('is-sidebar-full-height', $below);

        $option = $this->render(['brand' => 'Acme', 'sidebar_layout' => 'full-height']);
        self::assertStringContainsString('data-nowo-ui-aside-head', $option);
    }

    public function testAsideBrandHtmlThenBrandHtmlFallbacks(): void
    {
        $html = $this->render(['sidebar_layout' => 'full-height', 'brand_html' => '<a href="/">Header brand</a>', 'aside_brand_html' => '<a href="/">Aside <span class="nowo-ui-aside-brand__name">brand</span></a>']);
        self::assertStringContainsString('<div class="nowo-ui-app-shell__aside-brand nowo-ui-aside-brand" data-nowo-ui-aside-brand><a href="/">Aside <span class="nowo-ui-aside-brand__name">brand</span></a></div>', $html);

        $fallback = $this->render(['sidebar_layout' => 'full-height', 'brand_html' => '<a href="/">Header brand</a>']);
        self::assertStringContainsString('<div class="nowo-ui-app-shell__aside-brand nowo-ui-aside-brand" data-nowo-ui-aside-brand><a href="/">Header brand</a></div>', $fallback);

        $none = $this->render(['sidebar_layout' => 'full-height']);
        self::assertStringContainsString('data-nowo-ui-aside-head', $none);
        self::assertStringNotContainsString('data-nowo-ui-aside-brand', $none);
    }

    public function testShellBrandConfigFillsTheAsideAndTheHeader(): void
    {
        $shell = self::SHELL + ['brand' => ['name' => 'Acme', 'href' => '/dashboard', 'icon_src' => '/images/logo.svg']];
        $html = $this->render(['sidebar_layout' => 'full-height'], 'below-header', null, $shell);

        self::assertStringContainsString('<a class="nowo-ui-app-shell__aside-brand nowo-ui-aside-brand" href="/dashboard" data-nowo-ui-aside-brand><span class="nowo-ui-app-shell__brand-icon nowo-ui-aside-brand__icon" aria-hidden="true"><img src="/images/logo.svg" alt="" width="28" height="28"></span><span class="nowo-ui-app-shell__brand-name nowo-ui-aside-brand__name">Acme</span></a>', $html);
        self::assertStringContainsString('<a class="nowo-ui-app-shell__brand nowo-ui-header__brand nowo-ui-aside-brand" href="/dashboard"><span class="nowo-ui-app-shell__brand-icon nowo-ui-aside-brand__icon" aria-hidden="true"><img src="/images/logo.svg" alt="" width="28" height="28"></span><span class="nowo-ui-app-shell__brand-name nowo-ui-aside-brand__name">Acme</span></a>', $html);

        // Options win over the config; unsafe URLs are dropped.
        $options = $this->render(['sidebar_layout' => 'full-height', 'brand_name' => 'Billing', 'brand_href' => 'javascript:alert(1)', 'brand_icon_src' => 'javascript:x'], 'below-header', null, $shell);
        self::assertStringContainsString('<strong class="nowo-ui-app-shell__aside-brand nowo-ui-aside-brand" data-nowo-ui-aside-brand><span class="nowo-ui-app-shell__brand-icon nowo-ui-aside-brand__icon nowo-ui-aside-brand__icon--initial" aria-hidden="true">B</span><span class="nowo-ui-app-shell__brand-name nowo-ui-aside-brand__name">Billing</span></strong>', $options);
        self::assertStringNotContainsString('javascript:', $options);

        // Below-header: the config brand only fills an otherwise empty header brand.
        $below = $this->render([], 'below-header', null, $shell);
        self::assertStringContainsString('<a class="nowo-ui-app-shell__brand nowo-ui-header__brand nowo-ui-aside-brand" href="/dashboard">', $below);
        self::assertStringNotContainsString('data-nowo-ui-aside-head', $below);
    }

    /**
     * GP-I2: the text and URL brand inputs are escaped — a name is text even when it arrives as Markup, and a quote
     * cannot break out of href / src. GP-I1 at render: an image source keeps no mailto:.
     */
    public function testBrandInputsAreEscaped(): void
    {
        $html = self::render([
            'sidebar_layout' => 'full-height',
            'brand_name' => '<script>alert(1)</script>"Acme"',
            'brand_href' => '/x" onmouseover="alert(1)',
            'brand_icon_src' => '/logo.svg" onerror="alert(1)',
        ]);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('<span class="nowo-ui-app-shell__brand-name nowo-ui-aside-brand__name">&lt;script&gt;alert(1)&lt;/script&gt;&quot;Acme&quot;</span>', $html);
        self::assertStringContainsString('href="/x&quot; onmouseover=&quot;alert(1)"', $html);
        self::assertStringContainsString('<img src="/logo.svg&quot; onerror=&quot;alert(1)" alt=""', $html);
        self::assertStringNotContainsString('" onmouseover="', $html);
        self::assertStringNotContainsString('" onerror="', $html);

        $markup = self::twig()->render('t', ['opts' => ['sidebar_layout' => 'full-height', 'brand_name' => new Markup('<b onclick="x()">Acme</b>', 'UTF-8')]]);
        self::assertStringContainsString('&lt;b onclick=&quot;x()&quot;&gt;Acme&lt;/b&gt;', $markup);
        self::assertStringNotContainsString('<b onclick', $markup);
        self::assertStringContainsString('nowo-ui-aside-brand__icon--initial" aria-hidden="true">&lt;</span>', $markup, 'the initial is escaped too');

        $config = self::render(['sidebar_layout' => 'full-height'], 'below-header', null, self::SHELL + ['brand' => ['name' => '<i>Acme</i>', 'href' => '/" x="', 'icon_src' => null]]);
        self::assertStringContainsString('&lt;i&gt;Acme&lt;/i&gt;', $config);
        self::assertStringContainsString('href="/&quot; x=&quot;"', $config);

        $mailto = self::render(['sidebar_layout' => 'full-height', 'brand_name' => 'Acme', 'brand_icon_src' => 'mailto:a@b.c', 'brand_href' => 'mailto:a@b.c']);
        self::assertStringNotContainsString('<img', $mailto, 'an image source is relative, http or https');
        self::assertStringContainsString('href="mailto:a@b.c"', $mailto, 'a mailto link is fine');
        self::assertStringNotContainsString('src="data:', self::render(['sidebar_layout' => 'full-height', 'brand_name' => 'Acme', 'brand_icon_src' => 'data:image/svg+xml,<svg onload=alert(1)>']));
    }

    public function testCollapsedRailAndHoverExpand(): void
    {
        $collapsed = $this->render(['sidebar_layout' => 'full-height', 'brand' => 'Acme', 'collapsed' => true]);
        self::assertStringContainsString('is-sidebar-collapsed is-aside-collapsed is-sidebar-full-height" data-nowo-ui-shell data-nowo-ui-sidebar-layout="full-height">', $collapsed);
        self::assertSame(2, substr_count($collapsed, 'aria-expanded="false"'));

        $rail = $this->render(['sidebar_layout' => 'full-height', 'brand' => 'Acme', 'rail_mode' => true, 'rail' => true, 'hover_expand' => true]);
        self::assertStringContainsString('is-sidebar-rail is-aside-rail is-sidebar-full-height" data-nowo-ui-shell data-nowo-ui-sidebar-layout="full-height" data-nowo-ui-sidebar-rail data-nowo-ui-sidebar-mode="rail" data-nowo-ui-sidebar-hover-expand>', $rail);
        self::assertSame(2, substr_count($rail, 'data-label-rail="Show icons only"'));

        // From the config; not without rail_mode; never with kit_styles off.
        $config = $this->render(['rail_mode' => true], 'below-header', null, self::SHELL + ['hover_expand' => true]);
        self::assertStringContainsString('data-nowo-ui-sidebar-mode="expanded" data-nowo-ui-sidebar-hover-expand>', $config);
        self::assertStringNotContainsString('hover-expand', $this->render(['hover_expand' => true]));
        self::assertStringNotContainsString('hover-expand', $this->render(['rail_mode' => true, 'hover_expand' => true, 'kit_styles' => false]));
    }

    public function testHostSlotClassesWithKitStylesOff(): void
    {
        $theme = ['classes' => ['app_aside_head' => 'app-sidebar__head', 'app_aside_brand' => 'app-sidebar__brand', 'app_burger' => 'app-sidebar-toggle'], 'attributes' => ['app_aside_head' => ['data-testid' => 'sidebar-head']]];
        $html = PageContractTwig::create(['t' => self::HALVES], $theme, request: new Request(attributes: ['_route' => 'home']), shell: ['kit_styles' => false] + self::SHELL, sidebarLayout: 'full-height')
            ->render('t', ['opts' => ['aside_id' => 'app-sidebar', 'aside_brand_html' => '<a href="/">Beacon</a>', 'aside_html' => '<div class="app-sidebar__inner">NAV</div>', 'burger_label_attr_names' => ['hide' => 'data-label-collapse']]]);

        self::assertStringContainsString('<div class="nowo-ui-app-shell is-content-width is-sidebar-full-height" data-nowo-ui-shell data-nowo-ui-sidebar-layout="full-height">', $html);
        self::assertMatchesRegularExpression('#<div class="nowo-ui-app-shell__aside-head app-sidebar__head" data-nowo-ui-aside-head data-testid="sidebar-head">\s*<button type="button" class="nowo-ui-app-shell__burger app-sidebar-toggle" data-nowo-ui-burger aria-controls="app-sidebar"#', $html);
        self::assertStringContainsString('<div class="nowo-ui-app-shell__aside-brand app-sidebar__brand" data-nowo-ui-aside-brand><a href="/">Beacon</a></div>', $html);
        self::assertMatchesRegularExpression('#</div>\s*<div class="app-sidebar__inner">NAV</div>\s*</aside>#', $html);
        self::assertStringNotContainsString(' nowo-ui-aside-head', $html);
        self::assertSame(2, substr_count($html, 'data-label-collapse="Hide navigation"'));
    }

    public function testShellBlock(): void
    {
        $html = $this->twig()->render('embed');
        self::assertStringContainsString('data-nowo-ui-sidebar-layout="full-height"', $html);
        self::assertStringContainsString('<div class="nowo-ui-app-shell__aside-brand nowo-ui-aside-brand" data-nowo-ui-aside-brand><a class="host-brand" href="/"><img src="/logo.svg" alt=""><span class="nowo-ui-aside-brand__name">Acme</span></a></div>', $html);
        self::assertStringContainsString('<strong class="nowo-ui-app-shell__brand nowo-ui-header__brand">Acme</strong>', $html);
        self::assertSame(0, preg_match('/\son[a-z]+=/i', $html));
    }
}
