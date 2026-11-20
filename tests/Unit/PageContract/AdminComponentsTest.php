<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use InvalidArgumentException;
use Nowo\UiKitBundle\Theme\ThemeSlots;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\EmptyState;
use Nowo\UiKitBundle\View\Stat;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormView;
use Twig\Environment;

/**
 * 2.61 admin components: filter bar (phone sheet), bulk bar and toggle, table view controls, client-side tab panels,
 * error summary, fieldset, dropzone, timeline, skeleton, avatar group, chip, code block, tooltip, steps, stat tiles
 * (View\Stat delta / trend / icon), illustrated empty state, client toasts and the destructive confirm focus. Every
 * partial is registered as a theme partial, prints no inline style or handler, escapes its data, and renders the same
 * output for the same input on a reused Twig environment (no per-request state: FrankenPHP worker, kernel reset off).
 */
final class AdminComponentsTest extends TestCase
{
    private const PARTIALS = ['avatar_group', 'bulk_bar', 'bulk_toggle', 'chip', 'code_block', 'dropzone', 'error_summary', 'filter_bar', 'form_fieldset', 'skeleton', 'steps', 'tab_panels', 'table_view', 'timeline', 'tooltip'];

    public function testEveryComponentIsAThemePartial(): void
    {
        foreach (self::PARTIALS as $name) {
            self::assertArrayHasKey($name, ThemeSlots::PARTIALS);
            self::assertFileExists(PageContractTwig::root().'/src/Resources/views/'.substr(ThemeSlots::PARTIALS[$name], \strlen('@NowoUiKitBundle/')));
        }
        $twig = PageContractTwig::create(['t' => "{{ nowo_ui_partial('tooltip') }}"], ['partials' => ['tooltip' => 'host/_tooltip.html.twig']]);
        self::assertSame('host/_tooltip.html.twig', $twig->render('t'));
    }

    public function testFilterBarCountsActiveFiltersAndCarriesTheSheet(): void
    {
        $form = PageContractTwig::forms()->createNamedBuilder('', FormType::class, null, ['method' => 'GET'])
            ->add('q', SearchType::class, ['required' => false])
            ->add('status', ChoiceType::class, ['required' => false, 'choices' => ['Paid' => 'paid', 'Due' => 'due']])
            ->add('tags', ChoiceType::class, ['required' => false, 'multiple' => true, 'choices' => ['A' => 'a']])
            ->add('mine', CheckboxType::class, ['required' => false])
            ->add('page', HiddenType::class)
            ->getForm();
        $form->submit(['q' => 'ada', 'status' => '', 'tags' => ['a'], 'mine' => '1', 'page' => '3']);
        $html = $this->render("{{ include(nowo_ui_partial('filter_bar'), {form: form, id: 'fb', reset_href: '/invoices'}) }}", ['form' => $form->createView()]);

        self::assertStringContainsString('<div id="fb" class="nowo-ui-filter-sheet" data-nowo-ui-filter-sheet data-nowo-ui-region="filters">', $html);
        self::assertStringContainsString('aria-haspopup="dialog" aria-controls="fb-dialog" data-nowo-ui-filter-sheet-open>Filters <span class="nowo-ui-filter-sheet__count"><span aria-hidden="true">3</span><span class="visually-hidden">3 active</span></span></button>', $html);
        self::assertMatchesRegularExpression('#<form[^>]*method="get"[^>]*class="nowo-ui-filter-sheet__form"|<form[^>]*class="nowo-ui-filter-sheet__form"[^>]*method="get"#', $html);
        self::assertStringContainsString('<a class="nowo-ui-btn nowo-ui-btn--link" href="/invoices">Reset</a>', $html);
        self::assertStringContainsString('<dialog id="fb-dialog" class="nowo-ui-filter-sheet__dialog" aria-labelledby="fb-title" data-nowo-ui-filter-sheet-dialog>', $html);
        self::assertStringContainsString('<h2 id="fb-title" class="nowo-ui-filter-sheet__dialog-title">Filters</h2>', $html);
        self::assertStringContainsString('name="page"', $html);
        self::assertStringNotContainsString('style=', $html);

        // Explicit count, heading level and labels; no count = no badge.
        $html = $this->render("{{ include(nowo_ui_partial('filter_bar'), {form: form, active_count: 0, heading_level: 9, title: 'Refine', submit_label: 'Go', close_label: 'Done'}) }}", ['form' => $this->searchForm()]);
        self::assertStringContainsString('data-nowo-ui-filter-sheet-open>Refine</button>', $html);
        self::assertStringContainsString('<h2 id="nowo-ui-filter-bar-title"', $html);
        self::assertStringContainsString('>Go</button>', $html);
        self::assertStringContainsString('>Done</button>', $html);
        self::assertStringNotContainsString('href="', $html);
    }

