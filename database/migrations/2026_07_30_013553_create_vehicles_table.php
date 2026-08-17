<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->string('registration', 20)->primary();
            $table->string('vin', 50)->unique();
            $table->string('vehicle_type', 50);
            $table->string('brand', 50);
            $table->string('model', 50);
            $table->string('euro_standard', 10)->nullable();
            $table->decimal('capacity_tonnes', 8, 2)->nullable();
            $table->decimal('capacity_volume', 8, 2)->nullable();
            $table->decimal('mileage', 10, 2)->default(0);
            $table->boolean('is_available')->default(true);
            $table->date('inspection_date')->nullable();
            $table->string('fuel_type', 30)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
