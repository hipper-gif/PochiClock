<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable()->index();
            $table->string('name');           // 早番, 中番, 遅番, etc.
            $table->string('color', 7)->default('#6366f1'); // hex color for calendar
            $table->string('start_time', 5);  // HH:MM
            $table->string('end_time', 5);    // HH:MM
            $table->integer('break_minutes')->default(60);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_templates');
    }
};
