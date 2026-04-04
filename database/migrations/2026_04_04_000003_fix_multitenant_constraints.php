<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A. UNIQUE制約をテナント複合ユニークに変更

        Schema::table('departments', function (Blueprint $table) {
            $table->dropUnique('departments_name_unique');
            $table->unique(['tenant_id', 'name'], 'departments_tenant_id_name_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_employee_number_unique');
            $table->unique(['tenant_id', 'employee_number'], 'users_tenant_id_employee_number_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->unique(['tenant_id', 'email'], 'users_tenant_id_email_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_kiosk_code_unique');
            $table->unique(['tenant_id', 'kiosk_code'], 'users_tenant_id_kiosk_code_unique');
        });

        Schema::table('job_groups', function (Blueprint $table) {
            $table->unique(['tenant_id', 'name'], 'job_groups_tenant_id_name_unique');
        });

        Schema::table('shift_templates', function (Blueprint $table) {
            $table->unique(['tenant_id', 'name'], 'shift_templates_tenant_id_name_unique');
        });

        // B. tenant_id FK を restrictOnDelete に統一

        $tablesToFix = [
            'comp_leaves',
            'paid_leaves',
            'paid_leave_balances',
            'shift_templates',
            'shift_assignments',
            'job_groups',
        ];

        foreach ($tablesToFix as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
                $table->foreign('tenant_id')
                    ->references('id')->on('tenants')
                    ->restrictOnDelete();
            });
        }

        // audit_logs: FK追加
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->restrictOnDelete();
        });

        // C. sessions テーブルから不要な tenant_id を削除
        if (Schema::hasColumn('sessions', 'tenant_id')) {
            Schema::table('sessions', function (Blueprint $table) {
                $table->dropIndex('sessions_tenant_id_index');
                $table->dropColumn('tenant_id');
            });
        }

        // D. work_rules から department_id と DEPARTMENT scope を削除

        DB::table('work_rules')
            ->where('scope', 'DEPARTMENT')
            ->update(['scope' => 'JOB_GROUP']);

        Schema::table('work_rules', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropUnique('work_rules_department_id_unique');
            $table->dropColumn('department_id');
        });

        DB::statement("ALTER TABLE work_rules MODIFY COLUMN scope ENUM('SYSTEM', 'JOB_GROUP', 'USER')");
    }

    public function down(): void
    {
        // D. Restore department_id and DEPARTMENT scope
        DB::statement("ALTER TABLE work_rules MODIFY COLUMN scope ENUM('SYSTEM', 'DEPARTMENT', 'JOB_GROUP', 'USER')");

        Schema::table('work_rules', function (Blueprint $table) {
            $table->uuid('department_id')->nullable()->after('scope');
            $table->foreign('department_id')->references('id')->on('departments')->cascadeOnDelete();
            $table->unique('department_id');
        });

        // C. Restore sessions tenant_id
        Schema::table('sessions', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->after('id');
            $table->index('tenant_id');
        });

        // B. Revert FK to nullOnDelete
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
        });

        $tablesToRevert = ['comp_leaves', 'paid_leaves', 'paid_leave_balances', 'shift_templates', 'shift_assignments', 'job_groups'];
        foreach ($tablesToRevert as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            });
        }

        // A. Restore original UNIQUE constraints
        Schema::table('shift_templates', function (Blueprint $table) {
            $table->dropUnique('shift_templates_tenant_id_name_unique');
        });
        Schema::table('job_groups', function (Blueprint $table) {
            $table->dropUnique('job_groups_tenant_id_name_unique');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_tenant_id_kiosk_code_unique');
            $table->unique('kiosk_code');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_tenant_id_email_unique');
            $table->unique('email');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_tenant_id_employee_number_unique');
            $table->unique('employee_number');
        });
        Schema::table('departments', function (Blueprint $table) {
            $table->dropUnique('departments_tenant_id_name_unique');
            $table->unique('name');
        });
    }
};
