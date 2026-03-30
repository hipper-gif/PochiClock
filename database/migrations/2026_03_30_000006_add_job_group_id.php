<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->uuid('job_group_id')->nullable()->after('name');
            $table->foreign('job_group_id')->references('id')->on('job_groups')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->uuid('job_group_id')->nullable()->after('department_id');
            $table->foreign('job_group_id')->references('id')->on('job_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['job_group_id']);
            $table->dropColumn('job_group_id');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['job_group_id']);
            $table->dropColumn('job_group_id');
        });
    }
};
