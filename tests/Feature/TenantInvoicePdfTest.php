<?php

use App\Actions\Invoices\GenerateInvoicePdf;
use App\Enums\InvoiceStatus;
use App\Jobs\GenerateInvoicePdfArtifact;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\Invoices\InvoicePdfArtifact;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

uses()->beforeEach(function () {
    Setting::set('invoice_pdf_enabled', true, 'boolean');
    Storage::fake('local');
});

function prepareTenantInvoicePdf(Invoice $invoice): void
{
    $artifact = app(InvoicePdfArtifact::class);

    GenerateInvoicePdfArtifact::dispatchSync(
        $invoice->getKey(),
        $artifact->fingerprint($invoice),
    );
}

function createTenantInvoicePdfFixture(?User $user = null): array
{
    $user ??= User::factory()->create();
    $tenant = Tenant::factory()->withUser($user)->create([
        'phone' => '+62 812 3456 7890',
    ]);
    $user->update(['email' => 'john@example.com']);
    $property = Property::factory()->create([
        'name' => 'Kos Sriwijaya',
        'address' => 'Jalan Merdeka 10',
        'postal_code' => '40123',
    ]);
    $unit = Unit::factory()->create([
        'property_id' => $property->id,
        'name' => 'Unit A-01',
    ]);
    $lease = Lease::factory()->create([
        'primary_tenant_id' => $tenant->id,
        'unit_id' => $unit->id,
    ]);
    $invoice = Invoice::factory()->create([
        'lease_id' => $lease->id,
        'period_start' => '2026-08-01',
        'period_end' => '2026-08-31',
        'due_date' => '2026-08-05',
        'total' => '1500000.75',
        'amount_paid' => '0.00',
        'status' => InvoiceStatus::Pending,
    ]);

    return compact('invoice', 'lease', 'property', 'tenant', 'unit', 'user');
}

function renderTenantInvoicePdfView(Invoice $invoice): string
{
    return view('invoices.pdf', [
        'currency' => 'IDR',
        'invoice' => $invoice,
        'locale' => 'id',
        'siteName' => 'OpenKOS',
    ])->render();
}

test('tenant downloads their current invoice as a PDF', function () {
    $fixture = createTenantInvoicePdfFixture();
    $invoice = $fixture['invoice'];
    $invoice->lineItems()->create([
        'type' => 'rent',
        'description' => 'Rent August 2026',
        'amount' => $invoice->total,
    ]);
    prepareTenantInvoicePdf($invoice);

    $response = $this->actingAs($fixture['user'])
        ->get(route('portal.billing.invoices.download', $invoice));

    $response
        ->assertOk()
        ->assertStreamed()
        ->assertDownload("invoice-{$invoice->reference}.pdf")
        ->assertHeader('content-type', 'application/pdf');

    expect($response->streamedContent())->toStartWith('%PDF-');
});

test('invoice PDF download preserves tenant ownership rules', function () {
    $fixture = createTenantInvoicePdfFixture();
    $otherUser = User::factory()->create();
    Tenant::factory()->withUser($otherUser)->create();

    $this->actingAs($otherUser)
        ->get(route('portal.billing.invoices.download', $fixture['invoice']))
        ->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->get(route('portal.billing.invoices.download', $fixture['invoice']))
        ->assertForbidden();
});

test('tenant can open a print-ready invoice page', function () {
    $fixture = createTenantInvoicePdfFixture();
    $invoice = $fixture['invoice'];
    $invoice->lineItems()->create([
        'type' => 'rent',
        'description' => 'Rent August 2026',
        'amount' => $invoice->total,
    ]);

    $this->actingAs($fixture['user'])
        ->get(route('portal.billing.invoices.print', $invoice))
        ->assertOk()
        ->assertSee($invoice->reference)
        ->assertSee('Rent August 2026')
        ->assertSee('window.print()');
});

test('invoice print pages use the canonical fallback locale', function () {
    Setting::set('locale', 'fr');
    $fixture = createTenantInvoicePdfFixture();

    $this->actingAs($fixture['user'])
        ->get(route('portal.billing.invoices.print', $fixture['invoice']))
        ->assertOk()
        ->assertSee('<html lang="en">', false);
});

test('invoice print page preserves tenant ownership rules', function () {
    $fixture = createTenantInvoicePdfFixture();
    $otherUser = User::factory()->create();
    Tenant::factory()->withUser($otherUser)->create();

    $this->actingAs($otherUser)
        ->get(route('portal.billing.invoices.print', $fixture['invoice']))
        ->assertNotFound();
});

