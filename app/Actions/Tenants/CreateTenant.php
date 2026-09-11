<?php

namespace App\Actions\Tenants;

use App\Models\Tenant;

final class CreateTenant
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes): Tenant
    {
        return Tenant::create($attributes);
    }
}
