<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder  
{
    public function run(): void
    {
        $timestamp = now();

        foreach ([
            [
                'name' => 'Daniela Mateo',
                'email' => 'profe@mongame.com',
                'password' => Hash::make('password123'),
                'role' => 'teacher',
            ],
            [
                'name' => 'Admin Usuario',
                'email' => 'admin@mongame.com',
                'password' => Hash::make('password123'),
                'role' => 'admin',
            ],
        ] as $user) {
            DB::table('users')->updateOrInsert(
                ['email' => $user['email']],
                [...$user, 'updated_at' => $timestamp, 'created_at' => $timestamp],
            );
        }
    }
}