<?php

namespace Database\Seeders;

use App\Models\GameType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GameSeeder extends Seeder
{
    public function run(): void
    {
        $teacherId = User::query()->where('email', 'profe@mongame.com')->value('id');

        if (!$teacherId) {
            $this->command?->warn('GameSeeder omitido: no existe el usuario profe@mongame.com');
            return;
        }

        $gameTypeIds = GameType::query()->pluck('id', 'code');
        $timestamp = now();

        foreach ($this->games($teacherId, $gameTypeIds->all(), $timestamp) as $game) {
            DB::table('games')->updateOrInsert(
                [
                    'user_id' => $game['user_id'],
                    'game_type_id' => $game['game_type_id'],
                    'name' => $game['name'],
                ],
                $game,
            );
        }
    }

    private function games(int $teacherId, array $gameTypeIds, $timestamp): array
    {
        return array_values(array_filter([
            $this->buildGame($teacherId, $gameTypeIds, 'quiz', 'Quiz Redes Básicas', 'Cuestionario introductorio sobre redes y conectividad.', [
                'questions' => [
                    [
                        'id' => 'q1',
                        'text' => '¿Qué dispositivo se utiliza para enrutar paquetes entre redes distintas?',
                        'timeLimit' => 20,
                        'options' => [
                            ['id' => 'a', 'text' => 'Router'],
                            ['id' => 'b', 'text' => 'Teclado'],
                            ['id' => 'c', 'text' => 'Monitor'],
                            ['id' => 'd', 'text' => 'Micrófono'],
                        ],
                        'correctAnswer' => 'a',
                    ],
                    [
                        'id' => 'q2',
                        'text' => '¿Qué significa LAN?',
                        'timeLimit' => 20,
                        'options' => [
                            ['id' => 'a', 'text' => 'Large Access Node'],
                            ['id' => 'b', 'text' => 'Local Area Network'],
                            ['id' => 'c', 'text' => 'Logical Analog Network'],
                            ['id' => 'd', 'text' => 'Linked Admin Number'],
                        ],
                        'correctAnswer' => 'b',
                    ],
                ],
            ], $timestamp),
            $this->buildGame($teacherId, $gameTypeIds, 'memory', 'Memory Conceptos de Hardware', 'Empareja componentes con su definición.', [
                'pairs' => [
                    ['id' => 'pair-a-1', 'pairId' => 'A', 'text' => 'CPU'],
                    ['id' => 'pair-a-2', 'pairId' => 'A', 'text' => 'Unidad que procesa instrucciones'],
                    ['id' => 'pair-b-1', 'pairId' => 'B', 'text' => 'RAM'],
                    ['id' => 'pair-b-2', 'pairId' => 'B', 'text' => 'Memoria temporal de acceso rápido'],
                ],
            ], $timestamp),
            $this->buildGame($teacherId, $gameTypeIds, 'filling_blanks', 'Completa el Enunciado TCP/IP', 'Arrastra las palabras correctas al texto técnico.', [
                'text' => 'En el modelo TCP/IP, la capa de transporte utiliza protocolos como TCP y UDP para mover datos entre aplicaciones.',
                'hiddenWords' => ['transporte', 'TCP', 'UDP'],
                'options' => ['enlace', 'transporte', 'IP', 'TCP', 'UDP', 'HTTP'],
                'hint' => 'Busca la capa y los protocolos que actúan entre aplicaciones.',
            ], $timestamp),
            $this->buildGame($teacherId, $gameTypeIds, 'guess_who', 'Adivina Qué: Energía Solar', 'Juego de pistas sobre una fuente de energía renovable.', [
                'answer' => 'Energía solar',
                'clues' => [
                    'Se obtiene a partir de la radiación de una estrella.',
                    'Puede transformarse en electricidad con paneles fotovoltaicos.',
                    'Es una fuente renovable muy usada en cubiertas de edificios.',
                ],
            ], $timestamp),
            $this->buildGame($teacherId, $gameTypeIds, 'shooting', 'Shooter Seguridad Web', 'Derriba la opción correcta en cada pregunta.', [
                'questions' => [
                    [
                        'id' => 's1',
                        'text' => '¿Cuál de estas prácticas ayuda a evitar inyecciones SQL?',
                        'options' => ['Concatenar strings', 'Prepared statements', 'Guardar contraseñas en texto plano'],
                        'correct' => 1,
                    ],
                    [
                        'id' => 's2',
                        'text' => '¿Qué encabezado ayuda a evitar que un sitio se cargue en iframes maliciosos?',
                        'options' => ['X-Frame-Options', 'Accept-Language', 'Cache-Control'],
                        'correct' => 0,
                    ],
                ],
            ], $timestamp),
            $this->buildGame($teacherId, $gameTypeIds, 'timeline', 'Cronología de Internet', 'Ordena hitos importantes de la historia de Internet.', [
                'items' => [
                    [
                        'id' => 't1',
                        'text' => 'Nacimiento de ARPANET',
                        'date' => '1969',
                        'question' => '¿Qué red es considerada antecedente directo de Internet?',
                        'options' => ['ARPANET', 'Bluetooth', 'Intranet'],
                        'correct' => 0,
                    ],
                    [
                        'id' => 't2',
                        'text' => 'Propuesta de la World Wide Web',
                        'date' => '1989',
                        'question' => '¿Quién propuso la Web en el CERN?',
                        'options' => ['Tim Berners-Lee', 'Alan Turing', 'Linus Torvalds'],
                        'correct' => 0,
                    ],
                ],
            ], $timestamp),
            $this->buildGame($teacherId, $gameTypeIds, 'hangman', 'Ahorcado Sistemas Operativos', 'Adivina un concepto básico de sistemas operativos.', [
                'word' => 'kernel',
                'clue' => 'Núcleo de un sistema operativo.',
            ], $timestamp),
        ]));
    }

    private function buildGame(int $teacherId, array $gameTypeIds, string $code, string $name, string $description, array $content, $timestamp): ?array
    {
        $gameTypeId = $gameTypeIds[$code] ?? null;

        if (!$gameTypeId) {
            $this->command?->warn("GameSeeder omitido: no existe game_type con code {$code}");
            return null;
        }

        return [
            'user_id' => $teacherId,
            'game_type_id' => $gameTypeId,
            'name' => $name,
            'description' => $description,
            'game_content' => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_active' => true,
            'times_played' => 0,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }
}