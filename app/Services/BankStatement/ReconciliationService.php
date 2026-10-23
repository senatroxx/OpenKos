<?php

namespace App\Services\BankStatement;

use App\Models\BankStatementRow;
use App\Models\Tenant;
use App\Models\Unit;

class ReconciliationService
{
    public function normalizeUnitNumber(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $value = strtoupper($value);
        $value = str_replace([' ', '-', '_'], '', $value);

        return preg_replace('/[^A-Z0-9]/', '', $value) ?? '';
    }

    public function findUnitByStatementValue(?string $value): ?Unit
    {
        $normalized = $this->normalizeUnitNumber($value);

        if ($normalized === '') {
            return null;
        }

        return Unit::query()
            ->whereRaw('UPPER(REPLACE(name, " ", "")) = ?', [$normalized])
            ->orWhereRaw('UPPER(REPLACE(slug, " ", "")) = ?', [$normalized])
            ->orWhereRaw('UPPER(REPLACE(name, "-", "")) = ?', [$normalized])
            ->first();
    }

    public function matchTenantFallback(?string $payerName): ?Tenant
    {
        $name = trim((string) $payerName);

        if ($name === '') {
            return null;
        }

        return Tenant::query()
            ->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($name).'%'])
            ->first();
    }

    public function reconcileRow(BankStatementRow $row): void
    {
        $row->update([
            'match_status' => 'unmatched',
            'matched_unit_id' => null,
            'matched_tenant_id' => null,
        ]);

        $unit = $this->findUnitByStatementValue($row->unit_number);

        if ($unit) {
            $row->update([
                'matched_unit_id' => $unit->id,
                'normalized_unit_number' => $this->normalizeUnitNumber($row->unit_number),
                'match_status' => 'matched',
                'notes' => 'Matched by normalized unit number.',
            ]);

            return;
        }

        $tenant = $this->matchTenantFallback($row->payer_name);

        if ($tenant) {
            $row->update([
                'matched_tenant_id' => $tenant->id,
                'normalized_unit_number' => $this->normalizeUnitNumber($row->unit_number),
                'match_status' => 'matched_by_name',
                'notes' => 'Matched by tenant name fallback.',
            ]);

            return;
        }

        $row->update([
            'normalized_unit_number' => $this->normalizeUnitNumber($row->unit_number),
            'match_status' => 'unmatched',
            'notes' => 'No valid unit or tenant match found.',
        ]);
    }
}
