<?php

namespace App\Http\Controllers\API;

use App\Events\GameSessionStarted;
use App\Events\GameStateUpdated;
use App\Events\PlayerAnswered;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameSession;
use App\Models\LessonPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sessions = GameSession::with(['lessonPlan', 'game.gameType'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'data' => $sessions->map(fn (GameSession $session) => $this->serializeSession($session))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lesson_plan_id' => 'nullable|exists:lesson_plans,id',
            'game_id'      => 'nullable|exists:games,id',
            'game_content' => 'nullable|array',
            'current_phase_index' => 'nullable|integer|min:0',
        ]);

        if (empty($validated['lesson_plan_id']) && empty($validated['game_id'])) {
            throw ValidationException::withMessages([
                'game_id' => ['Debes enviar un game_id o un lesson_plan_id para crear la sesión.'],
            ]);
        }

        [$lessonPlan, $activeGame, $currentPhaseIndex] = $this->resolveCreationContext($validated);

        $session = GameSession::create([
            'lesson_plan_id' => $lessonPlan?->id,
            'game_id'      => $activeGame?->id,
            'current_phase_index' => $currentPhaseIndex,
            'status'       => 'waiting',
            'game_content' => $validated['game_content'] ?? ($activeGame?->game_content ?? []),
        ]);

        return response()->json(['data' => $this->serializeSession($session)], 201);
    }

    public function show(int $id): JsonResponse
    {
        $session = GameSession::with(['lessonPlan', 'game.gameType'])->findOrFail($id);
        return response()->json(['data' => $this->serializeSession($session)]);
    }

    public function joinByPin(string $pin): JsonResponse
    {
        $session = GameSession::with(['lessonPlan', 'game.gameType'])
            ->where('pin', $pin)
            ->whereIn('status', ['waiting', 'playing', 'paused'])
            ->first();

        if (!$session) {
            throw ValidationException::withMessages([
                'pin' => ['No existe ninguna sesión activa con ese PIN.'],
            ]);
        }

        if ($session->pin_expires_at && now()->greaterThan($session->pin_expires_at)) {
            throw ValidationException::withMessages([
                'pin' => ['El PIN de la sesión ha expirado.'],
            ]);
        }

        return response()->json([
            'data' => $this->serializeSession($session),
            'meta' => [
                'game_type_code' => $session->game?->gameType?->code,
            ],
        ]);
    }

    public function submitAnswer(Request $request, int $id): JsonResponse
    {
        $session = GameSession::findOrFail($id);

        if (!in_array($session->status, ['waiting', 'playing', 'paused'], true)) {
            throw ValidationException::withMessages([
                'session' => ['La sesión ya no acepta respuestas.'],
            ]);
        }

        $validated = $request->validate([
            'question_id' => 'required',
            'answer' => 'required',
            'device_id' => 'nullable|string|max:100',
            'player_name' => 'nullable|string|max:100',
            'player_number' => 'nullable|integer|min:1',
        ]);

        $questions = collect($session->game_content['questions'] ?? []);
        $question = $questions->firstWhere('id', $validated['question_id']);

        if (!$question) {
            throw ValidationException::withMessages([
                'question_id' => ['La pregunta no existe dentro del contenido de la sesión.'],
            ]);
        }

        $correctAnswer = $question['correctAnswer'] ?? $question['correct'] ?? null;
        $isCorrect = $validated['answer'] == $correctAnswer;
        $score = $isCorrect ? 100 : 0;
        $deviceId = $validated['device_id'] ?? ('web-' . ($validated['player_number'] ?? 'guest'));

        broadcast(new PlayerAnswered(
            sessionId: $session->id,
            deviceId: $deviceId,
            questionId: (int) preg_replace('/\D+/', '', (string) $validated['question_id']) ?: 0,
            answer: $validated['answer'],
            isCorrect: $isCorrect,
            score: $score,
        ))->toOthers();

        return response()->json([
            'data' => [
                'session_id' => $session->id,
                'question_id' => $validated['question_id'],
                'answer' => $validated['answer'],
                'is_correct' => $isCorrect,
                'score' => $score,
                'device_id' => $deviceId,
                'player_name' => $validated['player_name'] ?? null,
            ],
        ]);
    }

    public function nextPhase(int $id): JsonResponse
    {
        $session = GameSession::with('lessonPlan')->findOrFail($id);
        $lessonPlan = $session->lessonPlan;

        if (!$lessonPlan) {
            throw ValidationException::withMessages([
                'lesson_plan_id' => ['Esta sesión no tiene lesson plan asociado.'],
            ]);
        }

        $gameIds = array_values($lessonPlan->game_ids ?? []);
        $nextPhaseIndex = ($session->current_phase_index ?? 0) + 1;
        $nextGameId = $gameIds[$nextPhaseIndex] ?? null;

        if (!$nextGameId) {
            throw ValidationException::withMessages([
                'current_phase_index' => ['No quedan más fases en esta sesión.'],
            ]);
        }

        $nextGame = Game::with('gameType')->findOrFail($nextGameId);

        $session->update([
            'current_phase_index' => $nextPhaseIndex,
            'game_id' => $nextGame->id,
            'game_content' => $nextGame->game_content ?? [],
        ]);

        $session->load(['lessonPlan', 'game.gameType']);

        broadcast(new GameStateUpdated($session, 'phase_changed'))->toOthers();

        return response()->json([
            'data' => $this->serializeSession($session),
            'message' => 'Fase avanzada',
        ]);
    }

    public function start(int $id): JsonResponse
    {
        $session = GameSession::with(['lessonPlan', 'game.gameType'])->findOrFail($id);
        $session->update(['status' => 'playing', 'started_at' => now()]);
        broadcast(new GameSessionStarted($session))->toOthers();
        return response()->json(['data' => $this->serializeSession($session), 'message' => 'Sesión iniciada']);
    }

    public function pause(int $id): JsonResponse
    {
        $session = GameSession::with(['lessonPlan', 'game.gameType'])->findOrFail($id);
        $session->update(['status' => 'paused']);
        broadcast(new GameStateUpdated($session, 'paused'))->toOthers();
        return response()->json(['data' => $this->serializeSession($session), 'message' => 'Sesión pausada']);
    }

    public function resume(int $id): JsonResponse
    {
        $session = GameSession::with(['lessonPlan', 'game.gameType'])->findOrFail($id);
        $session->update(['status' => 'playing']);
        broadcast(new GameStateUpdated($session, 'playing'))->toOthers();
        return response()->json(['data' => $this->serializeSession($session), 'message' => 'Sesión reanudada']);
    }

    public function finish(int $id): JsonResponse
    {
        $session = GameSession::with(['lessonPlan', 'game.gameType'])->findOrFail($id);
        $session->update(['status' => 'finished', 'ended_at' => now()]);
        broadcast(new GameStateUpdated($session, 'finished'))->toOthers();
        return response()->json(['data' => $this->serializeSession($session), 'message' => 'Sesión finalizada']);
    }

    private function resolveCreationContext(array $validated): array
    {
        $lessonPlan = null;
        $activeGame = null;
        $currentPhaseIndex = (int) ($validated['current_phase_index'] ?? 0);

        if (!empty($validated['lesson_plan_id'])) {
            $lessonPlan = LessonPlan::findOrFail($validated['lesson_plan_id']);
            $gameIds = array_values($lessonPlan->game_ids ?? []);
            $activeGameId = $gameIds[$currentPhaseIndex] ?? null;

            if (!$activeGameId) {
                throw ValidationException::withMessages([
                    'lesson_plan_id' => ['El lesson plan no tiene una fase válida para el índice indicado.'],
                ]);
            }

            $activeGame = Game::findOrFail($activeGameId);
            return [$lessonPlan, $activeGame, $currentPhaseIndex];
        }

        $activeGame = Game::findOrFail($validated['game_id']);
        return [null, $activeGame, 0];
    }

    private function serializeSession(GameSession $session): array
    {
        $session->loadMissing(['lessonPlan', 'game.gameType']);

        $lessonGameIds = array_values($session->lessonPlan?->game_ids ?? []);
        $totalPhases = count($lessonGameIds) ?: ($session->game_id ? 1 : 0);

        return [
            'id' => $session->id,
            'lesson_plan_id' => $session->lesson_plan_id,
            'game_id' => $session->game_id,
            'pin' => $session->pin,
            'status' => $session->status,
            'game_mode' => $session->game_mode,
            'game_content' => $session->game_content,
            'current_phase_index' => $session->current_phase_index ?? 0,
            'total_phases' => $totalPhases,
            'started_at' => $session->started_at,
            'ended_at' => $session->ended_at,
            'created_at' => $session->created_at,
            'updated_at' => $session->updated_at,
            'lesson_plan' => $session->lessonPlan ? [
                'id' => $session->lessonPlan->id,
                'name' => $session->lessonPlan->name,
                'description' => $session->lessonPlan->description,
                'game_ids' => $lessonGameIds,
                'created_at' => $session->lessonPlan->created_at,
                'updated_at' => $session->lessonPlan->updated_at,
            ] : null,
            'game' => $session->game ? [
                'id' => $session->game->id,
                'name' => $session->game->name,
                'description' => $session->game->description,
                'game_type_id' => $session->game->game_type_id,
                'created_at' => $session->game->created_at,
                'updated_at' => $session->game->updated_at,
                'game_type' => $session->game->gameType ? [
                    'id' => $session->game->gameType->id,
                    'code' => $session->game->gameType->code,
                    'name' => $session->game->gameType->name,
                ] : null,
            ] : null,
        ];
    }
}
