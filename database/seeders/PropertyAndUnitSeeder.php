<?php

namespace Database\Seeders;

use App\Enums\LeaseStatus;
use App\Enums\UnitStatus;
use App\Models\City;
use App\Models\Property;
use App\Models\Region;
use App\Models\Unit;
use App\Models\UnitRate;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PropertyAndUnitSeeder extends Seeder
{
    private const NAMESPACE = 'ope-184-demo';

    private array $properties = [
        [
            'name' => 'Kos Melati Indah',
            'address' => 'Jl. Melati No. 10, RT 03/RW 05',
            'region_name' => 'Jawa Barat',
            'city_name' => 'Kota Bandung',
            'postal_code' => '40111',
            'phone' => '081234567890',
            'description' => 'Kos nyaman di pusat kota dengan akses mudah ke kampus dan pusat perbelanjaan.',
        ],
        [
            'name' => 'Kos Mawar Putih',
            'address' => 'Jl. Mawar No. 25, Kelurahan Sukamaju',
            'region_name' => 'DKI Jakarta',
            'city_name' => 'Kota Jakarta Selatan',
            'postal_code' => '12110',
            'phone' => '081234567891',
            'description' => 'Kos eksklusif dengan fasilitas lengkap, AC, WiFi, dan listrik termasuk.',
        ],
        [
            'name' => 'Kos Kenanga Asri',
            'address' => 'Jl. Kenanga No. 7, Perumahan Griya Asri',
            'region_name' => 'Jawa Timur',
            'city_name' => 'Kota Surabaya',
            'postal_code' => '60111',
            'phone' => '081234567892',
            'description' => 'Kos asri dengan taman hijau, cocok untuk pekerja dan mahasiswa.',
        ],
        [
            'name' => 'Kos Dahlia Permai',
            'address' => 'Jl. Dahlia No. 33, Kelurahan Caturtunggal',
            'region_name' => 'DI Yogyakarta',
            'city_name' => 'Kabupaten Sleman',
            'postal_code' => '55281',
            'phone' => '081234567893',
            'description' => 'Kos strategis dekat kampus UGM, lingkungan aman dan nyaman.',
        ],
        [
            'name' => 'Kos Anggrek Residence',
            'address' => 'Jl. Anggrek Raya No. 15, Kecamatan Denpasar Selatan',
            'region_name' => 'Bali',
            'city_name' => 'Kota Denpasar',
            'postal_code' => '80221',
            'phone' => '081234567894',
            'description' => 'Kos modern dengan kolam renang dan area lounge bersama.',
        ],
        [
            'name' => 'Kos Tulip Hijau',
            'address' => 'Jl. Tulip No. 5A, Kelurahan Tembalang',
            'region_name' => 'Jawa Tengah',
            'city_name' => 'Kota Semarang',
            'postal_code' => '50271',
            'phone' => '081234567895',
            'description' => 'Kos baru dengan desain minimalis, dekat kampus UNDIP.',
        ],
        [
            'name' => 'Kos Bougenville Suites',
            'address' => 'Jl. Bougenville No. 88, Kecamatan Medan Baru',
            'region_name' => 'Sumatera Utara',
            'city_name' => 'Kota Medan',
            'postal_code' => '20111',
            'phone' => '081234567896',
            'description' => 'Kos premium full furnished dengan kamar mandi dalam.',
        ],
        [
            'name' => 'Kos Flamboyan Indah',
            'address' => 'Jl. Flamboyan No. 12, Kelurahan Panakkukang',
            'region_name' => 'Sulawesi Selatan',
            'city_name' => 'Kota Makassar',
            'postal_code' => '90231',
            'phone' => '081234567897',
            'description' => 'Kos nyaman dekat pusat bisnis dan perbelanjaan.',
        ],
        [
            'name' => 'Kos Cendana Asri',
            'address' => 'Jl. Cendana Raya No. 3, Kecamatan Sukolilo',
            'region_name' => 'Jawa Timur',
            'city_name' => 'Kota Surabaya',
            'postal_code' => '60111',
            'phone' => '081234567898',
            'description' => 'Kos keluarga dengan suasana asri dan parkir luas.',
        ],
        [
            'name' => 'Kos Cempaka Residence',
            'address' => 'Jl. Cempaka No. 20, Kecamatan Coblong',
            'region_name' => 'Jawa Barat',
            'city_name' => 'Kota Bandung',
            'postal_code' => '40131',
            'phone' => '081234567899',
            'description' => 'Kos di kawasan Dago atas dengan pemandangan kota.',
        ],
    ];

    private array $unitTemplates = [
        [
            'prefix' => 'A',
            'count' => 4,
            'floor' => 1,
            'capacity' => 1,
            'rates' => [
                ['billing_interval' => 1, 'billing_unit' => 'month', 'amount' => '1500000', 'currency' => 'IDR'],
                ['billing_interval' => 1, 'billing_unit' => 'year', 'amount' => '15000000', 'currency' => 'IDR'],
                ['billing_interval' => 1, 'billing_unit' => 'month', 'amount' => '450.00', 'currency' => 'USD'],
            ],
        ],
        [
            'prefix' => 'B',
            'count' => 4,
            'floor' => 2,
            'capacity' => 3,
            'rates' => [
                ['billing_interval' => 1, 'billing_unit' => 'month', 'amount' => '1800000', 'currency' => 'IDR'],
                ['billing_interval' => 3, 'billing_unit' => 'month', 'amount' => '5000000', 'currency' => 'IDR'],
            ],
        ],
        [
            'prefix' => 'C',
            'count' => 4,
            'floor' => 3,
            'capacity' => 1,
            'rates' => [
                ['billing_interval' => 1, 'billing_unit' => 'month', 'amount' => '2200000', 'currency' => 'IDR'],
                ['billing_interval' => 1, 'billing_unit' => 'week', 'amount' => '600000', 'currency' => 'IDR'],
            ],
        ],
    ];

    public function run(): void
    {
        $now = now();

        DB::transaction(function () use ($now): void {
            foreach ($this->properties as $data) {
                $region = Region::query()
                    ->where('country_code', 'ID')
                    ->where('name', $data['region_name'])
                    ->firstOrFail();
                $city = City::query()
                    ->where('region_id', $region->id)
                    ->where('name', $data['city_name'])
                    ->firstOrFail();

                $property = $this->upsertProperty($data, $region->id, $city->id);

                foreach ($this->unitTemplates as $template) {
                    for ($index = 1; $index <= $template['count']; $index++) {
                        $unit = $this->upsertUnit($property, $template, $index);

                        foreach ($template['rates'] as $rate) {
                            $this->upsertRate($unit, $rate, $now);
                        }
                    }
                }
            }
        });
    }

    /**
     * @param  array{name: string, address: string, region_name: string, city_name: string, postal_code: string, phone: string, description: string}  $data
     */
    private function upsertProperty(array $data, int $regionId, int $cityId): Property
    {
        $slug = self::NAMESPACE.'-'.str($data['name'])->slug();
        $property = Property::withTrashed()->firstOrNew(['slug' => $slug]);

        if ($property->trashed()) {
            $property->restoreQuietly();
        }

        $property->forceFill([
            'name' => $data['name'],
            'type' => 'boarding_house',
            'slug' => $slug,
            'address' => $data['address'],
            'region_id' => $regionId,
            'city_id' => $cityId,
            'postal_code' => $data['postal_code'],
            'phone' => $data['phone'],
            'description' => $data['description'],
            'is_active' => true,
        ])->saveQuietly();

        return $property->refresh();
    }

    /**
     * @param  array{prefix: string, count: int, floor: int, capacity: int, rates: array<int, array{billing_interval: int, billing_unit: string, amount: string, currency: string}>}  $template
     */
    private function upsertUnit(Property $property, array $template, int $index): Unit
    {
        $name = $template['prefix'].$index;
        $unit = Unit::withTrashed()->firstOrNew([
            'property_id' => $property->id,
            'name' => $name,
        ]);

        if ($unit->trashed()) {
            $unit->restoreQuietly();
        }

        $status = $unit->leases()->where('status', LeaseStatus::Active->value)->exists()
            ? UnitStatus::Occupied
            : UnitStatus::Available;

        $unit->forceFill([
            'property_id' => $property->id,
            'name' => $name,
            'slug' => self::NAMESPACE.'-'.str($property->slug.'-'.$name)->slug(),
            'floor' => (string) $template['floor'],
            'size_sqm' => 16 + ($index * 2),
            'capacity' => $template['capacity'],
            'status' => $status,
            'notes' => 'OpenKOS demonstration unit.',
        ])->saveQuietly();

        return $unit->refresh();
    }

    /**
     * @param  array{billing_interval: int, billing_unit: string, amount: string, currency: string}  $rateData
     */
    private function upsertRate(Unit $unit, array $rateData, CarbonInterface $now): UnitRate
    {
        $rate = UnitRate::query()->firstOrNew([
            'unit_id' => $unit->id,
            'billing_interval' => $rateData['billing_interval'],
            'billing_unit' => $rateData['billing_unit'],
            'currency' => $rateData['currency'],
        ]);

        $rate->forceFill([
            'unit_id' => $unit->id,
            'billing_interval' => $rateData['billing_interval'],
            'billing_unit' => $rateData['billing_unit'],
            'amount' => $rateData['amount'],
            'currency' => $rateData['currency'],
            'is_active' => true,
            'effective_from' => $now->copy()->startOfMonth()->toDateString(),
            'effective_until' => null,
        ])->saveQuietly();

        return $rate->refresh();
    }
}
