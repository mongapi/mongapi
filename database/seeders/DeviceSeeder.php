<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DeviceSeeder extends Seeder
{
    public function run(): void
    {
        $timestamp = now();

        foreach ([
            ['device_name' => 'Mesa 1', 'device_type' => 'mesa', 'is_active' => true],
            ['device_name' => 'Mesa 2', 'device_type' => 'mesa', 'is_active' => true],
            ['device_name' => 'Mesa 3', 'device_type' => 'mesa', 'is_active' => true],
            ['device_name' => 'Mesa 4', 'device_type' => 'mesa', 'is_active' => true],
            ['device_name' => 'Proyector 1', 'device_type' => 'projector', 'is_active' => true],
            ['device_name' => 'Proyector 2', 'device_type' => 'projector', 'is_active' => true],
        ] as $device) {
            DB::table('devices')->updateOrInsert(
                ['device_name' => $device['device_name']],
                [...$device, 'updated_at' => $timestamp, 'created_at' => $timestamp],
            );
        }
    }
}
