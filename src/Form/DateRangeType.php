<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Date-range filter field (2.15): one labelled field ("Collection date") for a from / to pair of days, rendered by the
 * `nowo_ui_date_range_widget` block (`form/nowo_ui_date_range.html.twig`, used by `form/nowo_ui_layout.html.twig` and
 * added to `twig.form_themes`). Without JavaScript it is two native `<input type="date">` with visible short labels
 * ("From" / "To"); the `date-range` Stimulus peer turns it into one control of the filter grid that opens a range
 * calendar (Cally, imported on demand), shows the chosen range in the viewer's locale and has a clear button.
 *
 * The two days are submitted as two plain parameters whose names the host chooses (`from_name` / `to_name`, default
 * `<name>_from` / `<name>_to`, siblings of the field: `created_from=2026-01-01&created_to=2026-01-31` in a rootless GET
 * filter form, `filter[created_from]` under a `filter` root), so existing query names keep working; the field's
 * data is `['from' => ?string, 'to' => ?string]` (`Y-m-d`), as the children are `DateType` single text with string
 * input. {@see DateRangeSubmitExtension} maps the two parameters onto the field when its parent is submitted.
 *
 * Options:
 *   - `from_name` / `to_name`: submitted names of the two days (letters, digits, `_`, `-`, `.`, `:`; default
 *     `<name>_from` / `<name>_to`)
 *   - `placeholder`: text of the empty control and accessible name of the group (translated with the field's
 *     `translation_domain`; default `attr.placeholder`, then the label, then the humanized name)
 *   - `from_label` / `to_label`: the short labels (already translated; default `date_range.from` / `date_range.to`
 *     of the NowoUiKitBundle domain)
 *   - `from_options` / `to_options`: extra `DateType` options of each day (e.g. `attr`, `constraints`)
 *   - `picker`: `true` (default) | `false` (two native inputs, no peer) | an array with `controller` (Stimulus
 *     identifier, default `date-range`), `months` (`0` auto: two from 40rem, else one; `1`, `2`, `3`),
 *     `first_day_of_week` (`0` Sunday … `6`, default `1`), `locale` (BCP 47 / Symfony locale; default the request
 *     locale in the template) and `labels` (`clear`, `previous`, `next`, `done` overrides, already translated)
 *
 * @extends AbstractType<array{from: string|null, to: string|null}>
 */
final class DateRangeType extends AbstractType
{
    public const BLOCK_PREFIX = 'nowo_ui_date_range';

    public const DEFAULT_CONTROLLER = 'date-range';

    /** Form attribute holding the submitted names `[from, to]` ({@see DateRangeSubmitExtension}). */
    public const NAMES_ATTRIBUTE = 'nowo_ui_date_range_names';

    public const FROM = 'from';

    public const TO = 'to';

    private const PICKER_KEYS = ['controller', 'months', 'first_day_of_week', 'locale', 'labels'];

    private const PICKER_LABELS = ['clear', 'previous', 'next', 'done'];

    private const NAME_PATTERN = '/^[A-Za-z0-9_.:-]+$/';

    public function __construct(private readonly ?TranslatorInterface $translator = null)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $name = $builder->getName();
        /** @var string|null $fromName */
        $fromName = $options['from_name'];
        /** @var string|null $toName */
        $toName = $options['to_name'];
        $fromName ??= $name.'_'.self::FROM;
        $toName ??= $name.'_'.self::TO;
        if ($fromName === $toName) {
            throw new InvalidOptionsException(\sprintf('The "from_name" and "to_name" of the date range "%s" must differ.', $name));
        }
        $builder->setAttribute(self::NAMES_ATTRIBUTE, [$fromName, $toName]);

