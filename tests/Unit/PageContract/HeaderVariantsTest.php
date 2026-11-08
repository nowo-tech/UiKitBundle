<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\DependencyInjection\Configuration;
use Nowo\UiKitBundle\Shell\HeaderPresentation;
use Nowo\UiKitBundle\Shell\HeaderPresentationResolver;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\AreaNav;
use Nowo\UiKitBundle\View\AreaNavItem;
use Nowo\UiKitBundle\View\BackLink;
use Nowo\UiKitBundle\View\Crumb;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\PageStatus;
use Nowo\UiKitBundle\View\ShowPage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * 2.39 (UI-6) page header variants: `inline`, `no_intro`, `no_title`, `no_header` on the page header (breadcrumbs, back
 * link, status, toolbar and tabs stay; nothing moves to the top bar). Standard / compact markup is pinned by
 * CompactHeaderTest and CompactHeader227BcTest.
 */
final class HeaderVariantsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function variants(): iterable
    {
        // presentation => [intro shown, title visually hidden]
        yield 'inline' => ['inline', true, false];
        yield 'no_intro' => ['no_intro', false, false];
        yield 'no_title' => ['no_title', true, true];
        yield 'no_header' => ['no_header', false, true];
    }

    #[DataProvider('variants')]
    public function testVariantsKeepEverythingButTheirOwnPart(string $presentation, bool $intro, bool $titleHidden): void
    {
        $twig = PageContractTwig::create(theme: ['classes' => ['page_header' => 'kit-admin-page-header']]);
        $html = $twig->render('@NowoUiKit/page/_page_header.html.twig', ['header' => $this->header(), 'presentation' => $presentation]);
        $modifier = 'nowo-ui-page-header--'.str_replace('_', '-', $presentation);

        self::assertStringContainsString('kit-admin-page-header '.$modifier, str_replace(' d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', '', $html));
        self::assertStringContainsString('data-nowo-ui-page-header="'.$presentation.'" data-nowo-ui-region="header"', $html);
        self::assertStringNotContainsString('nowo-ui-page-header--compact', $html);
        self::assertStringNotContainsString('data-nowo-ui-page-header-empty', $html);
        self::assertSame(1, substr_count($html, '<h1'));
        if ($titleHidden) {
            self::assertMatchesRegularExpression('#<h1 class="[^"]*nowo-ui-page-header__title[^"]* visually-hidden">Quotes</h1>#', $html);
        } else {
            self::assertStringNotContainsString('visually-hidden">Quotes</h1>', $html);
        }
        self::assertSame($intro, str_contains($html, 'Every quote of the team'));
        self::assertStringContainsString('aria-label="Breadcrumb"', $html, 'breadcrumbs stay');
        self::assertStringContainsString('href="/sales"', $html, 'the back link stays');
        self::assertStringContainsString('New quote', $html, 'the toolbar stays');
        self::assertStringContainsString('Draft', $html, 'the status stays');
        self::assertStringContainsString('nowo-ui-page-header__nav', $html, 'the tabs stay');
        self::assertSame(0, preg_match('/\sstyle=|<style|<script|\son[a-z]+=/i', $html), 'CSP: no inline style, script or handler');

        // The request attribute and the config select the variant too; compact_actions bar changes nothing.
        $request = PageContractTwig::create(request: new Request(attributes: [HeaderPresentation::ATTRIBUTE => $presentation]));
        self::assertStringContainsString($modifier, $request->render('@NowoUiKit/page/show.html.twig', ['page' => new ShowPage($this->header(), [])]));
        $config = PageContractTwig::create(header: ['presentation' => $presentation, 'compact_actions' => 'bar']);
        $fromConfig = $config->render('@NowoUiKit/page/_page_header.html.twig', ['header' => $this->header()]);
        self::assertStringContainsString($modifier, $fromConfig);
        self::assertStringContainsString('New quote', $fromConfig);
    }

    public function testStandardAndCompactAreUnchangedAndCompactStillWins(): void
    {
        $twig = PageContractTwig::create();
        $standard = $twig->render('@NowoUiKit/page/_page_header.html.twig', ['header' => $this->header()]);
        self::assertStringNotContainsString('data-nowo-ui-page-header', $standard);
        self::assertStringNotContainsString('nowo-ui-page-header--', $standard);

        $request = PageContractTwig::create(request: new Request(attributes: [HeaderPresentation::ATTRIBUTE => 'no_intro']));
        $compact = $request->render('@NowoUiKit/page/_page_header.html.twig', ['header' => $this->header(), 'presentation' => 'compact']);
        self::assertStringContainsString('nowo-ui-page-header--compact', $compact, 'the option beats the request attribute');
        self::assertStringNotContainsString('--no-intro', $compact);
    }

    public function testPartialWithoutTheExtensionAndHostOverrides(): void
    {
        // Without UiKit's extension (no nowo_ui_header global), an explicit valid variant still applies.
        $bare = PageContractTwig::create();
        $partial = '@NowoUiKitBundle/partials/_page_header.html.twig';
        $noTitle = $bare->render($partial, ['title' => 'T', 'intro' => 'I', 'presentation' => 'no_title']);
        self::assertStringContainsString('visually-hidden">T</h1>', $noTitle);
        self::assertStringContainsString('>I</p>', $noTitle);
        self::assertStringNotContainsString('--compact', $noTitle);
        // title_hidden with a variant hides the title only; without one it still means compact (2.27).
        $inlineHidden = $bare->render($partial, ['title' => 'T', 'intro' => 'I', 'presentation' => 'inline', 'title_hidden' => true]);
        self::assertStringContainsString('visually-hidden">T</h1>', $inlineHidden);
        self::assertStringContainsString('nowo-ui-page-header--inline', $inlineHidden);
        self::assertStringNotContainsString('--compact', $inlineHidden);
        self::assertStringContainsString('--compact', $bare->render($partial, ['title' => 'T', 'title_hidden' => true]));

        $twig = PageContractTwig::create(['host/_ph.html.twig' => "<header data-p=\"{{ presentation }}\" data-h=\"{{ title_hidden ? 'y' : 'n' }}\">{{ intro ?? 'no intro' }}</header>"], ['partials' => ['page_header' => 'host/_ph.html.twig']]);
        $header = new PageHeader('Quotes', 'Intro');
        $expected = ['inline' => 'n">Intro', 'no_intro' => 'n">no intro', 'no_title' => 'y">Intro', 'no_header' => 'y">no intro'];
        foreach ($expected as $presentation => $tail) {
            self::assertSame('<header data-p="'.$presentation.'" data-h="'.$tail.'</header>', trim($twig->render('@NowoUiKit/page/_page_header.html.twig', ['header' => $header, 'presentation' => $presentation])));
        }
    }

    public function testResolverConfigAndCss(): void
    {
        $stack = new RequestStack();
        $stack->push(new Request(attributes: [HeaderPresentation::ATTRIBUTE => HeaderPresentation::NoHeader]));
        $resolver = new HeaderPresentationResolver($stack, 'inline');
        self::assertSame('no_header', $resolver->resolve());
        self::assertSame('no_title', $resolver->resolve('no_title'));
        self::assertFalse($resolver->isCompact());
        self::assertSame('inline', (new HeaderPresentationResolver(null, 'inline'))->resolve());
        self::assertSame(HeaderPresentation::NoIntro, HeaderPresentation::fromOption('no_intro'));

        $processed = (new Processor())->processConfiguration(new Configuration(), [['header' => ['presentation' => 'no_header']]]);
        self::assertSame('no_header', $processed['header']['presentation']);

        $css = (string) file_get_contents(PageContractTwig::root().'/src/Resources/public/css/nowo-ui.unlayered.css');
        foreach (['.nowo-ui-page-header--inline .nowo-ui-page-header__text:has(> .nowo-ui-page-header__intro)', '.nowo-ui-page-header--no-title', '.nowo-ui-page-header--no-header', 'var(--nowo-ui-page-header-inline-gap, 0.75rem)', 'var(--nowo-ui-page-header-inline-intro-min, 16rem)'] as $needle) {
            self::assertStringContainsString($needle, $css);
        }
    }

    private function header(): PageHeader
    {
        return new PageHeader(
            'Quotes',
            'Every quote of the team',
            [Action::create('/quotes/new', 'New quote')],
            [new Crumb('Sales', '/sales'), new Crumb('Quotes')],
            tabs: new AreaNav([new AreaNavItem('General', '/g', true)]),
            back: new BackLink('/sales', 'Back'),
            status: new PageStatus('Draft', 'warning'),
        );
    }
}
