<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_positions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transport_order_id')->constrained()->cascadeOnDelete();

            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type', 6);
            $table->string('evenement', 20)->nullable();

            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);

            $table->unsignedInteger('precision_m')->nullable();

            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['transport_order_id', 'recorded_at']);
            $table->index(['type', 'recorded_at']);
        });

        Schema::table('transport_orders', function (Blueprint $table) {
            $table->boolean('suivi_direct')->default(false)->after('driver_id');
        });
    }

    public function down(): void
    {
        Schema::table('transport_orders', function (Blueprint $table) {
            $table->dropColumn('suivi_direct');
        });

        Schema::dropIfExists('shipment_positions');
    }
};
