<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Form;

use Symfony\Component\Form\Exception\ExceptionInterface as FormException;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface as OptionsException;
use Twig\Environment;
use Twig\Error\Error as TwigError;
use Twig\TwigFunction;

/**
 * Form views of the UiKit partials that used to print a hand-written `<form>` / `<input>` (2.19, REQ-TWIG-005):
 * the CSRF-only / hidden-field POST forms of `row_actions` (1.x hashes), `user_menu` (logout) and `locale_switcher`,
 * the GET search of `filters`, the per-page select of `pagination_compact` and the plain checkbox of `segmented`.
 * The partials render them with `form_start` / `form_label` / `form_widget` / the child `form_row` loop /
 * `form_end`; the request keeps its 2.x contract (URL, method, field names and values).
 *
 * Every view is built by a standalone factory (core types only: no host type extension adds fields or CSRF) and
 * themed with {@see self::THEMES} only (`form_div_layout` + UiKit's `nowo_ui_kit_fields` blocks: hidden inputs without
 * id, always with a value), so the markup does not depend on the host's form theme.
 *
 * Each builder returns null — and triggers a deprecation — when symfony/form, its Twig extension (`form_start` and
 * the {@see FormRenderer} runtime) or a valid form field name is missing: the partial then prints its deprecated 2.18
 * raw markup (`*_legacy.html.twig`, removed in 3.0).
 */
final class UiFormViews
{
    /** Form theme with the hidden-field blocks of the UiKit forms (2.19). */
    public const FIELDS_THEME = '@NowoUiKitBundle/form/nowo_ui_kit_fields.html.twig';

    /** Themes of every UiKit-built form (the host's default form themes are not used). */
    public const THEMES = ['form_div_layout.html.twig', self::FIELDS_THEME];

    /**
     * @param FormFactoryInterface|null $factory standalone factory (core types); null = a new one per call
     */
    public function __construct(private readonly ?FormFactoryInterface $factory = null)
    {
    }

    /**
     * `nowo_ui_form(kind, options)`: the view of one kind — `hidden` (options `fields`, `method`), `search`
     * ({@see SearchFilterType} options), `per_page` ({@see PerPageType} options) or `checkbox` (`name`, `checked`,
     * `value`, `id`); `partial` (any kind) names the partial in the deprecation. Null = deprecated raw fallback.
     *
     * @param array<string, mixed> $options
     */
    public function create(Environment $env, string $kind, array $options = []): ?FormView
    {
        $partial = \is_string($options['partial'] ?? null) ? $options['partial'] : $kind;
        unset($options['partial']);

        return match ($kind) {
            'hidden' => $this->hidden($env, \is_array($options['fields'] ?? null) ? $options['fields'] : [], \is_string($options['method'] ?? null) ? $options['method'] : 'POST', $partial),
            'search' => $this->search($env, $options, $partial),
            'per_page' => $this->perPage($env, $options, $partial),
            'checkbox' => $this->checkbox($env, \is_string($options['name'] ?? null) ? $options['name'] : '', (bool) ($options['checked'] ?? false), \is_scalar($options['value'] ?? null) ? (string) $options['value'] : '1', \is_string($options['id'] ?? null) && '' !== $options['id'] ? $options['id'] : null, $partial),
            default => throw new \InvalidArgumentException(\sprintf('Unknown UiKit form kind "%s" (hidden, search, per_page, checkbox).', $kind)),
        };
    }

    /**
     * Nameless hidden-field form (POST by default): `fields` name => value (nested arrays = `a[b]` names).
     *
     * @param array<array-key, mixed> $fields
     */
    public function hidden(Environment $env, array $fields, string $method = 'POST', string $partial = 'a UiKit partial'): ?FormView
    {
        return $this->build($env, $partial, static fn (FormFactoryInterface $factory): FormView => $factory
            ->createNamed('', HiddenFieldsType::class, null, ['fields' => $fields, 'method' => strtoupper($method)])
            ->createView());
    }

