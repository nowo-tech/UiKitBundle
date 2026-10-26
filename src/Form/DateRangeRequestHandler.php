<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Form;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\RequestHandlerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Request side of {@see DateRangeType} (2.15.1): a form without a root name (`getBlockPrefix() === ''`, e.g. a GET
 * filter form) is only submitted by Symfony's request handler when one of its children's names is in the request.
 * A date range is submitted under the host's names (`from_name` / `to_name`), not its own, so a URL carrying nothing
 * but a range (`?min_receipt_issue_date=2026-01-01`) filtered the host's list while the form stayed unsubmitted and
 * the field rendered empty.
 *
 * This decorator of the request handler (`form.type_extension.form.request_handler`, the HttpFoundation one) makes
 * such a range count as a present child: for a nameless form whose request carries a range's from / to parameter but
 * not the range's own name, it hands the inner handler a copy of the request with an empty `<range name>` entry
 * added. The inner handler then submits the form as for any other present field, and
 * {@see DateRangeSubmitExtension} maps the two flat values onto the range. Named forms, other methods and requests
 * without a range parameter are passed through untouched. Stateless (safe in kept-alive workers).
 */
final class DateRangeRequestHandler implements RequestHandlerInterface
{
    public function __construct(private readonly RequestHandlerInterface $inner)
    {
    }

    /**
     * @param FormInterface<mixed> $form
     */
    public function handleRequest(FormInterface $form, mixed $request = null): void
    {
        if ($request instanceof Request && '' === $form->getName()) {
            $request = self::withRanges($form, $request);
        }
        $this->inner->handleRequest($form, $request);
    }

    public function isFileUpload(mixed $data): bool
    {
        return $this->inner->isFileUpload($data);
    }

    public function getUploadFileError(mixed $data): ?int
    {
        return method_exists($this->inner, 'getUploadFileError') ? $this->inner->getUploadFileError($data) : null;
    }

    /**
     * The request, or a copy of it in which every date range of the nameless form whose from / to parameter is present
     * (and its own name is not) has an empty entry under its own name; the request itself when another child is
     * present (the inner handler submits the form anyway).
     *
     * @param FormInterface<mixed> $form
     */
    public static function withRanges(FormInterface $form, Request $request): Request
    {
        $method = $form->getConfig()->getMethod();
        if ($method !== $request->getMethod()) {
            return $request;
        }
        $query = \in_array($method, ['GET', 'HEAD', 'TRACE'], true);
        $params = $query ? $request->query->all() : $request->request->all();
        if ([] !== array_intersect_key($params, $form->all())) {
            return $request;
        }
        $added = false;
        foreach ($form->all() as $name => $child) {
            $names = $child->getConfig()->getAttribute(DateRangeType::NAMES_ATTRIBUTE);
            if (!\is_array($names)) {
                continue;
            }
            foreach ($names as $flat) {
                if (\is_string($flat) && \array_key_exists($flat, $params)) {
                    $params[$name] = [];
                    $added = true;
                    break;
                }
            }
        }
        if (!$added) {
            return $request;
        }

        return $query ? $request->duplicate($params) : $request->duplicate(null, $params);
    }
}
