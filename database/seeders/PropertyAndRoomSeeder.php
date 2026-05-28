<?php

namespace Database\Seeders;

use App\Enums\RoomStatus;
use App\Models\City;
use App\Models\Property;
use App\Models\Region;
use App\Models\Room;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PropertyAndRoomSeeder extends Seeder
{
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

    private array $roomTemplates = [
        ['prefix' => 'A', 'count' => 4, 'base_price' => 1_500_000, 'floor' => 1],
        ['prefix' => 'B', 'count' => 4, 'base_price' => 1_800_000, 'floor' => 2],
        ['prefix' => 'C', 'count' => 4, 'base_price' => 2_200_000, 'floor' => 3],
    ];

    public function run(): void
    {
        DB::statement('TRUNCATE TABLE properties CASCADE');

        $regions = Region::pluck('id', 'name');
        $cities = City::all()->groupBy('region_id');

        foreach ($this->properties as $data) {
            $regionId = $regions->get($data['region_name']);
            if ($regionId === null) {
                continue;
            }

            $city = $cities[$regionId]->firstWhere('name', $data['city_name']);
            if ($city === null) {
                $city = $cities[$regionId]->first();
            }

            $property = Property::create([
                'name' => $data['name'],
                'address' => $data['address'],
                'region_id' => $regionId,
                'city_id' => $city->id,
                'postal_code' => $data['postal_code'],
                'phone' => $data['phone'],
                'description' => $data['description'],
            ]);

            DB::statement('UPDATE properties SET is_active = true WHERE id = ?', [$property->id]);

            foreach ($this->roomTemplates as $template) {
                for ($i = 1; $i <= $template['count']; $i++) {
                    Room::create([
                        'property_id' => $property->id,
                        'name' => $template['prefix'].$i,
                        'floor' => (string) $template['floor'],
                        'base_price' => $template['base_price'],
                        'size_sqm' => fake()->randomFloat(2, 14, 28),
                        'capacity' => fake()->randomElement([1, 2]),
                        'status' => fake()->randomElement([
                            RoomStatus::Available,
                            RoomStatus::Available,
                            RoomStatus::Available,
                            RoomStatus::Occupied,
                            RoomStatus::Occupied,
                            RoomStatus::Maintenance,
                        ]),
                        'notes' => null,
                    ]);
                }
            }
        }
    }
}
