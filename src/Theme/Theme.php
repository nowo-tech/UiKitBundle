<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Theme;

/**
 * The host theme (`nowo_ui_kit.theme`, REQ-UI-003): slot classes, slot attributes, partial overrides and
 * `--nowo-ui-*` tokens, with optional per-bundle exceptions. Immutable, request-independent (worker safe).
 *
 * Resolution of a slot class: semantic `nowo-ui-*` class + UiKit framework defaults (per `css_framework`) +
 * host classes; a bundle exception (`bundles.<Bundle>.classes.<slot>`) replaces the host classes of that slot.
 * Attributes: host attributes, bundle attributes merged over them. Partials: bundle > host > UiKit default.
 * Tokens: host tokens on `:root` (dark ones on `[data-theme="dark"]`); bundle tokens scoped to
 * `[data-nowo-ui-bundle="<Bundle>"]`.
 */
final readonly class Theme
{
    /** Attribute value: string/number, true = bare attribute, false/null = omitted. */
    private const ATTRIBUTE_NAME = '/^[a-zA-Z_:][a-zA-Z0-9_:.-]*$/';

    /**
     * @param array<string, string>                                                                                                                                                                                                               $classes    slot => host classes
     * @param array<string, array<string, string|int|float|bool|null>>                                                                                                                                                                            $attributes slot => attributes
     * @param array<string, string>                                                                                                                                                                                                               $partials   partial name => template
     * @param array<string, string>                                                                                                                                                                                                               $tokens     token name (without --nowo-ui-) => value
     * @param array<string, string>                                                                                                                                                                                                               $tokensDark dark-mode tokens
     * @param array<string, array{classes?: array<string, string>, attributes?: array<string, array<string, string|int|float|bool|null>>, partials?: array<string, string>, tokens?: array<string, string>, tokens_dark?: array<string, string>}> $bundles
     */
    public function __construct(
        private string $framework = 'bootstrap5',
        private array $classes = [],
        private array $attributes = [],
        private array $partials = [],
        private array $tokens = [],
        private array $tokensDark = [],
        private array $bundles = [],
    ) {
        foreach ([$classes, ...array_map(static fn (array $b): array => $b['classes'] ?? [], $bundles)] as $map) {
            foreach (array_keys($map) as $slot) {
                ThemeSlots::semantic($slot);
            }
        }
        foreach ([$attributes, ...array_map(static fn (array $b): array => $b['attributes'] ?? [], $bundles)] as $map) {
            foreach ($map as $slot => $attrs) {
                ThemeSlots::semantic($slot);
                foreach (array_keys($attrs) as $name) {
                    $this->assertAttributeName($name);
                }
            }
        }
        foreach ([$partials, ...array_map(static fn (array $b): array => $b['partials'] ?? [], $bundles)] as $map) {
            foreach (array_keys($map) as $name) {
                $this->assertPartialName($name);
            }
        }
        foreach ([$tokens, $tokensDark, ...array_map(static fn (array $b): array => array_merge($b['tokens'] ?? [], $b['tokens_dark'] ?? []), $bundles)] as $map) {
            foreach ($map as $name => $value) {
                $this->assertToken($name, $value);
            }
        }
        foreach (array_keys($bundles) as $bundle) {
            $this->assertBundleName($bundle);
        }
    }

    /**
     * Build from the processed `nowo_ui_kit.theme` config.
     *
     * @param array<string, mixed> $theme
     *
     * @return self Fluent interface
     */
    public static function fromConfig(string $framework, array $theme): self
    {
        /** @var array<string, string> $classes */
        $classes = $theme['classes'] ?? [];
        /** @var array<string, array<string, string|int|float|bool|null>> $attributes */
        $attributes = $theme['attributes'] ?? [];
        /** @var array<string, string> $partials */
        $partials = $theme['partials'] ?? [];
        /** @var array<string, string> $tokens */
        $tokens = $theme['tokens'] ?? [];
        /** @var array<string, string> $tokensDark */
        $tokensDark = $theme['tokens_dark'] ?? [];
        /** @var array<string, array{classes?: array<string, string>, attributes?: array<string, array<string, string|int|float|bool|null>>, partials?: array<string, string>, tokens?: array<string, string>, tokens_dark?: array<string, string>}> $bundles */
        $bundles = $theme['bundles'] ?? [];

        return new self($framework, $classes, $attributes, $partials, $tokens, $tokensDark, $bundles);
    }

    public function framework(): string
    {
        return $this->framework;
    }

    /**
     * Full class list of a slot: semantic + framework defaults + host (or bundle) classes, de-duplicated.
     *
     * @param string|null $framework css_framework override (partials that accept `framework`)
     */
    public function classFor(string $slot, ?string $bundle = null, ?string $framework = null): string
    {
        $framework = null !== $framework && '' !== trim($framework) ? trim($framework) : $this->framework;

        return $this->merge(ThemeSlots::semantic($slot), ThemeSlots::defaults($slot, $framework), $this->extraClasses($slot, $bundle));
    }

    /**
     * Only the host / bundle classes of a slot ('' when none). Used by the 1.x partials, which keep their
     * macro classes and append these.
     */
    public function extraClasses(string $slot, ?string $bundle = null): string
    {
        ThemeSlots::semantic($slot);
        $bundleClasses = null !== $bundle ? ($this->bundles[$bundle]['classes'] ?? []) : [];

        return $this->merge($bundleClasses[$slot] ?? $this->classes[$slot] ?? '');
    }

    /**
     * Slot attributes (host, then bundle merged over them).
     *
     * @param array<string, string|int|float|bool|null> $extra attributes merged last (call site)
     *
     * @return array<string, string|int|float|bool|null>
     */
    public function attributesFor(string $slot, ?string $bundle = null, array $extra = []): array
    {
        ThemeSlots::semantic($slot);
        $bundleAttributes = null !== $bundle ? ($this->bundles[$bundle]['attributes'] ?? []) : [];

        return array_merge($this->attributes[$slot] ?? [], $bundleAttributes[$slot] ?? [], $extra);
    }

    /**
     * Rendered, escaped attribute string with a leading space per attribute ('' when none).
     *
     * @param array<string, string|int|float|bool|null> $extra
     */
    public function renderAttributes(string $slot, ?string $bundle = null, array $extra = []): string
    {
        $html = '';
        foreach ($this->attributesFor($slot, $bundle, $extra) as $name => $value) {
            $this->assertAttributeName($name);
            if (null === $value || false === $value) {
                continue;
            }
            $html .= ' '.$name;
            if (true !== $value) {
                $html .= '="'.htmlspecialchars((string) $value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8').'"';
            }
        }

        return $html;
    }

    /**
     * Template of a partial: bundle override > host override > UiKit default.
     */
    public function partial(string $name, ?string $bundle = null): string
    {
        $this->assertPartialName($name);
        $bundlePartials = null !== $bundle ? ($this->bundles[$bundle]['partials'] ?? []) : [];

        return $bundlePartials[$name] ?? $this->partials[$name] ?? ThemeSlots::PARTIALS[$name];
    }

    public function hasTokens(): bool
    {
        if ([] !== $this->tokens || [] !== $this->tokensDark) {
            return true;
        }
        foreach ($this->bundles as $bundle) {
            if ([] !== ($bundle['tokens'] ?? []) || [] !== ($bundle['tokens_dark'] ?? [])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Token stylesheet text ('' without tokens): host `:root` + `[data-theme="dark"]` blocks, then the bundle
     * scopes. Values are validated at construction (no `{`, `}`, `;`, `<`, `>`).
     */
    public function tokensCss(): string
    {
        $blocks = [];
        $blocks[] = $this->block(':root', $this->tokens);
        $blocks[] = $this->block(':root[data-theme="dark"]', $this->tokensDark);
        foreach ($this->bundles as $bundle => $data) {
            $scope = '[data-nowo-ui-bundle="'.$bundle.'"]';
            $blocks[] = $this->block($scope, $data['tokens'] ?? []);
            $blocks[] = $this->block('[data-theme="dark"] '.$scope, $data['tokens_dark'] ?? []);
        }

        return implode("\n", array_filter($blocks, static fn (string $b): bool => '' !== $b));
    }

    /**
     * Normalises a token key: accepts `primary` or `--nowo-ui-primary`, returns `primary`.
     */
    public static function normalizeTokenName(string $name): string
    {
        return str_starts_with($name, '--nowo-ui-') ? substr($name, \strlen('--nowo-ui-')) : $name;
    }

    /**
     * Error message for an invalid token, null when valid.
     */
    public static function tokenError(string $name, mixed $value): ?string
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9-]*$/', self::normalizeTokenName($name))) {
            return \sprintf('token name "%s" must be lowercase letters, digits and "-" (written without or with the "--nowo-ui-" prefix).', $name);
        }
        if (!\is_string($value) && !\is_int($value) && !\is_float($value)) {
            return \sprintf('token "%s" must be a string or a number, %s given.', $name, get_debug_type($value));
        }
        $value = trim((string) $value);
        if ('' === $value || 1 === preg_match('/[{};<>\\\\]/', $value)) {
            return \sprintf('token "%s" has an empty or unsafe value ("{", "}", ";", "<", ">" and "\\" are not allowed).', $name);
        }

        return null;
    }

    /**
     * Error message for an invalid attribute name, null when valid (`on*` handlers are refused: CSP).
     */
    public static function attributeNameError(string $name): ?string
    {
        if (1 !== preg_match(self::ATTRIBUTE_NAME, $name)) {
            return \sprintf('"%s" is not a valid attribute name.', $name);
        }
        if (str_starts_with(strtolower($name), 'on')) {
            return \sprintf('inline event handler attribute "%s" is not allowed (CSP); use a Stimulus controller or data attribute.', $name);
        }

        return null;
    }

    /**
     * @param array<string, string> $tokens
     */
    private function block(string $selector, array $tokens): string
    {
        if ([] === $tokens) {
            return '';
        }
        $lines = [$selector.' {'];
        foreach ($tokens as $name => $value) {
            $lines[] = \sprintf('  --nowo-ui-%s: %s;', self::normalizeTokenName((string) $name), trim((string) $value));
        }
        $lines[] = '}';

        return implode("\n", $lines);
    }

    private function merge(string ...$parts): string
    {
        $tokens = [];
        foreach ($parts as $part) {
            foreach (preg_split('/\s+/', trim($part)) ?: [] as $class) {
                if ('' !== $class) {
                    $tokens[$class] = true;
                }
            }
        }

        return implode(' ', array_keys($tokens));
    }

    private function assertAttributeName(string $name): void
    {
        $error = self::attributeNameError($name);
        if (null !== $error) {
            throw new \InvalidArgumentException('Invalid UiKit theme attribute: '.$error);
        }
    }

    private function assertPartialName(string $name): void
    {
        if (!isset(ThemeSlots::PARTIALS[$name])) {
            throw new \InvalidArgumentException(\sprintf('Unknown UiKit theme partial "%s" (known: %s).', $name, implode(', ', ThemeSlots::partialNames())));
        }
    }

    private function assertToken(string $name, mixed $value): void
    {
        $error = self::tokenError($name, $value);
        if (null !== $error) {
            throw new \InvalidArgumentException('Invalid UiKit theme token: '.$error);
        }
    }

    private function assertBundleName(string $bundle): void
    {
        if (1 !== preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $bundle)) {
            throw new \InvalidArgumentException(\sprintf('Invalid UiKit theme bundle key "%s" (bundle class short name, e.g. NowoHttpLogBundle).', $bundle));
        }
    }
}
