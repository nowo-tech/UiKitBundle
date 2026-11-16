<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Combobox field (2.53): one text input that searches a URL of the host as the user types and lists the answers in a grouped
 * listbox, rendered by the `nowo_ui_combobox_widget` block (`form/nowo_ui_combobox.html.twig`) through the `combobox` partial.
 * Without JavaScript it is a plain text input and submits what was typed; `js/nowo-ui-combobox.js` (opt-in runtime `combobox`)
 * adds the listbox. The value is a string either way: what the user typed or the picked option's `value`. The host's search
 * endpoint and its answer shape: docs/USAGE.md "Combobox". Stateless: nothing is kept between requests (FrankenPHP worker mode).
 *
 * Options:
 *   - `url` (required): the search URL, on the page's origin (a path, or an absolute http(s) URL of the same origin)
 *   - `min_length`: characters before the first request (default 2, 1 to 50)
 *   - `debounce`: milliseconds after the last keystroke (default 250, 0 to 2000)
 *   - `param`: query parameter of the term (default `q`; letters, digits, `_`, `.` and `-`, at most 64)
 *   - `labels`: `list`, `results`, `none`, `min_length`, `error` overrides, already translated or `View\Label`
 *   - `bundle`: bundle name for per-bundle theme exceptions
 *
 * @extends AbstractType<string|null>
 */
final class ComboboxType extends AbstractType
{
    public const BLOCK_PREFIX = 'nowo_ui_combobox';

    /** Label keys a host may override. */
    public const LABELS = ['list', 'results', 'none', 'min_length', 'error'];

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars[self::BLOCK_PREFIX] = [
            'url' => $options['url'],
            'min_length' => $options['min_length'],
            'debounce' => $options['debounce'],
            'param' => $options['param'],
            'labels' => $options['labels'],
            'bundle' => $options['bundle'],
        ];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('url');
        $resolver->setDefaults([
            'min_length' => 2,
            'debounce' => 250,
            'param' => 'q',
            'labels' => [],
            'bundle' => null,
        ]);
        $resolver->setAllowedTypes('url', 'string');
        $resolver->setAllowedValues('url', static fn (string $url): bool => 1 === preg_match('#\A(/(?![/\\\\])|https?://)[^\s]*\z#i', $url));
        $resolver->setAllowedTypes('min_length', 'int');
        $resolver->setAllowedValues('min_length', static fn (int $value): bool => $value >= 1 && $value <= 50);
        $resolver->setAllowedTypes('debounce', 'int');
        $resolver->setAllowedValues('debounce', static fn (int $value): bool => $value >= 0 && $value <= 2000);
        $resolver->setAllowedTypes('param', 'string');
        $resolver->setAllowedValues('param', static fn (string $param): bool => 1 === preg_match('/\A[A-Za-z0-9_.-]{1,64}\z/', $param));
        $resolver->setAllowedTypes('labels', 'array');
        $resolver->setAllowedValues('labels', static fn (array $labels): bool => [] === array_diff(array_keys($labels), self::LABELS));
        $resolver->setAllowedTypes('bundle', ['null', 'string']);
    }

    public function getParent(): string
    {
        return TextType::class;
    }

    public function getBlockPrefix(): string
    {
        return self::BLOCK_PREFIX;
    }
}
