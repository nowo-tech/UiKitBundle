<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Twig;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Text handed to a Symfony form theme that translates it once more (`attr.placeholder`, the choice `placeholder`):
 * the theme's `|trans` calls {@see self::trans()}, so a catalogue message is translated exactly once in its own
 * domain and a literal (`domain` null) is printed as given (2.3, `|nowo_ui_translatable`).
 */
final readonly class TranslatableText implements TranslatableInterface, \Stringable
{
    /**
     * @param string               $text   catalogue id (with a domain) or literal text (domain null)
     * @param string|null          $domain translation domain; null = literal, never translated
     * @param array<string, mixed> $params placeholder values
     */
    public function __construct(
        public string $text,
        public ?string $domain = null,
        public array $params = [],
    ) {
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        if (null === $this->domain) {
            return $this->literal();
        }

        return $translator->trans($this->text, $this->params, $this->domain, $locale);
    }

    /**
     * The literal text (placeholders replaced), or the catalogue id of a message.
     */
    public function __toString(): string
    {
        return null === $this->domain ? $this->literal() : $this->text;
    }

    private function literal(): string
    {
        return strtr($this->text, array_map(static fn (mixed $v): string => \is_scalar($v) || $v instanceof \Stringable ? (string) $v : '', $this->params));
    }
}
