<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit;

use Nowo\UiKitBundle\Avatar\AvatarUrlPolicy;
use Nowo\UiKitBundle\Tests\Unit\Avatar\AvatarUrlPolicyTest;
use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Theme\ThemeSlots;
use Nowo\UiKitBundle\Twig\ThemeExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\IdentityTranslator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\Markup;

/**
 * 2.26: partials/_avatar.html.twig is the shared avatar markup (picture over the initials, URL policy, labelled or
 * decorative, xs/sm/default/lg sizes) and the user menu passes the name and avatar_src through it.
 */
final class AvatarPartialTest extends TestCase
{
    public function testWithoutAPictureTheInitialsAreDecorative(): void
    {
        self::assertSame('<span class="nowo-ui-avatar" aria-hidden="true">AL</span>', $this->avatar(['initials' => 'al']));
        self::assertSame('<span class="nowo-ui-avatar" aria-hidden="true">?</span>', $this->avatar([]));
        self::assertSame('<span class="nowo-ui-avatar nowo-ui-avatar--sm" aria-hidden="true">AB</span>', $this->avatar(['initials' => 'abc', 'size' => 'sm']));
    }

    public function testADecorativePictureGoesOverTheInitials(): void
    {
        self::assertSame(
            '<span class="nowo-ui-avatar has-image" aria-hidden="true" data-nowo-ui-avatar>AL<img class="nowo-ui-avatar__image" src="/files/0b9b/s64" alt="" width="32" height="32" loading="lazy" decoding="async" referrerpolicy="same-origin"></span>',
            $this->avatar(['name' => 'Ada Lovelace', 'src' => '/files/0b9b/s64', 'decorative' => true]),
        );
    }

    public function testANamedAvatarIsLabelledOnTheWrapper(): void
    {
        self::assertSame(
            '<span class="nowo-ui-avatar nowo-ui-avatar--sm has-image" role="img" aria-label="Ada Lovelace" data-nowo-ui-avatar>AL<img class="nowo-ui-avatar__image" src="https://cdn.example.com/a.webp" alt="" width="24" height="24" loading="lazy" decoding="async" referrerpolicy="same-origin"></span>',
            $this->avatar(['name' => 'Ada Lovelace', 'src' => 'https://cdn.example.com/a.webp', 'size' => 'sm']),
        );
        // The label survives a missing picture (and the error fallback), `alt` wins over `name`.
        self::assertSame('<span class="nowo-ui-avatar" role="img" aria-label="Countess">AL</span>', $this->avatar(['name' => 'Ada Lovelace', 'alt' => ' Countess ']));
        self::assertSame('<span class="nowo-ui-avatar" role="img" aria-label="JD">JD</span>', $this->avatar(['alt' => 'JD', 'initials' => 'jd']));
    }

    public function testTheLabelAndTheAttributesAreEscaped(): void
    {
        $html = $this->avatar(['name' => '"><script>x</script>', 'src' => '/a?x=1&y=2', 'attr' => ['data-testid' => '"><i>', 'data-flag' => true, 'data-off' => false, 'data-null' => null, 'onclick' => 'alert(1)', 'style' => 'color:red', 'id' => 'x', 'data-Bad' => 'x']]);
        self::assertStringContainsString('aria-label="&quot;&gt;&lt;script&gt;x&lt;/script&gt;"', $html);
        self::assertStringContainsString('src="/a?x=1&amp;y=2"', $html);
        self::assertStringContainsString(' data-testid="&quot;&gt;&lt;i&gt;" data-flag="">', $html);
        self::assertStringNotContainsString('<script', $html);
        foreach (['onclick', 'style', ' id=', 'data-off', 'data-null', 'data-Bad'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $html);
        }
    }

    /**
     * Audit DZ-L2: a Twig\Markup src or attr value is printed as an escaped string, never as raw markup.
     */
    public function testMarkupValuesAreEscapedToo(): void
    {
        self::assertSame('<span class="nowo-ui-avatar" aria-hidden="true">A</span>', $this->avatar(['initials' => 'A', 'src' => new Markup('/a"/onerror="alert(1)', 'UTF-8')]));

        $html = $this->avatar(['initials' => 'A', 'src' => new Markup('/a.webp?x=1&y=2', 'UTF-8'), 'attr' => ['data-x' => new Markup('"/onclick="alert(1)', 'UTF-8')]]);
        self::assertStringContainsString('src="/a.webp?x=1&amp;y=2"', $html);
        self::assertStringContainsString('data-x="&quot;/onclick=&quot;alert(1)"', $html);
        self::assertStringNotContainsString('onclick="', $html);
        self::assertStringNotContainsString('onerror', $html);
    }

    public function testADataKeyWithATrailingNewlineIsDropped(): void
    {
        self::assertStringNotContainsString('data-x', $this->avatar(['initials' => 'A', 'attr' => ["data-x\n" => 'y']]));
    }

