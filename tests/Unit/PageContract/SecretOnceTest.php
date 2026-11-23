<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use InvalidArgumentException;
use Nowo\UiKitBundle\Theme\ThemeSlots;
use Nowo\UiKitBundle\View\Label;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Error\RuntimeError;

/**
 * 2.69 one-time secret (theme partial `secret_once`, runtime `secret-once`; owner decision 2026-11-23 for the
 * ApiToken "new token" page): the secret is printed exactly once, as the escaped text of the value element — never in
 * an attribute, a hidden input, a title or an aria-label — visible without JavaScript, with the controls `hidden`
 * until the runtime runs. No inline style or handler (CSP), the same output on a reused environment (worker mode).
 */
final class SecretOnceTest extends TestCase
{
    private const SECRET = 'nowo_tok_7f3a9c1e5b2d4f6a8c0e';

    public function testItIsAThemePartialAHostCanReplace(): void
    {
        self::assertArrayHasKey('secret_once', ThemeSlots::PARTIALS);
        self::assertFileExists(PageContractTwig::root().'/src/Resources/views/partials/_secret_once.html.twig');
        $twig = PageContractTwig::create(['t' => "{{ nowo_ui_partial('secret_once') }}"], ['partials' => ['secret_once' => 'host/_secret.html.twig']]);
        self::assertSame('host/_secret.html.twig', $twig->render('t'));
    }

    public function testTheSecretIsPrintedExactlyOnceAsTextAndVisibleWithoutJavaScript(): void
    {
        $html = $this->render("{{ include(nowo_ui_partial('secret_once'), {secret: secret, label: 'API token', secret_id: 'tok'}) }}");

        self::assertSame(1, substr_count($html, self::SECRET));
        self::assertStringContainsString('<code class="nowo-ui-secret-once__value" id="tok" translate="no" spellcheck="false" autocomplete="off" autocapitalize="off" data-nowo-ui-secret-once-value>'.self::SECRET.'</code>', $html);
        // never in an attribute value
        self::assertDoesNotMatchRegularExpression('#="[^"]*'.self::SECRET.'#', $html);
        self::assertStringNotContainsString('<input', $html);
        self::assertStringNotContainsString('title=', $html);
        self::assertStringNotContainsString('aria-label=', $html);
        // no state server-side: the runtime masks it, CSS hides nothing without it
        self::assertStringNotContainsString('data-nowo-ui-secret-once-state', $html);
        self::assertStringContainsString('<div class="nowo-ui-secret-once" role="group" aria-labelledby="tok-label" data-nowo-ui-secret-once data-nowo-ui-secret-once-copied-label="Copied" data-nowo-ui-secret-once-copy-failed-label="Copy failed" data-nowo-ui-secret-once-cleared-label="Cleared: the secret is no longer on this page">', $html);
        self::assertStringContainsString('<span class="nowo-ui-secret-once__label" id="tok-label">API token</span>', $html);
        self::assertStringContainsString('<div class="nowo-ui-secret-once__actions" data-nowo-ui-secret-once-controls hidden>', $html);
        self::assertStringContainsString('<button type="button" class="nowo-ui-btn nowo-ui-btn--outline nowo-ui-btn--sm" aria-pressed="false" aria-controls="tok" aria-describedby="tok-label" data-nowo-ui-secret-once-toggle>Show</button>', $html);
        self::assertStringContainsString('<button type="button" class="nowo-ui-btn nowo-ui-btn--outline nowo-ui-btn--sm" aria-describedby="tok-label" data-nowo-ui-secret-once-copy>Copy</button>', $html);
        self::assertStringContainsString('<span class="nowo-ui-secret-once__status" role="status" data-nowo-ui-secret-once-status></span>', $html);
        self::assertStringContainsString('<span class="visually-hidden">Hidden</span>', $html);
        self::assertStringNotContainsString('nowo-ui-secret-once__hint', $html);
    }

