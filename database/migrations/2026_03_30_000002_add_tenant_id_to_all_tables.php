<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that need tenant_id.
     * sessions is included for tenant-aware session queries.
     */
    private array $tables = [
        'departments',
        'users',
        'attendances',
        'break_records',
        'work_rules',
        'sessions',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->uuid('tenant_id')->nullable()->after('id');
                if ($table !== 'sessions') {
                    $blueprint->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
                }
                $blueprint->index('tenant_id');
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if ($table !== 'sessions') {
                    $blueprint->dropForeign(['tenant_id']);
                }
                $blueprint->dropIndex(['tenant_id']);
                $blueprint->dropColumn('tenant_id');
            });
        }
    }
};
