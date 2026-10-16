<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

use Symfony\Component\Form\FormView;

/**
 * View model of `@NowoUiKit/page/auth.html.twig` (guest layout): brand, panel heading, form, footer links.
 * Bundles may override `nowo_ui_auth_fields` (default: REQ-TWIG-003 child loop), `nowo_ui_auth_actions` and
 * `nowo_ui_auth_footer`.
 */
final readonly class AuthPage
{
    /** @var list<Link> */
    public array $links;

    /**
     * @param iterable<Link>                            $links      footer links (forgot password, register, back to site…)
     * @param array<string, string|int|float|bool|null> $attributes extra attributes of the scaffold root (data-testid…; no on* handlers)
     */
    public function __construct(
        public Label|string $title,
        public ?FormView $form = null,
        public Label|string|null $intro = null,
        public Label|string|null $brand = null,
        public Label|string|null $error = null,
        public Label|string|null $submitLabel = null,
        iterable $links = [],
        public ?string $bundle = null,
        public ?string $layout = null,
        public array $attributes = [],
    ) {
        Action::assertAttributes($attributes);
        $this->links = ViewList::of($links, Link::class, 'AuthPage links');
    }
}
