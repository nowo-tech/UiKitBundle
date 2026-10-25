<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Form;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * ChoiceType option `nowo_ui_multi_select` (2.14): the collapsed `<select>` of a choice field (and of every type built
 * on it: EntityType, EnumType, …) gets the markup of the `multi-select` Stimulus peer (Tom Select chips, search,
 * remove buttons; the native select without JavaScript). Meant for `multiple: true` filters (GET `name[]=…`); a
 * single collapsed choice works too (searchable single select). Expanded choices (checkboxes / radios) are left as
 * they are.
 *
 * Values: `false` (default) | `true` | an array with
 *   - `controller`: Stimulus identifier the host registered the peer under (default `multi-select`)
 *   - `options`: Tom Select settings merged over the peer's defaults (JSON-serialisable, e.g. `['maxItems' => 3]`)
 *   - `labels`: `remove` / `noResults` overrides (already translated)
 *   - `dropdown_parent`: `auto` (default: <body>, inline inside a dialog) | `body` | `inline`
 *
 * The select gets the class `nowo-ui-multi-select`, the controller in `data-controller` (merged with the field's
 * own) and the peer's values (`labels`, translated from `multi_select.*` in the NowoUiKitBundle domain, `options`,
 * `dropdownParent`). The text of the empty control is the field's `attr.placeholder` (translated by the form theme
 * as usual). Only registered when symfony/form is installed.
 */
final class MultiSelectTypeExtension extends AbstractTypeExtension
{
    public const OPTION = 'nowo_ui_multi_select';

    public const DEFAULT_CONTROLLER = 'multi-select';

    private const KEYS = ['controller', 'options', 'labels', 'dropdown_parent'];

    private const DROPDOWN_PARENTS = ['auto', 'body', 'inline'];

    public function __construct(private readonly ?TranslatorInterface $translator = null)
    {
    }

    public static function getExtendedTypes(): iterable
    {
        return [ChoiceType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault(self::OPTION, false);
        $resolver->setAllowedTypes(self::OPTION, ['bool', 'array']);
        $resolver->setNormalizer(self::OPTION, static fn (Options $options, bool|array $value): array|false => self::normalize($value));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        /** @var array{controller: string, options: array<string, mixed>, labels: array<string, string>, dropdown_parent: string}|false $config */
        $config = $options[self::OPTION];
        if (false === $config || true === ($options['expanded'] ?? false)) {
            return;
        }
        $controller = $config['controller'];

        /** @var array<string, mixed> $attr */
        $attr = $view->vars['attr'] ?? [];
        $attr['class'] = $this->mergeTokens($attr['class'] ?? null, 'nowo-ui-multi-select');
        $attr['data-controller'] = $this->mergeTokens($attr['data-controller'] ?? null, $controller);
        $attr['data-'.$controller.'-labels-value'] = $this->json($config['labels'] + $this->labels());
        if ([] !== $config['options']) {
            $attr['data-'.$controller.'-options-value'] = $this->json($config['options']);
        }
        if ('auto' !== $config['dropdown_parent']) {
            $attr['data-'.$controller.'-dropdown-parent-value'] = $config['dropdown_parent'];
        }
        $view->vars['attr'] = $attr;
        $view->vars[self::OPTION] = true;
    }

    /**
     * Validated option value: `false`, or the full array (defaults filled in).
     *
     * @param bool|array<mixed> $value
     *
     * @return array{controller: string, options: array<string, mixed>, labels: array<string, string>, dropdown_parent: string}|false
     */
    public static function normalize(bool|array $value): array|false
    {
        if (false === $value) {
            return false;
        }
        $value = true === $value ? [] : $value;
        $unknown = array_diff(array_map(strval(...), array_keys($value)), self::KEYS);
        if ([] !== $unknown) {
            throw new InvalidOptionsException(\sprintf('Unknown "%s" key(s) "%s" (%s).', self::OPTION, implode('", "', $unknown), implode(', ', self::KEYS)));
        }

        $controller = $value['controller'] ?? self::DEFAULT_CONTROLLER;
        if (!\is_string($controller) || 1 !== preg_match('/^[a-z0-9]+(?:-{1,2}[a-z0-9]+)*$/', $controller)) {
            throw new InvalidOptionsException(\sprintf('"%s" controller must be a Stimulus identifier (e.g. "multi-select").', self::OPTION));
        }
        $settings = $value['options'] ?? [];
        if (!\is_array($settings) || ([] !== $settings && array_is_list($settings))) {
            throw new InvalidOptionsException(\sprintf('"%s" options must be a map of Tom Select settings.', self::OPTION));
        }
        $labels = $value['labels'] ?? [];
        if (!\is_array($labels) || [] !== array_diff(array_keys($labels), ['remove', 'noResults']) || [] !== array_filter($labels, static fn (mixed $label): bool => !\is_string($label))) {
            throw new InvalidOptionsException(\sprintf('"%s" labels must be strings keyed "remove" / "noResults".', self::OPTION));
        }
        $dropdownParent = $value['dropdown_parent'] ?? 'auto';
        if (!\in_array($dropdownParent, self::DROPDOWN_PARENTS, true)) {
            throw new InvalidOptionsException(\sprintf('"%s" dropdown_parent must be one of %s.', self::OPTION, implode(', ', self::DROPDOWN_PARENTS)));
        }

        /* @var array<string, mixed> $settings */
        /* @var array<string, string> $labels */
        return ['controller' => $controller, 'options' => $settings, 'labels' => $labels, 'dropdown_parent' => $dropdownParent];
    }

    /**
     * @return array{remove: string, noResults: string}
     */
    private function labels(): array
    {
        return [
            'remove' => $this->translator?->trans('multi_select.remove', [], 'NowoUiKitBundle') ?? 'Remove',
            'noResults' => $this->translator?->trans('multi_select.no_results', [], 'NowoUiKitBundle') ?? 'No results',
        ];
    }

    private function mergeTokens(mixed $current, string $token): string
    {
        $tokens = preg_split('/\s+/', \is_scalar($current) ? trim((string) $current) : '', -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        if (!\in_array($token, $tokens, true)) {
            $tokens[] = $token;
        }

        return implode(' ', $tokens);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function json(array $data): string
    {
        return json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }
}
