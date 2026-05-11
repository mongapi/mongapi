<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder  
{
    public function run(): void
    {
        DB::table('users')->insert([  
            [
                'name' => 'Daniela Mateo',
                'email' => 'profe@mongame.com',
                'password' => Hash::make('password123'),
                'role' => 'teacher', 
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Admin Usuario',
                'email' => 'admin@mongame.com',
                'password' => Hash::make('password123'),
                'role' => 'admin',  
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}