<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GameTypeSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('game_types')->insert([
            [
                'code' => 'memory',
                'name' => 'Juego de Memoria',
                'description' => 'Encuentra las parejas de cartas iguales.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'quiz',
                'name' => 'Juego de Preguntas',
                'description' => 'Responde preguntas para ganar puntos.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'timeline',
                'name' => 'Juego de Línea de Tiempo',
                'description' => 'Organiza los eventos en el orden correcto.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'hangman',
                'name' => 'Ahorcado',
                'description' => 'Adivina la palabra antes de que se complete el dibujo del ahorcado.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'filling_blanks',
                'name' => 'Juego de Rellenar Espacios',
                'description' => 'Rellena los espacios en blanco con la palabra correcta.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'guess_who',
                'name' => 'Quién es quién',
                'description' => 'Adivina la identidad del personaje secreto.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'code' => 'shooting',
                'name' => 'Juego de Disparos',
                'description' => 'Dispara a los objetivos para ganar puntos.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);
    }
}