    public function testTheSecretIsEscaped(): void
    {
        $html = $this->render("{{ include(nowo_ui_partial('secret_once'), {secret: '<b>\"x\"&</b>', secret_id: 'e'}) }}");
        self::assertStringContainsString('data-nowo-ui-secret-once-value>&lt;b&gt;&quot;x&quot;&amp;&lt;/b&gt;</code>', $html);
        self::assertStringNotContainsString('<b>', $html);
    }

    public function testOptionsLabelsHintAndAttributes(): void
    {
        $html = $this->render(
            "{{ include(nowo_ui_partial('secret_once'), {secret: secret, secret_id: 'k', label: label, hint: 'Shown only once.', revealed: true, clear_on_hide: true, clear_after: 60000.7, labels: {show: 'Reveal', copy: 'Copy token', copied: 'Done', copy_failed: 'Failed', masked: 'Masked', cleared: 'Wiped'}, attr: {class: 'mine', 'data-testid': 'tok', 'data-nowo-ui-secret-once-state': 'revealed', role: 'none', 'aria-labelledby': 'x'}}) }}",
            ['label' => Label::kit('secret_once.label')],
        );
        self::assertSame(1, substr_count($html, self::SECRET));
        self::assertStringContainsString('<div class="nowo-ui-secret-once mine" role="group" aria-labelledby="k-label" aria-describedby="k-hint" data-nowo-ui-secret-once data-nowo-ui-secret-once-copied-label="Done" data-nowo-ui-secret-once-copy-failed-label="Failed" data-nowo-ui-secret-once-cleared-label="Wiped" data-nowo-ui-secret-once-revealed data-nowo-ui-secret-once-clear-on-hide data-nowo-ui-secret-once-clear-after="60000" data-testid="tok">', $html);
        self::assertStringContainsString('id="k-label">Secret</span>', $html);
        self::assertStringContainsString('<p class="nowo-ui-secret-once__hint" id="k-hint">Shown only once.</p>', $html);
        self::assertStringContainsString('data-nowo-ui-secret-once-toggle>Reveal</button>', $html);
        self::assertStringContainsString('data-nowo-ui-secret-once-copy>Copy token</button>', $html);
        self::assertStringContainsString('<span class="visually-hidden">Masked</span>', $html);
        self::assertStringContainsString('data-nowo-ui-secret-once-cleared>Wiped</span>', $html);
        self::assertStringNotContainsString('data-nowo-ui-secret-once-state', $html);
        self::assertStringNotContainsString('role="none"', $html);

        self::assertStringNotContainsString('keep-on-leave', $html);
        $html = $this->render("{{ include(nowo_ui_partial('secret_once'), {secret: secret, secret_id: 'w', wipe_on_leave: false}) }}");
        self::assertStringContainsString(' data-nowo-ui-secret-once-keep-on-leave>', $html);
        self::assertStringNotContainsString('keep-on-leave', $this->render("{{ include(nowo_ui_partial('secret_once'), {secret: secret, wipe_on_leave: 0}) }}"));

        // clear_after 0 / negative prints nothing; the default id is partial-scoped (an inherited `id` never reaches it)
        $html = $this->render("{{ include(nowo_ui_partial('secret_once'), {secret: secret, clear_after: 0}) }}", ['id' => 'page']);
        self::assertStringNotContainsString('clear-after', $html);
        self::assertMatchesRegularExpression('#<code class="nowo-ui-secret-once__value" id="nowo-ui-secret-\d+"#', $html);
        self::assertStringNotContainsString('id="page"', $html);
    }

    public function testTranslatedInEveryLocale(): void
    {
        foreach (['es' => 'Mostrar', 'fr' => 'Afficher', 'de' => 'Anzeigen', 'it' => 'Mostra', 'nl' => 'Tonen', 'pt' => 'Mostrar'] as $locale => $show) {
            $twig = PageContractTwig::create(['t' => "{{ include(nowo_ui_partial('secret_once'), {secret: 's3cr3t', secret_id: 'l'}) }}"], framework: 'custom', locale: $locale);
            self::assertStringContainsString('data-nowo-ui-secret-once-toggle>'.$show.'</button>', $twig->render('t'));
        }
    }

