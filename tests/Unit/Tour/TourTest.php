<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Tour;

use Nowo\UiKitBundle\Tour\Tour;
use Nowo\UiKitBundle\Tour\TourBuilder;
use Nowo\UiKitBundle\Tour\TourStep;
use Nowo\UiKitBundle\View\Label;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 2.13: guided tour model (Tour, TourStep, TourBuilder).
 */
final class TourTest extends TestCase
{
    public function testStepDefaultsAndAnchor(): void
    {
        $step = new TourStep(null, 'Welcome');
        self::assertNull($step->selector);
        self::assertSame('Welcome', $step->title);
        self::assertSame('', $step->description);
        self::assertNull($step->side);
        self::assertNull($step->align);

        $step = new TourStep(TourStep::anchor('user-menu'), new Label('t'), new Label('d'), 'left', 'end');
        self::assertSame('[data-tour="user-menu"]', $step->selector);
        self::assertSame('left', $step->side);
        self::assertSame('end', $step->align);
        self::assertSame('[data-tour="admin.users:list_2"]', TourStep::anchor('admin.users:list_2'));
    }

    /**
     * @return iterable<string, array{0: \Closure(): mixed, 1: string}>
     */
    public static function invalidProvider(): iterable
    {
        yield 'blank selector' => [static fn (): TourStep => new TourStep(' ', 'T'), 'non-empty CSS selector'];
        yield 'side' => [static fn (): TourStep => new TourStep(null, 'T', '', 'middle'), 'Invalid TourStep side "middle"'];
        yield 'align' => [static fn (): TourStep => new TourStep(null, 'T', '', null, 'top'), 'Invalid TourStep align "top"'];
        yield 'anchor' => [static fn (): string => TourStep::anchor('a"]'), 'Invalid tour anchor name'];
        yield 'key' => [static fn (): Tour => new Tour('a b'), 'Invalid Tour key "a b"'];
        yield 'empty key' => [static fn (): Tour => new Tour(''), 'Invalid Tour key ""'];
    }

    /**
     * @param \Closure(): mixed $factory
     */
    #[DataProvider('invalidProvider')]
    public function testInvalidInputThrows(\Closure $factory, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        $factory();
    }

    public function testTour(): void
    {
        $tour = new Tour('dashboard');
        self::assertTrue($tour->isEmpty());
        self::assertTrue($tour->autoStart);

        $tour = new Tour('project_issues', [new TourStep(null, 'T')], false);
        self::assertFalse($tour->isEmpty());
        self::assertFalse($tour->autoStart);
    }

    public function testBuilderIsImmutableAndBuildsLabels(): void
    {
        $base = TourBuilder::create('dashboard', 'app', 'tour.steps.dashboard.')->welcome('welcome', ['%name%' => 'Ada']);
        $more = $base->step(TourStep::anchor('sidebar'), 'sidebar', 'right', 'start')->add(new TourStep('#x', 'Plain', 'Text'));

        self::assertCount(1, $base->build()->steps);
        $tour = $more->build();
        self::assertSame('dashboard', $tour->key);
        self::assertTrue($tour->autoStart);
        self::assertCount(3, $tour->steps);

        [$welcome, $sidebar, $plain] = $tour->steps;
        self::assertNull($welcome->selector);
        self::assertEquals(new Label('tour.steps.dashboard.welcome.title', 'app'), $welcome->title);
        self::assertEquals(new Label('tour.steps.dashboard.welcome.description', 'app', ['%name%' => 'Ada']), $welcome->description);
        self::assertSame('[data-tour="sidebar"]', $sidebar->selector);
        self::assertSame(['right', 'start'], [$sidebar->side, $sidebar->align]);
        self::assertEquals(new Label('tour.steps.dashboard.sidebar.title', 'app'), $sidebar->title);
        self::assertSame('Plain', $plain->title);

        $manual = TourBuilder::create('account', autoStart: false)->step(null, 'tour.account.intro')->build();
        self::assertFalse($manual->autoStart);
        self::assertEquals(new Label('tour.account.intro.title'), $manual->steps[0]->title);
    }
}
