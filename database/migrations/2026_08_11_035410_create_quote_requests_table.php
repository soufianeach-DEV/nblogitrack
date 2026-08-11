<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->nullable()->unique();

            $table->string('company_name', 150);
            $table->string('contact_name', 150);
            $table->string('email', 150);
            $table->string('phone', 20);
            $table->string('vat_number', 30)->nullable();
            $table->string('customer_type', 60);

            $table->string('pickup_address', 255);
            $table->decimal('pickup_lat', 10, 7);
            $table->decimal('pickup_lng', 10, 7);
            $table->string('delivery_address', 255);
            $table->decimal('delivery_lat', 10, 7);
            $table->decimal('delivery_lng', 10, 7);
            $table->string('delivery_country', 2);

            $table->date('pickup_date');
            $table->string('trip_type', 60);
            $table->string('frequency', 60);
            $table->string('date_flexibility', 60);

            $table->string('goods_type', 100);
            $table->integer('weight')->nullable();
            $table->string('volume', 60)->nullable();
            $table->string('vehicle_type', 80);
            $table->string('insurance_value', 60);

            $table->boolean('needs_tail_lift')->default(false);
            $table->boolean('is_hazardous')->default(false);
            $table->boolean('needs_express')->default(false);
            $table->boolean('needs_ecmr')->default(false);

            $table->text('special_instructions')->nullable();
            $table->string('status', 20)->default('PENDING');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_requests');
    }
};
