<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit;

use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Theme\ThemeSlots;
use Nowo\UiKitBundle\Twig\ThemeExtension;
use Nowo\UiKitBundle\View\Label;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\IdentityTranslator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

/**
 * 2.25: the `side_panel`, `side_panel_toggle` and `side_panel_container` partials (markup contract for hosts).
 */
final class SidePanelPartialTest extends TestCase
{
    public function testAClosedPanelWithACloseLinkAndAContentTemplate(): void
    {
        $html = $this->render("{% include '@NowoUiKitBundle/partials/_side_panel.html.twig' with {id: 'comments', title: 'Comments', close_href: '?comments=0', content_template: 'body.twig', content_context: {n: 3}, attr: {id: 'ignored', 'data-testid': 'panel'}} only %}");

        self::assertStringContainsString('<aside id="comments" class="nowo-ui-side-panel nowo-ui-side-panel--end nowo-ui-side-panel--inline" data-nowo-ui-side-panel data-side="end" data-layout="inline" aria-labelledby="comments-title" data-nowo-ui-side-panel-opened="side_panel.opened" data-nowo-ui-side-panel-closed="side_panel.closed" hidden data-testid="panel">', $html);
        self::assertStringContainsString('<h2 id="comments-title" class="nowo-ui-side-panel__title" tabindex="-1">Comments</h2>', $html);
        self::assertStringContainsString('<a href="?comments=0" role="button" class="nowo-ui-btn nowo-ui-btn--icon nowo-ui-side-panel__close" data-nowo-ui-side-panel-close aria-label="side_panel.close">', $html);
        self::assertStringContainsString('<div class="nowo-ui-side-panel__body" data-nowo-ui-side-panel-body><p>3 threads</p></div>', $html);
        self::assertStringContainsString('<div class="nowo-ui-side-panel__backdrop" data-nowo-ui-side-panel-backdrop data-for="comments" hidden></div>', $html);
        self::assertStringContainsString('<p class="visually-hidden" role="status" aria-live="polite" data-nowo-ui-side-panel-status data-for="comments"></p>', $html);
        self::assertStringNotContainsString('ignored', $html);
        self::assertStringNotContainsString('style=', $html);
    }

    public function testAnOpenStartOverlayPanelWithAButtonLabelsAndAnEmbeddedBody(): void
    {
        $html = $this->render(<<<'TWIG'
            {% embed '@NowoUiKitBundle/partials/_side_panel.html.twig' with {id: 'nav', title: label, open: true, side: 'start', layout: 'overlay', heading_level: 9, labels: {close: 'Hide', opened: 'Shown', closed: 'Hidden'}} only %}
                {% block nowo_ui_side_panel_body %}<em>embedded</em>{% endblock %}
            {% endembed %}
            TWIG, ['label' => new Label('panel.title')]);

        self::assertStringContainsString('class="nowo-ui-side-panel nowo-ui-side-panel--start nowo-ui-side-panel--overlay" data-nowo-ui-side-panel data-side="start" data-layout="overlay" aria-labelledby="nav-title" data-nowo-ui-side-panel-opened="Shown" data-nowo-ui-side-panel-closed="Hidden">', $html);
        self::assertStringContainsString('<h6 id="nav-title" class="nowo-ui-side-panel__title" tabindex="-1">panel.title</h6>', $html);
        self::assertStringContainsString('<button type="button" class="nowo-ui-btn nowo-ui-btn--icon nowo-ui-side-panel__close" data-nowo-ui-side-panel-close aria-label="Hide">', $html);
        self::assertStringContainsString('<em>embedded</em>', $html);
        self::assertStringNotContainsString(' hidden>', explode('</aside>', $html)[0]);

        $odd = $this->render("{% include '@NowoUiKitBundle/partials/_side_panel.html.twig' with {id: 'x', title: 'X', side: 'left', layout: 'float', heading_level: 1} only %}");
        self::assertStringContainsString('nowo-ui-side-panel--end nowo-ui-side-panel--inline', $odd);
        self::assertStringContainsString('<h2 id="x-title"', $odd);
        self::assertStringContainsString('<div class="nowo-ui-side-panel__body" data-nowo-ui-side-panel-body></div>', $odd);

        $content = $this->render("{% include '@NowoUiKitBundle/partials/_side_panel.html.twig' with {id: 'x', title: 'X', content: '<p>safe</p>', content_template: 'body.twig'} only %}");
        self::assertStringContainsString('<div class="nowo-ui-side-panel__body" data-nowo-ui-side-panel-body><p>safe</p></div>', $content);
    }

