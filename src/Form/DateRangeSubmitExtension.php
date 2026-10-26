<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Form;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * Submission side of {@see DateRangeType} (2.15): a {@see DateRangeType} field submits its two days under the host's
 * names, as siblings of the field (`created_from` / `created_to`), not nested (`created[from]`). Before a compound
 * form submits its children, this moves those two values onto the date-range child (`created => [from, to]`), so the
 * field gets its data and the parameters do not count as extra fields. A nested value (`created[from]`) still works;
 * a flat one wins when both are sent. Only registered when symfony/form is installed.
 */
final class DateRangeSubmitExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    /**
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (true !== ($options['compound'] ?? false)) {
            return;
        }
        $builder->addEventListener(FormEvents::PRE_SUBMIT, self::mapRanges(...), 1024);
    }

    public static function mapRanges(FormEvent $event): void
    {
        $data = $event->getData();
        if (!\is_array($data)) {
            return;
        }
        $changed = false;
        foreach ($event->getForm()->all() as $name => $child) {
            $names = $child->getConfig()->getAttribute(DateRangeType::NAMES_ATTRIBUTE);
            if (!\is_array($names)) {
                continue;
            }
            $nested = \is_array($data[$name] ?? null) ? $data[$name] : [];
            foreach ([DateRangeType::FROM => $names[0] ?? null, DateRangeType::TO => $names[1] ?? null] as $key => $flat) {
                if (\is_string($flat) && \array_key_exists($flat, $data)) {
                    $nested[$key] = $data[$flat];
                    unset($data[$flat]);
                    $changed = true;
                }
            }
            if ([] !== $nested) {
                $data[$name] = $nested;
            }
        }
        if ($changed) {
            $event->setData($data);
        }
    }
}
