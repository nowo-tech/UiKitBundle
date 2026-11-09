<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Form;

use InvalidArgumentException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Attribute contract of the image crop step (2.43, js/nowo-ui-image-crop.js): the attributes an `<input type="file">` carries so that
 * a picked image is cropped in the browser before it is uploaded. The browser replaces the input's file with the crop, so the server
 * receives and validates an ordinary upload; nothing here validates the image.
 *
 * Options (all optional):
 *   - `shape`: `square` (default) | `circle`. A circle is a visual mask: the stored image stays square unless `bake_circle`.
 *   - `aspect`: width / height of the frame, `1` (default), a number or ratio (`1.5`, `'16/9'`, `'16:9'`), or `'free'` (the
 *     frame takes the image's own proportions). A circle is always 1.
 *   - `max`: longest edge of the output in px, 16 to 8192 (default 1024). The crop is never enlarged.
 *   - `min`: shortest edge the crop may have in image px (default 1): zooming in stops there, so a server minimum is met.
 *   - `format`: `auto` (default: WebP where the engine encodes it, else PNG for a crop with transparency and JPEG without) |
 *     `png` | `webp` | `jpeg`. A browser that cannot encode WebP gives PNG for an explicit `webp`.
 *   - `max_bytes`: largest output file in bytes (default none). Over it, the quality (WebP, JPEG) and then the size step down;
 *     when nothing fits, the dialog says so and stays open.
 *   - `quality`: encoder quality for WebP and JPEG, 0 to 1 (default 0.92).
 *   - `bake_circle`: `true` (circle only, PNG or WebP): the pixels outside the circle become transparent.
 *   - `preview`: id of an `<img>` that shows the crop once applied.
 *   - `labels`: texts of the dialog (`title`, `instructions`, `stage`, `zoom`, `zoom_status` with `%percent%`, `apply`,
 *     `cancel`, `close`, `too_large` with `%max%`), over the translations of `image_crop.*` (domain NowoUiKitBundle).
 *
 * Use it on a FileType through the option `nowo_ui_image_crop` ({@see ImageCropTypeExtension}), on a hand-written input through
 * the Twig function `nowo_ui_image_crop_attrs()`, or from another bundle (FormKit) with `ImageCrop::attributes($options,
 * $translator)`: the result is a plain attribute map to print on the input.
 */
final class ImageCrop
{
    public const ATTRIBUTE = 'data-nowo-ui-image-crop';

    public const SHAPES = ['square', 'circle'];

    public const FORMATS = ['auto', 'png', 'webp', 'jpeg'];

    public const MAX_RANGE = [16, 8192];

    public const DEFAULT_MAX = 1024;

    private const KEYS = ['shape', 'aspect', 'max', 'min', 'format', 'max_bytes', 'quality', 'bake_circle', 'preview', 'labels'];

    /** Label key => translation key. */
    private const LABELS = [
        'title' => 'image_crop.title',
        'instructions' => 'image_crop.instructions',
        'stage' => 'image_crop.stage',
        'zoom' => 'image_crop.zoom',
        'zoom_status' => 'image_crop.zoom_status',
        'apply' => 'image_crop.apply',
        'cancel' => 'image_crop.cancel',
        'close' => 'image_crop.close',
        'too_large' => 'image_crop.too_large',
    ];

    /** English wording when no translator is given (the runtime's own defaults). */
    private const ENGLISH = [
        'title' => 'Crop the image',
        'instructions' => 'Drag the image or use the arrow keys to move it. Zoom with the slider, the mouse wheel, a pinch, or the + and - keys. Enter applies, Escape cancels.',
        'stage' => 'Image to crop',
        'zoom' => 'Zoom',
        'zoom_status' => 'Zoom %percent%%',
        'apply' => 'Apply',
        'cancel' => 'Cancel',
        'close' => 'Close',
        'too_large' => 'This crop cannot be made smaller than %max%. Crop a smaller part of the image, or cancel.',
    ];

    /**
     * The validated options with their defaults.
     *
     * @param array<mixed> $options
     *
     * @return array{shape: string, aspect: string, max: int, min: int, format: string, max_bytes: ?int, quality: float, bake_circle: bool, preview: ?string, labels: array<string, string>}
     */
    public static function normalize(array $options): array
    {
        $unknown = array_diff(array_map(strval(...), array_keys($options)), self::KEYS);
        if ([] !== $unknown) {
            throw new InvalidArgumentException(\sprintf('Unknown image crop option(s) "%s" (%s).', implode('", "', $unknown), implode(', ', self::KEYS)));
        }
        $shape = $options['shape'] ?? 'square';
        if (!\in_array($shape, self::SHAPES, true)) {
            throw new InvalidArgumentException('The image crop shape is "square" or "circle".');
        }
        $format = $options['format'] ?? 'auto';
        if (!\in_array($format, self::FORMATS, true)) {
            throw new InvalidArgumentException('The image crop format is "auto", "png", "webp" or "jpeg".');
        }
        $maxBytes = $options['max_bytes'] ?? null;
        if (null !== $maxBytes && (!\is_int($maxBytes) || $maxBytes < 1)) {
            throw new InvalidArgumentException('The image crop max_bytes is a positive integer (bytes) or null.');
        }
        $max = $options['max'] ?? self::DEFAULT_MAX;
        if (!\is_int($max) || $max < self::MAX_RANGE[0] || $max > self::MAX_RANGE[1]) {
            throw new InvalidArgumentException(\sprintf('The image crop max is an integer from %d to %d.', ...self::MAX_RANGE));
        }
        $min = $options['min'] ?? 1;
        if (!\is_int($min) || $min < 1 || $min > self::MAX_RANGE[1]) {
            throw new InvalidArgumentException(\sprintf('The image crop min is an integer from 1 to %d.', self::MAX_RANGE[1]));
        }
        $quality = $options['quality'] ?? 0.92;
        if ((!\is_int($quality) && !\is_float($quality)) || $quality < 0 || $quality > 1) {
            throw new InvalidArgumentException('The image crop quality is a number from 0 to 1.');
        }
        $bake = $options['bake_circle'] ?? false;
        if (!\is_bool($bake)) {
            throw new InvalidArgumentException('The image crop bake_circle is a boolean.');
        }
        if ($bake && ('circle' !== $shape || 'jpeg' === $format)) {
            throw new InvalidArgumentException('bake_circle needs the circle shape and a format with transparency (png or webp).');
        }
        $preview = $options['preview'] ?? null;
        if (null !== $preview && (!\is_string($preview) || 1 !== preg_match('/^[A-Za-z][\w:.-]{0,127}$/', $preview))) {
            throw new InvalidArgumentException('The image crop preview is the id of an <img>.');
        }
        $labels = $options['labels'] ?? [];
        if (!\is_array($labels) || [] !== array_diff(array_map(strval(...), array_keys($labels)), array_keys(self::LABELS)) || [] !== array_filter($labels, static fn (mixed $label): bool => !\is_string($label))) {
            throw new InvalidArgumentException(\sprintf('The image crop labels are strings keyed %s.', implode(', ', array_keys(self::LABELS))));
        }

        /* @var array<string, string> $labels */
        return [
            'shape' => $shape,
            'aspect' => 'circle' === $shape ? '1' : self::aspect($options['aspect'] ?? 1),
            'max' => $max,
            'min' => $min,
            'format' => $format,
            'max_bytes' => $maxBytes,
            'quality' => (float) $quality,
            'bake_circle' => $bake,
            'preview' => $preview,
            'labels' => $labels,
        ];
    }

    /**
     * The aspect as the runtime reads it: `free`, a ratio `w/h` or a number, positive.
     */
    public static function aspect(mixed $aspect): string
    {
        if ('free' === $aspect) {
            return 'free';
        }
        if ((\is_int($aspect) || \is_float($aspect)) && $aspect > 0 && is_finite((float) $aspect)) {
            return rtrim(rtrim(number_format((float) $aspect, 6, '.', ''), '0'), '.');
        }
        if (\is_string($aspect) && 1 === preg_match('/^(\d+(?:\.\d+)?)\s*[\/:]\s*(\d+(?:\.\d+)?)$/', trim($aspect), $match) && (float) $match[1] > 0 && (float) $match[2] > 0) {
            return $match[1].'/'.$match[2];
        }
        if (\is_string($aspect) && 1 === preg_match('/^\d+(?:\.\d+)?$/', trim($aspect)) && (float) $aspect > 0) {
            return trim($aspect);
        }

        throw new InvalidArgumentException('The image crop aspect is "free", a positive number or a ratio such as "16/9".');
    }

    /**
     * Attributes of an input with the crop step (every option printed, labels translated).
     *
     * @param array<mixed> $options
     *
     * @return array<string, string>
     */
    public static function attributes(array $options = [], ?TranslatorInterface $translator = null): array
    {
        $config = self::normalize($options);
        $attributes = [
            self::ATTRIBUTE => '',
            self::ATTRIBUTE.'-shape' => $config['shape'],
            self::ATTRIBUTE.'-aspect' => $config['aspect'],
            self::ATTRIBUTE.'-max' => (string) $config['max'],
            self::ATTRIBUTE.'-format' => $config['format'],
        ];
        if (1 !== $config['min']) {
            $attributes[self::ATTRIBUTE.'-min'] = (string) $config['min'];
        }
        if (null !== $config['max_bytes']) {
            $attributes[self::ATTRIBUTE.'-max-bytes'] = (string) $config['max_bytes'];
        }
        if ('png' !== $config['format']) {
            $attributes[self::ATTRIBUTE.'-quality'] = rtrim(rtrim(number_format($config['quality'], 3, '.', ''), '0'), '.');
        }
        if ($config['bake_circle']) {
            $attributes[self::ATTRIBUTE.'-bake-circle'] = 'true';
        }
        if (null !== $config['preview']) {
            $attributes[self::ATTRIBUTE.'-preview'] = $config['preview'];
        }
        $attributes[self::ATTRIBUTE.'-labels'] = json_encode(self::labels($config['labels'], $translator), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

        return $attributes;
    }

    /**
     * The dialog's texts keyed as the runtime reads them (camelCase), translated, with `$overrides` over them.
     *
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    public static function labels(array $overrides = [], ?TranslatorInterface $translator = null): array
    {
        $labels = [];
        foreach (self::LABELS as $key => $id) {
            $text = $overrides[$key] ?? ($translator?->trans($id, [], 'NowoUiKitBundle') ?? self::ENGLISH[$key]);
            $labels[lcfirst(str_replace('_', '', ucwords($key, '_')))] = $text;
        }

        return $labels;
    }
}
