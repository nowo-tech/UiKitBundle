<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Test;

/**
 * One page rendered by {@see PageContractTestCase}: a template name and the context a controller would pass
 * (usually `['page' => new ListPage(...)]`). Plain data so fixtures can be built in a static data provider.
 */
final readonly class PageFixture
{
    /**
     * @param string               $template Twig template name of the bundle page (e.g. `@NowoXxxBundle/admin/index.html.twig`)
     * @param array<string, mixed> $context  render context (view models, FormViews…)
     */
    public function __construct(
        public string $template,
        public array $context = [],
    ) {
    }
}
