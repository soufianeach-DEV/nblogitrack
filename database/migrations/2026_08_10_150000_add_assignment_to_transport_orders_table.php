<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_orders', function (Blueprint $table) {
            $table->string('vehicle_registration', 20)->nullable()->after('tariff_grid_id');
            $table->unsignedBigInteger('driver_id')->nullable()->after('vehicle_registration');
            $table->timestamp('assigned_at')->nullable()->after('driver_id');

            $table->foreign('vehicle_registration')->references('registration')->on('vehicles')->nullOnDelete();
            $table->foreign('driver_id')->references('id')->on('drivers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transport_orders', function (Blueprint $table) {
            $table->dropForeign(['vehicle_registration']);
            $table->dropForeign(['driver_id']);
            $table->dropColumn(['vehicle_registration', 'driver_id', 'assigned_at']);
        });
    }
};
