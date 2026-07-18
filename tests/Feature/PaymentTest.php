<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RegionAndCitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\File;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(RegionAndCitySeeder::class);
});

function createLeaseForProperty(?Property $property = null): Lease
{
    $property ??= Property::factory()->create();
    $unit = Unit::factory()->for($property)->create();
    $tenant = Tenant::factory()->create();

    return Lease::factory()->create([
        'unit_id' => $unit->id,
        'primary_tenant_id' => $tenant->id,
    ]);
}

function createInvoiceFor(Lease $lease, array $overrides = []): Invoice
{
    return Invoice::factory()->create(array_merge([
        'lease_id' => $lease->id,
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
        'due_date' => now()->startOfMonth()->addDays(4),
        'total' => 1_500_000,
    ], $overrides));
}

function paymentPayload(Invoice $invoice, array $overrides = []): array
{
    return array_merge([
        'invoice_id' => $invoice->id,
        'amount' => 1_500_000,
        'payment_method' => 'cash',
        'paid_at' => now()->format('Y-m-d'),
    ], $overrides);
}

describe('authorization', function () {
    it('allows owner to view payment', function () {
        $user = User::factory()->owner()->create();
        $payment = Payment::factory()->create();

        expect($user->can('view', $payment))->toBeTrue();
    });

    it('allows admin assigned to the property to view payment', function () {
        $admin = User::factory()->admin()->create();
        $property = Property::factory()->create();
        $admin->properties()->sync([$property->id]);
        $lease = createLeaseForProperty($property);
        $payment = Payment::factory()->create([
            'invoice_id' => createInvoiceFor($lease)->id,
        ]);

        expect($admin->can('view', $payment))->toBeTrue();
    });

    it('denies admin not assigned to the property from viewing payment', function () {
        $admin = User::factory()->admin()->create();
        $propertyA = Property::factory()->create();
        $admin->properties()->sync([$propertyA->id]);
        $lease = createLeaseForProperty();
        $payment = Payment::factory()->create([
            'invoice_id' => createInvoiceFor($lease)->id,
        ]);

        expect($admin->can('view', $payment))->toBeFalse();
    });
});

