<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Twig;

use Nowo\UiKitBundle\Avatar\AvatarUrlPolicy;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * 2.26: `nowo_ui_avatar_url` filter, the {@see AvatarUrlPolicy} in Twig (`{{ url|nowo_ui_avatar_url }}` is the URL or
 * null). Used by `partials/_avatar.html.twig`; a host that draws its own picture applies it too. Not `is_safe`: the
 * result is still escaped by autoescape. Stateless.
 */
final class AvatarExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('nowo_ui_avatar_url', static fn (mixed $url): ?string => \is_string($url) ? AvatarUrlPolicy::sanitize($url) : null),
        ];
    }
}
