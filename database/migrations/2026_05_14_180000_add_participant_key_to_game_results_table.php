<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_results', function (Blueprint $table) {
            $table->string('participant_key', 140)->nullable()->after('game_session_id');
            $table->index(['game_session_id', 'participant_key']);
        });
    }

    public function down(): void
    {
        Schema::table('game_results', function (Blueprint $table) {
            $table->dropIndex(['game_session_id', 'participant_key']);
            $table->dropColumn('participant_key');
        });
    }
};