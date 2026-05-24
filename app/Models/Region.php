<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'country_code',
    'name',
    'slug',
])]
class Region extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Region $region) {
            if (empty($region->slug)) {
                $region->slug = Str::slug($region->name);
            }
        });
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }
}
