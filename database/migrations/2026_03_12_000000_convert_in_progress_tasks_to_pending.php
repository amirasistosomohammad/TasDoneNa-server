<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Converts any tasks with status 'in_progress' to 'pending'.
     * The in_progress status has been removed from the application.
     */
    public function up(): void
    {
        DB::table('tasks')
            ->where('status', 'in_progress')
            ->update(['status' => 'pending']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse - we cannot reliably restore which tasks were in_progress
    }
};
