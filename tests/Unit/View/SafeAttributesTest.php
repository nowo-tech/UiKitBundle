<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\View;

use InvalidArgumentException;
use Nowo\UiKitBundle\View\SafeAttributes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 2.34: the attribute check of the per-element options (no on*, `style`, `srcdoc` or unsafe URL schemes).
 */
final class SafeAttributesTest extends TestCase
{
    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function refused(): iterable
    {
        yield 'handler' => [['onclick' => 'x()']];
        yield 'handler, upper case' => [['ONCLICK' => 'x()']];
        yield 'style' => [['style' => 'color:red']];
        yield 'style, upper case' => [['STYLE' => 'color:red']];
        yield 'srcdoc' => [['srcdoc' => '<script>x()</script>']];
        yield 'bad name' => [['a b' => 'x']];
        yield 'array value' => [['data-x' => ['y']]];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    #[DataProvider('refused')]
    public function testRefused(array $attributes): void
    {
        $this->expectException(InvalidArgumentException::class);
        SafeAttributes::of($attributes);
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function droppedValues(): iterable
    {
        yield 'javascript href' => ['href', 'javascript:alert(1)'];
        yield 'mixed case' => ['href', 'JaVaScRiPt:alert(1)'];
        yield 'name in upper case' => ['HREF', 'javascript:alert(1)'];
        yield 'tab in the scheme' => ['href', "java\tscript:alert(1)"];
        yield 'line break in the scheme' => ['href', "java\nscript:alert(1)"];
        yield 'leading control and space' => ['href', "\x01 javascript:alert(1)"];
        yield 'vbscript' => ['src', 'vbscript:x'];
        yield 'data url' => ['src', 'data:text/html,x'];
        yield 'data image' => ['data-image-url', 'data:image/png;base64,AAAA'];
        yield 'text in a url attribute' => ['data-url', 'JavaScript: the good parts'];
        yield 'xlink' => ['xlink:href', 'javascript:x'];
        yield 'formaction' => ['formaction', 'javascript:x'];
        yield 'action' => ['action', 'javascript:x'];
        yield 'object data' => ['data', 'javascript:x'];
        yield 'poster' => ['poster', 'javascript:x'];
        yield 'data attribute url' => ['data-task-panel-url', 'javascript:x'];
        yield 'data attribute src' => ['data-nowo-ui-dialog-src', 'javascript:x'];
        yield 'data attribute href' => ['data-href', 'javascript:x'];
        yield 'stimulus url value' => ['data-progress-url-value', 'javascript:x'];
        yield 'glued url' => ['data-nexturl', 'javascript:x'];
        yield 'uri' => ['data-next-uri', 'javascript:x'];
        yield 'link' => ['data-link', 'javascript:x'];
        yield 'blob' => ['data-row-link', 'blob:https://example.test/1'];
        yield 'path' => ['data-path', 'javascript:x'];
        yield 'path value' => ['data-x-path-value', 'javascript:x'];
        yield 'redirect' => ['data-redirect', 'javascript:x'];
        yield 'location' => ['data-location', 'javascript:x'];
    }

    #[DataProvider('droppedValues')]
    public function testAnUnsafeValueIsDroppedAndTheRestStays(string $name, string $value): void
    {
        self::assertSame(['data-keep' => '1'], SafeAttributes::of([$name => $value, 'data-keep' => '1']));
    }

    /**
     * HG-I1: the wider list must not cost a normal value of those attributes.
     */
    public function testNormalValuesOfUrlAttributesPass(): void
    {
        $attributes = [
            'data-path' => 'docs/guide/intro.md',
            'data-row-link' => '/rows/1?x=1#y',
            'data-next-uri' => '../up',
            'data-nexturl' => 'https://example.test/next',
            'data-redirect' => '/after',
            'data-location' => 'Madrid',
            'data-time-path' => '12:30',
            // HG-L4: only what runs script is dropped; these run nothing.
            'data-link-url' => 'tel:+34600000000',
            'data-sms-href' => 'sms:+34600000000',
            'data-file-href' => 'ftp://example.test/x',
            'data-chat-url' => 'slack://channel',
            'data-place-path' => 'C:\\Users',
            'data-read-link' => 'Read: intro',
        ];
        self::assertSame($attributes, SafeAttributes::of($attributes));
        self::assertSame(['data-location' => 'Madrid: centro'], SafeAttributes::of(['data-location' => 'Madrid: centro']));
    }

    public function testTextIsNotAUrl(): void
    {
        $attributes = [
            'data-title' => 'JavaScript: the good parts',
            'title' => 'javascript:alert(1)',
            'aria-label' => 'vbscript: notes',
            'data-action' => 'nowo-ui:tree:move->board#move',
            'data-task-panel-url' => '/tasks/1/panel?x=javascript:y',
            'href' => 'https://example.test/',
            'data-mail-href' => 'mailto:a@example.test',
            'src' => '',
        ];
        self::assertSame($attributes, SafeAttributes::of($attributes));
    }

    public function testDropsNullFalseAndReservedAndNormalisesClass(): void
    {
        self::assertSame(
            ['class' => 'x', 'data-a' => 1, 'data-b' => true],
            SafeAttributes::of(['CLASS' => 'x', 'data-a' => 1, 'data-b' => true, 'data-c' => null, 'data-d' => false, 'ROLE' => 'tree', 'hidden' => true], ['role', 'hidden']),
        );
        self::assertSame(['data-a' => 1], SafeAttributes::withoutClass(['class' => 'x', 'data-a' => 1]));
        self::assertSame([], SafeAttributes::of([]));
    }

    public function testAReservedNameIsDroppedWhateverItsValue(): void
    {
        self::assertSame([], SafeAttributes::of(['href' => 'javascript:x'], ['href']));
    }
}
