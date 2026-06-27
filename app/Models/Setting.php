<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'site_name',
    'country_code',
    'locale',
    'currency',
    'timezone',
    'lease_id_prefix',
])]
class Setting extends Model
{
    use HasFactory;

    public static function get(): self
    {
        return self::firstOrCreate([], [
            'site_name' => 'OpenKOS',
            'country_code' => 'ID',
            'locale' => 'id',
            'currency' => 'IDR',
            'timezone' => 'Asia/Jakarta',
            'lease_id_prefix' => 'LSX',
        ]);
    }
}
