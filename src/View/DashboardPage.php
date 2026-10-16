<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * View model of `@NowoUiKit/page/dashboard.html.twig`: header, KPI tiles, cards grid (`nowo_ui_dashboard_cards`).
 */
final readonly class DashboardPage
{
    /** @var list<KpiTile> */
    public array $kpis;

    /**
     * @param iterable<KpiTile>                         $kpis
     * @param array<string, string|int|float|bool|null> $attributes extra attributes of the scaffold root (data-testid…; no on* handlers)
     */
    public function __construct(
        public PageHeader $header,
        iterable $kpis = [],
        public ?string $bundle = null,
        public ?string $layout = null,
        public array $attributes = [],
    ) {
        Action::assertAttributes($attributes);
        $this->kpis = ViewList::of($kpis, KpiTile::class, 'DashboardPage kpis');
    }
}
