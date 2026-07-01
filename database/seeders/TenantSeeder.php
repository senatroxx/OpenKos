<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = [
            ['name' => 'Budi Santoso', 'phone' => '+6281234567890', 'id_card_number' => '3273010203040001'],
            ['name' => 'Siti Nurhaliza', 'phone' => '+6281234567891', 'id_card_number' => '3273010203040002'],
            ['name' => 'Ahmad Rizki', 'phone' => '+6281234567892', 'id_card_number' => '3273010203040003'],
            ['name' => 'Dewi Lestari', 'phone' => '+6281234567893', 'id_card_number' => '3273010203040004'],
            ['name' => 'Rudi Hartono', 'phone' => '+6281234567894', 'id_card_number' => '3273010203040005'],
            ['name' => 'Rina Wijaya', 'phone' => '+6281234567895', 'id_card_number' => '3273010203040006'],
            ['name' => 'Agus Prasetyo', 'phone' => '+6281234567896', 'id_card_number' => '3273010203040007'],
            ['name' => 'Maya Anggraini', 'phone' => '+6281234567897', 'id_card_number' => '3273010203040008'],
            ['name' => 'Doni Firmansyah', 'phone' => '+6281234567898', 'id_card_number' => '3273010203040009'],
            ['name' => 'Fitri Handayani', 'phone' => '+6281234567899', 'id_card_number' => '3273010203040010'],
            ['name' => 'Hendra Gunawan', 'phone' => '+6281234567800', 'id_card_number' => '3273010203040011'],
            ['name' => 'Indah Permata', 'phone' => '+6281234567801', 'id_card_number' => '3273010203040012'],
            ['name' => 'Joko Susilo', 'phone' => '+6281234567802', 'id_card_number' => '3273010203040013'],
            ['name' => 'Kartika Sari', 'phone' => '+6281234567803', 'id_card_number' => '3273010203040014'],
            ['name' => 'Lukman Hakim', 'phone' => '+6281234567804', 'id_card_number' => '3273010203040015'],
            ['name' => 'Eko Wahyudi', 'phone' => '+6281234567805', 'id_card_number' => '3273010203040016'],
            ['name' => 'Dian Permata', 'phone' => '+6281234567806', 'id_card_number' => '3273010203040017'],
            ['name' => 'Fajar Nugroho', 'phone' => '+6281234567807', 'id_card_number' => '3273010203040018'],
            ['name' => 'Ratna Sari', 'phone' => '+6281234567808', 'id_card_number' => '3273010203040019'],
            ['name' => 'Bayu Aji', 'phone' => '+6281234567809', 'id_card_number' => '3273010203040020'],
        ];

        foreach ($tenants as $data) {
            Tenant::firstOrCreate(
                ['id_card_number' => $data['id_card_number']],
                $data,
            );
        }

        DB::statement("UPDATE tenants SET is_active = false WHERE id_card_number = '3273010203040012'");
        DB::statement("UPDATE tenants SET is_active = false WHERE id_card_number = '3273010203040015'");
    }
}
