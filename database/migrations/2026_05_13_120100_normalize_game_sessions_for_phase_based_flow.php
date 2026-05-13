<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('game_sessions', 'current_phase_index')) {
                $table->unsignedInteger('current_phase_index')->default(0)->after('game_id');
            }

            if (Schema::hasColumn('game_sessions', 'classroom_id')) {
                try {
                    $table->dropIndex(['classroom_id']);
                } catch (\Throwable $exception) {
                    // The legacy column may exist without a standalone index.
                }

                $table->dropColumn('classroom_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('game_sessions', 'current_phase_index')) {
                $table->dropColumn('current_phase_index');
            }

            if (!Schema::hasColumn('game_sessions', 'classroom_id')) {
                $table->unsignedBigInteger('classroom_id')->nullable()->after('lesson_plan_id');
                $table->index('classroom_id');
            }
        });
    }
};