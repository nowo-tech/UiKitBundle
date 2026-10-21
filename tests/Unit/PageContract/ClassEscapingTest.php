<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\Tests\Unit\Test\ScaffoldFixtures;
use Nowo\UiKitBundle\Theme\ThemeSlots;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Confirm;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Theme class lists accept any printable character (Tailwind arbitrary / child variants such as `[&>li]:mt-1` or
 * `content-['·']`): every slot, on the host, per page type and per bundle, is printed HTML-escaped inside its quoted
 * `class` attribute in every scaffold and partial — no attribute or element injection, no double escaping.
 */
final class ClassEscapingTest extends TestCase
{
    /** Tailwind variants + attribute / element break-out attempts with every HTML-special character. */
    private const PAYLOAD = "[&>li]:mt-1 content-['·'] data-[state=open]:[&_a]:underline x\"onmouseover=\"alert(1) y'><img src=x onerror=alert(2)> &amp;";

    /** Class tokens of the payload that must reach the DOM verbatim. */
    private const TOKENS = ['[&>li]:mt-1', "content-['·']", 'data-[state=open]:[&_a]:underline', 'x"onmouseover="alert(1)', "y'><img", 'onerror=alert(2)>', '&amp;'];

    /**
     * @return array<string, mixed>
     */
    private function theme(string $scope): array
    {
        $classes = array_fill_keys(ThemeSlots::names(), self::PAYLOAD);

        return match ($scope) {
            'host' => ['classes' => $classes],
            'page' => ['pages' => array_fill_keys(ThemeSlots::PAGE_TYPES, ['classes' => $classes])],
            default => ['bundles' => ['NowoXBundle' => ['classes' => $classes]]],
        };
    }

    /**
     * @return iterable<string, array{string, string, array<string, mixed>}>
     */
    public static function renders(): iterable
    {
        $templates = [];
        foreach (['list', 'show', 'form', 'settings', 'dashboard', 'confirm', 'wizard', 'auth'] as $scaffold) {
            $templates[$scaffold] = '@NowoUiKit/page/'.$scaffold.'.html.twig';
        }
        foreach (['host', 'page', 'bundle'] as $scope) {
            foreach (ScaffoldFixtures::all($templates, 'bundle' === $scope ? 'NowoXBundle' : null) as $name => $fixture) {
                yield $scope.' '.$name => [$scope, $fixture->template, $fixture->context];
            }
        }
        $partials = [
            '_card' => ['title' => 'T', 'intro' => 'I', 'body' => 'b', 'footer' => 'f', 'toolbar' => 't'],
            '_tabs' => ['items' => [['label' => 'A', 'href' => '/a'], ['label' => 'B', 'href' => '/b', 'current' => true]]],
            '_pagination' => ['pagination' => ['page' => 2, 'per_page' => 10, 'total' => 95, 'total_pages' => 10], 'item_count' => 10, 'route' => 'items'],
            '_row_actions' => ['actions' => [['kind' => 'edit', 'href' => '/e'], ['kind' => 'delete', 'href' => '/d', 'method' => 'post', 'csrf_token' => 't'], ['kind' => 'view', 'href' => '/v', 'disabled' => true], Action::delete('/x', 'tok')]],
            '_toasts' => ['flashes' => ['success' => ['Saved']]],
            '_flashes' => [],
            '_page_header' => ['title' => 'Items', 'intro' => 'All', 'toolbar' => '<a href="/n">New</a>'],
            '_confirm' => ['confirm' => new Confirm('Delete?', 'Gone.', expectedValue: 'acme'), 'id' => 'c1', 'action' => Action::delete('/x', 'tok')],
            '_empty' => ['message' => 'Nothing'],
            '_burger' => [],
            '_modal_shell' => ['id' => 'm1', 'title' => 'Modal', 'body' => '<p>b</p>', 'footer' => '<p>f</p>'],
            '_breadcrumbs' => ['header' => new \Nowo\UiKitBundle\View\PageHeader('T', breadcrumbs: [new \Nowo\UiKitBundle\View\Crumb('Home', '/'), new \Nowo\UiKitBundle\View\Crumb('T')])],
            '_shell' => ['brand' => 'Acme', 'name' => 'Ann', 'initials' => 'A', 'aside_items' => [], 'main' => '<p>main</p>'],
            // 2.6
            '_confirm_dialog' => ['trigger_label' => 'Open', 'dialog_id' => 'd1'],
            '_pagination_compact' => ['pagination' => \Nowo\UiKitBundle\View\Pagination::fromTotal(95, 2, 10, route: 'items', variant: 'compact')],
        ];
        foreach ($partials as $partial => $vars) {
            yield 'host partial '.$partial => ['host', '@NowoUiKitBundle/partials/'.$partial.'.html.twig', $vars];
            yield 'bundle partial '.$partial => ['bundle', '@NowoUiKitBundle/partials/'.$partial.'.html.twig', $vars + ['bundle' => 'NowoXBundle']];
        }
        // 2.6: app shell and error pages
        $kit = [
            'app shell' => ['@NowoUiKitBundle/shell/_shell.html.twig', ['brand' => 'Acme', 'name' => 'Ann', 'initials' => 'A', 'aside_items' => [['label' => 'A', 'href' => '/a']], 'main' => '<p>main</p>', 'burger_icon' => 'morph']],
            'error page' => ['@NowoUiKit/error/status.html.twig', ['status_code' => 500]],
        ];
        foreach ($kit as $name => [$template, $vars]) {
            yield 'host '.$name => ['host', $template, $vars];
            if ('app shell' === $name) {
                yield 'bundle '.$name => ['bundle', $template, $vars + ['bundle' => 'NowoXBundle']];
            }
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    #[DataProvider('renders')]
    public function testThemeClassesStayInsideTheirClassAttribute(string $scope, string $template, array $context): void
    {
        $html = PageContractTwig::create([], $this->theme($scope))->render($template, $context);

        foreach (['&amp;quot;', '&amp;gt;', '&amp;lt;', '&amp;#039;', '&amp;amp;amp;'] as $double) {
            self::assertStringNotContainsString($double, $html, 'double-escaped class list');
        }

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($dom);

        self::assertSame(0, $xpath->query('//img')?->length, 'injected element');
        self::assertSame(0, $xpath->query('//@*[starts-with(name(), "on")]')?->length, 'injected attribute');

        $themed = 0;
        foreach ($xpath->query('//*[@class]') ?: [] as $element) {
            \assert($element instanceof \DOMElement);
            $tokens = preg_split('/\s+/', $element->getAttribute('class')) ?: [];
            if (\in_array('[&>li]:mt-1', $tokens, true)) {
                ++$themed;
                foreach (self::TOKENS as $token) {
                    self::assertContains($token, $tokens, $element->getAttribute('class'));
                }
            }
        }
        self::assertGreaterThan(0, $themed, 'no themed slot rendered');
    }
}
