<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->foreign('id')->references('id')->on('users')->onDelete('cascade');
            $table->string('license_number', 50)->unique();
            $table->enum('license_type', ['B', 'C', 'CE', 'C1', 'C1E']);
            $table->date('license_expiry');
            $table->boolean('is_available')->default(true);
            $table->boolean('adr_certified')->default(false);
            $table->date('medical_exam_date')->nullable();
            $table->decimal('daily_driving_hours', 4, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
