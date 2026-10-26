<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Form;

use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use Nowo\UiKitBundle\Form\DateRangeRequestHandler;
use Nowo\UiKitBundle\Form\DateRangeSubmitExtension;
use Nowo\UiKitBundle\Form\DateRangeType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationRequestHandler;
use Symfony\Component\Form\Extension\HttpFoundation\Type\FormTypeHttpFoundationExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\RequestHandlerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * A nameless GET filter form whose URL carries nothing but a date range (2.15.1): the range's host-named parameters
 * count as a present child, so the form is submitted and the field shows the range.
 */
final class DateRangeRequestHandlerTest extends TestCase
{
    /**
     * @return iterable<string, array{0: array<string, string>, 1: bool, 2: array{from: string|null, to: string|null}|null}>
     */
    public static function rangeOnlyQueries(): iterable
    {
        yield 'only from' => [['min_receipt_issue_date' => '2026-01-01'], true, ['from' => '2026-01-01', 'to' => null]];
        yield 'only to' => [['max_receipt_issue_date' => '2026-01-31'], true, ['from' => null, 'to' => '2026-01-31']];
        yield 'both' => [['min_receipt_issue_date' => '2026-01-01', 'max_receipt_issue_date' => '2026-01-31'], true, ['from' => '2026-01-01', 'to' => '2026-01-31']];
        yield 'none' => [[], false, null];
        yield 'an unrelated parameter only' => [['page' => '2'], false, null];
        yield 'an empty from' => [['min_receipt_issue_date' => ''], true, ['from' => null, 'to' => null]];
    }

    /**
     * @param array<string, string>                          $query
     * @param array{from: string|null, to: string|null}|null $expected null: not submitted, no initial data
     */
    #[DataProvider('rangeOnlyQueries')]
    public function testANamelessGetFormWithOnlyARange(array $query, bool $submitted, ?array $expected): void
    {
        $form = $this->filterForm();
        $form->handleRequest(Request::create('/receipts', 'GET', $query));

        self::assertSame($submitted, $form->isSubmitted());
        if ($submitted) {
            self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        }
        self::assertSame($expected, $form->get('issue_date')->getData());
        $view = $form->createView()['issue_date'];
        self::assertSame($expected['from'] ?? '', $view['from']->vars['value']);
        self::assertSame($expected['to'] ?? '', $view['to']->vars['value']);
        if (null !== $expected) {
            self::assertTrue($form->get('issue_date')->isSubmitted());
        }
        self::assertSame('min_receipt_issue_date', $view['from']->vars['full_name']);
    }

    public function testWithoutTheDecoratorSymfonyDoesNotSubmitTheForm(): void
    {
        $form = $this->filterForm(new HttpFoundationRequestHandler());
        $form->handleRequest(Request::create('/receipts', 'GET', ['min_receipt_issue_date' => '2026-01-01']));

        self::assertFalse($form->isSubmitted(), 'the 2.15.0 behaviour this decorator fixes');
    }

    public function testANamelessPostFormWithOnlyARange(): void
    {
        $form = $this->filterForm(method: 'POST');
        $form->handleRequest(Request::create('/receipts', 'POST', ['max_receipt_issue_date' => '2026-02-28']));

        self::assertTrue($form->isSubmitted());
        self::assertSame(['from' => null, 'to' => '2026-02-28'], $form->get('issue_date')->getData());
    }

    public function testAnotherFieldStillSubmitsAndTheRequestIsNotCopied(): void
    {
        $form = $this->filterForm();
        $request = Request::create('/receipts', 'GET', ['q' => 'abc', 'min_receipt_issue_date' => '2026-01-01']);
        self::assertSame($request, DateRangeRequestHandler::withRanges($form, $request), 'another child already submits the form');

        $request = Request::create('/receipts', 'GET', ['issue_date' => ['to' => '2026-01-31'], 'min_receipt_issue_date' => '2026-01-01']);
        self::assertSame($request, DateRangeRequestHandler::withRanges($form, $request), 'a nested value is already a present child');

        $request = Request::create('/receipts', 'GET', ['q' => 'abc']);
        $form->handleRequest($request);
        self::assertTrue($form->isSubmitted());
        self::assertSame('abc', $form->get('q')->getData());
    }