test('disabled invoice PDFs leave the invoice page usable without queueing work', function () {
    Setting::set('invoice_pdf_enabled', false, 'boolean');
    Bus::fake();
    $fixture = createTenantInvoicePdfFixture();

    $this->actingAs($fixture['user'])
        ->get(route('portal.billing.invoices.show', $fixture['invoice']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('invoicePdf.status', 'disabled'));

    Bus::assertNotDispatched(GenerateInvoicePdfArtifact::class);

    $this->actingAs($fixture['user'])
        ->get(route('portal.billing.invoices.download', $fixture['invoice']))
        ->assertRedirect(route('portal.billing.invoices.show', $fixture['invoice']));
});

test('enabled invoice PDFs queue when the private artifact is missing', function () {
    Bus::fake();
    $fixture = createTenantInvoicePdfFixture();

    $this->actingAs($fixture['user'])
        ->get(route('portal.billing.invoices.show', $fixture['invoice']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('invoicePdf.status', 'pending'));

    Bus::assertDispatched(GenerateInvoicePdfArtifact::class);
});

test('invoice PDF artifacts are reused until rendered invoice data changes', function () {
    $fixture = createTenantInvoicePdfFixture();
    $invoice = $fixture['invoice'];
    prepareTenantInvoicePdf($invoice);

    $this->actingAs($fixture['user'])
        ->get(route('portal.billing.invoices.download', $invoice))
        ->assertOk();

    $invoice->lineItems()->create([
        'type' => 'rent',
        'description' => 'Updated charge',
        'amount' => '100.00',
    ]);

    Bus::fake();
    $this->actingAs($fixture['user'])
        ->get(route('portal.billing.invoices.show', $invoice))
        ->assertInertia(fn ($page) => $page->where('invoicePdf.status', 'pending'));

    Bus::assertDispatched(GenerateInvoicePdfArtifact::class);
});

test('invoice PDF fingerprints use canonical locale values', function () {
    $fixture = createTenantInvoicePdfFixture();
    $artifact = app(InvoicePdfArtifact::class);

    Setting::set('locale', 'id-ID');
    $aliasFingerprint = $artifact->fingerprint($fixture['invoice']);

    Setting::set('locale', 'id');
    expect($artifact->fingerprint($fixture['invoice']))
        ->toBe($aliasFingerprint);
});

test('invoice PDF fingerprints change when translations change', function () {
    $fixture = createTenantInvoicePdfFixture();
    $artifact = app(InvoicePdfArtifact::class);
    $path = lang_path('id.json');
    $original = file_get_contents($path);

    expect($original)->toBeString();

    try {
        $before = $artifact->fingerprint($fixture['invoice']);
        file_put_contents($path, $original."\n");
        $after = $artifact->fingerprint($fixture['invoice']);
    } finally {
        file_put_contents($path, $original);
    }

    expect($after)->not->toBe($before);
});

test('invoice PDF fingerprints change when the display timezone changes', function () {
    $fixture = createTenantInvoicePdfFixture();
    $artifact = app(InvoicePdfArtifact::class);
    $originalTimezone = config('app.display_timezone');

    try {
        config(['app.display_timezone' => 'UTC']);
        $before = $artifact->fingerprint($fixture['invoice']);

        config(['app.display_timezone' => 'Asia/Jakarta']);
        $after = $artifact->fingerprint($fixture['invoice']);
    } finally {
        config(['app.display_timezone' => $originalTimezone]);
    }

    expect($after)->not->toBe($before);
});

test('invoice PDF view reflects the current aggregate for each payable state', function (
    string $paymentAmount,
    InvoiceStatus $expectedStatus,
) {
    $this->travelTo('2026-08-01');

    $fixture = createTenantInvoicePdfFixture();
    $invoice = $fixture['invoice'];
    if ($expectedStatus === InvoiceStatus::Cancelled) {
        $invoice->update(['status' => InvoiceStatus::Cancelled]);
    }
    $invoice->lineItems()->create([
        'type' => 'rent',
        'description' => 'Rent <script>alert("x")</script>',
        'amount' => $invoice->total,
    ]);

    if ($paymentAmount !== '0.00') {
        Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => $paymentAmount,
        ]);
    }

    $invoice->recalculateStatus();
    $invoice->refresh()->load(['lease.unit.property', 'lineItems']);
    $invoice->append(['outstanding', 'display_status']);
    $html = renderTenantInvoicePdfView($invoice);
    $expectedLabel = $expectedStatus === InvoiceStatus::Partial
        ? 'Partially Paid'
        : $expectedStatus->label();
    $expectedLabel = __($expectedLabel, [], 'id');

    expect($invoice->status)->toBe($expectedStatus)
        ->and($html)
        ->toContain($invoice->reference)
        ->toContain($fixture['lease']->reference)
        ->toContain('Kos Sriwijaya')
        ->toContain('Unit A-01')
        ->toContain(__('Bill To', [], 'id'))
        ->toContain(e($fixture['tenant']->name))
        ->toContain(__('Tenant ID', [], 'id').' '.$fixture['tenant']->id)
        ->toContain('john@example.com')
        ->toContain('+62 812 3456 7890')
        ->toContain((string) Number::currency((float) $invoice->total, in: 'IDR', locale: 'id', precision: 0))
        ->toContain((string) Number::currency((float) $invoice->amount_paid, in: 'IDR', locale: 'id', precision: 0))
        ->toContain((string) Number::currency((float) $invoice->outstanding, in: 'IDR', locale: 'id', precision: 0))
        ->toContain($expectedLabel)
        ->toContain('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;')
        ->not->toContain('<script>');
})->with([
    'pending' => ['0.00', InvoiceStatus::Pending],
    'partial with fractional amount' => ['500000.25', InvoiceStatus::Partial],
    'paid with fractional total' => ['1500000.75', InvoiceStatus::Paid],
    'cancelled' => ['0.00', InvoiceStatus::Cancelled],
]);