    /**
     * @return iterable<string, array{?string, int}>
     */
    public static function sizes(): iterable
    {
        yield 'xs' => ['xs', 20];
        yield 'sm' => ['sm', 24];
        yield 'default' => [null, 32];
        yield 'lg' => ['lg', 44];
        yield 'unknown' => ['xl', 32];
    }

    #[DataProvider('sizes')]
    public function testTheSizeSetsTheImageDimensions(?string $size, int $px): void
    {
        $html = $this->avatar(['initials' => 'A', 'src' => '/a.webp', 'size' => $size]);
        self::assertStringContainsString(\sprintf('width="%d" height="%d"', $px, $px), $html);
        self::assertStringContainsString(null === $size ? 'class="nowo-ui-avatar has-image"' : 'class="nowo-ui-avatar nowo-ui-avatar--'.$size.' has-image"', $html);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectedSources(): iterable
    {
        yield 'http' => ['http://cdn.example.com/a.png'];
        yield 'data image' => ['data:image/png;base64,iVBORw0KGgo='];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'protocol-relative' => ['//evil.example/a.png'];
        yield 'backslash' => ['/\\evil.example'];
        yield 'userinfo' => ['https://good@evil.example/a.png'];
    }

    #[DataProvider('rejectedSources')]
    public function testARejectedSourceRendersTheInitialsOnly(string $src): void
    {
        self::assertSame('<span class="nowo-ui-avatar" aria-hidden="true">AL</span>', $this->avatar(['name' => 'Ada Lovelace', 'src' => $src, 'decorative' => true]));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function policyCases(): iterable
    {
        yield from AvatarUrlPolicyTest::accepted();
        yield from AvatarUrlPolicyTest::rejected();
        yield 'integer' => [42];
        yield 'array' => [['/a.webp']];
    }

    /**
     * The partial (core Twig, no extension) and AvatarUrlPolicy agree on every case.
     */
    #[DataProvider('policyCases')]
    public function testThePartialAppliesExactlyThePhpPolicy(mixed $src): void
    {
        $html = $this->avatar(['initials' => 'A', 'src' => $src, 'decorative' => true]);
        $allowed = \is_string($src) && AvatarUrlPolicy::isAllowed($src);
        self::assertSame($allowed, str_contains($html, '<img '), var_export($src, true));
        self::assertSame($allowed, str_contains($html, 'has-image'));
    }

    public function testInitialsComeFromTheName(): void
    {
        self::assertStringContainsString('>ÁL</span>', $this->avatar(['name' => '  ánne   lovelace  byron ']));
        self::assertStringContainsString('>P</span>', $this->avatar(['name' => 'prince']));
    }

    public function testTheUserMenuPassesTheNameAndTheFilteredPictureThrough(): void
    {
        $menu = $this->render("{{ include('@NowoUiKitBundle/partials/_user_menu.html.twig', {name: 'Ada Lovelace', email: 'ada@example.com', avatar_src: '/files/a/s64'}, false) }}");
        self::assertSame(1, substr_count($menu, '<span class="nowo-ui-avatar has-image" aria-hidden="true" data-nowo-ui-avatar>A<img class="nowo-ui-avatar__image" src="/files/a/s64" alt="" width="32" height="32"'));
        self::assertSame(1, substr_count($menu, '<span class="nowo-ui-avatar nowo-ui-avatar--sm has-image" aria-hidden="true" data-nowo-ui-avatar>A<img class="nowo-ui-avatar__image" src="/files/a/s64" alt="" width="24" height="24"'));

        $legacy = $this->render("{{ include('@NowoUiKitBundle/partials/_user_menu.html.twig', {name: 'Ada', initials: 'AL', avatar_src: 'data:image/png;base64,AAAA'}, false) }}");
        self::assertStringNotContainsString('<img', $legacy);
        self::assertSame(2, substr_count($legacy, 'aria-hidden="true">AL</span>'));
    }

    public function testTheAvatarRuntimeIsADefaultScript(): void
    {
        self::assertContains('avatar', ThemeSlots::SCRIPTS);
        self::assertFileExists(\dirname(__DIR__, 2).'/src/Resources/public/js/nowo-ui-avatar.js');
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function avatar(array $vars): string
    {
        return $this->render("{% include '@NowoUiKitBundle/partials/_avatar.html.twig' with vars only %}", $vars);
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function render(string $template, array $vars = []): string
    {
        $fs = new FilesystemLoader();
        $fs->addPath(\dirname(__DIR__, 2).'/src/Resources/views', 'NowoUiKitBundle');
        $twig = new Environment(new ChainLoader([new ArrayLoader(['t.twig' => $template]), $fs]), ['strict_variables' => true]);
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));
        $twig->addExtension(new ThemeExtension(new Theme()));
        $twig->addGlobal('nowo_ui_kit_css_framework', 'custom');

        return trim($twig->render('t.twig', ['vars' => $vars]));
    }
}
