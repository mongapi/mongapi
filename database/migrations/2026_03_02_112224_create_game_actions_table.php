<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('game_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_result_id')
                  ->constrained('game_results')
                  ->onDelete('cascade');
            $table->string('action_type', 50);
            $table->jsonb('action_data')->nullable();
            $table->timestamp('happened_at')->useCurrent();
            $table->timestamps();

            $table->index('game_result_id');
            $table->index('action_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_actions');
    }
};
