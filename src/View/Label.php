<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * Translatable label (REQ-UI-003, REQ-I18N-003): translation key + explicit domain + parameters. Scaffolds and
 * partials translate it; bundles never pre-render strings into view models. A plain `string` wherever a
 * `Label|string` is accepted is printed as is (escaped) — use it for data such as a record name.
 */
final readonly class Label
{
    /**
     * @param array<string, string|int|float|\Stringable|null> $params
     */
    public function __construct(
        public string $key,
        public string $domain = 'messages',
        public array $params = [],
    ) {
        if ('' === trim($key)) {
            throw new \InvalidArgumentException('A Label needs a non-empty translation key.');
        }
    }

    /**
     * UiKit's own catalogue (`NowoUiKitBundle` domain).
     *
     * @param array<string, string|int|float|\Stringable|null> $params
     *
     * @return self Fluent interface
     */
    public static function kit(string $key, array $params = []): self
    {
        return new self($key, 'NowoUiKitBundle', $params);
    }

    /**
     * @param array<string, string|int|float|\Stringable|null> $params
     *
     * @return self Fluent interface
     */
    public function withParams(array $params): self
    {
        return new self($this->key, $this->domain, array_merge($this->params, $params));
    }
}
