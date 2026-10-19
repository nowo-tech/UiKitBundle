<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Twig;

use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Twig\LocaleNames;
use Nowo\UiKitBundle\Twig\ThemeExtension;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class LocaleNamesTest extends TestCase
{
    #[RequiresPhpExtension('intl')]
    public function testNamesWithIntl(): void
    {
        $names = new LocaleNames();
        self::assertSame('Spanish', $names->name('es', 'en'));
        self::assertSame('Portuguese (Brazil)', $names->name('pt_BR', 'en'));
        self::assertSame('ZZ', $names->name('zz', 'en'), 'unknown locale: the code');
        self::assertSame('', $names->name(''));
        \Locale::setDefault('en');
        self::assertSame('German', $names->name('de'));
    }

    public function testNamesWithoutIntlAreTheCode(): void
    {
        self::assertSame('PT_BR', (new LocaleNames(false))->name('pt_BR', 'en'));
    }

    public function testDirection(): void
    {
        $names = new LocaleNames();
        self::assertSame(['rtl', 'rtl', 'rtl', 'rtl', 'ltr', 'ltr', 'ltr'], array_map($names->dir(...), ['ar', 'he_IL', 'fa-IR', 'UR', 'en', 'es', '']));
    }

    #[RequiresPhpExtension('intl')]
    public function testTwigFunctionsUseTheRequestLocale(): void
    {
        $stack = new RequestStack();
        $request = Request::create('/');
        $request->setLocale('en');
        $stack->push($request);
        $twig = new Environment(new ArrayLoader(['t' => "{{ nowo_ui_locale_name('fr') }}|{{ nowo_ui_locale_name('fr', 'en') }}|{{ nowo_ui_locale_dir('ar') }}"]));
        $twig->addExtension(new ThemeExtension(new Theme(), requestStack: $stack));
        self::assertSame('French|French|rtl', $twig->render('t'));
    }
}
