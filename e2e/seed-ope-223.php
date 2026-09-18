use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$owner = User::query()->where('email', 'budi@openkos.com')->firstOrFail();
$property = Property::factory()->create([
    'name' => 'OPE-223 Pricing Property',
    'slug' => 'ope223-pricing-property',
    'public_slug' => 'ope223-pricing-property',
    'rental_mode' => 'unit',
    'is_active' => true,
    'is_published' => true,
]);
$property->users()->attach($owner->id);

$type = UnitType::factory()->for($property)->create([
    'name' => 'OPE-223 Studio',
    'public_slug' => 'ope223-studio',
    'is_active' => true,
    'is_published' => true,
]);
$type->rates()->createMany([
    ['billing_interval' => 1, 'billing_unit' => 'month', 'amount' => 1000000, 'currency' => 'IDR', 'is_active' => true],
    ['billing_interval' => 3, 'billing_unit' => 'month', 'amount' => 2700000, 'currency' => 'IDR', 'is_active' => true],
    ['billing_interval' => 1, 'billing_unit' => 'month', 'amount' => 100, 'currency' => 'USD', 'is_active' => true],
]);

$unit = Unit::factory()->for($property)->for($type)->create([
    'name' => 'OPE-223 Unit 1',
]);
$targetUnit = Unit::factory()->for($property)->for($type)->create([
    'name' => 'OPE-223 Unit 2',
]);
$unitRate = $unit->rates()->firstOrFail();
$unitRate->update(['amount' => 1250000, 'currency' => 'IDR', 'is_active' => true]);

$tenantUser = User::factory()->create([
    'name' => 'OPE-223 Tenant',
    'email' => 'ope223.tenant@example.test',
    'password' => Hash::make('password'),
]);
$tenant = Tenant::factory()->create([
    'name' => 'OPE-223 Tenant',
    'user_id' => $tenantUser->id,
]);

file_put_contents(
    getenv('OPENKOS_E2E_FIXTURE_PATH'),
    json_encode([
        'property' => $property->slug,
        'unit' => $unit->slug,
        'target_unit' => $targetUnit->slug,
        'unit_type' => $type->id,
        'tenant' => $tenant->name,
    ], JSON_THROW_ON_ERROR),
);
