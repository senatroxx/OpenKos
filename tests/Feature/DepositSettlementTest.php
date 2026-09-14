<?php

use App\Actions\Leases\MoveOutLease;
use App\Actions\Leases\SettleLeaseDeposit;
use App\Data\Lease\DepositDeductionData;
use App\Data\Lease\DepositSettlementData;
use App\Data\Lease\MoveOutLeaseData;
use App\Enums\DepositSettlementStatus;
use App\Enums\LeaseStatus;
use App\Models\AuditLog;
use App\Models\DepositSettlement;
use App\Models\Lease;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RegionAndCitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses()->beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(RegionAndCitySeeder::class);
});

function depositSettlementData(
    DepositSettlementStatus $status = DepositSettlementStatus::Draft,
    string $refundAmount = '700',
    array $deductions = [],
): DepositSettlementData {
    return new DepositSettlementData(
        status: $status,
        settlementDate: CarbonImmutable::parse('2026-09-11'),
        refundAmount: $refundAmount,
        deductions: array_map(
            fn (array $deduction): DepositDeductionData => new DepositDeductionData(...$deduction),
            $deductions,
        ),
    );
}

function terminatedDepositLease(array $attributes = []): Lease
{
    return Lease::factory()->terminated()->create([
        'deposit_amount' => '1000',
        'currency' => 'USD',
        ...$attributes,
    ]);
}

it('creates a draft with a deposit snapshot and deduction lines', function () {
    $lease = terminatedDepositLease();
    $auditCount = AuditLog::count();

    $settlement = app(SettleLeaseDeposit::class)->execute(
        $lease,
        depositSettlementData(deductions: [
            ['amount' => '300', 'reason' => 'Repairs', 'description' => 'Broken door'],
        ]),
    );

    expect($settlement)
        ->original_amount->toBe('1000.000')
        ->currency->toBe('USD')
        ->status->toBe(DepositSettlementStatus::Draft)
        ->refund_amount->toBe('700.000')
        ->deductions_total->toBe('300.000');
    expect($settlement->deductions)->toHaveCount(1);
    expect(AuditLog::where('created_at', '>=', now()->subMinute())->count())->toBeGreaterThan($auditCount);
});

it('allows drafts to be edited and replaces deduction rows with audit events', function () {
    $lease = terminatedDepositLease();
    $action = app(SettleLeaseDeposit::class);

    $draft = $action->execute($lease, depositSettlementData());
    $oldDeduction = $draft->deductions()->create([
        'amount' => '300',
        'reason' => 'Repairs',
    ]);

    $action->execute($lease, depositSettlementData(
        refundAmount: '500',
        deductions: [
            ['amount' => '500', 'reason' => 'Cleaning', 'description' => null],
        ],
    ));

    expect($draft->fresh()->deductions)->toHaveCount(1)
        ->and($draft->fresh()->deductions->first()->reason)->toBe('Cleaning');
    expect(AuditLog::where('auditable_type', $oldDeduction->getMorphClass())
        ->where('auditable_id', $oldDeduction->id)
        ->where('operation', 'delete')
        ->exists())->toBeTrue();
});

it('rejects allocations above the original deposit', function () {
    $lease = terminatedDepositLease();

    expect(fn () => app(SettleLeaseDeposit::class)->execute(
        $lease,
        depositSettlementData(
            refundAmount: '701',
            deductions: [['amount' => '300', 'reason' => 'Repairs']],
        ),
    ))->toThrow(ValidationException::class);

    expect(DepositSettlement::count())->toBe(0);
});

it('requires exact reconciliation when settling', function () {
    $lease = terminatedDepositLease();

    expect(fn () => app(SettleLeaseDeposit::class)->execute(
        $lease,
        depositSettlementData(DepositSettlementStatus::Settled, refundAmount: '700'),
    ))->toThrow(ValidationException::class);

    expect(DepositSettlement::count())->toBe(0);
});