        foreach ([self::FROM => 'from_options', self::TO => 'to_options'] as $child => $optionName) {
            /** @var array<string, mixed> $extra */
            $extra = $options[$optionName];
            $builder->add($child, DateType::class, array_replace([
                'widget' => 'single_text',
                'input' => 'string',
                'html5' => true,
                'required' => false,
                'label' => false,
                'error_bubbling' => true,
            ], $extra));
        }
        // An empty day is null in the field's data (DateType string input yields '' for an empty submission).
        $builder->addModelTransformer(new CallbackTransformer(
            static fn (mixed $value): mixed => $value,
            static fn (mixed $value): array => [
                self::FROM => self::day($value, self::FROM),
                self::TO => self::day($value, self::TO),
            ],
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => true,
            'data_class' => null,
            'required' => false,
            'error_bubbling' => false,
            'from_name' => null,
            'to_name' => null,
            'placeholder' => null,
            'from_label' => null,
            'to_label' => null,
            'from_options' => [],
            'to_options' => [],
            'picker' => true,
        ]);
        $resolver->setAllowedTypes('from_name', ['null', 'string']);
        $resolver->setAllowedTypes('to_name', ['null', 'string']);
        $resolver->setAllowedTypes('placeholder', ['null', 'string']);
        $resolver->setAllowedTypes('from_label', ['null', 'string']);
        $resolver->setAllowedTypes('to_label', ['null', 'string']);
        $resolver->setAllowedTypes('from_options', 'array');
        $resolver->setAllowedTypes('to_options', 'array');
        $resolver->setAllowedTypes('picker', ['bool', 'array']);
        foreach (['from_name', 'to_name'] as $option) {
            $resolver->setAllowedValues($option, static fn (?string $value): bool => null === $value || 1 === preg_match(self::NAME_PATTERN, $value));
        }
        $resolver->setNormalizer('picker', static fn (Options $options, bool|array $value): array|false => self::normalizePicker($value));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        /** @var array{controller: string, months: int, first_day_of_week: int, locale: string|null, labels: array<string, string>}|false $picker */
        $picker = $options['picker'];
        /** @var array{0: string, 1: string} $names */
        $names = $form->getConfig()->getAttribute(self::NAMES_ATTRIBUTE);
        [$placeholder, $translate] = $this->placeholder($view, $options);
        /** @var string|null $fromLabel */
        $fromLabel = $options['from_label'];
        /** @var string|null $toLabel */
        $toLabel = $options['to_label'];
        $labels = [
            self::FROM => $fromLabel ?? $this->trans('date_range.from', 'From'),
            self::TO => $toLabel ?? $this->trans('date_range.to', 'To'),
            'clear' => $this->trans('date_range.clear', 'Clear'),
            'previous' => $this->trans('date_range.previous', 'Previous month'),
            'next' => $this->trans('date_range.next', 'Next month'),
            'done' => $this->trans('date_range.done', 'Done'),
        ];

