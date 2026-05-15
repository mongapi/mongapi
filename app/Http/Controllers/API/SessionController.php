<?php

namespace App\Http\Controllers\API;

use App\Events\GameSessionStarted;
use App\Events\SessionPresenceUpdated;
use App\Events\GameStateUpdated;
use App\Events\PlayerAnswered;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameResult;
use App\Models\GameSession;
use App\Models\LessonPlan;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    private const PRESENCE_TTL_SECONDS = 45;
    private const GAME_MODES = ['shared', 'table', 'individual'];

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
            'game_mode' => 'nullable|string|in:shared,table,individual',
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
            'game_mode' => $validated['game_mode'] ?? 'individual',
            'status'       => 'waiting',
            'game_content' => $validated['game_content'] ?? ($activeGame?->game_content ?? []),
        ]);

        return response()->json(['data' => $this->serializeSession($session)], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $session = GameSession::with(['lessonPlan', 'game.gameType'])->findOrFail($id);
        $this->authorizeSessionView($request, $session);

        return response()->json([
            'data' => $this->serializeSession(
                $session,
                $this->canManageSession($request, $session),
            ),
        ]);
    }

    public function results(Request $request, int $id): JsonResponse
    {
        $session = GameSession::with(['lessonPlan', 'game.gameType', 'user'])->findOrFail($id);
        $this->authorizeSessionResults($request, $session);

        return response()->json([
            'data' => $this->buildResultsPayload($session),
        ]);
    }

    public function exportResults(Request $request, int $id)
    {
        $session = GameSession::with(['lessonPlan', 'game.gameType', 'user'])->findOrFail($id);
        $this->authorizeSessionResults($request, $session);

        $payload = $this->buildResultsPayload($session);
        $filename = sprintf(
            'resultados-sesion-%d-%s.csv',
            $session->id,
            now()->format('Ymd-His')
        );

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response($this->buildResultsCsv($payload), 200, $headers);
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

    public function touchPresence(Request $request, int $id): JsonResponse
    {
        $session = GameSession::with(['lessonPlan', 'game.gameType'])->findOrFail($id);

        if (!in_array($session->status, ['waiting', 'playing', 'paused'], true)) {
            throw ValidationException::withMessages([
                'session' => ['La sesión ya no admite participantes activos.'],
            ]);
        }

        $validated = $request->validate([
            'pin' => 'required|string',
            'device_id' => 'required|string|max:100',
            'player_name' => 'nullable|string|max:100',
        ]);

        if ($session->pin !== $validated['pin']) {
            throw ValidationException::withMessages([
                'pin' => ['El PIN no corresponde a esta sesión.'],
            ]);
        }

        $participants = $this->cleanAndReadParticipants($session->id);
        $participantKey = $this->resolveParticipantKey($session->game_mode, $validated['device_id'], $validated['player_name'] ?? null);
        $participants[$validated['device_id']] = [
            'device_id' => $validated['device_id'],
            'participant_key' => $participantKey,
            'player_name' => $validated['player_name'] ?: 'Alumno web',
            'last_seen_at' => now()->toISOString(),
        ];

        $this->storeParticipants($session->id, $participants);
        broadcast(new SessionPresenceUpdated($session->id, array_values($participants)))->toOthers();

        return response()->json([
            'data' => [
                'participants' => array_values($participants),
                'participants_count' => count($participants),
            ],
        ]);
    }

    public function leavePresence(Request $request, int $id): JsonResponse
    {
        $session = GameSession::findOrFail($id);

        $validated = $request->validate([
            'pin' => 'required|string',
            'device_id' => 'required|string|max:100',
        ]);

        if ($session->pin !== $validated['pin']) {
            throw ValidationException::withMessages([
                'pin' => ['El PIN no corresponde a esta sesión.'],
            ]);
        }

        $participants = $this->cleanAndReadParticipants($session->id);
        unset($participants[$validated['device_id']]);

        $this->storeParticipants($session->id, $participants);
        broadcast(new SessionPresenceUpdated($session->id, array_values($participants)))->toOthers();

        return response()->json([
            'data' => [
                'participants' => array_values($participants),
                'participants_count' => count($participants),
            ],
        ]);
    }

    public function submitAnswer(Request $request, int $id): JsonResponse
    {
        $session = GameSession::with('game.gameType')->findOrFail($id);

        if (!in_array($session->status, ['waiting', 'playing', 'paused'], true)) {
            throw ValidationException::withMessages([
                'session' => ['La sesión ya no acepta respuestas.'],
            ]);
        }

        $validated = $request->validate([
            'pin' => 'required|string',
            'question_id' => 'required',
            'answer' => 'required',
            'device_id' => 'nullable|string|max:100',
            'player_name' => 'nullable|string|max:100',
            'player_number' => 'nullable|integer|min:1',
            'elapsed_seconds' => 'nullable|integer|min:0|max:86400',
            'completed' => 'nullable|boolean',
        ]);

        if ($session->pin !== $validated['pin']) {
            throw ValidationException::withMessages([
                'pin' => ['El PIN no corresponde a esta sesión.'],
            ]);
        }

        $evaluation = $this->evaluateAnswer(
            session: $session,
            questionId: $validated['question_id'],
            answer: $validated['answer'],
        );

        if ($evaluation === null) {
            throw ValidationException::withMessages([
                'question_id' => ['La respuesta no corresponde con el contenido o el tipo de esta sesión.'],
            ]);
        }

        $isCorrect = $evaluation['is_correct'];
        $score = $evaluation['score'];
        $deviceId = $validated['device_id'] ?? ('web-' . ($validated['player_number'] ?? 'guest'));
        $playerName = $validated['player_name'] ?? null;
        $participantKey = $this->resolveParticipantKey($session->game_mode, $deviceId, $playerName);
        $gameResult = $this->upsertGameResult(
            session: $session,
            participantKey: $participantKey,
            deviceId: $deviceId,
            playerName: $playerName,
            playerNumber: $validated['player_number'] ?? null,
            isCorrect: $isCorrect,
            score: $score,
            elapsedSeconds: $validated['elapsed_seconds'] ?? null,
            completed: (bool) ($validated['completed'] ?? false),
        );

        broadcast(new PlayerAnswered(
            sessionId: $session->id,
            deviceId: $deviceId,
            questionId: (int) preg_replace('/\D+/', '', (string) $validated['question_id']) ?: 0,
            answer: $validated['answer'],
            isCorrect: $isCorrect,
            score: $score,
            playerName: $playerName,
            elapsedSeconds: $gameResult->time_seconds,
        ))->toOthers();

        return response()->json([
            'data' => [
                'session_id' => $session->id,
                'question_id' => $validated['question_id'],
                'answer' => $validated['answer'],
                'is_correct' => $isCorrect,
                'score' => $score,
                'device_id' => $deviceId,
                'player_name' => $playerName,
                'elapsed_seconds' => $gameResult->time_seconds,
                'results_summary' => $this->buildResultsSummary($session),
            ],
        ]);
    }

    private function evaluateAnswer(GameSession $session, mixed $questionId, mixed $answer): ?array
    {
        $gameContent = $session->game_content ?? [];
        $question = collect($gameContent['questions'] ?? [])->firstWhere('id', $questionId);
        if ($question) {
            $correctAnswer = $question['correctAnswer'] ?? $question['correct'] ?? null;

            return [
                'is_correct' => $answer == $correctAnswer,
                'score' => $answer == $correctAnswer ? 100 : 0,
            ];
        }

        $item = collect($gameContent['items'] ?? [])->firstWhere('id', $questionId);
        if ($item) {
            $correctAnswer = $item['correct'] ?? null;

            return [
                'is_correct' => $answer == $correctAnswer,
                'score' => $answer == $correctAnswer ? 100 : 0,
            ];
        }

        $typeCode = $session->game?->gameType?->code;

        if ($typeCode === 'memory' && $questionId === 'memory-complete') {
            $matchedPairs = collect(is_array($answer) ? $answer : [])->filter()->unique()->count();
            $totalPairs = collect($gameContent['pairs'] ?? [])->pluck('pairId')->filter()->unique()->count();
            $isCorrect = $matchedPairs > 0 && $matchedPairs === $totalPairs;

            return [
                'is_correct' => $isCorrect,
                'score' => $isCorrect ? 100 : 0,
            ];
        }

        if ($typeCode === 'filling_blanks' && $questionId === 'fill-blanks') {
            $expectedWords = collect($gameContent['hiddenWords'] ?? [$gameContent['hiddenWord'] ?? $gameContent['answer'] ?? null])
                ->map(fn ($word) => mb_strtolower(trim((string) $word)))
                ->filter()
                ->values()
                ->all();

            $submittedWords = collect(is_array($answer) ? $answer : [$answer])
                ->map(fn ($word) => mb_strtolower(trim((string) $word)))
                ->filter()
                ->values()
                ->all();

            $isCorrect = !empty($expectedWords) && $expectedWords === $submittedWords;

            return [
                'is_correct' => $isCorrect,
                'score' => $isCorrect ? 100 : 0,
            ];
        }

        if ($typeCode === 'guess_who' && $questionId === 'guess-who') {
            $expectedAnswer = mb_strtolower(trim((string) ($gameContent['answer'] ?? '')));
            $submittedAnswer = mb_strtolower(trim((string) $answer));
            $isCorrect = $expectedAnswer !== '' && $submittedAnswer === $expectedAnswer;

            return [
                'is_correct' => $isCorrect,
                'score' => $isCorrect ? 100 : 0,
            ];
        }

        return null;
    }

    public function nextPhase(Request $request, int $id): JsonResponse
    {
        $session = GameSession::with('lessonPlan')->findOrFail($id);
        $this->authorizeSessionManagement($request, $session);
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

    public function start(Request $request, int $id): JsonResponse
    {
        $session = GameSession::with(['lessonPlan', 'game.gameType'])->findOrFail($id);
        $this->authorizeSessionManagement($request, $session);
        $session->update(['status' => 'playing', 'started_at' => now()]);
        broadcast(new GameSessionStarted($session))->toOthers();
        return response()->json(['data' => $this->serializeSession($session), 'message' => 'Sesión iniciada']);
    }

    public function pause(Request $request, int $id): JsonResponse
    {
        $session = GameSession::with(['lessonPlan', 'game.gameType'])->findOrFail($id);
        $this->authorizeSessionManagement($request, $session);
        $session->update(['status' => 'paused']);
        broadcast(new GameStateUpdated($session, 'paused'))->toOthers();
        return response()->json(['data' => $this->serializeSession($session), 'message' => 'Sesión pausada']);
    }

    public function resume(Request $request, int $id): JsonResponse
    {
        $session = GameSession::with(['lessonPlan', 'game.gameType'])->findOrFail($id);
        $this->authorizeSessionManagement($request, $session);
        $session->update(['status' => 'playing']);
        broadcast(new GameStateUpdated($session, 'playing'))->toOthers();
        return response()->json(['data' => $this->serializeSession($session), 'message' => 'Sesión reanudada']);
    }

    public function finish(Request $request, int $id): JsonResponse
    {
        $session = GameSession::with(['lessonPlan', 'game.gameType'])->findOrFail($id);
        $this->authorizeSessionManagement($request, $session);
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

    private function serializeSession(GameSession $session, bool $includeSensitive = true): array
    {
        $session->loadMissing(['lessonPlan', 'game.gameType']);

        $lessonGameIds = array_values($session->lessonPlan?->game_ids ?? []);
        $totalPhases = count($lessonGameIds) ?: ($session->game_id ? 1 : 0);
        $activeParticipants = $includeSensitive ? array_values($this->cleanAndReadParticipants($session->id)) : [];
        $resultsSummary = $includeSensitive ? $this->buildResultsSummary($session) : [];

        $payload = [
            'id' => $session->id,
            'lesson_plan_id' => $session->lesson_plan_id,
            'game_id' => $session->game_id,
            'status' => $session->status,
            'game_mode' => $session->game_mode,
            'game_content' => $session->game_content,
            'current_phase_index' => $session->current_phase_index ?? 0,
            'total_phases' => $totalPhases,
            'participants_count' => count($activeParticipants),
            'active_participants' => $activeParticipants,
            'results_summary' => $resultsSummary,
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

        if ($includeSensitive) {
            $payload['pin'] = $session->pin;
        }

        return $payload;
    }

    private function presenceCacheKey(int $sessionId): string
    {
        return "session:{$sessionId}:presence";
    }

    private function cleanAndReadParticipants(int $sessionId): array
    {
        $participants = Cache::get($this->presenceCacheKey($sessionId), []);
        $threshold = now()->subSeconds(self::PRESENCE_TTL_SECONDS);

        $filteredParticipants = collect($participants)
            ->filter(function ($participant) use ($threshold) {
                $lastSeenAt = $participant['last_seen_at'] ?? null;

                return $lastSeenAt && Carbon::parse($lastSeenAt)->greaterThanOrEqualTo($threshold);
            })
            ->mapWithKeys(fn ($participant) => [($participant['device_id'] ?? '') => $participant])
            ->filter(fn ($participant, $deviceId) => filled($deviceId))
            ->all();

        $this->storeParticipants($sessionId, $filteredParticipants);

        return $filteredParticipants;
    }

    private function storeParticipants(int $sessionId, array $participants): void
    {
        Cache::put($this->presenceCacheKey($sessionId), $participants, now()->addSeconds(self::PRESENCE_TTL_SECONDS));
    }

    private function resolveParticipantKey(string $gameMode, string $deviceId, ?string $playerName): string
    {
        if ($gameMode === 'table') {
            $normalizedName = trim((string) $playerName);

            return $normalizedName !== ''
                ? 'table:' . strtolower(preg_replace('/\s+/', '-', $normalizedName))
                : 'table:' . strtolower($deviceId);
        }

        return 'device:' . strtolower($deviceId);
    }

    private function upsertGameResult(
        GameSession $session,
        string $participantKey,
        string $deviceId,
        ?string $playerName,
        ?int $playerNumber,
        bool $isCorrect,
        int $score,
        ?int $elapsedSeconds,
        bool $completed,
    ): GameResult {
        $gameResult = GameResult::firstOrNew([
            'game_session_id' => $session->id,
            'participant_key' => $participantKey,
        ]);

        $gameResult->fill([
            'player_name' => $playerName ?: $gameResult->player_name ?: $deviceId,
            'player_number' => $playerNumber ?? $gameResult->player_number,
            'completed' => $completed || $gameResult->completed,
        ]);

        if (!$gameResult->exists) {
            $gameResult->score = 0;
            $gameResult->correct_answers = 0;
            $gameResult->incorrect_answers = 0;
        }

        $gameResult->score += $score;
        $gameResult->correct_answers += $isCorrect ? 1 : 0;
        $gameResult->incorrect_answers += $isCorrect ? 0 : 1;

        if ($elapsedSeconds !== null) {
            $gameResult->time_seconds = max((int) ($gameResult->time_seconds ?? 0), $elapsedSeconds);
        }

        if ($completed && !$gameResult->completed_at) {
            $gameResult->completed_at = now();
        }

        $gameResult->save();

        return $gameResult->fresh();
    }

    private function buildResultsSummary(GameSession $session): array
    {
        return $session->gameResults()
            ->orderByDesc('score')
            ->orderByDesc('correct_answers')
            ->orderBy('time_seconds')
            ->get()
            ->map(function (GameResult $result) use ($session) {
                return [
                    'participant_key' => $result->participant_key,
                    'label' => $result->player_name ?: ($session->game_mode === 'table' ? 'Mesa sin nombre' : 'Jugador web'),
                    'score' => $result->score,
                    'correct_answers' => $result->correct_answers,
                    'incorrect_answers' => $result->incorrect_answers,
                    'answers_count' => $result->correct_answers + $result->incorrect_answers,
                    'time_seconds' => $result->time_seconds,
                    'completed' => (bool) $result->completed,
                    'completed_at' => $result->completed_at,
                ];
            })
            ->values()
            ->all();
    }

    private function buildResultsPayload(GameSession $session): array
    {
        $resultsSummary = $this->buildResultsSummary($session);
        $completedCount = collect($resultsSummary)->where('completed', true)->count();
        $totalScore = collect($resultsSummary)->sum('score');
        $bestResult = collect($resultsSummary)->sortByDesc('score')->sortBy('time_seconds')->first();

        return [
            'session_id' => $session->id,
            'session_status' => $session->status,
            'game_mode' => $session->game_mode,
            'session_label' => $session->lessonPlan?->name ?? $session->game?->name ?? ('Sesión #' . $session->id),
            'results_summary' => $resultsSummary,
            'stats' => [
                'participants_with_results' => count($resultsSummary),
                'completed_count' => $completedCount,
                'total_score' => $totalScore,
                'average_score' => count($resultsSummary) > 0 ? (int) round($totalScore / count($resultsSummary)) : 0,
            ],
            'best_result' => $bestResult,
            'updated_at' => $session->updated_at,
        ];
    }

    private function authorizeSessionResults(Request $request, GameSession $session): void
    {
        $user = $request->user();

        if (!$user->isAdmin() && (int) $session->user_id !== (int) $user->id) {
            abort(403, 'No puedes consultar los resultados de esta sesión.');
        }
    }

    private function canManageSession(Request $request, GameSession $session): bool
    {
        $user = $request->user();

        return $user && ($user->isAdmin() || (int) $session->user_id === (int) $user->id);
    }

    private function authorizeSessionManagement(Request $request, GameSession $session): void
    {
        if (!$this->canManageSession($request, $session)) {
            abort(Response::HTTP_FORBIDDEN, 'No puedes gestionar una sesión creada por otro profesor.');
        }
    }

    private function authorizeSessionView(Request $request, GameSession $session): void
    {
        if ($this->canManageSession($request, $session)) {
            return;
        }

        $pin = (string) $request->query('pin', '');

        if ($pin !== '' && hash_equals((string) $session->pin, $pin)) {
            return;
        }

        abort(Response::HTTP_FORBIDDEN, 'No puedes consultar esta sesión sin un PIN válido.');
    }

    private function buildResultsCsv(array $payload): string
    {
        $stream = fopen('php://temp', 'r+');

        fputcsv($stream, ['session_id', $payload['session_id']]);
        fputcsv($stream, ['session_label', $payload['session_label']]);
        fputcsv($stream, ['session_status', $payload['session_status']]);
        fputcsv($stream, ['game_mode', $payload['game_mode']]);
        fputcsv($stream, ['participants_with_results', $payload['stats']['participants_with_results']]);
        fputcsv($stream, ['completed_count', $payload['stats']['completed_count']]);
        fputcsv($stream, ['total_score', $payload['stats']['total_score']]);
        fputcsv($stream, ['average_score', $payload['stats']['average_score']]);
        fputcsv($stream, []);
        fputcsv($stream, ['participant_key', 'label', 'score', 'correct_answers', 'incorrect_answers', 'answers_count', 'time_seconds', 'completed', 'completed_at']);

        foreach ($payload['results_summary'] as $row) {
            fputcsv($stream, [
                $row['participant_key'],
                $row['label'],
                $row['score'],
                $row['correct_answers'],
                $row['incorrect_answers'],
                $row['answers_count'],
                $row['time_seconds'],
                $row['completed'] ? 'true' : 'false',
                $row['completed_at'],
            ]);
        }

        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return "\xEF\xBB\xBF" . $csv;
    }
}