it('supports full refunds and full forfeiture through deductions', function () {
    $action = app(SettleLeaseDeposit::class);
    $refundSettlement = $action->execute(
        terminatedDepositLease(),
        depositSettlementData(DepositSettlementStatus::Settled, refundAmount: '1000'),
    );
    $forfeitSettlement = $action->execute(
        terminatedDepositLease(),
        depositSettlementData(
            DepositSettlementStatus::Settled,
            refundAmount: '0',
            deductions: [['amount' => '1000', 'reason' => 'Full forfeiture']],
        ),
    );

    expect($refundSettlement->status)->toBe(DepositSettlementStatus::Settled)
        ->and($refundSettlement->deductions)->toBeEmpty()
        ->and($forfeitSettlement->refund_amount)->toBe('0.000')
        ->and($forfeitSettlement->deductions_total)->toBe('1000.000');
});

it('does not allow settled settlements to be changed', function () {
    $lease = terminatedDepositLease();
    $action = app(SettleLeaseDeposit::class);

    $action->execute($lease, depositSettlementData(DepositSettlementStatus::Settled, refundAmount: '1000'));

    expect(fn () => $action->execute($lease, depositSettlementData()))
        ->toThrow(ValidationException::class);
});

it('keeps settled settlements and deduction lines immutable at the model boundary', function () {
    $lease = terminatedDepositLease();
    $settlement = app(SettleLeaseDeposit::class)->execute(
        $lease,
        depositSettlementData(
            DepositSettlementStatus::Settled,
            refundAmount: '700',
            deductions: [['amount' => '300', 'reason' => 'Repairs']],
        ),
    );
    $deduction = $settlement->deductions->firstOrFail();

    expect(fn () => $settlement->update(['notes' => 'Changed']))
        ->toThrow(LogicException::class);
    expect(fn () => $settlement->delete())
        ->toThrow(LogicException::class);
    expect(fn () => $deduction->update(['amount' => '200']))
        ->toThrow(LogicException::class);
    expect(fn () => $deduction->delete())
        ->toThrow(LogicException::class);
    expect(fn () => $settlement->deductions()->create([
        'amount' => '100',
        'reason' => 'Additional repairs',
    ]))->toThrow(LogicException::class);
});

it('saves a post-move-out draft through the lease endpoint', function () {
    $user = User::factory()->owner()->create();
    $lease = terminatedDepositLease();

    $this->actingAs($user)
        ->post(route('leases.deposit-settlement', $lease), [
            'status' => 'draft',
            'refund_amount' => '500',
            'deductions' => [
                ['amount' => '100', 'reason' => 'Cleaning'],
            ],
        ])
        ->assertRedirect();

    expect($lease->fresh()->depositSettlement)
        ->status->toBe(DepositSettlementStatus::Draft)
        ->refund_amount->toBe('500.000');
});

it('keeps one settlement when a draft is saved more than once', function () {
    $lease = terminatedDepositLease();
    $action = app(SettleLeaseDeposit::class);

    $action->execute($lease, depositSettlementData());
    $action->execute($lease, depositSettlementData(refundAmount: '600'));

    expect(DepositSettlement::where('lease_id', $lease->id)->count())->toBe(1);
});

it('keeps the original snapshot when lease deposit data later changes', function () {
    $lease = terminatedDepositLease();
    $action = app(SettleLeaseDeposit::class);

    $action->execute($lease, depositSettlementData());
    DB::table('leases')->whereKey($lease->id)->update([
        'deposit_amount' => '2000',
        'currency' => 'EUR',
    ]);

    $settlement = $action->execute($lease, depositSettlementData(refundAmount: '600'));

    expect($settlement)
        ->original_amount->toBe('1000.000')
        ->currency->toBe('USD')
        ->refund_amount->toBe('600.000');
});

