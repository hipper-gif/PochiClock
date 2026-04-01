<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('hire_date')->nullable()->after('department_id');
            $table->decimal('weekly_work_days', 2, 1)->nullable()->after('hire_date');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['hire_date', 'weekly_work_days']);
        });
    }
};
