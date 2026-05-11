<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DeviceSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('devices')->insert([
            [
                'device_name' => 'Mesa 1',
                'device_type' => 'mesa',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'device_name' => 'Mesa 2',
                'device_type' => 'mesa',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'device_name' => 'Mesa 3',
                'device_type' => 'mesa',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'device_name' => 'Mesa 4',
                'device_type' => 'mesa',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],


            [
                'device_name' => 'Proyector 1',
                'device_type' => 'projector',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'device_name' => 'Proyector 2',
                'device_type' => 'projector',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],

        ]);
    }
}
