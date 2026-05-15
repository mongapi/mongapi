<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameSession;
use App\Models\LessonPlan;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $recentSessions = GameSession::with(['lessonPlan', 'game.gameType', 'user'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function (GameSession $session) {
                return [
                    'id' => $session->id,
                    'pin' => $session->pin,
                    'status' => $session->status,
                    'created_at' => $session->created_at,
                    'teacher' => $session->user ? [
                        'id' => $session->user->id,
                        'name' => $session->user->name,
                        'email' => $session->user->email,
                    ] : null,
                    'lesson_plan' => $session->lessonPlan ? [
                        'id' => $session->lessonPlan->id,
                        'name' => $session->lessonPlan->name,
                    ] : null,
                    'game' => $session->game ? [
                        'id' => $session->game->id,
                        'name' => $session->game->name,
                        'game_type' => $session->game->gameType ? [
                            'id' => $session->game->gameType->id,
                            'name' => $session->game->gameType->name,
                            'code' => $session->game->gameType->code,
                        ] : null,
                    ] : null,
                ];
            })
            ->values();

        $recentUsers = User::latest()
            ->take(5)
            ->get(['id', 'name', 'email', 'role', 'created_at'])
            ->values();

        $recentGames = Game::latest()
            ->take(3)
            ->get(['id', 'name', 'created_at'])
            ->map(fn (Game $game) => [
                'type' => 'game',
                'id' => $game->id,
                'name' => $game->name,
                'created_at' => $game->created_at,
            ]);

        $recentLessonPlans = LessonPlan::latest()
            ->take(3)
            ->get(['id', 'name', 'created_at'])
            ->map(fn (LessonPlan $lessonPlan) => [
                'type' => 'lesson_plan',
                'id' => $lessonPlan->id,
                'name' => $lessonPlan->name,
                'created_at' => $lessonPlan->created_at,
            ]);

        $recentResources = $recentGames
            ->concat($recentLessonPlans)
            ->sortByDesc('created_at')
            ->take(6)
            ->values();

        return response()->json([
            'data' => [
                'metrics' => [
                    'users_total' => User::count(),
                    'users_by_role' => [
                        'admin' => User::where('role', 'admin')->count(),
                        'teacher' => User::where('role', 'teacher')->count(),
                        'student' => User::where('role', 'student')->count(),
                    ],
                    'games_total' => Game::count(),
                    'lesson_plans_total' => LessonPlan::count(),
                    'sessions_total' => GameSession::count(),
                ],
                'recent_activity' => [
                    'sessions' => $recentSessions,
                    'users' => $recentUsers,
                    'resources' => $recentResources,
                ],
                'health' => [
                    'api' => [
                        'status' => 'ok',
                        'label' => 'API operativa',
                    ],
                    'websocket' => [
                        'driver' => config('broadcasting.default'),
                        'configured' => filled(config('reverb.apps.apps.0.key')),
                        'host' => config('reverb.servers.reverb.hostname'),
                        'port' => config('reverb.servers.reverb.port'),
                    ],
                    'queue' => [
                        'driver' => config('queue.default'),
                        'in_use' => config('queue.default') !== 'sync',
                    ],
                ],
            ],
        ]);
    }
}
