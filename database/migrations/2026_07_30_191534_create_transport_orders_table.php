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
            Schema::create('transport_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('restrict');
            $table->date('created_date')->useCurrent();
            $table->string('pickup_address', 255);
            $table->string('delivery_address', 255);
            $table->decimal('weight', 10, 2)->nullable();
            $table->decimal('volume', 10, 2)->nullable();
            $table->string('goods_type', 100)->nullable();
            $table->boolean('is_hazardous')->default(false);
            $table->enum('status', ['PENDING', 'IN_PROGRESS', 'DELIVERED', 'CANCELLED'])->default('PENDING');
            $table->enum('priority', ['LOW', 'NORMAL', 'HIGH', 'URGENT'])->default('NORMAL');
            $table->string('tracking_number', 50)->unique();
            $table->text('special_instructions')->nullable();
            $table->date('requested_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();
            $table->decimal('estimated_cost', 12, 2)->nullable();
            $table->foreignId('tariff_grid_id')->nullable()->constrained('tariff_grids')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transport_orders');
    }
};
