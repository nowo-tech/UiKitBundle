<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Test;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Output of one page rendered by {@see PageContractTestCase} inside the stub host layout, with DOM helpers for the
 * contract assertions (stub layout blocks, classes, text, attributes) and the translation ids the translator could
 * not resolve while rendering.
 */
final readonly class RenderedPage
{
    private DOMXPath $xpath;

    /**
     * @param list<string>                         $missingTranslations translation ids (domain-prefixed, `domain:id`) missing in the catalogues
     * @param array<string, array<string, string>> $actionForms         hidden fields of the FormViews backing the
     *                                                                  fixture's actions: form name => field full name => value
     */
    public function __construct(
        public string $template,
        public string $html,
        public string $framework,
        public array $missingTranslations = [],
        public array $actionForms = [],
    ) {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"?><div data-nowo-ui-contract-root>'.$html.'</div>', \LIBXML_NONET | \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $this->xpath = new DOMXPath($document);
    }

    /**
     * Stub layouts the page went through (`admin` / `guest`), one entry per rendered stub layout.
     *
     * @return list<string>
     */
    public function layouts(): array
    {
        return $this->attributeValues('data-nowo-ui-contract-layout');
    }

    /**
     * Element of a canonical block in the stub layout (null when the layout did not render it).
     */
    public function block(string $name): ?DOMElement
    {
        $nodes = $this->xpath->query(\sprintf('//*[@data-nowo-ui-contract-block="%s"]', $name));
        $node = false !== $nodes ? $nodes->item(0) : null;

        return $node instanceof DOMElement ? $node : null;
    }

    /**
     * Inner HTML of a canonical block ('' when empty or absent).
     */
    public function blockHtml(string $name): string
    {
        $block = $this->block($name);
        if (!$block instanceof DOMElement) {
            return '';
        }
        $html = '';
        foreach ($block->childNodes as $child) {
            $html .= $block->ownerDocument?->saveHTML($child);
        }

        return trim($html);
    }

    /**
     * Every class token used in the output.
     *
     * @return list<string>
     */
    public function classes(): array
    {
        $classes = [];
        foreach ($this->attributeValues('class') as $value) {
            $classes = [...$classes, ...(preg_split('/\s+/', $value, -1, \PREG_SPLIT_NO_EMPTY) ?: [])];
        }

        return array_values(array_unique($classes));
    }

    /**
     * Values of an attribute on every element (optionally only inside a canonical block).
     *
     * @return list<string>
     */
    public function attributeValues(string $attribute, ?string $block = null): array
    {
        $scope = null === $block ? '' : \sprintf('//*[@data-nowo-ui-contract-block="%s"]', $block);
        $values = [];
        foreach ($this->xpath->query($scope.'//*[@'.$attribute.']') ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $values[] = $node->getAttribute($attribute);
            }
        }

        return $values;
    }

    /**
     * Names of the attributes starting with a prefix found inside a canonical block.
     *
     * @return list<string>
     */
    public function attributeNames(string $prefix, string $block): array
    {
        $names = [];
        foreach ($this->xpath->query(\sprintf('//*[@data-nowo-ui-contract-block="%s"]//@*', $block)) ?: [] as $attribute) {
            if (str_starts_with($attribute->nodeName, $prefix)) {
                $names[] = $attribute->nodeName;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Visible text nodes (trimmed, non-empty; `<script>` / `<style>` excluded) plus the human-facing attributes
     * (`placeholder`, `title`, `aria-label`, `alt`, `value` of buttons).
     *
     * @return list<string>
     */
    public function texts(): array
    {
        $texts = [];
        foreach ($this->xpath->query('//text()[not(ancestor::script) and not(ancestor::style)]') ?: [] as $node) {
            $text = trim((string) $node->nodeValue);
            if ('' !== $text) {
                $texts[] = $text;
            }
        }
        foreach (['placeholder', 'title', 'aria-label', 'alt'] as $attribute) {
            $texts = [...$texts, ...$this->attributeValues($attribute)];
        }
        foreach ($this->xpath->query('//input[@type="submit" or @type="button"]/@value') ?: [] as $node) {
            $texts[] = (string) $node->nodeValue;
        }

        return array_values(array_filter(array_map(trim(...), $texts), static fn (string $t): bool => '' !== $t));
    }

    /**
     * For every `<form name="$form">` in the output (document order): field name => values of its hidden inputs
     * with that name.
     *
     * @param list<string> $fields
     *
     * @return list<array<string, list<string>>>
     */
    public function hiddenFieldValues(string $form, array $fields): array
    {
        $result = [];
        foreach ($this->xpath->query('//form[@name]') ?: [] as $node) {
            if (!$node instanceof DOMElement || $node->getAttribute('name') !== $form) {
                continue;
            }
            $values = array_fill_keys($fields, []);
            foreach ($this->xpath->query('.//input[@type="hidden"][@name]', $node) ?: [] as $input) {
                if ($input instanceof DOMElement && \array_key_exists($input->getAttribute('name'), $values)) {
                    $values[$input->getAttribute('name')][] = $input->getAttribute('value');
                }
            }
            $result[] = $values;
        }

        return $result;
    }

    /**
     * The controls of every form actions row (`nowo-ui-form__actions`, document order), each as `cancel` (marked
     * `data-nowo-ui-form-cancel`, or a secondary link / `type="button"` button), `primary` (a submit button with the
     * `nowo-ui-btn--primary` class) or `other` (2.66).
     *
     * @return list<list<string>> `cancel` / `primary` / `other` per control
     */
    public function formActionsRows(): array
    {
        $rows = [];
        foreach ($this->elements('//*[contains(concat(" ", normalize-space(@class), " "), " nowo-ui-form__actions ")]') as $row) {
            $rows[] = array_map(static function (DOMElement $node): string {
                $classes = ' '.preg_replace('/\s+/', ' ', $node->getAttribute('class')).' ';
                $submit = 'a' !== $node->nodeName && \in_array(strtolower($node->getAttribute('type')), ['', 'submit'], true);

                return match (true) {
                    $node->hasAttribute('data-nowo-ui-form-cancel'),
                    !$submit && str_contains($classes, ' nowo-ui-btn--secondary ') => 'cancel',
                    $submit && str_contains($classes, ' nowo-ui-btn--primary ') => 'primary',
                    default => 'other',
                };
            }, $this->elements('.//a | .//button | .//input[@type="submit" or @type="button"]', $row));
        }

        return $rows;
    }

    /**
     * The anatomy of every form actions row, in the order of {@see self::formActionsRows()} (2.66.1): `dialog` (inside a
     * `<dialog>`, or a control closes the UiKit dialog with `data-nowo-ui-dialog-close`: a dialog footer or a dialog
     * form fragment), `confirm` (full-page confirm), `wizard` (wizard step) — these keep Cancel / Back first — or
     * `form` (a page form row, which follows `theme.form_actions_order`).
     *
     * @return list<'dialog'|'confirm'|'wizard'|'form'>
     */
    public function formActionsRowAnatomies(): array
    {
        $anatomies = [];
        foreach ($this->elements('//*[contains(concat(" ", normalize-space(@class), " "), " nowo-ui-form__actions ")]') as $row) {
            $anatomies[] = match (true) {
                [] !== $this->elements('ancestor::dialog | .//*[@data-nowo-ui-dialog-close]', $row) => 'dialog',
                [] !== $this->elements('ancestor::*[@data-nowo-ui-page="confirm"]', $row) => 'confirm',
                [] !== $this->elements('ancestor::*[@data-nowo-ui-page="wizard"]', $row) => 'wizard',
                default => 'form',
            };
        }

        return $anatomies;
    }

    /**
     * Elements matching an XPath query (optionally relative to a context element).
     *
     * @return list<DOMElement>
     */
    private function elements(string $query, ?DOMElement $context = null): array
    {
        $nodes = iterator_to_array($this->xpath->query($query, $context) ?: [], false);

        return array_values(array_filter($nodes, static fn (mixed $node): bool => $node instanceof DOMElement));
    }
}
