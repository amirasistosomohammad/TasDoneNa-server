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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('mfo')->nullable();
            $table->string('kra')->nullable();
            $table->decimal('kra_weight', 5, 2)->nullable();
            $table->text('objective')->nullable();
            $table->json('movs')->nullable();
            $table->date('due_date')->nullable();
            $table->date('cutoff_date')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('priority')->default('medium');
            $table->date('timeline_start')->nullable();
            $table->date('timeline_end')->nullable();
            $table->json('performance_criteria')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