        /** @var array<string, mixed> $attr */
        $attr = $view->vars['attr'] ?? [];
        unset($attr['placeholder']);
        $attr['class'] = $this->mergeTokens($attr['class'] ?? null, 'nowo-ui-date-range');
        $controller = null;
        if (false !== $picker) {
            $controller = $picker['controller'];
            $labels = $picker['labels'] + $labels;
            $attr['data-controller'] = $this->mergeTokens($attr['data-controller'] ?? null, $controller);
            $attr['data-'.$controller.'-labels-value'] = $this->json($labels);
            if (0 !== $picker['months']) {
                $attr['data-'.$controller.'-months-value'] = (string) $picker['months'];
            }
            if (1 !== $picker['first_day_of_week']) {
                $attr['data-'.$controller.'-first-day-of-week-value'] = (string) $picker['first_day_of_week'];
            }
            if (null !== $picker['locale']) {
                $attr['data-'.$controller.'-locale-value'] = $picker['locale'];
            }
        }
        $view->vars['attr'] = $attr;
        $view->vars[self::BLOCK_PREFIX] = [
            'controller' => $controller,
            'placeholder' => $placeholder,
            'translate' => $translate,
            'labels' => $labels,
            'from_name' => $names[0],
            'to_name' => $names[1],
        ];
    }

    /**
     * The two days are submitted under the host's names, siblings of the field (not `field[from]`).
     *
     * @param array<string, mixed> $options
     */
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        /** @var array{0: string, 1: string} $names */
        $names = $form->getConfig()->getAttribute(self::NAMES_ATTRIBUTE);
        $parent = $view->parent?->vars['full_name'] ?? '';
        $parent = \is_string($parent) ? $parent : '';
        foreach ([self::FROM => $names[0], self::TO => $names[1]] as $child => $name) {
            if (isset($view->children[$child])) {
                $view->children[$child]->vars['full_name'] = '' === $parent ? $name : $parent.'['.$name.']';
            }
        }
    }

    public function getBlockPrefix(): string
    {
        return self::BLOCK_PREFIX;
    }

    /**
     * Validated `picker` option: `false`, or the full array (defaults filled in).
     *
     * @param bool|array<mixed> $value
     *
     * @return array{controller: string, months: int, first_day_of_week: int, locale: string|null, labels: array<string, string>}|false
     */
    public static function normalizePicker(bool|array $value): array|false
    {
        if (false === $value) {
            return false;
        }
        $value = true === $value ? [] : $value;
        $unknown = array_diff(array_map(strval(...), array_keys($value)), self::PICKER_KEYS);
        if ([] !== $unknown) {
            throw new InvalidOptionsException(\sprintf('Unknown date range "picker" key(s) "%s" (%s).', implode('", "', $unknown), implode(', ', self::PICKER_KEYS)));
        }
        $controller = $value['controller'] ?? self::DEFAULT_CONTROLLER;
        if (!\is_string($controller) || 1 !== preg_match('/^[a-z0-9]+(?:-{1,2}[a-z0-9]+)*$/', $controller)) {
            throw new InvalidOptionsException('The date range "picker.controller" must be a Stimulus identifier (e.g. "date-range").');
        }
        $months = $value['months'] ?? 0;
        if (!\is_int($months) || $months < 0 || $months > 3) {
            throw new InvalidOptionsException('The date range "picker.months" must be 0 (auto), 1, 2 or 3.');
        }
        $firstDay = $value['first_day_of_week'] ?? 1;
        if (!\is_int($firstDay) || $firstDay < 0 || $firstDay > 6) {
            throw new InvalidOptionsException('The date range "picker.first_day_of_week" must be 0 (Sunday) to 6.');
        }
        $locale = $value['locale'] ?? null;
        if (null !== $locale && (!\is_string($locale) || 1 !== preg_match('/^[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*$/', $locale))) {
            throw new InvalidOptionsException('The date range "picker.locale" must be a locale such as "es" or "en_GB".');
        }
        $labels = $value['labels'] ?? [];
        if (!\is_array($labels) || [] !== array_diff(array_keys($labels), self::PICKER_LABELS) || [] !== array_filter($labels, static fn (mixed $label): bool => !\is_string($label))) {
            throw new InvalidOptionsException(\sprintf('The date range "picker.labels" must be strings keyed %s.', implode(' / ', self::PICKER_LABELS)));
        }

        /* @var array<string, string> $labels */
        return ['controller' => $controller, 'months' => $months, 'first_day_of_week' => $firstDay, 'locale' => $locale, 'labels' => $labels];
    }

    /**
     * Text of the empty control and whether the template translates it: `placeholder`, `attr.placeholder`, the
     * label (all translated) or the humanized name (as is).
     *
     * @param array<string, mixed> $options
     *
     * @return array{0: string, 1: bool}
     */
    private function placeholder(FormView $view, array $options): array
    {
        /** @var array<string, mixed> $attr */
        $attr = $view->vars['attr'] ?? [];
        foreach ([$options['placeholder'], $attr['placeholder'] ?? null, $options['label'] ?? null] as $candidate) {
            if (\is_string($candidate) && '' !== trim($candidate)) {
                return [$candidate, true];
            }
        }
        $name = \is_string($view->vars['name'] ?? null) ? $view->vars['name'] : '';

        return [ucfirst(strtolower(trim((string) preg_replace(['/([A-Z])/', '/[_\s]+/'], ['_$1', ' '], $name)))), false];
    }

    private static function day(mixed $value, string $key): ?string
    {
        $day = \is_array($value) ? ($value[$key] ?? null) : null;

        return \is_string($day) && '' !== $day ? $day : null;
    }

    private function trans(string $id, string $fallback): string
    {
        return $this->translator?->trans($id, [], 'NowoUiKitBundle') ?? $fallback;
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
     * @param array<string, string> $data
     */
    private function json(array $data): string
    {
        return json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }
}
