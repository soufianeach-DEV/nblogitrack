<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
    $table->string('reference', 20)->unique();
    $table->date('issued_on');
    $table->date('due_on');
    $table->date('period_start');
    $table->date('period_end');
    $table->decimal('amount_excl_tax', 10, 2);
    $table->decimal('vat_rate', 5, 2);
    $table->decimal('vat_amount', 10, 2);
    $table->decimal('amount_incl_tax', 10, 2);
    $table->boolean('reverse_charge')->default(false);
    $table->string('status', 12)->default('DRAFT');
    $table->date('paid_on')->nullable();
    $table->string('payment_reference', 24)->nullable();
    $table->timestamps();

    $table->index(['client_id', 'status']);
});

DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_status_check
    CHECK (status IN ('DRAFT', 'SENT', 'PAID', 'OVERDUE'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