describe('payment recording', function () {
    it('allows owner to record payment for active lease', function () {
        $user = User::factory()->owner()->create();
        $lease = createLeaseForProperty();
        $invoice = createInvoiceFor($lease);

        $this->actingAs($user)
            ->post(route('leases.payments.store', $lease), paymentPayload($invoice, [
                'notes' => 'Test payment',
            ]));

        expect($invoice->payments()->count())->toBe(1)
            ->and($invoice->payments()->first()->status)->toBe(PaymentStatus::Confirmed)
            ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
    });

    it('allows admin assigned to property to record payment', function () {
        $admin = User::factory()->admin()->create();
        $property = Property::factory()->create();
        $admin->properties()->sync([$property->id]);
        $lease = createLeaseForProperty($property);
        $invoice = createInvoiceFor($lease);

        $this->actingAs($admin)
            ->post(route('leases.payments.store', $lease), paymentPayload($invoice, [
                'payment_method' => 'transfer',
            ]));

        expect($invoice->payments()->count())->toBe(1)
            ->and($invoice->payments()->first()->confirmedBy->id)->toBe($admin->id);
    });

    it('denies admin not assigned to property from recording payment', function () {
        $admin = User::factory()->admin()->create();
        $lease = createLeaseForProperty();
        $invoice = createInvoiceFor($lease);

        $this->actingAs($admin)
            ->post(route('leases.payments.store', $lease), paymentPayload($invoice))
            ->assertForbidden();
    });

    it('rejects a payment against an invoice from another lease', function () {
        $user = User::factory()->owner()->create();
        $lease = createLeaseForProperty();
        $otherInvoice = createInvoiceFor(createLeaseForProperty());

        $this->actingAs($user)
            ->post(route('leases.payments.store', $lease), paymentPayload($otherInvoice))
            ->assertSessionHasErrors('invoice_id');
    });

    it('rejects a payment against a paid invoice', function () {
        $user = User::factory()->owner()->create();
        $lease = createLeaseForProperty();
        $invoice = createInvoiceFor($lease, [
            'status' => InvoiceStatus::Paid,
            'amount_paid' => 1_500_000,
        ]);

        $this->actingAs($user)
            ->post(route('leases.payments.store', $lease), paymentPayload($invoice))
            ->assertSessionHasErrors('invoice_id');
    });

    it('rejects overpayment beyond outstanding balance', function () {
        $user = User::factory()->owner()->create();
        $lease = createLeaseForProperty();
        $invoice = createInvoiceFor($lease);

        $this->actingAs($user)
            ->post(route('leases.payments.store', $lease), paymentPayload($invoice, [
                'amount' => 2_000_000,
            ]))
            ->assertSessionHasErrors('amount');
    });

    it('prevents recording payment for inactive lease', function () {
        $user = User::factory()->owner()->create();
        $lease = createLeaseForProperty();
        $invoice = createInvoiceFor($lease);
        $lease->update(['status' => 'terminated']);

        $this->actingAs($user)
            ->post(route('leases.payments.store', $lease), paymentPayload($invoice))
            ->assertSessionHasErrors('lease');
    });

    it('requires positive amount', function () {
        $user = User::factory()->owner()->create();
        $lease = createLeaseForProperty();
        $invoice = createInvoiceFor($lease);

        $this->actingAs($user)
            ->post(route('leases.payments.store', $lease), paymentPayload($invoice, [
                'amount' => 0,
            ]))
            ->assertSessionHasErrors('amount');
    });

    it('sets recorded_by and confirmed_by to the recording user', function () {
        $user = User::factory()->owner()->create();
        $lease = createLeaseForProperty();
        $invoice = createInvoiceFor($lease);

        $this->actingAs($user)
            ->post(route('leases.payments.store', $lease), paymentPayload($invoice));

        $payment = $invoice->payments()->first();

        expect((int) $payment->recorded_by)->toBe((int) $user->id);
        expect((int) $payment->confirmed_by)->toBe((int) $user->id);
        expect($payment->status)->toBe(PaymentStatus::Confirmed);
    });
});

describe('invoice settlement', function () {
    it('marks invoice partial on partial payment and tracks outstanding', function () {
        $user = User::factory()->owner()->create();
        $lease = createLeaseForProperty();
        $invoice = createInvoiceFor($lease);

        $this->actingAs($user)
            ->post(route('leases.payments.store', $lease), paymentPayload($invoice, [
                'amount' => 500_000,
            ]));

        $invoice->refresh();

        expect($invoice->status)->toBe(InvoiceStatus::Partial)
            ->and((float) $invoice->amount_paid)->toBe(500_000.00)
            ->and((float) $invoice->outstanding)->toBe(1_000_000.00);
    });

    it('marks invoice paid when payments cover the total', function () {
        $user = User::factory()->owner()->create();
        $lease = createLeaseForProperty();
        $invoice = createInvoiceFor($lease);

        $this->actingAs($user)
            ->post(route('leases.payments.store', $lease), paymentPayload($invoice, ['amount' => 500_000]));
        $this->actingAs($user)
            ->post(route('leases.payments.store', $lease), paymentPayload($invoice, ['amount' => 1_000_000]));

        expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
    });

    it('does not count unverified payments toward the invoice', function () {
        $lease = createLeaseForProperty();
        $invoice = createInvoiceFor($lease);

        Payment::factory()->pending()->create([
            'invoice_id' => $invoice->id,
            'amount' => 1_500_000,
        ]);

        $invoice->recalculateStatus();

        expect($invoice->fresh()->status)->toBe(InvoiceStatus::Pending)
            ->and((float) $invoice->fresh()->amount_paid)->toBe(0.00);
    });

    it('reverts invoice status when the only confirmed payment is rejected', function () {
        $user = User::factory()->owner()->create();
        $lease = createLeaseForProperty();
        $invoice = createInvoiceFor($lease);

        $payment = Payment::factory()->pending()->create([
            'invoice_id' => $invoice->id,
            'amount' => 1_500_000,
        ]);

        $this->actingAs($user)
            ->post(route('payments.verify', $payment), ['action' => 'confirm']);

        expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);

        $payment2 = Payment::factory()->pending()->create([
            'invoice_id' => createInvoiceFor($lease, ['period_start' => now()->addMonth()->startOfMonth(), 'period_end' => now()->addMonth()->endOfMonth()])->id,
            'amount' => 500_000,
        ]);

        $this->actingAs($user)
            ->post(route('payments.verify', $payment2), ['action' => 'reject']);

        expect($payment2->fresh()->status)->toBe(PaymentStatus::Cancelled)
            ->and($payment2->invoice->fresh()->status)->toBe(InvoiceStatus::Pending);
    });
});

