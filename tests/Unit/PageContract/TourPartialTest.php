<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\Test\FrameworkClasses;
use Nowo\UiKitBundle\Theme\ThemeSlots;
use Nowo\UiKitBundle\Tour\TourBuilder;
use Nowo\UiKitBundle\Tour\TourStep;
use Nowo\UiKitBundle\View\Label;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Yaml\Yaml;

/**
 * 2.13: the `tour` partial (Stimulus peer `product-tour`, driver.js) and the button mode of the page-edge tab.
 */
final class TourPartialTest extends TestCase
{
    private const TPL = "{{ include(nowo_ui_partial('tour'), vars, false) }}";

    private const MESSAGES = ['messages' => [
        'tour.steps.dashboard.welcome.title' => 'Welcome',
        'tour.steps.dashboard.welcome.description' => 'Hello %name% <3',
        'tour.steps.dashboard.sidebar.title' => 'Sidebar',
        'tour.steps.dashboard.sidebar.description' => 'Every area',
    ]];

    public function testRegisteredAndTranslated(): void
    {
        self::assertSame('@NowoUiKitBundle/partials/_tour.html.twig', ThemeSlots::PARTIALS['tour']);
        foreach (['en', 'es', 'de', 'fr', 'it', 'nl', 'pt'] as $locale) {
            /** @var array<string, string> $catalogue */
            $catalogue = Yaml::parseFile(PageContractTwig::root().'/src/Resources/translations/NowoUiKitBundle.'.$locale.'.yaml');
            foreach (['tab', 'next', 'previous', 'done', 'close', 'progress'] as $key) {
                self::assertNotEmpty($catalogue['tour.'.$key] ?? null, $locale.' tour.'.$key);
            }
            self::assertStringContainsString('{{current}}', $catalogue['tour.progress']);
            self::assertStringContainsString('{{total}}', $catalogue['tour.progress']);
        }
    }

