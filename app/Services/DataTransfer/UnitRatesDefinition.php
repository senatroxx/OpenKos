<?php

namespace App\Services\DataTransfer;

use App\Actions\Units\CreateUnitRate;
use App\Enums\BillingUnit;
use App\Enums\DataTransferDataset;
use App\Models\Unit;
use App\Models\UnitRate;
use App\Models\User;
use App\Services\Payments\MoneyConverter;
use App\Services\Settings\InstallationCurrencySettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

final class UnitRatesDefinition extends DatasetDefinition
{
    public function __construct(
        private CreateUnitRate $createUnitRate,
        private MoneyConverter $money,
        private InstallationCurrencySettings $currencies,
    ) {}

    public function dataset(): DataTransferDataset
    {
        return DataTransferDataset::UnitRates;
    }

    public function importHeaders(): array
    {
        return [
            'property_slug',
            'unit_name',
            'billing_interval',
            'billing_unit',
            'amount',
            'currency',
            'is_active',
            'effective_from',
            'effective_until',
        ];
    }

    public function exportHeaders(bool $sensitive = false): array
    {
        return $this->importHeaders();
    }

    protected function requiredImportHeaders(): array
    {
        return ['property_slug', 'unit_name', 'billing_interval', 'billing_unit', 'amount'];
    }

    public function validateRow(
        array $values,
        int $line,
        User $actor,
        ImportValidationContext $context,
    ): ?array {
        $values = $this->normalizeValues($values);
        $valid = $this->validateFields($values, [
            'property_slug' => ['required', 'string', 'max:255'],
            'unit_name' => ['required', 'string', 'max:255'],
            'billing_interval' => ['required', 'integer', 'min:1', 'max:255'],
            'billing_unit' => ['required', Rule::in(BillingUnit::values())],
            'amount' => ['required', 'string'],
            'currency' => ['nullable', 'string', 'size:3'],
            'effective_from' => ['nullable', 'date_format:Y-m-d'],
            'effective_until' => ['nullable', 'date_format:Y-m-d'],
        ], $line, $context);

        if (! $valid) {
            return null;
        }

        $rowErrorCount = count($context->errors);
        $property = $this->accessibleProperty($actor, (string) $values['property_slug']);

        if ($property === null) {
            $context->error($line, 'property_slug', __('The referenced property could not be resolved or is not accessible.'));

            return null;
        }

        $unitKey = mb_strtolower($property->id.'|'.(string) $values['unit_name']);
        $unit = $context->state['unit_cache'][$unitKey] ?? null;

        if (! $unit instanceof Unit) {
            $units = Unit::query()
                ->where('property_id', $property->id)
                ->where('name', $values['unit_name'])
                ->get();
            $unit = $units->count() === 1 ? $units->first() : null;
            $context->state['unit_cache'][$unitKey] = $unit;

            if ($units->isEmpty()) {
                $context->error($line, 'unit_name', __('The referenced unit could not be resolved.'));
            } elseif ($units->count() > 1) {
                $context->error($line, 'unit_name', __('The referenced unit is ambiguous.'));
            }
        }

        if (! $unit instanceof Unit) {
            return null;
        }

        try {
            $currency = $this->money->normalizeCurrency($values['currency'] ?? null);
        } catch (\Throwable) {
            $context->error($line, 'currency', __('The currency is invalid.'));

            return null;
        }

        if (! $this->currencies->supports($currency, fresh: true)) {
            $context->error($line, 'currency', __('This currency is not enabled for new pricing rates.'));
        }

        try {
            $amount = $this->money->normalizeAmount((string) $values['amount'], $currency);
        } catch (\Throwable) {
            $context->error($line, 'amount', __('The amount is invalid for the selected currency.'));
            $amount = null;
        }

        $effectiveFrom = $values['effective_from'] ?? null;
        $effectiveUntil = $values['effective_until'] ?? null;

        if ($effectiveFrom !== null && $effectiveUntil !== null
            && CarbonImmutable::parse($effectiveUntil)->lt(CarbonImmutable::parse($effectiveFrom))) {
            $context->error($line, 'effective_until', __('The effective end date must be on or after the start date.'));
        }

        $isActive = $this->boolean($values['is_active'] ?? null, $line, 'is_active', $context);
        $rateKey = implode('|', [$unit->id, $values['billing_interval'], $values['billing_unit'], $currency]);

        if (isset($context->state['rate_keys'][$rateKey]) || UnitRate::query()
            ->where('unit_id', $unit->id)
            ->where('billing_interval', $values['billing_interval'])
            ->where('billing_unit', $values['billing_unit'])
            ->where('currency', $currency)
            ->exists()
        ) {
            $context->error($line, 'billing_interval', __('A rate with the same billing period and currency already exists.'));
        }
        $context->state['rate_keys'][$rateKey] = $line;

        if ($context->errorsSince($rowErrorCount) || $amount === null) {
            return null;
        }

        return [
            'unit_id' => $unit->id,
            'billing_interval' => (int) $values['billing_interval'],
            'billing_unit' => $values['billing_unit'],
            'amount' => $amount,
            'currency' => $currency,
            'is_active' => $isActive,
            'effective_from' => $effectiveFrom,
            'effective_until' => $effectiveUntil,
        ];
    }

    public function persist(array $row, User $actor): Model
    {
        $unit = Unit::findOrFail($row['unit_id']);
        unset($row['unit_id']);

        return $this->createUnitRate->execute($unit, $row);
    }

    public function exportQuery(User $actor, array $filters): Builder
    {
        $includeArchived = (bool) ($filters['include_archived'] ?? false);

        return UnitRate::query()
            ->when(! $includeArchived, fn (Builder $query) => $query->where('unit_rates.is_active', true))
            ->whereHas('unit.property', function (Builder $property) use ($actor, $filters): void {
                $property
                    ->when(! $actor->isOwner(), fn (Builder $query) => $query->whereHas(
                        'users',
                        fn (Builder $users) => $users->whereKey($actor->id),
                    ))
                    ->when($filters['property_slug'] ?? null, fn (Builder $query, string $slug) => $query->where('slug', $slug));
            })
            ->when($filters['unit_name'] ?? null, fn (Builder $query, string $name) => $query->whereHas(
                'unit',
                fn (Builder $unit) => $unit->where('name', $name),
            ))
            ->when($filters['currency'] ?? null, fn (Builder $query, string $currency) => $query->where('unit_rates.currency', $currency))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->when(
                in_array($status, ['active', 'inactive'], true),
                fn (Builder $query) => $query->where('unit_rates.is_active', $status === 'active'),
            ))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $search = mb_strtolower($search);
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereHas('unit', fn (Builder $unit) => $unit->whereRaw('lower(name) like ?', ["%{$search}%"]))
                        ->orWhereHas('unit.property', fn (Builder $property) => $property->whereRaw('lower(name) like ?', ["%{$search}%"]));
                });
            })
            ->with('unit.property')
            ->orderBy('unit_id')
            ->orderBy('billing_interval');
    }

    public function exportRow(Model $model, bool $sensitive = false): array
    {
        /** @var UnitRate $rate */
        $rate = $model;

        return [
            $rate->unit?->property?->slug,
            $rate->unit?->name,
            $rate->billing_interval,
            $rate->billing_unit?->value ?? $rate->billing_unit,
            $rate->amount,
            $rate->currency,
            $rate->is_active ? '1' : '0',
            $rate->effective_from?->format('Y-m-d'),
            $rate->effective_until?->format('Y-m-d'),
        ];
    }
}
