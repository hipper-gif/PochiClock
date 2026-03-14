<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('break_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('attendance_id');
            $table->foreign('attendance_id')->references('id')->on('attendances')->cascadeOnDelete();
            $table->dateTime('break_start');
            $table->dateTime('break_end')->nullable();
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->double('end_latitude')->nullable();
            $table->double('end_longitude')->nullable();
            $table->timestamps();

            $table->index('attendance_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('break_records');
    }
};