    public function testFilterBarBlocksForExtraContentAndActions(): void
    {
        $html = $this->render("{% embed nowo_ui_partial('filter_bar') with {form: form} %}{% block filter_bar_extra %}<i data-extra></i>{% endblock %}{% block filter_bar_actions %}<i data-more></i>{% endblock %}{% endembed %}", ['form' => $this->searchForm()]);
        self::assertStringContainsString('<i data-extra></i>', $html);
        self::assertStringContainsString('<i data-more></i>', $html);
    }

    public function testBulkBarAndToggle(): void
    {
        $html = $this->render("{{ include(nowo_ui_partial('bulk_bar'), {form_id: 'bulk', total: 42, all_field: 'bulk_all', actions: [{label: 'Archive', value: 'archive'}, {label: 'Delete', value: 'delete', variant: 'danger', name: 'op', attr: {'data-testid': 'del', class: 'x'}}]}) }}");
        self::assertStringContainsString('<div class="nowo-ui-bulk-bar" role="region" aria-label="Bulk actions" data-nowo-ui-bulk-bar data-nowo-ui-bulk-scope="bulk"', $html);
        self::assertStringContainsString('data-nowo-ui-bulk-many="%count% rows selected"', $html);
        self::assertStringContainsString('data-nowo-ui-bulk-total="42" data-nowo-ui-bulk-all-field="bulk_all" data-nowo-ui-bulk-all-pages="All 42 rows selected"', $html);
        self::assertStringContainsString('<p class="nowo-ui-bulk-bar__count" role="status" data-nowo-ui-bulk-status>No rows selected</p>', $html);
        self::assertStringContainsString('data-nowo-ui-bulk-select-all-pages hidden>Select all 42 rows</button>', $html);
        self::assertStringContainsString('<button type="submit" form="bulk" name="bulk_action" value="archive" class="nowo-ui-btn nowo-ui-btn--outline nowo-ui-btn--sm" data-nowo-ui-bulk-action>Archive</button>', $html);
        self::assertStringContainsString('<button type="submit" form="bulk" name="op" value="delete" class="nowo-ui-btn nowo-ui-btn--danger nowo-ui-btn--sm x" data-nowo-ui-bulk-action data-testid="del">Delete</button>', $html);
        self::assertStringContainsString('data-nowo-ui-bulk-clear hidden>Clear selection</button>', $html);

        $plain = $this->render("{% embed nowo_ui_partial('bulk_bar') with {form_id: 'f', label: 'Rows'} %}{% block bulk_bar_actions %}<i data-own></i>{% endblock %}{% endembed %}");
        self::assertStringContainsString('aria-label="Rows"', $plain);
        self::assertStringContainsString('<i data-own></i>', $plain);
        self::assertStringNotContainsString('data-nowo-ui-bulk-total', $plain);
        self::assertStringNotContainsString('select-all-pages', $plain);

        self::assertSame(
            '<input type="checkbox" class="nowo-ui-bulk-toggle" aria-label="Select every row on this page" data-nowo-ui-bulk-all hidden>',
            trim($this->render("{{ include(nowo_ui_partial('bulk_toggle')) }}")),
        );
        self::assertStringContainsString('aria-label="All invoices"', $this->render("{{ include(nowo_ui_partial('bulk_toggle'), {label: 'All invoices'}) }}"));
    }

