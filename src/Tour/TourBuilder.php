<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tour;

use Nowo\UiKitBundle\View\Label;

/**
 * Immutable builder of a {@see Tour} (2.13) from translation keys: a step key `k` gives the labels `<prefix>k.title`
 * and `<prefix>k.description` in `domain`. Every call returns a new builder, so a host can branch on permissions:
 *
 *     $tour = TourBuilder::create('dashboard', prefix: 'tour.steps.dashboard.')
 *         ->welcome('welcome')
 *         ->step(TourStep::anchor('sidebar'), 'sidebar', 'right', 'start');
 *     if ($canCreate) {
 *         $tour = $tour->step(TourStep::anchor('new-project'), 'new_project', 'bottom', 'end');
 *     }
 *     return $this->render('dashboard.html.twig', ['tour' => $tour->build()]);
 */
final readonly class TourBuilder
{
    /**
     * @param list<TourStep> $steps
     */
    private function __construct(
        private string $key,
        private string $domain,
        private string $prefix,
        private bool $autoStart,
        private array $steps,
    ) {
    }

    /**
     * @param string $domain translation domain of the step labels
     * @param string $prefix prepended to every step key (e.g. `tour.steps.dashboard.`)
     *
     * @return self Fluent interface
     */
    public static function create(string $key, string $domain = 'messages', string $prefix = '', bool $autoStart = true): self
    {
        return new self($key, $domain, $prefix, $autoStart, []);
    }

    /**
     * A centred step (no highlighted element), e.g. the welcome popover.
     *
     * @param array<string, string|int|float|\Stringable|null> $params description parameters
     *
     * @return self Fluent interface
     */
    public function welcome(string $key, array $params = []): self
    {
        return $this->step(null, $key, null, null, $params);
    }

    /**
     * A step anchored to `selector` (null = centred).
     *
     * @param array<string, string|int|float|\Stringable|null> $params description parameters
     *
     * @return self Fluent interface
     */
    public function step(?string $selector, string $key, ?string $side = null, ?string $align = null, array $params = []): self
    {
        $id = $this->prefix.$key;

        return $this->add(new TourStep(
            $selector,
            new Label($id.'.title', $this->domain),
            new Label($id.'.description', $this->domain, $params),
            $side,
            $align,
        ));
    }

    /**
     * Appends a ready-made step (own labels or plain strings).
     *
     * @return self Fluent interface
     */
    public function add(TourStep $step): self
    {
        return new self($this->key, $this->domain, $this->prefix, $this->autoStart, [...$this->steps, $step]);
    }

    public function build(): Tour
    {
        return new Tour($this->key, $this->steps, $this->autoStart);
    }
}
