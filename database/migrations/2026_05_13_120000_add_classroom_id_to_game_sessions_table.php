<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy migration name retained to avoid breaking databases that already recorded it.
    }

    public function down(): void
    {
        // Intentionally left blank. The schema change now lives in a properly named follow-up migration.
    }
};