    public function testTableViewControls(): void
    {
        $html = $this->render("{{ include(nowo_ui_partial('table_view'), {table: 't', persist: 'inv', density: 'compact', labels: {columns: 'Show', density: null}, columns: [{key: 'n', label: 'Number', locked: true}, {key: 'c', label: 'Customer', visible: false}]}) }}");
        self::assertStringContainsString('<div class="nowo-ui-table-view" data-nowo-ui-table-view data-nowo-ui-table-view-table="t" data-nowo-ui-table-view-persist="inv" hidden>', $html);
        self::assertStringContainsString('<div class="nowo-ui-table-view__group" role="group" aria-label="Row density">', $html);
        self::assertStringContainsString('aria-pressed="false" data-nowo-ui-density="comfortable">Comfortable</button>', $html);
        self::assertStringContainsString('aria-pressed="true" data-nowo-ui-density="compact">Compact</button>', $html);
        self::assertStringContainsString('<summary class="nowo-ui-btn nowo-ui-btn--outline nowo-ui-btn--sm">Show</summary>', $html);
        self::assertStringContainsString('aria-pressed="true" data-nowo-ui-column="n" disabled><span class="nowo-ui-table-view__check" aria-hidden="true"></span>Number</button>', $html);
        self::assertStringContainsString('aria-pressed="false" data-nowo-ui-column="c">', $html);

        // Invalid persist keys are dropped; no columns = no menu.
        $html = $this->render("{{ include(nowo_ui_partial('table_view'), {table: 't', persist: 'bad key!'}) }}");
        self::assertStringNotContainsString('persist', $html);
        self::assertStringNotContainsString('<details', $html);
        self::assertStringContainsString('aria-pressed="true" data-nowo-ui-density="comfortable"', $html);
    }

    public function testTabPanelsAreProgressive(): void
    {
        $html = $this->render("{{ include(nowo_ui_partial('tab_panels'), {id: 'inv', label: 'Invoice', active: 'lines', heading_level: 2, items: [{id: 'details', label: 'Details', content: '<p>A</p>'}, {id: 'lines', label: 'Lines', content_template: 'part', content_context: {x: '<b>'}}]}) }}", [], ['part' => '<p>{{ x }}</p>']);
        self::assertStringContainsString('<div id="inv" class="nowo-ui-tab-panels" data-nowo-ui-tabs data-nowo-ui-tabs-upgrade data-nowo-ui-tabs-active="inv-lines">', $html);
        self::assertStringContainsString('<div role="tablist" class="nowo-ui-tab-panels__list" aria-label="Invoice" data-nowo-ui-tabs-list hidden>', $html);
        self::assertStringContainsString('<button type="button" role="tab" id="tab-inv-details" class="nowo-ui-tab-panels__tab" aria-controls="tab-inv-details-panel" aria-selected="false" tabindex="-1" data-nowo-ui-tabs-trigger data-nowo-ui-tab-id="inv-details">Details</button>', $html);
        self::assertStringContainsString('aria-selected="true" tabindex="0" data-nowo-ui-tabs-trigger data-nowo-ui-tab-id="inv-lines" data-active>Lines</button>', $html);
        self::assertStringContainsString('<section id="tab-inv-details-panel" class="nowo-ui-tab-panels__panel" tabindex="0" aria-labelledby="tab-inv-details-heading" data-nowo-ui-tabs-panel data-nowo-ui-tab-id="inv-details">', $html);
        self::assertStringContainsString('<h2 id="tab-inv-details-heading" class="nowo-ui-tab-panels__heading" data-nowo-ui-tabs-legend>Details</h2>', $html);
        self::assertStringContainsString('<p>A</p>', $html);
        self::assertStringContainsString('<p>&lt;b&gt;</p>', $html);
        // Defaults: first item active, tabs.nav label, h3 headings.
        $html = $this->render("{{ include(nowo_ui_partial('tab_panels'), {id: 'x', heading_level: 'h1', items: [{id: 'a', label: 'A'}]}) }}");
        self::assertStringContainsString('data-nowo-ui-tabs-active="x-a"', $html);
        self::assertStringContainsString('aria-label="Sections"', $html);
        self::assertStringContainsString('<h3 id="tab-x-a-heading"', $html);
    }

