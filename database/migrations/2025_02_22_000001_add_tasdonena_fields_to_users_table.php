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
            $table->enum('role', ['admin', 'officer'])->default('officer')->after('email');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->after('role');
            $table->text('rejection_reason')->nullable()->after('status');
            $table->string('employee_id')->nullable()->after('rejection_reason');
            $table->string('position')->nullable()->after('employee_id');
            $table->string('division')->nullable()->after('position');
            $table->string('school_name')->nullable()->after('division');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role',
                'status',
                'rejection_reason',
                'employee_id',
                'position',
                'division',
                'school_name',
            ]);
        });
    }
};
