<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_name', 100);
            $table->string('reference', 50);
            $table->string('category', 12);
            $table->string('vehicle_registration', 20);
            $table->foreign('vehicle_registration')
                ->references('registration')->on('vehicles')
                ->onDelete('restrict');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('issued_on');
            $table->date('due_on');
            $table->decimal('liters', 8, 2)->nullable();
            $table->decimal('taxed_km', 8, 1)->nullable();
            $table->decimal('amount_excl_tax', 10, 2);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('vat_amount', 10, 2)->default(0);
            $table->decimal('amount_incl_tax', 10, 2);
            $table->boolean('vat_deductible')->default(false);
            $table->string('status', 12)->default('TO_PAY');
            $table->date('paid_on')->nullable();
            $table->timestamps();

            $table->unique(['supplier_name', 'reference']);
            $table->index(['vehicle_registration', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoices');
    }
};
