<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('scope', ['SYSTEM', 'DEPARTMENT', 'USER']);
            $table->uuid('department_id')->nullable()->unique();
            $table->foreign('department_id')->references('id')->on('departments')->cascadeOnDelete();
            $table->uuid('user_id')->nullable()->unique();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('work_start_time', 5);
            $table->string('work_end_time', 5);
            $table->integer('default_break_minutes');
            $table->json('break_tiers')->nullable();
            $table->boolean('allow_multiple_clock_ins')->default(false);
            $table->integer('rounding_unit')->default(1);
            $table->string('clock_in_rounding', 5)->default('none');
            $table->string('clock_out_rounding', 5)->default('none');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_rules');
    }
};