    /**
     * Nameless GET search form of the `filters` partial ({@see SearchFilterType} options).
     *
     * @param array<string, mixed> $options
     */
    public function search(Environment $env, array $options, string $partial = 'filters'): ?FormView
    {
        return $this->build($env, $partial, static fn (FormFactoryInterface $factory): FormView => $factory
            ->createNamed('', SearchFilterType::class, null, $options)
            ->createView());
    }

    /**
     * Nameless GET per-page form of the compact pagination ({@see PerPageType} options; `fields` = other query).
     *
     * @param array<string, mixed> $options
     */
    public function perPage(Environment $env, array $options, string $partial = 'pagination_compact'): ?FormView
    {
        return $this->build($env, $partial, static fn (FormFactoryInterface $factory): FormView => $factory
            ->createNamed('', PerPageType::class, null, $options)
            ->createView());
    }

    /**
     * Checkbox FormView named `name` (`a`, `a[b]`, `a[b][c]`), value `value`, checked or not, id `id` (else the
     * generated one) — the plain-checkbox mode of the `segmented` partial. Returns the checkbox (leaf) view.
     */
    public function checkbox(Environment $env, string $name, bool $checked, string $value = '1', ?string $id = null, string $partial = 'segmented'): ?FormView
    {
        if (1 !== preg_match('/^([^\[\]]+)((?:\[[^\[\]]+\])*)$/', $name, $match)) {
            $this->deprecate($partial, \sprintf('"%s" is not a form field name', $name));

            return null;
        }
        $path = [$match[1], ...('' === $match[2] ? [] : explode('][', substr($match[2], 1, -1)))];

        return $this->build($env, $partial, static function (FormFactoryInterface $factory) use ($path, $checked, $value, $id): FormView {
            $leaf = array_pop($path);
            $root = $factory->createNamedBuilder('', HiddenFieldsType::class);
            $parent = $root;
            foreach ($path as $segment) {
                $child = $factory->createNamedBuilder($segment, HiddenFieldsType::class, null, ['auto_initialize' => false]);
                $parent->add($child);
                $parent = $child;
            }
            $parent->add($leaf, CheckboxType::class, ['data' => $checked, 'value' => $value, 'required' => false, 'mapped' => false]);

            $view = $root->getForm()->createView();
            foreach ([...$path, $leaf] as $segment) {
                $view = $view->children[$segment];
            }
            if (null !== $id) {
                $view->vars['id'] = $id;
            }

            return $view;
        });
    }

    /**
     * One hidden leaf field rendered with the UiKit `hidden_widget` block (no id, value always printed), whatever
     * its rendered flag: a POST Action's shared FormView prints its CSRF token on every render (`_action_form_view`).
     */
    public function renderHidden(Environment $env, FormView $field): string
    {
        return $env->load(self::FIELDS_THEME)->renderBlock('hidden_widget', $field->vars);
    }

    /**
     * @param \Closure(FormFactoryInterface): FormView $create
     */
    private function build(Environment $env, string $partial, \Closure $create): ?FormView
    {
        if (!class_exists(Forms::class) || !$env->getFunction('form_start') instanceof TwigFunction) {
            $this->deprecate($partial, 'symfony/form or its Twig extension (form_start) is missing');

            return null;
        }
        try {
            $view = $create($this->factory ?? Forms::createFormFactory());
            $root = $view;
            while (null !== $root->parent) {
                $root = $root->parent;
            }
            $env->getRuntime(FormRenderer::class)->setTheme($root, self::THEMES, false);
        } catch (FormException|OptionsException|TwigError $e) {
            $this->deprecate($partial, $e->getMessage());

            return null;
        }

        return $view;
    }

    private function deprecate(string $partial, string $reason): void
    {
        @trigger_error(\sprintf('Since nowo-tech/ui-kit-bundle 2.19: the "%s" partial printed its raw <form> / <input> fallback (%s); it is deprecated and will be removed in 3.0 (REQ-TWIG-005). Install symfony/form and use valid form field names.', $partial, $reason), \E_USER_DEPRECATED);
    }
}