    public function testErrorSummaryLinksEveryErrorToItsField(): void
    {
        $form = PageContractTwig::forms()->createNamedBuilder('customer', FormType::class, null, [])
            ->add('name', TextType::class, ['label' => 'Company name'])
            ->add('email', TextType::class)
            ->add('plan', ChoiceType::class, ['expanded' => true, 'choices' => ['A' => 'a', 'B' => 'b']])
            ->add('ok', TextType::class)
            ->getForm();
        $form->submit([]);
        $form->addError(new FormError('The form is out of date.'));
        $form->get('name')->addError(new FormError('Enter the <company> name.'));
        $form->get('email')->addError(new FormError('Enter an email.'));
        $form->get('plan')->addError(new FormError('Choose a plan.'));
        $html = $this->render("{{ include(nowo_ui_partial('error_summary'), {form: form, attr: {'data-testid': 'es'}}) }}", ['form' => $form->createView()]);

        self::assertStringContainsString('<div class="nowo-ui-error-summary" tabindex="-1" aria-labelledby="customer-error-summary-title" data-nowo-ui-error-summary data-testid="es">', $html);
        self::assertStringContainsString('<h2 id="customer-error-summary-title" class="nowo-ui-error-summary__title">There is a problem</h2>', $html);
        self::assertStringContainsString('<li>The form is out of date.</li>', $html);
        self::assertStringContainsString('<li><a href="#customer_name">Company name: Enter the &lt;company&gt; name.</a></li>', $html);
        self::assertStringContainsString('<li><a href="#customer_email">Email: Enter an email.</a></li>', $html);
        // An expanded choice links to its first box.
        self::assertStringContainsString('<li><a href="#customer_plan_0">Plan: Choose a plan.</a></li>', $html);
        self::assertStringNotContainsString('Ok:', $html);

        // A valid form renders nothing.
        self::assertSame('', trim($this->render("{{ include(nowo_ui_partial('error_summary'), {form: form}) }}", ['form' => $this->searchForm()])));
        self::assertStringContainsString('<h3 id="customer-error-summary-title" class="nowo-ui-error-summary__title">Fix these</h3>', $this->render("{{ include(nowo_ui_partial('error_summary'), {form: form, title: 'Fix these', heading_level: 3}) }}", ['form' => $form->createView()]));
    }

    public function testFieldsetGroupsAChoiceUnderItsLegend(): void
    {
        $form = PageContractTwig::forms()->createNamedBuilder('c', FormType::class, null, [])
            ->add('plan', ChoiceType::class, ['expanded' => true, 'choices' => ['Starter' => 's', 'Business' => 'b'], 'help' => 'Change it any time.', 'attr' => ['data-x' => 'y']])
            ->getForm();
        $form->submit([]);
        $form->get('plan')->addError(new FormError('Choose a plan.'));
        $html = $this->render("{% form_theme f '@NowoUiKitBundle/form/nowo_ui_layout.html.twig' %}{{ include(nowo_ui_partial('form_fieldset'), {field: f.plan, inline: true, boxed: true}) }}", ['f' => $form->createView()]);
        self::assertStringContainsString('<fieldset class="nowo-ui-fieldset nowo-ui-fieldset--boxed" aria-describedby="c_plan_help c_plan_error1" data-nowo-ui-invalid>', $html);
        self::assertStringContainsString('<legend class="nowo-ui-fieldset__legend">Plan</legend>', $html);
        self::assertMatchesRegularExpression('#<div id="c_plan" data-x="y" class="nowo-ui-choice-group nowo-ui-choice-group--inline"#', $html);
        self::assertStringContainsString('Change it any time.', $html);
        self::assertStringContainsString('Choose a plan.', $html);

        $plain = PageContractTwig::forms()->createNamedBuilder('d', FormType::class, null, [])->add('plan', ChoiceType::class, ['expanded' => true, 'choices' => ['A' => 'a']])->getForm()->createView();
        $html = $this->render("{{ include(nowo_ui_partial('form_fieldset'), {field: f.plan, legend: 'Pick one'}) }}", ['f' => $plain]);
        self::assertStringContainsString('<fieldset class="nowo-ui-fieldset">', $html);
        self::assertStringContainsString('>Pick one</legend>', $html);
    }

    public function testDropzoneEnhancesTheFileInput(): void
    {
        $form = PageContractTwig::forms()->createNamedBuilder('c', FormType::class, null, [])
            ->add('contract', FileType::class, ['multiple' => true, 'required' => false, 'attr' => ['accept' => '.pdf']])
            ->getForm()->createView();
        $html = $this->render("{{ include(nowo_ui_partial('dropzone'), {field: f.contract, hint: 'PDF, 5 MB', max_bytes: 5242880}) }}", ['f' => $form]);
        self::assertStringContainsString('<label for="c_contract">Contract</label>', $html);
        self::assertStringContainsString('<div class="nowo-ui-dropzone" data-nowo-ui-dropzone data-nowo-ui-dropzone-max="5242880"', $html);
        self::assertStringContainsString('data-nowo-ui-dropzone-too-large="%name% is larger than %size%"', $html);
        self::assertMatchesRegularExpression('#<input type="file" id="c_contract" name="c\[contract\]\[\]"[^>]*accept="\.pdf"#', $html);
        self::assertMatchesRegularExpression('#<input type="file" id="c_contract"[^>]* aria-describedby="c_contract_dropzone_hint c_contract_dropzone_status"#', $html);
        self::assertStringContainsString('<p class="nowo-ui-dropzone__hint" id="c_contract_dropzone_hint">PDF, 5 MB</p>', $html);
        self::assertStringContainsString('<p class="visually-hidden" id="c_contract_dropzone_status" role="status" data-nowo-ui-dropzone-status></p>', $html);
        self::assertStringContainsString('Drag files here or click to choose', $html);

        $form = PageContractTwig::forms()->createNamedBuilder('c', FormType::class)->add('contract', FileType::class, ['required' => false])->getForm()->createView();
        $html = $this->render("{{ include(nowo_ui_partial('dropzone'), {field: f.contract, prompt: 'Drop the contract'}) }}", ['f' => $form]);
        self::assertStringNotContainsString('data-nowo-ui-dropzone-max', $html);
        self::assertStringContainsString('>Drop the contract</p>', $html);
        self::assertStringContainsString('aria-describedby="c_contract_dropzone_status"', $html);
    }

