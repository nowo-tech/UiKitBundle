<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\Test\FrameworkClasses;
use Nowo\UiKitBundle\View\Label;
use PHPUnit\Framework\TestCase;

/**
 * Generic native dialog partial (2.1): slots dialog / dialog_header / dialog_title / dialog_body / dialog_footer,
 * opener contract for js/nowo-ui-dialog.js, no framework classes, loaded by the document layout.
 */
final class DialogPartialTest extends TestCase
{
    private const THEME = [
        'classes' => ['dialog' => 'app-dialog', 'dialog_header' => 'app-dh', 'dialog_title' => 'app-dt', 'dialog_body' => 'app-db', 'dialog_footer' => 'app-df'],
        'attributes' => ['dialog' => ['data-host' => '1'], 'dialog_body' => ['data-body' => '1']],
    ];

    public function testDefaultMarkup(): void
    {
        $html = PageContractTwig::create()->render('@NowoUiKitBundle/partials/_dialog.html.twig', ['id' => 'd1', 'title' => 'Import <b>', 'body' => '<p>Body</p>', 'footer' => '<button type="button" data-nowo-ui-dialog-close>OK</button>']);

        self::assertMatchesRegularExpression('#<dialog id="d1" class="nowo-ui-dialog" data-nowo-ui-dialog aria-labelledby="d1-title" data-nowo-ui-dialog-backdrop-close>#', $html);
        self::assertStringContainsString('<header class="nowo-ui-dialog__header">', $html);
        self::assertStringContainsString('<h2 class="nowo-ui-dialog__title" id="d1-title">Import &lt;b&gt;</h2>', $html);
        self::assertStringContainsString('<button type="button" class="nowo-ui-dialog__close" data-nowo-ui-dialog-close aria-label="Close" title="Close"><span aria-hidden="true">&times;</span></button>', $html);
        self::assertStringContainsString('<div class="nowo-ui-dialog__body"><p>Body</p></div>', $html);
        self::assertStringContainsString('<footer class="nowo-ui-dialog__footer"><button type="button" data-nowo-ui-dialog-close>OK</button></footer>', $html);
        self::assertSame(0, preg_match('/\son[a-z]+=/', $html));

        preg_match_all('/class="([^"]*)"/', $html, $m);
        $classes = preg_split('/\s+/', implode(' ', $m[1])) ?: [];
        foreach (['bootstrap5', 'tailwind', 'none'] as $framework) {
            $html = PageContractTwig::create([], [], $framework)->render('@NowoUiKitBundle/partials/_dialog.html.twig', ['id' => 'd1', 'title' => 'T']);
            preg_match_all('/class="([^"]*)"/', $html, $m);
            self::assertSame([], FrameworkClasses::violations(preg_split('/\s+/', implode(' ', $m[1])) ?: [], $framework), $framework);
        }
        self::assertNotContains('modal', $classes);
    }

    public function testOptionsThemeAndLabelTitle(): void
    {
        $html = PageContractTwig::create([], self::THEME)->render('@NowoUiKitBundle/partials/_dialog.html.twig', [
            'id' => 'd2',
            'title' => new Label('action.close', 'NowoUiKitBundle'),
            'size' => 'lg',
            'body_muted' => true,
            'dismissible' => false,
            'backdrop_close' => false,
            'attr' => ['data-turbo' => 'false', 'data-host' => '2'],
            'body' => 'x',
        ]);

        self::assertStringContainsString('<dialog id="d2" class="nowo-ui-dialog app-dialog nowo-ui-dialog--lg" data-nowo-ui-dialog aria-labelledby="d2-title" data-host="2" data-turbo="false">', $html);
        self::assertStringContainsString('<header class="nowo-ui-dialog__header app-dh">', $html);
        self::assertStringContainsString('<h2 class="nowo-ui-dialog__title app-dt" id="d2-title">Close</h2>', $html);
        self::assertStringContainsString('<div class="nowo-ui-dialog__body app-db nowo-ui-dialog__body--muted" data-body="1">x</div>', $html);
        self::assertStringNotContainsString('nowo-ui-dialog__close', $html);
        self::assertStringNotContainsString('<footer', $html);
        self::assertStringNotContainsString('backdrop-close', $html);
    }

    public function testUnknownSizeFallsBackToMediumAndBodyBlockCanBeEmbedded(): void
    {
        $twig = PageContractTwig::create(['t' => "{% embed '@NowoUiKitBundle/partials/_dialog.html.twig' with {id: 'd3', title: 'T', size: 'huge', close_label: 'Dismiss'} %}{% block nowo_ui_dialog_body %}<form method=\"dialog\"><button>Done</button></form>{% endblock %}{% endembed %}"]);
        $html = $twig->render('t');

        self::assertStringContainsString('<dialog id="d3" class="nowo-ui-dialog" data-nowo-ui-dialog', $html);
        self::assertStringContainsString('<div class="nowo-ui-dialog__body"><form method="dialog"><button>Done</button></form></div>', $html);
        self::assertStringContainsString('aria-label="Dismiss"', $html);
    }

    public function testPartialIsReplaceableAndTheLayoutLoadsTheScript(): void
    {
        $twig = PageContractTwig::create(['host/_dialog.html.twig' => '<dialog data-host-dialog id="{{ id }}"></dialog>', 't' => "{{ include(nowo_ui_partial('dialog'), {id: 'x', title: 'T'}) }}"], ['partials' => ['dialog' => 'host/_dialog.html.twig']]);
        self::assertSame('<dialog data-host-dialog id="x"></dialog>', trim($twig->render('t')));

        $layout = (string) file_get_contents(PageContractTwig::root().'/src/Resources/views/layout/document.html.twig');
        self::assertStringContainsString("'nowo-ui-dialog'", $layout);
        self::assertFileExists(PageContractTwig::root().'/src/Resources/public/js/nowo-ui-dialog.js');
    }
}
