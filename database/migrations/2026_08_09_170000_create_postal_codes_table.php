<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postal_codes', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2);
            $table->string('code', 16);
            $table->string('city', 180);
            $table->string('region', 100)->nullable();
            $table->decimal('lat', 9, 6);
            $table->decimal('lng', 9, 6);
            $table->index(['country_code', 'code']);
            $table->index(['country_code', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postal_codes');
    }
};
