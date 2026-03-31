<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_rules', function (Blueprint $table) {
            // Time before which clock-in is auto-rounded forward (e.g., "08:00")
            $table->string('early_clock_in_cutoff', 5)->nullable()->after('clock_out_rounding');
            // Optional second cutoff for PM session (e.g., "14:00" for delivery PM)
            $table->string('early_clock_in_cutoff_pm', 5)->nullable()->after('early_clock_in_cutoff');
        });
    }

    public function down(): void
    {
        Schema::table('work_rules', function (Blueprint $table) {
            $table->dropColumn(['early_clock_in_cutoff', 'early_clock_in_cutoff_pm']);
        });
    }
};
