<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('backup_settings', function (Blueprint $table) {
            $table->id();
            $table->string('frequency')->default('off'); // off, daily, weekly
            $table->string('run_at_time')->default('02:00'); // HH:mm format
            $table->string('timezone')->default('Asia/Manila');
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->string('last_backup_path')->nullable();
            $table->timestamps();
        });

        // Insert default row
        DB::table('backup_settings')->insert([
            'frequency' => 'off',
            'run_at_time' => '02:00',
            'timezone' => 'Asia/Manila',
            'last_run_at' => null,
            'next_run_at' => null,
            'last_backup_path' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_settings');
    }
};