    public function testTimelineSkeletonAvatarGroupAndChip(): void
    {
        $html = $this->render("{{ include(nowo_ui_partial('timeline'), {label: 'Activity', heading_level: 3, items: [{title: 'Paid <now>', datetime: '2026-11-04T09:12', time: '4 Nov', text: 'By transfer', tone: 'success', meta: '<i data-meta></i>'}, {title: 'Issued', tone: 'bogus', meta_template: 'm', meta_context: {v: 1}}]}) }}", [], ['m' => '<b data-v="{{ v }}"></b>']);
        self::assertStringContainsString('<ol class="nowo-ui-timeline" aria-label="Activity">', $html);
        self::assertStringContainsString('<li class="nowo-ui-timeline__item nowo-ui-timeline__item--success">', $html);
        self::assertStringContainsString('<h3 class="nowo-ui-timeline__title">Paid &lt;now&gt;</h3>', $html);
        self::assertStringContainsString('<time class="nowo-ui-timeline__time" datetime="2026-11-04T09:12">4 Nov</time>', $html);
        self::assertStringContainsString('<p class="nowo-ui-timeline__text">By transfer</p>', $html);
        self::assertStringContainsString('<div class="nowo-ui-timeline__meta"><i data-meta></i></div>', $html);
        self::assertStringContainsString('<li class="nowo-ui-timeline__item nowo-ui-timeline__item--neutral">', $html);
        self::assertStringContainsString('<b data-v="1"></b>', $html);
        self::assertStringContainsString('<p class="nowo-ui-timeline__title">x</p>', $this->render("{{ include(nowo_ui_partial('timeline'), {items: [{title: 'x'}]}) }}"));

        $html = $this->render("{{ include(nowo_ui_partial('skeleton'), {variant: 'list', rows: 2, label: 'Loading payments'}) }}");
        self::assertStringContainsString('<div class="nowo-ui-skeleton nowo-ui-skeleton--list" role="region" aria-busy="true" aria-label="Loading payments" data-nowo-ui-skeleton>', $html);
        self::assertStringContainsString('<p class="visually-hidden" role="status">Loading payments</p>', $html);
        self::assertSame(2, substr_count($html, 'nowo-ui-skeleton__circle'));
        self::assertSame(3, substr_count($this->render("{{ include(nowo_ui_partial('skeleton')) }}"), 'nowo-ui-skeleton__line'));
        self::assertSame(1, substr_count($this->render("{{ include(nowo_ui_partial('skeleton'), {variant: 'card', lines: 1}) }}"), 'nowo-ui-skeleton__line'));
        self::assertSame(6, substr_count($this->render("{{ include(nowo_ui_partial('skeleton'), {variant: 'table', rows: 2}) }}"), 'nowo-ui-skeleton__line'));
        self::assertStringContainsString('nowo-ui-skeleton--text', $this->render("{{ include(nowo_ui_partial('skeleton'), {variant: 'bogus'}) }}"));
        self::assertStringContainsString('aria-label="Loading…"', $this->render("{{ include(nowo_ui_partial('skeleton')) }}"));

        $html = $this->render("{{ include(nowo_ui_partial('avatar_group'), {max: 2, people: [{name: 'Ada Lovelace', presence: 'online'}, {name: 'Grace Hopper', presence: 'bogus'}, {name: 'Alan Turing'}]}) }}");
        self::assertStringContainsString('<ul class="nowo-ui-avatar-group" aria-label="3 people">', $html);
        self::assertStringContainsString('<span class="nowo-ui-presence nowo-ui-presence--online"><span class="nowo-ui-avatar" role="img" aria-label="Ada Lovelace (online)">AL</span><span class="nowo-ui-presence__dot" aria-hidden="true"></span></span>', $html);
        self::assertStringContainsString('<span class="nowo-ui-avatar" role="img" aria-label="Grace Hopper">GH</span>', $html);
        self::assertStringContainsString('<span class="nowo-ui-avatar-group__more" role="img" aria-label="1 more">+1</span>', $html);
        self::assertStringNotContainsString('Alan', $html);

        $html = $this->render("{{ include(nowo_ui_partial('chip'), {label: 'Status: <due>', remove_href: '/x?a=1&b=2', avatar: {name: 'Ada Lovelace'}}) }}");
        self::assertStringContainsString('<span class="nowo-ui-chip"><span class="nowo-ui-avatar nowo-ui-avatar--xs" aria-hidden="true">AL</span><span class="nowo-ui-chip__label">Status: &lt;due&gt;</span><a class="nowo-ui-chip__remove" href="/x?a=1&amp;b=2" aria-label="Remove Status: &lt;due&gt;"><span aria-hidden="true">&times;</span></a></span>', $html);
        self::assertStringContainsString('aria-label="Drop it"', $this->render("{{ include(nowo_ui_partial('chip'), {label: 'x', remove_href: '/', remove_label: 'Drop it'}) }}"));
    }

