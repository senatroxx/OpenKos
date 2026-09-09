<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\HasMedia;
use App\Concerns\SerializesDatesWithTimezone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;

#[Fillable([
    'user_id',
    'name',
    'phone',
    'id_card_number',
    'emergency_contact_name',
    'emergency_contact_phone',
    'notes',
    'is_active',
])]
class Tenant extends Model
{
    use Auditable, HasFactory, HasMedia, Notifiable, SerializesDatesWithTimezone, SoftDeletes;

    protected array $auditableMask = ['phone', 'id_card_number', 'emergency_contact_phone'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function routeNotificationForWhatsApp(Notification $notification): string
    {
        return $this->phone;
    }

    public function routeNotificationForMail(Notification $notification): array
    {
        $email = $this->user?->email;

        return $email ? [$email => $this->name] : [];
    }

    public function hasReminderRoute(array $channels): bool
    {
        $hasPhoneRoute = $this->phone
            && (in_array('whatsapp', $channels, true) || in_array('log', $channels, true));
        $hasMailRoute = $this->user?->email && in_array('mail', $channels, true);

        return (bool) ($hasPhoneRoute || $hasMailRoute);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leases(): BelongsToMany
    {
        return $this->belongsToMany(Lease::class)
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TenantDocument::class);
    }

    public function scopeStatusFilter(Builder $query, string $status): void
    {
        match ($status) {
            'active' => $query->whereNull('tenants.deleted_at')->where('tenants.is_active', true),
            'inactive' => $query->whereNull('tenants.deleted_at')->where('tenants.is_active', false),
            'archived' => $query->whereNotNull('tenants.deleted_at'),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function scopeAppAccessFilter(Builder $query, string $status): void
    {
        match ($status) {
            'none' => $query->whereNull('user_id'),
            'active' => $query->whereHas('user', fn (Builder $user) => $user
                ->where('is_active', true)
                ->whereNotNull('email_verified_at')),
            'invited' => $query->whereHas('user', fn (Builder $user) => $user
                ->whereNotNull('invited_at')
                ->where(fn (Builder $state) => $state
                    ->where('is_active', false)
                    ->orWhereNull('email_verified_at'))),
            'disabled' => $query->whereHas('user', fn (Builder $user) => $user
                ->where('is_active', false)
                ->whereNull('invited_at')
                ->whereNotNull('email_verified_at')),
            'email_only' => $query->whereHas('user', fn (Builder $user) => $user
                ->whereNull('invited_at')
                ->whereNull('email_verified_at')),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function scopeListSearch(Builder $query, string $search, bool $includeSensitive = true): void
    {
        $search = mb_strtolower($search);

        $query->where(function (Builder $query) use ($search, $includeSensitive): void {
            $query->whereRaw('lower(tenants.name) like ?', ["%{$search}%"])
                ->orWhereRaw('lower(tenants.phone) like ?', ["%{$search}%"]);

            if ($includeSensitive) {
                $query->orWhereRaw('lower(tenants.id_card_number) like ?', ["%{$search}%"]);
            }
        });
    }
}
