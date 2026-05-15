<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameResult;
use App\Models\GameType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionModesFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_time_and_groups_results_according_to_session_mode(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
        ]);

        $quizType = GameType::create([
            'code' => 'quiz',
            'name' => 'Quiz',
            'description' => 'Quiz test',
            'is_active' => true,
        ]);

        Sanctum::actingAs($teacher);

        $game = Game::create([
            'game_type_id' => $quizType->id,
            'name' => 'Quiz de prueba',
            'description' => 'Juego para validar modos',
            'game_content' => [
                'questions' => [
                    [
                        'id' => 'q1',
                        'text' => 'Pregunta de prueba',
                        'options' => [
                            ['id' => 'a', 'text' => 'Correcta'],
                            ['id' => 'b', 'text' => 'Incorrecta'],
                        ],
                        'correctAnswer' => 'a',
                    ],
                ],
            ],
            'is_active' => true,
        ]);

        $shared = $this->createSession($game->id, 'shared');
        $this->touchPresence($shared, 'shared-a', 'Puesto 1');
        $this->submitAnswer($shared, 'shared-a', 'Puesto 1', 'a', 5, true);
        $this->touchPresence($shared, 'shared-b', 'Puesto 2');
        $this->submitAnswer($shared, 'shared-b', 'Puesto 2', 'b', 8, true);

        $sharedState = $this->getJson("/api/sessions/{$shared['id']}")->assertOk()->json('data');
        $this->assertSame('shared', $sharedState['game_mode']);
        $this->assertCount(2, $sharedState['results_summary']);
        $this->assertSame(['Puesto 1', 'Puesto 2'], array_column($sharedState['results_summary'], 'label'));
        $this->assertSame([5, 8], array_column($sharedState['results_summary'], 'time_seconds'));

        $table = $this->createSession($game->id, 'table');
        $this->touchPresence($table, 'table-a', 'Mesa 3');
        $this->submitAnswer($table, 'table-a', 'Mesa 3', 'a', 6, false);
        $this->touchPresence($table, 'table-b', 'Mesa 3');
        $this->submitAnswer($table, 'table-b', 'Mesa 3', 'b', 9, true);

        $tableState = $this->getJson("/api/sessions/{$table['id']}")->assertOk()->json('data');
        $this->assertSame('table', $tableState['game_mode']);
        $this->assertCount(1, $tableState['results_summary']);
        $this->assertSame('Mesa 3', $tableState['results_summary'][0]['label']);
        $this->assertSame(100, $tableState['results_summary'][0]['score']);
        $this->assertSame(1, $tableState['results_summary'][0]['correct_answers']);
        $this->assertSame(1, $tableState['results_summary'][0]['incorrect_answers']);
        $this->assertSame(9, $tableState['results_summary'][0]['time_seconds']);
        $this->assertTrue($tableState['results_summary'][0]['completed']);

        $individual = $this->createSession($game->id, 'individual');
        $this->touchPresence($individual, 'ind-a', 'Lucía');
        $this->submitAnswer($individual, 'ind-a', 'Lucía', 'a', 4, true);
        $this->touchPresence($individual, 'ind-b', 'Mario');
        $this->submitAnswer($individual, 'ind-b', 'Mario', 'b', 7, true);

        $individualState = $this->getJson("/api/sessions/{$individual['id']}")->assertOk()->json('data');
        $this->assertSame('individual', $individualState['game_mode']);
        $this->assertCount(2, $individualState['results_summary']);
        $this->assertSame(['Lucía', 'Mario'], array_column($individualState['results_summary'], 'label'));
        $this->assertSame([100, 0], array_column($individualState['results_summary'], 'score'));

        $this->assertDatabaseHas('game_results', [
            'game_session_id' => $table['id'],
            'participant_key' => 'table:mesa-3',
            'score' => 100,
            'correct_answers' => 1,
            'incorrect_answers' => 1,
            'time_seconds' => 9,
        ]);

        $this->assertSame(5, GameResult::query()->count());
        $this->assertNotEmpty($tableState['active_participants'][0]['participant_key'] ?? null);
    }

    public function test_it_accepts_memory_filling_blanks_and_guess_who_answer_formats(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
        ]);

        Sanctum::actingAs($teacher);

        $memoryType = GameType::create([
            'code' => 'memory',
            'name' => 'Memory',
            'description' => 'Memory test',
            'is_active' => true,
        ]);

        $fillType = GameType::create([
            'code' => 'filling_blanks',
            'name' => 'Completar',
            'description' => 'Fill test',
            'is_active' => true,
        ]);

        $guessType = GameType::create([
            'code' => 'guess_who',
            'name' => 'Adivina',
            'description' => 'Guess test',
            'is_active' => true,
        ]);

        $memoryGame = Game::create([
            'game_type_id' => $memoryType->id,
            'name' => 'Memory de prueba',
            'description' => 'Juego memory',
            'game_content' => [
                'pairs' => [
                    ['id' => 'm1a', 'pairId' => 'A', 'text' => 'CPU'],
                    ['id' => 'm1b', 'pairId' => 'A', 'text' => 'Procesador'],
                    ['id' => 'm2a', 'pairId' => 'B', 'text' => 'RAM'],
                    ['id' => 'm2b', 'pairId' => 'B', 'text' => 'Memoria temporal'],
                ],
            ],
            'is_active' => true,
        ]);

        $fillGame = Game::create([
            'game_type_id' => $fillType->id,
            'name' => 'Completar de prueba',
            'description' => 'Juego completar',
            'game_content' => [
                'text' => 'TCP y UDP trabajan en la capa de transporte.',
                'hiddenWords' => ['TCP', 'UDP'],
                'options' => ['TCP', 'UDP', 'HTTP'],
            ],
            'is_active' => true,
        ]);

        $guessGame = Game::create([
            'game_type_id' => $guessType->id,
            'name' => 'Adivina de prueba',
            'description' => 'Juego adivina',
            'game_content' => [
                'answer' => 'Energía solar',
                'clues' => ['Pista 1'],
            ],
            'is_active' => true,
        ]);

        $memorySession = $this->createSession($memoryGame->id, 'individual');
        $this->submitGenericAnswer($memorySession, 'mem-a', 'Lucía', 'memory-complete', ['A', 'B'], 12, true);
        $memoryState = $this->getJson("/api/sessions/{$memorySession['id']}")->assertOk()->json('data');
        $this->assertCount(1, $memoryState['results_summary']);
        $this->assertSame(100, $memoryState['results_summary'][0]['score']);

        $fillSession = $this->createSession($fillGame->id, 'individual');
        $this->submitGenericAnswer($fillSession, 'fill-a', 'Mario', 'fill-blanks', ['TCP', 'UDP'], 15, true);
        $fillState = $this->getJson("/api/sessions/{$fillSession['id']}")->assertOk()->json('data');
        $this->assertCount(1, $fillState['results_summary']);
        $this->assertSame(100, $fillState['results_summary'][0]['score']);

        $guessSession = $this->createSession($guessGame->id, 'individual');
        $this->submitGenericAnswer($guessSession, 'guess-a', 'Laura', 'guess-who', 'energía solar', 7, true);
        $guessState = $this->getJson("/api/sessions/{$guessSession['id']}")->assertOk()->json('data');
        $this->assertCount(1, $guessState['results_summary']);
        $this->assertSame(100, $guessState['results_summary'][0]['score']);

        $this->assertSame(3, GameResult::query()->count());
    }

    public function test_teacher_can_access_persisted_results_endpoint_for_owned_session(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
        ]);

        $otherTeacher = User::factory()->create([
            'role' => 'teacher',
        ]);

        $quizType = GameType::create([
            'code' => 'quiz',
            'name' => 'Quiz',
            'description' => 'Quiz test',
            'is_active' => true,
        ]);

        Sanctum::actingAs($teacher);

        $game = Game::create([
            'game_type_id' => $quizType->id,
            'name' => 'Quiz resultados',
            'description' => 'Juego para endpoint de resultados',
            'game_content' => [
                'questions' => [[
                    'id' => 'q1',
                    'text' => 'Pregunta',
                    'options' => [
                        ['id' => 'a', 'text' => 'A'],
                        ['id' => 'b', 'text' => 'B'],
                    ],
                    'correctAnswer' => 'a',
                ]],
            ],
            'is_active' => true,
        ]);

        $session = $this->createSession($game->id, 'individual');
        $this->submitAnswer($session, 'ind-a', 'Lucía', 'a', 4, true);

        $resultsResponse = $this->getJson("/api/sessions/{$session['id']}/results")
            ->assertOk()
            ->json('data');

        $this->assertSame($session['id'], $resultsResponse['session_id']);
        $this->assertSame('individual', $resultsResponse['game_mode']);
        $this->assertSame(1, $resultsResponse['stats']['participants_with_results']);
        $this->assertSame('Lucía', $resultsResponse['best_result']['label']);

        Sanctum::actingAs($otherTeacher);
        $this->getJson("/api/sessions/{$session['id']}/results")->assertForbidden();
    }

    public function test_teacher_can_export_persisted_results_as_csv(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
        ]);

        $otherTeacher = User::factory()->create([
            'role' => 'teacher',
        ]);

        $quizType = GameType::create([
            'code' => 'quiz',
            'name' => 'Quiz',
            'description' => 'Quiz test',
            'is_active' => true,
        ]);

        Sanctum::actingAs($teacher);

        $game = Game::create([
            'game_type_id' => $quizType->id,
            'name' => 'Quiz exportable',
            'description' => 'Juego para exportar resultados',
            'game_content' => [
                'questions' => [[
                    'id' => 'q1',
                    'text' => 'Pregunta',
                    'options' => [
                        ['id' => 'a', 'text' => 'A'],
                        ['id' => 'b', 'text' => 'B'],
                    ],
                    'correctAnswer' => 'a',
                ]],
            ],
            'is_active' => true,
        ]);

        $session = $this->createSession($game->id, 'individual');
        $this->submitAnswer($session, 'ind-a', 'Lucía', 'a', 4, true);

        $response = $this->get("/api/sessions/{$session['id']}/results/export");
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertSee('session_id', false);
        $response->assertSee('participant_key,label,score,correct_answers,incorrect_answers,answers_count,time_seconds,completed,completed_at', false);
        $response->assertSee('Lucía', false);

        Sanctum::actingAs($otherTeacher);
        $this->get("/api/sessions/{$session['id']}/results/export")->assertForbidden();
    }

    private function createSession(int $gameId, string $mode): array
    {
        return $this->postJson('/api/sessions', [
            'game_id' => $gameId,
            'game_mode' => $mode,
        ])->assertCreated()->json('data');
    }

    private function touchPresence(array $session, string $deviceId, string $playerName): void
    {
        $this->postJson("/api/sessions/{$session['id']}/presence", [
            'pin' => $session['pin'],
            'device_id' => $deviceId,
            'player_name' => $playerName,
        ])->assertOk();
    }

    private function submitAnswer(array $session, string $deviceId, string $playerName, string $answer, int $elapsedSeconds, bool $completed): void
    {
        $this->submitGenericAnswer($session, $deviceId, $playerName, 'q1', $answer, $elapsedSeconds, $completed);
    }

    private function submitGenericAnswer(array $session, string $deviceId, string $playerName, string $questionId, mixed $answer, int $elapsedSeconds, bool $completed): void
    {
        $this->postJson("/api/sessions/{$session['id']}/answers", [
            'question_id' => $questionId,
            'answer' => $answer,
            'device_id' => $deviceId,
            'player_name' => $playerName,
            'player_number' => 1,
            'elapsed_seconds' => $elapsedSeconds,
            'completed' => $completed,
        ])->assertOk();
    }
}