    public function testCodeBlockTooltipAndSteps(): void
    {
        $html = $this->render("{{ include(nowo_ui_partial('code_block'), {id: 'c1', language: 'YAML', code: 'a: <b>', wrap: true}) }}");
        self::assertStringContainsString('<button type="button" class="nowo-ui-btn nowo-ui-btn--outline nowo-ui-btn--sm" data-nowo-ui-clipboard data-nowo-ui-clipboard-target="c1" data-nowo-ui-clipboard-label="Copy code" data-nowo-ui-clipboard-done-label="Copied" aria-label="Copy code" data-nowo-ui-clipboard-reveal hidden>Copy code</button>', $html);
        self::assertStringContainsString('<pre class="nowo-ui-code-block nowo-ui-code-block--wrap" tabindex="0" aria-label="YAML"><code id="c1">a: &lt;b&gt;</code></pre>', $html);
        self::assertMatchesRegularExpression('#<code id="nowo-ui-code-\d+">x</code>#', $this->render("{{ include(nowo_ui_partial('code_block'), {code: 'x'}) }}"));

        $html = $this->render("{% embed nowo_ui_partial('tooltip') with {text: 'Exports <all>', id: 'tip'} %}{% block tooltip_trigger %}<button aria-describedby=\"{{ tooltip_id }}\">Export</button>{% endblock %}{% endembed %}");
        self::assertStringContainsString('<span class="nowo-ui-tooltip" data-nowo-ui-tooltip><button aria-describedby="tip">Export</button><span class="nowo-ui-tooltip__bubble" id="tip" role="tooltip">Exports &lt;all&gt;</span>', $html);
        self::assertMatchesRegularExpression('#<span class="nowo-ui-tooltip" data-nowo-ui-tooltip><b>t</b><span class="nowo-ui-tooltip__bubble" id="nowo-ui-tooltip-\d+" role="tooltip">Help</span>#', $this->render("{{ include(nowo_ui_partial('tooltip'), {text: 'Help', trigger: '<b>t</b>'}) }}"));

        $html = $this->render("{{ include(nowo_ui_partial('steps'), {label: 'Billing', items: [{label: 'Company', status: 'done', href: '/c', hint: 'Name'}, {label: 'Bank', status: 'current'}, {label: 'Review'}, {label: 'Odd', status: 'bogus'}]}) }}");
        self::assertStringContainsString('<ol class="nowo-ui-steps" aria-label="Billing">', $html);
        self::assertStringContainsString('<li class="nowo-ui-steps__item nowo-ui-steps__item--done">', $html);
        self::assertStringContainsString('<a class="nowo-ui-steps__link" href="/c">', $html);
        self::assertStringContainsString('Company<span class="visually-hidden"> (completed)</span>', $html);
        self::assertStringContainsString('<span class="nowo-ui-steps__hint">Name</span>', $html);
        self::assertStringContainsString('<li class="nowo-ui-steps__item nowo-ui-steps__item--current" aria-current="step">', $html);
        self::assertStringContainsString('Bank<span class="visually-hidden"> (current)</span>', $html);
        self::assertSame(2, substr_count($html, '(not started)'));
        self::assertStringContainsString('aria-label="Steps"', $this->render("{{ include(nowo_ui_partial('steps'), {items: []}) }}"));
    }

