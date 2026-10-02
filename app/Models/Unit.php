<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\SerializesDatesWithTimezone;
use App\Enums\ReservationStatus;
use App\Enums\UnitStatus;
use App\Services\Payments\MoneyConverter;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable([
    'property_id',
    'unit_type_id',
    'name',
    'slug',
    'floor',
    'description',
    'size_sqm',
    'capacity',
    'status',
    'notes',
])]
class Unit extends Model
{
    use Auditable, HasFactory, SerializesDatesWithTimezone, SoftDeletes;

    protected function casts(): array
    {
        return [
            'size_sqm' => 'decimal:2',
            'capacity' => 'integer',
            'status' => UnitStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Unit $unit) {
            if (empty($unit->slug)) {
                $base = Str::slug($unit->name) ?: 'unit';
                $slug = $base;
                $counter = 1;
                while (static::withTrashed()
                    ->where('property_id', $unit->property_id)
                    ->where('slug', $slug)
                    ->exists()
                ) {
                    $slug = $base.'-'.++$counter;
                }
                $unit->slug = $slug;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function unitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class);
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(UnitRate::class);
    }

    public function utilityMeters(): HasMany
    {
        return $this->hasMany(UtilityMeter::class);
    }

    public function meters(): HasMany
    {
        return $this->utilityMeters();
    }

    public function activeRates(): HasMany
    {
        // Deterministic, meaningful order: shortest billing period first
        // (day < week < month < year), then interval, then id as a final
        // tiebreaker. Without this, two rates that share a billing_interval
        // (e.g. "1 month" and "1 year") tie, and the DB may return them in a
        // different order after an update — flipping which rate a "first rate"
        // consumer (the units list price) shows.
        return $this->hasMany(UnitRate::class)
            ->where('is_active', true)
            ->orderByRaw(
                "case billing_unit when 'day' then 1 when 'week' then 2 when 'month' then 3 when 'year' then 4 else 5 end"
            )
            ->orderBy('billing_interval')
            ->orderBy('id');
    }

    public function defaultActiveRate(?string $currency = null): ?UnitRate
    {
        $preferredCurrency = app(MoneyConverter::class)->normalizeCurrency($currency);
        $rates = $this->activeRates()->get();

        return $rates->first(fn (UnitRate $rate): bool => $rate->currency === $preferredCurrency)
            ?? $rates->first();
    }

    public function scopeAvailableForAssignment(Builder $query): void
    {
        $now = now();
        $today = $now->toDateString();
        $activeOccupants = DB::table('lease_tenant')
            ->join('leases', 'leases.id', '=', 'lease_tenant.lease_id')
            ->selectRaw('COUNT(*)')
            ->whereColumn('leases.unit_id', 'units.id')
            ->whereIn('leases.id', Lease::query()->active()->select('id'));
        $reservedSlots = DB::table('reservations')
            ->selectRaw('COUNT(*)')
            ->whereColumn('unit_id', 'units.id')
            ->where('status', ReservationStatus::Confirmed->value)
            ->where('expires_at', '>', $now)
            ->whereDate('move_in_date', '<=', $today);

        $query->whereNull('deleted_at')
            ->whereNotIn('status', [UnitStatus::Maintenance->value, UnitStatus::Unavailable->value])
            ->whereDoesntHave('property', fn (Builder $q) => $q->whereHas('activeWholePropertyLeases'))
            ->whereDoesntHave('property', fn (Builder $q) => $q->whereHas('reservations', fn (Builder $reservations) => $reservations
                ->where('reservations.status', ReservationStatus::Confirmed->value)
                ->where('reservations.expires_at', '>', $now)
                ->whereNull('reservations.unit_id')
                ->whereDate('reservations.move_in_date', '<=', $today)))
            ->whereRaw(
                "units.capacity > ({$activeOccupants->toSql()}) + ({$reservedSlots->toSql()})",
                [...$activeOccupants->getBindings(), ...$reservedSlots->getBindings()],
            );
    }

    public function scopeEligibleForPublicOffering(Builder $query): void
    {
        $query->whereNotIn('status', [UnitStatus::Maintenance->value, UnitStatus::Unavailable->value])
            ->whereDoesntHave('property', fn (Builder $q) => $q->whereHas('activeWholePropertyLeases'))
            ->where(function (Builder $query): void {
                $query->whereHas('rates', fn (Builder $query) => $query->where('is_active', true))
                    ->orWhereHas('unitType', fn (Builder $query) => $query->whereHas('rates', fn (Builder $query) => $query->where('is_active', true)));
            });
    }

    public function scopeWithOccupiedCount(Builder $query): void
    {
        $query->addSelect([
            'occupied_count' => DB::table('lease_tenant')
                ->selectRaw('COALESCE(COUNT(*), 0)')
                ->whereIn('lease_id', Lease::query()
                    ->active()
                    ->whereColumn('unit_id', 'units.id')
                    ->select('id')),
        ]);
    }

    public function scopeStatusFilter(Builder $query, string $status): void
    {
        if ($status === 'archived') {
            $query->whereNotNull('units.deleted_at');

            return;
        }

        if (in_array($status, UnitStatus::values(), true)) {
            $query->whereNull('units.deleted_at')->where('units.status', $status);

            return;
        }

        $query->whereRaw('1 = 0');
    }

    public function scopeListSearch(Builder $query, string $search): void
    {
        $search = mb_strtolower($search);

        $query->where(function (Builder $query) use ($search): void {
            $query->whereRaw('lower(units.name) like ?', ["%{$search}%"])
                ->orWhereRaw('lower(units.floor) like ?', ["%{$search}%"]);
        });
    }

    public function maintenanceTickets(): HasMany
    {
        return $this->hasMany(MaintenanceTicket::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }
}
