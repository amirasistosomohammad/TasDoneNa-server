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
        Schema::create('accomplishment_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('year');
            $table->integer('month'); // 1-12
            $table->string('status')->default('draft'); // draft, submitted, noted
            $table->foreignId('noted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('noted_at')->nullable();
            $table->json('tasks_summary'); // Tasks grouped by KRA with details
            $table->text('remarks')->nullable(); // Officer remarks
            $table->text('admin_remarks')->nullable(); // School Head notes/remarks
            $table->timestamps();

            // Ensure one report per user per month
            $table->unique(['user_id', 'year', 'month']);
            
            // Indexes for performance
            $table->index(['user_id', 'year', 'month']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accomplishment_reports');
    }
};