    public function testStatTilesWithDeltaTrendAndIcon(): void
    {
        $html = $this->render("{{ include(nowo_ui_partial('stat_row'), {items: stats, attr: {class: 'nowo-ui-stat-row--tiles'}}) }}", ['stats' => [
            new Stat('Revenue', '48 k€', 'vs. last month', delta: '+12 %', trend: 'up', icon: 'export'),
            new Stat('Errors', 4, delta: '+1', trend: 'up', deltaTone: 'negative'),
            new Stat('Open', 14, delta: '−3', trend: 'down'),
            new Stat('Users', 9, delta: '0', trend: 'flat'),
            new Stat('Plain', 1, delta: 'n/a'),
            new Stat('Old', 2),
        ]]);
        self::assertStringContainsString('nowo-ui-stat-row nowo-ui-stat-row--tiles', $html);
        self::assertStringContainsString('<dd class="nowo-ui-stat__delta nowo-ui-stat__delta--up nowo-ui-stat__delta--positive"><svg class="nowo-ui-stat__delta-arrow" viewBox="0 0 12 12" aria-hidden="true" focusable="false"><path d="M6 1.5 11 8H1z"/></svg><span class="visually-hidden">Up </span>+12 %</dd>', $html);
        self::assertStringContainsString('nowo-ui-stat__delta--up nowo-ui-stat__delta--negative', $html);
        self::assertStringContainsString('nowo-ui-stat__delta--down nowo-ui-stat__delta--negative"><svg', $html);
        self::assertStringContainsString('<span class="visually-hidden">No change </span>0</dd>', $html);
        self::assertStringContainsString('<dd class="nowo-ui-stat__delta nowo-ui-stat__delta--flat nowo-ui-stat__delta--neutral">n/a</dd>', $html);
        self::assertStringContainsString('<dd class="nowo-ui-stat__icon" aria-hidden="true">', $html);
        self::assertSame(5, substr_count($html, 'nowo-ui-stat__delta '));
        self::assertSame(1, substr_count($html, 'nowo-ui-stat__icon'));

        self::assertSame('negative', (new Stat('a', 1, trend: 'down'))->resolvedDeltaTone());
        self::assertSame('neutral', (new Stat('a', 1))->resolvedDeltaTone());
        self::assertSame('positive', (new Stat('a', 1, trend: 'down', deltaTone: 'positive'))->resolvedDeltaTone());
    }

    public function testStatRejectsUnknownTrendAndDeltaTone(): void
    {
        try {
            new Stat('a', 1, trend: 'sideways');
            self::fail('trend');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('Invalid Stat trend "sideways"', $e->getMessage());
        }
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Stat delta tone "good"');
        new Stat('a', 1, deltaTone: 'good');
    }

    public function testIllustratedEmptyState(): void
    {
        $html = $this->render("{{ include(nowo_ui_partial('empty'), {empty: empty}) }}", ['empty' => new EmptyState('No credit notes', 'Issue one from an invoice.', Action::create('/new'), icon: 'create')]);
        self::assertStringContainsString('nowo-ui-empty--illustrated"', $html);
        self::assertStringContainsString('<span class="nowo-ui-empty__icon" aria-hidden="true">', $html);
        self::assertStringContainsString('<p class="nowo-ui-empty__title">No credit notes</p>', $html);
        // Without an icon: the 2.60 markup.
        $plain = $this->render("{{ include(nowo_ui_partial('empty'), {empty: empty}) }}", ['empty' => new EmptyState('No credit notes')]);
        self::assertStringNotContainsString('illustrated', $plain);
        self::assertStringNotContainsString('nowo-ui-empty__icon', $plain);
    }

