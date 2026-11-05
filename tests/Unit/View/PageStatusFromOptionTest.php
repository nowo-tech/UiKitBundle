<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\View;

use ArrayObject;
use InvalidArgumentException;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\PageStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * 2.29: the `status` option of the `dialog` / `confirm_dialog` partials (`nowo_ui_dialog_status()`).
 */
final class PageStatusFromOptionTest extends TestCase
{
    public function testPageStatusIsKept(): void
    {
        $status = new PageStatus('Up', 'success');
        self::assertSame($status, PageStatus::fromOption($status));
    }

    public function testHashBecomesAPageStatus(): void
    {
        $label = new Label('status.up');
        $title = new Label('status.since');
        $status = PageStatus::fromOption(['label' => $label, 'tone' => 'warning', 'title' => $title, 'attributes' => ['data-x' => '1']]);
        self::assertInstanceOf(PageStatus::class, $status);
        self::assertSame($label, $status->label);
        self::assertSame('warning', $status->tone);
        self::assertSame($title, $status->title);
        self::assertSame(['data-x' => '1'], $status->attributes);

        $plain = PageStatus::fromOption(['label' => 'Up', 'title' => 'Since 2h']);
        self::assertNotNull($plain);
        self::assertSame('neutral', $plain->tone);
        self::assertSame('Since 2h', $plain->title);
        self::assertSame([], $plain->attributes);

        $odd = PageStatus::fromOption(['label' => 'Up', 'title' => 3, 'attributes' => 'data-x']);
        self::assertNotNull($odd);
        self::assertNull($odd->title);
        self::assertSame([], $odd->attributes);
    }

    /**
     * @return iterable<string, array{0: mixed}>
     */
    public static function otherTones(): iterable
    {
        yield 'unknown' => ['bogus'];
        yield 'primary (a badge slot, not a PageStatus tone)' => ['primary'];
        yield 'not a string' => [true];
        yield 'empty' => [''];
        yield 'null' => [null];
    }

    #[DataProvider('otherTones')]
    public function testToneOutsideTonesIsNeutral(mixed $tone): void
    {
        self::assertSame('neutral', PageStatus::fromOption(['label' => 'Up', 'tone' => $tone])?->tone);
    }

    /**
     * @return iterable<string, array{0: mixed}>
     */
    public static function notAStatus(): iterable
    {
        yield 'null' => [null];
        yield 'string (inherited from the context)' => ['up'];
        yield 'int' => [3];
        yield 'bool' => [true];
        yield 'object' => [new stdClass()];
        yield 'traversable' => [new ArrayObject(['label' => 'Up'])];
        yield 'hash without a label' => [['tone' => 'success']];
        yield 'empty label' => [['label' => '']];
        yield 'label not a string' => [['label' => 3]];
    }

    #[DataProvider('notAStatus')]
    public function testAnythingElseIsIgnored(mixed $value): void
    {
        self::assertNull(PageStatus::fromOption($value));
    }

    public function testEventHandlerAttributesStillThrow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PageStatus::fromOption(['label' => 'Up', 'attributes' => ['onclick' => 'x()']]);
    }
}
