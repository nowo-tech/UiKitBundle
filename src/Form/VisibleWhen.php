<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Form;

use InvalidArgumentException;

/**
 * Contract of the `visible-when` Stimulus peer (2.23): a form row shown only while another field (the "provider")
 * holds one of some values. The peer hides non-matching rows (`hidden`, `inert`, `aria-hidden`) and never disables
 * or clears an input, so hidden fields are still submitted and stored values survive; without JavaScript every row is
 * visible. Use the attributes as a row's `row_attr` (the UiKit form theme and Symfony's form_div_layout render it) and
 * put `data-controller="visible-when"` ({@see self::containerAttributes()}) on the form:
 *
 *     $builder->add('smtp_host', TextType::class, [
 *         'row_attr' => VisibleWhen::attributes('settings[provider]', ['smtp']),
 *     ]);
 *
 * `field` is the controlling input's `name` attribute (full name, e.g. `settings[provider]`) or its id. In Twig:
 * `nowo_ui_visible_when(field, values)` returns the same map ({@see self::attributes()}).
 */
final class VisibleWhen
{
    public const CONTROLLER = 'visible-when';

    public const FIELD_ATTRIBUTE = 'data-nowo-ui-visible-when-field';

    public const VALUES_ATTRIBUTE = 'data-nowo-ui-visible-when-in';

    /**
     * Row attributes: the controlling field and the values (JSON list) the row is visible for.
     *
     * @param iterable<mixed> $values strings, integers or booleans (true → "1", false → "")
     *
     * @return array{data-nowo-ui-visible-when-field: string, data-nowo-ui-visible-when-in: string}
     */
    public static function attributes(string $field, iterable $values): array
    {
        if ('' === trim($field) || \strlen($field) > 255 || 1 === preg_match('/[\x00-\x1F\x7F"]/', $field)) {
            throw new InvalidArgumentException(\sprintf('Invalid visible_when field "%s": the name (or id) of the controlling input.', $field));
        }
        $list = [];
        foreach ($values as $value) {
            if (!\is_scalar($value)) {
                throw new InvalidArgumentException('visible_when values must be strings, integers or booleans.');
            }
            $list[] = \is_bool($value) ? ($value ? '1' : '') : (string) $value;
        }

        return [
            self::FIELD_ATTRIBUTE => $field,
            self::VALUES_ATTRIBUTE => json_encode(array_values(array_unique($list)), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * Attributes of a `visible_when` option `{field: string, in: list<scalar>}` (the InstanceSettings shape); `null`
     * gives none.
     *
     * @param array{field?: mixed, in?: mixed}|null $option
     *
     * @return array<string, string>
     */
    public static function fromOption(?array $option): array
    {
        if (null === $option) {
            return [];
        }
        if (!isset($option['field']) || !\is_string($option['field']) || !isset($option['in']) || !is_iterable($option['in'])) {
            throw new InvalidArgumentException('A visible_when option is {field: string, in: list<string|int|bool>}.');
        }

        return self::attributes($option['field'], $option['in']);
    }

    /**
     * `row_attr` with the visible-when attributes merged in (keeps the other keys, e.g. `class`).
     *
     * @param array<string, mixed> $rowAttr
     * @param iterable<mixed>      $values
     *
     * @return array<string, mixed>
     */
    public static function rowAttr(array $rowAttr, string $field, iterable $values): array
    {
        return array_merge($rowAttr, self::attributes($field, $values));
    }

    /**
     * Attributes of the container (the form) that connects the peer.
     *
     * @return array{data-controller: string}
     */
    public static function containerAttributes(): array
    {
        return ['data-controller' => self::CONTROLLER];
    }
}