    public function testTheCopyKeepsTheOtherParametersAndTheOriginalIsUntouched(): void
    {
        $form = $this->filterForm();
        $request = Request::create('/receipts', 'GET', ['min_receipt_issue_date' => '2026-01-01', 'page' => '2']);
        $copy = DateRangeRequestHandler::withRanges($form, $request);

        self::assertNotSame($request, $copy);
        self::assertSame(['min_receipt_issue_date' => '2026-01-01', 'page' => '2', 'issue_date' => []], $copy->query->all());
        self::assertSame(['min_receipt_issue_date' => '2026-01-01', 'page' => '2'], $request->query->all());
    }

    public function testANamedFormAndAnotherMethodArePassedThrough(): void
    {
        $named = $this->factory()->createNamedBuilder('filter', FormType::class, null, ['method' => 'GET'])
            ->add('issue_date', DateRangeType::class, ['from_name' => 'min_receipt_issue_date', 'to_name' => 'max_receipt_issue_date'])
            ->getForm();
        $named->handleRequest(Request::create('/receipts', 'GET', ['min_receipt_issue_date' => '2026-01-01']));
        self::assertFalse($named->isSubmitted(), 'a named form is submitted under its own name only');

        $form = $this->filterForm();
        $request = Request::create('/receipts', 'POST', ['min_receipt_issue_date' => '2026-01-01']);
        self::assertSame($request, DateRangeRequestHandler::withRanges($form, $request));
        $form->handleRequest($request);
        self::assertFalse($form->isSubmitted());
    }

    public function testDelegatesNonRequestsAndUploadsToTheInnerHandler(): void
    {
        $inner = new class implements RequestHandlerInterface {
            public mixed $handled = 'nothing';

            public function handleRequest(FormInterface $form, mixed $request = null): void
            {
                $this->handled = $request;
            }

            public function isFileUpload(mixed $data): bool
            {
                return 'file' === $data;
            }
        };
        $handler = new DateRangeRequestHandler($inner);
        $handler->handleRequest($this->filterForm(), ['min_receipt_issue_date' => '2026-01-01']);

        self::assertSame(['min_receipt_issue_date' => '2026-01-01'], $inner->handled);
        self::assertTrue($handler->isFileUpload('file'));
        self::assertNull($handler->getUploadFileError('file'), 'inner handler without getUploadFileError()');

        $http = new DateRangeRequestHandler(new HttpFoundationRequestHandler());
        $upload = new UploadedFile(__FILE__, 'x.php', null, \UPLOAD_ERR_NO_FILE, true);
        self::assertTrue($http->isFileUpload($upload));
        self::assertSame(\UPLOAD_ERR_NO_FILE, $http->getUploadFileError($upload));
    }

    public function testDecoratesTheFrameworkRequestHandler(): void
    {
        $container = new ContainerBuilder();
        $container->register('form.type_extension.form.request_handler', HttpFoundationRequestHandler::class)->setPublic(true);
        (new NowoUiKitExtension())->load([[]], $container);
        $container->compile();

        $handler = $container->get('form.type_extension.form.request_handler');
        self::assertInstanceOf(DateRangeRequestHandler::class, $handler);

        $bare = new ContainerBuilder();
        (new NowoUiKitExtension())->load([[]], $bare);
        $bare->compile();
        self::assertFalse($bare->has('nowo_ui_kit.form.date_range_request_handler'), 'no FrameworkBundle request handler: nothing to decorate');
    }

    /**
     * @return FormInterface<mixed>
     */
    private function filterForm(?RequestHandlerInterface $handler = null, string $method = 'GET'): FormInterface
    {
        return $this->factory($handler)->createNamedBuilder('', FormType::class, null, ['method' => $method])
            ->add('q', TextType::class, ['required' => false])
            ->add('issue_date', DateRangeType::class, ['from_name' => 'min_receipt_issue_date', 'to_name' => 'max_receipt_issue_date'])
            ->getForm();
    }

    private function factory(?RequestHandlerInterface $handler = null): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addTypeExtension(new FormTypeHttpFoundationExtension($handler ?? new DateRangeRequestHandler(new HttpFoundationRequestHandler())))
            ->addType(new DateRangeType())
            ->addTypeExtension(new DateRangeSubmitExtension())
            ->getFormFactory();
    }
}
