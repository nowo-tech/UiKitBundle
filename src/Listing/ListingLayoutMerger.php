<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Listing;

/**
 * Merges stored layouts with the current definition, and validates submitted layouts.
 *
 * Stored layouts (a cookie, a host table) are read leniently: old preferences stay valid after a release adds or
 * removes fields, and a tampered cookie never breaks the page. Submitted layouts (a host save endpoint) are validated
 * strictly. Entry shape: `{"key": "<field key>", "visible": true|false, "merged": true}` (`merged` only on columns,
 * only when set).
 */
final class ListingLayoutMerger
{
    /**
     * Unknown, malformed and duplicate entries are dropped, fields missing from `$stored` are appended in definition
     * order with their default visibility, required fields are always visible. A null `$stored` yields the
     * definition defaults.
     *
     * @param list<ListingField> $fields
     * @param array<mixed>|null  $stored
     *
     * @return list<ListingFieldState>
     */
    public static function merge(array $fields, ?array $stored): array
    {
        $byKey = self::byKey($fields);

        $states = [];
        foreach ($stored ?? [] as $entry) {
            if (!\is_array($entry) || !\is_string($entry['key'] ?? null)) {
                continue;
            }
            $field = $byKey[$entry['key']] ?? null;
            if (!$field instanceof ListingField) {
                continue;
            }
            unset($byKey[$entry['key']]);
            $states[] = new ListingFieldState(
                $field,
                $field->required || true === ($entry['visible'] ?? null),
                true === ($entry['merged'] ?? null),
            );
        }

        foreach ($byKey as $field) {
            $states[] = new ListingFieldState($field, $field->isVisibleByDefault());
        }

        return $states;
    }

    /**
     * Validates a submitted field list (`[{key, visible, merged?}]`); returns the storable form or null when an entry
     * is malformed, unknown or duplicated. Required fields are forced visible; `merged` is only kept when true.
     *
     * @param list<ListingField> $fields
     *
     * @return list<array{key: string, visible: bool, merged?: true}>|null
     */
    public static function normalize(array $fields, mixed $submitted): ?array
    {
        if (!\is_array($submitted) || !array_is_list($submitted)) {
            return null;
        }

        $byKey = self::byKey($fields);
        $normalized = [];
        $seen = [];
        foreach ($submitted as $entry) {
            if (!\is_array($entry)) {
                return null;
            }
            $key = $entry['key'] ?? null;
            $visible = $entry['visible'] ?? null;
            $merged = $entry['merged'] ?? false;
            if (!\is_string($key) || !\is_bool($visible) || !\is_bool($merged) || !isset($byKey[$key]) || isset($seen[$key])) {
                return null;
            }
            $seen[$key] = true;
            $item = ['key' => $key, 'visible' => $byKey[$key]->required || $visible];
            if ($merged) {
                $item['merged'] = true;
            }
            $normalized[] = $item;
        }

        return $normalized;
    }

    /**
     * Validates a whole submitted layout (`{"columns": […], "filters": […]}`, what the `listing-customizer` peer
     * sends to a host `save_url`) against a definition: the storable form, or null when either list is invalid.
     *
     * @return array{columns: list<array{key: string, visible: bool, merged?: true}>, filters: list<array{key: string, visible: bool, merged?: true}>}|null
     */
    public static function normalizeLayout(ListingDefinitionInterface $definition, mixed $submitted): ?array
    {
        if (!\is_array($submitted)) {
            return null;
        }
        $columns = self::normalize($definition->columns(), $submitted['columns'] ?? []);
        $filters = self::normalize($definition->filters(), $submitted['filters'] ?? []);
        if (null === $columns || null === $filters) {
            return null;
        }

        return ['columns' => $columns, 'filters' => array_map(static function (array $item): array {
            unset($item['merged']);

            return $item;
        }, $filters)];
    }

    /**
     * @param list<ListingField> $fields
     *
     * @return array<string, ListingField>
     */
    private static function byKey(array $fields): array
    {
        $byKey = [];
        foreach ($fields as $field) {
            $byKey[$field->key] = $field;
        }

        return $byKey;
    }
}
