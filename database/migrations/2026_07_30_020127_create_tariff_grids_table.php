<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tariff_grids', function (Blueprint $table) {
            $table->id();
            $table->string('label', 100);
            $table->string('zone', 60);
            $table->decimal('base_rate', 10, 2)->default(0);
            $table->decimal('price_per_km', 8, 4)->default(0);
            $table->decimal('price_per_kg', 8, 4)->default(0);
            $table->decimal('adr_coefficient', 5, 2)->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariff_grids');
    }
};
