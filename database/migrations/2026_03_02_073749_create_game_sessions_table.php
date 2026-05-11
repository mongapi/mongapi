<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lesson_plan_id')
                ->nullable()
                ->constrained('lesson_plans')
                ->onDelete('set null');

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->foreignId('game_id')
                ->nullable()
                ->constrained('games')
                ->onDelete('set null');

            $table->jsonb('game_content');

            $table->string('pin', 6)->unique()->nullable();
            $table->timestamp('pin_expires_at')->nullable();

            $table->string('game_mode', 30)->default('individual');
            $table->string('status', 20)->default('waiting'); // waiting, playing, finished
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index('lesson_plan_id');
            $table->index('user_id');
            $table->index('game_id');
            $table->index('status');
            $table->index('pin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_sessions');
    }
};