<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('ADMIN', 'MANAGER', 'EMPLOYEE') NOT NULL DEFAULT 'EMPLOYEE'");
    }

    public function down(): void
    {
        // Move any MANAGER users back to EMPLOYEE before removing the enum value
        DB::table('users')->where('role', 'MANAGER')->update(['role' => 'EMPLOYEE']);
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('ADMIN', 'EMPLOYEE') NOT NULL DEFAULT 'EMPLOYEE'");
    }
};