    public function testDefaultMarkupFromATour(): void
    {
        $html = $this->render(['tour' => $this->tour()]);
        $root = (new Crawler($html))->filter('div.nowo-ui-tour');

        self::assertSame('product-tour', $root->attr('data-controller'));
        self::assertSame('nowo-ui-tour:start@window->product-tour#start', $root->attr('data-action'));
        self::assertSame('dashboard', $root->attr('data-product-tour-key-value'));
        self::assertSame('true', $root->attr('data-product-tour-auto-start-value'));
        self::assertSame([
            ['popover' => ['title' => 'Welcome', 'description' => 'Hello Ada <3']],
            ['element' => '[data-tour="sidebar"]', 'popover' => ['title' => 'Sidebar', 'description' => 'Every area', 'side' => 'right', 'align' => 'start']],
        ], json_decode((string) $root->attr('data-product-tour-steps-value'), true));
        self::assertSame(
            ['next' => 'Next', 'previous' => 'Back', 'done' => 'Done', 'close' => 'Close the tour', 'progress' => '{{current}} of {{total}}'],
            json_decode((string) $root->attr('data-product-tour-labels-value'), true),
        );
        foreach (['force', 'force-param', 'storage-key', 'seen-url', 'seen-token', 'seen-token-url', 'seen-method', 'html', 'popover-class'] as $value) {
            self::assertNull($root->attr('data-product-tour-'.$value.'-value'), $value);
        }
        self::assertNull($root->attr('data-testid'));

        self::assertStringContainsString('<div class="nowo-ui-edge-tab" data-nowo-ui-edge-tab-slot="0">', $html);
        self::assertStringContainsString('<button type="button" class="nowo-ui-edge-tab__link" title="Tour this page" aria-label="Tour this page" data-action="product-tour#start" data-product-tour-target="tab">', $html);
        self::assertStringContainsString('<i class="bi bi-signpost-split" aria-hidden="true"></i>', $html);
        self::assertStringNotContainsString('<a ', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('style=', $html);
        self::assertSame(0, preg_match('/\son[a-z]+=/', $html));

        foreach (['bootstrap5', 'tailwind', 'none'] as $framework) {
            $markup = PageContractTwig::create(['t' => self::TPL], [], $framework, messages: self::MESSAGES)->render('t', ['vars' => ['tour' => $this->tour()]]);
            preg_match_all('/class="([^"]*)"/', $markup, $m);
            $classes = array_filter(preg_split('/\s+/', implode(' ', $m[1])) ?: [], static fn (string $c): bool => !str_starts_with($c, 'bi'));
            self::assertSame([], FrameworkClasses::violations(array_values($classes), $framework), $framework);
        }
    }

    public function testHashInputAndOptions(): void
    {
        $html = $this->render([
            'tour' => ['key' => 'issues', 'auto_start' => false, 'steps' => [
                ['selector' => null, 'title' => ['key' => 'tour.tab', 'domain' => 'NowoUiKitBundle'], 'description' => 'Plain <b>'],
                ['selector' => '#list', 'title' => new Label('tour.done', 'NowoUiKitBundle')],
            ]],
            'force' => true,
            'force_param' => 'tour',
            'storage_key' => 'app-tours',
            'seen_url' => '/account/tour-seen',
            'seen_token' => 'tok',
            'seen_token_url' => '/account/csrf',
            'seen_method' => 'PUT',
            'html_content' => true,
            'popover_class' => 'app-popover',
            'labels' => ['tab' => 'Guide me', 'next' => 'Go'],
            'tab_slot' => 2,
            'tab_icon' => 'book',
            'tab_class' => 'app-tab',
            'attr' => ['data-turbo-permanent' => 'true'],
            'testids' => true,
        ]);
        $root = (new Crawler($html))->filter('div.nowo-ui-tour');

        self::assertSame('false', $root->attr('data-product-tour-auto-start-value'));
        self::assertSame([
            ['popover' => ['title' => 'Tour this page', 'description' => 'Plain <b>']],
            ['element' => '#list', 'popover' => ['title' => 'Done', 'description' => '']],
        ], json_decode((string) $root->attr('data-product-tour-steps-value'), true));
        foreach (['force' => 'true', 'force-param' => 'tour', 'storage-key' => 'app-tours', 'seen-url' => '/account/tour-seen', 'seen-token' => 'tok', 'seen-token-url' => '/account/csrf', 'seen-method' => 'PUT', 'html' => 'true', 'popover-class' => 'app-popover'] as $value => $expected) {
            self::assertSame($expected, $root->attr('data-product-tour-'.$value.'-value'), $value);
        }
        self::assertSame('Go', json_decode((string) $root->attr('data-product-tour-labels-value'), true)['next']);
        self::assertSame('true', $root->attr('data-turbo-permanent'));
        self::assertSame('tour', $root->attr('data-testid'));
        self::assertStringContainsString('<div class="nowo-ui-edge-tab app-tab" data-nowo-ui-edge-tab-slot="2" data-testid="tour-tab">', $html);
        self::assertStringContainsString('title="Guide me" aria-label="Guide me"', $html);
        self::assertStringContainsString('bi bi-book', $html);

        // A Tour's own autoStart flag, overridden by the option.
        $manual = $this->render(['tour' => TourBuilder::create('k', autoStart: false)->add(new TourStep(null, 'T'))->build()]);
        self::assertStringContainsString('data-product-tour-auto-start-value="false"', $manual);
        $forced = $this->render(['tour' => TourBuilder::create('k', autoStart: false)->add(new TourStep(null, 'T'))->build(), 'auto_start' => true]);
        self::assertStringContainsString('data-product-tour-auto-start-value="true"', $forced);
        $hash = $this->render(['tour' => ['key' => 'k', 'steps' => [['title' => 'T']]]]);
        self::assertStringContainsString('data-product-tour-auto-start-value="true"', $hash);
    }

    public function testInlineTabNoTabAndEmptyTour(): void
    {
        $inline = $this->render(['tour' => $this->tour(), 'tab_inline' => true]);
        self::assertStringContainsString('<p class="nowo-ui-edge-link">', $inline);
        self::assertStringContainsString('<button type="button" class="nowo-ui-edge-link__button" data-action="product-tour#start" data-product-tour-target="tab">Tour this page</button>', $inline);

        $noTab = $this->render(['tour' => $this->tour(), 'show_tab' => false]);
        self::assertStringContainsString('data-controller="product-tour"', $noTab);
        self::assertStringNotContainsString('<button', $noTab);

        self::assertSame('', trim($this->render(['tour' => TourBuilder::create('empty')->build()])));
        self::assertSame('', trim($this->render(['tour' => ['key' => 'empty']])));
    }

    public function testPartialIsReplaceable(): void
    {
        $twig = PageContractTwig::create(['host/_tour.html.twig' => '<div data-host-tour="{{ tour.key }}"></div>', 't' => self::TPL], ['partials' => ['tour' => 'host/_tour.html.twig']]);
        self::assertSame('<div data-host-tour="dashboard"></div>', trim($twig->render('t', ['vars' => ['tour' => $this->tour()]])));
    }

    public function testEdgeTabButtonMode(): void
    {
        $twig = PageContractTwig::create(['t' => "{% include '@NowoUiKitBundle/partials/_edge_tab.html.twig' with vars only %}"]);

        $button = $twig->render('t', ['vars' => ['button' => true, 'label' => 'Help', 'attr' => ['data-action' => 'help#open'], 'testid' => 'help']]);
        self::assertStringContainsString('<div class="nowo-ui-edge-tab" data-nowo-ui-edge-tab-slot="0" data-testid="help">', $button);
        self::assertStringContainsString('<button type="button" class="nowo-ui-edge-tab__link" title="Help" aria-label="Help" data-action="help#open">', $button);
        self::assertStringContainsString('bi bi-book', $button);

        // `attr` on the link of the default mode.
        $link = $twig->render('t', ['vars' => ['href' => '/m', 'new_tab' => false, 'attr' => ['data-turbo' => 'false']]]);
        self::assertStringContainsString('<a class="nowo-ui-edge-tab__link" href="/m" title="Read this chapter in the manual" aria-label="Read this chapter in the manual" data-turbo="false">', $link);
        $bare = $twig->render('t', ['vars' => ['href' => '/m', 'attr' => ['data-bare' => true, 'data-off' => false, 'data-null' => null, 'onclick' => 'x()', 'ONLOAD' => 'y()']]]);
        self::assertStringContainsString('aria-label="Read this chapter in the manual" data-bare>', $bare);
        self::assertStringNotContainsString('data-off', $bare);
        self::assertStringNotContainsString('data-null', $bare);
        self::assertSame(0, preg_match('/\son[a-z]+=/i', $bare));
        $inline = $twig->render('t', ['vars' => ['href' => '/m', 'inline' => true, 'attr' => ['data-x' => '1']]]);
        self::assertStringContainsString('<a href="/m" target="_blank" rel="noopener noreferrer" data-x="1">Read this chapter in the manual</a>', $inline);

        $css = (string) file_get_contents(PageContractTwig::root().'/src/Resources/public/css/nowo-ui.css');
        self::assertStringContainsString('button.nowo-ui-edge-tab__link {', $css);
        self::assertStringContainsString('.nowo-ui-edge-link__button {', $css);
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function render(array $vars): string
    {
        return PageContractTwig::create(['t' => self::TPL], messages: self::MESSAGES)->render('t', ['vars' => $vars]);
    }

    private function tour(): \Nowo\UiKitBundle\Tour\Tour
    {
        return TourBuilder::create('dashboard', prefix: 'tour.steps.dashboard.')
            ->welcome('welcome', ['%name%' => 'Ada'])
            ->step(TourStep::anchor('sidebar'), 'sidebar', 'right', 'start')
            ->build();
    }
}