    public function testTheToggleAsALinkWithAnIconACountAndAShortcut(): void
    {
        $html = $this->render("{% include '@NowoUiKitBundle/partials/_side_panel_toggle.html.twig' with {target: 'comments', label: 'Comments', href: '?comments=1', icon: 'tabler:message-circle', count: 3, count_suffix: 'open', shortcut: 'Alt+Shift+C', attr: {'data-testid': 't'}} only %}", [], 'tabler-icons');

        self::assertStringContainsString('<a href="?comments=1" role="button" class="nowo-ui-btn nowo-ui-btn--secondary nowo-ui-side-panel-toggle nowo-ui-side-panel-toggle--icon" aria-controls="comments" aria-expanded="false" data-nowo-ui-side-panel-toggle aria-keyshortcuts="Alt+Shift+C" data-nowo-ui-side-panel-shortcut="Alt+Shift+C" data-testid="t">', $html);
        self::assertStringContainsString('<span class="nowo-ui-side-panel-toggle__icon" aria-hidden="true">', $html);
        self::assertStringContainsString('<span class="visually-hidden">Comments</span>', $html);
        self::assertStringContainsString('<span class="nowo-ui-badge nowo-ui-badge--primary nowo-ui-side-panel-toggle__badge" data-nowo-ui-side-panel-badge>3</span>', $html);
        self::assertStringContainsString('<span class="visually-hidden" data-nowo-ui-side-panel-badge-suffix>open</span>', $html);
        self::assertStringEndsWith('</a>', $html);
    }

    public function testTheToggleAsAButtonWithoutCountOrUnsupportedIcon(): void
    {
        // A tabler icon with the bootstrap icon set cannot be drawn: the label stays visible.
        $html = $this->render("{% include '@NowoUiKitBundle/partials/_side_panel_toggle.html.twig' with {target: 'c', label: 'Comments', open: true, icon: 'tabler:message-circle', count: 0, count_suffix: 'open'} only %}");

        self::assertStringContainsString('<button type="button" class="nowo-ui-btn nowo-ui-btn--secondary nowo-ui-side-panel-toggle" aria-controls="c" aria-expanded="true" data-nowo-ui-side-panel-toggle>', $html);
        self::assertStringContainsString('<span class="nowo-ui-side-panel-toggle__label">Comments</span>', $html);
        self::assertStringContainsString('data-nowo-ui-side-panel-badge hidden></span>', $html);
        self::assertStringContainsString('data-nowo-ui-side-panel-badge-suffix hidden>open</span>', $html);
        self::assertStringNotContainsString('aria-keyshortcuts', $html);
        self::assertStringEndsWith('</button>', $html);

        $tabler = $this->render("{% include '@NowoUiKitBundle/partials/_side_panel_toggle.html.twig' with {target: 'c', label: 'Comments', icon: 'tabler:message-circle'} only %}", [], 'tabler-icons');
        self::assertStringContainsString('<i class="ti ti-message-circle" aria-hidden="true"></i>', $tabler);
        self::assertStringNotContainsString('badge-suffix', $tabler);

        $none = $this->render("{% include '@NowoUiKitBundle/partials/_side_panel_toggle.html.twig' with {target: 'c', label: 'Comments', icon: 'close'} only %}", [], 'none');
        self::assertStringContainsString('nowo-ui-side-panel-toggle__label', $none);
    }

    public function testTheContainerAndTheThemeRegistration(): void
    {
        $html = $this->render(<<<'TWIG'
            {% embed '@NowoUiKitBundle/partials/_side_panel_container.html.twig' with {attr: {'data-testid': 'layout'}} only %}
                {% block nowo_ui_side_panel_main %}<p>main</p>{% endblock %}
                {% block nowo_ui_side_panel_panel %}<aside>panel</aside>{% endblock %}
            {% endembed %}
            TWIG);

        self::assertStringContainsString('<div class="nowo-ui-side-panel-container" data-nowo-ui-side-panel-container data-testid="layout">', $html);
        self::assertStringContainsString('<div class="nowo-ui-side-panel-container__main" data-nowo-ui-side-panel-main><p>main</p></div><aside>panel</aside>', $html);

        self::assertSame('@NowoUiKitBundle/partials/_side_panel.html.twig', ThemeSlots::PARTIALS['side_panel']);
        self::assertSame('@NowoUiKitBundle/partials/_side_panel_toggle.html.twig', ThemeSlots::PARTIALS['side_panel_toggle']);
        self::assertSame('@NowoUiKitBundle/partials/_side_panel_container.html.twig', ThemeSlots::PARTIALS['side_panel_container']);
        self::assertSame('btn btn-outline-secondary', ThemeSlots::SLOTS['side_panel_toggle'][1]['bootstrap']);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context = [], string $iconSet = 'bootstrap-icons'): string
    {
        $fs = new FilesystemLoader();
        $fs->addPath(\dirname(__DIR__, 2).'/src/Resources/views', 'NowoUiKitBundle');
        $twig = new Environment(new ChainLoader([new ArrayLoader([
            't.twig' => $template,
            'body.twig' => '<p>{{ n }} threads</p>',
        ]), $fs]), ['strict_variables' => true]);
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));
        $twig->addExtension(new ThemeExtension(new Theme('custom')));
        $twig->addGlobal('nowo_ui_kit_css_framework', 'custom');
        $twig->addGlobal('nowo_ui_kit_icon_set', $iconSet);

        return trim($twig->render('t.twig', $context));
    }
}
