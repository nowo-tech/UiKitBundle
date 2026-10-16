<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Theme;

/**
 * The host theme (`nowo_ui_kit.theme`, REQ-UI-003): slot classes, slot attributes, partial overrides and
 * `--nowo-ui-*` tokens, with optional per-bundle exceptions. Immutable, request-independent (worker safe).
 *
 * Resolution of a slot class: semantic `nowo-ui-*` class + UiKit framework defaults (per `css_framework`) +
 * host classes; a bundle exception (`bundles.<Bundle>.classes.<slot>`) replaces the host classes of that slot.
 * Framework defaults can be dropped globally (`framework_defaults: false`), per bundle, or per slot (a class entry
 * `{value: '…', framework_defaults: false}`); the most specific setting wins (bundle slot > bundle > host slot >
 * host). Attributes: host attributes, bundle attributes merged over them. Partials: bundle > host > UiKit default.
 * Tokens: host tokens on `:root` (dark ones on `[data-theme="dark"]`); bundle tokens scoped to
 * `[data-nowo-ui-bundle="<Bundle>"]`.
 */
final readonly class Theme
{
    /** Attribute value: string/number, true = bare attribute, false/null = omitted. */
    private const ATTRIBUTE_NAME = '/^[a-zA-Z_:][a-zA-Z0-9_:.-]*$/';

    /** Keys of the map form of a class entry. */
    private const CLASS_ENTRY_KEYS = ['value', 'framework_defaults'];

    /** @var array<string, array{0: string, 1: bool|null}> slot => [host classes, framework defaults (null = inherit)] */
    private array $classes;

    /** @var array<string, array{classes: array<string, array{0: string, 1: bool|null}>, framework_defaults: bool|null, attributes: array<string, array<string, string|int|float|bool|null>>, partials: array<string, string>, tokens: array<string, string>, tokens_dark: array<string, string>}> */
    private array $bundles;

    /**
     * A class entry is a class string, a list of classes, or `{value: string|list, framework_defaults: bool}`.
     *
     * @param array<string, mixed>                                     $classes           slot => class entry
     * @param array<string, array<string, string|int|float|bool|null>> $attributes        slot => attributes
     * @param array<string, string>                                    $partials          partial name => template
     * @param array<string, string>                                    $tokens            token name (without --nowo-ui-) => value
     * @param array<string, string>                                    $tokensDark        dark-mode tokens
     * @param array<string, array<string, mixed>>                      $bundles           bundle => {classes, framework_defaults, attributes, partials, tokens, tokens_dark}
     * @param bool                                                     $frameworkDefaults false = semantic + host classes only, for every slot
     */
    public function __construct(
        private string $framework = 'bootstrap5',
        array $classes = [],
        private array $attributes = [],
        private array $partials = [],
        private array $tokens = [],
        private array $tokensDark = [],
        array $bundles = [],
        private bool $frameworkDefaults = true,
    ) {
        $this->classes = $this->normalizeClasses($classes, 'classes');
        $normalized = [];
        foreach ($bundles as $bundle => $data) {
            $this->assertBundleName((string) $bundle);
            $defaults = $data['framework_defaults'] ?? null;
            if (null !== $defaults && !\is_bool($defaults)) {
                throw new \InvalidArgumentException(\sprintf('Invalid UiKit theme bundles.%s.framework_defaults: a boolean is expected.', $bundle));
            }
            /** @var array<string, mixed> $bundleClasses */
            $bundleClasses = $data['classes'] ?? [];
            /** @var array<string, array<string, string|int|float|bool|null>> $bundleAttributes */
            $bundleAttributes = $data['attributes'] ?? [];
            /** @var array<string, string> $bundlePartials */
            $bundlePartials = $data['partials'] ?? [];
            /** @var array<string, string> $bundleTokens */
            $bundleTokens = $data['tokens'] ?? [];
            /** @var array<string, string> $bundleTokensDark */
            $bundleTokensDark = $data['tokens_dark'] ?? [];
            $normalized[(string) $bundle] = [
                'classes' => $this->normalizeClasses($bundleClasses, 'bundles.'.$bundle.'.classes'),
                'framework_defaults' => $defaults,
                'attributes' => $bundleAttributes,
                'partials' => $bundlePartials,
                'tokens' => $bundleTokens,
                'tokens_dark' => $bundleTokensDark,
            ];
        }
        $this->bundles = $normalized;
        foreach ([$attributes, ...array_column($normalized, 'attributes')] as $map) {
            foreach ($map as $slot => $attrs) {
                ThemeSlots::semantic($slot);
                foreach (array_keys($attrs) as $name) {
                    $this->assertAttributeName($name);
                }
            }
        }
        foreach ([$partials, ...array_column($normalized, 'partials')] as $map) {
            foreach (array_keys($map) as $name) {
                $this->assertPartialName($name);
            }
        }
        foreach ([$tokens, $tokensDark, ...array_map(static fn (array $b): array => array_merge($b['tokens'], $b['tokens_dark']), $normalized)] as $map) {
            foreach ($map as $name => $value) {
                $this->assertToken($name, $value);
            }
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
        /** @var array<string, mixed> $classes */
        $classes = $theme['classes'] ?? [];
        /** @var array<string, array<string, string|int|float|bool|null>> $attributes */
        $attributes = $theme['attributes'] ?? [];
        /** @var array<string, string> $partials */
        $partials = $theme['partials'] ?? [];
        /** @var array<string, string> $tokens */
        $tokens = $theme['tokens'] ?? [];
        /** @var array<string, string> $tokensDark */
        $tokensDark = $theme['tokens_dark'] ?? [];
        /** @var array<string, array<string, mixed>> $bundles */
        $bundles = $theme['bundles'] ?? [];

        return new self($framework, $classes, $attributes, $partials, $tokens, $tokensDark, $bundles, false !== ($theme['framework_defaults'] ?? true));
    }

    public function framework(): string
    {
        return $this->framework;
    }

    /**
     * Full class list of a slot: semantic + framework defaults (unless dropped for the slot) + host (or bundle)
     * classes, de-duplicated.
     *
     * @param string|null $framework css_framework override (partials that accept `framework`)
     */
    public function classFor(string $slot, ?string $bundle = null, ?string $framework = null): string
    {
        $framework = null !== $framework && '' !== trim($framework) ? trim($framework) : $this->framework;
        $defaults = $this->frameworkDefaults($slot, $bundle) ? ThemeSlots::defaults($slot, $framework) : '';

        return $this->merge(ThemeSlots::semantic($slot), $defaults, $this->extraClasses($slot, $bundle));
    }

    /**
     * Only the host / bundle classes of a slot ('' when none). Used by the 1.x partials, which keep their
     * macro classes and append these.
     */
    public function extraClasses(string $slot, ?string $bundle = null): string
    {
        ThemeSlots::semantic($slot);
        $bundleClasses = null !== $bundle ? ($this->bundles[$bundle]['classes'] ?? []) : [];

        return $this->merge(($bundleClasses[$slot] ?? $this->classes[$slot] ?? [''])[0]);
    }

    /**
     * Whether the UiKit framework defaults apply to a slot: bundle slot entry > bundle `framework_defaults` >
     * host slot entry > host `framework_defaults`.
     */
    public function frameworkDefaults(string $slot, ?string $bundle = null): bool
    {
        ThemeSlots::semantic($slot);
        $data = null !== $bundle ? ($this->bundles[$bundle] ?? null) : null;

        return $data['classes'][$slot][1] ?? $data['framework_defaults'] ?? $this->classes[$slot][1] ?? $this->frameworkDefaults;
    }

    /**
     * Classes of a slot printed by a 1.x partial from a `ui.*` macro: the macro classes + host / bundle classes
     * (exactly as 1.x when the framework defaults apply); when they are dropped for the slot, only the `nowo-ui-*`
     * and `is-*` classes of the macro are kept, plus the slot's semantic and host / bundle classes.
     */
    public function macroClasses(string $slot, string|\Stringable $macroClasses, ?string $bundle = null): string
    {
        $extra = $this->extraClasses($slot, $bundle);
        if ($this->frameworkDefaults($slot, $bundle)) {
            return $macroClasses.('' !== $extra ? ' '.$extra : '');
        }
        $kept = array_filter(preg_split('/\s+/', trim((string) $macroClasses)) ?: [], static fn (string $c): bool => str_starts_with($c, 'nowo-ui-') || str_starts_with($c, 'is-'));

        return $this->merge(ThemeSlots::semantic($slot), implode(' ', $kept), $extra);
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
            if ([] !== $bundle['tokens'] || [] !== $bundle['tokens_dark']) {
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
            $blocks[] = $this->block($scope, $data['tokens']);
            $blocks[] = $this->block('[data-theme="dark"] '.$scope, $data['tokens_dark']);
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
     * Error message for an invalid class entry, null when valid. An entry is a class string, a list of classes, or
     * `{value: string|list, framework_defaults: bool}`; class lists may not contain `"`, `<` or `>`.
     */
    public static function classEntryError(mixed $entry): ?string
    {
        if (\is_array($entry) && !array_is_list($entry)) {
            $unknown = array_diff(array_map(strval(...), array_keys($entry)), self::CLASS_ENTRY_KEYS);
            if ([] !== $unknown) {
                return \sprintf('unknown key(s) "%s" (use "value" and "framework_defaults").', implode('", "', $unknown));
            }
            if (isset($entry['framework_defaults']) && !\is_bool($entry['framework_defaults'])) {
                return '"framework_defaults" must be a boolean.';
            }
            $entry = $entry['value'] ?? '';
        }
        if (\is_array($entry)) {
            foreach ($entry as $class) {
                if (!\is_string($class)) {
                    return 'class lists are strings or lists of strings.';
                }
            }
            $entry = implode(' ', $entry);
        }
        if (!\is_string($entry) || 1 === preg_match('/["<>]/', $entry)) {
            return 'class lists are plain strings without \'"\', "<" or ">".';
        }

        return null;
    }

    /**
     * Normalises a valid class entry to [classes, framework defaults (null = inherit)].
     *
     * @return array{0: string, 1: bool|null}
     */
    public static function normalizeClassEntry(mixed $entry): array
    {
        $defaults = null;
        if (\is_array($entry) && !array_is_list($entry)) {
            $defaults = isset($entry['framework_defaults']) ? (bool) $entry['framework_defaults'] : null;
            $entry = $entry['value'] ?? '';
        }
        if (\is_array($entry)) {
            $entry = implode(' ', array_map(static fn (mixed $c): string => \is_scalar($c) ? (string) $c : '', $entry));
        }

        return [trim(\is_scalar($entry) ? (string) $entry : ''), $defaults];
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

    /**
     * @param array<string, mixed> $classes
     *
     * @return array<string, array{0: string, 1: bool|null}>
     */
    private function normalizeClasses(array $classes, string $path): array
    {
        $normalized = [];
        foreach ($classes as $slot => $entry) {
            ThemeSlots::semantic((string) $slot);
            $error = self::classEntryError($entry);
            if (null !== $error) {
                throw new \InvalidArgumentException(\sprintf('Invalid UiKit theme %s.%s: %s', $path, $slot, $error));
            }
            $normalized[(string) $slot] = self::normalizeClassEntry($entry);
        }

        return $normalized;
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
