<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
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
    use HasFactory, Notifiable, SoftDeletes;

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
}
