<?php

declare(strict_types=1);

namespace App\Controller;

use Nowo\UiKitBundle\Form\VisibleWhen;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\DetailItem;
use Nowo\UiKitBundle\View\DetailSection;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\PageStatus;
use Nowo\UiKitBundle\View\Pagination;
use Nowo\UiKitBundle\View\ShowPage;
use Nowo\UiKitBundle\View\Stat;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * UiKit 2.23 demos (docs/USAGE.md): per-item attributes, pagination modes (pager | infinite | responsive) and
 * provider-dependent fields (visible-when peer). The pages use the kit admin layout plus the demo Stimulus app
 * (public/js/demo-peers.js). Fixtures only; the provider settings are kept in var/ (demo, not a pattern).
 */
#[Route('/kit/2.23')]
final class Kit223Controller extends AbstractController
{
    private const LAYOUT = 'kit/v223/_layout.html.twig';

    private const STATUSES = ['up', 'up', 'down', 'up', 'paused'];

    #[Route('/item-attributes', name: 'kit_223_item_attributes')]
    public function itemAttributes(): Response
    {
        $header = (new PageHeader('api.example.com', 'HTTPS monitor, checked every minute.'))
            ->withStatus(new PageStatus('Up', 'success', 'Checked 1 min ago', attributes: ['data-detail-status' => 'up', 'data-testid' => 'monitor-status']));

        return $this->render('kit/v223/item_attributes.html.twig', ['page' => new ShowPage(
            $header,
            [new DetailSection('Check', [
                new DetailItem('Interval', '60 s', attributes: ['data-fact' => 'interval']),
                new DetailItem('Last check', '2026-11-02 10:41', attributes: ['data-fact' => 'last-check'], valueAttributes: ['data-last-check' => '2026-11-02T10:41:00Z']),
                new DetailItem('Status', 'Up', badge: 'success', valueAttributes: ['data-detail-status' => 'up']),
            ], 'check', 'facts')],
            layout: self::LAYOUT,
            attributes: ['data-testid' => 'item-attributes'],
            stats: [
                new Stat('Uptime 24h', '99.98 %', 'last 24 h', 'success', attributes: ['data-stat' => 'uptime-24h'], valueAttributes: ['data-uptime-24h' => '99.98']),
                new Stat('Response', '182 ms', tone: 'neutral', valueAttributes: ['data-response-ms' => '182']),
                new Stat('Incidents', 0),
            ],
        )]);
    }

    #[Route('/pagination/{mode}', name: 'kit_223_pagination', requirements: ['mode' => 'pager|infinite|responsive'])]
    public function pagination(Request $request, string $mode): Response
    {
        $status = (string) $request->query->get('status', '');
        $rows = [];
        for ($i = 1; $i <= 23; ++$i) {
            $rowStatus = self::STATUSES[$i % \count(self::STATUSES)];
            if ('' === $status || $status === $rowStatus) {
                $rows[] = ['id' => $i, 'name' => \sprintf('monitor-%02d.example.com', $i), 'status' => $rowStatus, 'uptime' => \sprintf('%.2f %%', 100 - $i / 10)];
            }
        }
        $pagination = Pagination::fromRequest($request, \count($rows), 5, [5, 10])->withMode($mode);
        // one slice per request (the host's LIMIT / OFFSET query): the peer fetches the next page as a full page
        $slice = \array_slice($rows, $pagination->offset(), $pagination->limit());

        return $this->render('kit/v223/pagination.html.twig', ['page' => new ListPage(
            new PageHeader(\sprintf('Monitors (%s)', $mode), 'Pagination modes of UiKit 2.23.'),
            [new Column('id', '#', required: true), new Column('name', 'Name', required: true), new Column('status', 'Status'), new Column('uptime', 'Uptime', align: 'end', priority: 2)],
            $slice,
            rowActions: fn (array $row): array => [
                Action::delete($this->generateUrl('kit_223_pagination', ['mode' => $mode]), 'demo-token', Confirm::delete(\sprintf('%s will be deleted.', $row['name']), \sprintf('Delete monitor #%d?', $row['id']))),
            ],
            pagination: $pagination,
            layout: self::LAYOUT,
            attributes: ['data-testid' => 'pagination-'.$mode],
        )]);
    }

    #[Route('/provider-fields', name: 'kit_223_provider_fields', methods: ['GET', 'POST'])]
    public function providerFields(Request $request): Response
    {
        $file = $this->getParameter('kernel.project_dir').'/var/demo-provider-settings.json';
        $defaults = ['provider' => 'smtp', 'smtp_host' => 'mail.example.com', 'smtp_port' => 587, 'api_key' => '', 'region' => 'eu-west-1'];
        if ($request->query->has('reset') && is_file($file)) {
            unlink($file);
        }
        /** @var array<string, mixed> $stored */
        $stored = is_file($file) ? array_replace($defaults, (array) json_decode((string) file_get_contents($file), true)) : $defaults;

        $form = $this->container->get('form.factory')->createNamedBuilder('settings', FormType::class, array_replace($stored, ['api_key' => null]), [
            'attr' => VisibleWhen::containerAttributes() + ['data-testid' => 'provider-form'],
        ])
            ->add('provider', ChoiceType::class, ['label' => 'Provider', 'choices' => ['SMTP' => 'smtp', 'Amazon SES' => 'ses', 'Disabled' => 'null']])
            ->add('smtp_host', TextType::class, ['label' => 'SMTP host', 'required' => false, 'row_attr' => VisibleWhen::attributes('settings[provider]', ['smtp'])])
            ->add('smtp_port', IntegerType::class, ['label' => 'SMTP port', 'required' => false, 'row_attr' => VisibleWhen::attributes('settings[provider]', ['smtp'])])
            ->add('api_key', PasswordType::class, ['label' => 'API key (blank = keep)', 'required' => false, 'row_attr' => VisibleWhen::fromOption(['field' => 'settings[provider]', 'in' => ['ses']])])
            ->add('region', TextType::class, ['label' => 'Region', 'required' => false, 'row_attr' => VisibleWhen::attributes('settings[provider]', ['ses'])])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $data */
            $data = $form->getData();
            // secret: blank keeps the stored value
            if (null === $data['api_key'] || '' === $data['api_key']) {
                $data['api_key'] = $stored['api_key'];
            }
            file_put_contents($file, json_encode($data, \JSON_THROW_ON_ERROR));

            return $this->redirectToRoute('kit_223_provider_fields', ['saved' => 1]);
        }

        return $this->render('kit/v223/provider_fields.html.twig', ['form' => $form->createView(), 'stored' => $stored, 'saved' => $request->query->has('saved')]);
    }
}
