use App\Enums\LeaseStatus;
use App\Enums\PropertyRentalMode;
use App\Enums\UnitStatus;
use App\Models\Lease;
use App\Models\Property;
use App\Models\PropertyRate;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Actions\Invoices\GenerateInvoices;
use Illuminate\Support\Facades\Hash;

$makeTenant = static function (string $name, string $email): Tenant {
    $user = User::factory()->create([
        'name' => $name,
        'email' => $email,
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    return Tenant::factory()->create([
        'name' => $name,
        'user_id' => $user->id,
        'id_card_number' => fake()->numerify('3222############'),
    ]);
};

$makeProperty = static function (string $name, string $slug, PropertyRentalMode $mode): Property {
    $property = Property::factory()->create([
        'name' => $name,
        'slug' => $slug,
        'public_slug' => $slug,
        'rental_mode' => $mode,
        'is_active' => true,
        'is_published' => true,
    ]);

    PropertyRate::factory()->for($property)->create([
        'amount' => '2500000',
        'currency' => 'IDR',
        'is_active' => true,
        'billing_interval' => 1,
        'billing_unit' => 'month',
    ]);

    return $property->refresh();
};

$makeUnit = static function (Property $property, string $name): Unit {
    $unitType = UnitType::factory()->for($property)->create([
        'name' => $name.' Type',
        'public_slug' => str($name)->slug().'-type',
        'is_active' => true,
        'is_published' => true,
    ]);

    return Unit::factory()->for($property)->withRate('1500000', 'IDR')->create([
        'name' => $name,
        'unit_type_id' => $unitType->id,
        'status' => UnitStatus::Available,
    ]);
};

$makeUnitLease = static function (Property $property, Unit $unit, Tenant $tenant, string $reference, string $endDate, bool $generateInvoice = false): Lease {
    $rate = $unit->defaultActiveRate();
    $lease = Lease::query()->create([
        'reference' => $reference,
        'primary_tenant_id' => $tenant->id,
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => $endDate,
        'rent_amount' => $rate->amount,
        'currency' => $rate->currency,
        'billing_interval' => $rate->billing_interval,
        'billing_unit' => $rate->billing_unit,
        'unit_rate_id' => $rate->id,
        'property_rate_id' => null,
        'deposit_amount' => '1000000',
        'deposit_paid_at' => now()->subMonth()->toDateString(),
        'rent_due_day' => 1,
        'status' => LeaseStatus::Active,
    ]);
    $lease->tenants()->sync([$tenant->id => ['is_primary' => true]]);
    if ($generateInvoice) {
        app(GenerateInvoices::class)->execute($lease);
    }

    return $lease->refresh();
};

$makeWholeLease = static function (Property $property, Tenant $tenant, string $reference, string $endDate, bool $generateInvoice = false): Lease {
    $rate = $property->propertyRates()->where('is_active', true)->firstOrFail();
    $lease = Lease::query()->create([
        'reference' => $reference,
        'primary_tenant_id' => $tenant->id,
        'property_id' => $property->id,
        'unit_id' => null,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => $endDate,
        'rent_amount' => $rate->amount,
        'currency' => $rate->currency,
        'billing_interval' => $rate->billing_interval,
        'billing_unit' => $rate->billing_unit,
        'unit_rate_id' => null,
        'property_rate_id' => $rate->id,
        'deposit_amount' => '1000000',
        'deposit_paid_at' => now()->subMonth()->toDateString(),
        'rent_due_day' => 1,
        'status' => LeaseStatus::Active,
    ]);
    $lease->tenants()->sync([$tenant->id => ['is_primary' => true]]);
    if ($generateInvoice) {
        app(GenerateInvoices::class)->execute($lease);
    }

    return $lease->refresh();
};

$endDate = now()->addMonthsNoOverflow(2)->toDateString();

$wholeTarget = $makeProperty('OPE-220 Whole Target', 'ope220-e2e-whole-target', PropertyRentalMode::Hybrid);
$wholeTargetUnit = $makeUnit($wholeTarget, 'Whole Target Unit');
$wholeTargetTenant = $makeTenant('OPE-220 Whole Target Tenant', 'ope220.whole-target@example.test');

$wholeOccupied = $makeProperty('OPE-220 Whole Occupied', 'ope220-e2e-whole-occupied', PropertyRentalMode::Hybrid);
$wholeOccupiedUnit = $makeUnit($wholeOccupied, 'Whole Occupied Unit');
$portalTenant = $makeTenant('OPE-220 Portal Tenant', 'ope220.portal@example.test');
$wholeBlockedTenant = $makeTenant('OPE-220 Whole Blocked Tenant', 'ope220.whole-blocked@example.test');
$wholeOccupiedLease = $makeWholeLease($wholeOccupied, $portalTenant, 'OPE220-WHOLE-ACTIVE', $endDate, true);

$unitOccupied = $makeProperty('OPE-220 Unit Occupied', 'ope220-e2e-unit-occupied', PropertyRentalMode::Hybrid);
$unitOccupiedUnit = $makeUnit($unitOccupied, 'Unit Occupied Unit');
$unitConflictTenant = $makeTenant('OPE-220 Whole Conflict Tenant', 'ope220.whole-conflict@example.test');
$unitWholeBlockedTenant = $makeTenant('OPE-220 Unit Whole Blocked Tenant', 'ope220.unit-whole-blocked@example.test');
$unitOccupiedLease = $makeUnitLease($unitOccupied, $unitOccupiedUnit, $unitConflictTenant, 'OPE220-UNIT-ACTIVE', $endDate);

$renewUnitConflict = $makeProperty('OPE-220 Renew Unit Conflict', 'ope220-e2e-renew-unit-conflict', PropertyRentalMode::Hybrid);
$renewUnitConflictUnit = $makeUnit($renewUnitConflict, 'Renew Unit Conflict Unit');
$renewUnitTenant = $makeTenant('OPE-220 Renew Unit Tenant', 'ope220.renew-unit@example.test');
$renewUnitWholeTenant = $makeTenant('OPE-220 Renew Unit Whole Tenant', 'ope220.renew-unit-whole@example.test');
$renewUnitLease = $makeUnitLease($renewUnitConflict, $renewUnitConflictUnit, $renewUnitTenant, 'OPE220-RENEW-UNIT', $endDate);
$renewUnitWholeLease = $makeWholeLease($renewUnitConflict, $renewUnitWholeTenant, 'OPE220-RENEW-UNIT-WHOLE', $endDate);

$renewWholeConflict = $makeProperty('OPE-220 Renew Whole Conflict', 'ope220-e2e-renew-whole-conflict', PropertyRentalMode::Hybrid);
$renewWholeConflictUnit = $makeUnit($renewWholeConflict, 'Renew Whole Conflict Unit');
$renewWholeTenant = $makeTenant('OPE-220 Renew Whole Tenant', 'ope220.renew-whole@example.test');
$renewWholeUnitTenant = $makeTenant('OPE-220 Renew Whole Unit Tenant', 'ope220.renew-whole-unit@example.test');
$renewWholeLease = $makeWholeLease($renewWholeConflict, $renewWholeTenant, 'OPE220-RENEW-WHOLE', $endDate);
$renewWholeUnitLease = $makeUnitLease($renewWholeConflict, $renewWholeConflictUnit, $renewWholeUnitTenant, 'OPE220-RENEW-WHOLE-UNIT', $endDate);

$renewUnitUnrelated = $makeProperty('OPE-220 Renew Unit Unrelated', 'ope220-e2e-renew-unit-unrelated', PropertyRentalMode::Hybrid);
$renewUnitUnrelatedUnit = $makeUnit($renewUnitUnrelated, 'Renew Unrelated Unit');
$renewUnitUnrelatedOther = $makeUnit($renewUnitUnrelated, 'Renew Unrelated Other');
$renewUnitUnrelatedTenant = $makeTenant('OPE-220 Renew Unrelated Tenant', 'ope220.renew-unrelated@example.test');
$renewUnitOtherTenant = $makeTenant('OPE-220 Renew Other Tenant', 'ope220.renew-other@example.test');
$renewUnitUnrelatedLease = $makeUnitLease($renewUnitUnrelated, $renewUnitUnrelatedUnit, $renewUnitUnrelatedTenant, 'OPE220-RENEW-UNRELATED', $endDate);
$renewUnitOtherLease = $makeUnitLease($renewUnitUnrelated, $renewUnitUnrelatedOther, $renewUnitOtherTenant, 'OPE220-RENEW-OTHER', $endDate);

$renewNormal = $makeProperty('OPE-220 Renew Normal', 'ope220-e2e-renew-normal', PropertyRentalMode::Hybrid);
$renewNormalUnit = $makeUnit($renewNormal, 'Renew Normal Unit');
$renewNormalTenant = $makeTenant('OPE-220 Renew Normal Tenant', 'ope220.renew-normal@example.test');
$renewNormalLease = $makeUnitLease($renewNormal, $renewNormalUnit, $renewNormalTenant, 'OPE220-RENEW-NORMAL', $endDate);

file_put_contents(
    getenv('OPENKOS_E2E_FIXTURE_PATH'),
    json_encode([
        'owner' => ['email' => 'budi@openkos.com', 'password' => 'password'],
        'portal' => ['email' => 'ope220.portal@example.test', 'password' => 'password'],
        'wholeTarget' => ['slug' => $wholeTarget->slug, 'tenant' => $wholeTargetTenant->name],
        'wholeOccupied' => ['slug' => $wholeOccupied->slug, 'lease' => $wholeOccupiedLease->id, 'tenant' => $wholeBlockedTenant->name],
        'unitOccupied' => ['slug' => $unitOccupied->slug, 'unit' => $unitOccupiedUnit->slug, 'tenant' => $unitConflictTenant->name, 'wholeTenant' => $unitWholeBlockedTenant->name],
        'renewUnitConflict' => ['lease' => $renewUnitLease->id],
        'renewWholeConflict' => ['lease' => $renewWholeLease->id],
        'renewUnitUnrelated' => ['lease' => $renewUnitUnrelatedLease->id],
        'renewNormal' => ['lease' => $renewNormalLease->id],
    ], JSON_THROW_ON_ERROR),
);
