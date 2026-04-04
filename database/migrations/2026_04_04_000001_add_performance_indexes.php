<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['user_id', 'clock_in'], 'attendances_user_id_clock_in_index');
        });

        Schema::table('paid_leave_balances', function (Blueprint $table) {
            $table->index(['user_id', 'expiry_date'], 'paid_leave_balances_user_id_expiry_date_index');
        });

        Schema::table('shift_assignments', function (Blueprint $table) {
            $table->index('date', 'shift_assignments_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('attendances_user_id_clock_in_index');
        });

        Schema::table('paid_leave_balances', function (Blueprint $table) {
            $table->dropIndex('paid_leave_balances_user_id_expiry_date_index');
        });

        Schema::table('shift_assignments', function (Blueprint $table) {
            $table->dropIndex('shift_assignments_date_index');
        });
    }
};