    public function testTheSecretIsRequiredAndHandlersAreRefused(): void
    {
        try {
            $this->render("{{ include(nowo_ui_partial('secret_once'), {label: 'x'}, false) }}");
            self::fail('secret is required');
        } catch (RuntimeError $e) {
            self::assertStringContainsString('secret', $e->getMessage());
        }

        $this->expectException(RuntimeError::class);
        try {
            $this->render("{{ include(nowo_ui_partial('secret_once'), {secret: 'x', attr: {onclick: 'alert(1)'}}) }}");
        } catch (RuntimeError $e) {
            self::assertInstanceOf(InvalidArgumentException::class, $e->getPrevious());

            throw $e;
        }
    }

    public function testNoInlineStyleOrHandlerAndTheSameOutputOnAReusedEnvironment(): void
    {
        $twig = $this->twig(['t' => "{{ include(nowo_ui_partial('secret_once'), {secret: secret, secret_id: 'r'}) }}"]);
        $a = $twig->render('t', ['secret' => 'first-secret']);
        $b = $twig->render('t', ['secret' => 'second-secret']);
        self::assertSame($a, $twig->render('t', ['secret' => 'first-secret']));
        self::assertStringNotContainsString('first-secret', $b);
        $source = (string) file_get_contents(PageContractTwig::root().'/src/Resources/views/partials/_secret_once.html.twig');
        self::assertDoesNotMatchRegularExpression('#\sstyle=|\son[a-z]+=|<script#', $source);
    }

    public function testTheCssUsesTokensMasksWithoutTheRuntimeStateOnlyAndIsLayered(): void
    {
        $css = (string) file_get_contents(PageContractTwig::root().'/src/Resources/public/css/nowo-ui.unlayered.css');
        $start = strpos($css, 'One-time secret (2.69');
        $end = strpos($css, '/* --- Locale switcher --- */');
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $section = substr($css, $start, $end - $start);
        self::assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}\b|rgba?\(|hsla?\(/', $section);
        self::assertStringNotContainsString('transition:', $section);
        self::assertStringNotContainsString('animation:', $section);
        // the value is hidden only in a runtime state, never by default
        preg_match_all('/([^{}]+)\{\s*display: none;/', $section, $rules);
        foreach ($rules[1] as $selector) {
            if (str_contains($selector, '__value')) {
                self::assertStringContainsString("[data-nowo-ui-secret-once-state='", $selector);
            }
        }
        self::assertStringContainsString('.nowo-ui-secret-once__actions:not([hidden])', $section);
        self::assertStringContainsString($section, (string) file_get_contents(PageContractTwig::root().'/src/Resources/public/css/nowo-ui.css'));
    }

    public function testTheRuntimeIsBuiltAndKeepsNoCopy(): void
    {
        $js = PageContractTwig::root().'/src/Resources/public/js/nowo-ui-secret-once.js';
        self::assertFileExists($js);
        $source = (string) file_get_contents(PageContractTwig::root().'/src/Resources/assets/src/nowo-ui-secret-once.ts');
        self::assertStringNotContainsString("createElement('textarea')", $source);
        self::assertStringNotContainsString('setAttribute(\'data-secret', $source);
        self::assertStringContainsString('navigator.clipboard.writeText', $source);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $source, array $context = []): string
    {
        return $this->twig(['t' => $source])->render('t', $context + ['secret' => self::SECRET]);
    }

    /**
     * @param array<string, string> $templates
     */
    private function twig(array $templates): Environment
    {
        return PageContractTwig::create($templates, framework: 'custom');
    }
}