test('invoice PDF renders confirmed payment details and history', function () {
    $fixture = createTenantInvoicePdfFixture();
    $invoice = $fixture['invoice'];
    $invoice->payments()->createMany([
        [
            'amount' => '500000.00',
            'payment_date' => '2026-07-29',
            'payment_method' => 'bank_transfer',
            'reference_number' => 'PAY20260021',
            'status' => 'confirmed',
            'verified_at' => '2026-07-29 12:00:00',
        ],
        [
            'amount' => '300000.00',
            'payment_date' => '2026-07-31',
            'payment_method' => 'cash',
            'reference_number' => null,
            'status' => 'confirmed',
        ],
    ]);
    $invoice->recalculateStatus();
    $invoice->refresh()->load(['lease.primaryTenant.user', 'lease.unit.property', 'lineItems', 'payments']);
    $invoice->append(['outstanding', 'display_status']);

    $html = renderTenantInvoicePdfView($invoice);

    expect($html)
        ->toContain(__('Payments', [], 'id'))
        ->toContain(__('Bank Transfer', [], 'id'))
        ->toContain('PAY20260021')
        ->toContain(__('Total paid', [], 'id'))
        ->toContain(__('Outstanding', [], 'id'))
        ->toContain(__('Generated on', [], 'id'))
        ->toContain('UTC');
});

test('invoice PDF formats verified timestamps in the configured timezone', function () {
    config(['app.display_timezone' => 'Asia/Jakarta']);

    $fixture = createTenantInvoicePdfFixture();
    $invoice = $fixture['invoice'];
    $invoice->payments()->create([
        'amount' => '500000.00',
        'payment_date' => '2026-07-29',
        'payment_method' => 'bank_transfer',
        'status' => 'confirmed',
        'verified_at' => '2026-07-29 23:30:00',
    ]);
    $invoice->refresh()->load(['lease.unit.property', 'lineItems', 'payments']);
    $invoice->append(['outstanding', 'display_status']);

    expect(renderTenantInvoicePdfView($invoice))
        ->toContain('30 Jul 2026, 06:30');
});

test('invoice PDF derives overdue status at the end-of-day boundary', function () {
    $this->travelTo(now()->setDate(2026, 8, 5)->setTime(12, 0));
    $fixture = createTenantInvoicePdfFixture();
    $invoice = $fixture['invoice']->load(['lease.unit.property', 'lineItems']);
    $invoice->append(['outstanding', 'display_status']);

    expect($invoice->display_status)->toBe('pending')
        ->and(renderTenantInvoicePdfView($invoice))->toContain(__('Pending', [], 'id'));

    $invoice->update(['due_date' => '2026-08-04']);
    $invoice->refresh()->load(['lease.unit.property', 'lineItems']);
    $invoice->append(['outstanding', 'display_status']);

    expect($invoice->display_status)->toBe('overdue')
        ->and(renderTenantInvoicePdfView($invoice))->toContain(__('Overdue', [], 'id'));
});

test('invoice PDF handles missing references and line items', function () {
    $fixture = createTenantInvoicePdfFixture();
    $invoice = $fixture['invoice'];
    $invoice->update(['reference' => null]);
    $invoice->load(['lease.unit.property', 'lineItems']);
    $invoice->append(['outstanding', 'display_status']);
    prepareTenantInvoicePdf($invoice);

    expect(renderTenantInvoicePdfView($invoice))
        ->toContain("#{$invoice->id}")
        ->toContain(__('No itemized charges.', [], 'id'));

    $this->actingAs($fixture['user'])
        ->get(route('portal.billing.invoices.download', $invoice))
        ->assertOk()
        ->assertDownload("invoice-{$invoice->id}.pdf");
});

test('invoice PDF paginates long line item lists', function () {
    $fixture = createTenantInvoicePdfFixture();
    $invoice = $fixture['invoice'];

    foreach (range(1, 60) as $number) {
        $invoice->lineItems()->create([
            'type' => 'rent',
            'description' => "Long invoice item {$number} ".str_repeat('description ', 8),
            'amount' => '1.00',
        ]);
    }

    $invoice->load(['lease.unit.property', 'lineItems']);
    $invoice->append(['outstanding', 'display_status']);
    $pdf = app(GenerateInvoicePdf::class)->execute($invoice);

    expect($pdf)->toStartWith('%PDF-')
        ->and(preg_match_all('/\/Type\s*\/Page\b/', $pdf))->toBeGreaterThan(1);
});
