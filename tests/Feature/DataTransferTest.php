<?php

use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Region;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitRate;
use App\Models\User;
use Database\Seeders\RegionAndCitySeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;

uses()->beforeEach(function () {
    $this->seed([RoleAndPermissionSeeder::class, RegionAndCitySeeder::class]);
    Setting::set('supported_currencies', ['IDR', 'USD']);
    PropertyType::firstOrCreate([
        'slug' => 'boarding_house',
    ], [
        'label' => 'Boarding House',
        'is_active' => true,
    ]);
});

function csvFile(string $name, string $contents): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents);
}

it('previews the complete properties file without persisting anything', function () {
    $user = User::factory()->owner()->create();
    $region = Region::query()->where('country_code', 'ID')->firstOrFail();
    $city = $region->cities()->firstOrFail();
    $csv = implode("\n", [
        'slug,name,type,region_country_code,region_name,city_name,address,postal_code,phone,description,is_active',
        "sunrise-house,Sunrise House,boarding_house,ID,{$region->name},{$city->name},Main Road,12345,+628123456789,Imported,1",
    ]);

    $response = $this->actingAs($user)->post(route('data-transfer.preview'), [
        'dataset' => 'properties',
        'file' => csvFile('properties.csv', $csv),
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('valid', true)
        ->assertJsonPath('row_count', 1)
        ->assertJsonPath('error_count', 0);

    $this->assertDatabaseMissing('properties', ['slug' => 'sunrise-house']);
});

it('rejects unknown headers and reports the file-level header error', function () {
    $user = User::factory()->owner()->create();
    $csv = implode("\n", [
        'slug,name,unexpected',
        ',,ignored',
        'known-slug,Known,ignored',
    ]);

    $response = $this->actingAs($user)->post(route('data-transfer.preview'), [
        'dataset' => 'properties',
        'file' => csvFile('properties.csv', $csv),
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('valid', false)
        ->assertJsonPath('error_count', 1);

    expect($response->json('errors.0.field'))->toBe('unexpected');
});

it('rejects a missing required header before validating rows', function () {
    $user = User::factory()->owner()->create();
    $csv = "slug,is_active\nmissing-name,1";

    $response = $this->actingAs($user)->post(route('data-transfer.preview'), [
        'dataset' => 'properties',
        'file' => csvFile('properties.csv', $csv),
    ]);

    $response->assertStatus(422)->assertJsonPath('valid', false);
    expect(collect($response->json('errors'))->pluck('field')->all())
        ->toContain('name');
});

it('commits a valid file atomically through the create action', function () {
    $user = User::factory()->owner()->create();
    $csv = implode("\n", [
        'slug,name,is_active',
        'first-house,First House,1',
        'second-house,Second House,1',
    ]);

    $response = $this->actingAs($user)->post(route('data-transfer.commit'), [
        'dataset' => 'properties',
        'file' => csvFile('properties.csv', $csv),
    ]);

    $response->assertSuccessful()->assertJsonPath('count', 2);
    $this->assertDatabaseHas('properties', ['slug' => 'first-house']);
    $this->assertDatabaseHas('properties', ['slug' => 'second-house']);
    $this->assertDatabaseHas('property_user', [
        'property_id' => Property::where('slug', 'first-house')->value('id'),
        'user_id' => $user->id,
    ]);
});

it('rolls back the complete commit when one property conflicts', function () {
    $user = User::factory()->owner()->create();
    Property::factory()->create(['slug' => 'existing-house', 'name' => 'Existing House']);
    $csv = implode("\n", [
        'slug,name',
        'new-house,New House',
        'existing-house,Existing Replacement',
    ]);

    $response = $this->actingAs($user)->post(route('data-transfer.commit'), [
        'dataset' => 'properties',
        'file' => csvFile('properties.csv', $csv),
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseMissing('properties', ['slug' => 'new-house']);
    expect(Property::where('slug', 'existing-house')->value('name'))->toBe('Existing House');
});

it('imports units and unit rates through their domain references', function () {
    $user = User::factory()->owner()->create();
    Property::factory()->create([
        'slug' => 'sunrise-house',
        'name' => 'Sunrise House',
    ]);

    $this->actingAs($user)->post(route('data-transfer.commit'), [
        'dataset' => 'units',
        'file' => csvFile('units.csv', implode("\n", [
            'property_slug,name,capacity,status',
            'sunrise-house,Unit 101,2,available',
        ])),
    ])->assertSuccessful()->assertJsonPath('count', 1);

    $this->assertDatabaseHas('units', [
        'name' => 'Unit 101',
        'capacity' => 2,
    ]);

    $this->actingAs($user)->post(route('data-transfer.commit'), [
        'dataset' => 'unit-rates',
        'file' => csvFile('unit-rates.csv', implode("\n", [
            'property_slug,unit_name,billing_interval,billing_unit,amount,currency',
            'sunrise-house,Unit 101,1,month,1250.00,USD',
        ])),
    ])->assertSuccessful()->assertJsonPath('count', 1);

    $unit = Unit::where('name', 'Unit 101')->firstOrFail();
    expect(UnitRate::where('unit_id', $unit->id)
        ->where('billing_unit', 'month')
        ->where('currency', 'USD')
        ->exists())->toBeTrue();
});

it('rejects duplicate explicit unit slugs in one file', function () {
    $user = User::factory()->owner()->create();
    Property::factory()->create(['slug' => 'sunrise-house']);
    $csv = implode("\n", [
        'property_slug,name,slug,capacity',
        'sunrise-house,Unit 101,shared-slug,1',
        'sunrise-house,Unit 102,shared-slug,1',
    ]);

    $response = $this->actingAs($user)->post(route('data-transfer.preview'), [
        'dataset' => 'units',
        'file' => csvFile('units.csv', $csv),
    ]);

    $response->assertStatus(422)->assertJsonPath('valid', false);
    expect(collect($response->json('errors'))->pluck('field')->all())
        ->toContain('slug');
});

it('detects tenant duplicates only from explicit identifier fields', function () {
    $user = User::factory()->owner()->create();
    $csv = implode("\n", [
        'name,phone,notes',
        'Alex One,+628111111111,first',
        'Alex One,+628222222222,second',
        'Alex Two,+628111111111,duplicate phone',
    ]);

    $response = $this->actingAs($user)->post(route('data-transfer.preview'), [
        'dataset' => 'tenants',
        'file' => csvFile('tenants.csv', $csv),
    ]);

    $response->assertStatus(422)->assertJsonPath('valid', false);
    expect(collect($response->json('errors'))->pluck('field')->all())->toContain('phone');
});

it('keeps tenant imports owner-authorized and does not create leases or users', function () {
    $admin = User::factory()->admin()->create();
    $csv = "name,phone\nTenant One,+628123456789";

    $this->actingAs($admin)
        ->post(route('data-transfer.commit'), [
            'dataset' => 'tenants',
            'file' => csvFile('tenants.csv', $csv),
        ])
        ->assertForbidden();

    $owner = User::factory()->owner()->create();
    $this->actingAs($owner)
        ->post(route('data-transfer.commit'), [
            'dataset' => 'tenants',
            'file' => csvFile('tenants.csv', $csv),
        ])
        ->assertSuccessful();

    $tenant = Tenant::where('name', 'Tenant One')->firstOrFail();
    expect($tenant->user_id)->toBeNull()
        ->and($tenant->leases()->count())->toBe(0);
});

it('excludes sensitive tenant identifiers unless explicitly authorized', function () {
    $user = User::factory()->owner()->create();
    Tenant::factory()->create([
        'name' => 'Private Tenant',
        'phone' => '+628123456789',
        'id_card_number' => 'SECRET-ID',
    ]);

    $ordinary = $this->actingAs($user)->get(route('data-transfer.export', ['dataset' => 'tenants']));
    $ordinary->assertDownload('tenants-v1.csv');
    expect($ordinary->streamedContent())->not->toContain('SECRET-ID');

    $sensitive = $this->actingAs($user)->get(route('data-transfer.export', [
        'dataset' => 'tenants',
        'include_sensitive' => 1,
    ]));
    $sensitive->assertDownload('tenants-v1.csv');
    expect($sensitive->streamedContent())->toContain('SECRET-ID');
});

it('excludes inactive unit rates unless explicitly requested', function () {
    $user = User::factory()->owner()->create();
    $unit = Unit::factory()->create();
    $unit->rates()->create([
        'billing_interval' => 1,
        'billing_unit' => 'year',
        'amount' => 24000,
        'currency' => 'USD',
        'is_active' => false,
    ]);

    $ordinary = $this->actingAs($user)->get(route('data-transfer.export', [
        'dataset' => 'unit-rates',
    ]));
    $ordinary->assertDownload('unit-rates-v1.csv');
    expect($ordinary->streamedContent())->not->toContain('24000');

    $all = $this->actingAs($user)->get(route('data-transfer.export', [
        'dataset' => 'unit-rates',
        'include_archived' => 1,
    ]));
    $all->assertDownload('unit-rates-v1.csv');
    expect($all->streamedContent())->toContain('24000');
});
