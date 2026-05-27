<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = [
            ['name' => 'Budi Santoso', 'phone' => '081234567890', 'id_card_number' => '3273010203040001'],
            ['name' => 'Siti Nurhaliza', 'phone' => '081234567891', 'id_card_number' => '3273010203040002'],
            ['name' => 'Ahmad Rizki', 'phone' => '081234567892', 'id_card_number' => '3273010203040003'],
            ['name' => 'Dewi Lestari', 'phone' => '081234567893', 'id_card_number' => '3273010203040004'],
            ['name' => 'Rudi Hartono', 'phone' => '081234567894', 'id_card_number' => '3273010203040005'],
            ['name' => 'Rina Wijaya', 'phone' => '081234567895', 'id_card_number' => '3273010203040006'],
            ['name' => 'Agus Prasetyo', 'phone' => '081234567896', 'id_card_number' => '3273010203040007'],
            ['name' => 'Maya Anggraini', 'phone' => '081234567897', 'id_card_number' => '3273010203040008'],
            ['name' => 'Doni Firmansyah', 'phone' => '081234567898', 'id_card_number' => '3273010203040009'],
            ['name' => 'Fitri Handayani', 'phone' => '081234567899', 'id_card_number' => '3273010203040010'],
            ['name' => 'Hendra Gunawan', 'phone' => '081234567800', 'id_card_number' => '3273010203040011'],
            ['name' => 'Indah Permata', 'phone' => '081234567801', 'id_card_number' => '3273010203040012', 'is_active' => false],
            ['name' => 'Joko Susilo', 'phone' => '081234567802', 'id_card_number' => '3273010203040013'],
            ['name' => 'Kartika Sari', 'phone' => '081234567803', 'id_card_number' => '3273010203040014'],
            ['name' => 'Lukman Hakim', 'phone' => '081234567804', 'id_card_number' => '3273010203040015', 'is_active' => false],
        ];

        foreach ($tenants as $data) {
            Tenant::create($data);
        }
    }
}
