<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_rules', function (Blueprint $table) {
            $table->uuid('job_group_id')->nullable()->after('department_id');
            $table->foreign('job_group_id')->references('id')->on('job_groups')->nullOnDelete();
            $table->unique('job_group_id');
        });

        // Update scope enum: SYSTEM, DEPARTMENT, JOB_GROUP, USER
        // Keep DEPARTMENT temporarily for backward compatibility during migration
        DB::statement("ALTER TABLE work_rules MODIFY COLUMN scope ENUM('SYSTEM', 'DEPARTMENT', 'JOB_GROUP', 'USER')");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE work_rules MODIFY COLUMN scope ENUM('SYSTEM', 'DEPARTMENT', 'USER')");

        Schema::table('work_rules', function (Blueprint $table) {
            $table->dropForeign(['job_group_id']);
            $table->dropIndex('work_rules_job_group_id_unique');
            $table->dropColumn('job_group_id');
        });
    }
};
