<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * View model of `@NowoUiKit/page/settings.html.twig`: header, section tabs (when sections have hrefs) and one
 * form per section. With `current` set only that section renders; otherwise every section renders stacked.
 * Bundles may override `nowo_ui_settings_section` (`section` in context).
 */
final readonly class SettingsPage
{
    /** @var list<SettingsSection> */
    public array $sections;

    /**
     * @param iterable<SettingsSection>                 $sections
     * @param array<string, string|int|float|bool|null> $attributes extra attributes of the scaffold root (data-testid…; no on* handlers)
     */
    public function __construct(
        public PageHeader $header,
        iterable $sections = [],
        public ?string $current = null,
        public ?string $bundle = null,
        public ?string $layout = null,
        public array $attributes = [],
    ) {
        Action::assertAttributes($attributes);
        $this->sections = ViewList::of($sections, SettingsSection::class, 'SettingsPage sections');
        if (null !== $current && [] === array_filter($this->sections, static fn (SettingsSection $s): bool => $s->id === $current)) {
            throw new \InvalidArgumentException(\sprintf('SettingsPage current section "%s" is not one of its sections.', $current));
        }
    }

    /**
     * Sections to render (only the current one when set).
     *
     * @return list<SettingsSection>
     */
    public function visibleSections(): array
    {
        if (null === $this->current) {
            return $this->sections;
        }

        return array_values(array_filter($this->sections, fn (SettingsSection $s): bool => $s->id === $this->current));
    }

    /**
     * Whether the section tabs render (more than one section, all with an href).
     */
    public function hasTabs(): bool
    {
        return \count($this->sections) > 1 && [] === array_filter($this->sections, static fn (SettingsSection $s): bool => null === $s->href);
    }
}