    public function testClientToastsPrintTheStackAndTheTemplate(): void
    {
        $html = $this->render("{{ include(nowo_ui_partial('toasts'), {client: true, flashes: {}, timeouts: {error: 0}}) }}");
        self::assertStringContainsString('data-nowo-ui-toast-stack data-nowo-ui-toast-client', $html);
        self::assertStringContainsString('aria-live="polite"', $html);
        self::assertStringContainsString('<template data-nowo-ui-toast-template>', $html);
        self::assertStringContainsString('data-timeout="5000" data-timeout-urgent="0"', $html);
        self::assertStringContainsString('<p class="nowo-ui-toast__message" data-nowo-ui-toast-message></p>', $html);
        self::assertStringContainsString('aria-label="Dismiss notification"', $html);
        // Default (client false): the flashes only, no template, no client marker.
        $flashes = $this->render("{{ include(nowo_ui_partial('toasts'), {flashes: {success: ['Saved.']}}) }}");
        self::assertStringContainsString('Saved.', $flashes);
        self::assertStringNotContainsString('data-nowo-ui-toast-template', $flashes);
        self::assertStringNotContainsString('data-nowo-ui-toast-client', $flashes);
    }

    public function testNoInlineStyleOrHandlerAndTheSameOutputOnAReusedEnvironment(): void
    {
        $twig = PageContractTwig::create(['t' => "{{ include(nowo_ui_partial('steps'), {items: items}) }}{{ include(nowo_ui_partial('timeline'), {items: items|map(i => {title: i.label})}) }}{{ include(nowo_ui_partial('bulk_bar'), {form_id: id}) }}"]);
        $a = $twig->render('t', ['items' => [['label' => 'One', 'status' => 'current']], 'id' => 'a']);
        $b = $twig->render('t', ['items' => [['label' => 'Two']], 'id' => 'b']);
        $a2 = $twig->render('t', ['items' => [['label' => 'One', 'status' => 'current']], 'id' => 'a']);
        self::assertSame($a, $a2);
        self::assertStringNotContainsString('One', $b);
        self::assertStringNotContainsString('aria-current', $b);
        foreach (self::PARTIALS as $name) {
            $source = (string) file_get_contents(PageContractTwig::root().'/src/Resources/views/partials/_'.$name.'.html.twig');
            self::assertDoesNotMatchRegularExpression('#\sstyle=|\son[a-z]+=#', $source, $name);
        }
    }

    public function testTheAdminCssUsesTokensOnlyAndRespectsReducedMotion(): void
    {
        $css = (string) file_get_contents(PageContractTwig::root().'/src/Resources/public/css/nowo-ui.unlayered.css');
        $start = strpos($css, 'Admin components (2.61)');
        $end = strpos($css, '/* --- Locale switcher --- */');
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $section = substr($css, $start, $end - $start);
        self::assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}\b|rgba?\(|hsla?\(/', $section, 'colour literals in the 2.61 section');
        foreach (['.nowo-ui-filter-sheet', '.nowo-ui-bulk-bar', '.nowo-ui-table-view', '.nowo-ui-dl', '.nowo-ui-stat-row--tiles', '.nowo-ui-tab-panels', '.nowo-ui-error-summary', '.nowo-ui-fieldset', '.nowo-ui-choice-group', '.nowo-ui-input-group', '.nowo-ui-form-row--switch', '.nowo-ui-form-grid', '.nowo-ui-card--sticky-footer', '.nowo-ui-dropzone', '.nowo-ui-timeline', '.nowo-ui-skeleton', '.nowo-ui-empty--illustrated', '.nowo-ui-avatar-group', '.nowo-ui-chip', ".nowo-ui-btn[aria-busy='true']", '.nowo-ui-code-panel', '.nowo-ui-tooltip', '.nowo-ui-steps'] as $selector) {
            self::assertStringContainsString($selector, $section);
        }
        // Every animation / transition sits behind the motion guards.
        preg_match_all('/@media \(prefers-reduced-motion: no-preference\) \{\s*:root:not\(\[data-motion=\'reduce\'\]\)/', $section, $guards);
        self::assertSame(substr_count($section, 'animation:') + substr_count($section, 'transition:'), \count($guards[0]));
        self::assertStringContainsString($section, (string) file_get_contents(PageContractTwig::root().'/src/Resources/public/css/nowo-ui.css'));
    }

    /**
     * @param array<string, mixed>  $context
     * @param array<string, string> $templates
     */
    private function render(string $source, array $context = [], array $templates = []): string
    {
        return $this->twig(['t' => $source] + $templates)->render('t', $context);
    }

    /**
     * @param array<string, string> $templates
     */
    private function twig(array $templates): Environment
    {
        return PageContractTwig::create($templates, framework: 'custom');
    }

    private function searchForm(): FormView
    {
        return PageContractTwig::forms()->createNamedBuilder('f', FormType::class, null, ['method' => 'GET'])->add('q', SearchType::class, ['required' => false])->getForm()->createView();
    }
}
