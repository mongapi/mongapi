<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Game;
use App\Models\LessonPlan;
use App\Models\GameSession;
use App\Observers\GameObserver;
use App\Observers\LessonPlanObserver;
use App\Observers\GameSessionObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registrar observers
        Game::observe(GameObserver::class);
        LessonPlan::observe(LessonPlanObserver::class);
        GameSession::observe(GameSessionObserver::class);
    }
}