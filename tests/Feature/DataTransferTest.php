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

it('renders dedicated contextual transfer pages with export filters', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    $unit = Unit::factory()->for($property)->create();

    $this->actingAs($user)
        ->get(route('properties.transfer.import'))
        ->assertInertia(fn ($page) => $page
            ->component('data-transfer/import')
            ->where('dataset', 'properties'));

    $this->actingAs($user)
        ->get(route('properties.transfer.export', [
            'search' => 'Sunrise',
            'status' => 'active,archived',
            'type' => 'boarding_house,apartment',
        ]))
        ->assertInertia(fn ($page) => $page
            ->component('data-transfer/export')
            ->where('dataset', 'properties')
            ->where('initialQuery.search', 'Sunrise')
            ->where('initialQuery.status', 'active,archived')
            ->where('initialQuery.type', 'boarding_house,apartment')
            ->where('includeArchivedDefault', true));

    $this->actingAs($user)
        ->get(route('properties.units.rates.transfer.export', [
            'property' => $property,
            'unit' => $unit,
        ]))
        ->assertInertia(fn ($page) => $page
            ->component('data-transfer/export')
            ->where('dataset', 'unit-rates')
            ->where('context.property_slug', $property->slug)
            ->where('context.unit_name', $unit->name));
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

it('rejects duplicate CSV headers', function () {
    $user = User::factory()->owner()->create();

    $response = $this->actingAs($user)->post(route('data-transfer.preview'), [
        'dataset' => 'properties',
        'file' => csvFile('properties.csv', "slug,name,name\nproperty,Property,Duplicate"),
    ]);

    $response->assertStatus(422)->assertJsonPath('valid', false);
    expect($response->json('errors.0.field'))->toBe('name');
});

it('rejects CSV rows whose width does not match the header', function () {
    $user = User::factory()->owner()->create();

    $response = $this->actingAs($user)->post(route('data-transfer.preview'), [
        'dataset' => 'properties',
        'file' => csvFile('properties.csv', "slug,name\nproperty,Property,Extra"),
    ]);

    $response->assertStatus(422)->assertJsonPath('valid', false);
    expect($response->json('errors.0.field'))->toBe('row');
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

it('rejects a name-only tenant row that matches an existing tenant', function () {
    $user = User::factory()->owner()->create();
    Tenant::factory()->create(['name' => 'Alex One']);

    $response = $this->actingAs($user)->post(route('data-transfer.preview'), [
        'dataset' => 'tenants',
        'file' => csvFile('tenants.csv', "name\nAlex One"),
    ]);

    $response->assertStatus(422)->assertJsonPath('valid', false);
    expect($response->json('errors.0.message'))->toContain('Manual resolution');
});

it('reports multiple existing name-only tenant candidates explicitly', function () {
    $user = User::factory()->owner()->create();
    Tenant::factory()->count(2)->create(['name' => 'Alex One']);

    $response = $this->actingAs($user)->post(route('data-transfer.preview'), [
        'dataset' => 'tenants',
        'file' => csvFile('tenants.csv', "name\nAlex One"),
    ]);

    $response->assertStatus(422)->assertJsonPath('valid', false);
    expect($response->json('errors.0.message'))->toContain('Multiple existing tenants');
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

it('applies two-value property type export filters', function () {
    $user = User::factory()->owner()->create();
    Property::factory()->create(['name' => 'Boarding Export', 'type' => 'boarding_house']);
    Property::factory()->create(['name' => 'Apartment Export', 'type' => 'apartment']);
    Property::factory()->create(['name' => 'Villa Export', 'type' => 'villa']);

    $response = $this->actingAs($user)->get(route('data-transfer.export', [
        'dataset' => 'properties',
        'type' => 'boarding_house,apartment',
    ]));

    $response->assertDownload('properties-v1.csv');
    expect($response->streamedContent())
        ->toContain('Boarding Export')
        ->toContain('Apartment Export')
        ->not->toContain('Villa Export');
});

it('does not broaden an export for an unknown combined status value', function () {
    $user = User::factory()->owner()->create();
    Unit::factory()->create(['name' => 'Known Filter Unit', 'status' => 'available']);

    $response = $this->actingAs($user)->get(route('data-transfer.export', [
        'dataset' => 'units',
        'status' => 'available,not-a-status',
    ]));

    $response->assertDownload('units-v1.csv');
    expect($response->streamedContent())->not->toContain('Known Filter Unit');
});

it('applies two-value unit status export filters', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    Unit::factory()->for($property)->create(['name' => 'Available Export', 'status' => 'available']);
    Unit::factory()->for($property)->occupied()->create(['name' => 'Occupied Export']);
    Unit::factory()->for($property)->maintenance()->create(['name' => 'Maintenance Export']);

    $response = $this->actingAs($user)->get(route('data-transfer.export', [
        'dataset' => 'units',
        'status' => 'available,occupied',
    ]));

    $response->assertDownload('units-v1.csv');
    expect($response->streamedContent())
        ->toContain('Available Export')
        ->toContain('Occupied Export')
        ->not->toContain('Maintenance Export');
});

it('applies two-value tenant app access export filters', function () {
    $user = User::factory()->owner()->create();
    Tenant::factory()->create(['name' => 'No Access Export', 'user_id' => null]);
    Tenant::factory()->withUser(User::factory()->create())->create(['name' => 'Active Access Export']);
    Tenant::factory()->withUser(User::factory()->create(['is_active' => false]))->create(['name' => 'Disabled Access Export']);

    $response = $this->actingAs($user)->get(route('data-transfer.export', [
        'dataset' => 'tenants',
        'app_access' => 'none,active',
    ]));

    $response->assertDownload('tenants-v1.csv');
    expect($response->streamedContent())
        ->toContain('No Access Export')
        ->toContain('Active Access Export')
        ->not->toContain('Disabled Access Export');
});

it('applies two-value unit rate currency export filters', function () {
    $user = User::factory()->owner()->create();
    $unit = Unit::factory()->create(['name' => 'Currency Export Unit']);
    $unit->rates()->create([
        'billing_interval' => 1,
        'billing_unit' => 'year',
        'amount' => 1200,
        'currency' => 'USD',
        'is_active' => true,
    ]);
    $unit->rates()->create([
        'billing_interval' => 1,
        'billing_unit' => 'week',
        'amount' => 300,
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->get(route('data-transfer.export', [
        'dataset' => 'unit-rates',
        'currency' => 'IDR,USD',
    ]));

    $response->assertDownload('unit-rates-v1.csv');
    expect($response->streamedContent())
        ->toContain('USD')
        ->not->toContain('EUR');
});

it('applies two-value unit name export filters', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create();
    Unit::factory()->for($property)->create(['name' => 'Unit Filter One']);
    Unit::factory()->for($property)->create(['name' => 'Unit Filter Two']);
    Unit::factory()->for($property)->create(['name' => 'Unit Filter Three']);

    $response = $this->actingAs($user)->get(route('data-transfer.export', [
        'dataset' => 'unit-rates',
        'unit_name' => 'Unit Filter One,Unit Filter Two',
    ]));

    $response->assertDownload('unit-rates-v1.csv');
    expect($response->streamedContent())
        ->toContain('Unit Filter One')
        ->toContain('Unit Filter Two')
        ->not->toContain('Unit Filter Three');
});

it('keeps tenant inactive and archived exports separate from the list', function () {
    $user = User::factory()->owner()->create();
    Tenant::factory()->create(['name' => 'Active Parity', 'is_active' => true]);
    Tenant::factory()->inactive()->create(['name' => 'Inactive Parity']);
    $archived = Tenant::factory()->create(['name' => 'Archived Parity']);
    $archived->delete();

    $inactiveList = $this->actingAs($user)
        ->get(route('tenants.index', ['status' => 'inactive']))
        ->assertOk();
    $inactiveList->assertInertia(fn ($page) => $page
        ->where('tenants.data', fn ($rows) => collect($rows)->pluck('name')->all() === ['Inactive Parity'])
    );

    $archivedList = $this->actingAs($user)
        ->get(route('tenants.index', ['status' => 'archived']))
        ->assertOk();
    $archivedList->assertInertia(fn ($page) => $page
        ->where('tenants.data', fn ($rows) => collect($rows)->pluck('name')->all() === ['Archived Parity'])
    );

    $inactiveExport = $this->actingAs($user)->get(route('data-transfer.export', [
        'dataset' => 'tenants',
        'status' => 'inactive',
    ]));
    expect($inactiveExport->streamedContent())
        ->toContain('Inactive Parity')
        ->not->toContain('Active Parity')
        ->not->toContain('Archived Parity');

    $archivedExport = $this->actingAs($user)->get(route('data-transfer.export', [
        'dataset' => 'tenants',
        'status' => 'archived',
    ]));
    expect($archivedExport->streamedContent())
        ->toContain('Archived Parity')
        ->not->toContain('Active Parity')
        ->not->toContain('Inactive Parity');
});

it('keeps tenant export search aligned with authorized list search fields', function () {
    $admin = User::factory()->admin()->create();
    Tenant::factory()->create([
        'name' => 'Searchable Tenant',
        'phone' => '+628123456700',
        'id_card_number' => 'SECRET-SEARCH-ID',
    ]);

    $list = $this->actingAs($admin)
        ->get(route('tenants.index', ['search' => 'SECRET-SEARCH-ID']))
        ->assertOk();
    $list->assertInertia(fn ($page) => $page->has('tenants.data', 0));

    $ordinary = $this->actingAs($admin)->get(route('data-transfer.export', [
        'dataset' => 'tenants',
        'search' => 'SECRET-SEARCH-ID',
    ]));
    expect($ordinary->streamedContent())->not->toContain('Searchable Tenant');

    $owner = User::factory()->owner()->create();
    $sensitive = $this->actingAs($owner)->get(route('data-transfer.export', [
        'dataset' => 'tenants',
        'search' => 'SECRET-SEARCH-ID',
        'include_sensitive' => 1,
    ]));
    expect($sensitive->streamedContent())
        ->toContain('Searchable Tenant')
        ->toContain('SECRET-SEARCH-ID');
});

it('does not use property or unit slugs as export search fields', function () {
    $user = User::factory()->owner()->create();
    $property = Property::factory()->create(['slug' => 'portable-property-slug', 'name' => 'Portable Property']);
    Unit::factory()->for($property)->create(['slug' => 'portable-unit-slug', 'name' => 'Portable Unit']);

    $propertyExport = $this->actingAs($user)->get(route('data-transfer.export', [
        'dataset' => 'properties',
        'search' => 'portable-property-slug',
    ]));
    $unitExport = $this->actingAs($user)->get(route('data-transfer.export', [
        'dataset' => 'units',
        'search' => 'portable-unit-slug',
    ]));

    expect($propertyExport->streamedContent())->not->toContain('Portable Property')
        ->and($unitExport->streamedContent())->not->toContain('Portable Unit');
});

it('accepts exactly 10000 rows and rejects the 10001st row', function () {
    $user = User::factory()->owner()->create();
    $rows = ['slug,name'];

    for ($index = 1; $index <= 10000; $index++) {
        $rows[] = "boundary-{$index},Boundary {$index}";
    }

    $boundary = $this->actingAs($user)->post(route('data-transfer.preview'), [
        'dataset' => 'properties',
        'file' => csvFile('properties.csv', implode("\n", $rows)),
    ]);

    $boundary->assertSuccessful()
        ->assertJsonPath('valid', true)
        ->assertJsonPath('row_count', 10000);
    expect(Property::count())->toBe(0);

    $rows[] = 'boundary-10001,Boundary 10001';
    $overflow = $this->actingAs($user)->post(route('data-transfer.preview'), [
        'dataset' => 'properties',
        'file' => csvFile('properties.csv', implode("\n", $rows)),
    ]);

    $overflow->assertStatus(422)
        ->assertJsonPath('valid', false)
        ->assertJsonPath('row_count', 10001);
    expect(Property::count())->toBe(0);
});
