<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Form;

use Closure;
use InvalidArgumentException;

/**
 * Options of the image input (2.47, FileType option `nowo_ui_image_input`, {@see ImageInputTypeExtension}): one box that shows the
 * stored image (or the effective default, marked "Default"), swaps to a local preview as soon as a file is picked (after the crop
 * step's Apply when `nowo_ui_image_crop` is on too), and holds Replace / Remove / Undo. Without JavaScript it is the plain file
 * input, the current image and the host's remove checkbox.
 *
 * Options (all optional):
 *   - `current_url`: URL of the stored image, or a `Closure(FormInterface $field): ?string` resolved when the view is built (e.g.
 *     from the parent's data). Null / '' = nothing stored.
 *   - `default_url`: URL of the image used when nothing is stored (shown with a "Default" badge). Null = an empty box.
 *   - `remove_field`: name of a sibling field of the same parent form (a CheckboxType, unmapped or mapped) that tells the host to
 *     delete the stored image. The box renders it (and the Remove button drives it) only when something is stored; otherwise it is
 *     marked rendered and never printed.
 *   - `backdrop`: `checkerboard` (default) | `light` | `dark`: what shows through transparent art.
 *   - `labels`: overrides of `replace`, `choose`, `remove`, `undo`, `default`, `empty`, `selected` (`%name%`), `removed`,
 *     `restored`, `drop`, `not_image`, already translated.
 *
 * URLs: http(s), protocol-relative, root-relative or relative, or a `data:image/…;base64,` URI; no other scheme, no whitespace or
 * control characters. The template escapes them.
 */
final class ImageInput
{
    public const BACKDROPS = ['checkerboard', 'light', 'dark'];

    public const LABELS = ['replace', 'choose', 'remove', 'undo', 'default', 'empty', 'selected', 'removed', 'restored', 'drop', 'not_image'];

    private const KEYS = ['current_url', 'default_url', 'remove_field', 'backdrop', 'labels'];

    /**
     * The validated options with their defaults (`current_url` may still be a Closure).
     *
     * @param array<mixed> $options
     *
     * @return array{current_url: string|Closure|null, default_url: ?string, remove_field: ?string, backdrop: string, labels: array<string, string>}
     */
    public static function normalize(array $options): array
    {
        $unknown = array_diff(array_map(strval(...), array_keys($options)), self::KEYS);
        if ([] !== $unknown) {
            throw new InvalidArgumentException(\sprintf('Unknown image input option(s) "%s" (%s).', implode('", "', $unknown), implode(', ', self::KEYS)));
        }
        $current = $options['current_url'] ?? null;
        if (!$current instanceof Closure) {
            $current = self::url($current, 'current_url');
        }
        $remove = $options['remove_field'] ?? null;
        if (null !== $remove && (!\is_string($remove) || 1 !== preg_match('/^[A-Za-z0-9_][A-Za-z0-9_\-:.]*$/', $remove))) {
            throw new InvalidArgumentException('The image input remove_field is the name of a sibling form field.');
        }
        $backdrop = $options['backdrop'] ?? 'checkerboard';
        if (!\in_array($backdrop, self::BACKDROPS, true)) {
            throw new InvalidArgumentException('The image input backdrop is "checkerboard", "light" or "dark".');
        }
        $labels = $options['labels'] ?? [];
        if (!\is_array($labels) || [] !== array_diff(array_map(strval(...), array_keys($labels)), self::LABELS) || [] !== array_filter($labels, static fn (mixed $label): bool => !\is_string($label))) {
            throw new InvalidArgumentException(\sprintf('The image input labels are strings keyed %s.', implode(', ', self::LABELS)));
        }

        /* @var array<string, string> $labels */
        return [
            'current_url' => $current,
            'default_url' => self::url($options['default_url'] ?? null, 'default_url'),
            'remove_field' => $remove,
            'backdrop' => $backdrop,
            'labels' => $labels,
        ];
    }

    /**
     * A safe image URL, or null for null / ''.
     */
    public static function url(mixed $url, string $option = 'url'): ?string
    {
        if (null === $url || '' === $url) {
            return null;
        }
        if (!\is_string($url) || 1 === preg_match('/[\x00-\x20\x7F]/', $url)) {
            throw new InvalidArgumentException(\sprintf('The image input %s is a URL without whitespace or control characters.', $option));
        }
        if (1 === preg_match('#^([a-z][a-z0-9+.-]*):#i', $url, $m)) {
            $scheme = strtolower($m[1]);
            $dataImage = 'data' === $scheme && 1 === preg_match('#^data:image/[a-z0-9.+-]+;base64,[A-Za-z0-9+/]+={0,2}$#i', $url);
            if (!$dataImage && !\in_array($scheme, ['http', 'https'], true)) {
                throw new InvalidArgumentException(\sprintf('The image input %s: the "%s" scheme is not allowed (http, https, a path or data:image/…;base64).', $option, $m[1]));
            }
        }

        return $url;
    }
}
