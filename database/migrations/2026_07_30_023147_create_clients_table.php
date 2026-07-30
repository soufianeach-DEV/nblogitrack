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
            Schema::create('clients', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->foreign('id')->references('id')->on('users')->onDelete('cascade');
            $table->string('company_name', 150);
            $table->string('vat_number', 30)->unique();
            $table->string('enterprise_number', 20)->nullable();
            $table->string('peppol_id', 30)->nullable();
            $table->string('billing_address', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('country', 60)->default('Belgique');
            $table->boolean('is_validated')->default(false);
            $table->string('business_sector', 100)->nullable();
            $table->decimal('credit_limit', 10, 2)->default(0);
            $table->string('payment_terms', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