describe('proof download', function () {
    it('allows authorized user to download a valid proof', function () {
        $user = User::factory()->owner()->create();
        $lease = createLeaseForProperty();
        $invoice = createInvoiceFor($lease);
        $payment = Payment::factory()->create(['invoice_id' => $invoice->id]);
        $proofPath = 'proofs/test-proof.pdf';
        $fullPath = storage_path('app/private/'.$proofPath);
        File::makeDirectory(dirname($fullPath), 0755, true, true);
        File::put($fullPath, 'fake-pdf-content');
        $proof = PaymentProof::factory()->create([
            'payment_id' => $payment->id,
            'path' => $proofPath,
            'original_name' => 'proof.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $this->actingAs($user)
            ->get(route('payments.proof', [$payment, $proof]))
            ->assertOk();

        File::delete($fullPath);
    });

    it('returns 404 when proof belongs to another payment', function () {
        $user = User::factory()->owner()->create();
        $lease = createLeaseForProperty();
        $invoice = createInvoiceFor($lease);
        $payment = Payment::factory()->create(['invoice_id' => $invoice->id]);
        $otherPayment = Payment::factory()->create(['invoice_id' => $invoice->id]);

        $proofPath = 'proofs/test-proof.pdf';
        $fullPath = storage_path('app/private/'.$proofPath);
        File::makeDirectory(dirname($fullPath), 0755, true, true);
        File::put($fullPath, 'fake-pdf-content');
        $proof = PaymentProof::factory()->create([
            'payment_id' => $otherPayment->id,
            'path' => $proofPath,
        ]);

        $this->actingAs($user)
            ->get(route('payments.proof', [$payment, $proof]))
            ->assertNotFound();

        File::delete($fullPath);
    });

    it('returns 403 for user without access to the property', function () {
        $user = User::factory()->admin()->create();
        $lease = createLeaseForProperty();
        $invoice = createInvoiceFor($lease);
        $payment = Payment::factory()->create(['invoice_id' => $invoice->id]);
        $proofPath = 'proofs/test-proof.pdf';
        $fullPath = storage_path('app/private/'.$proofPath);
        File::makeDirectory(dirname($fullPath), 0755, true, true);
        File::put($fullPath, 'fake-pdf-content');
        $proof = PaymentProof::factory()->create([
            'payment_id' => $payment->id,
            'path' => $proofPath,
        ]);

        $this->actingAs($user)
            ->get(route('payments.proof', [$payment, $proof]))
            ->assertForbidden();

        File::delete($fullPath);
    });

    it('returns 404 when proof file is missing from disk', function () {
        $user = User::factory()->owner()->create();
        $lease = createLeaseForProperty();
        $invoice = createInvoiceFor($lease);
        $payment = Payment::factory()->create(['invoice_id' => $invoice->id]);
        $proof = PaymentProof::factory()->create([
            'payment_id' => $payment->id,
            'path' => 'proofs/nonexistent.pdf',
        ]);

        $this->actingAs($user)
            ->get(route('payments.proof', [$payment, $proof]))
            ->assertNotFound();
    });
});
