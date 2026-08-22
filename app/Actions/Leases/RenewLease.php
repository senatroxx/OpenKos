<?php

namespace App\Actions\Leases;

use App\Actions\Invoices\GenerateInvoices;
use App\Business\Leases\LeaseFinancialChecker;
use App\Business\Leases\LeaseStatusValidator;
use App\Business\Leases\RenewalEligibilityChecker;
use App\Data\Lease\RenewLeaseData;
use App\Enums\LeaseStatus;
use App\Enums\UnitStatus;
use App\Exceptions\LeaseRenewalException;
use App\Models\Lease;
use App\Models\Unit;
use App\Results\Lease\RenewLeaseResult;
use App\Services\Payments\MoneyConverter;
use Illuminate\Support\Facades\DB;

class RenewLease
{
    public function __construct(
        private readonly RenewalEligibilityChecker $eligibility,
        private readonly LeaseFinancialChecker $financial,
        private readonly LeaseStatusValidator $leaseStatusValidator,
        private readonly GenerateInvoices $generateInvoices,
        private readonly MoneyConverter $money,
    ) {}

    public function execute(Lease $lease, RenewLeaseData $data): RenewLeaseResult
    {
        return DB::transaction(function () use ($lease, $data) {
            $unit = Unit::lockForUpdate()->findOrFail($lease->unit_id);
            $lockedLease = Lease::query()
                ->whereKey($lease->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                (int) $lockedLease->unit_id === $unit->id,
                422,
                __('Lease is no longer assigned to this unit.'),
            );

            if (in_array($unit->status, [UnitStatus::Maintenance, UnitStatus::Unavailable], true)) {
                return RenewLeaseResult::error('This unit is not available for lease.');
            }

            try {
                $this->eligibility->ensureCanRenew($lockedLease);
            } catch (LeaseRenewalException $e) {
                return RenewLeaseResult::error($e->getMessage());
            }

            $outstanding = $this->financial->outstandingCheck($lockedLease);

            if ($outstanding['hasOutstanding'] && ! $data->confirmedOutstanding) {
                return RenewLeaseResult::error(
                    'Lease has an outstanding balance of '.$outstanding['balance'].'. Confirm to proceed.'
                );
            }

            $this->leaseStatusValidator->validate($lockedLease->status, LeaseStatus::Renewed);

            $existingActive = $unit->leases()
                ->where('status', LeaseStatus::Active->value)
                ->where('id', '!=', $lockedLease->id)
                ->exists();

            if ($existingActive) {
                return RenewLeaseResult::error('Unit already has an active lease.');
            }

            if ($lockedLease->end_date === null || $data->endDate->lessThanOrEqualTo($lockedLease->end_date)) {
                return RenewLeaseResult::error('The renewal end date must be after the current lease end date.');
            }

            try {
                $rentAmount = $this->money->normalizeAmount($data->rentAmount, $lockedLease->currency);
                $depositAmount = $lockedLease->deposit_amount === null
                    ? null
                    : $this->money->normalizeAmount((string) $lockedLease->deposit_amount, $lockedLease->currency);
            } catch (\InvalidArgumentException) {
                return RenewLeaseResult::error('The existing lease amount is invalid for its currency.');
            }

            $lockedLease->update([
                'status' => LeaseStatus::Renewed,
            ]);

            $newEndDate = $data->endDate;

            $newLease = $unit->leases()->create([
                'previous_lease_id' => $lockedLease->id,
                'primary_tenant_id' => $lockedLease->primary_tenant_id,
                'start_date' => $lockedLease->end_date->addDay(),
                'end_date' => $newEndDate,
                'rent_amount' => $rentAmount,
                'currency' => $lockedLease->currency,
                'deposit_amount' => $depositAmount,
                'deposit_paid_at' => $lockedLease->deposit_paid_at,
                'billing_interval' => $lockedLease->billing_interval,
                'billing_unit' => $lockedLease->billing_unit,
                'billing_strategy' => $lockedLease->billing_strategy,
                'rent_due_day' => $lockedLease->rent_due_day,
                'is_custom_price' => $lockedLease->is_custom_price,
                'unit_rate_id' => $lockedLease->unit_rate_id,
                'status' => LeaseStatus::Active,
            ]);

            foreach ($lockedLease->tenants as $tenant) {
                $newLease->tenants()->attach($tenant->id, [
                    'is_primary' => $tenant->id === $lockedLease->primary_tenant_id,
                ]);
            }

            $newLease->load('tenants:id,name,phone', 'primaryTenant:id,name,phone');

            $this->generateInvoices->execute($newLease);

            return RenewLeaseResult::success($newLease);
        });
    }
}
