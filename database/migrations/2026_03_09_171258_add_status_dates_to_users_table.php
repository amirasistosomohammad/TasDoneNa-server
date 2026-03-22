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
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('approval_remarks');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->timestamp('deactivated_at')->nullable()->after('deactivation_reason');
            $table->timestamp('activated_at')->nullable()->after('deactivated_at');
            $table->text('activation_remarks')->nullable()->after('activated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'approved_at',
                'rejected_at',
                'deactivated_at',
                'activated_at',
                'activation_remarks',
            ]);
        });
    }
};