it('translates concurrent settlement creation conflicts into validation errors', function () {
    $lease = terminatedDepositLease();
    $dispatcher = DepositSettlement::getEventDispatcher();
    $testDispatcher = clone $dispatcher;
    $injected = false;

    DepositSettlement::setEventDispatcher($testDispatcher);
    DepositSettlement::creating(function (DepositSettlement $settlement) use (&$injected): void {
        if ($injected) {
            return;
        }

        $injected = true;
        DB::table('deposit_settlements')->insert([
            'lease_id' => $settlement->lease_id,
            'original_amount' => $settlement->original_amount,
            'currency' => $settlement->currency,
            'status' => DepositSettlementStatus::Draft->value,
            'settlement_date' => $settlement->settlement_date,
            'refund_amount' => $settlement->refund_amount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    try {
        expect(fn () => app(SettleLeaseDeposit::class)->execute(
            $lease,
            depositSettlementData(),
        ))->toThrow(ValidationException::class);
    } finally {
        DepositSettlement::setEventDispatcher($dispatcher);
    }

    expect(DepositSettlement::count())->toBe(0);
});

it('does not settle a deposit while transferring a lease to another unit', function () {
    $sourceUnit = Unit::factory()->withRate('1000', 'USD')->create();
    $targetUnit = Unit::factory()->withRate('1000', 'USD')->create();
    $lease = Lease::factory()->create([
        'unit_id' => $sourceUnit->id,
        'deposit_amount' => '1000',
        'currency' => 'USD',
    ]);

    expect(fn () => app(MoveOutLease::class)->execute($lease, new MoveOutLeaseData(
        terminationDate: '2026-09-11',
        endDate: '2026-09-11',
        reason: 'Moved unit',
        moveToAnotherUnit: true,
        targetUnitId: $targetUnit->id,
        depositSettlement: depositSettlementData(DepositSettlementStatus::Settled, refundAmount: '1000'),
    )))->toThrow(HttpException::class);

    expect($lease->fresh()->status)->toBe(LeaseStatus::Active)
        ->and(DepositSettlement::count())->toBe(0);
});

it('rejects zero-deposit settlements', function () {
    $lease = terminatedDepositLease(['deposit_amount' => '0']);

    expect(fn () => app(SettleLeaseDeposit::class)->execute($lease, depositSettlementData()))
        ->toThrow(ValidationException::class);
});

it('settles an active lease atomically with move-out', function () {
    $user = User::factory()->owner()->create();
    $lease = Lease::factory()->create([
        'deposit_amount' => '1000',
        'currency' => 'USD',
    ]);

    $this->actingAs($user)
        ->post(route('leases.move-out', $lease), [
            'move_out_date' => '2026-09-11',
            'reason' => 'Moved out',
            'settlement' => [
                'status' => 'settled',
                'settlement_date' => '2026-09-11',
                'refund_amount' => '800',
                'deductions' => [
                    ['amount' => '200', 'reason' => 'Cleaning'],
                ],
            ],
        ])
        ->assertRedirect();

    expect($lease->fresh()->status)->toBe(LeaseStatus::Terminated);
    expect($lease->fresh()->depositSettlement)
        ->status->toBe(DepositSettlementStatus::Settled)
        ->refund_amount->toBe('800.000');
});

it('rolls back move-out when its settlement is invalid', function () {
    $user = User::factory()->owner()->create();
    $lease = Lease::factory()->create([
        'deposit_amount' => '1000',
        'currency' => 'USD',
    ]);

    $this->actingAs($user)
        ->from(route('leases.show', $lease))
        ->post(route('leases.move-out', $lease), [
            'move_out_date' => '2026-09-11',
            'settlement' => [
                'status' => 'settled',
                'refund_amount' => '500',
            ],
        ])
        ->assertRedirect(route('leases.show', $lease))
        ->assertSessionHasErrors('settlement');

    expect($lease->fresh()->status)->toBe(LeaseStatus::Active)
        ->and(DepositSettlement::count())->toBe(0);
});

it('keeps legacy refund fields separate from a new settlement', function () {
    $user = User::factory()->owner()->create();
    $lease = Lease::factory()->create([
        'deposit_amount' => '1000',
        'deposit_refund_amount' => '600',
        'deposit_refunded_at' => '2026-09-01 10:00:00',
        'currency' => 'USD',
    ]);

    $this->actingAs($user)->post(route('leases.move-out', $lease), [
        'move_out_date' => '2026-09-11',
        'settlement' => [
            'status' => 'settled',
            'refund_amount' => '1000',
            'settlement_date' => '2026-09-11',
        ],
    ])->assertRedirect();

    expect($lease->fresh())
        ->deposit_refund_amount->toBe('600.000')
        ->deposit_refunded_at->not->toBeNull();
});
